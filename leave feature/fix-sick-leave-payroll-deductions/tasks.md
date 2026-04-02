# Implementation Plan: Fix Sick Leave Payroll Deductions Display

## Overview

This plan implements the fix for sick leave deductions not displaying in the payroll grid. The primary issue is that the `createSickLeaveDeductions()` method creates multiple database records per month (one per tier segment), but the system expects a single aggregated amount per month. The fix involves modifying the deduction creation logic to aggregate tier segments within each month before inserting into the database.

## Tasks

- [x] 1. Fix monthly deduction aggregation in LeavePolicy.php
  - Modify `createSickLeaveDeductions()` method to aggregate all tier segments within a month into a single total
  - Change from inserting one record per tier segment to inserting one record per month
  - Use simple deduction title: "Sick Leave Deduction" or "خصم الإجازة المرضية"
  - Ensure the method still correctly handles multi-month leave requests
  - _Requirements: 1.1, 1.2, 1.3, 1.4_

- [x] 1.1 Write property test for monthly deduction aggregation
  - **Property 1: Monthly Deduction Aggregation**
  - **Validates: Requirements 1.1, 1.2**
  - Generate random sick leave requests spanning multiple tiers within a single month
  - Verify that only one deduction record is created per month
  - Verify that the record amount equals the sum of all tier segment deductions for that month
  - Run with minimum 100 iterations

- [x] 1.2 Write property test for multi-month distribution
  - **Property 2: Multi-Month Distribution**
  - **Validates: Requirements 1.3, 10.1**
  - Generate random sick leave requests spanning multiple months
  - Verify that the number of deduction records equals the number of months in the leave period
  - Verify that each record contains the correct aggregated deduction for its month
  - Run with minimum 100 iterations

- [x] 1.3 Write property test for tier calculation correctness
  - **Property 3: Tier Calculation Correctness**
  - **Validates: Requirements 1.4, 10.2**
  - Generate random sick leave requests with varying start dates and durations
  - Verify that tier percentages are correctly applied based on cumulative days used
  - Verify that tier progression is correct across the entire leave period
  - Run with minimum 100 iterations

- [x] 1.4 Write unit tests for edge cases
  - Test single month, single tier (no deduction expected)
  - Test single month, multiple tiers (aggregated deduction)
  - Test multi-month leave (120 days example from design)
  - Test partial month at start and end of leave period
  - Test leave spanning year boundary (Dec-Jan)
  - _Requirements: 1.1, 1.2, 1.3, 1.4_

- [x] 2. Verify deduction retrieval logic
  - Review `getSickLeaveDeductionsForPayroll()` method in LeavePolicy.php
  - Ensure it correctly queries ci_payslip_statutory_deductions table
  - Verify filters: staff_id, salary_month, payslip_id = 0, contract_option_id = 0
  - Verify it filters by pay_title LIKE '%Sick%'
  - Add logging for debugging
  - _Requirements: 2.1, 2.2, 2.3, 2.4_

- [x] 2.1 Write property test for deduction retrieval accuracy
  - **Property 4: Deduction Retrieval Accuracy**
  - **Validates: Requirements 2.1, 2.2, 2.3, 2.4**
  - Create random deduction records in the database
  - Query using getSickLeaveDeductionsForPayroll()
  - Verify all matching records are returned
  - Verify total amount equals sum of all matching records
  - Run with minimum 100 iterations

- [x] 2.2 Write unit tests for retrieval edge cases
  - Test retrieval when no deductions exist (should return empty array)
  - Test retrieval with invalid salary_month format (should handle gracefully)
  - Test retrieval with database connection failure (should log error and return empty)
  - _Requirements: 8.1, 8.2, 8.4_

- [x] 3. Verify payroll helper functions
  - Review `calculate_sick_leave_deductions_total()` function in payroll_helper.php
  - Ensure it correctly calls `get_sick_leave_deductions_for_payroll()`
  - Verify it sums all deduction amounts correctly
  - Review `get_payroll_list()` function
  - Ensure it calls `calculate_sick_leave_deductions_total()` for each employee
  - Verify sick_leave_deduction is included in returned data array
  - Add logging for debugging
  - _Requirements: 5.3, 5.4_

- [~] 3.1 Write unit tests for helper functions
  - Test calculate_sick_leave_deductions_total() with multiple deduction records
  - Test calculate_sick_leave_deductions_total() with no deductions
  - Test get_payroll_list() includes sick_leave_deduction in output
  - _Requirements: 5.3, 5.4_

- [~] 4. Verify payroll grid display
  - Review erp_payroll_grid.php view file
  - Verify sick_leave_deduction column is defined in grid configuration
  - Verify column has correct CSS class: 'statutory sick-leave-bg'
  - Verify column is set to editor: false (read-only)
  - Review Payroll controller payslip_list() method
  - Ensure it returns sick_leave_deduction in JSON response
  - Test grid rendering with sample data
  - _Requirements: 3.1, 3.2, 3.3, 3.4_

- [~] 4.1 Write property test for grid display consistency
  - **Property 5: Grid Display Consistency**
  - **Validates: Requirements 3.2**
  - Create random deduction records in database
  - Call get_payroll_list() to retrieve data
  - Verify sick_leave_deduction field in returned data matches database records
  - Run with minimum 100 iterations

- [~] 4.2 Write unit tests for grid display
  - Test grid column configuration includes sick_leave_deduction
  - Test grid displays 0.00 when no deductions exist
  - Test grid displays correct amount when deductions exist
  - Test CSS class is applied correctly
  - _Requirements: 3.1, 3.2, 3.3, 3.4_

- [~] 5. Verify net salary calculation
  - Review net salary calculation in get_payroll_list() function
  - Ensure sick_leave_deduction is subtracted from gross salary
  - Verify calculation: net = basic + allowances + commissions + other_payments - statutory - sick_leave - maternity_leave - unpaid_leave - loans
  - Test with various combinations of deductions
  - _Requirements: 4.1, 4.2, 4.3, 4.4_

- [~] 5.1 Write property test for net salary calculation accuracy
  - **Property 6: Net Salary Calculation Accuracy**
  - **Validates: Requirements 4.1, 4.2, 4.3, 4.4**
  - Generate random employee data with various deduction combinations
  - Calculate expected net salary manually
  - Call get_payroll_list() and verify net_salary field matches expected value
  - Verify displayed sick_leave_deduction is consistent with net salary calculation
  - Run with minimum 100 iterations

- [~] 5.2 Write unit tests for net salary calculation
  - Test net salary with only sick leave deduction
  - Test net salary with sick leave + other statutory deductions
  - Test net salary with sick leave + maternity leave + unpaid leave + loans
  - Test net salary with no deductions (should equal gross)
  - _Requirements: 4.1, 4.2, 4.3, 4.4_

- [~] 6. Test end-to-end workflow
  - Create a test sick leave application (120 days from 2026-02-09 to 2026-06-08)
  - Approve the leave application
  - Verify createSickLeaveDeductions() is called
  - Verify deduction records are created in database (one per month)
  - Verify deduction amounts match expected values from design document
  - Load payroll grid for each month (Feb, Mar, Apr, May, Jun)
  - Verify sick leave deductions display correctly in each month
  - Verify net salary is calculated correctly
  - _Requirements: 5.1, 5.2, 6.1, 6.2_

- [~] 6.1 Write property test for deduction total conservation
  - **Property 7: Deduction Total Conservation**
  - **Validates: Requirements 10.4**
  - Generate random sick leave requests spanning multiple months
  - Calculate expected total deduction for entire leave period
  - Sum all monthly deduction records created
  - Verify sum of monthly deductions equals total deduction
  - Run with minimum 100 iterations

- [x] 7. **FIX MATERNITY LEAVE DEDUCTION AGGREGATION** (SAME BUG AS SICK LEAVE - PRIORITY)
  - **CRITICAL**: The `createMaternityLeaveDeductions()` method has the EXACT SAME BUG as sick leave had
  - **Current Problem**: Creates multiple database records per month (one per tier segment)
  - **Required Fix**: Aggregate all tier segments within a month into a single total BEFORE inserting
  - **Implementation**: Apply the SAME aggregation pattern you used for sick leave in Task 1
  - Modify `createMaternityLeaveDeductions()` in LeavePolicy.php:
    - Accumulate tier deductions in a `$monthlyDeductionTotal` variable
    - Insert ONE record per month with the aggregated amount
    - Use simple title: "Maternity Leave Deduction" or "خصم إجازة الأمومة"
  - Verify `getMaternityLeaveDeductionsForPayroll()` retrieves records correctly
  - Verify `calculate_maternity_leave_deductions_total()` sums amounts correctly
  - Verify maternity_leave_deduction column displays in payroll grid
  - **Test Case**: 77-day maternity leave from 2026-03-01 to 2026-05-16
    - Days 1-70: Full pay (no deduction)
    - Days 71-77: 100% deduction (7 days in May)
    - Expected: One deduction record in May with 7 days × daily_rate
  - _Requirements: 7.1, 7.2, 7.3, 7.4_

- [x] 7.1 Write property test for maternity leave parity
  - **Property 8: Maternity Leave Parity**
  - **Validates: Requirements 7.1, 7.2, 7.3, 7.4**
  - Generate random maternity leave requests
  - Verify deduction creation, retrieval, and display follow same pattern as sick leave
  - Verify only difference is the leave type identifier in pay_title
  - Run with minimum 100 iterations

- [~] 8. Add error handling and logging
  - Add try-catch blocks around database operations in createSickLeaveDeductions()
  - Add logging at key points: deduction creation, retrieval, display
  - Log employee_id, salary_month, deduction amount, and record count
  - Handle edge cases: no basic salary, invalid date formats, database failures
  - Return appropriate default values (0.00) when errors occur
  - _Requirements: 8.1, 8.2, 8.3, 8.4, 9.1, 9.2, 9.3_

- [~] 8.1 Write unit tests for error handling
  - Test behavior when database query fails
  - Test behavior when employee has no basic salary
  - Test behavior when salary_month format is invalid
  - Test behavior when no deductions exist
  - Verify errors are logged correctly
  - Verify system returns 0.00 and continues without crashing
  - _Requirements: 8.1, 8.2, 8.3, 8.4_

- [~] 9. Checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

- [~] 10. Integration testing with real data
  - Test with actual employee data from sfessa_hr database
  - Test with existing sick leave applications
  - Verify backward compatibility with existing payslips
  - Test performance with large datasets (100+ employees)
  - Verify payroll grid loads within acceptable time (<2 seconds)
  - _Requirements: All_

- [~] 11. Final checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional property-based and unit tests that can be skipped for faster MVP
- Each task references specific requirements for traceability
- Property tests validate universal correctness properties with minimum 100 iterations
- Unit tests validate specific examples and edge cases
- Integration tests verify end-to-end workflows with real data
- The primary fix is in Task 1: aggregating tier segments into single monthly records
- All other tasks verify that existing components work correctly with the fix
