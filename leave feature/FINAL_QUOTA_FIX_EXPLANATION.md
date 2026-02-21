# Final Explanation: Leave Quota Balance Fix

## What You Reported
> "if the employee has 21 days in his quota, the logic deals with them as they are number of hours and it must 21 * the Number of working hours per day he has according to the office shift has been assigned to him"

You were absolutely correct! The system was treating 21 days as 21 hours instead of converting it to 168 hours (21 × 8 for an 8-hour shift).

## What I Initially Misunderstood

I initially thought ALL quota sources stored values in days and needed conversion. This was **wrong**.

## The Real Situation

The system has **3 different quota sources**:

### 1. Country Policy (`$policyEntitlement`) - Stores DAYS ✓
- Comes from: `LeavePolicy::calculateEntitlement()`
- Returns: Days (e.g., 21 days)
- **Needs conversion**: YES
- Example: 21 days × 8 hours/day = 168 hours

### 2. Assigned Hours (`$iassigned_hours`) - Stores HOURS ✓
- Comes from: `ci_erp_users_details.assigned_hours` (database)
- Stored as: Hours (already converted when saved)
- **Needs conversion**: NO
- Example: Already 168 hours in database

### 3. Accrual (`$ileave_option`) - Stores HOURS ✓
- Comes from: `ci_erp_users_details.leave_options` (database)
- Stored as: Hours per month
- **Needs conversion**: NO
- Example: Already 14 hours per month

## Why Assigned Hours and Accrual Are Already in Hours

When an admin sets up employee leave options in the staff details page (`app/Views/erp/employees/staff_details.php`), the system:

1. Checks if the leave type uses `quota_unit = 'days'`
2. If yes, converts: `$quota_val * $hours_per_day`
3. Saves the **hours value** to database
4. The form label says "assigned hrs" and "total hours"

So by the time the Leave controller reads `$iassigned_hours` and `$ileave_option`, they're already in hours!

## The Correct Fix

### Before (WRONG - Your Bug Report)
```php
// Country policy: 21 days
$days_per_year = $policyEntitlement;  // 21
if ($fday_hours > $days_per_year) {   // Comparing 160 hours > 21 days ✗
    // Always fails!
}
```

### After (CORRECT)
```php
// Country policy: 21 days → convert to hours
$days_per_year = $policyEntitlement;  // 21 days
$hours_per_year = $LeavePolicy->convertQuotaDaysToHours($luser_id, $days_per_year);  // 168 hours
if ($fday_hours > $hours_per_year) {  // Comparing 160 hours > 168 hours ✓
    // Correctly allows the request
}

// Assigned hours: already in hours → don't convert
$hours_per_year = $iassigned_hours[$leave_type];  // 168 hours (no conversion)

// Accrual: already in hours → don't convert
$hours_per_year = $ileave_option[$leave_type][$get_month];  // 14 hours (no conversion)
```

## What Changed in the Code

### File: `app/Controllers/Erp/Leave.php` (Lines 1280-1380)

#### Change 1: Country Policy - Convert Days to Hours ✓
```php
if ($hasPolicy) {
    $days_per_year = $policyEntitlement;  // 21 days
    $hours_per_year = $LeavePolicy->convertQuotaDaysToHours($luser_id, $days_per_year);  // 168 hours
    // Use $hours_per_year for all comparisons
}
```

#### Change 2: Assigned Hours - Don't Convert (Already Hours) ✓
```php
elseif (isset($ifield_one['enable_leave_accrual']) && $ifield_one['enable_leave_accrual'] == 0) {
    $qdays_per_year = $iassigned_hours[$leave_type];  // 168 hours (already)
    $hours_per_year = $qdays_per_year;  // Just assign, don't convert
}
```

#### Change 3: Accrual - Don't Convert (Already Hours) ✓
```php
else {
    $days_per_year = $ileave_option[$leave_type][$get_month];  // 14 hours (already)
    $hours_per_year = $days_per_year;  // Just assign, don't convert
}
```

## Testing the Fix

### Test Scenario: Country Policy Leave
1. Employee: Ahmed (8-hour shift)
2. Country policy: 21 days annual leave
3. System calculates: 21 × 8 = **168 hours**
4. Ahmed requests 5 days (40 hours) → ✓ Approved
5. Ahmed requests 25 days (200 hours) → ✗ Rejected (exceeds 168 hours)

### Test Scenario: Assigned Hours Leave
1. Employee: Sarah (8-hour shift)
2. Admin manually assigned: 168 hours
3. System uses: **168 hours** (no conversion)
4. Sarah requests 5 days (40 hours) → ✓ Approved
5. Sarah requests 25 days (200 hours) → ✗ Rejected (exceeds 168 hours)

### Test Scenario: Accrual Leave
1. Employee: John (8-hour shift)
2. Accrual: 14 hours per month
3. After 12 months: **168 hours** accumulated
4. John requests 5 days (40 hours) → ✓ Approved
5. John requests 25 days (200 hours) → ✗ Rejected (exceeds 168 hours)

## Summary

**Your bug report was correct**: The system was treating 21 days as 21 hours for country policy leaves.

**The fix**: Only convert country policy quotas from days to hours. Leave assigned hours and accrual as-is because they're already stored in hours.

**Result**: All three quota sources now work correctly with shift-aware hour calculations.

## Files Modified
- `app/Controllers/Erp/Leave.php` (lines 1280-1380)

## Files NOT Modified (Already Correct)
- `app/Libraries/LeavePolicy.php` (conversion methods work correctly)
- `app/Views/erp/employees/staff_details.php` (already converts when saving)
- Database schema (no changes needed)

Thank you for catching this! The fix is now correct.
