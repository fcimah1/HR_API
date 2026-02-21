# Requirements Document

## Introduction

The leave hours calculation feature in the HR system currently has a critical flaw: it stores leave entitlements in days but fails to properly convert these days to hours based on each employee's office shift configuration. This leads to incorrect leave balance calculations because employees have different working hours per day (8 hours, 10 hours, etc.) depending on their assigned shift.

This specification addresses the calculation logic for two types of leave requests:
1. Full day leave requests (multi-day leave with start and end dates)
2. Hourly permission requests (partial day leave with start and end times)

The system must accurately calculate working hours by considering employee shift configurations, excluding non-working days, company holidays, and break times.

## Glossary

- **Leave_System**: The HR leave management system that processes leave requests and manages leave balances
- **Office_Shift**: A configuration defining an employee's working schedule including daily hours, in/out times, and break periods
- **Working_Day**: A day where the employee's shift has a non-empty in_time value for that day of the week
- **Leave_Hours**: The calculated total hours to be deducted from an employee's leave balance
- **Hours_Per_Day**: The number of working hours in a standard working day for a specific office shift (excluding breaks)
- **Quota_Unit**: The unit in which leave entitlements are stored (currently 'days')
- **Break_Time**: Non-working time during a shift (lunch break) that should not be counted in leave calculations
- **Company_Holiday**: A date stored in ci_holidays table that should be excluded from working days calculation
- **Hourly_Permission**: A leave request for a portion of a single day, specified by start and end times

## Requirements

### Requirement 1: Shift Assignment Validation

**User Story:** As an employee, I want to be prevented from requesting leave if I don't have an office shift assigned, so that the system can accurately calculate my leave hours.

#### Acceptance Criteria

1. WHEN an employee attempts to submit a leave request, THE Leave_System SHALL verify the employee has an office_shift_id assigned
2. IF an employee has no office_shift_id assigned, THEN THE Leave_System SHALL prevent the leave request submission and display an error message
3. WHEN displaying the shift validation error, THE Leave_System SHALL show the message "You must have an office shift assigned before requesting leave" in the user's language
4. THE Leave_System SHALL validate shift assignment before any leave hours calculation occurs

### Requirement 2: Working Days Calculation for Full Day Leave

**User Story:** As an employee requesting multi-day leave, I want the system to calculate only my actual working days, so that weekends and holidays don't count against my leave balance.

#### Acceptance Criteria

1. WHEN calculating working days between from_date and to_date, THE Leave_System SHALL exclude days where the employee's shift has empty in_time for that day of the week
2. WHEN calculating working days, THE Leave_System SHALL exclude dates that exist in the ci_holidays table for the employee's company
3. WHEN a leave request spans multiple days, THE Leave_System SHALL count only the days that are both working days in the shift AND not company holidays
4. THE Leave_System SHALL iterate through each date in the range and evaluate it individually against shift configuration and holiday calendar

### Requirement 3: Hours Calculation for Full Day Leave

**User Story:** As an employee with an 8-hour shift, I want my 5-day leave request to deduct 40 hours, so that my leave balance reflects my actual working hours.

#### Acceptance Criteria

1. WHEN an employee submits a full day leave request, THE Leave_System SHALL calculate total hours as working_days multiplied by the employee's hours_per_day
2. WHEN retrieving hours_per_day, THE Leave_System SHALL query the ci_office_shifts table using the employee's office_shift_id
3. WHEN storing the calculated hours, THE Leave_System SHALL save the value in the leave_hours column of ci_leave_applications
4. THE Leave_System SHALL perform hours calculation only for NEW leave requests, not modifying existing leave applications

### Requirement 4: Hours Calculation for Hourly Permission

**User Story:** As an employee, I want to request permission for a few hours during a single day, so that I can leave early or arrive late without using a full day of leave.

#### Acceptance Criteria

1. WHEN an employee submits an hourly permission request with start_time and end_time on the same date, THE Leave_System SHALL calculate hours as the difference between end_time and start_time
2. WHEN the hourly permission spans across the employee's break time, THE Leave_System SHALL subtract the break duration from the calculated hours
3. WHEN validating hourly permission times, THE Leave_System SHALL verify that start_time and end_time fall within the employee's shift in_time and out_time for that day of the week
4. IF hourly permission times fall outside shift hours, THEN THE Leave_System SHALL reject the request with an appropriate error message

### Requirement 5: Break Time Exclusion

**User Story:** As an employee with a 1-hour lunch break, I want my full day leave to deduct only my working hours (8 hours), so that break time doesn't count against my leave balance.

#### Acceptance Criteria

1. WHEN calculating hours for a full day leave, THE Leave_System SHALL use the hours_per_day value which excludes break time
2. WHEN calculating hours for an hourly permission, THE Leave_System SHALL check if the permission time range overlaps with lunch_break and lunch_break_out times
3. IF an hourly permission overlaps with break time, THEN THE Leave_System SHALL subtract the overlapping break duration from the total calculated hours
4. THE Leave_System SHALL retrieve break times from the appropriate day columns (e.g., monday_lunch_break, monday_lunch_break_out) based on the leave date

### Requirement 6: Leave Balance Display

**User Story:** As an employee, I want to see my leave balance in both days and hours format, so that I understand how much leave I have available based on my working schedule.

#### Acceptance Criteria

1. WHEN displaying leave balance to an employee, THE Leave_System SHALL show the format "X days (Y hours)"
2. WHEN calculating the hours portion of the display, THE Leave_System SHALL multiply the days balance by the employee's hours_per_day
3. WHEN two employees have the same days balance but different shifts, THE Leave_System SHALL display different hour values based on each employee's hours_per_day
4. THE Leave_System SHALL calculate the hours display dynamically at the time of viewing, not store it permanently

### Requirement 7: Backward Compatibility

**User Story:** As a system administrator, I want existing leave applications to remain unchanged, so that historical leave data maintains its integrity.

#### Acceptance Criteria

1. WHEN the new calculation logic is deployed, THE Leave_System SHALL apply it only to new leave requests submitted after deployment
2. THE Leave_System SHALL NOT recalculate or modify the leave_hours value for existing records in ci_leave_applications
3. WHEN querying leave history, THE Leave_System SHALL display existing leave applications with their original leave_hours values
4. THE Leave_System SHALL maintain data integrity for all leave applications created before the fix implementation

### Requirement 8: Database Schema Compliance

**User Story:** As a developer, I want the system to correctly read shift configuration from the database schema, so that calculations use accurate employee working schedules.

#### Acceptance Criteria

1. WHEN retrieving shift information, THE Leave_System SHALL query ci_office_shifts table using the employee's office_shift_id from ci_erp_users_details
2. WHEN checking if a day is a working day, THE Leave_System SHALL verify that the corresponding day_in_time column (e.g., monday_in_time) is NOT empty
3. WHEN retrieving break times, THE Leave_System SHALL read from day_lunch_break and day_lunch_break_out columns for the specific day of the week
4. WHEN storing calculated hours, THE Leave_System SHALL save the value as a DECIMAL in the leave_hours column of ci_leave_applications

### Requirement 9: Company Holiday Integration

**User Story:** As an employee, I want company holidays to be automatically excluded from my leave request, so that I don't waste leave days on dates when the office is closed.

#### Acceptance Criteria

1. WHEN calculating working days for a leave request, THE Leave_System SHALL query the ci_holidays table for the employee's company
2. WHEN a date in the leave request range exists in ci_holidays, THE Leave_System SHALL exclude that date from the working days count
3. THE Leave_System SHALL filter holidays by company_id to ensure only relevant holidays are considered
4. WHEN both a company holiday and a shift non-working day fall on the same date, THE Leave_System SHALL count it only once as excluded

### Requirement 10: Error Message Localization

**User Story:** As a bilingual user, I want error messages to appear in my preferred language, so that I can understand validation errors clearly.

#### Acceptance Criteria

1. WHEN displaying the shift assignment error, THE Leave_System SHALL show "You must have an office shift assigned before requesting leave" in English
2. WHEN displaying the shift assignment error to Arabic users, THE Leave_System SHALL show "يجب أن يكون لديك وردية مكتبية معينة قبل طلب الإجازة"
3. THE Leave_System SHALL retrieve error messages from the appropriate language file (app/Language/en/Main.php or app/Language/ar/Main.php)
4. WHEN adding new error messages, THE Leave_System SHALL define them in both English and Arabic language files
