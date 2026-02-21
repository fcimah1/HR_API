# Requirements Document

## Introduction

This document specifies the requirements for fixing a bug where sick leave deductions are calculated and stored in the database but are not displaying correctly in the payroll section (erp/payroll-list). The system implements a Saudi Arabia leave policy with tiered sick leave deductions (Days 1-30: 100% Pay, Days 31-90: 75% Pay, Days 91-120: 0% Pay). When an employee requests 120 days of sick leave spanning multiple months, the deductions must be distributed across months with each month showing a single total deduction amount that accounts for all applicable tiers within that month. Currently, the deductions are created via the LeavePolicy.php library and stored in the ci_payslip_statutory_deductions table, but the monthly aggregation and display logic needs to be fixed.

## Glossary

- **Payroll_System**: The HR payroll management system
- **Sick_Leave_Deduction**: A salary deduction applied when an employee takes sick leave beyond the fully paid threshold, distributed monthly across the leave period
- **Monthly_Deduction_Total**: The single aggregated deduction amount for a specific employee and month, combining all tier segments within that month
- **Payroll_Grid**: The user interface displaying employee payroll information at erp/payroll-list
- **Standing_Deduction**: A deduction record stored with payslip_id = 0, indicating it has not yet been processed into a payslip
- **Statutory_Deduction**: A mandatory deduction from employee salary (stored in ci_payslip_statutory_deductions table)
- **Tiered_Policy**: A leave policy with multiple tiers having different payment percentages
- **LeavePolicy_Library**: The PHP library (LeavePolicy.php) responsible for calculating and creating leave deductions
- **Payroll_Helper**: The helper file (payroll_helper.php) containing functions for payroll calculations and data retrieval

## Requirements

### Requirement 1: Monthly Deduction Aggregation

**User Story:** As a payroll administrator, I want sick leave deductions to be aggregated by month, so that each month shows a single total deduction amount regardless of how many tiers apply.

#### Acceptance Criteria

1. WHEN a sick leave spans multiple tiers within a single month, THE LeavePolicy_Library SHALL calculate the total deduction for that month by summing all tier segments
2. WHEN creating deduction records, THE LeavePolicy_Library SHALL insert one record per employee per month with the aggregated amount
3. WHEN a leave request spans multiple months, THE LeavePolicy_Library SHALL create separate deduction records for each month
4. FOR ALL months with sick leave deductions, the deduction amount SHALL reflect the correct tier percentages applied to the days in that month

### Requirement 2: Deduction Data Retrieval

**User Story:** As a payroll administrator, I want sick leave deductions to be retrieved from the database, so that they can be displayed in the payroll grid.

#### Acceptance Criteria

1. WHEN the payroll grid loads for a specific month, THE Payroll_System SHALL query ci_payslip_statutory_deductions table for sick leave deductions
2. WHEN querying for sick leave deductions, THE Payroll_System SHALL filter by staff_id, salary_month, payslip_id = 0, and contract_option_id = 0
3. WHEN sick leave deductions exist for an employee, THE Payroll_System SHALL include the pay_title and pay_amount in the result set
4. WHEN multiple sick leave deduction records exist for the same employee and month, THE Payroll_System SHALL sum all deduction amounts

### Requirement 2: Deduction Data Retrieval

**User Story:** As a payroll administrator, I want sick leave deductions to be retrieved from the database, so that they can be displayed in the payroll grid.

#### Acceptance Criteria

1. WHEN the payroll grid loads for a specific month, THE Payroll_System SHALL query ci_payslip_statutory_deductions table for sick leave deductions
2. WHEN querying for sick leave deductions, THE Payroll_System SHALL filter by staff_id, salary_month, payslip_id = 0, and contract_option_id = 0
3. WHEN sick leave deductions exist for an employee, THE Payroll_System SHALL include the pay_title and pay_amount in the result set
4. WHEN retrieving deductions for a month, THE Payroll_System SHALL return a single total amount per employee (already aggregated at creation time)

### Requirement 3: Deduction Display in Grid

**User Story:** As a payroll administrator, I want to see sick leave deductions displayed in the payroll grid, so that I can verify the correct amounts are being deducted.

#### Acceptance Criteria

1. WHEN the payroll grid renders, THE Payroll_Grid SHALL display a dedicated column for sick leave deductions
2. WHEN an employee has sick leave deductions for the displayed month, THE Payroll_Grid SHALL show the total deduction amount in the sick leave column
3. WHEN an employee has no sick leave deductions, THE Payroll_Grid SHALL display 0.00 or an empty value in the sick leave column
4. THE Payroll_Grid SHALL visually distinguish sick leave deduction columns using the "sick-leave-bg" CSS class

### Requirement 3: Deduction Display in Grid

**User Story:** As a payroll administrator, I want to see sick leave deductions displayed in the payroll grid, so that I can verify the correct amounts are being deducted.

#### Acceptance Criteria

1. WHEN the payroll grid renders, THE Payroll_Grid SHALL display a dedicated column for sick leave deductions
2. WHEN an employee has sick leave deductions for the displayed month, THE Payroll_Grid SHALL show the total deduction amount in the sick leave column
3. WHEN an employee has no sick leave deductions, THE Payroll_Grid SHALL display 0.00 or an empty value in the sick leave column
4. THE Payroll_Grid SHALL visually distinguish sick leave deduction columns using the "sick-leave-bg" CSS class

### Requirement 4: Net Salary Calculation

**User Story:** As a payroll administrator, I want sick leave deductions to be included in net salary calculations, so that employee pay is accurate.

#### Acceptance Criteria

1. WHEN calculating net salary for an employee, THE Payroll_System SHALL subtract sick leave deductions from the gross salary
2. WHEN both sick leave deductions and other statutory deductions exist, THE Payroll_System SHALL subtract all deductions from gross salary
3. WHEN displaying net salary in the grid, THE Payroll_System SHALL reflect the impact of sick leave deductions
4. THE Payroll_System SHALL maintain consistency between the displayed deduction amount and the net salary calculation

### Requirement 4: Net Salary Calculation

**User Story:** As a payroll administrator, I want sick leave deductions to be included in net salary calculations, so that employee pay is accurate.

#### Acceptance Criteria

1. WHEN calculating net salary for an employee, THE Payroll_System SHALL subtract sick leave deductions from the gross salary
2. WHEN both sick leave deductions and other statutory deductions exist, THE Payroll_System SHALL subtract all deductions from gross salary
3. WHEN displaying net salary in the grid, THE Payroll_System SHALL reflect the impact of sick leave deductions
4. THE Payroll_System SHALL maintain consistency between the displayed deduction amount and the net salary calculation

### Requirement 5: Deduction Data Flow Integrity

**User Story:** As a system architect, I want to ensure sick leave deductions flow correctly from creation to display, so that data integrity is maintained throughout the payroll process.

#### Acceptance Criteria

1. WHEN a sick leave application is approved, THE LeavePolicy_Library SHALL create deduction records in ci_payslip_statutory_deductions
2. WHEN deduction records are created, THE LeavePolicy_Library SHALL set payslip_id = 0 and contract_option_id = 0 to indicate standing deductions
3. WHEN the payroll helper retrieves deductions, THE Payroll_Helper SHALL use the calculate_sick_leave_deductions_total function
4. WHEN calculate_sick_leave_deductions_total executes, THE Payroll_Helper SHALL call get_sick_leave_deductions_for_payroll to retrieve database records

### Requirement 5: Deduction Data Flow Integrity

**User Story:** As a system architect, I want to ensure sick leave deductions flow correctly from creation to display, so that data integrity is maintained throughout the payroll process.

#### Acceptance Criteria

1. WHEN a sick leave application is approved, THE LeavePolicy_Library SHALL create deduction records in ci_payslip_statutory_deductions
2. WHEN deduction records are created, THE LeavePolicy_Library SHALL set payslip_id = 0 and contract_option_id = 0 to indicate standing deductions
3. WHEN the payroll helper retrieves deductions, THE Payroll_Helper SHALL use the calculate_sick_leave_deductions_total function
4. WHEN calculate_sick_leave_deductions_total executes, THE Payroll_Helper SHALL call get_sick_leave_deductions_for_payroll to retrieve database records

### Requirement 6: Existing Payslip Handling

**User Story:** As a payroll administrator, I want to see sick leave deductions for both unpaid and paid payslips, so that I have complete visibility into all deductions.

#### Acceptance Criteria

1. WHEN viewing unpaid payroll records, THE Payroll_Grid SHALL display sick leave deductions from standing deduction records
2. WHEN viewing paid payroll records, THE Payroll_Grid SHALL display sick leave deductions from the associated payslip records
3. WHEN a payslip is created from standing deductions, THE Payroll_System SHALL copy sick leave deduction amounts to the payslip
4. WHEN displaying paid payslips, THE Payroll_Grid SHALL retrieve deduction amounts from ci_payslip_statutory_deductions where payslip_id matches the payslip

### Requirement 6: Existing Payslip Handling

**User Story:** As a payroll administrator, I want to see sick leave deductions for both unpaid and paid payslips, so that I have complete visibility into all deductions.

#### Acceptance Criteria

1. WHEN viewing unpaid payroll records, THE Payroll_Grid SHALL display sick leave deductions from standing deduction records
2. WHEN viewing paid payroll records, THE Payroll_Grid SHALL display sick leave deductions from the associated payslip records
3. WHEN a payslip is created from standing deductions, THE Payroll_System SHALL copy sick leave deduction amounts to the payslip
4. WHEN displaying paid payslips, THE Payroll_Grid SHALL retrieve deduction amounts from ci_payslip_statutory_deductions where payslip_id matches the payslip

### Requirement 7: Maternity Leave Deduction Parity

**User Story:** As a payroll administrator, I want maternity leave deductions to display using the same mechanism as sick leave deductions, so that all tiered leave deductions are handled consistently.

#### Acceptance Criteria

1. WHEN the payroll grid loads, THE Payroll_System SHALL retrieve maternity leave deductions using the same pattern as sick leave deductions
2. WHEN displaying maternity leave deductions, THE Payroll_Grid SHALL show them in a dedicated column
3. WHEN calculating net salary, THE Payroll_System SHALL subtract maternity leave deductions in the same manner as sick leave deductions
4. THE Payroll_System SHALL use calculate_maternity_leave_deductions_total function parallel to calculate_sick_leave_deductions_total

### Requirement 7: Maternity Leave Deduction Parity

**User Story:** As a payroll administrator, I want maternity leave deductions to display using the same mechanism as sick leave deductions, so that all tiered leave deductions are handled consistently.

#### Acceptance Criteria

1. WHEN the payroll grid loads, THE Payroll_System SHALL retrieve maternity leave deductions using the same pattern as sick leave deductions
2. WHEN displaying maternity leave deductions, THE Payroll_Grid SHALL show them in a dedicated column
3. WHEN calculating net salary, THE Payroll_System SHALL subtract maternity leave deductions in the same manner as sick leave deductions
4. THE Payroll_System SHALL use calculate_maternity_leave_deductions_total function parallel to calculate_sick_leave_deductions_total

### Requirement 8: Error Handling and Edge Cases

**User Story:** As a system administrator, I want the system to handle edge cases gracefully, so that the payroll grid remains stable and informative.

#### Acceptance Criteria

1. WHEN no sick leave deductions exist for an employee, THE Payroll_System SHALL return a total of 0.00 without errors
2. WHEN database queries fail, THE Payroll_System SHALL log the error and display 0.00 for deductions
3. WHEN an employee has deductions but no basic salary configured, THE Payroll_System SHALL still display the deduction amount
4. WHEN the salary_month format is invalid, THE Payroll_System SHALL handle the error gracefully and return empty results

### Requirement 8: Error Handling and Edge Cases

**User Story:** As a system administrator, I want the system to handle edge cases gracefully, so that the payroll grid remains stable and informative.

#### Acceptance Criteria

1. WHEN no sick leave deductions exist for an employee, THE Payroll_System SHALL return a total of 0.00 without errors
2. WHEN database queries fail, THE Payroll_System SHALL log the error and display 0.00 for deductions
3. WHEN an employee has deductions but no basic salary configured, THE Payroll_System SHALL still display the deduction amount
4. WHEN the salary_month format is invalid, THE Payroll_System SHALL handle the error gracefully and return empty results

### Requirement 9: Data Consistency Verification

**User Story:** As a developer, I want to verify that deduction data is consistent across all system components, so that debugging and maintenance are easier.

#### Acceptance Criteria

1. WHEN sick leave deductions are created, THE LeavePolicy_Library SHALL log the creation with employee_id, salary_month, and amount
2. WHEN deductions are retrieved for display, THE Payroll_Helper SHALL log the query parameters and result count
3. WHEN deductions are displayed in the grid, THE Payroll_Grid SHALL include data attributes for debugging
4. THE Payroll_System SHALL provide a way to trace a deduction from creation through to display


### Requirement 10: Multi-Month Leave Distribution

**User Story:** As a payroll administrator, I want sick leave deductions to be correctly distributed across multiple months, so that each month reflects only the deductions applicable to that month's portion of the leave.

#### Acceptance Criteria

1. WHEN a sick leave request spans multiple months, THE LeavePolicy_Library SHALL calculate deductions separately for each month
2. WHEN calculating monthly deductions, THE LeavePolicy_Library SHALL account for the cumulative days used to determine the correct tier for each day
3. WHEN a month contains days from multiple tiers, THE LeavePolicy_Library SHALL sum the deductions from all tiers into a single monthly total
4. FOR ALL months in a leave period, the sum of monthly deductions SHALL equal the total deduction for the entire leave period
