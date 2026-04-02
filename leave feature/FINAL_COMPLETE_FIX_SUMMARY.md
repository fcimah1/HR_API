# Final Complete Fix Summary - Leave Quota Hours Conversion

## Problem Overview

The leave quota system was not properly converting quotas from **days** to **hours** based on employee shift configuration, causing incorrect balance validation.

### Example of the Bug:
- Employee has 21 days annual leave quota
- Employee works 8 hours/day
- System treated 21 days as 21 hours ✗
- Should be: 21 days × 8 hours/day = 168 hours ✓

## Root Causes Identified

### Issue #1: Policy-Based Leave Entitlement Not Converted (staff_details.php)
**Location**: `app/Views/erp/employees/staff_details.php` (Line ~2563)

**Problem**: When displaying policy-based leave entitlements, the system called `LeavePolicy::calculateEntitlement()` which returns **days**, but didn't convert to hours before saving to `assigned_hours`.

**Result**: Database stored 21 instead of 168 for an 8-hour shift employee.

### Issue #2: Undefined Variable in Balance Checking (Leave.php)
**Location**: `app/Controllers/Erp/Leave.php` (Line 1312)

**Problem**: Code used undefined variable `$no_of_days` instead of `$cfleave_hours` when checking country policy balance.

```php
// WRONG:
$current_req = ($request_type == 'leave') ? $no_of_days : $leave_hours;  // $no_of_days undefined!

// CORRECT:
$current_req = ($request_type == 'leave') ? $cfleave_hours : $leave_hours;
```

### Issue #3: Incorrect Conversion of Already-Converted Quotas (Leave.php)
**Location**: `app/Controllers/Erp/Leave.php` (Lines 1333-1365)

**Problem**: The code was converting `assigned_hours` and `ileave_option` from days to hours, but these values were **already in hours** (converted when saved).

**Result**: Double conversion - 168 hours × 8 = 1,344 hours ✗

## Complete Solution - 3 Fixes

### Fix #1: Convert Policy Entitlement to Hours (staff_details.php)

**File**: `app/Views/erp/employees/staff_details.php`  
**Line**: ~2563

**Before**:
```php
if ($isPolicyBased) {
    $LeavePolicy = new \App\Libraries\LeavePolicy();
    $iiiassigned_hours = $LeavePolicy->calculateEntitlement(...);  // Returns DAYS
    // Saved as days ✗
}
```

**After**:
```php
if ($isPolicyBased) {
    $LeavePolicy = new \App\Libraries\LeavePolicy();
    $entitlement_days = $LeavePolicy->calculateEntitlement(...);  // Returns DAYS
    
    // Convert days to hours based on employee's shift
    $iiiassigned_hours = $entitlement_days * ($hours_per_day > 0 ? $hours_per_day : 8);
    // Now saved as hours ✓
}
```

**Impact**: Policy-based leave quotas are now correctly stored as hours in the database.

### Fix #2: Use Correct Variable for Current Request (Leave.php)

**File**: `app/Controllers/Erp/Leave.php`  
**Line**: 1312

**Before**:
```php
// Determine current request amount (days or hours based on type)
// $no_of_days is calculated for 'leave', $leave_hours for 'permission'
$current_req = ($request_type == 'leave') ? $no_of_days : $leave_hours;  // ✗ $no_of_days undefined!
$fday_hours = $tinc + $current_req;
```

**After**:
```php
// Determine current request amount (hours for both leave and permission)
// For 'leave': $cfleave_hours contains calculated hours from working days
// For 'permission': $leave_hours contains calculated hours from time range
$current_req = ($request_type == 'leave') ? $cfleave_hours : $leave_hours;  // ✓ Correct variable
$fday_hours = $tinc + $current_req;
```

**Impact**: Balance checking now correctly uses the calculated leave hours for the current request.

### Fix #3: Don't Double-Convert Already-Converted Quotas (Leave.php)

**File**: `app/Controllers/Erp/Leave.php`  
**Lines**: 1295-1380

#### Fix 3a: Country Policy - Convert Days to Hours ✓

```php
if ($hasPolicy) {
    // Country Policy Logic
    $days_per_year = $policyEntitlement;  // 21 days from policy
    
    // Convert quota from days to hours based on employee's shift
    $hours_per_year = $LeavePolicy->convertQuotaDaysToHours($luser_id, $days_per_year);
    // 21 days × 8 hours/day = 168 hours ✓
    
    // Calculate Annual Usage (Approved + Pending) from DB
    $builder = $db->table('ci_leave_applications');
    $builder->where('employee_id', $luser_id);
    $builder->where('leave_type_id', $leave_type);
    $builder->where('leave_year', $leave_year);
    $builder->whereIn('status', [0, 1]);
    $usageQuery = $builder->selectSum('leave_hours')->get()->getRow();
    $tinc = $usageQuery->leave_hours ?? 0;
    
    // Use correct variable for current request
    $current_req = ($request_type == 'leave') ? $cfleave_hours : $leave_hours;
    $fday_hours = $tinc + $current_req;
    
    // Calculate remaining balance in hours
    $dis_rem_leave = $hours_per_year - $tinc;
    
    if ($dis_rem_leave <= 0) {
        $Return['error'] = lang('Main.xin_hr_cant_appply_leave_quota_completed');
    } else if ($fday_hours > $hours_per_year) {
        $remainingDisplay = $LeavePolicy->formatHoursBalanceDisplay($luser_id, $dis_rem_leave);
        $Return['error'] = lang('Main.xin_hr_cant_appply_morethan') . ' ' . $remainingDisplay;
    }
}
```

#### Fix 3b: Assigned Hours - Don't Convert (Already Hours) ✓

```php
elseif (isset($ifield_one['enable_leave_accrual']) && $ifield_one['enable_leave_accrual'] == 0) {
    if (isset($iassigned_hours[$leave_type])) {
        $qdays_per_year = $iassigned_hours[$leave_type];  // Already in HOURS
    } else {
        $qdays_per_year = 0;
    }
    
    // assigned_hours already contains hours (converted when saved in staff_details.php)
    // No need to convert again
    $hours_per_year = $qdays_per_year;  // Just assign, don't convert ✓
    $days_per_year = $qdays_per_year;   // Keep for backward compatibility
    
    // Calculate remaining balance in hours
    $dis_rem_leave = $hours_per_year - $tinc;
    
    if ($dis_rem_leave < 0 || $dis_rem_leave == 0) {
        $Return['error'] = lang('Main.xin_hr_cant_appply_leave_quota_completed');
    } else if ($fday_hours > $hours_per_year) {
        $remainingDisplay = $LeavePolicy->formatHoursBalanceDisplay($luser_id, $dis_rem_leave);
        $Return['error'] = lang('Main.xin_hr_cant_appply_morethan') . $remainingDisplay;
    }
}
```

#### Fix 3c: Accrual - Don't Convert (Already Hours) ✓

```php
else {
    if (isset($ileave_option[$leave_type][$get_month])) {
        $days_per_year = $ileave_option[$leave_type][$get_month];  // Already in HOURS
    } else {
        $days_per_year = 0;
    }
    
    // ileave_option already contains hours (accrual hours per month)
    // No need to convert
    $hours_per_year = $days_per_year;  // Just assign, don't convert ✓
}
```

**Impact**: Assigned hours and accrual quotas are no longer double-converted, preventing inflated quota values.

## Complete Data Flow Example

### Scenario: Employee with 21 Days Annual Leave (8-hour shift)

#### Step 1: Policy Calculation
```
Saudi Arabia Country Policy → 21 days annual leave
```

#### Step 2: Display in Staff Details (Fix #1)
```php
$entitlement_days = 21;  // From LeavePolicy::calculateEntitlement()
$hours_per_day = 8;      // From employee's shift
$iiiassigned_hours = 21 × 8 = 168 hours  // ✓ Converted!
```

#### Step 3: Save to Database
```
ci_erp_users_details.assigned_hours = serialize([leave_type_id => 168])
// ✓ Stored as 168 hours
```

#### Step 4: Leave Request - Full Day (5 days)
```php
// Employee requests 5 days leave
$workingDays = 5;  // From LeavePolicy::calculateWorkingDaysInRange()
$cfleave_hours = 5 × 8 = 40 hours;  // From LeavePolicy::convertDaysToHours()

// Check balance (Fix #2 & #3)
$hasPolicy = true;
$policyEntitlement = 21;  // days from policy
$hours_per_year = 21 × 8 = 168 hours;  // ✓ Convert to hours (Fix #3a)

// Usage from database
$tinc = 0;  // No previous leave

// Current request (Fix #2)
$current_req = $cfleave_hours;  // 40 hours ✓ Correct variable
$fday_hours = 0 + 40 = 40 hours;

// Check if request exceeds quota
if (40 > 168) {  // false
    // Request approved ✓
}
```

#### Step 5: Leave Request - Hourly Permission (3 hours)
```php
// Employee requests permission from 8:00 AM to 11:00 AM
$leave_hours = 3;  // From LeavePolicy::calculateHourlyPermissionHours()

// Check balance (Fix #2 & #3)
$hasPolicy = true;
$hours_per_year = 168 hours;  // Already converted

// Usage from database
$tinc = 40;  // Previous 5-day leave

// Current request (Fix #2)
$current_req = $leave_hours;  // 3 hours ✓ Correct variable
$fday_hours = 40 + 3 = 43 hours;

// Check if request exceeds quota
if (43 > 168) {  // false
    // Request approved ✓
}
```

## Files Modified

### 1. app/Views/erp/employees/staff_details.php
- **Line ~2563**: Convert policy-based entitlement from days to hours before saving
- **Impact**: Ensures `assigned_hours` stores hours, not days

### 2. app/Controllers/Erp/Leave.php
- **Line 1312**: Use `$cfleave_hours` instead of undefined `$no_of_days`
- **Lines 1295-1325**: Convert country policy quota from days to hours
- **Lines 1333-1350**: Keep assigned_hours as-is (already hours)
- **Lines 1351-1365**: Keep accrual as-is (already hours)
- **Impact**: Ensures balance checking compares hours vs hours correctly

### 3. app/Libraries/LeavePolicy.php
- **No changes needed**: Methods already work correctly
- `convertQuotaDaysToHours()`: Converts days to hours
- `formatHoursBalanceDisplay()`: Formats for display

## Testing Verification

### Test Case 1: Policy-Based Leave (21 days, 8-hour shift)
1. Create employee with 8-hour shift
2. Assign Saudi Arabia country policy
3. View staff details page
4. **Expected**: Annual leave shows 168 hours (21 × 8) ✓
5. **Expected**: Database stores 168 in assigned_hours ✓
6. Request 5 days (40 hours) leave
7. **Expected**: Request approved (40 < 168) ✓
8. **Expected**: Remaining balance: 128 hours (16 days) ✓

### Test Case 2: Policy-Based Leave (21 days, 10-hour shift)
1. Create employee with 10-hour shift
2. Assign Saudi Arabia country policy
3. View staff details page
4. **Expected**: Annual leave shows 210 hours (21 × 10) ✓
5. **Expected**: Database stores 210 in assigned_hours ✓
6. Request 5 days (50 hours) leave
7. **Expected**: Request approved (50 < 210) ✓
8. **Expected**: Remaining balance: 160 hours (16 days) ✓

### Test Case 3: Hourly Permission (3 hours)
1. Employee with 168 hours available
2. Request permission from 8:00 AM to 11:00 AM
3. **Expected**: System calculates 3 hours ✓
4. **Expected**: Request approved (3 < 168) ✓
5. **Expected**: Remaining balance: 165 hours ✓

### Test Case 4: Existing Employee (Legacy Data)
1. Employee already has 168 hours in assigned_hours
2. Request 5 days (40 hours) leave
3. **Expected**: Request approved (40 < 168) ✓
4. **Expected**: No data migration needed ✓

## Summary of All Fixes

| Issue | Location | Problem | Solution | Status |
|-------|----------|---------|----------|--------|
| Policy entitlement not converted | staff_details.php:2563 | Saved days instead of hours | Convert days to hours when saving | ✅ Fixed |
| Undefined variable | Leave.php:1312 | Used `$no_of_days` (undefined) | Use `$cfleave_hours` instead | ✅ Fixed |
| Double conversion | Leave.php:1333-1350 | Converted already-converted hours | Don't convert assigned_hours | ✅ Fixed |
| Double conversion | Leave.php:1351-1365 | Converted already-converted hours | Don't convert accrual | ✅ Fixed |
| Country policy conversion | Leave.php:1295-1325 | Days compared to hours | Convert days to hours | ✅ Fixed |

## Backward Compatibility

✅ **Existing data unchanged**: Employees with hours already in assigned_hours continue to work  
✅ **No migration needed**: Fix applies to new calculations only  
✅ **Legacy leave types**: Continue to work as before (already in hours)  
✅ **New policy types**: Now correctly convert days to hours  

## Conclusion

All three issues have been identified and fixed:
1. ✅ Policy-based entitlements now convert days to hours when saving
2. ✅ Balance checking uses correct variable (`$cfleave_hours` not `$no_of_days`)
3. ✅ Only country policy quotas are converted; assigned_hours and accrual are kept as-is

The system now correctly handles all quota types with shift-aware hour calculations.
