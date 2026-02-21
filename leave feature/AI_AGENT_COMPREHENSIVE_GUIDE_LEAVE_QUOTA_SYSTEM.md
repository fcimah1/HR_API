# Comprehensive Guide: Leave Quota System - Days to Hours Conversion

## Table of Contents
1. [System Overview](#system-overview)
2. [The Problem](#the-problem)
3. [Database Structure](#database-structure)
4. [Three Types of Quota Storage](#three-types-of-quota-storage)
5. [The Complete Solution](#the-complete-solution)
6. [Code Implementation](#code-implementation)
7. [Data Flow Examples](#data-flow-examples)
8. [Testing Guide](#testing-guide)
9. [Key Concepts](#key-concepts)

---

## System Overview

This HR system manages employee leave requests with quotas that can be stored in either **days** or **hours**. The critical requirement is:

**When quota is stored in DAYS, it MUST be converted to HOURS based on the employee's shift configuration before:**
1. Saving to the database
2. Comparing against leave usage

### Why This Matters

Different employees work different hours per day based on their assigned office shift:
- Employee A: 8 hours/day
- Employee B: 10 hours/day  
- Employee C: 12 hours/day

If all three have "21 days" annual leave:
- Employee A should have: 21 × 8 = **168 hours**
- Employee B should have: 21 × 10 = **210 hours**
- Employee C should have: 21 × 12 = **252 hours**

---

## The Problem

### Original Bug
The system was treating quota values in **days** as if they were **hours**, causing incorrect balance validation.

**Example:**
- Employee has 21 days annual leave quota
- Employee works 8 hours/day
- System treated 21 days as **21 hours** ✗
- Should be: 21 days × 8 hours/day = **168 hours** ✓

### Impact
- Employees couldn't request their full entitled leave
- Balance checking rejected valid requests
- Quota display showed incorrect values

---

## Database Structure

### Key Tables

#### 1. `ci_erp_constants` - Leave Type Configuration
```sql
CREATE TABLE ci_erp_constants (
  constants_id INT PRIMARY KEY,
  company_id INT,
  type VARCHAR(100),           -- 'leave_type'
  category_name VARCHAR(200),  -- 'Annual Leave', 'Sick Leave', etc.
  field_one TEXT,              -- Serialized configuration (quota_assign, quota_unit, etc.)
  created_at VARCHAR(200)
);
```

**field_one structure (serialized PHP array):**
```php
[
  'is_quota' => 1,
  'quota_assign' => [
    0 => '21',   // Year 0: 21 (days or hours depending on quota_unit)
    1 => '21',   // Year 1: 21
    // ... up to year 49
  ],
  'quota_unit' => 'days',  // NEW: 'days' or 'hours' (if missing, assume hours)
  'policy_based' => 1,     // NEW: 1 if country policy, 0 otherwise
  'enable_leave_accrual' => 0,
  'is_carry' => 1,
  'carry_limit' => 168,
  // ... other settings
]
```

#### 2. `ci_erp_users_details` - Employee Configuration
```sql
CREATE TABLE ci_erp_users_details (
  user_id INT PRIMARY KEY,
  office_shift_id INT,         -- FK to ci_office_shifts
  assigned_hours TEXT,         -- Serialized: [leave_type_id => hours]
  leave_options TEXT,          -- Serialized: accrual configuration
  date_of_joining DATE
);
```

**assigned_hours structure:**
```php
[
  leave_type_id => hours_value  // ALWAYS stored in HOURS
]
// Example: [25 => 168, 26 => 72]
```

#### 3. `ci_office_shifts` - Shift Configuration
```sql
CREATE TABLE ci_office_shifts (
  office_shift_id INT PRIMARY KEY,
  shift_name VARCHAR(200),
  hours_per_day INT,           -- 8, 10, 12, etc.
  monday_in_time TIME,
  monday_out_time TIME,
  // ... other days
);
```

#### 4. `ci_leave_applications` - Leave Requests
```sql
CREATE TABLE ci_leave_applications (
  leave_id INT PRIMARY KEY,
  employee_id INT,
  leave_type_id INT,
  from_date DATE,
  to_date DATE,
  leave_hours DECIMAL(10,2),   -- ALWAYS stored in HOURS
  leave_year INT,
  status INT,                  -- 0=Pending, 1=Approved, 2=Rejected
  created_at DATETIME
);
```

#### 5. `ci_leave_country_policy` - Country-Based Policies
```sql
CREATE TABLE ci_leave_country_policy (
  policy_id INT PRIMARY KEY,
  country_code VARCHAR(2),     -- 'SA', 'EG', 'AE', etc.
  leave_type VARCHAR(50),      -- 'annual', 'sick', 'maternity', etc.
  entitlement_days INT,        -- Quota in DAYS
  service_years_min DECIMAL(5,2),
  service_years_max DECIMAL(5,2),
  is_active TINYINT
);
```

---

## Three Types of Quota Storage

### Type 1: Legacy Quotas (Old System)
**Storage**: `ci_erp_constants.field_one` → `quota_assign` array  
**Format**: **Hours** (e.g., 168, 105, 72)  
**Identifier**: No `quota_unit` field  
**Example**:
```php
'quota_assign' => [0 => '168', 1 => '168']  // Already in hours
```

### Type 2: New Quotas with quota_unit="days"
**Storage**: `ci_erp_constants.field_one` → `quota_assign` array  
**Format**: **Days** (e.g., 21, 30, 15)  
**Identifier**: Has `quota_unit = "days"` field  
**Example**:
```php
'quota_assign' => [0 => '21', 1 => '21'],  // In days
'quota_unit' => 'days'                      // Needs conversion!
```

### Type 3: Country Policy-Based (Newest System)
**Storage**: `ci_leave_country_policy` table  
**Format**: **Days** (e.g., 21, 30, 70)  
**Identifier**: Has `policy_based = 1` flag in `field_one`  
**Calculation**: Dynamic based on service years  
**Example**:
```sql
-- Saudi Arabia annual leave
country_code = 'SA'
leave_type = 'annual'
entitlement_days = 21  -- In days, needs conversion!
```

---

## The Complete Solution

### Three Critical Fixes

#### Fix #1: Convert Policy Entitlement to Hours (staff_details.php)
**Location**: `app/Views/erp/employees/staff_details.php` (Line ~2563)

**Problem**: Policy-based leave entitlements returned days but weren't converted to hours before saving.

**Solution**:
```php
// BEFORE (WRONG):
if ($isPolicyBased) {
    $LeavePolicy = new \App\Libraries\LeavePolicy();
    $iiiassigned_hours = $LeavePolicy->calculateEntitlement(...);  // Returns DAYS
    // Saved as days ✗
}

// AFTER (CORRECT):
if ($isPolicyBased) {
    $LeavePolicy = new \App\Libraries\LeavePolicy();
    $entitlement_days = $LeavePolicy->calculateEntitlement(...);  // Returns DAYS
    
    // Convert days to hours based on employee's shift
    $iiiassigned_hours = $entitlement_days * ($hours_per_day > 0 ? $hours_per_day : 8);
    // Now saved as hours ✓
}
```

#### Fix #2: Use Correct Variable (Leave.php Line 1312)
**Location**: `app/Controllers/Erp/Leave.php` (Line 1312)

**Problem**: Code used undefined variable `$no_of_days` instead of `$cfleave_hours`.

**Solution**:
```php
// BEFORE (WRONG):
$current_req = ($request_type == 'leave') ? $no_of_days : $leave_hours;  // $no_of_days undefined!

// AFTER (CORRECT):
$current_req = ($request_type == 'leave') ? $cfleave_hours : $leave_hours;  // ✓
```

#### Fix #3: Prevent Double Conversion (Leave.php Lines 1295-1380)
**Location**: `app/Controllers/Erp/Leave.php` (Lines 1295-1380)

**Problem**: Code was converting `assigned_hours` and `ileave_option` from days to hours, but they were already in hours.

**Solution**: Only convert country policy quotas; keep others as-is.

---


## Code Implementation

### File 1: app/Views/erp/employees/staff_details.php

**Purpose**: Display and save employee leave configuration

**Key Section** (Line ~2563):
```php
// For policy-based leave types, calculate entitlement from country policy
if ($isPolicyBased) {
    $LeavePolicy = new \App\Libraries\LeavePolicy();
    $entitlement_days = $LeavePolicy->calculateEntitlement($employee_detail['user_id'], 
        $LeavePolicy->getSystemLeaveType($employee_detail['company_id'], $ltype['constants_id']) ?? '');
    
    // ✓ FIX #1: Convert days to hours based on employee's shift
    $iiiassigned_hours = $entitlement_days * ($hours_per_day > 0 ? $hours_per_day : 8);
    
} elseif (isset($iassigned_hours[$ltype['constants_id']])) {
    // Assigned hours already exist
    $iiiassigned_hours = $iassigned_hours[$ltype['constants_id']];
    
    if ($iiiassigned_hours == 0) {
        if (isset($ieleave_option['quota_assign']) && $ieleave_option['is_quota'] == 1) {
            if (isset($ieleave_option['quota_assign'][$fyear_quota])) {
                $quota_val = $ieleave_option['quota_assign'][$fyear_quota];
                
                // Check if quota is in days or hours
                if(isset($ieleave_option['quota_unit']) && $ieleave_option['quota_unit'] === 'days') {
                    // Convert days to hours
                    $iiiassigned_hours = $quota_val * ($hours_per_day > 0 ? $hours_per_day : 8);
                } else {
                    // Already in hours
                    $iiiassigned_hours = $quota_val;
                }
            }
        }
    }
} else {
    // No assigned hours, check quota_assign
    if (isset($ieleave_option['quota_assign']) && $ieleave_option['is_quota'] == 1) {
        if (isset($ieleave_option['quota_assign'][$fyear_quota])) {
            $quota_val = $ieleave_option['quota_assign'][$fyear_quota];
            
            // Check if quota is in days or hours
            if(isset($ieleave_option['quota_unit']) && $ieleave_option['quota_unit'] === 'days') {
                // Convert days to hours
                $iiiassigned_hours = $quota_val * ($hours_per_day > 0 ? $hours_per_day : 8);
            } else {
                // Already in hours
                $iiiassigned_hours = $quota_val;
            }
        }
    }
}

// Display in form (will be saved to assigned_hours)
?>
<input type="text" name="assigned_hours[<?= $ltype['constants_id'] ?>]" 
       value="<?= $iiiassigned_hours ?>" />
<small><?= lang('Main.xin_assigned_hrs'); ?></small>
```

**Key Points**:
- `$hours_per_day` comes from employee's shift
- `$iiiassigned_hours` is ALWAYS in hours when saved
- Conversion happens BEFORE saving to database

---

### File 2: app/Controllers/Erp/Leave.php

**Purpose**: Process leave requests and validate quota balance

**Key Section 1** (Lines 1280-1290): Initialize Country Policy Check
```php
// Start Country Policy Integration
$LeavePolicy = new \App\Libraries\LeavePolicy();
$systemLeaveType = $LeavePolicy->getSystemLeaveType($icompany_id, $leave_type);
$policyEntitlement = 0;
$hasPolicy = false;

if ($systemLeaveType) {
    $policyEntitlement = $LeavePolicy->calculateEntitlement($luser_id, $systemLeaveType);
    if($policyEntitlement > 0) $hasPolicy = true;
}
// End Country Policy Integration
```

**Key Section 2** (Lines 1293-1325): Country Policy Balance Check
```php
if ($is_deducted == 1) {
    if ($hasPolicy) {
        // Country Policy Logic
        $days_per_year = $policyEntitlement;  // In DAYS
        
        // ✓ FIX #3a: Convert quota from days to hours based on employee's shift
        $hours_per_year = $LeavePolicy->convertQuotaDaysToHours($luser_id, $days_per_year);
        
        // Calculate Annual Usage (Approved + Pending) from DB
        $db = \Config\Database::connect();
        $builder = $db->table('ci_leave_applications');
        $builder->where('employee_id', $luser_id);
        $builder->where('leave_type_id', $leave_type);
        $builder->where('leave_year', $leave_year);
        $builder->whereIn('status', [0, 1]); // Pending(0), Approved(1)
        $usageQuery = $builder->selectSum('leave_hours')->get()->getRow();
        $tinc = $usageQuery->leave_hours ?? 0;  // In HOURS

        // ✓ FIX #2: Use correct variable for current request
        $current_req = ($request_type == 'leave') ? $cfleave_hours : $leave_hours;
        $fday_hours = $tinc + $current_req;
        
        // Calculate remaining balance in hours
        $dis_rem_leave = $hours_per_year - $tinc;

        if ($dis_rem_leave <= 0) {
            $Return['error'] = lang('Main.xin_hr_cant_appply_leave_quota_completed');
        } else if ($fday_hours > $hours_per_year) {
            // Format remaining balance for display: "X days (Y hours)"
            $remainingDisplay = $LeavePolicy->formatHoursBalanceDisplay($luser_id, $dis_rem_leave);
            $Return['error'] = lang('Main.xin_hr_cant_appply_morethan') . ' ' . $remainingDisplay;
        }
    }
```

**Key Section 3** (Lines 1326-1350): Assigned Hours Balance Check
```php
    elseif (isset($ifield_one['enable_leave_accrual']) && $ifield_one['enable_leave_accrual'] == 0) {
        // quota assignment year
        $ejoining_date = Time::parse($employee_detail['date_of_joining']);
        $curr_date    = Time::parse(date('Y-m-d'));
        $diff_year_quota = $ejoining_date->difference($curr_date);
        $fyear_quota = $diff_year_quota->getYears();

        if (isset($iassigned_hours[$leave_type])) {
            $qdays_per_year = $iassigned_hours[$leave_type];  // Already in HOURS
        } else {
            $qdays_per_year = 0;
        }
        
        // ✓ FIX #3b: assigned_hours already contains hours (converted when saved)
        // No need to convert again
        $hours_per_year = $qdays_per_year;  // Just assign, don't convert
        $days_per_year = $qdays_per_year;   // Keep for backward compatibility
        
        // Calculate remaining balance in hours
        $dis_rem_leave = $hours_per_year - $tinc;
        
        if ($dis_rem_leave < 0 || $dis_rem_leave == 0) {
            $Return['error'] = lang('Main.xin_hr_cant_appply_leave_quota_completed');
        } else if ($fday_hours > $hours_per_year) {
            // Format remaining balance for display
            $remainingDisplay = $LeavePolicy->formatHoursBalanceDisplay($luser_id, $dis_rem_leave);
            $Return['error'] = lang('Main.xin_hr_cant_appply_morethan') . $remainingDisplay;
        }
    }
```

**Key Section 4** (Lines 1351-1365): Accrual Balance Check
```php
    else {
        // Accrual-based quota
        $fyear_quota = 0;
        $accrual_disable_hr = 0;
        if (isset($ileave_option[$leave_type][$get_month])) {
            $days_per_year = $ileave_option[$leave_type][$get_month];  // Already in HOURS
        } else {
            $days_per_year = 0;
        }
        
        // ✓ FIX #3c: ileave_option already contains hours (accrual hours per month)
        // No need to convert
        $hours_per_year = $days_per_year;  // Just assign, don't convert
    }
}
```

---

### File 3: app/Libraries/LeavePolicy.php

**Purpose**: Centralized library for leave calculations

**Key Methods**:

#### 1. calculateEntitlement()
```php
/**
 * Calculate leave entitlement for an employee based on country policy
 * 
 * @param int $employeeId
 * @param string $systemLeaveType (annual, sick, maternity, hajj, emergency)
 * @return int|float Entitled days (IN DAYS, needs conversion to hours)
 */
public function calculateEntitlement($employeeId, $systemLeaveType)
{
    $employee = $this->usersModel->where('user_id', $employeeId)->first();
    if (!$employee) return 0;

    $companyId = $employee['company_id'];
    $countryCode = $this->getCompanyCountryCode($companyId);
    if (!$countryCode) return 0;

    $serviceYears = $this->calculateServiceYears($employeeId);
    
    $policy = $this->getApplicablePolicy($countryCode, $systemLeaveType, $serviceYears);
    return $policy ? $policy['entitlement_days'] : 0;  // Returns DAYS
}
```

#### 2. convertQuotaDaysToHours()
```php
/**
 * Convert quota from days to hours for a specific employee
 * 
 * @param int $employeeId
 * @param int|float $quotaDays
 * @return int|float Quota in hours
 */
public function convertQuotaDaysToHours($employeeId, $quotaDays)
{
    $hoursPerDay = $this->getEmployeeHoursPerDay($employeeId);
    return $quotaDays * $hoursPerDay;
}
```

#### 3. getEmployeeHoursPerDay()
```php
/**
 * Get employee's hours_per_day based on their assigned shift
 * 
 * @param int $employeeId
 * @return int Hours per day (default 8)
 */
public function getEmployeeHoursPerDay($employeeId)
{
    $staffDetails = $this->staffModel->where('user_id', $employeeId)->first();
    if (!$staffDetails || empty($staffDetails['office_shift_id'])) {
        return 8; // Default to 8 hours if no shift assigned
    }

    $ShiftModel = new \App\Models\ShiftModel();
    $shift = $ShiftModel->where('office_shift_id', $staffDetails['office_shift_id'])->first();
    
    if ($shift && (int)$shift['hours_per_day'] > 0) {
        return (int)$shift['hours_per_day'];
    }
    
    return 8; // Default
}
```

#### 4. formatHoursBalanceDisplay()
```php
/**
 * Formats leave balance in hours to "X days (Y hours)" format
 * 
 * @param int $employeeId
 * @param float $hoursBalance Leave balance in hours
 * @return string Formatted string "X days (Y hours)"
 */
public function formatHoursBalanceDisplay($employeeId, $hoursBalance)
{
    $shift = $this->getEmployeeShiftData($employeeId);
    
    $hoursPerDay = 8;
    if ($shift && isset($shift->hours_per_day)) {
        $hoursPerDay = (float)$shift->hours_per_day;
    }
    
    // Calculate days: hoursBalance ÷ hours_per_day
    $days = $hoursBalance / $hoursPerDay;
    
    // Format string: "{$days} days ({$hoursBalance} hours)"
    return sprintf('%.2f days (%.2f hours)', $days, $hoursBalance);
}
```

---


## Data Flow Examples

### Example 1: Policy-Based Leave Request (Full Day)

**Scenario**: Employee with 21 days annual leave, 8-hour shift, requests 5 days leave

#### Step 1: Policy Calculation
```
Country: Saudi Arabia
Leave Type: Annual Leave
Service Years: 2 years
Policy Entitlement: 21 days
```

#### Step 2: Display in Staff Details (staff_details.php)
```php
$isPolicyBased = true;
$entitlement_days = 21;  // From LeavePolicy::calculateEntitlement()
$hours_per_day = 8;      // From employee's shift

// ✓ FIX #1: Convert to hours
$iiiassigned_hours = 21 * 8 = 168 hours
```

#### Step 3: Save to Database
```sql
UPDATE ci_erp_users_details 
SET assigned_hours = 'a:1:{i:25;i:168;}'  -- Serialized: [25 => 168]
WHERE user_id = 123;
```

#### Step 4: Leave Request (Leave.php)
```php
// Employee requests 5 days leave
$start_date = '2026-02-10';
$end_date = '2026-02-14';
$request_type = 'leave';

// Calculate working days (excludes weekends/holidays)
$workingDays = $LeavePolicy->calculateWorkingDaysInRange(123, $start_date, $end_date);
// Result: 5 working days

// Convert to hours
$cfleave_hours = $LeavePolicy->convertDaysToHours(123, 5);
// Result: 5 * 8 = 40 hours
```

#### Step 5: Balance Check (Leave.php)
```php
// Country policy check
$hasPolicy = true;
$policyEntitlement = 21;  // days

// ✓ FIX #3a: Convert to hours
$hours_per_year = $LeavePolicy->convertQuotaDaysToHours(123, 21);
// Result: 21 * 8 = 168 hours

// Get usage from database
$tinc = 0;  // No previous leave

// ✓ FIX #2: Use correct variable
$current_req = $cfleave_hours;  // 40 hours
$fday_hours = 0 + 40 = 40 hours;

// Check balance
if (40 > 168) {  // false
    // Request APPROVED ✓
}

// Remaining balance
$dis_rem_leave = 168 - 0 = 168 hours;
// Display: "21.00 days (168.00 hours)"
```

#### Step 6: Save Leave Application
```sql
INSERT INTO ci_leave_applications (
    employee_id, leave_type_id, from_date, to_date, 
    leave_hours, leave_year, status
) VALUES (
    123, 25, '2026-02-10', '2026-02-14',
    40, 2026, 0  -- 40 hours, Pending
);
```

---

### Example 2: Hourly Permission Request

**Scenario**: Same employee requests permission from 8:00 AM to 11:00 AM

#### Step 1: Permission Request
```php
$request_type = 'permission';
$particular_date = '2026-02-15';
$clock_in = '8:00 AM';
$clock_out = '11:00 AM';
```

#### Step 2: Calculate Hours (Leave.php)
```php
// Convert to 24-hour format
$clock_in_24 = '08:00:00';
$clock_out_24 = '11:00:00';

// Calculate hours using LeavePolicy
$permissionResult = $LeavePolicy->calculateHourlyPermissionHours(
    123, '2026-02-15', $clock_in_24, $clock_out_24
);

// Result: 3 hours (no break time overlap)
$leave_hours = 3;
```

#### Step 3: Balance Check (Leave.php)
```php
// Country policy check
$hours_per_year = 168;  // Already converted

// Get usage from database (includes previous 5-day leave)
$tinc = 40;  // Previous leave

// ✓ FIX #2: Use correct variable
$current_req = $leave_hours;  // 3 hours
$fday_hours = 40 + 3 = 43 hours;

// Check balance
if (43 > 168) {  // false
    // Request APPROVED ✓
}

// Remaining balance
$dis_rem_leave = 168 - 40 = 128 hours;
// Display: "16.00 days (128.00 hours)"
```

#### Step 4: Save Permission
```sql
INSERT INTO ci_leave_applications (
    employee_id, leave_type_id, particular_date,
    leave_hours, leave_year, status
) VALUES (
    123, 25, '2026-02-15',
    3, 2026, 0  -- 3 hours, Pending
);
```

---

### Example 3: Employee with Different Shift (10 hours/day)

**Scenario**: Employee with 21 days annual leave, 10-hour shift

#### Step 1: Policy Calculation
```
Same policy: 21 days
Different shift: 10 hours/day
```

#### Step 2: Convert to Hours (staff_details.php)
```php
$entitlement_days = 21;
$hours_per_day = 10;  // Different shift!

// ✓ FIX #1: Convert to hours
$iiiassigned_hours = 21 * 10 = 210 hours  // More hours!
```

#### Step 3: Save to Database
```sql
UPDATE ci_erp_users_details 
SET assigned_hours = 'a:1:{i:25;i:210;}'  -- [25 => 210]
WHERE user_id = 456;
```

#### Step 4: Leave Request (5 days)
```php
$workingDays = 5;

// Convert to hours (different shift!)
$cfleave_hours = 5 * 10 = 50 hours  // More hours per day
```

#### Step 5: Balance Check
```php
$hours_per_year = 21 * 10 = 210 hours;
$tinc = 0;
$current_req = 50;
$fday_hours = 0 + 50 = 50 hours;

if (50 > 210) {  // false
    // Request APPROVED ✓
}

// Remaining balance
$dis_rem_leave = 210 - 0 = 210 hours;
// Display: "21.00 days (210.00 hours)"
```

**Key Point**: Same 5-day request, but different hours (50 vs 40) based on shift!

---

### Example 4: Legacy Quota (Already in Hours)

**Scenario**: Employee with legacy leave type (quota already in hours)

#### Step 1: Leave Type Configuration
```php
// ci_erp_constants.field_one
[
  'quota_assign' => [0 => '168'],  // Already in hours
  // No 'quota_unit' field (legacy)
]
```

#### Step 2: Display in Staff Details (staff_details.php)
```php
$quota_val = 168;  // From quota_assign

// Check quota_unit
if (isset($ieleave_option['quota_unit']) && $ieleave_option['quota_unit'] === 'days') {
    // Convert
} else {
    // ✓ Already in hours, no conversion
    $iiiassigned_hours = 168;
}
```

#### Step 3: Balance Check (Leave.php)
```php
// Not country policy, use assigned_hours
$qdays_per_year = $iassigned_hours[$leave_type];  // 168

// ✓ FIX #3b: Don't convert (already hours)
$hours_per_year = 168;  // Just assign

// Check balance normally
if ($fday_hours > 168) {
    // Reject
}
```

**Key Point**: Legacy quotas skip conversion because they're already in hours!

---


## Testing Guide

### Test Case 1: Policy-Based Leave (21 days, 8-hour shift)

**Setup**:
1. Create employee with 8-hour shift
2. Assign Saudi Arabia country policy
3. Leave type: Annual Leave (21 days)

**Test Steps**:
1. Navigate to employee details page
2. Check leave quota display
3. Request 5 days leave (Feb 10-14, 2026)
4. Verify balance check
5. Check database values

**Expected Results**:
- Staff details shows: **168 hours** (21 × 8)
- Database `assigned_hours`: **168**
- Leave request: **40 hours** (5 × 8)
- Balance check: **40 < 168** → APPROVED ✓
- Remaining: **128 hours** (16 days)
- Error message format: "16.00 days (128.00 hours)"

**SQL Verification**:
```sql
-- Check assigned hours
SELECT assigned_hours FROM ci_erp_users_details WHERE user_id = 123;
-- Expected: a:1:{i:25;i:168;}

-- Check leave application
SELECT leave_hours FROM ci_leave_applications WHERE employee_id = 123;
-- Expected: 40.00

-- Check balance
SELECT SUM(leave_hours) FROM ci_leave_applications 
WHERE employee_id = 123 AND leave_type_id = 25 AND status IN (0,1);
-- Expected: 40.00
```

---

### Test Case 2: Policy-Based Leave (21 days, 10-hour shift)

**Setup**:
1. Create employee with 10-hour shift
2. Assign Saudi Arabia country policy
3. Leave type: Annual Leave (21 days)

**Test Steps**:
1. Navigate to employee details page
2. Check leave quota display
3. Request 5 days leave
4. Verify balance check

**Expected Results**:
- Staff details shows: **210 hours** (21 × 10)
- Database `assigned_hours`: **210**
- Leave request: **50 hours** (5 × 10)
- Balance check: **50 < 210** → APPROVED ✓
- Remaining: **160 hours** (16 days)

---

### Test Case 3: Hourly Permission (3 hours)

**Setup**:
1. Employee with 168 hours available
2. No previous leave taken

**Test Steps**:
1. Request permission from 8:00 AM to 11:00 AM
2. Verify hours calculation
3. Check balance

**Expected Results**:
- Calculated hours: **3.00**
- Balance check: **3 < 168** → APPROVED ✓
- Remaining: **165 hours**

---

### Test Case 4: Hourly Permission with Break Time

**Setup**:
1. Employee with 8-hour shift
2. Shift has lunch break: 12:00 PM - 1:00 PM

**Test Steps**:
1. Request permission from 8:00 AM to 2:00 PM
2. Verify break time subtraction

**Expected Results**:
- Time difference: **6 hours** (14:00 - 8:00)
- Break overlap: **1 hour** (12:00 - 13:00)
- Calculated hours: **5.00** (6 - 1)
- Balance check: **5 < 168** → APPROVED ✓

---

### Test Case 5: Exceed Quota

**Setup**:
1. Employee with 168 hours available
2. Already used 160 hours

**Test Steps**:
1. Request 2 days leave (16 hours)
2. Verify rejection

**Expected Results**:
- Available: **168 hours**
- Used: **160 hours**
- Remaining: **8 hours** (1 day)
- Request: **16 hours** (2 days)
- Balance check: **176 > 168** → REJECTED ✗
- Error message: "Cannot apply more than 1.00 days (8.00 hours)"

---

### Test Case 6: Legacy Quota (Already in Hours)

**Setup**:
1. Employee with legacy leave type
2. Quota: 168 hours (no quota_unit field)

**Test Steps**:
1. Check staff details display
2. Request 5 days leave
3. Verify no double conversion

**Expected Results**:
- Staff details shows: **168 hours** (no conversion)
- Database `assigned_hours`: **168**
- Leave request: **40 hours**
- Balance check: **40 < 168** → APPROVED ✓
- **No double conversion** (168 × 8 = 1,344) ✓

---

### Test Case 7: Weekend Exclusion

**Setup**:
1. Employee with 8-hour shift
2. Shift: Monday-Friday (Saturday-Sunday off)

**Test Steps**:
1. Request leave from Friday to Tuesday (5 calendar days)
2. Verify working days calculation

**Expected Results**:
- Calendar days: **5** (Fri, Sat, Sun, Mon, Tue)
- Weekends excluded: **2** (Sat, Sun)
- Working days: **3** (Fri, Mon, Tue)
- Calculated hours: **24** (3 × 8)
- Balance check: **24 < 168** → APPROVED ✓

---

### Test Case 8: Holiday Exclusion

**Setup**:
1. Employee with 8-hour shift
2. Company holiday: Wednesday, Feb 12, 2026

**Test Steps**:
1. Request leave from Monday to Friday (5 calendar days)
2. Verify holiday exclusion

**Expected Results**:
- Calendar days: **5** (Mon, Tue, Wed, Thu, Fri)
- Holidays excluded: **1** (Wed)
- Working days: **4** (Mon, Tue, Thu, Fri)
- Calculated hours: **32** (4 × 8)
- Balance check: **32 < 168** → APPROVED ✓

---

### Test Case 9: Multiple Leave Types

**Setup**:
1. Employee with multiple leave types:
   - Annual: 21 days (168 hours for 8h shift)
   - Sick: 30 days (240 hours for 8h shift)

**Test Steps**:
1. Request 5 days annual leave
2. Request 3 days sick leave
3. Verify separate balance tracking

**Expected Results**:
- Annual leave used: **40 hours** (5 × 8)
- Annual remaining: **128 hours** (16 days)
- Sick leave used: **24 hours** (3 × 8)
- Sick remaining: **216 hours** (27 days)
- **Separate tracking** ✓

---

### Test Case 10: Accrual-Based Quota

**Setup**:
1. Employee with accrual leave type
2. Accrual: 14 hours per month
3. Employee worked 12 months

**Test Steps**:
1. Check accumulated quota
2. Request 5 days leave

**Expected Results**:
- Accumulated: **168 hours** (14 × 12)
- Leave request: **40 hours** (5 × 8)
- Balance check: **40 < 168** → APPROVED ✓
- Remaining: **128 hours**

---

## Key Concepts

### Concept 1: Quota Storage Units

**Rule**: Quota can be stored in either **days** or **hours**

**Identification**:
- **Days**: Has `quota_unit = "days"` field OR is country policy
- **Hours**: No `quota_unit` field (legacy) OR `quota_unit = "hours"`

**Conversion Required**:
- **Days → Hours**: YES, multiply by `hours_per_day`
- **Hours → Hours**: NO, use as-is

---

### Concept 2: Three Quota Sources

| Source | Storage | Format | Conversion Needed |
|--------|---------|--------|-------------------|
| Country Policy | `ci_leave_country_policy` | Days | ✓ YES |
| Assigned Hours | `ci_erp_users_details.assigned_hours` | Hours | ✗ NO |
| Accrual | `ci_erp_users_details.leave_options` | Hours | ✗ NO |

**Key Point**: Only country policy needs conversion; others are already in hours!

---

### Concept 3: Shift-Aware Calculations

**Rule**: All hour calculations must use employee's `hours_per_day` from their shift

**Examples**:
- 8-hour shift: 1 day = 8 hours
- 10-hour shift: 1 day = 10 hours
- 12-hour shift: 1 day = 12 hours

**Impact**:
- Same quota in days → Different hours based on shift
- Same leave request in days → Different hours based on shift

---

### Concept 4: Working Days Calculation

**Rule**: Only count days that are:
1. Working days in employee's shift (has `in_time`)
2. NOT company holidays

**Example**:
```
Request: Feb 10-14 (5 calendar days)
Shift: Monday-Friday (Sat-Sun off)
Holiday: Feb 12 (Wednesday)

Calculation:
- Feb 10 (Mon): Working day ✓
- Feb 11 (Tue): Working day ✓
- Feb 12 (Wed): Holiday ✗
- Feb 13 (Thu): Working day ✓
- Feb 14 (Fri): Working day ✓

Result: 4 working days
Hours: 4 × 8 = 32 hours
```

---

### Concept 5: Balance Tracking

**Rule**: All leave usage is tracked in **hours** in `ci_leave_applications.leave_hours`

**Balance Formula**:
```
Available = Quota (in hours)
Used = SUM(leave_hours) WHERE status IN (0, 1)  -- Pending + Approved
Remaining = Available - Used
```

**Validation**:
```
Current Request + Used <= Available
```

---

### Concept 6: Backward Compatibility

**Rule**: Existing data is NOT modified; fixes apply to new calculations only

**Guarantees**:
- ✓ Existing `assigned_hours` values unchanged
- ✓ Existing `leave_applications` records unchanged
- ✓ No data migration required
- ✓ Legacy leave types continue to work

---

### Concept 7: Error Message Format

**Rule**: Show balance in both days and hours for clarity

**Format**: `"X.XX days (Y.YY hours)"`

**Examples**:
- 8-hour shift, 128 hours: "16.00 days (128.00 hours)"
- 10-hour shift, 160 hours: "16.00 days (160.00 hours)"
- 8-hour shift, 8 hours: "1.00 days (8.00 hours)"

**Implementation**:
```php
$remainingDisplay = $LeavePolicy->formatHoursBalanceDisplay($employeeId, $hoursBalance);
// Returns: "16.00 days (128.00 hours)"
```

---

## Summary for AI Agents

### Critical Understanding Points

1. **Quota Storage**: Can be in days OR hours; check `quota_unit` field
2. **Conversion Rule**: Days → Hours = Days × Employee's `hours_per_day`
3. **Three Sources**: Country policy (days), Assigned hours (hours), Accrual (hours)
4. **Only Convert Once**: Country policy at balance check; others already converted when saved
5. **Shift-Aware**: All calculations use employee's specific shift hours
6. **Hours Tracking**: All usage tracked in hours in database
7. **Balance Formula**: Available (hours) - Used (hours) = Remaining (hours)

### Common Pitfalls to Avoid

❌ **Don't**: Convert assigned_hours from days to hours (already hours)  
❌ **Don't**: Convert accrual from days to hours (already hours)  
❌ **Don't**: Use undefined variables like `$no_of_days`  
❌ **Don't**: Compare days against hours  
❌ **Don't**: Assume all employees have same hours per day  

✓ **Do**: Convert country policy from days to hours  
✓ **Do**: Use employee's shift `hours_per_day` for all calculations  
✓ **Do**: Track everything in hours in database  
✓ **Do**: Format display as "X days (Y hours)"  
✓ **Do**: Validate shift assignment before processing  

### Files to Understand

1. **app/Views/erp/employees/staff_details.php**: Where quotas are displayed and saved
2. **app/Controllers/Erp/Leave.php**: Where leave requests are processed and validated
3. **app/Libraries/LeavePolicy.php**: Centralized calculation methods
4. **Database Tables**: 
   - `ci_erp_constants`: Leave type configuration
   - `ci_erp_users_details`: Employee quota storage
   - `ci_office_shifts`: Shift configuration
   - `ci_leave_applications`: Leave usage tracking
   - `ci_leave_country_policy`: Country-based policies

---

## Quick Reference

### Conversion Formula
```
Hours = Days × Employee's hours_per_day
```

### Balance Check Formula
```
if (Used + Current Request > Available) {
    REJECT
} else {
    APPROVE
}
```

### Display Format
```
"X.XX days (Y.YY hours)"
```

### Key Variables
- `$policyEntitlement`: Days from country policy
- `$hours_per_year`: Quota in hours (after conversion)
- `$cfleave_hours`: Current request in hours (full day leave)
- `$leave_hours`: Current request in hours (hourly permission)
- `$tinc`: Total used hours from database
- `$fday_hours`: Total hours including current request
- `$dis_rem_leave`: Remaining balance in hours

---

**End of Comprehensive Guide**

This document provides complete understanding of the leave quota system's days-to-hours conversion feature. Use it as reference when working with leave management functionality.

