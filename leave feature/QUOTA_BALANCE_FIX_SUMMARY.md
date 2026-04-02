# Leave Quota Balance Fix - Corrected Understanding

## The Real Problem

The system has **3 different quota sources**, and only **1 of them** (country policy) stores quota in days. The other 2 already store quota in hours:

1. **Country Policy** (`$policyEntitlement`): Stores quota in **DAYS** ✓ Needs conversion
2. **Assigned Hours** (`$iassigned_hours`): Stores quota in **HOURS** ✗ Already converted when saved
3. **Accrual** (`$ileave_option`): Stores quota in **HOURS** ✗ Already in hours

### The Bug
The initial fix incorrectly converted ALL quota sources from days to hours, which caused:
- Country policy: 21 days × 8 = 168 hours ✓ Correct
- Assigned hours: 168 hours × 8 = 1,344 hours ✗ Wrong! (double conversion)
- Accrual: 168 hours × 8 = 1,344 hours ✗ Wrong! (double conversion)

## The Correct Fix

Only convert **country policy** quota from days to hours. Leave the other two as-is since they're already in hours.

### Code Changes in app/Controllers/Erp/Leave.php (Lines 1280-1380)

#### 1. Country Policy (Line ~1295) - CONVERT ✓
```php
if ($hasPolicy) {
    // Country Policy Logic
    $days_per_year = $policyEntitlement;  // This is in DAYS
    
    // Convert quota from days to hours based on employee's shift
    $hours_per_year = $LeavePolicy->convertQuotaDaysToHours($luser_id, $days_per_year);
    
    // Now compare hours vs hours ✓
    if ($fday_hours > $hours_per_year) {
        $remainingDisplay = $LeavePolicy->formatHoursBalanceDisplay($luser_id, $dis_rem_leave);
        $Return['error'] = lang('Main.xin_hr_cant_appply_morethan') . ' ' . $remainingDisplay;
    }
}
```

#### 2. Assigned Hours (Line ~1333) - DON'T CONVERT ✓
```php
elseif (isset($ifield_one['enable_leave_accrual']) && $ifield_one['enable_leave_accrual'] == 0) {
    if (isset($iassigned_hours[$leave_type])) {
        $qdays_per_year = $iassigned_hours[$leave_type];  // Already in HOURS
    } else {
        $qdays_per_year = 0;
    }
    
    // assigned_hours already contains hours (converted when saved)
    // No need to convert again
    $hours_per_year = $qdays_per_year;  // Just assign, don't convert
    $days_per_year = $qdays_per_year;   // Keep for backward compatibility
}
```

#### 3. Accrual (Line ~1351) - DON'T CONVERT ✓
```php
else {
    if (isset($ileave_option[$leave_type][$get_month])) {
        $days_per_year = $ileave_option[$leave_type][$get_month];  // Already in HOURS
    } else {
        $days_per_year = 0;
    }
    
    // ileave_option already contains hours (accrual hours per month)
    // No need to convert
    $hours_per_year = $days_per_year;  // Just assign, don't convert
}
```

## Why Assigned Hours and Accrual Are Already in Hours

### Evidence from staff_details.php (Lines 2560-2610)

When saving employee leave options, the system converts days to hours:

```php
// For policy-based leave types
if ($isPolicyBased) {
    $iiiassigned_hours = $LeavePolicy->calculateEntitlement(...);  // Returns days
} elseif (isset($iassigned_hours[$ltype['constants_id']])) {
    $iiiassigned_hours = $iassigned_hours[$ltype['constants_id']];
    if ($iiiassigned_hours == 0) {
        if (isset($ieleave_option['quota_assign']) && $ieleave_option['is_quota'] == 1) {
            $quota_val = $ieleave_option['quota_assign'][$fyear_quota];
            
            // KEY LINE: Converts days to hours when saving
            if(isset($ieleave_option['quota_unit']) && $ieleave_option['quota_unit'] === 'days') {
                $iiiassigned_hours = $quota_val * ($hours_per_day > 0 ? $hours_per_day : 8);
            } else {
                $iiiassigned_hours = $quota_val;  // Already in hours
            }
        }
    }
}

// Saved to database as hours
<input name="assigned_hours[<?= $ltype['constants_id'] ?>]" value="<?= $iiiassigned_hours ?>" />
<small><?= lang('Main.xin_assigned_hrs'); ?></small>  // Label says "assigned hrs"
```

### Evidence from Accrual Fields

```php
<input name="leave_opt[<?= $ltype['constants_id']; ?>][<?= $key; ?>]" value="<?= $ileave_option_days; ?>" />
<small><?= lang('Main.xin_total_hours'); ?></small>  // Label says "total hours"
```

## Impact of Correct Fix

### ✅ Country Policy (21 days quota, 8-hour shift)
- Before fix: 21 days treated as 21 hours ✗
- After fix: 21 days × 8 = 168 hours ✓

### ✅ Assigned Hours (168 hours saved in database)
- Before fix: 168 hours (correct, no conversion) ✓
- Wrong fix: 168 hours × 8 = 1,344 hours ✗
- Correct fix: 168 hours (no conversion) ✓

### ✅ Accrual (14 hours per month saved)
- Before fix: 14 hours (correct, no conversion) ✓
- Wrong fix: 14 hours × 8 = 112 hours ✗
- Correct fix: 14 hours (no conversion) ✓

## Testing

### Test Case 1: Country Policy Leave
1. Employee with 8-hour shift
2. Country policy grants 21 days annual leave
3. Expected: 168 hours available (21 × 8)
4. Request 160 hours → Should succeed ✓
5. Request 170 hours → Should fail ✓

### Test Case 2: Assigned Hours Leave
1. Employee with 8-hour shift
2. Manually assigned 168 hours in staff details
3. Expected: 168 hours available (already in hours)
4. Request 160 hours → Should succeed ✓
5. Request 170 hours → Should fail ✓

### Test Case 3: Accrual Leave
1. Employee with 8-hour shift
2. Accrual set to 14 hours per month
3. After 12 months: 168 hours available
4. Request 160 hours → Should succeed ✓
5. Request 170 hours → Should fail ✓

## Summary

**Key Insight**: The system already handles days-to-hours conversion when **saving** employee leave options. The Leave controller should only convert country policy quotas (which come directly from the policy calculation and are in days).

**Files Modified**:
- `app/Controllers/Erp/Leave.php` (lines 1280-1380)
  - Convert country policy quota: days → hours ✓
  - Keep assigned hours as-is: already in hours ✓
  - Keep accrual as-is: already in hours ✓

**No Changes Needed**:
- `app/Libraries/LeavePolicy.php` (methods work correctly)
- `app/Views/erp/employees/staff_details.php` (already converts when saving)
- Database schema (no changes needed)

