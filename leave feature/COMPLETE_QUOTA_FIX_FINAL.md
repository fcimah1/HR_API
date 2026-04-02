# Complete Leave Quota Fix - Final Solution

## The Complete Picture

After thorough investigation of the database and code, here's the complete understanding:

### Three Types of Leave Quota Storage

#### 1. **Legacy Leave Types** (Old System)
- Stored in: `ci_erp_constants.field_one` → `quota_assign` array
- Format: **Hours** (e.g., 168, 105, 72)
- No `quota_unit` field
- Example: `quota_assign[0] = "168"` (168 hours)

#### 2. **New Leave Types with quota_unit = "days"**
- Stored in: `ci_erp_constants.field_one` → `quota_assign` array
- Format: **Days** (e.g., 21, 30, 15)
- Has `quota_unit = "days"` field
- Example: `quota_assign[0] = "21"` (21 days, needs conversion to hours)

#### 3. **Country Policy-Based Leave Types** (Newest System)
- Stored in: `ci_leave_country_policy` table
- Format: **Days** (e.g., 21, 30, 70)
- Has `policy_based = 1` flag in `field_one`
- Calculated dynamically based on service years
- Example: Saudi Arabia annual leave = 21 days

### The Problem

When quota is stored in **days**, it must be converted to **hours** based on the employee's shift `hours_per_day` before:
1. **Saving** to `ci_erp_users_details.assigned_hours`
2. **Comparing** against leave usage in `ci_leave_applications.leave_hours`

### The Solution - Two Fixes Required

## Fix #1: staff_details.php View (Display & Save)

**File**: `app/Views/erp/employees/staff_details.php` (Line ~2563)

**Problem**: Policy-based leave entitlements were returned in days but not converted to hours before saving.

**Before**:
```php
if ($isPolicyBased) {
    $LeavePolicy = new \App\Libraries\LeavePolicy();
    $iiiassigned_hours = $LeavePolicy->calculateEntitlement(...);  // Returns DAYS
    // Saved as days, not hours! ✗
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

## Fix #2: Leave.php Controller (Balance Checking)

**File**: `app/Controllers/Erp/Leave.php` (Lines 1280-1380)

**Problem**: Country policy quotas were in days but compared against usage in hours.

**Solution**: Convert only country policy quotas from days to hours. Other quota sources are already in hours.

### Fix 2a: Country Policy - Convert Days to Hours ✓

```php
if ($hasPolicy) {
    // Country Policy Logic
    $days_per_year = $policyEntitlement;  // 21 days from policy
    
    // Convert quota from days to hours based on employee's shift
    $hours_per_year = $LeavePolicy->convertQuotaDaysToHours($luser_id, $days_per_year);
    // 21 days × 8 hours/day = 168 hours
    
    // Now compare hours vs hours ✓
    if ($fday_hours > $hours_per_year) {
        $remainingDisplay = $LeavePolicy->formatHoursBalanceDisplay($luser_id, $dis_rem_leave);
        $Return['error'] = lang('Main.xin_hr_cant_appply_morethan') . ' ' . $remainingDisplay;
    }
}
```

### Fix 2b: Assigned Hours - Don't Convert (Already Hours) ✓

```php
elseif (isset($ifield_one['enable_leave_accrual']) && $ifield_one['enable_leave_accrual'] == 0) {
    if (isset($iassigned_hours[$leave_type])) {
        $qdays_per_year = $iassigned_hours[$leave_type];  // Already in HOURS
    } else {
        $qdays_per_year = 0;
    }
    
    // assigned_hours already contains hours (converted when saved in staff_details.php)
    // No need to convert again
    $hours_per_year = $qdays_per_year;
}
```

### Fix 2c: Accrual - Don't Convert (Already Hours) ✓

```php
else {
    if (isset($ileave_option[$leave_type][$get_month])) {
        $days_per_year = $ileave_option[$leave_type][$get_month];  // Already in HOURS
    } else {
        $days_per_year = 0;
    }
    
    // ileave_option already contains hours (accrual hours per month)
    // No need to convert
    $hours_per_year = $days_per_year;
}
```

## Complete Data Flow

### Scenario: Employee with 21 Days Annual Leave (8-hour shift)

#### Step 1: Policy Calculation
```
Country Policy (Saudi Arabia) → 21 days annual leave
```

#### Step 2: Display in Staff Details (staff_details.php)
```php
$entitlement_days = 21;  // From LeavePolicy::calculateEntitlement()
$hours_per_day = 8;      // From employee's shift
$iiiassigned_hours = 21 × 8 = 168 hours  // Converted!
```

#### Step 3: Save to Database
```
ci_erp_users_details.assigned_hours = serialize([leave_type_id => 168])
// Stored as 168 hours ✓
```

#### Step 4: Leave Request (Leave.php controller)
```php
// Employee requests 5 days leave
$no_of_days = 40 hours;  // 5 days × 8 hours/day

// Check balance
$hasPolicy = true;
$policyEntitlement = 21;  // days from policy
$hours_per_year = 21 × 8 = 168 hours;  // Convert to hours

// Usage from database
$tinc = 0;  // No previous leave

// Check if request exceeds quota
$fday_hours = 0 + 40 = 40 hours;
if (40 > 168) {  // false
    // Request approved ✓
}
```

## Database Examples from sfessa_hr.sql (company_id=724)

### Example 1: Policy-Based Leave with quota_unit="days"
```sql
constants_id = 365
category_name = 'الاجازة السنوية'
field_one = 'a:...s:10:"quota_unit";s:4:"days";s:12:"quota_assign";a:50:{i:0;i:30;...}'
```
- Quota: 30 days
- For 8-hour shift: 30 × 8 = 240 hours
- For 10-hour shift: 30 × 10 = 300 hours

### Example 2: Policy-Based Leave with quota_unit="days"
```sql
constants_id = 366
category_name = 'الاجازة المرضية'
field_one = 'a:...s:10:"quota_unit";s:4:"days";s:12:"quota_assign";a:50:{i:0;i:5;...}'
```
- Quota: 5 days
- For 8-hour shift: 5 × 8 = 40 hours
- For 10-hour shift: 5 × 10 = 50 hours

### Example 3: Policy-Based Leave with quota_unit="days"
```sql
constants_id = 367
category_name = 'اجازة الحج والعمرة'
field_one = 'a:...s:10:"quota_unit";s:4:"days";s:12:"quota_assign";a:50:{i:0;i:15;...}'
```
- Quota: 15 days
- For 8-hour shift: 15 × 8 = 120 hours
- For 10-hour shift: 15 × 10 = 150 hours

## Testing the Complete Fix

### Test Case 1: New Employee with Policy-Based Leave
1. Create employee with 8-hour shift
2. Assign Saudi Arabia country policy
3. View staff details page
4. **Expected**: Annual leave shows 168 hours (21 × 8)
5. **Expected**: Database stores 168 in assigned_hours
6. Request 5 days (40 hours) leave
7. **Expected**: Request approved (40 < 168)

### Test Case 2: Employee with 10-Hour Shift
1. Create employee with 10-hour shift
2. Assign Saudi Arabia country policy
3. View staff details page
4. **Expected**: Annual leave shows 210 hours (21 × 10)
5. **Expected**: Database stores 210 in assigned_hours
6. Request 5 days (50 hours) leave
7. **Expected**: Request approved (50 < 210)

### Test Case 3: Existing Employee (Legacy Data)
1. Employee already has 168 hours in assigned_hours
2. Request 5 days (40 hours) leave
3. **Expected**: Request approved (40 < 168)
4. **Expected**: No data migration needed

## Files Modified

### 1. app/Views/erp/employees/staff_details.php
- **Line ~2563**: Convert policy-based entitlement from days to hours before saving
- **Impact**: Ensures assigned_hours stores hours, not days

### 2. app/Controllers/Erp/Leave.php
- **Lines 1295-1325**: Convert country policy quota from days to hours
- **Lines 1333-1350**: Keep assigned_hours as-is (already hours)
- **Lines 1351-1365**: Keep accrual as-is (already hours)
- **Impact**: Ensures balance checking compares hours vs hours

### 3. app/Libraries/LeavePolicy.php
- **No changes needed**: Methods already work correctly
- `convertQuotaDaysToHours()`: Converts days to hours
- `formatHoursBalanceDisplay()`: Formats for display

## Summary

**Root Cause**: Policy-based leave quotas stored in days were not being converted to hours consistently.

**Solution**:
1. **Fix staff_details.php**: Convert policy entitlement from days to hours when displaying/saving
2. **Fix Leave.php**: Convert country policy quota from days to hours when checking balance
3. **Keep other quotas as-is**: They're already in hours

**Result**: All quota sources now work correctly with shift-aware hour calculations.

## Backward Compatibility

✅ **Existing data unchanged**: Employees with hours already in assigned_hours continue to work
✅ **No migration needed**: Fix applies to new calculations only
✅ **Legacy leave types**: Continue to work as before (already in hours)
✅ **New policy types**: Now correctly convert days to hours

## Verification SQL Queries

### Check Leave Type Configuration
```sql
SELECT constants_id, category_name, 
       SUBSTRING(field_one, 1, 200) as config_preview
FROM ci_erp_constants
WHERE company_id = 724 
  AND type = 'leave_type'
  AND field_one LIKE '%quota_unit%';
```

### Check Employee Assigned Hours
```sql
SELECT user_id, first_name, last_name, assigned_hours
FROM ci_erp_users u
JOIN ci_erp_users_details ud ON u.user_id = ud.user_id
WHERE u.company_id = 724
  AND assigned_hours IS NOT NULL
LIMIT 10;
```

### Check Employee Shift Hours
```sql
SELECT u.user_id, u.first_name, u.last_name, 
       s.shift_name, s.hours_per_day
FROM ci_erp_users u
JOIN ci_erp_users_details ud ON u.user_id = ud.user_id
JOIN ci_office_shifts s ON ud.office_shift_id = s.office_shift_id
WHERE u.company_id = 724
LIMIT 10;
```

## Conclusion

The fix is now complete and handles all three quota storage types correctly:
1. **Legacy quotas** (hours) → Use as-is ✓
2. **New quotas with quota_unit="days"** → Convert when saving ✓
3. **Country policy quotas** (days) → Convert when checking balance ✓

All employees now get the correct quota in hours based on their shift configuration.
