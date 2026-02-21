# Design Document: Fix Leave Hours Calculation

## Overview

This design addresses the critical flaw in the HR leave system where leave entitlements stored in days are not properly converted to hours based on employee shift configurations. The solution implements accurate hours calculation for both full-day leave requests and hourly permissions by considering employee-specific shift data, working days, company holidays, and break times.

The design introduces new helper methods in the LeavePolicy library to handle working days calculation, hours conversion, and shift validation. It modifies the Leave controller to integrate these calculations into the leave request submission flow while maintaining backward compatibility with existing leave applications.

## Architecture

### High-Level Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                    Leave Controller                          │
│  (app/Controllers/Erp/Leave.php)                            │
│  - Receives leave request from user                         │
│  - Validates employee has shift                             │
│  - Delegates calculation to LeavePolicy library             │
│  - Stores calculated hours in database                      │
└────────────────┬────────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────────┐
│                  LeavePolicy Library                         │
│  (app/Libraries/LeavePolicy.php)                            │
│  - calculateWorkingDaysInRange()                            │
│  - convertDaysToHours()                                     │
│  - calculateHourlyPermissionHours()                         │
│  - validateEmployeeHasShift()                               │
│  - getEmployeeShiftData()                                   │
└────────────────┬────────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────────┐
│                    Database Layer                            │
│  - ci_office_shifts (shift configuration)                   │
│  - ci_erp_users_details (employee shift assignment)         │
│  - ci_holidays (company holidays)                           │
│  - ci_leave_applications (leave requests)                   │
└─────────────────────────────────────────────────────────────┘
```

### Component Interaction Flow

**Full Day Leave Request Flow:**
1. User submits leave request with from_date and to_date
2. Leave controller validates employee has shift assigned
3. LeavePolicy calculates working days in date range (excluding weekends/holidays)
4. LeavePolicy converts working days to hours using employee's hours_per_day
5. Controller stores calculated hours in leave_hours column
6. Leave balance is updated accordingly

**Hourly Permission Request Flow:**
1. User submits permission request with start_time and end_time
2. Leave controller validates employee has shift assigned
3. LeavePolicy validates times fall within shift hours
4. LeavePolicy calculates hours between start and end time
5. LeavePolicy subtracts break time if permission spans break period
6. Controller stores calculated hours in leave_hours column

## Components and Interfaces

### 1. LeavePolicy Library (app/Libraries/LeavePolicy.php)

This library contains all calculation logic for leave hours.

#### Method: validateEmployeeHasShift()

```php
/**
 * Validates that an employee has an office shift assigned
 * 
 * @param int $employeeId The employee's user ID
 * @return array ['valid' => bool, 'error_message' => string|null]
 */
public function validateEmployeeHasShift($employeeId)
{
    // Query ci_erp_users_details for office_shift_id
    // If office_shift_id is NULL or 0, return error
    // Return validation result with appropriate error message
}
```

#### Method: getEmployeeShiftData()

```php
/**
 * Retrieves complete shift configuration for an employee
 * 
 * @param int $employeeId The employee's user ID
 * @return object|null Shift data object or null if not found
 */
public function getEmployeeShiftData($employeeId)
{
    // Join ci_erp_users_details with ci_office_shifts
    // Return shift object with all day columns and hours_per_day
    // Return null if employee has no shift or shift not found
}
```

#### Method: calculateWorkingDaysInRange()

```php
/**
 * Calculates actual working days between two dates for an employee
 * Excludes non-working days based on shift and company holidays
 * 
 * @param int $employeeId The employee's user ID
 * @param string $fromDate Start date (Y-m-d format)
 * @param string $toDate End date (Y-m-d format)
 * @return int Number of working days
 */
public function calculateWorkingDaysInRange($employeeId, $fromDate, $toDate)
{
    // Get employee shift data
    // Get company holidays from ci_holidays
    // Iterate through each date in range
    // For each date:
    //   - Get day of week (monday, tuesday, etc.)
    //   - Check if shift has non-empty {day}_in_time
    //   - Check if date exists in company holidays
    //   - Count as working day only if both conditions pass
    // Return total working days count
}
```

#### Method: convertDaysToHours()

```php
/**
 * Converts working days to hours based on employee's shift
 * 
 * @param int $employeeId The employee's user ID
 * @param int $workingDays Number of working days
 * @return float Total hours (working_days × hours_per_day)
 */
public function convertDaysToHours($employeeId, $workingDays)
{
    // Get employee shift data
    // Retrieve hours_per_day from shift
    // Calculate: workingDays × hours_per_day
    // Return total hours as float
}
```

#### Method: calculateHourlyPermissionHours()

```php
/**
 * Calculates hours for a partial-day leave request
 * Validates times and excludes break time if applicable
 * 
 * @param int $employeeId The employee's user ID
 * @param string $date The permission date (Y-m-d format)
 * @param string $startTime Start time (H:i:s format)
 * @param string $endTime End time (H:i:s format)
 * @return array ['valid' => bool, 'hours' => float, 'error_message' => string|null]
 */
public function calculateHourlyPermissionHours($employeeId, $date, $startTime, $endTime)
{
    // Get employee shift data
    // Get day of week from date
    // Retrieve shift in_time and out_time for that day
    // Validate startTime >= in_time and endTime <= out_time
    // Calculate hours difference: (endTime - startTime)
    // Get lunch_break and lunch_break_out for that day
    // If permission spans break time:
    //   - Calculate overlap duration
    //   - Subtract overlap from total hours
    // Return validation result with calculated hours
}
```

#### Method: formatLeaveBalanceDisplay()

```php
/**
 * Formats leave balance for display in "X days (Y hours)" format
 * 
 * @param int $employeeId The employee's user ID
 * @param float $daysBalance Leave balance in days
 * @return string Formatted string "X days (Y hours)"
 */
public function formatLeaveBalanceDisplay($employeeId, $daysBalance)
{
    // Get employee shift data
    // Calculate hours: daysBalance × hours_per_day
    // Format string: "{$daysBalance} days ({$hours} hours)"
    // Return formatted string
}
```

### 2. Leave Controller (app/Controllers/Erp/Leave.php)

Modifications to the add_leave() method to integrate new calculation logic.

#### Modified Method: add_leave()

```php
public function add_leave()
{
    // Existing validation logic...
    
    // NEW: Validate employee has shift
    $shiftValidation = $this->leavePolicy->validateEmployeeHasShift($employeeId);
    if (!$shiftValidation['valid']) {
        return $this->response->setJSON([
            'status' => 'error',
            'message' => $shiftValidation['error_message']
        ]);
    }
    
    // Determine leave type (full day vs hourly permission)
    if ($this->isFullDayLeave($requestData)) {
        // Full day leave calculation
        $workingDays = $this->leavePolicy->calculateWorkingDaysInRange(
            $employeeId,
            $requestData['from_date'],
            $requestData['to_date']
        );
        
        $leaveHours = $this->leavePolicy->convertDaysToHours(
            $employeeId,
            $workingDays
        );
    } else {
        // Hourly permission calculation
        $result = $this->leavePolicy->calculateHourlyPermissionHours(
            $employeeId,
            $requestData['date'],
            $requestData['start_time'],
            $requestData['end_time']
        );
        
        if (!$result['valid']) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => $result['error_message']
            ]);
        }
        
        $leaveHours = $result['hours'];
    }
    
    // Store leave_hours in database
    $leaveData['leave_hours'] = $leaveHours;
    
    // Existing save logic...
}
```

### 3. Language Files

#### English (app/Language/en/Main.php)

```php
'no_shift_assigned' => 'You must have an office shift assigned before requesting leave',
'invalid_permission_times' => 'Permission times must fall within your shift working hours',
```

#### Arabic (app/Language/ar/Main.php)

```php
'no_shift_assigned' => 'يجب أن يكون لديك وردية مكتبية معينة قبل طلب الإجازة',
'invalid_permission_times' => 'يجب أن تكون أوقات الإذن ضمن ساعات عمل الوردية الخاصة بك',
```

## Data Models

### Database Tables

#### ci_office_shifts
```
- id: INT (Primary Key)
- hours_per_day: INT (Working hours excluding break)
- monday_in_time: VARCHAR (e.g., "08:00:00")
- monday_out_time: VARCHAR (e.g., "17:00:00")
- monday_lunch_break: VARCHAR (e.g., "12:00:00")
- monday_lunch_break_out: VARCHAR (e.g., "13:00:00")
- tuesday_in_time: VARCHAR
- tuesday_out_time: VARCHAR
- tuesday_lunch_break: VARCHAR
- tuesday_lunch_break_out: VARCHAR
- ... (similar columns for other days)
- friday_in_time: VARCHAR (empty if Friday is holiday)
- saturday_in_time: VARCHAR (empty if Saturday is holiday)
```

#### ci_erp_users_details
```
- id: INT (Primary Key)
- user_id: INT (Foreign Key to users)
- office_shift_id: INT (Foreign Key to ci_office_shifts, can be NULL)
- company_id: INT
```

#### ci_holidays
```
- id: INT (Primary Key)
- company_id: INT
- holiday_date: DATE
- description: VARCHAR
```

#### ci_leave_applications
```
- id: INT (Primary Key)
- user_id: INT (Foreign Key)
- from_date: DATE
- to_date: DATE
- leave_hours: DECIMAL(10,2) (Calculated hours to deduct)
- status: VARCHAR
- created_at: TIMESTAMP
```

### Data Flow

**Input Data (Full Day Leave):**
```php
[
    'user_id' => 123,
    'from_date' => '2024-01-15',
    'to_date' => '2024-01-19',
    'leave_type_id' => 1
]
```

**Processing:**
1. Retrieve employee shift: office_shift_id = 5
2. Retrieve shift data: hours_per_day = 8
3. Calculate working days: 5 days (Mon-Fri)
4. Exclude holidays: 0 holidays in range
5. Calculate hours: 5 × 8 = 40 hours

**Output Data:**
```php
[
    'user_id' => 123,
    'from_date' => '2024-01-15',
    'to_date' => '2024-01-19',
    'leave_hours' => 40.00,
    'leave_type_id' => 1
]
```

**Input Data (Hourly Permission):**
```php
[
    'user_id' => 123,
    'date' => '2024-01-15',
    'start_time' => '08:00:00',
    'end_time' => '14:00:00',
    'leave_type_id' => 2
]
```

**Processing:**
1. Retrieve employee shift: office_shift_id = 5
2. Retrieve shift data for Monday: in_time = 08:00, out_time = 17:00, lunch_break = 12:00-13:00
3. Validate times: 08:00 >= 08:00 ✓, 14:00 <= 17:00 ✓
4. Calculate raw hours: 14:00 - 08:00 = 6 hours
5. Check break overlap: 12:00-13:00 falls within 08:00-14:00
6. Subtract break: 6 - 1 = 5 hours

**Output Data:**
```php
[
    'user_id' => 123,
    'date' => '2024-01-15',
    'start_time' => '08:00:00',
    'end_time' => '14:00:00',
    'leave_hours' => 5.00,
    'leave_type_id' => 2
]
```


## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system—essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property 1: Shift Assignment Validation

*For any* employee attempting to submit a leave request, if the employee has no office_shift_id assigned, then the system should reject the request and return an error message.

**Validates: Requirements 1.1, 1.2**

### Property 2: Non-Working Days Exclusion

*For any* employee with a shift configuration and any date range, when calculating working days, all days where the shift has an empty in_time for that day of the week should be excluded from the working days count.

**Validates: Requirements 2.1**

### Property 3: Company Holidays Exclusion

*For any* employee and any date range, when calculating working days, all dates that exist in the ci_holidays table for the employee's company should be excluded from the working days count.

**Validates: Requirements 2.2**

### Property 4: Hours Calculation Formula

*For any* employee with an assigned shift and any number of working days, the calculated leave hours should equal working_days multiplied by the employee's hours_per_day from their shift configuration.

**Validates: Requirements 3.1**

### Property 5: Leave Hours Persistence Round Trip

*For any* leave request, after calculating and storing leave_hours in the database, querying the leave application should return the same leave_hours value that was calculated.

**Validates: Requirements 3.3**

### Property 6: Hourly Permission Time Difference

*For any* hourly permission request with start_time and end_time on the same date (excluding break time considerations), the calculated hours should equal the time difference between end_time and start_time.

**Validates: Requirements 4.1**

### Property 7: Break Time Subtraction

*For any* hourly permission request where the time range overlaps with the employee's lunch break period, the calculated hours should equal the time difference minus the overlapping break duration.

**Validates: Requirements 4.2**

### Property 8: Hourly Permission Time Validation

*For any* hourly permission request, if the start_time or end_time falls outside the employee's shift in_time and out_time for that day of the week, then the system should reject the request with an error message.

**Validates: Requirements 4.3, 4.4**

### Property 9: Leave Balance Display Format

*For any* employee with a leave balance in days, the displayed balance should follow the format "X days (Y hours)" where Y equals X multiplied by the employee's hours_per_day.

**Validates: Requirements 6.1**

### Property 10: Company-Specific Holiday Filtering

*For any* employee and any date range, when calculating working days, only holidays from the ci_holidays table that match the employee's company_id should be excluded from the working days count.

**Validates: Requirements 9.3**

### Property 11: No Double-Counting Exclusions

*For any* date that is both a company holiday and a shift non-working day, when calculating working days, that date should be counted only once as excluded (not excluded twice).

**Validates: Requirements 9.4**

## Error Handling

### Validation Errors

**No Shift Assigned Error:**
- **Trigger:** Employee attempts to submit leave request without office_shift_id
- **Response:** HTTP 200 with JSON error response
- **Message:** Localized error message from language file
- **User Action:** Contact HR to get shift assigned

**Invalid Permission Times Error:**
- **Trigger:** Hourly permission times fall outside shift hours
- **Response:** HTTP 200 with JSON error response
- **Message:** "Permission times must fall within your shift working hours"
- **User Action:** Adjust times to fall within shift hours

### Data Integrity Errors

**Missing Shift Data Error:**
- **Trigger:** Employee has office_shift_id but shift record doesn't exist
- **Response:** Log error, return generic error to user
- **Message:** "Unable to retrieve shift information. Please contact support."
- **Recovery:** System administrator must fix data integrity issue

**Invalid Date Range Error:**
- **Trigger:** to_date is before from_date
- **Response:** HTTP 200 with JSON error response
- **Message:** "End date must be after start date"
- **User Action:** Correct the date range

### Edge Cases

**Zero Working Days:**
- **Scenario:** Leave request spans only non-working days and holidays
- **Behavior:** Calculate 0 working days, 0 hours
- **Result:** Leave request is accepted but no hours deducted

**Break Time Longer Than Permission:**
- **Scenario:** Hourly permission is entirely within break time
- **Behavior:** Calculate 0 hours (or negative, then clamp to 0)
- **Result:** Leave request is accepted but no hours deducted

**Midnight Crossing:**
- **Scenario:** Hourly permission spans midnight (edge case, likely invalid)
- **Behavior:** Validate that start_time and end_time are on same date
- **Result:** Reject if times span multiple days

## Testing Strategy

### Dual Testing Approach

This feature requires both unit tests and property-based tests to ensure comprehensive coverage:

- **Unit tests** verify specific examples, edge cases, and error conditions
- **Property tests** verify universal properties across all inputs
- Both are complementary and necessary for complete validation

### Unit Testing Focus

Unit tests should cover:
- Specific examples demonstrating correct behavior (e.g., 5 days × 8 hours = 40 hours)
- Edge cases (zero working days, break time edge cases, midnight crossing)
- Error conditions (no shift assigned, invalid times, missing data)
- Integration points between Leave controller and LeavePolicy library
- Database interactions and data persistence

**Example Unit Tests:**
```php
// Test specific example
testFullDayLeaveCalculation_5Days8HourShift_Returns40Hours()

// Test edge case
testFullDayLeaveCalculation_OnlyHolidays_Returns0Hours()

// Test error condition
testLeaveRequest_NoShiftAssigned_ReturnsError()

// Test integration
testLeaveRequest_StoresCalculatedHoursInDatabase()
```

### Property-Based Testing Configuration

**Library Selection:** Use a property-based testing library appropriate for PHP:
- **Eris** (PHP property-based testing library)
- **PHPUnit** with custom generators (if Eris not available)

**Test Configuration:**
- Minimum 100 iterations per property test
- Each test must reference its design document property
- Tag format: `@group Feature: fix-leave-hours-calculation, Property {number}: {property_text}`

**Property Test Implementation:**

Each correctness property must be implemented as a SINGLE property-based test:

```php
/**
 * @group Feature: fix-leave-hours-calculation, Property 4: Hours Calculation Formula
 */
public function testProperty4_HoursCalculationFormula()
{
    $this->forAll(
        Generator::employeeWithShift(),
        Generator::positiveInt(1, 30) // working days
    )->then(function ($employee, $workingDays) {
        $calculatedHours = $this->leavePolicy->convertDaysToHours(
            $employee->id,
            $workingDays
        );
        
        $expectedHours = $workingDays * $employee->shift->hours_per_day;
        
        $this->assertEquals($expectedHours, $calculatedHours);
    });
}
```

### Test Coverage Requirements

**Property Tests Must Cover:**
1. Shift validation (Property 1)
2. Non-working days exclusion (Property 2)
3. Company holidays exclusion (Property 3)
4. Hours calculation formula (Property 4)
5. Leave hours persistence (Property 5)
6. Hourly permission calculation (Property 6)
7. Break time subtraction (Property 7)
8. Time validation (Property 8)
9. Balance display format (Property 9)
10. Company-specific holidays (Property 10)
11. No double-counting (Property 11)

**Unit Tests Must Cover:**
- Specific examples for each calculation type
- All error conditions and validation failures
- Edge cases (zero days, break edge cases, etc.)
- Database integration and persistence
- Localization of error messages
- Backward compatibility with existing leave applications

### Test Data Generation

**For Property Tests:**
- Generate random employees with various shift configurations
- Generate random date ranges spanning different periods
- Generate random holiday sets for different companies
- Generate random time ranges for hourly permissions
- Ensure generators cover edge cases (empty shifts, overlapping breaks, etc.)

**For Unit Tests:**
- Use fixed, known test data for reproducibility
- Create specific scenarios for each edge case
- Use database fixtures for integration tests
- Test with both English and Arabic locales

### Backward Compatibility Testing

**Critical Test:**
Create leave applications using the OLD logic (before fix), then verify:
1. Existing leave_hours values remain unchanged
2. New leave requests use NEW calculation logic
3. Leave balance queries work correctly for both old and new records
4. No data migration or recalculation occurs

This ensures the fix doesn't break historical data integrity.
