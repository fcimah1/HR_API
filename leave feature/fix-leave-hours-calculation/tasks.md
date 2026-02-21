# Implementation Plan: Fix Leave Hours Calculation

## Overview

This implementation plan breaks down the leave hours calculation fix into discrete coding tasks. The approach follows an incremental strategy: first establishing helper methods in the LeavePolicy library, then integrating them into the Leave controller, and finally adding localization support. Each task builds on previous work to ensure no orphaned code.

## Tasks

- [x] 1. Add shift validation and data retrieval methods to LeavePolicy library
  - [x] 1.1 Implement validateEmployeeHasShift() method
    - Create method in app/Libraries/LeavePolicy.php
    - Query ci_erp_users_details for office_shift_id
    - Return validation result with error message if no shift assigned
    - _Requirements: 1.1, 1.2_
  
  - [x] 1.2 Implement getEmployeeShiftData() method
    - Create method to join ci_erp_users_details with ci_office_shifts
    - Return complete shift object with all day columns and hours_per_day
    - Handle case where shift doesn't exist
    - _Requirements: 8.1_
  
  - [x] 1.3 Write property test for shift validation
    - **Property 1: Shift Assignment Validation**
    - **Validates: Requirements 1.1, 1.2**
    - Generate random employees with and without shifts
    - Verify validation correctly identifies missing shifts

- [x] 2. Implement working days calculation logic
  - [x] 2.1 Implement calculateWorkingDaysInRange() method
    - Create method in app/Libraries/LeavePolicy.php
    - Iterate through date range from fromDate to toDate
    - For each date, check if day_in_time is non-empty in shift
    - Query ci_holidays table for company holidays
    - Exclude dates that are non-working days or holidays
    - Return total working days count
    - _Requirements: 2.1, 2.2, 2.3, 9.1, 9.2_
  
  - [x] 2.2 Write property test for non-working days exclusion
    - **Property 2: Non-Working Days Exclusion**
    - **Validates: Requirements 2.1**
    - Generate random shifts with various non-working days
    - Generate random date ranges
    - Verify days with empty in_time are excluded
  
  - [x] 2.3 Write property test for company holidays exclusion
    - **Property 3: Company Holidays Exclusion**
    - **Validates: Requirements 2.2**
    - Generate random holiday sets for different companies
    - Generate random date ranges
    - Verify holidays are excluded from working days count
  
  - [x] 2.4 Write property test for company-specific holiday filtering
    - **Property 10: Company-Specific Holiday Filtering**
    - **Validates: Requirements 9.3**
    - Create holidays for multiple companies
    - Verify only employee's company holidays are excluded
  
  - [x] 2.5 Write property test for no double-counting exclusions
    - **Property 11: No Double-Counting Exclusions**
    - **Validates: Requirements 9.4**
    - Generate scenarios where holidays overlap with non-working days
    - Verify date is counted only once as excluded

- [x] 3. Implement hours conversion methods
  - [x] 3.1 Implement convertDaysToHours() method
    - Create method in app/Libraries/LeavePolicy.php
    - Get employee shift data using getEmployeeShiftData()
    - Retrieve hours_per_day from shift
    - Calculate: workingDays × hours_per_day
    - Return total hours as float
    - _Requirements: 3.1_
  
  - [x] 3.2 Write property test for hours calculation formula
    - **Property 4: Hours Calculation Formula**
    - **Validates: Requirements 3.1**
    - Generate random employees with different hours_per_day
    - Generate random working day counts
    - Verify multiplication is correct
  
  - [x] 3.3 Implement formatLeaveBalanceDisplay() method
    - Create method in app/Libraries/LeavePolicy.php
    - Get employee shift data
    - Calculate hours: daysBalance × hours_per_day
    - Format string: "{$daysBalance} days ({$hours} hours)"
    - Return formatted string
    - _Requirements: 6.1, 6.2_
  
  - [x] 3.4 Write property test for leave balance display format
    - **Property 9: Leave Balance Display Format**
    - **Validates: Requirements 6.1**
    - Generate random balance values
    - Verify format matches "X days (Y hours)"

- [x] 4. Checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

- [x] 5. Implement hourly permission calculation logic
  - [x] 5.1 Implement calculateHourlyPermissionHours() method
    - Create method in app/Libraries/LeavePolicy.php
    - Get employee shift data
    - Get day of week from date
    - Retrieve shift in_time and out_time for that day
    - Validate startTime >= in_time and endTime <= out_time
    - Calculate hours difference: (endTime - startTime)
    - Get lunch_break and lunch_break_out for that day
    - If permission spans break time, calculate overlap and subtract
    - Return validation result with calculated hours
    - _Requirements: 4.1, 4.2, 4.3, 4.4, 5.2, 5.3_
  
  - [x] 5.2 Write property test for hourly permission time difference
    - **Property 6: Hourly Permission Time Difference**
    - **Validates: Requirements 4.1**
    - Generate random start and end times (excluding break considerations)
    - Verify calculated hours equals time difference
  
  - [x] 5.3 Write property test for break time subtraction
    - **Property 7: Break Time Subtraction**
    - **Validates: Requirements 4.2**
    - Generate time ranges that overlap with breaks
    - Verify break duration is subtracted from calculated hours
  
  - [x] 5.4 Write property test for hourly permission time validation
    - **Property 8: Hourly Permission Time Validation**
    - **Validates: Requirements 4.3, 4.4**
    - Generate times both inside and outside shift hours
    - Verify validation rejects times outside shift hours

- [x] 6. Integrate calculation logic into Leave controller
  - [x] 6.1 Modify add_leave() method to validate shift assignment
    - Open app/Controllers/Erp/Leave.php
    - Add shift validation before processing leave request
    - Call $this->leavePolicy->validateEmployeeHasShift($employeeId)
    - Return error response if validation fails
    - _Requirements: 1.1, 1.2, 1.3, 1.4_
  
  - [x] 6.2 Add full day leave calculation logic to add_leave()
    - Determine if request is full day leave (has from_date and to_date)
    - Call calculateWorkingDaysInRange() with date range
    - Call convertDaysToHours() with working days count
    - Store calculated hours in $leaveData['leave_hours']
    - _Requirements: 3.1, 3.3_
  
  - [x] 6.3 Add hourly permission calculation logic to add_leave()
    - Determine if request is hourly permission (has start_time and end_time)
    - Call calculateHourlyPermissionHours() with time range
    - Handle validation errors from the method
    - Store calculated hours in $leaveData['leave_hours']
    - _Requirements: 4.1, 4.2, 4.3, 4.4_
  
  - [x] 6.4 Write property test for leave hours persistence
    - **Property 5: Leave Hours Persistence Round Trip**
    - **Validates: Requirements 3.3**
    - Create leave requests with calculated hours
    - Query database and verify leave_hours matches
  
  - [x] 6.5 Write unit tests for controller integration
    - Test full day leave request end-to-end
    - Test hourly permission request end-to-end
    - Test error handling for no shift assigned
    - Test error handling for invalid permission times
    - _Requirements: 1.1, 1.2, 3.1, 4.1, 4.3_

- [x] 7. Add localization support for error messages
  - [x] 7.1 Add English error messages to language file
    - Open app/Language/en/Main.php
    - Add 'no_shift_assigned' => 'You must have an office shift assigned before requesting leave'
    - Add 'invalid_permission_times' => 'Permission times must fall within your shift working hours'
    - _Requirements: 1.3, 10.1, 10.3_
  
  - [x] 7.2 Add Arabic error messages to language file
    - Open app/Language/ar/Main.php
    - Add 'no_shift_assigned' => 'يجب أن يكون لديك وردية مكتبية معينة قبل طلب الإجازة'
    - Add 'invalid_permission_times' => 'يجب أن تكون أوقات الإذن ضمن ساعات عمل الوردية الخاصة بك'
    - _Requirements: 10.2, 10.3, 10.4_
  
  - [x] 7.3 Write unit tests for error message localization
    - Test English error messages display correctly
    - Test Arabic error messages display correctly
    - _Requirements: 1.3, 10.1, 10.2_

- [x] 8. Add backward compatibility safeguards
  - [x] 8.1 Add timestamp check to only apply new logic to new requests
    - In add_leave() method, ensure new calculation only applies to new requests
    - Do not modify existing leave applications
    - Add comments documenting backward compatibility approach
    - _Requirements: 3.4, 7.1, 7.2, 7.3, 7.4_
  
  - [x] 8.2 Write unit test for backward compatibility
    - Create leave applications using old logic (before fix)
    - Verify existing leave_hours values remain unchanged
    - Verify new requests use new calculation logic
    - _Requirements: 3.4, 7.1, 7.2, 7.3, 7.4_

- [x] 9. Implement leave balance display formatting
  - [x] 9.1 Update leave balance display views to use formatLeaveBalanceDisplay()
    - Identify views that display leave balance
    - Call formatLeaveBalanceDisplay() method for display
    - Update view templates to show "X days (Y hours)" format
    - _Requirements: 6.1, 6.3_
  
  - [x] 9.2 Write unit test for employee-specific balance display
    - Create two employees with different shifts and same days balance
    - Verify they see different hour values
    - _Requirements: 6.3_

- [x] 10. Final checkpoint - Comprehensive testing
  - Run all unit tests and property tests
  - Verify all 11 correctness properties pass
  - Test with real employee data in development environment
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation
- Property tests validate universal correctness properties (minimum 100 iterations each)
- Unit tests validate specific examples and edge cases
- The implementation maintains backward compatibility with existing leave applications
- All error messages must be localized in both English and Arabic
