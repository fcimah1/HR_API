# Design Document: Fix Sick Leave Payroll Deductions Display

## Overview

This design addresses a bug where sick leave deductions are correctly calculated and stored in the database but fail to display in the payroll grid (erp/payroll-list). The system implements tiered sick leave policies (e.g., Saudi Arabia: Days 1-30 at 100% pay, Days 31-90 at 75% pay, Days 91-120 at 0% pay). When an employee requests extended sick leave spanning multiple months, the deductions must be:

1. **Calculated correctly** across tier boundaries
2. **Aggregated monthly** - each month shows a single total deduction amount
3. **Stored properly** in ci_payslip_statutory_deductions table
4. **Retrieved efficiently** by the payroll helper functions
5. **Displayed accurately** in the payroll grid

### Root Cause Analysis

After analyzing the codebase, the issue stems from the `createSickLeaveDeductions()` method in LeavePolicy.php. The current implementation creates **multiple database records per month** (one for each tier segment), but the payroll grid expects a **single aggregated amount per month**. Additionally, the retrieval and display logic may not be properly handling these deduction records.

## Architecture

### Component Interaction Flow

```
Leave Approval → LeavePolicy::createSickLeaveDeductions()
                 ↓
                 Creates records in ci_payslip_statutory_deductions
                 (payslip_id = 0, contract_option_id = 0)
                 ↓
Payroll Grid Load → get_payroll_list()
                    ↓
                    calculate_sick_leave_deductions_total()
                    ↓
                    get_sick_leave_deductions_for_payroll()
                    ↓
                    LeavePolicy::getSickLeaveDeductionsForPayroll()
                    ↓
                    Query ci_payslip_statutory_deductions
                    ↓
                    Display in Grid Column
```

### Database Schema

**ci_payslip_statutory_deductions table:**
- `payslip_deduction_id` (PK)
- `staff_id` - Employee ID
- `salary_month` - Format: YYYY-MM
- `pay_title` - Deduction description
- `pay_amount` - Deduction amount
- `is_fixed` - 0 for calculated deductions
- `payslip_id` - 0 for standing deductions, >0 when processed into payslip
- `contract_option_id` - 0 for automatic deductions (sick/maternity leave)
- `created_at` - Timestamp

## Components and Interfaces

### 1. LeavePolicy Library (app/Libraries/LeavePolicy.php)

**Modified Method: createSickLeaveDeductions()**

```php
/**
 * Create salary deductions for approved tiered sick leave
 * FIXED: Aggregates tier segments into single monthly records
 * 
 * @param int $leaveApplicationId
 * @return bool
 */
public function createSickLeaveDeductions($leaveApplicationId)
{
    // 1. Fetch leave application details
    // 2. Delete existing sick leave deductions for this employee (prevent duplicates)
    // 3. Calculate cumulative days before this request
    // 4. Iterate through each month in the leave period
    // 5. For each month:
    //    a. Calculate days in this month segment
    //    b. Calculate tier split for these days
    //    c. AGGREGATE all tier deductions into single monthly total
    //    d. Insert ONE record per month with aggregated amount
    // 6. Return success
}
```

**Key Changes:**
- Instead of inserting one record per tier segment, accumulate all tier deductions for a month
- Insert a single record per employee per month with the total deduction amount
- Use a simple title like "Sick Leave Deduction" or "خصم الإجازة المرضية"

**Existing Method: getSickLeaveDeductionsForPayroll()**

```php
/**
 * Get pending sick leave deductions for an employee and month
 * Used by payroll to include in payslip
 * 
 * @param int $employeeId
 * @param string $salaryMonth YYYY-MM format
 * @return array
 */
public function getSickLeaveDeductionsForPayroll($employeeId, $salaryMonth)
{
    // Query ci_payslip_statutory_deductions
    // Filter: staff_id, salary_month, payslip_id = 0, contract_option_id = 0
    // Filter: pay_title LIKE '%Sick%'
    // Return: deduction_id, deduction_amount, pay_title
}
```

**Status:** Already implemented correctly - no changes needed.

### 2. Payroll Helper (app/Helpers/payroll_helper.php)

**Existing Function: calculate_sick_leave_deductions_total()**

```php
/**
 * Calculate total sick leave deductions amount for a month
 * 
 * @param int $employee_id
 * @param string $salary_month Y-m format
 * @return array ['total' => float, 'deductions' => array, 'ids' => array]
 */
function calculate_sick_leave_deductions_total($employee_id, $salary_month)
{
    // 1. Call get_sick_leave_deductions_for_payroll()
    // 2. Sum all deduction amounts
    // 3. Return total, deductions array, and IDs
}
```

**Status:** Already implemented correctly - no changes needed.

**Existing Function: get_payroll_list()**

This function already calls `calculate_sick_leave_deductions_total()` and includes the result in the returned data array. The sick_leave_deduction field is populated for both unpaid and paid payroll records.

**Status:** Already implemented correctly - no changes needed.

### 3. Payroll Grid View (app/Views/erp/payroll/erp_payroll_grid.php)

**Grid Column Configuration:**

The grid already has a column defined for sick leave deductions:

```javascript
{
  field: 'sick_leave_deduction',
  title: '<?= lang('Payroll.xin_sick_leave_deduction'); ?> -',
  editor: false,
  cssClass: 'statutory sick-leave-bg'
}
```

**Status:** Already implemented correctly - no changes needed.

### 4. Payroll Controller (app/Controllers/Erp/Payroll.php)

**Method: payslip_list()**

This method returns JSON data for the payroll grid. It calls `get_payroll_list()` which already includes sick leave deductions.

**Status:** Verify that the data flow is working correctly - may need debugging.

## Data Models

### Deduction Record Structure

**Standing Deduction (Unpaid):**
```php
[
    'staff_id' => 123,
    'salary_month' => '2026-03',
    'pay_title' => 'Sick Leave Deduction',  // or 'خصم الإجازة المرضية'
    'pay_amount' => 1250.00,  // Aggregated monthly total
    'is_fixed' => 0,
    'payslip_id' => 0,  // Standing deduction
    'contract_option_id' => 0,  // Automatic deduction
    'created_at' => '2026-02-15 10:30:00'
]
```

**Processed Deduction (Paid):**
```php
[
    'staff_id' => 123,
    'salary_month' => '2026-03',
    'pay_title' => 'Sick Leave Deduction',
    'pay_amount' => 1250.00,
    'is_fixed' => 0,
    'payslip_id' => 456,  // Associated with payslip
    'contract_option_id' => 0,
    'created_at' => '2026-02-15 10:30:00'
]
```

### Monthly Distribution Example

**Leave Request:** 120 days from 2026-02-09 to 2026-06-08
**Basic Salary:** 10,000 SAR/month
**Daily Rate:** 10,000 / 30 = 333.33 SAR/day

**Tier Configuration:**
- Days 1-30: 100% pay (0% deduction)
- Days 31-90: 75% pay (25% deduction)
- Days 91-120: 0% pay (100% deduction)

**Monthly Breakdown:**

| Month | Days in Month | Cumulative Start | Tier Distribution | Deduction Calculation | Monthly Total |
|-------|---------------|------------------|-------------------|----------------------|---------------|
| Feb 2026 | 20 (Feb 9-28) | Day 1 | Days 1-20: 100% pay | 0 deduction | 0.00 SAR |
| Mar 2026 | 31 (Mar 1-31) | Day 21 | Days 21-30: 100% pay (10 days)<br>Days 31-51: 75% pay (21 days) | 21 × 333.33 × 25% | 1,750.00 SAR |
| Apr 2026 | 30 (Apr 1-30) | Day 52 | Days 52-81: 75% pay (30 days) | 30 × 333.33 × 25% | 2,500.00 SAR |
| May 2026 | 31 (May 1-31) | Day 82 | Days 82-90: 75% pay (9 days)<br>Days 91-112: 0% pay (22 days) | (9 × 333.33 × 25%) + (22 × 333.33 × 100%) | 8,083.33 SAR |
| Jun 2026 | 8 (Jun 1-8) | Day 113 | Days 113-120: 0% pay (8 days) | 8 × 333.33 × 100% | 2,666.67 SAR |

**Total Deduction:** 14,1000.00 SAR

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system—essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property 1: Monthly Deduction Aggregation

*For any* sick leave request spanning multiple tiers within a single month, the total deduction amount stored for that month SHALL equal the sum of deductions from all tier segments within that month.

**Validates: Requirements 1.1, 1.2**

### Property 2: Multi-Month Distribution

*For any* sick leave request spanning multiple months, the number of deduction records created SHALL equal the number of months in the leave period, with each record containing the aggregated deduction for that specific month.

**Validates: Requirements 1.3, 10.1**

### Property 3: Tier Calculation Correctness

*For any* sick leave request, when calculating deductions for each month, the tier percentage applied to each day SHALL be determined by the cumulative days used up to that point in the year, ensuring correct tier progression across the entire leave period.

**Validates: Requirements 1.4, 10.2**

### Property 4: Deduction Retrieval Accuracy

*For any* employee and salary month, when retrieving sick leave deductions, the system SHALL return all deduction records matching staff_id, salary_month, payslip_id = 0, and contract_option_id = 0, with the total amount equal to the sum of all matching records.

**Validates: Requirements 2.1, 2.2, 2.3, 2.4**

### Property 5: Grid Display Consistency

*For any* employee with sick leave deductions in a given month, the amount displayed in the payroll grid sick leave column SHALL equal the total deduction amount retrieved from the database for that employee and month.

**Validates: Requirements 3.2**

### Property 6: Net Salary Calculation Accuracy

*For any* employee, the net salary displayed SHALL equal the gross salary (basic + allowances + commissions + other payments) minus all deductions (statutory + sick leave + maternity leave + unpaid leave + loans), and the displayed sick leave deduction amount SHALL be consistent with the net salary calculation.

**Validates: Requirements 4.1, 4.2, 4.3, 4.4**

### Property 7: Deduction Total Conservation

*For any* sick leave request spanning multiple months, the sum of all monthly deduction amounts SHALL equal the total deduction calculated for the entire leave period based on the tier rules and daily rate.

**Validates: Requirements 10.4**

### Property 8: Maternity Leave Parity

*For any* maternity leave request, the deduction calculation, storage, retrieval, and display logic SHALL follow the same pattern as sick leave deductions, with the only difference being the leave type identifier in the pay_title field.

**Validates: Requirements 7.1, 7.2, 7.3, 7.4**

## Error Handling

### Database Query Failures

```php
try {
    $deductions = $db->table('ci_payslip_statutory_deductions')
        ->where('staff_id', $employeeId)
        ->where('salary_month', $salaryMonth)
        ->where('payslip_id', 0)
        ->where('contract_option_id', 0)
        ->like('pay_title', 'Sick', 'both')
        ->get()->getResultArray();
} catch (\Exception $e) {
    log_message('error', 'Failed to retrieve sick leave deductions: ' . $e->getMessage());
    return []; // Return empty array to prevent grid errors
}
```

### Missing Employee Data

```php
if (!$basicSalary || $basicSalary <= 0) {
    log_message('warning', "Employee {$employeeId} has no basic salary configured");
    // Still create deduction records with 0 amount or skip
    return true;
}
```

### Invalid Date Formats

```php
if (!preg_match('/^\d{4}-\d{2}$/', $salaryMonth)) {
    log_message('error', "Invalid salary_month format: {$salaryMonth}");
    return ['total' => 0, 'deductions' => [], 'ids' => []];
}
```

### Edge Cases

1. **No Deductions:** Return 0.00 without errors
2. **Partial Month Leave:** Calculate days accurately using date arithmetic
3. **Year Boundary:** Handle leaves spanning December-January correctly
4. **Duplicate Deductions:** Delete existing deductions before creating new ones

## Testing Strategy

### Dual Testing Approach

This fix requires both unit tests and property-based tests to ensure comprehensive coverage:

**Unit Tests** will verify:
- Specific examples of monthly distribution calculations
- Edge cases (no deductions, partial months, year boundaries)
- Error handling (database failures, invalid inputs)
- Integration between components (LeavePolicy → Helper → Grid)

**Property-Based Tests** will verify:
- Universal properties across all possible leave requests
- Correctness of tier calculations for random leave durations
- Conservation of total deduction amounts across monthly splits
- Consistency between database records and grid display

### Property-Based Testing Configuration

- **Library:** Use PHPUnit with a property-based testing extension or implement custom generators
- **Iterations:** Minimum 100 iterations per property test
- **Tagging:** Each property test must reference its design document property

**Example Tag Format:**
```php
/**
 * @test
 * Feature: fix-sick-leave-payroll-deductions, Property 1: Monthly Deduction Aggregation
 */
```

### Test Data Generators

**Random Leave Request Generator:**
```php
function generateRandomLeaveRequest() {
    return [
        'employee_id' => rand(1, 1000),
        'from_date' => randomDate('2026-01-01', '2026-12-31'),
        'duration_days' => rand(1, 120),
        'basic_salary' => rand(5000, 20000),
        'country_code' => 'SA'
    ];
}
```

**Random Tier Configuration Generator:**
```php
function generateRandomTierConfig() {
    return [
        ['days' => 30, 'payment_percentage' => 100],
        ['days' => 60, 'payment_percentage' => 75],
        ['days' => 30, 'payment_percentage' => 0]
    ];
}
```

### Unit Test Examples

**Test: Single Month, Single Tier**
```php
public function test_single_month_single_tier_deduction()
{
    // Leave: 15 days in March, all in Tier 1 (100% pay)
    // Expected: 0 deduction
    $leaveId = $this->createLeave('2026-03-01', '2026-03-15', 15);
    $this->leavePolicy->createSickLeaveDeductions($leaveId);
    
    $deductions = $this->getDeductionsForMonth(123, '2026-03');
    $this->assertEquals(0, count($deductions));
}
```

**Test: Single Month, Multiple Tiers**
```php
public function test_single_month_multiple_tiers()
{
    // Leave: 31 days in March, Days 1-30 at 100%, Day 31 at 75%
    // Expected: 1 record with deduction for 1 day at 25%
    $leaveId = $this->createLeave('2026-03-01', '2026-03-31', 31);
    $this->leavePolicy->createSickLeaveDeductions($leaveId);
    
    $deductions = $this->getDeductionsForMonth(123, '2026-03');
    $this->assertEquals(1, count($deductions));
    $this->assertEqualsWithDelta(333.33 * 0.25, $deductions[0]['pay_amount'], 0.01);
}
```

**Test: Multi-Month Distribution**
```php
public function test_multi_month_distribution()
{
    // Leave: 120 days from Feb 9 to Jun 8
    // Expected: 5 monthly records (Feb, Mar, Apr, May, Jun)
    $leaveId = $this->createLeave('2026-02-09', '2026-06-08', 120);
    $this->leavePolicy->createSickLeaveDeductions($leaveId);
    
    $febDeductions = $this->getDeductionsForMonth(123, '2026-02');
    $marDeductions = $this->getDeductionsForMonth(123, '2026-03');
    $aprDeductions = $this->getDeductionsForMonth(123, '2026-04');
    $mayDeductions = $this->getDeductionsForMonth(123, '2026-05');
    $junDeductions = $this->getDeductionsForMonth(123, '2026-06');
    
    $this->assertEquals(0, count($febDeductions)); // Days 1-20, no deduction
    $this->assertEquals(1, count($marDeductions)); // Days 21-51, mixed tiers
    $this->assertEquals(1, count($aprDeductions)); // Days 52-81, Tier 2
    $this->assertEquals(1, count($mayDeductions)); // Days 82-112, mixed tiers
    $this->assertEquals(1, count($junDeductions)); // Days 113-120, Tier 3
}
```

## Implementation Notes

### Key Fix: Aggregate Tier Segments

The primary fix is in `createSickLeaveDeductions()`:

**Before (Current - BROKEN):**
```php
foreach ($tierSegments as $segment) {
    if ($segment['payment_percentage'] < 100) {
        $deductionPercent = 100 - $segment['payment_percentage'];
        $deductionAmount = $segment['days'] * $dailyRate * ($deductionPercent / 100);
        
        $insertData = [
            'staff_id' => $employeeId,
            'salary_month' => $yearMonth,
            'pay_title' => "Sick Leave Deduction ({$segment['days']} days @ {$deductionPercent}%)",
            'pay_amount' => round($deductionAmount, 2),
            // ...
        ];
        
        $db->table('ci_payslip_statutory_deductions')->insert($insertData);
    }
}
```

**After (Fixed - CORRECT):**
```php
$monthlyDeductionTotal = 0;

foreach ($tierSegments as $segment) {
    if ($segment['payment_percentage'] < 100) {
        $deductionPercent = 100 - $segment['payment_percentage'];
        $deductionAmount = $segment['days'] * $dailyRate * ($deductionPercent / 100);
        $monthlyDeductionTotal += $deductionAmount;
    }
}

// Insert ONE record per month with aggregated total
if ($monthlyDeductionTotal > 0) {
    $insertData = [
        'staff_id' => $employeeId,
        'salary_month' => $yearMonth,
        'pay_title' => 'Sick Leave Deduction',  // Simple title
        'pay_amount' => round($monthlyDeductionTotal, 2),
        'is_fixed' => 0,
        'payslip_id' => 0,
        'contract_option_id' => 0,
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    $db->table('ci_payslip_statutory_deductions')->insert($insertData);
}
```

### Debugging Aids

Add logging at key points:

```php
// In createSickLeaveDeductions()
log_message('info', "Creating sick leave deductions for leave_id={$leaveApplicationId}, employee={$employeeId}");
log_message('info', "Month {$yearMonth}: Total deduction = {$monthlyDeductionTotal}");

// In getSickLeaveDeductionsForPayroll()
log_message('info', "Retrieving sick leave deductions for employee={$employeeId}, month={$salaryMonth}");
log_message('info', "Found " . count($deductions) . " deduction records");

// In get_payroll_list()
log_message('info', "Payroll list: employee={$r['user_id']}, sick_leave_deduction={$sick_leave_deduction}");
```

### Performance Considerations

- **Query Optimization:** Index on (staff_id, salary_month, payslip_id, contract_option_id)
- **Batch Processing:** When processing multiple employees, consider batching database operations
- **Caching:** Consider caching deduction calculations for frequently accessed months

### Backward Compatibility

- Existing payslips with multiple deduction records per month will continue to work
- The fix only affects new deduction creation going forward
- Consider a migration script to consolidate existing multi-record months into single records

## Deployment Checklist

1. ✅ Update LeavePolicy::createSickLeaveDeductions() to aggregate monthly deductions
2. ✅ Verify getSickLeaveDeductionsForPayroll() retrieves records correctly
3. ✅ Test calculate_sick_leave_deductions_total() sums amounts correctly
4. ✅ Verify get_payroll_list() includes sick_leave_deduction in returned data
5. ✅ Test payroll grid displays sick_leave_deduction column
6. ✅ Verify net salary calculation subtracts sick leave deductions
7. ✅ Test with multi-month leave requests (e.g., 120 days)
8. ✅ Test with single-month, multi-tier leave requests
9. ✅ Test edge cases (no deductions, partial months, year boundaries)
10. ✅ Add logging for debugging
11. ✅ Run property-based tests (minimum 100 iterations each)
12. ✅ Verify maternity leave deductions work the same way
13. ✅ Test both unpaid and paid payslip views
14. ✅ Performance test with large datasets
15. ✅ Update documentation and user guides
