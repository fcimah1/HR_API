# Sick and Maternity Leave Deduction Salary Cap Fix

## Problem Statement

When sick or maternity leave deductions were calculated for a full month with 31 days at 100% deduction rate:
- Salary: 30,000 SAR (based on 30-day model)
- Daily rate: 1,000 SAR (30,000 / 30)
- 31 days deduction: 31,000 SAR
- **Net salary: -1,000 SAR (INCORRECT - should be 0)**

The deduction exceeded the monthly salary, resulting in negative net salary.

## Root Cause

The `createSickLeaveDeductions()` and `createMaternityLeaveDeductions()` functions calculated monthly deductions but did not cap them at the monthly salary before inserting into the database.

The `createUnpaidLeaveDeductions()` function already had this cap implemented correctly:
```php
// Final safety check: Deduction cannot exceed monthly salary
$monthlyDeduction = min($monthlyDeduction, $basicSalary);
```

## Solution Implemented

Applied the same salary cap to both sick and maternity leave deduction functions.

### Changes Made

#### 1. `createSickLeaveDeductions()` (Line ~1165 in LeavePolicy.php)

**Before:**
```php
// Insert ONE record per month with aggregated total
if ($monthlyDeductionTotal > 0) {
    $insertData = [
        'staff_id' => $employeeId,
        'salary_month' => $yearMonth,
        'pay_title' => 'Sick Leave Deduction',
        'pay_amount' => round($monthlyDeductionTotal, 2),
        ...
    ];
    $db->table('ci_payslip_statutory_deductions')->insert($insertData);
}
```

**After:**
```php
// Cap deduction at monthly salary to prevent negative net salary
$monthlyDeductionTotal = min($monthlyDeductionTotal, $basicSalary);

// Insert ONE record per month with aggregated total
if ($monthlyDeductionTotal > 0) {
    $insertData = [
        'staff_id' => $employeeId,
        'salary_month' => $yearMonth,
        'pay_title' => 'Sick Leave Deduction',
        'pay_amount' => round($monthlyDeductionTotal, 2),
        ...
    ];
    $db->table('ci_payslip_statutory_deductions')->insert($insertData);
}
```

#### 2. `createMaternityLeaveDeductions()` (Line ~1260 in LeavePolicy.php)

**Before:**
```php
// Insert ONE record per month with aggregated total
if ($monthlyDeductionTotal > 0) {
    $insertData = [
        'staff_id' => $employeeId,
        'salary_month' => $yearMonth,
        'pay_title' => 'Maternity Leave Deduction',
        'pay_amount' => round($monthlyDeductionTotal, 2),
        ...
    ];
    $db->table('ci_payslip_statutory_deductions')->insert($insertData);
}
```

**After:**
```php
// Cap deduction at monthly salary to prevent negative net salary
$monthlyDeductionTotal = min($monthlyDeductionTotal, $basicSalary);

// Insert ONE record per month with aggregated total
if ($monthlyDeductionTotal > 0) {
    $insertData = [
        'staff_id' => $employeeId,
        'salary_month' => $yearMonth,
        'pay_title' => 'Maternity Leave Deduction',
        'pay_amount' => round($monthlyDeductionTotal, 2),
        ...
    ];
    $db->table('ci_payslip_statutory_deductions')->insert($insertData);
}
```

## Expected Behavior After Fix

### Scenario: 31 Days of 100% Deduction Leave

**Input:**
- Employee salary: 30,000 SAR
- Leave: 31 days in January (31-day month)
- Deduction rate: 100% (0% payment)

**Calculation:**
- Daily rate: 30,000 / 30 = 1,000 SAR
- Calculated deduction: 31 × 1,000 = 31,000 SAR
- **Capped deduction: min(31,000, 30,000) = 30,000 SAR**

**Result:**
- Deduction applied: 30,000 SAR
- Net salary: 30,000 - 30,000 = 0 SAR ✓ (not negative)

## Consistency Across Leave Types

All three leave deduction functions now follow the same pattern:

1. **Unpaid Leave** (`createUnpaidLeaveDeductions`)
   - Uses 30-day salary model
   - Caps days at 30 per month
   - Caps deduction at monthly salary ✓

2. **Sick Leave** (`createSickLeaveDeductions`)
   - Uses 30-day salary model
   - Handles tiered payment percentages
   - Caps deduction at monthly salary ✓ (NEW)

3. **Maternity Leave** (`createMaternityLeaveDeductions`)
   - Uses 30-day salary model
   - Handles tiered payment percentages
   - Caps deduction at monthly salary ✓ (NEW)

## Testing

Created test files to verify the fix:
- `tests/test_sick_maternity_salary_cap.php` - Tests sick leave cap
- `tests/test_salary_cap_direct.php` - Direct test of salary cap logic

Both tests confirm that deductions are properly capped and cannot exceed monthly salary.

## Files Modified

1. `app/Libraries/LeavePolicy.php`
   - `createSickLeaveDeductions()` method (added salary cap)
   - `createMaternityLeaveDeductions()` method (added salary cap)

## Impact

- Prevents negative net salary scenarios
- Ensures fair treatment of employees in 31-day months
- Maintains consistency with unpaid leave deduction logic
- No changes to existing approved leave records (only affects new deductions)

## Date

February 10, 2026
