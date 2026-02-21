# Implementation Plan: Country-Based Leave Policy System

## Overview

This implementation plan breaks down the country-based leave policy system into discrete, manageable tasks. Each task builds on previous work and includes specific requirements references. The implementation follows a phased approach: database setup, core models and helpers, controller integration, UI updates, and testing.

## Tasks

- [ ] 1. Database Schema Setup
  - Create migration files for new tables and schema modifications
  - Execute migrations and verify schema integrity
  - _Requirements: 11.1, 11.2, 11.3, 11.4, 11.5, 11.6, 12.1, 11.a_

- [ ] 1.1 Create migration for ci_leave_policy_countries table
  - Create migration file: `database/migrations/YYYY_MM_DD_HHMMSS_create_leave_policy_countries_table.php`
  - Define table structure with all fields (policy_id, company_id, country_code, leave_type, service_years_min, service_years_max, entitlement_days, is_paid, payment_percentage, max_consecutive_days, requires_documentation, documentation_after_days, is_one_time, deduct_from_annual, policy_description_en, policy_description_ar, is_active, timestamps)
  - Add indexes for performance (idx_country_leave_type, idx_company_country)
  - _Requirements: 11.1, 11.2_

- [ ] 1.2 Create migration for ci_employee_leave_balances table
  - Create migration file: `database/migrations/YYYY_MM_DD_HHMMSS_create_employee_leave_balances_table.php`
  - Define table structure with all fields (balance_id, company_id, employee_id, leave_type, year, total_entitled, used_days, pending_days, remaining_days, carried_forward, last_calculated)
  - Add unique constraint on (employee_id, leave_type, year)
  - Add indexes for performance (idx_company_employee)
  - _Requirements: 8.1, 8.2, 8.3, 8.4_

- [ ] 1.3 Create migration to enhance ci_users table
  - Create migration file: `database/migrations/YYYY_MM_DD_HHMMSS_add_country_to_users_table.php`
  - Add country_code VARCHAR(10) field with default 'SA'
  - Add index on country_code
  - _Requirements: 12.1_

- [ ] 1.4 Create migration to enhance ci_leave_applications table
  - Create migration file: `database/migrations/YYYY_MM_DD_HHMMSS_enhance_leave_applications_table.php`
  - Add fields: country_code, service_years, policy_id, calculated_days, payment_percentage, documentation_provided
  - Add index on (country_code, policy_id)
  - _Requirements: 9.1, 9.3_

- [ ] 1.5 Create migration to add is_disability to ci_erp_users_details
  - Create migration file: `database/migrations/YYYY_MM_DD_HHMMSS_add_disability_to_users_details.php`
  - Add `is_disability` TINYINT(1) DEFAULT 0
  - _Requirements: 11.a_

- [ ] 1.5 Create seed data for country leave policies
  - Create seeder file: `database/seeds/LeaveCountryPoliciesSeeder.php`
  - Insert policies for all 4 countries (SA, EG, KW, QA) and 6 leave types (annual, sick, emergency, hajj, maternity, paternity)
  - Include service duration tiers as per requirements
  - _Requirements: 1.1, 2.1, 2.2, 2.3, 2.4, 2.5, 2.6, 2.7, 3.1, 3.2, 3.3, 3.4, 3.5, 4.1, 4.2, 4.3, 4.4, 5.1, 5.2, 5.3, 5.4, 6.1, 6.2, 6.3, 6.4, 6.5, 6.6, 6.7, 6.8_

- [ ] 1.7 Create seed data for Emergency Leave Types (Sub-types)
  - Ensure leave types exist for: "Emergency (Death of Relative)", "Emergency (Paternity)" etc. if not using reason-based logic.
  - _Decision_: We will use a flexible mapping or assume standard types. For now, standard types enriched with policy logic.

- [ ] 1.6 Write unit tests for migrations
  - Test table creation succeeds
  - Test indexes are created correctly
  - Test constraints are enforced
  - _Requirements: 11.6_

- [ ] 2. Create Model Layer
  - Implement LeavePolicyModel and EmployeeLeaveBalanceModel
  - Add methods for policy retrieval and balance management
  - _Requirements: 1.3, 1.4, 8.1, 8.3, 8.4_

- [ ] 2.1 Create LeavePolicyModel
  - Create file: `app/Models/LeavePolicyModel.php`
  - Extend CodeIgniter Model class
  - Define table, primary key, and allowed fields
  - Implement getApplicablePolicy($company_id, $country_code, $leave_type, $service_years)
  - Implement getCountryPolicies($company_id, $country_code)
  - Implement getAllPolicies($company_id) for admin views
  - _Requirements: 1.3, 1.4, 7.5_

- [ ] 2.2 Write property test for LeavePolicyModel
  - **Property 18: Leave Application Country Policy Validation**
  - **Validates: Requirements 9.1, 8.5**
  - Test that getApplicablePolicy returns correct policy for any country, leave type, and service duration
  - Test that policy country_code matches input country
  - Test that service_years falls within policy's min/max range

- [ ] 2.3 Create EmployeeLeaveBalanceModel
  - Create file: `app/Models/EmployeeLeaveBalanceModel.php`
  - Extend CodeIgniter Model class
  - Define table, primary key, and allowed fields
  - Implement getBalance($employee_id, $leave_type, $year)
  - Implement getEmployeeBalances($employee_id, $year)
  - Implement updateBalance($employee_id, $leave_type, $year, $days, $action)
  - Implement createBalance($employee_id, $leave_type, $year, $entitled_days)
  - _Requirements: 8.1, 8.2, 8.3, 8.4_

- [ ] 2.4 Write property test for balance invariant
  - **Property 5: Leave Balance Tracking**
  - **Validates: Requirements 3.6, 8.3**
  - Test that after deducting N days: used_days increases by N, remaining_days decreases by N
  - Test invariant: total_entitled = used_days + remaining_days + pending_days

- [ ] 2.5 Write property test for balance restoration
  - **Property 6: Leave Balance Restoration**
  - **Validates: Requirements 8.4**
  - Test that restoring N days reverses the deduction correctly
  - Test that balance returns to original state after deduct then restore

- [ ] 3. Create Helper Functions
  - Implement leave_policy_helper.php with calculation and validation functions
  - _Requirements: 7.1, 7.3, 7.4, 7.5, 9.1, 9.2, 9.3_

- [ ] 3.1 Create leave_policy_helper.php
  - Create file: `app/Helpers/leave_policy_helper.php`
  - Implement calculate_service_duration($hire_date)
  - Implement get_employee_leave_policy($employee_id, $leave_type)
  - Implement calculate_leave_entitlement($employee_id, $leave_type, $year)
  - Implement calculate_working_days($start_date, $end_date, $company_id)
  - Implement validate_leave_request($employee_id, $leave_type, $start_date, $end_date, $year)
  - Implement initialize_employee_leave_balances($employee_id, $year)
  - Implement get_country_name($country_code, $lang)
  - Implement get_leave_type_name($leave_type, $lang)
  - _Requirements: 7.1, 7.4, 9.1, 9.2, 9.3, 14.1, 14.2_

- [ ] 3.2 Write property test for service duration calculation
  - **Property 13: Service Duration Calculation**
  - **Validates: Requirements 7.1, 7.4**
  - Test that calculate_service_duration returns correct years for any hire date
  - Test that duration is >= expected_years and < expected_years + 1

- [ ] 3.3 Write property test for working days calculation
  - **Property 16: Working Days Calculation Excludes Weekends and Holidays**
  - **Validates: Requirements 9.3**
  - Test that working days never exceed total days
  - Test that working days exclude Fridays and Saturdays
  - Test that working days exclude company holidays

- [ ] 3.4 Write property test for leave entitlement by service duration
  - **Property 3: Service-Duration-Based Annual Leave Entitlement**
  - **Validates: Requirements 2.1, 2.2, 2.3, 2.4, 2.5, 2.6, 2.7**
  - Test that employees with service < 5 years in SA get 21 days
  - Test that employees with service >= 5 years in SA get 30 days
  - Test similar rules for EG, KW, QA

- [ ] 3.5 Write property test for insufficient balance rejection
  - **Property 15: Leave Balance Insufficient Funds Rejection**
  - **Validates: Requirements 9.2**
  - Test that requesting more days than available balance is rejected
  - Test error message includes available and requested days

- [ ] 4. Enhance Leave Controller
  - Modify app/Controllers/Erp/Leave.php to integrate country-based policies
  - Update leave application submission and approval workflows
  - _Requirements: 9.1, 9.2, 9.3, 9.4, 9.5, 9.6_

- [ ] 4.1 Update leave application submission method
  - Modify add_leave() method in Leave.php
  - Add country_code and service_years to leave application data
  - Call validate_leave_request() before saving
  - Calculate working days using calculate_working_days()
  - Store policy_id and calculated_days in leave application
  - Return validation errors if validation fails
  - _Requirements: 9.1, 9.2, 9.3, 9.4_

- [ ] 4.2 Write property test for leave application validation
  - **Property 17: Leave Application Required Fields Validation**
  - **Validates: Requirements 9.4**
  - Test that applications missing required fields are rejected
  - Test error message lists all missing fields

- [ ] 4.3 Write property test for overlapping leave prevention
  - **Property 20: Overlapping Leave Prevention**
  - **Validates: Requirements 15.6**
  - Test that overlapping leave applications are rejected
  - Test error message indicates overlapping dates

- [ ] 4.4 Update leave approval method
  - Modify approve_leave() method in Leave.php
  - Load EmployeeLeaveBalanceModel
  - Call updateBalance() to deduct days from balance
  - Wrap in database transaction for atomicity
  - Send approval notification
  - _Requirements: 8.3, 9.5_

- [ ] 4.5 Update leave rejection method
  - Modify reject_leave() method in Leave.php
  - If leave was previously approved, restore balance using updateBalance()
  - Send rejection notification
  - _Requirements: 8.4, 9.5_

- [ ] 5. Create Leave Policy Admin Controller
  - Create new controller for managing country-based leave policies
  - _Requirements: 1.2, 1.4, 1.5_

- [ ] 5.1 Create LeavePolicy controller
  - Create file: `app/Controllers/Erp/LeavePolicy.php`
  - Implement index() to list all policies
  - Implement create() to add new policy
  - Implement edit() to update existing policy
  - Implement delete() to deactivate policy
  - Implement view_country_policies($country_code) to show policies by country
  - Add authorization checks (company admin only)
  - _Requirements: 1.2, 1.4, 1.5_

- [ ] 5.2 Write property test for policy creation validation
  - **Property 2: Policy Creation Validation**
  - **Validates: Requirements 1.2**
  - Test that policy creation without country_code is rejected
  - Test error message indicates missing country

- [ ] 5.3 Write property test for policy storage and retrieval
  - **Property 1: Country Policy Storage and Retrieval**
  - **Validates: Requirements 1.1, 1.3**
  - Test that storing a policy and retrieving it returns equivalent data
  - Test round-trip for all countries and leave types

- [ ] 6. Update Employee Management
  - Modify Employees controller to handle country assignment
  - _Requirements: 12.2, 12.3, 12.4, 12.5_

- [ ] 6.1 Add country field to employee creation form
  - Modify app/Views/erp/employees/add_employee.php
  - Add country dropdown with options: SA, EG, KW, QA
  - Make country field required
  - _Requirements: 12.2, 12.4_

- [ ] 6.2 Add country field to employee edit form
  - Modify app/Views/erp/employees/edit_employee.php
  - Add country dropdown
  - Add "Has Disability" toggle (Yes/No)
  - Add JavaScript to detect country changes and warn about balance recalculation
  - _Requirements: 12.2, 12.3, 11.a_

- [ ] 6.3 Update employee save method
  - Modify Employees controller save/update methods
  - Validate country_code is provided
  - If country changed, call recalculate_employee_balances()
  - Initialize leave balances for new employees
  - _Requirements: 12.3, 12.4_

- [ ] 6.4 Write property test for country change recalculation
  - **Property 21: Employee Country Change Triggers Recalculation**
  - **Validates: Requirements 12.3**
  - Test that changing country from A to B recalculates entitlements using country B policies
  - Test that balances reflect new country's rules

- [ ] 7. Create Leave Balance Views
  - Create UI for employees to view their leave balances
  - _Requirements: 8.1, 8.2, 8.6_

- [ ] 7.1 Create leave balance dashboard view
  - Create file: `app/Views/erp/leave/leave_balance_dashboard.php`
  - Display all leave types with balances (total, used, pending, remaining)
  - Show balances in both days and hours
  - Display employee's country and service duration
  - Add visual indicators (progress bars, color coding)
  - _Requirements: 8.1, 8.2, 8.6, 12.5_

- [ ] 7.2 Write property test for balance display completeness
  - **Property 24: Leave Balance Display Completeness**
  - **Validates: Requirements 8.1, 8.2**
  - Test that querying balances returns all applicable leave types
  - Test that each balance includes total, used, and remaining fields

- [ ] 7.3 Add leave balance route
  - Modify app/Config/Routes.php
  - Add route: `$routes->get('erp/leave-balance/', 'Leave::leave_balance');`
  - _Requirements: 8.1_

- [ ] 7.4 Implement leave_balance() method in Leave controller
  - Load EmployeeLeaveBalanceModel
  - Get current year balances for logged-in employee
  - Pass data to leave_balance_dashboard view
  - _Requirements: 8.1, 8.2_

- [ ] 8. Implement Leave Type Specific Validations
  - Add validation rules specific to each leave type
  - _Requirements: 4.5, 5.6, 6.9, 15.1, 15.2, 15.3, 15.4, 15.5_

- [ ] 8.1 Add Hajj leave one-time validation
  - In validate_leave_request(), check if employee has previous approved Hajj leave
  - Reject if Hajj leave already taken
  - _Requirements: 5.6, 15.1_

- [ ] 8.2 Write property test for Hajj leave one-time restriction
  - **Property 9: Hajj Leave One-Time Restriction**
  - **Validates: Requirements 5.6, 15.1**
  - Test that second Hajj leave application is rejected
  - Test error message indicates one-time restriction

- [ ] 8.3 Write property test for Hajj leave balance independence
  - **Property 10: Hajj Leave Balance Independence**
  - **Validates: Requirements 5.7**
  - Test that approving Hajj leave does not affect annual leave balance
  - Test that annual balance remains unchanged

- [ ] 8.4 Add gender validation for maternity/paternity leave
  - In validate_leave_request(), check employee gender
  - Reject maternity leave if gender != female
  - Reject paternity leave if gender != male
  - _Requirements: 15.3, 15.4_

- [ ] 8.5 Write property test for maternity leave gender validation
  - **Property 11: Maternity Leave Gender Validation**
  - **Validates: Requirements 15.3**
  - Test that male employees cannot apply for maternity leave
  - Test error message indicates gender mismatch

- [ ] 8.6 Write property test for paternity leave gender validation
  - **Property 12: Paternity Leave Gender Validation**
  - **Validates: Requirements 15.4**
  - Test that female employees cannot apply for paternity leave
  - Test error message indicates gender mismatch

- [ ] 8.7 Add documentation requirement validation
  - In validate_leave_request(), check if leave duration exceeds documentation_after_days
  - Require documentation_provided = 1 if threshold exceeded
  - _Requirements: 15.2_

- [ ] 8.8 Write property test for sick leave documentation requirement
  - **Property 23: Sick Leave Documentation Requirement**
  - **Validates: Requirements 15.2**
  - Test that sick leave exceeding threshold requires documentation
  - Test that applications without documentation are rejected

- [ ] 8.9 Add maximum consecutive days validation
  - In validate_leave_request(), check if requested days exceed max_consecutive_days
  - Reject if exceeded
  - _Requirements: 15.5_

- [ ] 8.10 Write property test for maximum consecutive days enforcement
  - **Property 19: Maximum Consecutive Days Enforcement**
  - **Validates: Requirements 15.5**
  - Test that leave exceeding max_consecutive_days is rejected
  - Test error message indicates maximum allowed

- [ ] 8.11 Add emergency leave reason validation
  - In validate_leave_request(), check if reason is provided for emergency leave
  - Reject if reason is empty
  - _Requirements: 4.5_

- [ ] 8.12 Write property test for emergency leave reason validation
  - **Property 8: Emergency Leave Reason Validation**
  - **Validates: Requirements 4.5**
  - Test that emergency leave without reason is rejected
  - Test error message indicates missing reason

- [ ] 9. Implement Multi-Language Support
  - Add translations for leave types and countries
  - _Requirements: 14.1, 14.2, 14.3, 14.4, 14.5_

- [ ] 9.1 Add English translations
  - Modify app/Language/en/Leave.php
  - Add translations for all leave types (annual, sick, emergency, hajj, maternity, paternity)
  - Add translations for all countries (SA, EG, KW, QA)
  - Add translations for new UI labels and messages
  - _Requirements: 14.1, 14.2, 14.3_

- [ ] 9.2 Add Arabic translations
  - Modify app/Language/ar/Leave.php
  - Add Arabic translations for all leave types
  - Add Arabic translations for all countries
  - Add Arabic translations for new UI labels and messages
  - _Requirements: 14.1, 14.2, 14.3_

- [ ] 9.3 Write property test for translation consistency
  - **Property 22: Leave Type Translation Consistency**
  - **Validates: Requirements 14.1, 14.2, 14.3**
  - Test that get_leave_type_name() returns consistent translations
  - Test that get_country_name() returns consistent translations
  - Test both English and Arabic translations

- [ ] 10. Create Leave Policy Admin Views
  - Create UI for managing country-based leave policies
  - _Requirements: 1.2, 1.4, 1.5_

- [ ] 10.1 Create policy list view
  - Create file: `app/Views/erp/leave_policy/policy_list.php`
  - Display table of all policies grouped by country
  - Show leave type, service years range, entitlement days, payment percentage
  - Add edit and delete buttons
  - Add filter by country dropdown
  - _Requirements: 1.4, 1.5_

- [ ] 10.2 Create policy create/edit form
  - Create file: `app/Views/erp/leave_policy/policy_form.php`
  - Add form fields for all policy attributes
  - Add country dropdown (SA, EG, KW, QA)
  - Add leave type dropdown (annual, sick, emergency, hajj, maternity, paternity)
  - Add service years min/max inputs
  - Add entitlement days input
  - Add payment percentage input
  - Add checkboxes for is_paid, requires_documentation, is_one_time, deduct_from_annual
  - Add bilingual description fields (English and Arabic)
  - _Requirements: 1.2, 14.5_

- [ ] 10.3 Add policy management routes
  - Modify app/Config/Routes.php
  - Add routes for policy CRUD operations
  - _Requirements: 1.2, 1.4_

- [ ] 11. Create Reporting Features
  - Add reports for leave usage by country and leave type
  - _Requirements: 10.1, 10.2, 10.3, 10.4, 10.5, 10.6_

- [ ] 11.1 Create leave usage by country report
  - Modify app/Controllers/Erp/Reports.php
  - Add method: leave_usage_by_country()
  - Query leave applications grouped by country
  - Calculate total days taken, pending, and available
  - Generate PDF and Excel export options
  - _Requirements: 10.1, 10.5_

- [ ] 11.2 Create leave usage by type report
  - Add method: leave_usage_by_type()
  - Query leave applications grouped by leave type
  - Show breakdown by country
  - Generate PDF and Excel export options
  - _Requirements: 10.2, 10.5_

- [ ] 11.3 Create employee leave summary report
  - Add method: employee_leave_summary()
  - Show all employees with their leave balances
  - Filter by country, department, designation
  - Calculate leave cost based on salary
  - _Requirements: 10.3, 10.4_

- [ ] 11.4 Create leave trends report
  - Add method: leave_trends()
  - Show leave usage over time (monthly, quarterly, yearly)
  - Group by country and leave type
  - Add charts for visualization
  - _Requirements: 10.6_

- [ ] 11.5 Create report views
  - Create files in app/Views/erp/reports/
  - leave_usage_by_country.php
  - leave_usage_by_type.php
  - employee_leave_summary.php
  - leave_trends.php
  - _Requirements: 10.1, 10.2, 10.3, 10.6_

- [ ] 12. Data Migration and Initialization
  - Migrate existing leave data to new schema
  - Initialize balances for all employees
  - _Requirements: 13.1, 13.2, 13.3, 13.4, 13.5_

- [ ] 12.1 Create data migration script
  - Create file: `app/Commands/MigrateLeaveData.php`
  - Assign default country (SA) to employees without country
  - Map existing leave types to new leave type codes
  - Recalculate leave balances for all employees
  - Preserve historical leave application records
  - Log all migration activities
  - _Requirements: 13.1, 13.2, 13.3, 13.4, 13.5_

- [ ] 12.2 Create balance initialization command
  - Create file: `app/Commands/InitializeLeaveBalances.php`
  - For each employee, call initialize_employee_leave_balances()
  - Handle employees with different countries and service durations
  - Log initialization results
  - _Requirements: 13.2, 13.3_

- [ ] 12.3 Run migration and verify data integrity
  - Execute migration script
  - Verify all employees have country assigned
  - Verify all employees have balances initialized
  - Verify historical leave applications preserved
  - Generate migration report
  - _Requirements: 13.1, 13.2, 13.3, 13.4, 13.5_

- [ ] 13. Checkpoint - Ensure all tests pass
  - Run all unit tests and property tests
  - Verify test coverage meets goals (90%+ for unit tests, 100+ iterations for property tests)
  - Fix any failing tests
  - Ask the user if questions arise

- [ ] 14. Integration and Final Testing
  - Test complete workflows end-to-end
  - Verify all features work together correctly
  - _Requirements: All_

- [ ] 14.1 Test leave application workflow
  - Create test employee with country SA
  - Initialize leave balances
  - Submit annual leave application
  - Verify validation passes
  - Approve leave
  - Verify balance updated correctly
  - Verify notifications sent
  - _Requirements: 9.1, 9.2, 9.3, 9.4, 9.5, 8.3_

- [ ] 14.2 Test country change workflow
  - Create test employee with country SA and 3 years service
  - Verify annual leave balance is 21 days
  - Change employee country to EG
  - Verify annual leave balance recalculated (21 days for <10 years)
  - Change service duration to 10 years
  - Verify annual leave balance updated to 30 days
  - _Requirements: 12.3, 7.3, 2.3, 2.4_

- [ ] 14.3 Test Hajj leave one-time restriction
  - Create test employee
  - Submit and approve Hajj leave
  - Attempt to submit second Hajj leave
  - Verify second application is rejected
  - Verify error message indicates one-time restriction
  - _Requirements: 5.6, 15.1_

- [ ] 14.4 Test sick leave payment tiers
  - Create test employee in SA
  - Submit sick leave for 30 days
  - Verify payment_percentage = 100%
  - Submit sick leave for 60 more days
  - Verify payment_percentage = 75%
  - _Requirements: 3.1_

- [ ] 14.5 Test maternity/paternity gender validation
  - Create male employee
  - Attempt to submit maternity leave
  - Verify rejection with gender mismatch error
  - Create female employee
  - Attempt to submit paternity leave
  - Verify rejection with gender mismatch error
  - _Requirements: 15.3, 15.4_

- [ ] 14.6 Run full property test suite
  - Execute all property tests with 100+ iterations each
  - Verify all properties pass
  - Document any edge cases discovered
  - Fix any issues found

- [ ] 15. Documentation and Deployment
  - Create user documentation
  - Prepare deployment instructions
  - _Requirements: All_

- [ ] 15.1 Create user documentation
  - Document how to configure country-based leave policies
  - Document how to assign countries to employees
  - Document how employees view and request leave
  - Document how admins approve leave and view reports
  - Include screenshots and examples
  - Provide documentation in both English and Arabic
  - _Requirements: 14.4, 14.5_

- [ ] 15.2 Create admin guide
  - Document policy configuration best practices
  - Document migration process for existing systems
  - Document troubleshooting common issues
  - Document database backup and recovery procedures
  - _Requirements: 13.1, 13.2, 13.3, 13.4, 13.5_

- [ ] 15.3 Prepare deployment checklist
  - Database backup before migration
  - Run migrations in correct order
  - Execute data migration script
  - Initialize leave balances
  - Verify data integrity
  - Test critical workflows
  - Monitor for errors
  - _Requirements: 13.1, 13.2, 13.3, 13.4, 13.5_

- [ ] 16. Final Checkpoint - Ensure all tests pass
  - Run complete test suite (unit + property + integration)
  - Verify all features working correctly
  - Verify documentation complete
  - Ask the user if questions arise

## Notes

- All tasks are required for comprehensive implementation
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation
- Property tests validate universal correctness properties (100+ iterations each)
- Unit tests validate specific examples and edge cases
- Integration tests validate end-to-end workflows
- The implementation follows a bottom-up approach: database → models → helpers → controllers → views
- All database operations use transactions for data consistency
- All user-facing text supports both English and Arabic
- The system maintains backward compatibility with existing leave data
