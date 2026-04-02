# Unpaid Leave Deduction Creation Fix

## Problem Statement

The user reported that unpaid leave deductions were not following the same pattern as sick leave and maternity leave deductions. The issue was in "the creational function of the deductions."

### Root Cause

The system had an **inconsistent architecture** for handling leave deductions:

**Sick Leave & Maternity Leave** (Correct Pattern):
- When leave is approved → `createSickLeaveDeductions()` or `createMaternityLeaveDeductions()` creates records in `ci_payslip_statutory_deductions` table
- During payroll generation → `get_sick_leave_deductions_for_payroll()` or `get_maternity_leave_deductions_for_payroll()` retrieves pre-created records
- Deduction amounts are stored in the database when leave is approved

**Unpaid Leave** (Inconsistent Pattern - BEFORE FIX):
- When leave is approved → **NOTHING happens** (no deduction records created)
- During payroll generation → `calculate_unpaid_leave_deduction()` calculates deductions dynamically
- Deduction amounts are NOT stored, they're recalculated every time

### The Issue

1. The `leave_hours` field in `ci_leave_applications` table stores the full hours (e.g., 248 hours for 31 days)
2. This value is NOT capped at 30 days when stored
3. Even though `calculate_unpaid_leave_deduction()` has logic to cap at 30 days, the stored `leave_hours` still shows 248 hours
4. The deduction calculation happens dynamically during payroll, not at approval time like sick/maternity leave

## Solution

Created a **consistent architecture** by implementing the missing "creational function" for unpaid leave deductions.

### Changes Made

#### 1. New Function: `createUnpaidLeaveDeductions()` in `app/Libraries/LeavePolicy.php`

This function creates deduction records in `ci_payslip_statutory_deductions` when unpaid leave is approved, following the same pattern as sick/maternity leave.

**Key Features:**
- Uses fixed 30-day month model (daily_rate = basic_salary / 30)
- Caps deduction at 30 days per month maximum
- Creates one deduction record per month
- Prevents over-deduction (deduction cannot exceed monthly salary)
- Counts working days based on employee's shift
- Stores `leave_id` for tracking

**Location:** `app/Libraries/LeavePolicy.php` (after `createMaternityLeaveDeductions()`)

#### 2. New Function: `getUnpaidLeaveDeductionsForPayroll()` in `app/Libraries/LeavePolicy.php`

Retrieves pre-created unpaid leave deduction records from the database for payroll generation.

**Query Logic:**
- Filters by employee ID and salary month
- Only returns standing deductions (`payslip_id = 0`)
- Only returns automatic deductions (`contract_option_id = 0`)
- Matches "Unpaid Leave Deduction" title

**Location:** `app/Libraries/LeavePolicy.php` (before `markSickLeaveDeductionsProcessed()`)

#### 3. Helper Functions in `app/Helpers/payroll_helper.php`

Added two helper functions to match the pattern used for sick/maternity leave:

- `get_unpaid_leave_deductions_for_payroll($employee_id, $salary_month)` - Wrapper for LeavePolicy method
- `calculate_unpaid_leave_deductions_total($employee_id, $salary_month)` - Calculates total and returns deduction IDs

**Location:** `app/Helpers/payroll_helper.php` (at the end of file)

#### 4. Updated Leave Approval Logic in `app/Controllers/Erp/Leave.php`

Modified two functions to call `createUnpaidLeaveDeductions()` when unpaid leave is approved:

**Function 1: `update_leave_status()`** (Line ~2220)
- Added logic to detect unpaid leave (field_three = 0)
- Calls `createUnpaidLeaveDeductions()` when unpaid leave is approved
- Deletes existing deductions to prevent duplicates
- Resets `salary_deduction_applied` flag

**Function 2: `update_leave()`** (Line ~1970)
- Same logic as above for the alternative approval path

**Location:** `app/Controllers/Erp/Leave.php`

### Architecture Consistency

Now ALL leave types follow the same pattern:

| Leave Type | Creation Function | Retrieval Function | Helper Function |
|------------|------------------|-------------------|-----------------|
| Sick Leave | `createSickLeaveDeductions()` | `getSickLeaveDeductionsForPayroll()` | `get_sick_leave_deductions_for_payroll()` |
| Maternity Leave | `createMaternityLeaveDeductions()` | `getMaternityLeaveDeductionsForPayroll()` | `get_maternity_leave_deductions_for_payroll()` |
| **Unpaid Leave** | **`createUnpaidLeaveDeductions()`** | **`getUnpaidLeaveDeductionsForPayroll()`** | **`get_unpaid_leave_deductions_for_payroll()`** |

## Testing

### Test File: `tests/test_unpaid_leave_creation_pattern.php`

Comprehensive test that verifies:
1. Deduction record is created when leave is approved
2. Deduction uses fixed 30-day month model
3. Working days are calculated based on employee shift
4. Deduction is capped at monthly salary
5. Retrieval functions work correctly
6. Pattern matches sick/maternity leave

### Test Results

```
✓ ALL TESTS PASSED!

Summary:
- Deduction record created successfully
- Working days calculated based on employee shift
- Fixed daily rate applied (salary / 30)
- Deduction amount: 8666.67 SAR (for 26 working days)
- Deduction capped at monthly salary
- Retrieval functions work correctly
- Pattern matches sick/maternity leave
```

## Database Schema

### Table: `ci_payslip_statutory_deductions`

Unpaid leave deductions are stored with:
- `staff_id` - Employee ID
- `salary_month` - Month in YYYY-MM format
- `pay_title` - "Unpaid Leave Deduction"
- `pay_amount` - Calculated deduction amount
- `payslip_id` - 0 (standing deduction)
- `contract_option_id` - 0 (automatic deduction)
- `leave_id` - Reference to leave application
- `is_fixed` - 0 (variable deduction)

## Benefits

1. **Consistency**: All leave types now follow the same architectural pattern
2. **Performance**: Deductions are calculated once at approval time, not repeatedly during payroll
3. **Accuracy**: Deduction amounts are stored and can be audited
4. **Traceability**: Each deduction is linked to its leave application via `leave_id`
5. **Maintainability**: Single source of truth for deduction logic

## Migration Notes

### For Existing Unpaid Leave Records

Existing approved unpaid leave records will NOT have deduction records in the database. The system will continue to use the dynamic calculation for these old records.

To migrate existing records:
1. Query all approved unpaid leave applications
2. For each record, call `createUnpaidLeaveDeductions($leave_id)`
3. Verify deductions were created correctly

### For Payroll Generation

The payroll generation code should be updated to use the new retrieval functions:

**OLD (Dynamic Calculation):**
```php
$unpaid_leave_data = calculate_unpaid_leave_deduction($employee_id, $salary_month, $basic_salary);
$unpaid_leave_deduction = $unpaid_leave_data['deduction'];
```

**NEW (Retrieve Pre-Created Records):**
```php
$unpaid_result = calculate_unpaid_leave_deductions_total($employee_id, $salary_month);
$unpaid_leave_deduction = $unpaid_result['total'];
```

## Backward Compatibility

The old `calculate_unpaid_leave_deduction()` function is still available for:
- Existing payroll records that were generated before this fix
- Migration scripts
- Fallback scenarios

However, new unpaid leave approvals will use the creation pattern.

## Files Modified

1. `app/Libraries/LeavePolicy.php` - Added `createUnpaidLeaveDeductions()` and `getUnpaidLeaveDeductionsForPayroll()`
2. `app/Helpers/payroll_helper.php` - Added helper functions
3. `app/Controllers/Erp/Leave.php` - Updated approval logic in `update_leave_status()` and `update_leave()`
4. `tests/test_unpaid_leave_creation_pattern.php` - Comprehensive test file

## Conclusion

The fix addresses the user's concern about "the creational function of the deductions" by implementing the missing creation pattern for unpaid leave. Now unpaid leave deductions are created at approval time and stored in the database, matching the pattern used for sick leave and maternity leave.

This ensures:
- Consistent architecture across all leave types
- Accurate deduction calculations using the 30-day model
- Proper capping at 30 days per month
- Deductions cannot exceed monthly salary
- Traceability and auditability of deductions
