# Requirements Document: Country-Based Leave Policy System

## Introduction

This document outlines the requirements for implementing a comprehensive country-based leave policy system that manages different types of leaves (Annual, Sick, Emergency, Hajj, Maternity/Paternity) with country-specific rules for Saudi Arabia, Egypt, Kuwait, and Qatar. The system will automatically calculate leave entitlements based on employee country, years of service, and leave type.

## Glossary

- **System**: The HR Management System (First Time HR)
- **Leave_Policy_Engine**: The component responsible for calculating leave entitlements based on country and service duration
- **Employee**: A user with staff role in the system
- **Company_Admin**: A user with company administrator privileges
- **Leave_Application**: A request submitted by an employee for time off
- **Service_Duration**: The number of years an employee has worked for the company
- **Leave_Balance**: The available leave days/hours for an employee
- **Country_Policy**: The set of leave rules specific to a country
- **Leave_Type**: Category of leave (Annual, Sick, Emergency, Hajj, Maternity/Paternity)
- **Paid_Leave**: Leave where the employee receives full salary
- **Unpaid_Leave**: Leave where the employee does not receive salary
- **Working_Days**: Days excluding weekends and holidays

## Requirements

### Requirement 1: Country-Based Leave Policy Configuration

**User Story:** As a Company Admin, I want to configure country-specific leave policies, so that employees receive accurate leave entitlements based on their country of employment.

#### Acceptance Criteria

1. THE System SHALL support leave policies for Saudi Arabia, Egypt, Kuwait, and Qatar
2. WHEN a Company Admin creates a leave policy, THE System SHALL require country selection
3. THE System SHALL store leave policy configurations for each leave type per country
4. THE System SHALL allow Company Admin to view and edit existing country-based leave policies
5. WHEN displaying leave policies, THE System SHALL show all five leave types (Annual, Sick, Emergency, Hajj, Maternity/Paternity) for each country

### Requirement 2: Annual Leave Calculation

**User Story:** As an Employee, I want my annual leave entitlement to be calculated automatically based on my country and years of service, so that I receive the correct number of leave days.

#### Acceptance Criteria

1. WHEN an Employee in Saudi Arabia has less than 5 years of service, THE System SHALL allocate 21 working days of annual leave
2. WHEN an Employee in Saudi Arabia has 5 or more years of service, THE System SHALL allocate 30 working days of annual leave
3. WHEN an Employee in Egypt has less than 10 years of service, THE System SHALL allocate 21 working days of annual leave
4. WHEN an Employee in Egypt has 10 or more years of service, THE System SHALL allocate 30 working days of annual leave
5. WHEN an Employee in Kuwait works, THE System SHALL allocate 30 working days of annual leave with no service duration condition
6. WHEN an Employee in Qatar works, THE System SHALL allocate 3 weeks (21 days) for employees with less than 5 years of service
7. WHEN an Employee in Qatar has 5 or more years of service, THE System SHALL allocate 4 weeks (28 days) of annual leave

### Requirement 3: Sick Leave Calculation

**User Story:** As an Employee, I want my sick leave entitlement to be calculated based on my country's labor law, so that I can take medical leave when needed.

#### Acceptance Criteria

1. WHEN an Employee in Saudi Arabia takes sick leave, THE System SHALL provide up to 30 days at full salary, then 60 days at 75% salary
2. WHEN an Employee in Egypt takes sick leave, THE System SHALL provide up to 90 days at full salary (first 6 months at full pay, next 9 months at 75% pay)
3. WHEN an Employee in Kuwait takes sick leave, THE System SHALL provide up to 75 days (first month at full pay, next 2 months at 75% pay)
4. WHEN an Employee in Qatar takes sick leave, THE System SHALL provide up to 14 days at full salary (after 3 months probation)
5. THE System SHALL track sick leave usage and remaining balance for each employee
6. WHEN sick leave exceeds paid duration, THE System SHALL mark additional days as unpaid

### Requirement 4: Emergency Leave Calculation

**User Story:** As an Employee, I want to request emergency leave for urgent personal matters, so that I can handle family emergencies.

#### Acceptance Criteria

1. WHEN an Employee in Saudi Arabia requests emergency leave, THE System SHALL allow up to 5 days per year (paid, for marriage or death)
2. WHEN an Employee in Egypt requests emergency leave, THE System SHALL allow up to 7 days per year (6 months paid)
3. WHEN an Employee in Kuwait requests emergency leave, THE System SHALL allow up to 4 days per year (paid, after probation)
4. WHEN an Employee in Qatar requests emergency leave, THE System SHALL allow up to 5 days per year (after one year of service)
5. THE System SHALL require the employee to specify the emergency reason
6. THE System SHALL deduct emergency leave from the employee's annual balance

### Requirement 5: Hajj Leave Calculation

**User Story:** As a Muslim Employee, I want to request Hajj leave once during my employment, so that I can perform the religious pilgrimage.

#### Acceptance Criteria

1. WHEN an Employee in Saudi Arabia requests Hajj leave, THE System SHALL allow 10-15 days (paid, after 2 years of service)
2. WHEN an Employee in Egypt requests Hajj leave, THE System SHALL allow as per ministry regulations or labor law
3. WHEN an Employee in Kuwait requests Hajj leave, THE System SHALL allow 21 days (paid, after 2 years of service)
4. WHEN an Employee in Qatar requests Hajj leave, THE System SHALL allow 3 days (paid, after one year of service)
5. THE System SHALL track if an employee has already taken Hajj leave
6. THE System SHALL prevent employees from requesting Hajj leave more than once during their employment
7. THE System SHALL not deduct Hajj leave from annual leave balance

### Requirement 6: Maternity and Paternity Leave Calculation

**User Story:** As an Employee expecting a child, I want to request maternity or paternity leave, so that I can care for my newborn.

#### Acceptance Criteria

1. WHEN a Female Employee in Saudi Arabia requests maternity leave, THE System SHALL provide 10 weeks (70 days) of paid leave
2. WHEN a Female Employee in Egypt requests maternity leave, THE System SHALL provide 4 months (120 days) of paid leave
3. WHEN a Female Employee in Kuwait requests maternity leave, THE System SHALL provide 70 days (paid, after probation)
4. WHEN a Female Employee in Qatar requests maternity leave, THE System SHALL provide 50 days of paid leave (after one year of service)
5. WHEN a Male Employee in Saudi Arabia requests paternity leave, THE System SHALL provide 3 days of paid leave
6. WHEN a Male Employee in Egypt requests paternity leave, THE System SHALL provide 3 days of paid leave
7. WHEN a Male Employee in Kuwait requests paternity leave, THE System SHALL provide 3 days of paid leave (after probation)
8. WHEN a Male Employee in Qatar requests paternity leave, THE System SHALL provide 3 days of paid leave for newborn
9. THE System SHALL require medical documentation for maternity leave requests

### Requirement 7: Service Duration Tracking

**User Story:** As the System, I want to automatically track employee service duration, so that leave entitlements are calculated accurately.

#### Acceptance Criteria

1. THE System SHALL calculate service duration from the employee's hire date to the current date
2. THE System SHALL update service duration calculations daily
3. WHEN an employee's service duration crosses a threshold (e.g., 5 years), THE System SHALL automatically update their leave entitlements
4. THE System SHALL display the employee's current service duration in years and months
5. THE System SHALL use service duration to determine eligibility for different leave types

### Requirement 8: Leave Balance Management

**User Story:** As an Employee, I want to view my current leave balances for all leave types, so that I know how much leave I have available.

#### Acceptance Criteria

1. THE System SHALL display current leave balance for each leave type
2. THE System SHALL show used leave days and remaining leave days separately
3. WHEN a leave application is approved, THE System SHALL deduct the days from the appropriate leave balance
4. WHEN a leave application is rejected or cancelled, THE System SHALL restore the days to the leave balance
5. THE System SHALL calculate leave balances based on the employee's country policy
6. THE System SHALL display leave balance in both days and hours format

### Requirement 9: Leave Application Workflow

**User Story:** As an Employee, I want to submit leave applications that follow my country's leave policy rules, so that my requests are validated automatically.

#### Acceptance Criteria

1. WHEN an Employee submits a leave application, THE System SHALL validate against the employee's country policy
2. WHEN an Employee requests more days than available balance, THE System SHALL reject the application with an error message
3. WHEN an Employee submits a leave application, THE System SHALL calculate the number of working days excluding weekends and holidays
4. THE System SHALL require employees to specify leave type, start date, end date, and reason
5. WHEN a leave application is submitted, THE System SHALL send notifications to the appropriate approvers
6. THE System SHALL enforce minimum notice periods as per country policy

### Requirement 16: Disability Support (Egypt)

**User Story:** As an HR Manager, I want to mark employees with disabilities so that their leave entitlements are calculated correctly according to Egyptian law.

#### Acceptance Criteria

1. THE System SHALL add `is_disability` (0/1) field to employee details table
2. THE System SHALL provide a Yes/No toggle for disability in employee profile
3. WHEN an employee is marked as disabled AND country is Egypt, THE System SHALL increase annual leave entitlement to 45 days

### Requirement 10: Leave Policy Reporting

**User Story:** As a Company Admin, I want to generate reports on leave usage by country and leave type, so that I can analyze leave patterns and compliance.

#### Acceptance Criteria

1. THE System SHALL generate leave usage reports filtered by country
2. THE System SHALL generate leave usage reports filtered by leave type
3. THE System SHALL show total leave days taken, pending, and available for each employee
4. THE System SHALL calculate leave cost based on employee salary and leave days
5. THE System SHALL export leave reports to PDF and Excel formats
6. THE System SHALL display leave trends over time (monthly, quarterly, yearly)

### Requirement 11: Database Schema for Country Policies

**User Story:** As a System Administrator, I want a flexible database schema to store country-based leave policies, so that policies can be easily maintained and extended.

#### Acceptance Criteria

1. THE System SHALL create a table `ci_leave_policy_countries` to store country-specific leave policies
2. THE System SHALL store leave type, country, service duration thresholds, and entitlement days
3. THE System SHALL support multiple service duration tiers per leave type per country
4. THE System SHALL link leave policies to the employee's country field
5. THE System SHALL allow policy versioning for historical tracking
6. THE System SHALL validate data integrity when inserting or updating leave policies

### Requirement 11.a: Employee Disability Field

**User Story:** As a System Administrator, I want to store disability status for employees.

#### Acceptance Criteria

1. THE System SHALL add `is_disability` column to `ci_erp_users_details`
2. Default value SHALL be 0 (No)

### Requirement 12: Employee Country Assignment

**User Story:** As a Company Admin, I want to assign a country to each employee, so that the correct leave policy is applied.

#### Acceptance Criteria

1. THE System SHALL add a country field to the employee profile
2. THE System SHALL provide a dropdown list of supported countries (Saudi Arabia, Egypt, Kuwait, Qatar)
3. WHEN an employee's country is changed, THE System SHALL recalculate their leave entitlements
4. THE System SHALL require country selection when creating a new employee
5. THE System SHALL display the employee's country in their profile and leave balance views

### Requirement 13: Leave Policy Migration

**User Story:** As a System Administrator, I want to migrate existing leave data to the new country-based system, so that historical data is preserved.

#### Acceptance Criteria

1. THE System SHALL provide a migration script to convert existing leave types to country-based policies
2. THE System SHALL assign default country (Saudi Arabia) to existing employees without a country
3. THE System SHALL recalculate leave balances for all employees based on new policies
4. THE System SHALL preserve historical leave application records
5. THE System SHALL log all migration activities for audit purposes

### Requirement 14: Multi-Language Support

**User Story:** As an Employee, I want to view leave policies and applications in Arabic or English, so that I can understand the information in my preferred language.

#### Acceptance Criteria

1. THE System SHALL display leave type names in both Arabic and English
2. THE System SHALL display country names in both Arabic and English
3. THE System SHALL translate leave policy descriptions to Arabic and English
4. THE System SHALL allow users to switch between Arabic and English interfaces
5. THE System SHALL store leave policy text in both languages

### Requirement 15: Leave Policy Validation Rules

**User Story:** As the System, I want to enforce leave policy validation rules, so that leave applications comply with country-specific regulations.

#### Acceptance Criteria

1. WHEN an Employee requests Hajj leave for the second time, THE System SHALL reject the application
2. WHEN an Employee requests sick leave, THE System SHALL require medical documentation after a threshold (e.g., 3 days)
3. WHEN an Employee requests maternity leave, THE System SHALL verify the employee's gender is female
4. WHEN an Employee requests paternity leave, THE System SHALL verify the employee's gender is male
5. THE System SHALL enforce maximum consecutive leave days as per country policy
6. THE System SHALL prevent leave applications that overlap with existing approved leaves
