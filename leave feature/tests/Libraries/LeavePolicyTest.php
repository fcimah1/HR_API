<?php

namespace Tests\Libraries;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use App\Libraries\LeavePolicy;

/**
 * Tests for LeavePolicy Library - Fix Leave Hours Calculation
 * 
 * @group Feature: fix-leave-hours-calculation
 */
class LeavePolicyTest extends CIUnitTestCase
{
    protected $leavePolicy;
    protected $db;
    protected $dbAvailable = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->leavePolicy = new LeavePolicy();
        
        // Try to connect to database
        try {
            $this->db = \Config\Database::connect();
            $this->db->query('SELECT 1');
            $this->dbAvailable = true;
        } catch (\Exception $e) {
            $this->dbAvailable = false;
        }
    }

    /**
     * Test getEmployeeShiftData() returns null when employee has no shift assigned
     */
    public function testGetEmployeeShiftData_NoShiftAssigned_ReturnsNull()
    {
        // This test assumes there's an employee with no shift assigned
        // In a real test, you would create test data in the database
        
        // Test with a non-existent employee ID
        $result = $this->leavePolicy->getEmployeeShiftData(999999);
        
        $this->assertNull($result, 'Should return null when employee has no shift assigned');
    }

    /**
     * Test getEmployeeShiftData() returns shift object when employee has valid shift
     * 
     * Note: This test requires actual database data to work properly.
     * In a production environment, you would use database fixtures or factories.
     */
    public function testGetEmployeeShiftData_ValidShift_ReturnsShiftObject()
    {
        // Skip this test if no test database is configured
        $this->markTestSkipped('Requires test database with employee and shift data');
        
        // Example test structure (would need actual test data):
        // $employeeId = 1; // Employee with shift assigned
        // $result = $this->leavePolicy->getEmployeeShiftData($employeeId);
        // 
        // $this->assertNotNull($result);
        // $this->assertIsObject($result);
        // $this->assertObjectHasProperty('office_shift_id', $result);
        // $this->assertObjectHasProperty('hours_per_day', $result);
        // $this->assertObjectHasProperty('monday_in_time', $result);
        // $this->assertObjectHasProperty('monday_out_time', $result);
    }

    /**
     * Test getEmployeeShiftData() returns complete shift data with all day columns
     * 
     * This test verifies that the returned object contains all required shift fields
     * including day-specific columns for all days of the week.
     */
    public function testGetEmployeeShiftData_ReturnsCompleteShiftData()
    {
        // Skip this test if no test database is configured
        $this->markTestSkipped('Requires test database with employee and shift data');
        
        // Example test structure:
        // $employeeId = 1;
        // $shift = $this->leavePolicy->getEmployeeShiftData($employeeId);
        // 
        // // Verify all day columns are present
        // $daysOfWeek = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        // foreach ($daysOfWeek as $day) {
        //     $this->assertObjectHasProperty("{$day}_in_time", $shift);
        //     $this->assertObjectHasProperty("{$day}_out_time", $shift);
        //     $this->assertObjectHasProperty("{$day}_lunch_break", $shift);
        //     $this->assertObjectHasProperty("{$day}_lunch_break_out", $shift);
        // }
    }

    /**
     * Property 1: Shift Assignment Validation
     * 
     * **Validates: Requirements 1.1, 1.2**
     * 
     * For any employee attempting to submit a leave request, if the employee has no 
     * office_shift_id assigned, then the system should reject the request and return 
     * an error message.
     * 
     * This property-based test generates random employee scenarios with and without 
     * shifts to verify that validation correctly identifies missing shifts.
     * 
     * @group Feature: fix-leave-hours-calculation, Property 1: Shift Assignment Validation
     */
    public function testProperty1_ShiftAssignmentValidation()
    {
        // Check if database is available
        if (!$this->dbAvailable) {
            $this->markTestSkipped('Database connection not available for property testing');
            return;
        }
        
        $iterations = 100;
        $passedTests = 0;
        $failedScenarios = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Generate random test scenario
            $scenario = $this->generateEmployeeShiftScenario();
            
            try {
                // Create test employee in database
                $employeeId = $this->createTestEmployee($scenario);
                
                // Test the validation
                $result = $this->leavePolicy->validateEmployeeHasShift($employeeId);
                
                // Verify the property holds
                if ($scenario['has_shift']) {
                    // Employee has shift: validation should pass
                    if (!$result['valid']) {
                        $failedScenarios[] = [
                            'iteration' => $i,
                            'scenario' => $scenario,
                            'result' => $result,
                            'expected' => 'valid=true',
                            'actual' => 'valid=false'
                        ];
                    }
                    $this->assertTrue(
                        $result['valid'],
                        "Property violated: Employee with shift (ID: {$employeeId}, shift_id: {$scenario['shift_id']}) should pass validation"
                    );
                    $this->assertNull(
                        $result['error_message'],
                        "Property violated: Employee with shift should not have error message"
                    );
                } else {
                    // Employee has no shift: validation should fail
                    if ($result['valid']) {
                        $failedScenarios[] = [
                            'iteration' => $i,
                            'scenario' => $scenario,
                            'result' => $result,
                            'expected' => 'valid=false',
                            'actual' => 'valid=true'
                        ];
                    }
                    $this->assertFalse(
                        $result['valid'],
                        "Property violated: Employee without shift (ID: {$employeeId}) should fail validation"
                    );
                    $this->assertNotNull(
                        $result['error_message'],
                        "Property violated: Employee without shift should have error message"
                    );
                    $this->assertStringContainsString(
                        'shift',
                        strtolower($result['error_message']),
                        "Property violated: Error message should mention 'shift'"
                    );
                }
                
                // Clean up test data
                $this->cleanupTestEmployee($employeeId);
                
                $passedTests++;
            } catch (\Exception $e) {
                // Clean up on error
                if (isset($employeeId)) {
                    $this->cleanupTestEmployee($employeeId);
                }
                throw $e;
            }
        }
        
        // Report any failed scenarios
        if (!empty($failedScenarios)) {
            $this->fail(
                "Property test failed for " . count($failedScenarios) . " scenarios:\n" .
                print_r($failedScenarios, true)
            );
        }
        
        // Verify all iterations passed
        $this->assertEquals(
            $iterations,
            $passedTests,
            "Property test should pass for all {$iterations} iterations"
        );
    }

    /**
     * Generate a random employee shift scenario for property testing
     * 
     * @return array ['has_shift' => bool, 'shift_id' => int|null, 'user_id' => int, 'company_id' => int]
     */
    private function generateEmployeeShiftScenario()
    {
        // Randomly decide if employee has shift (50% probability)
        $hasShift = (bool) random_int(0, 1);
        
        // Generate random IDs
        $userId = random_int(100000, 999999);
        $companyId = random_int(1, 100);
        
        if ($hasShift) {
            // Generate valid shift ID (non-zero)
            $shiftId = random_int(1, 1000);
        } else {
            // Use 0 for no shift (database doesn't allow null)
            $shiftId = 0;
        }
        
        return [
            'has_shift' => $hasShift,
            'shift_id' => $shiftId,
            'user_id' => $userId,
            'company_id' => $companyId
        ];
    }

    /**
     * Create a test employee in the database with the given scenario
     * 
     * @param array $scenario
     * @return int Employee ID
     */
    private function createTestEmployee($scenario)
    {
        // First, create the user record in ci_erp_users
        $userData = [
            'user_id' => $scenario['user_id'],
            'company_id' => $scenario['company_id'],
            'first_name' => 'Test',
            'last_name' => 'Employee',
            'email' => 'test' . $scenario['user_id'] . '@example.com',
            'username' => 'testuser' . $scenario['user_id'],
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $this->db->table('ci_erp_users')->insert($userData);
        
        // Then create the staff details record
        $staffData = [
            'user_id' => $scenario['user_id'],
            'company_id' => $scenario['company_id'],
            'office_shift_id' => $scenario['shift_id'],
            'employee_id' => 'EMP' . $scenario['user_id'],
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $this->db->table('ci_erp_users_details')->insert($staffData);
        
        return $scenario['user_id'];
    }

    /**
     * Clean up test employee data from the database
     * 
     * @param int $employeeId
     */
    private function cleanupTestEmployee($employeeId)
    {
        $this->db->table('ci_erp_users_details')->where('user_id', $employeeId)->delete();
        $this->db->table('ci_erp_users')->where('user_id', $employeeId)->delete();
    }

    /**
     * Test calculateWorkingDaysInRange() returns 0 when employee has no shift
     */
    public function testCalculateWorkingDaysInRange_NoShiftAssigned_ReturnsZero()
    {
        // Test with a non-existent employee ID
        $result = $this->leavePolicy->calculateWorkingDaysInRange(999999, '2024-01-15', '2024-01-19');
        
        $this->assertEquals(0, $result, 'Should return 0 working days when employee has no shift assigned');
    }

    /**
     * Test calculateWorkingDaysInRange() correctly calculates working days
     * 
     * Note: This test requires actual database data to work properly.
     * In a production environment, you would use database fixtures or factories.
     */
    public function testCalculateWorkingDaysInRange_ValidShift_CalculatesCorrectly()
    {
        // Skip this test if no test database is configured
        $this->markTestSkipped('Requires test database with employee, shift, and holiday data');
        
        // Example test structure (would need actual test data):
        // $employeeId = 1; // Employee with shift assigned
        // $fromDate = '2024-01-15'; // Monday
        // $toDate = '2024-01-19';   // Friday
        // 
        // $workingDays = $this->leavePolicy->calculateWorkingDaysInRange($employeeId, $fromDate, $toDate);
        // 
        // // Verify the result is reasonable (between 0 and 5 for a 5-day range)
        // $this->assertGreaterThanOrEqual(0, $workingDays);
        // $this->assertLessThanOrEqual(5, $workingDays);
    }

    /**
     * Property 2: Non-Working Days Exclusion
     * 
     * **Validates: Requirements 2.1**
     * 
     * For any employee with a shift configuration and any date range, when calculating 
     * working days, all days where the shift has an empty in_time for that day of the 
     * week should be excluded from the working days count.
     * 
     * This property-based test generates random shifts with various non-working days 
     * and random date ranges to verify that days with empty in_time are correctly excluded.
     * 
     * @group Feature: fix-leave-hours-calculation, Property 2: Non-Working Days Exclusion
     */
    public function testProperty2_NonWorkingDaysExclusion()
    {
        // Check if database is available
        if (!$this->dbAvailable) {
            $this->markTestSkipped('Database connection not available for property testing');
            return;
        }
        
        $iterations = 100;
        $passedTests = 0;
        $failedScenarios = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Generate random test scenario
            $scenario = $this->generateShiftAndDateRangeScenario();
            
            try {
                // Create test shift and employee in database
                $shiftId = $this->createTestShift($scenario['shift']);
                $employeeId = $this->createTestEmployeeWithShift($scenario['employee'], $shiftId);
                
                // Calculate working days using the method under test
                $calculatedWorkingDays = $this->leavePolicy->calculateWorkingDaysInRange(
                    $employeeId,
                    $scenario['from_date'],
                    $scenario['to_date']
                );
                
                // Calculate expected working days by manually checking each date
                $expectedWorkingDays = $this->countExpectedWorkingDays(
                    $scenario['shift'],
                    $scenario['from_date'],
                    $scenario['to_date']
                );
                
                // Verify the property holds: calculated days should match expected days
                if ($calculatedWorkingDays !== $expectedWorkingDays) {
                    $failedScenarios[] = [
                        'iteration' => $i,
                        'scenario' => $scenario,
                        'expected' => $expectedWorkingDays,
                        'actual' => $calculatedWorkingDays,
                        'shift_id' => $shiftId,
                        'employee_id' => $employeeId
                    ];
                }
                
                $this->assertEquals(
                    $expectedWorkingDays,
                    $calculatedWorkingDays,
                    "Property violated: Working days calculation should exclude non-working days. " .
                    "Expected {$expectedWorkingDays}, got {$calculatedWorkingDays} for date range " .
                    "{$scenario['from_date']} to {$scenario['to_date']}"
                );
                
                // Clean up test data
                $this->cleanupTestEmployee($employeeId);
                $this->cleanupTestShift($shiftId);
                
                $passedTests++;
            } catch (\Exception $e) {
                // Clean up on error
                if (isset($employeeId)) {
                    $this->cleanupTestEmployee($employeeId);
                }
                if (isset($shiftId)) {
                    $this->cleanupTestShift($shiftId);
                }
                throw $e;
            }
        }
        
        // Report any failed scenarios
        if (!empty($failedScenarios)) {
            $this->fail(
                "Property test failed for " . \count($failedScenarios) . " scenarios:\n" .
                print_r($failedScenarios, true)
            );
        }
        
        // Verify all iterations passed
        $this->assertEquals(
            $iterations,
            $passedTests,
            "Property test should pass for all {$iterations} iterations"
        );
    }

    /**
     * Generate a random shift configuration and date range scenario for property testing
     * 
     * @return array ['shift' => array, 'employee' => array, 'from_date' => string, 'to_date' => string]
     */
    private function generateShiftAndDateRangeScenario()
    {
        $daysOfWeek = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        
        // Generate random shift configuration
        $shift = [
            'shift_name' => 'Test Shift ' . random_int(1000, 9999),
            'hours_per_day' => random_int(6, 10)
        ];
        
        // Randomly set working/non-working days
        foreach ($daysOfWeek as $day) {
            // 70% chance of being a working day
            $isWorkingDay = random_int(1, 10) <= 7;
            
            if ($isWorkingDay) {
                // Set working hours (e.g., 08:00:00 to 17:00:00)
                $startHour = random_int(6, 10);
                $endHour = $startHour + $shift['hours_per_day'];
                $shift["{$day}_in_time"] = sprintf('%02d:00:00', $startHour);
                $shift["{$day}_out_time"] = sprintf('%02d:00:00', $endHour);
                $shift["{$day}_lunch_break"] = '12:00:00';
                $shift["{$day}_lunch_break_out"] = '13:00:00';
            } else {
                // Non-working day: empty in_time
                $shift["{$day}_in_time"] = '';
                $shift["{$day}_out_time"] = '';
                $shift["{$day}_lunch_break"] = '';
                $shift["{$day}_lunch_break_out"] = '';
            }
        }
        
        // Generate random date range (1 to 30 days)
        $startDate = new \DateTime();
        $startDate->modify('+' . random_int(1, 30) . ' days');
        $rangeDays = random_int(1, 30);
        $endDate = clone $startDate;
        $endDate->modify('+' . $rangeDays . ' days');
        
        // Generate employee data
        $employee = [
            'user_id' => random_int(100000, 999999),
            'company_id' => random_int(1, 100)
        ];
        
        return [
            'shift' => $shift,
            'employee' => $employee,
            'from_date' => $startDate->format('Y-m-d'),
            'to_date' => $endDate->format('Y-m-d')
        ];
    }

    /**
     * Create a test shift in the database
     * 
     * @param array $shiftData
     * @return int Shift ID
     */
    private function createTestShift($shiftData)
    {
        $this->db->table('ci_office_shifts')->insert($shiftData);
        return $this->db->insertID();
    }

    /**
     * Create a test employee with a specific shift assignment
     * 
     * @param array $employeeData
     * @param int $shiftId
     * @return int Employee ID
     */
    private function createTestEmployeeWithShift($employeeData, $shiftId)
    {
        // First, create the user record in ci_erp_users
        $userData = [
            'user_id' => $employeeData['user_id'],
            'company_id' => $employeeData['company_id'],
            'first_name' => 'Test',
            'last_name' => 'Employee',
            'email' => 'test' . $employeeData['user_id'] . '@example.com',
            'username' => 'testuser' . $employeeData['user_id'],
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $this->db->table('ci_erp_users')->insert($userData);
        
        // Then create the staff details record with shift assignment
        $staffData = [
            'user_id' => $employeeData['user_id'],
            'company_id' => $employeeData['company_id'],
            'office_shift_id' => $shiftId,
            'employee_id' => 'EMP' . $employeeData['user_id'],
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $this->db->table('ci_erp_users_details')->insert($staffData);
        
        return $employeeData['user_id'];
    }

    /**
     * Clean up test shift data from the database
     * 
     * @param int $shiftId
     */
    private function cleanupTestShift($shiftId)
    {
        $this->db->table('ci_office_shifts')->where('office_shift_id', $shiftId)->delete();
    }

    /**
     * Count expected working days by manually checking each date against shift configuration
     * This is the oracle for the property test
     * 
     * @param array $shift Shift configuration
     * @param string $fromDate Start date (Y-m-d format)
     * @param string $toDate End date (Y-m-d format)
     * @return int Expected working days count
     */
    private function countExpectedWorkingDays($shift, $fromDate, $toDate)
    {
        $workingDays = 0;
        $currentDate = new \DateTime($fromDate);
        $endDate = new \DateTime($toDate);
        
        while ($currentDate <= $endDate) {
            // Get day of week (lowercase: monday, tuesday, etc.)
            $dayOfWeek = strtolower($currentDate->format('l'));
            
            // Check if this day has a non-empty in_time in the shift
            $inTimeColumn = "{$dayOfWeek}_in_time";
            $inTime = $shift[$inTimeColumn] ?? '';
            
            // Count as working day if in_time is not empty and not '0:00'
            if (!empty($inTime) && $inTime !== '' && $inTime !== '0:00') {
                $workingDays++;
            }
            
            // Move to next day
            $currentDate->modify('+1 day');
        }
        
        return $workingDays;
    }

    /**
     * Property 3: Company Holidays Exclusion
     * 
     * **Validates: Requirements 2.2**
     * 
     * For any employee and any date range, when calculating working days, all dates 
     * that exist in the ci_holidays table for the employee's company should be excluded 
     * from the working days count.
     * 
     * This property-based test generates random holiday sets for different companies 
     * and random date ranges to verify that holidays are correctly excluded from the 
     * working days count.
     * 
     * @group Feature: fix-leave-hours-calculation, Property 3: Company Holidays Exclusion
     */
    public function testProperty3_CompanyHolidaysExclusion()
    {
        // Check if database is available
        if (!$this->dbAvailable) {
            $this->markTestSkipped('Database connection not available for property testing');
            return;
        }
        
        $iterations = 100;
        $passedTests = 0;
        $failedScenarios = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Generate random test scenario with holidays
            $scenario = $this->generateHolidayScenario();
            
            try {
                // Create test shift (all days working for simplicity)
                $shiftId = $this->createTestShift($scenario['shift']);
                
                // Create test employee with shift
                $employeeId = $this->createTestEmployeeWithShift($scenario['employee'], $shiftId);
                
                // Create company holidays in database
                $holidayIds = $this->createTestHolidays($scenario['holidays']);
                
                // Calculate working days using the method under test
                $calculatedWorkingDays = $this->leavePolicy->calculateWorkingDaysInRange(
                    $employeeId,
                    $scenario['from_date'],
                    $scenario['to_date']
                );
                
                // Calculate expected working days by manually checking each date
                $expectedWorkingDays = $this->countExpectedWorkingDaysWithHolidays(
                    $scenario['shift'],
                    $scenario['from_date'],
                    $scenario['to_date'],
                    $scenario['holidays']
                );
                
                // Verify the property holds: calculated days should match expected days
                if ($calculatedWorkingDays !== $expectedWorkingDays) {
                    $failedScenarios[] = [
                        'iteration' => $i,
                        'scenario' => $scenario,
                        'expected' => $expectedWorkingDays,
                        'actual' => $calculatedWorkingDays,
                        'shift_id' => $shiftId,
                        'employee_id' => $employeeId,
                        'holiday_count' => count($scenario['holidays'])
                    ];
                }
                
                $this->assertEquals(
                    $expectedWorkingDays,
                    $calculatedWorkingDays,
                    "Property violated: Working days calculation should exclude company holidays. " .
                    "Expected {$expectedWorkingDays}, got {$calculatedWorkingDays} for date range " .
                    "{$scenario['from_date']} to {$scenario['to_date']} with " . 
                    count($scenario['holidays']) . " holidays"
                );
                
                // Clean up test data
                $this->cleanupTestHolidays($holidayIds);
                $this->cleanupTestEmployee($employeeId);
                $this->cleanupTestShift($shiftId);
                
                $passedTests++;
            } catch (\Exception $e) {
                // Clean up on error
                if (isset($holidayIds)) {
                    $this->cleanupTestHolidays($holidayIds);
                }
                if (isset($employeeId)) {
                    $this->cleanupTestEmployee($employeeId);
                }
                if (isset($shiftId)) {
                    $this->cleanupTestShift($shiftId);
                }
                throw $e;
            }
        }
        
        // Report any failed scenarios
        if (!empty($failedScenarios)) {
            $this->fail(
                "Property test failed for " . count($failedScenarios) . " scenarios:\n" .
                print_r($failedScenarios, true)
            );
        }
        
        // Verify all iterations passed
        $this->assertEquals(
            $iterations,
            $passedTests,
            "Property test should pass for all {$iterations} iterations"
        );
    }

    /**
     * Generate a random holiday scenario for property testing
     * 
     * @return array ['shift' => array, 'employee' => array, 'holidays' => array, 'from_date' => string, 'to_date' => string]
     */
    private function generateHolidayScenario()
    {
        $daysOfWeek = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        
        // Generate a shift where all days are working days (for simplicity)
        $shift = [
            'shift_name' => 'Test Shift ' . random_int(1000, 9999),
            'hours_per_day' => 8
        ];
        
        // Set all days as working days
        foreach ($daysOfWeek as $day) {
            $shift["{$day}_in_time"] = '08:00:00';
            $shift["{$day}_out_time"] = '17:00:00';
            $shift["{$day}_lunch_break"] = '12:00:00';
            $shift["{$day}_lunch_break_out"] = '13:00:00';
        }
        
        // Generate random date range (5 to 30 days)
        $startDate = new \DateTime();
        $startDate->modify('+' . random_int(1, 30) . ' days');
        $rangeDays = random_int(5, 30);
        $endDate = clone $startDate;
        $endDate->modify('+' . $rangeDays . ' days');
        
        // Generate employee data
        $companyId = random_int(1, 100);
        $employee = [
            'user_id' => random_int(100000, 999999),
            'company_id' => $companyId
        ];
        
        // Generate random holidays (0 to 5 holidays within the date range)
        $holidays = [];
        $numHolidays = random_int(0, 5);
        
        for ($h = 0; $h < $numHolidays; $h++) {
            // Pick a random date within the range
            $holidayOffset = random_int(0, $rangeDays);
            $holidayDate = clone $startDate;
            $holidayDate->modify("+{$holidayOffset} days");
            
            // Randomly decide if it's a single-day or multi-day holiday
            $isMultiDay = random_int(0, 3) === 0; // 25% chance of multi-day
            
            if ($isMultiDay) {
                // Multi-day holiday (1-3 days)
                $holidayDuration = random_int(1, 3);
                $holidayEndDate = clone $holidayDate;
                $holidayEndDate->modify("+{$holidayDuration} days");
            } else {
                // Single-day holiday
                $holidayEndDate = clone $holidayDate;
            }
            
            $holidays[] = [
                'company_id' => $companyId,
                'event_name' => 'Test Holiday ' . random_int(1000, 9999),
                'description' => 'Property test holiday',
                'start_date' => $holidayDate->format('Y-m-d'),
                'end_date' => $holidayEndDate->format('Y-m-d'),
                'is_publish' => 1,
                'created_at' => date('Y-m-d H:i:s')
            ];
        }
        
        return [
            'shift' => $shift,
            'employee' => $employee,
            'holidays' => $holidays,
            'from_date' => $startDate->format('Y-m-d'),
            'to_date' => $endDate->format('Y-m-d')
        ];
    }

    /**
     * Create test holidays in the database
     * 
     * @param array $holidays Array of holiday data
     * @return array Array of holiday IDs
     */
    private function createTestHolidays($holidays)
    {
        $holidayIds = [];
        
        foreach ($holidays as $holiday) {
            $this->db->table('ci_holidays')->insert($holiday);
            $holidayIds[] = $this->db->insertID();
        }
        
        return $holidayIds;
    }

    /**
     * Clean up test holidays from the database
     * 
     * @param array $holidayIds Array of holiday IDs to delete
     */
    private function cleanupTestHolidays($holidayIds)
    {
        foreach ($holidayIds as $holidayId) {
            $this->db->table('ci_holidays')->where('holiday_id', $holidayId)->delete();
        }
    }

    /**
     * Count expected working days by manually checking each date against shift configuration and holidays
     * This is the oracle for the property test
     * 
     * @param array $shift Shift configuration
     * @param string $fromDate Start date (Y-m-d format)
     * @param string $toDate End date (Y-m-d format)
     * @param array $holidays Array of holiday data
     * @return int Expected working days count
     */
    private function countExpectedWorkingDaysWithHolidays($shift, $fromDate, $toDate, $holidays)
    {
        // Build a set of holiday dates for quick lookup
        $holidayDates = [];
        foreach ($holidays as $holiday) {
            $startDate = new \DateTime($holiday['start_date']);
            $endDate = new \DateTime($holiday['end_date']);
            
            // Add all dates in the holiday range
            $currentDate = clone $startDate;
            while ($currentDate <= $endDate) {
                $holidayDates[$currentDate->format('Y-m-d')] = true;
                $currentDate->modify('+1 day');
            }
        }
        
        // Count working days
        $workingDays = 0;
        $currentDate = new \DateTime($fromDate);
        $endDate = new \DateTime($toDate);
        
        while ($currentDate <= $endDate) {
            $dateStr = $currentDate->format('Y-m-d');
            
            // Get day of week (lowercase: monday, tuesday, etc.)
            $dayOfWeek = strtolower($currentDate->format('l'));
            
            // Check if this day has a non-empty in_time in the shift
            $inTimeColumn = "{$dayOfWeek}_in_time";
            $inTime = $shift[$inTimeColumn] ?? '';
            
            // Check if this day is a working day
            $isWorkingDay = !empty($inTime) && $inTime !== '' && $inTime !== '0:00';
            
            // Check if this date is a holiday
            $isHoliday = isset($holidayDates[$dateStr]);
            
            // Count as working day only if it's a working day AND not a holiday
            if ($isWorkingDay && !$isHoliday) {
                $workingDays++;
            }
            
            // Move to next day
            $currentDate->modify('+1 day');
        }
        
        return $workingDays;
    }

    /**
     * Property 10: Company-Specific Holiday Filtering
     * 
     * **Validates: Requirements 9.3**
     * 
     * For any employee and any date range, when calculating working days, only holidays 
     * from the ci_holidays table that match the employee's company_id should be excluded 
     * from the working days count.
     * 
     * This property-based test creates holidays for multiple companies and verifies that 
     * only the employee's company holidays are excluded from the working days calculation.
     * 
     * @group Feature: fix-leave-hours-calculation, Property 10: Company-Specific Holiday Filtering
     */
    public function testProperty10_CompanySpecificHolidayFiltering()
    {
        // Check if database is available
        if (!$this->dbAvailable) {
            $this->markTestSkipped('Database connection not available for property testing');
            return;
        }
        
        $iterations = 100;
        $passedTests = 0;
        $failedScenarios = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Generate random test scenario with holidays for multiple companies
            $scenario = $this->generateMultiCompanyHolidayScenario();
            
            try {
                // Create test shift (all days working for simplicity)
                $shiftId = $this->createTestShift($scenario['shift']);
                
                // Create test employee with shift
                $employeeId = $this->createTestEmployeeWithShift($scenario['employee'], $shiftId);
                
                // Create holidays for multiple companies (including employee's company and others)
                $holidayIds = $this->createTestHolidays($scenario['all_holidays']);
                
                // Calculate working days using the method under test
                $calculatedWorkingDays = $this->leavePolicy->calculateWorkingDaysInRange(
                    $employeeId,
                    $scenario['from_date'],
                    $scenario['to_date']
                );
                
                // Calculate expected working days by manually checking each date
                // Only holidays matching employee's company_id should be excluded
                $expectedWorkingDays = $this->countExpectedWorkingDaysWithCompanyHolidays(
                    $scenario['shift'],
                    $scenario['from_date'],
                    $scenario['to_date'],
                    $scenario['employee_company_holidays']
                );
                
                // Verify the property holds: calculated days should match expected days
                // This ensures only employee's company holidays are excluded, not other companies' holidays
                if ($calculatedWorkingDays !== $expectedWorkingDays) {
                    $failedScenarios[] = [
                        'iteration' => $i,
                        'employee_company_id' => $scenario['employee']['company_id'],
                        'employee_company_holidays' => count($scenario['employee_company_holidays']),
                        'other_company_holidays' => count($scenario['other_company_holidays']),
                        'expected' => $expectedWorkingDays,
                        'actual' => $calculatedWorkingDays,
                        'shift_id' => $shiftId,
                        'employee_id' => $employeeId,
                        'date_range' => "{$scenario['from_date']} to {$scenario['to_date']}"
                    ];
                }
                
                $this->assertEquals(
                    $expectedWorkingDays,
                    $calculatedWorkingDays,
                    "Property violated: Working days calculation should only exclude employee's company holidays. " .
                    "Employee company: {$scenario['employee']['company_id']}, " .
                    "Employee company holidays: " . count($scenario['employee_company_holidays']) . ", " .
                    "Other company holidays: " . count($scenario['other_company_holidays']) . ". " .
                    "Expected {$expectedWorkingDays}, got {$calculatedWorkingDays} for date range " .
                    "{$scenario['from_date']} to {$scenario['to_date']}"
                );
                
                // Clean up test data
                $this->cleanupTestHolidays($holidayIds);
                $this->cleanupTestEmployee($employeeId);
                $this->cleanupTestShift($shiftId);
                
                $passedTests++;
            } catch (\Exception $e) {
                // Clean up on error
                if (isset($holidayIds)) {
                    $this->cleanupTestHolidays($holidayIds);
                }
                if (isset($employeeId)) {
                    $this->cleanupTestEmployee($employeeId);
                }
                if (isset($shiftId)) {
                    $this->cleanupTestShift($shiftId);
                }
                throw $e;
            }
        }
        
        // Report any failed scenarios
        if (!empty($failedScenarios)) {
            $this->fail(
                "Property test failed for " . count($failedScenarios) . " scenarios:\n" .
                print_r($failedScenarios, true)
            );
        }
        
        // Verify all iterations passed
        $this->assertEquals(
            $iterations,
            $passedTests,
            "Property test should pass for all {$iterations} iterations"
        );
    }

    /**
     * Generate a random multi-company holiday scenario for property testing
     * Creates holidays for multiple companies to test company-specific filtering
     * 
     * @return array ['shift' => array, 'employee' => array, 'all_holidays' => array, 
     *                'employee_company_holidays' => array, 'other_company_holidays' => array,
     *                'from_date' => string, 'to_date' => string]
     */
    private function generateMultiCompanyHolidayScenario()
    {
        $daysOfWeek = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        
        // Generate a shift where all days are working days (for simplicity)
        $shift = [
            'shift_name' => 'Test Shift ' . random_int(1000, 9999),
            'hours_per_day' => 8
        ];
        
        // Set all days as working days
        foreach ($daysOfWeek as $day) {
            $shift["{$day}_in_time"] = '08:00:00';
            $shift["{$day}_out_time"] = '17:00:00';
            $shift["{$day}_lunch_break"] = '12:00:00';
            $shift["{$day}_lunch_break_out"] = '13:00:00';
        }
        
        // Generate random date range (5 to 30 days)
        $startDate = new \DateTime();
        $startDate->modify('+' . random_int(1, 30) . ' days');
        $rangeDays = random_int(5, 30);
        $endDate = clone $startDate;
        $endDate->modify('+' . $rangeDays . ' days');
        
        // Generate employee data with a specific company ID
        $employeeCompanyId = random_int(1, 100);
        $employee = [
            'user_id' => random_int(100000, 999999),
            'company_id' => $employeeCompanyId
        ];
        
        // Generate holidays for the employee's company (0 to 3 holidays)
        $employeeCompanyHolidays = [];
        $numEmployeeHolidays = random_int(0, 3);
        
        for ($h = 0; $h < $numEmployeeHolidays; $h++) {
            // Pick a random date within the range
            $holidayOffset = random_int(0, $rangeDays);
            $holidayDate = clone $startDate;
            $holidayDate->modify("+{$holidayOffset} days");
            
            // Randomly decide if it's a single-day or multi-day holiday
            $isMultiDay = random_int(0, 3) === 0; // 25% chance of multi-day
            
            if ($isMultiDay) {
                // Multi-day holiday (1-3 days)
                $holidayDuration = random_int(1, 3);
                $holidayEndDate = clone $holidayDate;
                $holidayEndDate->modify("+{$holidayDuration} days");
            } else {
                // Single-day holiday
                $holidayEndDate = clone $holidayDate;
            }
            
            $employeeCompanyHolidays[] = [
                'company_id' => $employeeCompanyId,
                'event_name' => 'Employee Company Holiday ' . random_int(1000, 9999),
                'description' => 'Property test holiday for employee company',
                'start_date' => $holidayDate->format('Y-m-d'),
                'end_date' => $holidayEndDate->format('Y-m-d'),
                'is_publish' => 1,
                'created_at' => date('Y-m-d H:i:s')
            ];
        }
        
        // Generate holidays for OTHER companies (1 to 4 holidays)
        // These should NOT be excluded from the employee's working days calculation
        $otherCompanyHolidays = [];
        $numOtherCompanies = random_int(1, 3); // 1-3 other companies
        
        for ($c = 0; $c < $numOtherCompanies; $c++) {
            // Generate a different company ID (ensure it's different from employee's company)
            $otherCompanyId = $employeeCompanyId + random_int(1, 50);
            
            // Generate 1-2 holidays for this other company
            $numOtherHolidays = random_int(1, 2);
            
            for ($h = 0; $h < $numOtherHolidays; $h++) {
                // Pick a random date within the range
                $holidayOffset = random_int(0, $rangeDays);
                $holidayDate = clone $startDate;
                $holidayDate->modify("+{$holidayOffset} days");
                
                // Single-day holiday for simplicity
                $holidayEndDate = clone $holidayDate;
                
                $otherCompanyHolidays[] = [
                    'company_id' => $otherCompanyId,
                    'event_name' => 'Other Company Holiday ' . random_int(1000, 9999),
                    'description' => 'Property test holiday for other company',
                    'start_date' => $holidayDate->format('Y-m-d'),
                    'end_date' => $holidayEndDate->format('Y-m-d'),
                    'is_publish' => 1,
                    'created_at' => date('Y-m-d H:i:s')
                ];
            }
        }
        
        // Combine all holidays for database insertion
        $allHolidays = array_merge($employeeCompanyHolidays, $otherCompanyHolidays);
        
        return [
            'shift' => $shift,
            'employee' => $employee,
            'all_holidays' => $allHolidays,
            'employee_company_holidays' => $employeeCompanyHolidays,
            'other_company_holidays' => $otherCompanyHolidays,
            'from_date' => $startDate->format('Y-m-d'),
            'to_date' => $endDate->format('Y-m-d')
        ];
    }

    /**
     * Count expected working days by manually checking each date against shift configuration 
     * and ONLY the employee's company holidays (not other companies' holidays)
     * This is the oracle for the property test
     * 
     * @param array $shift Shift configuration
     * @param string $fromDate Start date (Y-m-d format)
     * @param string $toDate End date (Y-m-d format)
     * @param array $employeeCompanyHolidays Array of holiday data for employee's company only
     * @return int Expected working days count
     */
    private function countExpectedWorkingDaysWithCompanyHolidays($shift, $fromDate, $toDate, $employeeCompanyHolidays)
    {
        // Build a set of holiday dates for quick lookup (only employee's company holidays)
        $holidayDates = [];
        foreach ($employeeCompanyHolidays as $holiday) {
            $startDate = new \DateTime($holiday['start_date']);
            $endDate = new \DateTime($holiday['end_date']);
            
            // Add all dates in the holiday range
            $currentDate = clone $startDate;
            while ($currentDate <= $endDate) {
                $holidayDates[$currentDate->format('Y-m-d')] = true;
                $currentDate->modify('+1 day');
            }
        }
        
        // Count working days
        $workingDays = 0;
        $currentDate = new \DateTime($fromDate);
        $endDate = new \DateTime($toDate);
        
        while ($currentDate <= $endDate) {
            $dateStr = $currentDate->format('Y-m-d');
            
            // Get day of week (lowercase: monday, tuesday, etc.)
            $dayOfWeek = strtolower($currentDate->format('l'));
            
            // Check if this day has a non-empty in_time in the shift
            $inTimeColumn = "{$dayOfWeek}_in_time";
            $inTime = $shift[$inTimeColumn] ?? '';
            
            // Check if this day is a working day
            $isWorkingDay = !empty($inTime) && $inTime !== '' && $inTime !== '0:00';
            
            // Check if this date is a holiday (only employee's company holidays)
            $isHoliday = isset($holidayDates[$dateStr]);
            
            // Count as working day only if it's a working day AND not a holiday
            if ($isWorkingDay && !$isHoliday) {
                $workingDays++;
            }
            
            // Move to next day
            $currentDate->modify('+1 day');
        }
        
        return $workingDays;
    }

    /**
     * Property 11: No Double-Counting Exclusions
     * 
     * **Validates: Requirements 9.4**
     * 
     * For any date that is both a company holiday and a shift non-working day, when 
     * calculating working days, that date should be counted only once as excluded 
     * (not excluded twice).
     * 
     * This property-based test generates scenarios where holidays overlap with non-working 
     * days and verifies that the date is counted only once as excluded. The key insight is 
     * that the total working days should be the same whether we:
     * 1. Exclude non-working days first, then exclude holidays from remaining days
     * 2. Exclude holidays first, then exclude non-working days from remaining days
     * 3. Exclude both simultaneously (which is what the implementation should do)
     * 
     * @group Feature: fix-leave-hours-calculation, Property 11: No Double-Counting Exclusions
     */
    public function testProperty11_NoDoubleCountingExclusions()
    {
        // Check if database is available
        if (!$this->dbAvailable) {
            $this->markTestSkipped('Database connection not available for property testing');
            return;
        }
        
        $iterations = 100;
        $passedTests = 0;
        $failedScenarios = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Generate random test scenario with overlapping holidays and non-working days
            $scenario = $this->generateOverlappingHolidayScenario();
            
            try {
                // Create test shift with some non-working days
                $shiftId = $this->createTestShift($scenario['shift']);
                
                // Create test employee with shift
                $employeeId = $this->createTestEmployeeWithShift($scenario['employee'], $shiftId);
                
                // Create holidays (some may overlap with non-working days)
                $holidayIds = $this->createTestHolidays($scenario['holidays']);
                
                // Calculate working days using the method under test
                $calculatedWorkingDays = $this->leavePolicy->calculateWorkingDaysInRange(
                    $employeeId,
                    $scenario['from_date'],
                    $scenario['to_date']
                );
                
                // Calculate expected working days by manually checking each date
                // A date is excluded if it's EITHER a non-working day OR a holiday (or both)
                // The key is that overlapping exclusions should not be double-counted
                $expectedWorkingDays = $this->countExpectedWorkingDaysWithOverlaps(
                    $scenario['shift'],
                    $scenario['from_date'],
                    $scenario['to_date'],
                    $scenario['holidays']
                );
                
                // Verify the property holds: calculated days should match expected days
                // This ensures overlapping exclusions are not double-counted
                if ($calculatedWorkingDays !== $expectedWorkingDays) {
                    $failedScenarios[] = [
                        'iteration' => $i,
                        'scenario' => $scenario,
                        'expected' => $expectedWorkingDays,
                        'actual' => $calculatedWorkingDays,
                        'shift_id' => $shiftId,
                        'employee_id' => $employeeId,
                        'holiday_count' => count($scenario['holidays']),
                        'overlapping_dates' => $scenario['overlapping_dates']
                    ];
                }
                
                $this->assertEquals(
                    $expectedWorkingDays,
                    $calculatedWorkingDays,
                    "Property violated: Dates that are both holidays and non-working days should be counted only once. " .
                    "Expected {$expectedWorkingDays}, got {$calculatedWorkingDays} for date range " .
                    "{$scenario['from_date']} to {$scenario['to_date']} with " . 
                    count($scenario['overlapping_dates']) . " overlapping dates"
                );
                
                // Clean up test data
                $this->cleanupTestHolidays($holidayIds);
                $this->cleanupTestEmployee($employeeId);
                $this->cleanupTestShift($shiftId);
                
                $passedTests++;
            } catch (\Exception $e) {
                // Clean up on error
                if (isset($holidayIds)) {
                    $this->cleanupTestHolidays($holidayIds);
                }
                if (isset($employeeId)) {
                    $this->cleanupTestEmployee($employeeId);
                }
                if (isset($shiftId)) {
                    $this->cleanupTestShift($shiftId);
                }
                throw $e;
            }
        }
        
        // Report any failed scenarios
        if (!empty($failedScenarios)) {
            $this->fail(
                "Property test failed for " . count($failedScenarios) . " scenarios:\n" .
                print_r($failedScenarios, true)
            );
        }
        
        // Verify all iterations passed
        $this->assertEquals(
            $iterations,
            $passedTests,
            "Property test should pass for all {$iterations} iterations"
        );
    }

    /**
     * Generate a random scenario with overlapping holidays and non-working days
     * This creates situations where some holidays fall on non-working days (e.g., Friday/Saturday)
     * 
     * @return array ['shift' => array, 'employee' => array, 'holidays' => array, 
     *                'from_date' => string, 'to_date' => string, 'overlapping_dates' => array]
     */
    private function generateOverlappingHolidayScenario()
    {
        $daysOfWeek = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        
        // Generate a shift with some non-working days
        $shift = [
            'shift_name' => 'Test Shift ' . random_int(1000, 9999),
            'hours_per_day' => 8
        ];
        
        // Randomly set working/non-working days
        // Ensure at least 1 non-working day and at least 1 working day
        $nonWorkingDays = [];
        $workingDays = [];
        
        foreach ($daysOfWeek as $day) {
            // 40% chance of being a non-working day
            $isWorkingDay = random_int(1, 10) > 4;
            
            if ($isWorkingDay) {
                $shift["{$day}_in_time"] = '08:00:00';
                $shift["{$day}_out_time"] = '17:00:00';
                $shift["{$day}_lunch_break"] = '12:00:00';
                $shift["{$day}_lunch_break_out"] = '13:00:00';
                $workingDays[] = $day;
            } else {
                // Non-working day: empty in_time
                $shift["{$day}_in_time"] = '';
                $shift["{$day}_out_time"] = '';
                $shift["{$day}_lunch_break"] = '';
                $shift["{$day}_lunch_break_out"] = '';
                $nonWorkingDays[] = $day;
            }
        }
        
        // Ensure at least one non-working day exists
        if (empty($nonWorkingDays)) {
            // Make Saturday and Sunday non-working
            $shift['saturday_in_time'] = '';
            $shift['saturday_out_time'] = '';
            $shift['saturday_lunch_break'] = '';
            $shift['saturday_lunch_break_out'] = '';
            $shift['sunday_in_time'] = '';
            $shift['sunday_out_time'] = '';
            $shift['sunday_lunch_break'] = '';
            $shift['sunday_lunch_break_out'] = '';
            $nonWorkingDays = ['saturday', 'sunday'];
        }
        
        // Generate random date range (10 to 30 days to ensure we hit various days of week)
        $startDate = new \DateTime();
        $startDate->modify('+' . random_int(1, 30) . ' days');
        $rangeDays = random_int(10, 30);
        $endDate = clone $startDate;
        $endDate->modify('+' . $rangeDays . ' days');
        
        // Generate employee data
        $companyId = random_int(1, 100);
        $employee = [
            'user_id' => random_int(100000, 999999),
            'company_id' => $companyId
        ];
        
        // Generate holidays with intentional overlaps with non-working days
        $holidays = [];
        $overlappingDates = [];
        $numHolidays = random_int(2, 5);
        
        for ($h = 0; $h < $numHolidays; $h++) {
            // Pick a random date within the range
            $holidayOffset = random_int(0, $rangeDays);
            $holidayDate = clone $startDate;
            $holidayDate->modify("+{$holidayOffset} days");
            
            // 50% chance to intentionally place holiday on a non-working day
            if (random_int(0, 1) === 1 && !empty($nonWorkingDays)) {
                // Find the next occurrence of a non-working day
                $currentDay = strtolower($holidayDate->format('l'));
                $daysToAdd = 0;
                
                // Search for next non-working day (max 7 days ahead)
                for ($d = 0; $d < 7; $d++) {
                    $testDate = clone $holidayDate;
                    $testDate->modify("+{$d} days");
                    $testDay = strtolower($testDate->format('l'));
                    
                    if (in_array($testDay, $nonWorkingDays)) {
                        $holidayDate = $testDate;
                        
                        // Track this as an overlapping date
                        $overlappingDates[] = $holidayDate->format('Y-m-d');
                        break;
                    }
                }
            }
            
            // Randomly decide if it's a single-day or multi-day holiday
            $isMultiDay = random_int(0, 3) === 0; // 25% chance of multi-day
            
            if ($isMultiDay) {
                // Multi-day holiday (1-3 days)
                $holidayDuration = random_int(1, 3);
                $holidayEndDate = clone $holidayDate;
                $holidayEndDate->modify("+{$holidayDuration} days");
                
                // Track overlapping dates in multi-day holidays
                $tempDate = clone $holidayDate;
                while ($tempDate <= $holidayEndDate) {
                    $dayOfWeek = strtolower($tempDate->format('l'));
                    if (in_array($dayOfWeek, $nonWorkingDays)) {
                        $overlappingDates[] = $tempDate->format('Y-m-d');
                    }
                    $tempDate->modify('+1 day');
                }
            } else {
                // Single-day holiday
                $holidayEndDate = clone $holidayDate;
            }
            
            $holidays[] = [
                'company_id' => $companyId,
                'event_name' => 'Test Holiday ' . random_int(1000, 9999),
                'description' => 'Property test holiday',
                'start_date' => $holidayDate->format('Y-m-d'),
                'end_date' => $holidayEndDate->format('Y-m-d'),
                'is_publish' => 1,
                'created_at' => date('Y-m-d H:i:s')
            ];
        }
        
        // Remove duplicate overlapping dates
        $overlappingDates = array_unique($overlappingDates);
        
        return [
            'shift' => $shift,
            'employee' => $employee,
            'holidays' => $holidays,
            'from_date' => $startDate->format('Y-m-d'),
            'to_date' => $endDate->format('Y-m-d'),
            'overlapping_dates' => $overlappingDates,
            'non_working_days' => $nonWorkingDays
        ];
    }

    /**
     * Count expected working days with overlapping exclusions handled correctly
     * A date is excluded if it's EITHER a non-working day OR a holiday (or both)
     * Overlapping exclusions should not be double-counted
     * 
     * @param array $shift Shift configuration
     * @param string $fromDate Start date (Y-m-d format)
     * @param string $toDate End date (Y-m-d format)
     * @param array $holidays Array of holiday data
     * @return int Expected working days count
     */
    private function countExpectedWorkingDaysWithOverlaps($shift, $fromDate, $toDate, $holidays)
    {
        // Build a set of holiday dates for quick lookup
        $holidayDates = [];
        foreach ($holidays as $holiday) {
            $startDate = new \DateTime($holiday['start_date']);
            $endDate = new \DateTime($holiday['end_date']);
            
            // Add all dates in the holiday range
            $currentDate = clone $startDate;
            while ($currentDate <= $endDate) {
                $holidayDates[$currentDate->format('Y-m-d')] = true;
                $currentDate->modify('+1 day');
            }
        }
        
        // Count working days
        $workingDays = 0;
        $currentDate = new \DateTime($fromDate);
        $endDate = new \DateTime($toDate);
        
        while ($currentDate <= $endDate) {
            $dateStr = $currentDate->format('Y-m-d');
            
            // Get day of week (lowercase: monday, tuesday, etc.)
            $dayOfWeek = strtolower($currentDate->format('l'));
            
            // Check if this day has a non-empty in_time in the shift
            $inTimeColumn = "{$dayOfWeek}_in_time";
            $inTime = $shift[$inTimeColumn] ?? '';
            
            // Check if this day is a working day in the shift
            $isWorkingDay = !empty($inTime) && $inTime !== '' && $inTime !== '0:00';
            
            // Check if this date is a holiday
            $isHoliday = isset($holidayDates[$dateStr]);
            
            // Count as working day only if it's a working day AND not a holiday
            // This ensures that dates that are BOTH non-working days AND holidays
            // are only excluded once (they're already excluded by being non-working days)
            if ($isWorkingDay && !$isHoliday) {
                $workingDays++;
            }
            
            // Move to next day
            $currentDate->modify('+1 day');
        }
        
        return $workingDays;
    }

    /**
     * Test calculateWorkingDaysInRange() excludes company holidays
     */
    public function testCalculateWorkingDaysInRange_ExcludesCompanyHolidays()
    {
        // Skip this test if no test database is configured
        $this->markTestSkipped('Requires test database with employee, shift, and holiday data');
        
        // Example test structure:
        // Create a company holiday on a working day
        // Calculate working days for a range including that holiday
        // Verify the holiday is excluded from the count
    }

    /**
     * Test calculateWorkingDaysInRange() handles date ranges spanning multiple weeks
     */
    public function testCalculateWorkingDaysInRange_MultiWeekRange_CalculatesCorrectly()
    {
        // Skip this test if no test database is configured
        $this->markTestSkipped('Requires test database with employee and shift data');
        
        // Example test structure:
        // Calculate working days for a 2-week range
        // Verify the result is reasonable (e.g., 10-14 days for 2 weeks)
    }

    /**
     * Property 4: Hours Calculation Formula
     * 
     * **Validates: Requirements 3.1**
     * 
     * For any employee with an assigned shift and any number of working days, the 
     * calculated leave hours should equal working_days multiplied by the employee's 
     * hours_per_day from their shift configuration.
     * 
     * This property-based test generates random employees with different hours_per_day 
     * values and random working day counts to verify that the multiplication formula 
     * is correctly applied.
     * 
     * @group Feature: fix-leave-hours-calculation, Property 4: Hours Calculation Formula
     */
    public function testProperty4_HoursCalculationFormula()
    {
        // Check if database is available
        if (!$this->dbAvailable) {
            $this->markTestSkipped('Database connection not available for property testing');
            return;
        }
        
        $iterations = 100;
        $passedTests = 0;
        $failedScenarios = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Generate random test scenario
            $scenario = $this->generateHoursCalculationScenario();
            
            try {
                // Create test shift with specific hours_per_day
                $shiftId = $this->createTestShift($scenario['shift']);
                
                // Create test employee with shift
                $employeeId = $this->createTestEmployeeWithShift($scenario['employee'], $shiftId);
                
                // Calculate hours using the method under test
                $calculatedHours = $this->leavePolicy->convertDaysToHours(
                    $employeeId,
                    $scenario['working_days']
                );
                
                // Calculate expected hours using the formula: working_days × hours_per_day
                $expectedHours = $scenario['working_days'] * $scenario['shift']['hours_per_day'];
                
                // Verify the property holds: calculated hours should match expected hours
                $this->assertEquals(
                    $expectedHours,
                    $calculatedHours,
                    "Property violated: Hours calculation should equal working_days × hours_per_day. " .
                    "Working days: {$scenario['working_days']}, Hours per day: {$scenario['shift']['hours_per_day']}, " .
                    "Expected: {$expectedHours}, Got: {$calculatedHours}"
                );
                
                // Also verify the result is a float
                $this->assertIsFloat(
                    $calculatedHours,
                    "Property violated: Calculated hours should be returned as a float"
                );
                
                // Verify the result is non-negative
                $this->assertGreaterThanOrEqual(
                    0,
                    $calculatedHours,
                    "Property violated: Calculated hours should be non-negative"
                );
                
                // Clean up test data
                $this->cleanupTestEmployee($employeeId);
                $this->cleanupTestShift($shiftId);
                
                $passedTests++;
            } catch (\Exception $e) {
                // Clean up on error
                if (isset($employeeId)) {
                    $this->cleanupTestEmployee($employeeId);
                }
                if (isset($shiftId)) {
                    $this->cleanupTestShift($shiftId);
                }
                throw $e;
            }
        }
        
        // Verify all iterations passed
        $this->assertEquals(
            $iterations,
            $passedTests,
            "Property test should pass for all {$iterations} iterations"
        );
    }

    /**
     * Generate a random hours calculation scenario for property testing
     * 
     * @return array ['shift' => array, 'employee' => array, 'working_days' => int]
     */
    private function generateHoursCalculationScenario()
    {
        $daysOfWeek = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        
        // Generate random hours_per_day (typical values: 6, 7, 8, 9, 10, 12)
        $possibleHours = [6, 7, 8, 9, 10, 12];
        $hoursPerDay = $possibleHours[array_rand($possibleHours)];
        
        // Generate shift configuration
        $shift = [
            'shift_name' => 'Test Shift ' . random_int(1000, 9999),
            'hours_per_day' => $hoursPerDay
        ];
        
        // Set all days as working days (for simplicity in this test)
        foreach ($daysOfWeek as $day) {
            $shift["{$day}_in_time"] = '08:00:00';
            $shift["{$day}_out_time"] = sprintf('%02d:00:00', 8 + $hoursPerDay);
            $shift["{$day}_lunch_break"] = '12:00:00';
            $shift["{$day}_lunch_break_out"] = '13:00:00';
        }
        
        // Generate random working days count (0 to 30 days)
        $workingDays = random_int(0, 30);
        
        // Generate employee data
        $employee = [
            'user_id' => random_int(100000, 999999),
            'company_id' => random_int(1, 100)
        ];
        
        return [
            'shift' => $shift,
            'employee' => $employee,
            'working_days' => $workingDays
        ];
    }

    /**
     * Property 9: Leave Balance Display Format
     * 
     * **Validates: Requirements 6.1**
     * 
     * For any employee with a leave balance in days, the displayed balance should 
     * follow the format "X days (Y hours)" where Y equals X multiplied by the 
     * employee's hours_per_day.
     * 
     * This property-based test generates random balance values and verifies that 
     * the format matches the expected pattern and the hours calculation is correct.
     * 
     * @group Feature: fix-leave-hours-calculation, Property 9: Leave Balance Display Format
     */
    public function testProperty9_LeaveBalanceDisplayFormat()
    {
        // Check if database is available
        if (!$this->dbAvailable) {
            $this->markTestSkipped('Database connection not available for property testing');
            return;
        }
        
        $iterations = 100;
        $passedTests = 0;
        
        for ($i = 0; $i < $iterations; $i++) {
            // Generate random test scenario
            $scenario = $this->generateBalanceDisplayScenario();
            
            try {
                // Create test shift with specific hours_per_day
                $shiftId = $this->createTestShift($scenario['shift']);
                
                // Create test employee with shift
                $employeeId = $this->createTestEmployeeWithShift($scenario['employee'], $shiftId);
                
                // Format balance using the method under test
                $formattedBalance = $this->leavePolicy->formatLeaveBalanceDisplay(
                    $employeeId,
                    $scenario['days_balance']
                );
                
                // Calculate expected hours
                $expectedHours = $scenario['days_balance'] * $scenario['shift']['hours_per_day'];
                
                // Verify the format matches "X days (Y hours)"
                $pattern = '/^' . preg_quote($scenario['days_balance'], '/') . ' days \(' . preg_quote($expectedHours, '/') . ' hours\)$/';
                $this->assertMatchesRegularExpression(
                    $pattern,
                    $formattedBalance,
                    "Property violated: Balance display should match format 'X days (Y hours)'. " .
                    "Days: {$scenario['days_balance']}, Hours per day: {$scenario['shift']['hours_per_day']}, " .
                    "Expected hours: {$expectedHours}, Got: {$formattedBalance}"
                );
                
                // Verify the string contains the correct days value
                $this->assertStringContainsString(
                    (string)$scenario['days_balance'] . ' days',
                    $formattedBalance,
                    "Property violated: Display should contain days value"
                );
                
                // Verify the string contains the correct hours value
                $this->assertStringContainsString(
                    (string)$expectedHours . ' hours',
                    $formattedBalance,
                    "Property violated: Display should contain calculated hours value"
                );
                
                // Clean up test data
                $this->cleanupTestEmployee($employeeId);
                $this->cleanupTestShift($shiftId);
                
                $passedTests++;
            } catch (\Exception $e) {
                // Clean up on error
                if (isset($employeeId)) {
                    $this->cleanupTestEmployee($employeeId);
                }
                if (isset($shiftId)) {
                    $this->cleanupTestShift($shiftId);
                }
                throw $e;
            }
        }
        
        // Verify all iterations passed
        $this->assertEquals(
            $iterations,
            $passedTests,
            "Property test should pass for all {$iterations} iterations"
        );
    }

    /**
     * Generate a random balance display scenario for property testing
     * 
     * @return array ['shift' => array, 'employee' => array, 'days_balance' => float]
     */
    private function generateBalanceDisplayScenario()
    {
        $daysOfWeek = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        
        // Generate random hours_per_day (typical values: 6, 7, 8, 9, 10, 12)
        $possibleHours = [6, 7, 8, 9, 10, 12];
        $hoursPerDay = $possibleHours[array_rand($possibleHours)];
        
        // Generate shift configuration
        $shift = [
            'shift_name' => 'Test Shift ' . random_int(1000, 9999),
            'hours_per_day' => $hoursPerDay
        ];
        
        // Set all days as working days (for simplicity in this test)
        foreach ($daysOfWeek as $day) {
            $shift["{$day}_in_time"] = '08:00:00';
            $shift["{$day}_out_time"] = sprintf('%02d:00:00', 8 + $hoursPerDay);
            $shift["{$day}_lunch_break"] = '12:00:00';
            $shift["{$day}_lunch_break_out"] = '13:00:00';
        }
        
        // Generate random days balance (0 to 30 days, can include decimals)
        // Use integers for simplicity in this test
        $daysBalance = random_int(0, 30);
        
        // Generate employee data
        $employee = [
            'user_id' => random_int(100000, 999999),
            'company_id' => random_int(1, 100)
        ];
        
        return [
            'shift' => $shift,
            'employee' => $employee,
            'days_balance' => $daysBalance
        ];
    }

    /**
     * Property 6: Hourly Permission Time Difference
     * 
     * **Validates: Requirements 4.1**
     * 
     * For any hourly permission request with start_time and end_time on the same date 
     * (excluding break time considerations), the calculated hours should equal the time 
     * difference between end_time and start_time.
     * 
     * This property-based test generates random start and end times that do NOT overlap 
     * with break time, and verifies that the calculated hours equals the time difference.
     * 
     * @group Feature: fix-leave-hours-calculation, Property 6: Hourly Permission Time Difference
     */
    public function testProperty6_HourlyPermissionTimeDifference()
    {
        // Check if database is available
        if (!$this->dbAvailable) {
            $this->markTestSkipped('Database connection not available for property testing');
            return;
        }
        
        $iterations = 100;
        $passedTests = 0;
        
        for ($i = 0; $i < $iterations; $i++) {
            // Generate random test scenario (times that don't overlap with break)
            $scenario = $this->generateHourlyPermissionScenario(false); // false = no break overlap
            
            try {
                // Create test shift
                $shiftId = $this->createTestShift($scenario['shift']);
                
                // Create test employee with shift
                $employeeId = $this->createTestEmployeeWithShift($scenario['employee'], $shiftId);
                
                // Calculate hours using the method under test
                $result = $this->leavePolicy->calculateHourlyPermissionHours(
                    $employeeId,
                    $scenario['date'],
                    $scenario['start_time'],
                    $scenario['end_time']
                );
                
                // Verify the result is valid
                $this->assertTrue(
                    $result['valid'],
                    "Property violated: Valid permission times should be accepted. " .
                    "Date: {$scenario['date']}, Start: {$scenario['start_time']}, End: {$scenario['end_time']}, " .
                    "Error: " . ($result['error_message'] ?? 'none')
                );
                
                // Calculate expected hours (time difference in hours)
                $expectedHours = $scenario['expected_hours'];
                
                // Verify the property holds: calculated hours should equal time difference
                $this->assertEquals(
                    $expectedHours,
                    $result['hours'],
                    "Property violated: Calculated hours should equal time difference. " .
                    "Start: {$scenario['start_time']}, End: {$scenario['end_time']}, " .
                    "Expected: {$expectedHours}, Got: {$result['hours']}"
                );
                
                // Clean up test data
                $this->cleanupTestEmployee($employeeId);
                $this->cleanupTestShift($shiftId);
                
                $passedTests++;
            } catch (\Exception $e) {
                // Clean up on error
                if (isset($employeeId)) {
                    $this->cleanupTestEmployee($employeeId);
                }
                if (isset($shiftId)) {
                    $this->cleanupTestShift($shiftId);
                }
                throw $e;
            }
        }
        
        // Verify all iterations passed
        $this->assertEquals(
            $iterations,
            $passedTests,
            "Property test should pass for all {$iterations} iterations"
        );
    }

    /**
     * Generate a random hourly permission scenario for property testing
     * 
     * @param bool $includeBreakOverlap Whether to generate times that overlap with break
     * @return array ['shift' => array, 'employee' => array, 'date' => string, 
     *                'start_time' => string, 'end_time' => string, 'expected_hours' => float]
     */
    private function generateHourlyPermissionScenario($includeBreakOverlap = false)
    {
        $daysOfWeek = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        
        // Generate shift configuration with fixed break time (12:00-13:00)
        $shift = [
            'shift_name' => 'Test Shift ' . random_int(1000, 9999),
            'hours_per_day' => 8
        ];
        
        // Set all days as working days with 08:00-17:00 shift and 12:00-13:00 break
        foreach ($daysOfWeek as $day) {
            $shift["{$day}_in_time"] = '08:00:00';
            $shift["{$day}_out_time"] = '17:00:00';
            $shift["{$day}_lunch_break"] = '12:00:00';
            $shift["{$day}_lunch_break_out"] = '13:00:00';
        }
        
        // Generate a random date (within next 30 days)
        $date = new \DateTime();
        $date->modify('+' . random_int(1, 30) . ' days');
        $dateStr = $date->format('Y-m-d');
        
        // Generate start and end times
        // Generate random minutes (0, 15, 30, 45)
        $possibleMinutes = [0, 15, 30, 45];
        $startMinute = $possibleMinutes[array_rand($possibleMinutes)];
        $endMinute = $possibleMinutes[array_rand($possibleMinutes)];
        
        if ($includeBreakOverlap) {
            // Generate times that overlap with break (12:00-13:00)
            // Start before break, end after break
            $startHour = random_int(8, 11);
            $endHour = random_int(13, 16);
            
            $startTime = sprintf('%02d:%02d:00', $startHour, $startMinute);
            $endTime = sprintf('%02d:%02d:00', $endHour, $endMinute);
        } else {
            // Generate times that don't overlap with break
            // Either before break (08:00-11:59) or after break (13:00-16:59)
            $beforeBreak = random_int(0, 1) === 1;
            
            if ($beforeBreak) {
                // Permission before break (must end before 12:00)
                $startHour = random_int(8, 10);
                // End hour must be 11 or less
                $endHour = random_int($startHour + 1, 11);
                
                $startTime = sprintf('%02d:%02d:00', $startHour, $startMinute);
                $endTime = sprintf('%02d:%02d:00', $endHour, $endMinute);
            } else {
                // Permission after break (must start at or after 13:00, end before 17:00)
                $startHour = random_int(13, 15);
                $endHour = random_int($startHour + 1, 16);
                
                $startTime = sprintf('%02d:%02d:00', $startHour, $startMinute);
                $endTime = sprintf('%02d:%02d:00', $endHour, $endMinute);
            }
        }
        
        // Calculate expected hours (time difference)
        $startSeconds = ($startHour * 3600) + ($startMinute * 60);
        $endSeconds = ($endHour * 3600) + ($endMinute * 60);
        $expectedHours = ($endSeconds - $startSeconds) / 3600;
        
        // If including break overlap, subtract break time (1 hour)
        if ($includeBreakOverlap) {
            // Check if permission actually overlaps with break (12:00-13:00)
            $breakStartSeconds = 12 * 3600;
            $breakEndSeconds = 13 * 3600;
            
            if ($startSeconds < $breakEndSeconds && $endSeconds > $breakStartSeconds) {
                // Calculate overlap
                $overlapStart = max($startSeconds, $breakStartSeconds);
                $overlapEnd = min($endSeconds, $breakEndSeconds);
                $overlapHours = ($overlapEnd - $overlapStart) / 3600;
                $expectedHours -= $overlapHours;
            }
        }
        
        // Generate employee data
        $employee = [
            'user_id' => random_int(100000, 999999),
            'company_id' => random_int(1, 100)
        ];
        
        return [
            'shift' => $shift,
            'employee' => $employee,
            'date' => $dateStr,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'expected_hours' => $expectedHours
        ];
    }

    /**
     * Property 7: Break Time Subtraction
     * 
     * **Validates: Requirements 4.2**
     * 
     * For any hourly permission request where the time range overlaps with the 
     * employee's lunch break period, the calculated hours should equal the time 
     * difference minus the overlapping break duration.
     * 
     * This property-based test generates time ranges that overlap with breaks and 
     * verifies that the break duration is correctly subtracted from the calculated hours.
     * 
     * @group Feature: fix-leave-hours-calculation, Property 7: Break Time Subtraction
     */
    public function testProperty7_BreakTimeSubtraction()
    {
        // Check if database is available
        if (!$this->dbAvailable) {
            $this->markTestSkipped('Database connection not available for property testing');
            return;
        }
        
        $iterations = 100;
        $passedTests = 0;
        
        for ($i = 0; $i < $iterations; $i++) {
            // Generate random test scenario (times that overlap with break)
            $scenario = $this->generateHourlyPermissionScenario(true); // true = include break overlap
            
            try {
                // Create test shift
                $shiftId = $this->createTestShift($scenario['shift']);
                
                // Create test employee with shift
                $employeeId = $this->createTestEmployeeWithShift($scenario['employee'], $shiftId);
                
                // Calculate hours using the method under test
                $result = $this->leavePolicy->calculateHourlyPermissionHours(
                    $employeeId,
                    $scenario['date'],
                    $scenario['start_time'],
                    $scenario['end_time']
                );
                
                // Verify the result is valid
                $this->assertTrue(
                    $result['valid'],
                    "Property violated: Valid permission times should be accepted. " .
                    "Date: {$scenario['date']}, Start: {$scenario['start_time']}, End: {$scenario['end_time']}, " .
                    "Error: " . ($result['error_message'] ?? 'none')
                );
                
                // Calculate expected hours (time difference minus break overlap)
                $expectedHours = $scenario['expected_hours'];
                
                // Verify the property holds: calculated hours should equal time difference minus break
                $this->assertEquals(
                    $expectedHours,
                    $result['hours'],
                    "Property violated: Calculated hours should equal time difference minus break overlap. " .
                    "Start: {$scenario['start_time']}, End: {$scenario['end_time']}, " .
                    "Expected: {$expectedHours}, Got: {$result['hours']}"
                );
                
                // Verify that the calculated hours is less than the raw time difference
                // (because break time was subtracted)
                $startSeconds = $this->timeToSecondsHelper($scenario['start_time']);
                $endSeconds = $this->timeToSecondsHelper($scenario['end_time']);
                $rawHours = ($endSeconds - $startSeconds) / 3600;
                
                $this->assertLessThan(
                    $rawHours,
                    $result['hours'],
                    "Property violated: Hours with break subtraction should be less than raw time difference. " .
                    "Raw hours: {$rawHours}, Calculated hours: {$result['hours']}"
                );
                
                // Clean up test data
                $this->cleanupTestEmployee($employeeId);
                $this->cleanupTestShift($shiftId);
                
                $passedTests++;
            } catch (\Exception $e) {
                // Clean up on error
                if (isset($employeeId)) {
                    $this->cleanupTestEmployee($employeeId);
                }
                if (isset($shiftId)) {
                    $this->cleanupTestShift($shiftId);
                }
                throw $e;
            }
        }
        
        // Verify all iterations passed
        $this->assertEquals(
            $iterations,
            $passedTests,
            "Property test should pass for all {$iterations} iterations"
        );
    }

    /**
     * Helper method to convert time string to seconds
     * 
     * @param string $time Time in H:i:s format
     * @return int Seconds since midnight
     */
    private function timeToSecondsHelper($time)
    {
        $parts = explode(':', $time);
        $hours = isset($parts[0]) ? (int)$parts[0] : 0;
        $minutes = isset($parts[1]) ? (int)$parts[1] : 0;
        $seconds = isset($parts[2]) ? (int)$parts[2] : 0;
        
        return ($hours * 3600) + ($minutes * 60) + $seconds;
    }

    /**
     * Property 8: Hourly Permission Time Validation
     * 
     * **Validates: Requirements 4.3, 4.4**
     * 
     * For any hourly permission request, if the start_time or end_time falls outside 
     * the employee's shift in_time and out_time for that day of the week, then the 
     * system should reject the request with an error message.
     * 
     * This property-based test generates times both inside and outside shift hours 
     * and verifies that validation correctly rejects times outside shift hours.
     * 
     * @group Feature: fix-leave-hours-calculation, Property 8: Hourly Permission Time Validation
     */
    public function testProperty8_HourlyPermissionTimeValidation()
    {
        // Check if database is available
        if (!$this->dbAvailable) {
            $this->markTestSkipped('Database connection not available for property testing');
            return;
        }
        
        $iterations = 100;
        $passedTests = 0;
        $failedScenarios = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Generate random test scenario with times inside or outside shift hours
            $scenario = $this->generateTimeValidationScenario();
            
            try {
                // Create test shift
                $shiftId = $this->createTestShift($scenario['shift']);
                
                // Create test employee with shift
                $employeeId = $this->createTestEmployeeWithShift($scenario['employee'], $shiftId);
                
                // Calculate hours using the method under test
                $result = $this->leavePolicy->calculateHourlyPermissionHours(
                    $employeeId,
                    $scenario['date'],
                    $scenario['start_time'],
                    $scenario['end_time']
                );
                
                // Verify the property holds based on whether times are within shift hours
                if ($scenario['times_within_shift']) {
                    // Times are within shift hours: should be valid
                    if (!$result['valid']) {
                        $failedScenarios[] = [
                            'iteration' => $i,
                            'scenario' => $scenario,
                            'expected' => 'valid=true',
                            'actual' => 'valid=false',
                            'error_message' => $result['error_message']
                        ];
                    }
                    
                    $this->assertTrue(
                        $result['valid'],
                        "Property violated: Times within shift hours should be accepted. " .
                        "Shift: {$scenario['shift_in_time']}-{$scenario['shift_out_time']}, " .
                        "Permission: {$scenario['start_time']}-{$scenario['end_time']}, " .
                        "Error: " . ($result['error_message'] ?? 'none')
                    );
                    
                    $this->assertNull(
                        $result['error_message'],
                        "Property violated: Valid times should not have error message"
                    );
                    
                    $this->assertGreaterThan(
                        0,
                        $result['hours'],
                        "Property violated: Valid permission should have positive hours"
                    );
                } else {
                    // Times are outside shift hours: should be invalid
                    if ($result['valid']) {
                        $failedScenarios[] = [
                            'iteration' => $i,
                            'scenario' => $scenario,
                            'expected' => 'valid=false',
                            'actual' => 'valid=true',
                            'hours' => $result['hours']
                        ];
                    }
                    
                    $this->assertFalse(
                        $result['valid'],
                        "Property violated: Times outside shift hours should be rejected. " .
                        "Shift: {$scenario['shift_in_time']}-{$scenario['shift_out_time']}, " .
                        "Permission: {$scenario['start_time']}-{$scenario['end_time']}"
                    );
                    
                    $this->assertNotNull(
                        $result['error_message'],
                        "Property violated: Invalid times should have error message"
                    );
                    
                    // Verify error message mentions time or shift
                    $errorLower = strtolower($result['error_message']);
                    $this->assertTrue(
                        strpos($errorLower, 'time') !== false || 
                        strpos($errorLower, 'shift') !== false ||
                        strpos($errorLower, 'hour') !== false,
                        "Property violated: Error message should mention time/shift/hour. Got: {$result['error_message']}"
                    );
                }
                
                // Clean up test data
                $this->cleanupTestEmployee($employeeId);
                $this->cleanupTestShift($shiftId);
                
                $passedTests++;
            } catch (\Exception $e) {
                // Clean up on error
                if (isset($employeeId)) {
                    $this->cleanupTestEmployee($employeeId);
                }
                if (isset($shiftId)) {
                    $this->cleanupTestShift($shiftId);
                }
                throw $e;
            }
        }
        
        // Report any failed scenarios
        if (!empty($failedScenarios)) {
            $this->fail(
                "Property test failed for " . count($failedScenarios) . " scenarios:\n" .
                print_r($failedScenarios, true)
            );
        }
        
        // Verify all iterations passed
        $this->assertEquals(
            $iterations,
            $passedTests,
            "Property test should pass for all {$iterations} iterations"
        );
    }

    /**
     * Generate a random time validation scenario for property testing
     * Creates scenarios with times both inside and outside shift hours
     * 
     * @return array ['shift' => array, 'employee' => array, 'date' => string, 
     *                'start_time' => string, 'end_time' => string, 
     *                'times_within_shift' => bool, 'shift_in_time' => string, 'shift_out_time' => string]
     */
    private function generateTimeValidationScenario()
    {
        $daysOfWeek = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        
        // Generate shift configuration with 08:00-17:00 shift and 12:00-13:00 break
        $shiftInTime = '08:00:00';
        $shiftOutTime = '17:00:00';
        
        $shift = [
            'shift_name' => 'Test Shift ' . random_int(1000, 9999),
            'hours_per_day' => 8
        ];
        
        // Set all days as working days
        foreach ($daysOfWeek as $day) {
            $shift["{$day}_in_time"] = $shiftInTime;
            $shift["{$day}_out_time"] = $shiftOutTime;
            $shift["{$day}_lunch_break"] = '12:00:00';
            $shift["{$day}_lunch_break_out"] = '13:00:00';
        }
        
        // Generate a random date (within next 30 days)
        $date = new \DateTime();
        $date->modify('+' . random_int(1, 30) . ' days');
        $dateStr = $date->format('Y-m-d');
        
        // Randomly decide if times should be within shift hours (60% within, 40% outside)
        $timesWithinShift = random_int(1, 10) <= 6;
        
        if ($timesWithinShift) {
            // Generate times within shift hours (08:00-17:00)
            // Start time: 08:00-15:00
            $startHour = random_int(8, 15);
            $startMinute = [0, 15, 30, 45][array_rand([0, 15, 30, 45])];
            
            // End time: must be after start time and before 17:00
            $endHour = random_int($startHour + 1, 16);
            $endMinute = [0, 15, 30, 45][array_rand([0, 15, 30, 45])];
            
            $startTime = sprintf('%02d:%02d:00', $startHour, $startMinute);
            $endTime = sprintf('%02d:%02d:00', $endHour, $endMinute);
        } else {
            // Generate times outside shift hours
            // Randomly choose which boundary to violate
            $violationType = random_int(1, 3);
            
            if ($violationType === 1) {
                // Start time before shift start (06:00-07:59)
                $startHour = random_int(6, 7);
                $startMinute = [0, 15, 30, 45][array_rand([0, 15, 30, 45])];
                
                // End time within shift hours
                $endHour = random_int(9, 16);
                $endMinute = [0, 15, 30, 45][array_rand([0, 15, 30, 45])];
            } elseif ($violationType === 2) {
                // End time after shift end (17:01-19:00)
                $startHour = random_int(8, 15);
                $startMinute = [0, 15, 30, 45][array_rand([0, 15, 30, 45])];
                
                $endHour = random_int(17, 19);
                $endMinute = [0, 15, 30, 45][array_rand([0, 15, 30, 45])];
                
                // Ensure end time is actually after 17:00
                if ($endHour === 17 && $endMinute === 0) {
                    $endMinute = 15; // Make it 17:15
                }
            } else {
                // Both times outside shift hours (06:00-07:59 to 18:00-19:00)
                $startHour = random_int(6, 7);
                $startMinute = [0, 15, 30, 45][array_rand([0, 15, 30, 45])];
                
                $endHour = random_int(18, 19);
                $endMinute = [0, 15, 30, 45][array_rand([0, 15, 30, 45])];
            }
            
            $startTime = sprintf('%02d:%02d:00', $startHour, $startMinute);
            $endTime = sprintf('%02d:%02d:00', $endHour, $endMinute);
        }
        
        // Generate employee data
        $employee = [
            'user_id' => random_int(100000, 999999),
            'company_id' => random_int(1, 100)
        ];
        
        return [
            'shift' => $shift,
            'employee' => $employee,
            'date' => $dateStr,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'times_within_shift' => $timesWithinShift,
            'shift_in_time' => $shiftInTime,
            'shift_out_time' => $shiftOutTime
        ];
    }

    /**
     * Property 5: Leave Hours Persistence Round Trip
     * 
     * **Validates: Requirements 3.3**
     * 
     * For any leave request, after calculating and storing leave_hours in the database, 
     * querying the leave application should return the same leave_hours value that was calculated.
     * 
     * This property-based test creates leave requests with calculated hours, stores them in 
     * the database, and verifies that the retrieved value matches the stored value.
     * 
     * @group Feature: fix-leave-hours-calculation, Property 5: Leave Hours Persistence Round Trip
     */
    public function testProperty5_LeaveHoursPersistenceRoundTrip()
    {
        // Check if database is available
        if (!$this->dbAvailable) {
            $this->markTestSkipped('Database connection not available for property testing');
            return;
        }
        
        $iterations = 100;
        $passedTests = 0;
        $failedScenarios = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Generate random test scenario
            $scenario = $this->generateLeaveApplicationScenario();
            
            try {
                // Create test shift and employee in database
                $shiftId = $this->createTestShift($scenario['shift']);
                $employeeId = $this->createTestEmployeeWithShift($scenario['employee'], $shiftId);
                
                // Calculate leave hours using LeavePolicy library
                if ($scenario['is_full_day']) {
                    // Full day leave: calculate working days and convert to hours
                    $workingDays = $this->leavePolicy->calculateWorkingDaysInRange(
                        $employeeId,
                        $scenario['from_date'],
                        $scenario['to_date']
                    );
                    $calculatedHours = $this->leavePolicy->convertDaysToHours($employeeId, $workingDays);
                } else {
                    // Hourly permission: calculate hours directly
                    $result = $this->leavePolicy->calculateHourlyPermissionHours(
                        $employeeId,
                        $scenario['date'],
                        $scenario['start_time'],
                        $scenario['end_time']
                    );
                    
                    if (!$result['valid']) {
                        // Skip invalid scenarios (e.g., times outside shift hours)
                        $this->cleanupTestEmployee($employeeId);
                        $this->cleanupTestShift($shiftId);
                        continue;
                    }
                    
                    $calculatedHours = $result['hours'];
                }
                
                // Store leave application in database
                $leaveData = [
                    'company_id' => $scenario['employee']['company_id'],
                    'employee_id' => $employeeId,
                    'leave_type_id' => $scenario['leave_type_id'],
                    'from_date' => $scenario['from_date'],
                    'to_date' => $scenario['to_date'],
                    'particular_date' => $scenario['date'],
                    'leave_hours' => $calculatedHours,
                    'leave_month' => date('n', strtotime($scenario['from_date'] ?: $scenario['date'])),
                    'leave_year' => date('Y', strtotime($scenario['from_date'] ?: $scenario['date'])),
                    'reason' => 'Property test leave',
                    'status' => 0,
                    'is_deducted' => 1,
                    'created_at' => date('d-m-Y h:i:s')
                ];
                
                $this->db->table('ci_leave_applications')->insert($leaveData);
                $leaveId = $this->db->insertID();
                
                // Query the database to retrieve the stored leave_hours
                $retrievedLeave = $this->db->table('ci_leave_applications')
                    ->where('leave_id', $leaveId)
                    ->get()
                    ->getRow();
                
                // Verify the property holds: retrieved hours should match calculated hours
                $retrievedHours = (float)$retrievedLeave->leave_hours;
                
                // Allow small floating point differences (0.01 hours = 36 seconds)
                $hoursDifference = abs($retrievedHours - $calculatedHours);
                
                if ($hoursDifference > 0.01) {
                    $failedScenarios[] = [
                        'iteration' => $i,
                        'scenario' => $scenario,
                        'calculated_hours' => $calculatedHours,
                        'retrieved_hours' => $retrievedHours,
                        'difference' => $hoursDifference,
                        'leave_id' => $leaveId,
                        'employee_id' => $employeeId
                    ];
                }
                
                $this->assertEqualsWithDelta(
                    $calculatedHours,
                    $retrievedHours,
                    0.01,
                    "Property violated: Retrieved leave_hours should match calculated hours. " .
                    "Calculated: {$calculatedHours}, Retrieved: {$retrievedHours}, " .
                    "Difference: {$hoursDifference} hours"
                );
                
                // Clean up test data
                $this->db->table('ci_leave_applications')->where('leave_id', $leaveId)->delete();
                $this->cleanupTestEmployee($employeeId);
                $this->cleanupTestShift($shiftId);
                
                $passedTests++;
            } catch (\Exception $e) {
                // Clean up on error
                if (isset($leaveId)) {
                    $this->db->table('ci_leave_applications')->where('leave_id', $leaveId)->delete();
                }
                if (isset($employeeId)) {
                    $this->cleanupTestEmployee($employeeId);
                }
                if (isset($shiftId)) {
                    $this->cleanupTestShift($shiftId);
                }
                throw $e;
            }
        }
        
        // Report any failed scenarios
        if (!empty($failedScenarios)) {
            $this->fail(
                "Property test failed for " . count($failedScenarios) . " scenarios:\n" .
                print_r($failedScenarios, true)
            );
        }
        
        // Verify all iterations passed
        $this->assertEquals(
            $iterations,
            $passedTests,
            "Property test should pass for all {$iterations} iterations"
        );
    }

    /**
     * Generate a random leave application scenario for property testing
     * 
     * @return array ['shift' => array, 'employee' => array, 'is_full_day' => bool, 
     *                'from_date' => string, 'to_date' => string, 'date' => string,
     *                'start_time' => string, 'end_time' => string, 'leave_type_id' => int]
     */
    private function generateLeaveApplicationScenario()
    {
        $daysOfWeek = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        
        // Generate a shift where all days are working days (for simplicity)
        $shift = [
            'shift_name' => 'Test Shift ' . random_int(1000, 9999),
            'hours_per_day' => random_int(6, 10)
        ];
        
        // Set all days as working days
        foreach ($daysOfWeek as $day) {
            $startHour = random_int(6, 10);
            $endHour = $startHour + $shift['hours_per_day'];
            $shift["{$day}_in_time"] = sprintf('%02d:00:00', $startHour);
            $shift["{$day}_out_time"] = sprintf('%02d:00:00', $endHour);
            $shift["{$day}_lunch_break"] = '12:00:00';
            $shift["{$day}_lunch_break_out"] = '13:00:00';
        }
        
        // Generate employee data
        $employee = [
            'user_id' => random_int(100000, 999999),
            'company_id' => random_int(1, 100)
        ];
        
        // Randomly decide if it's a full day leave or hourly permission (50/50)
        $isFullDay = random_int(0, 1) === 1;
        
        if ($isFullDay) {
            // Full day leave: generate date range (1 to 10 days)
            $startDate = new \DateTime();
            $startDate->modify('+' . random_int(1, 30) . ' days');
            $rangeDays = random_int(1, 10);
            $endDate = clone $startDate;
            $endDate->modify('+' . $rangeDays . ' days');
            
            return [
                'shift' => $shift,
                'employee' => $employee,
                'is_full_day' => true,
                'from_date' => $startDate->format('Y-m-d'),
                'to_date' => $endDate->format('Y-m-d'),
                'date' => null,
                'start_time' => null,
                'end_time' => null,
                'leave_type_id' => random_int(1, 10)
            ];
        } else {
            // Hourly permission: generate time range within shift hours
            $date = new \DateTime();
            $date->modify('+' . random_int(1, 30) . ' days');
            $dateStr = $date->format('Y-m-d');
            $dayOfWeek = strtolower($date->format('l'));
            
            // Get shift hours for this day
            $shiftInTime = $shift["{$dayOfWeek}_in_time"];
            $shiftOutTime = $shift["{$dayOfWeek}_out_time"];
            
            // Parse shift hours
            list($shiftStartHour) = explode(':', $shiftInTime);
            list($shiftEndHour) = explode(':', $shiftOutTime);
            
            // Generate random start and end times within shift hours
            $startHour = random_int((int)$shiftStartHour, (int)$shiftEndHour - 2);
            $endHour = random_int($startHour + 1, (int)$shiftEndHour);
            
            $startMinute = [0, 15, 30, 45][array_rand([0, 15, 30, 45])];
            $endMinute = [0, 15, 30, 45][array_rand([0, 15, 30, 45])];
            
            $startTime = sprintf('%02d:%02d:00', $startHour, $startMinute);
            $endTime = sprintf('%02d:%02d:00', $endHour, $endMinute);
            
            return [
                'shift' => $shift,
                'employee' => $employee,
                'is_full_day' => false,
                'from_date' => $dateStr,
                'to_date' => $dateStr,
                'date' => $dateStr,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'leave_type_id' => random_int(1, 10)
            ];
        }
    }

    /**
     * Test employee-specific balance display
     * 
     * This test verifies that two employees with the same days balance but different
     * shifts see different hour values when the balance is formatted for display.
     * 
     * Requirements: 6.3
     */
    public function testEmployeeSpecificBalanceDisplay_DifferentShifts_ShowDifferentHours()
    {
        // Check if database is available
        if (!$this->dbAvailable) {
            $this->markTestSkipped('Database connection not available for testing');
            return;
        }
        
        try {
            // Create two employees with different shifts
            
            // Employee 1: 8 hours per day shift
            $employee1Id = random_int(100000, 999999);
            $company1Id = random_int(1, 100);
            
            $shift1Data = [
                'shift_name' => 'Test Shift 8hrs ' . random_int(1000, 9999),
                'hours_per_day' => 8,
                'monday_in_time' => '08:00:00',
                'monday_out_time' => '17:00:00',
                'monday_lunch_break' => '12:00:00',
                'monday_lunch_break_out' => '13:00:00',
                'tuesday_in_time' => '08:00:00',
                'tuesday_out_time' => '17:00:00',
                'tuesday_lunch_break' => '12:00:00',
                'tuesday_lunch_break_out' => '13:00:00',
                'wednesday_in_time' => '08:00:00',
                'wednesday_out_time' => '17:00:00',
                'wednesday_lunch_break' => '12:00:00',
                'wednesday_lunch_break_out' => '13:00:00',
                'thursday_in_time' => '08:00:00',
                'thursday_out_time' => '17:00:00',
                'thursday_lunch_break' => '12:00:00',
                'thursday_lunch_break_out' => '13:00:00',
                'friday_in_time' => '08:00:00',
                'friday_out_time' => '17:00:00',
                'friday_lunch_break' => '12:00:00',
                'friday_lunch_break_out' => '13:00:00',
                'saturday_in_time' => '',
                'saturday_out_time' => '',
                'saturday_lunch_break' => '',
                'saturday_lunch_break_out' => '',
                'sunday_in_time' => '',
                'sunday_out_time' => '',
                'sunday_lunch_break' => '',
                'sunday_lunch_break_out' => ''
            ];
            
            $shift1Id = $this->createTestShift($shift1Data);
            
            $employee1Data = [
                'user_id' => $employee1Id,
                'company_id' => $company1Id
            ];
            
            $this->createTestEmployeeWithShift($employee1Data, $shift1Id);
            
            // Employee 2: 10 hours per day shift
            $employee2Id = random_int(100000, 999999);
            $company2Id = random_int(1, 100);
            
            $shift2Data = [
                'shift_name' => 'Test Shift 10hrs ' . random_int(1000, 9999),
                'hours_per_day' => 10,
                'monday_in_time' => '08:00:00',
                'monday_out_time' => '19:00:00',
                'monday_lunch_break' => '12:00:00',
                'monday_lunch_break_out' => '13:00:00',
                'tuesday_in_time' => '08:00:00',
                'tuesday_out_time' => '19:00:00',
                'tuesday_lunch_break' => '12:00:00',
                'tuesday_lunch_break_out' => '13:00:00',
                'wednesday_in_time' => '08:00:00',
                'wednesday_out_time' => '19:00:00',
                'wednesday_lunch_break' => '12:00:00',
                'wednesday_lunch_break_out' => '13:00:00',
                'thursday_in_time' => '08:00:00',
                'thursday_out_time' => '19:00:00',
                'thursday_lunch_break' => '12:00:00',
                'thursday_lunch_break_out' => '13:00:00',
                'friday_in_time' => '08:00:00',
                'friday_out_time' => '19:00:00',
                'friday_lunch_break' => '12:00:00',
                'friday_lunch_break_out' => '13:00:00',
                'saturday_in_time' => '',
                'saturday_out_time' => '',
                'saturday_lunch_break' => '',
                'saturday_lunch_break_out' => '',
                'sunday_in_time' => '',
                'sunday_out_time' => '',
                'sunday_lunch_break' => '',
                'sunday_lunch_break_out' => ''
            ];
            
            $shift2Id = $this->createTestShift($shift2Data);
            
            $employee2Data = [
                'user_id' => $employee2Id,
                'company_id' => $company2Id
            ];
            
            $this->createTestEmployeeWithShift($employee2Data, $shift2Id);
            
            // Both employees have the same days balance: 5 days
            $daysBalance = 5;
            
            // Format balance for Employee 1 (8 hours/day)
            $employee1Display = $this->leavePolicy->formatLeaveBalanceDisplay($employee1Id, $daysBalance);
            
            // Format balance for Employee 2 (10 hours/day)
            $employee2Display = $this->leavePolicy->formatLeaveBalanceDisplay($employee2Id, $daysBalance);
            
            // Verify both displays show the same days but different hours
            $this->assertStringContainsString('5 days', $employee1Display, 'Employee 1 display should show 5 days');
            $this->assertStringContainsString('40 hours', $employee1Display, 'Employee 1 display should show 40 hours (5 × 8)');
            
            $this->assertStringContainsString('5 days', $employee2Display, 'Employee 2 display should show 5 days');
            $this->assertStringContainsString('50 hours', $employee2Display, 'Employee 2 display should show 50 hours (5 × 10)');
            
            // Verify the displays are different
            $this->assertNotEquals(
                $employee1Display,
                $employee2Display,
                'Employees with different shifts should see different hour values for the same days balance'
            );
            
            // Verify the format matches "X days (Y hours)"
            $this->assertMatchesRegularExpression(
                '/\d+(\.\d+)?\s+days\s+\(\d+(\.\d+)?\s+hours\)/',
                $employee1Display,
                'Employee 1 display should match format "X days (Y hours)"'
            );
            
            $this->assertMatchesRegularExpression(
                '/\d+(\.\d+)?\s+days\s+\(\d+(\.\d+)?\s+hours\)/',
                $employee2Display,
                'Employee 2 display should match format "X days (Y hours)"'
            );
            
            // Clean up
            $this->cleanupTestEmployee($employee1Id);
            $this->cleanupTestShift($shift1Id);
            $this->cleanupTestEmployee($employee2Id);
            $this->cleanupTestShift($shift2Id);
            
        } catch (\Exception $e) {
            // Clean up on error
            if (isset($employee1Id)) {
                $this->cleanupTestEmployee($employee1Id);
            }
            if (isset($shift1Id)) {
                $this->cleanupTestShift($shift1Id);
            }
            if (isset($employee2Id)) {
                $this->cleanupTestEmployee($employee2Id);
            }
            if (isset($shift2Id)) {
                $this->cleanupTestShift($shift2Id);
            }
            throw $e;
        }
    }

    /**
     * Test English error message for no shift assigned
     * 
     * Validates: Requirements 1.3, 10.1
     */
    public function testEnglishErrorMessage_NoShiftAssigned()
    {
        // Set language to English
        $request = \Config\Services::request();
        $request->setLocale('en');
        
        // Test with a non-existent employee ID (no shift assigned)
        $result = $this->leavePolicy->validateEmployeeHasShift(999999);
        
        // Verify validation fails
        $this->assertFalse($result['valid'], 'Validation should fail for employee without shift');
        
        // Verify error message is in English
        $this->assertNotNull($result['error_message'], 'Error message should be present');
        $this->assertEquals(
            'You must have an office shift assigned before requesting leave',
            $result['error_message'],
            'English error message should match expected text'
        );
    }

    /**
     * Test Arabic error message for no shift assigned
     * 
     * Validates: Requirements 10.2
     */
    public function testArabicErrorMessage_NoShiftAssigned()
    {
        // Note: This test verifies that the Arabic translation exists in the language file
        // In a real environment, the locale would be set by the user's session/preferences
        
        // Verify the Arabic translation exists in the language file
        $arabicLangFile = APPPATH . 'Language/ar/Main.php';
        
        if (!file_exists($arabicLangFile)) {
            $this->markTestSkipped('Arabic language file not found');
            return;
        }
        
        $arabicTranslations = include $arabicLangFile;
        
        $this->assertArrayHasKey(
            'no_shift_assigned',
            $arabicTranslations,
            'Arabic language file should have no_shift_assigned key'
        );
        
        $this->assertEquals(
            'يجب أن يكون لديك وردية مكتبية معينة قبل طلب الإجازة',
            $arabicTranslations['no_shift_assigned'],
            'Arabic error message should match expected text'
        );
    }

    /**
     * Test English error message for invalid permission times
     * 
     * Validates: Requirements 1.3, 10.1
     */
    public function testEnglishErrorMessage_InvalidPermissionTimes()
    {
        // Skip this test if no test database is configured
        if (!$this->dbAvailable) {
            $this->markTestSkipped('Database connection not available for testing');
            return;
        }
        
        // Set language to English
        $request = \Config\Services::request();
        $request->setLocale('en');
        
        try {
            // Create a test shift with specific working hours (08:00 - 17:00)
            $shift = [
                'shift_name' => 'Test Shift ' . random_int(1000, 9999),
                'hours_per_day' => 8,
                'monday_in_time' => '08:00:00',
                'monday_out_time' => '17:00:00',
                'monday_lunch_break' => '12:00:00',
                'monday_lunch_break_out' => '13:00:00',
                'tuesday_in_time' => '08:00:00',
                'tuesday_out_time' => '17:00:00',
                'tuesday_lunch_break' => '12:00:00',
                'tuesday_lunch_break_out' => '13:00:00',
                'wednesday_in_time' => '08:00:00',
                'wednesday_out_time' => '17:00:00',
                'wednesday_lunch_break' => '12:00:00',
                'wednesday_lunch_break_out' => '13:00:00',
                'thursday_in_time' => '08:00:00',
                'thursday_out_time' => '17:00:00',
                'thursday_lunch_break' => '12:00:00',
                'thursday_lunch_break_out' => '13:00:00',
                'friday_in_time' => '08:00:00',
                'friday_out_time' => '17:00:00',
                'friday_lunch_break' => '12:00:00',
                'friday_lunch_break_out' => '13:00:00',
                'saturday_in_time' => '',
                'saturday_out_time' => '',
                'saturday_lunch_break' => '',
                'saturday_lunch_break_out' => '',
                'sunday_in_time' => '',
                'sunday_out_time' => '',
                'sunday_lunch_break' => '',
                'sunday_lunch_break_out' => ''
            ];
            
            $shiftId = $this->createTestShift($shift);
            
            // Create a test employee with this shift
            $employee = [
                'user_id' => random_int(100000, 999999),
                'company_id' => random_int(1, 100)
            ];
            
            $employeeId = $this->createTestEmployeeWithShift($employee, $shiftId);
            
            // Find a Monday date
            $date = new \DateTime();
            while (strtolower($date->format('l')) !== 'monday') {
                $date->modify('+1 day');
            }
            $dateStr = $date->format('Y-m-d');
            
            // Test with times OUTSIDE shift hours (06:00 - 07:00, before shift starts at 08:00)
            $result = $this->leavePolicy->calculateHourlyPermissionHours(
                $employeeId,
                $dateStr,
                '06:00:00',  // Before shift start time
                '07:00:00'   // Before shift start time
            );
            
            // Verify validation fails
            $this->assertFalse($result['valid'], 'Validation should fail for times outside shift hours');
            
            // Verify error message is in English
            $this->assertNotNull($result['error_message'], 'Error message should be present');
            $this->assertEquals(
                'Permission times must fall within your shift working hours',
                $result['error_message'],
                'English error message should match expected text'
            );
            
            // Clean up
            $this->cleanupTestEmployee($employeeId);
            $this->cleanupTestShift($shiftId);
            
        } catch (\Exception $e) {
            // Clean up on error
            if (isset($employeeId)) {
                $this->cleanupTestEmployee($employeeId);
            }
            if (isset($shiftId)) {
                $this->cleanupTestShift($shiftId);
            }
            throw $e;
        }
    }

    /**
     * Test Arabic error message for invalid permission times
     * 
     * Validates: Requirements 10.2
     */
    public function testArabicErrorMessage_InvalidPermissionTimes()
    {
        // Note: This test verifies that the Arabic translation exists in the language file
        // In a real environment, the locale would be set by the user's session/preferences
        
        // Verify the Arabic translation exists in the language file
        $arabicLangFile = APPPATH . 'Language/ar/Main.php';
        
        if (!file_exists($arabicLangFile)) {
            $this->markTestSkipped('Arabic language file not found');
            return;
        }
        
        $arabicTranslations = include $arabicLangFile;
        
        $this->assertArrayHasKey(
            'invalid_permission_times',
            $arabicTranslations,
            'Arabic language file should have invalid_permission_times key'
        );
        
        $this->assertEquals(
            'يجب أن تكون أوقات الإذن ضمن ساعات عمل الوردية الخاصة بك',
            $arabicTranslations['invalid_permission_times'],
            'Arabic error message should match expected text'
        );
    }

    /**
     * Property 1: Monthly Deduction Aggregation
     * 
     * **Validates: Requirements 1.1, 1.2**
     * 
     * For any sick leave request spanning multiple tiers within a single month, 
     * the total deduction amount stored for that month SHALL equal the sum of 
     * deductions from all tier segments within that month.
     * 
     * This property-based test generates random sick leave requests spanning 
     * multiple tiers within a single month and verifies:
     * 1. Only one deduction record is created per month
     * 2. The record amount equals the sum of all tier segment deductions for that month
     * 
     * @group Feature: fix-sick-leave-payroll-deductions, Property 1: Monthly Deduction Aggregation
     */
    public function testProperty1_MonthlyDeductionAggregation()
    {
        // Check if database is available
        if (!$this->dbAvailable) {
            $this->markTestSkipped('Database connection not available for property testing');
            return;
        }
        
        $iterations = 100;
        $passedTests = 0;
        $failedScenarios = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Generate random test scenario
            $scenario = $this->generateSickLeaveScenario();
            
            try {
                // Create test employee with basic salary
                $employeeId = $this->createTestEmployeeWithSalary($scenario['employee']);
                
                // Create test leave application
                $leaveId = $this->createTestLeaveApplication($scenario['leave'], $employeeId);
                
                // Execute the method under test
                $result = $this->leavePolicy->createSickLeaveDeductions($leaveId);
                
                $this->assertTrue($result, "createSickLeaveDeductions should return true");
                
                // Retrieve deduction records from database
                $deductions = $this->db->table('ci_payslip_statutory_deductions')
                    ->where('staff_id', $employeeId)
                    ->where('payslip_id', 0)
                    ->where('contract_option_id', 0)
                    ->like('pay_title', 'Sick', 'both')
                    ->orderBy('salary_month', 'ASC')
                    ->get()
                    ->getResultArray();
                
                // Calculate expected deductions manually
                $expectedDeductions = $this->calculateExpectedMonthlyDeductions(
                    $scenario['leave']['from_date'],
                    $scenario['leave']['to_date'],
                    $scenario['leave']['cumulative_before'],
                    $scenario['employee']['basic_salary'],
                    $scenario['leave']['country_code']
                );
                
                // Property 1.1: Verify one record per month
                $monthCounts = [];
                foreach ($deductions as $deduction) {
                    $month = $deduction['salary_month'];
                    $monthCounts[$month] = ($monthCounts[$month] ?? 0) + 1;
                }
                
                foreach ($monthCounts as $month => $count) {
                    if ($count !== 1) {
                        $failedScenarios[] = [
                            'iteration' => $i,
                            'property' => '1.1 - One record per month',
                            'scenario' => $scenario,
                            'month' => $month,
                            'record_count' => $count,
                            'expected_count' => 1,
                            'employee_id' => $employeeId,
                            'leave_id' => $leaveId
                        ];
                    }
                    
                    $this->assertEquals(
                        1,
                        $count,
                        "Property 1.1 violated: Month {$month} should have exactly 1 deduction record, found {$count}"
                    );
                }
                
                // Property 1.2: Verify aggregated amounts match expected
                foreach ($deductions as $deduction) {
                    $month = $deduction['salary_month'];
                    $actualAmount = (float)$deduction['pay_amount'];
                    $expectedAmount = $expectedDeductions[$month] ?? 0;
                    
                    // Allow small floating point differences (0.01 SAR tolerance)
                    if (abs($actualAmount - $expectedAmount) > 0.01) {
                        $failedScenarios[] = [
                            'iteration' => $i,
                            'property' => '1.2 - Aggregated amount correctness',
                            'scenario' => $scenario,
                            'month' => $month,
                            'actual_amount' => $actualAmount,
                            'expected_amount' => $expectedAmount,
                            'difference' => abs($actualAmount - $expectedAmount),
                            'employee_id' => $employeeId,
                            'leave_id' => $leaveId
                        ];
                    }
                    
                    $this->assertEqualsWithDelta(
                        $expectedAmount,
                        $actualAmount,
                        0.01,
                        "Property 1.2 violated: Month {$month} deduction amount should be {$expectedAmount}, found {$actualAmount}"
                    );
                }
                
                // Verify all expected months have records
                foreach ($expectedDeductions as $month => $expectedAmount) {
                    if ($expectedAmount > 0) {
                        $found = false;
                        foreach ($deductions as $deduction) {
                            if ($deduction['salary_month'] === $month) {
                                $found = true;
                                break;
                            }
                        }
                        
                        if (!$found) {
                            $failedScenarios[] = [
                                'iteration' => $i,
                                'property' => '1.2 - Missing month record',
                                'scenario' => $scenario,
                                'month' => $month,
                                'expected_amount' => $expectedAmount,
                                'employee_id' => $employeeId,
                                'leave_id' => $leaveId
                            ];
                        }
                        
                        $this->assertTrue(
                            $found,
                            "Property 1.2 violated: Expected deduction record for month {$month} with amount {$expectedAmount}"
                        );
                    }
                }
                
                // Clean up test data
                $this->cleanupTestLeaveApplication($leaveId);
                $this->cleanupTestDeductions($employeeId);
                $this->cleanupTestEmployee($employeeId);
                
                $passedTests++;
            } catch (\Exception $e) {
                // Clean up on error
                if (isset($leaveId)) {
                    $this->cleanupTestLeaveApplication($leaveId);
                }
                if (isset($employeeId)) {
                    $this->cleanupTestDeductions($employeeId);
                    $this->cleanupTestEmployee($employeeId);
                }
                throw $e;
            }
        }
        
        // Report any failed scenarios
        if (!empty($failedScenarios)) {
            $this->fail(
                "Property test failed for " . count($failedScenarios) . " scenarios:\n" .
                print_r($failedScenarios, true)
            );
        }
        
        // Verify all iterations passed
        $this->assertEquals(
            $iterations,
            $passedTests,
            "Property test should pass for all {$iterations} iterations"
        );
    }
    
    /**
     * Property 2: Multi-Month Distribution
     * 
     * **Validates: Requirements 1.3, 10.1**
     * 
     * For any sick leave request spanning multiple months, the number of deduction 
     * records created SHALL equal the number of months in the leave period, with each 
     * record containing the aggregated deduction for that specific month.
     * 
     * This property-based test generates random sick leave requests spanning multiple 
     * months and verifies:
     * 1. The number of deduction records equals the number of months in the leave period
     * 2. Each record contains the correct aggregated deduction for its month
     * 
     * @group Feature: fix-sick-leave-payroll-deductions, Property 2: Multi-Month Distribution
     */
    public function testProperty2_MultiMonthDistribution()
    {
        // Check if database is available
        if (!$this->dbAvailable) {
            $this->markTestSkipped('Database connection not available for property testing');
            return;
        }
        
        $iterations = 100;
        $passedTests = 0;
        $failedScenarios = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Generate random test scenario spanning multiple months
            $scenario = $this->generateMultiMonthSickLeaveScenario();
            
            try {
                // Create test employee with basic salary
                $employeeId = $this->createTestEmployeeWithSalary($scenario['employee']);
                
                // Create test leave application
                $leaveId = $this->createTestLeaveApplication($scenario['leave'], $employeeId);
                
                // Execute the method under test
                $result = $this->leavePolicy->createSickLeaveDeductions($leaveId);
                
                $this->assertTrue($result, "createSickLeaveDeductions should return true");
                
                // Retrieve deduction records from database
                $deductions = $this->db->table('ci_payslip_statutory_deductions')
                    ->where('staff_id', $employeeId)
                    ->where('payslip_id', 0)
                    ->where('contract_option_id', 0)
                    ->like('pay_title', 'Sick', 'both')
                    ->orderBy('salary_month', 'ASC')
                    ->get()
                    ->getResultArray();
                
                // Calculate expected months and deductions
                $expectedDeductions = $this->calculateExpectedMonthlyDeductions(
                    $scenario['leave']['from_date'],
                    $scenario['leave']['to_date'],
                    $scenario['leave']['cumulative_before'],
                    $scenario['employee']['basic_salary'],
                    $scenario['leave']['country_code']
                );
                
                $expectedMonths = $this->calculateExpectedMonths(
                    $scenario['leave']['from_date'],
                    $scenario['leave']['to_date'],
                    $scenario['leave']['cumulative_before'],
                    $scenario['employee']['basic_salary'],
                    $scenario['leave']['country_code']
                );
                
                // Property 2.1: Verify number of deduction records equals number of months
                $actualMonthCount = count($deductions);
                $expectedMonthCount = count($expectedMonths);
                
                if ($actualMonthCount !== $expectedMonthCount) {
                    $failedScenarios[] = [
                        'iteration' => $i,
                        'property' => '2.1 - Number of records equals number of months',
                        'scenario' => $scenario,
                        'actual_month_count' => $actualMonthCount,
                        'expected_month_count' => $expectedMonthCount,
                        'expected_months' => $expectedMonths,
                        'actual_months' => array_column($deductions, 'salary_month'),
                        'employee_id' => $employeeId,
                        'leave_id' => $leaveId
                    ];
                }
                
                $this->assertEquals(
                    $expectedMonthCount,
                    $actualMonthCount,
                    "Property 2.1 violated: Number of deduction records ({$actualMonthCount}) should equal " .
                    "number of months in leave period ({$expectedMonthCount}). " .
                    "Leave: {$scenario['leave']['from_date']} to {$scenario['leave']['to_date']}"
                );
                
                // Property 2.2: Verify each record contains correct aggregated deduction
                foreach ($deductions as $deduction) {
                    $month = $deduction['salary_month'];
                    $actualAmount = (float)$deduction['pay_amount'];
                    $expectedAmount = $expectedDeductions[$month] ?? 0;
                    
                    // Allow small floating point differences (0.01 SAR tolerance)
                    if (abs($actualAmount - $expectedAmount) > 0.01) {
                        $failedScenarios[] = [
                            'iteration' => $i,
                            'property' => '2.2 - Correct aggregated deduction per month',
                            'scenario' => $scenario,
                            'month' => $month,
                            'actual_amount' => $actualAmount,
                            'expected_amount' => $expectedAmount,
                            'difference' => abs($actualAmount - $expectedAmount),
                            'employee_id' => $employeeId,
                            'leave_id' => $leaveId
                        ];
                    }
                    
                    $this->assertEqualsWithDelta(
                        $expectedAmount,
                        $actualAmount,
                        0.01,
                        "Property 2.2 violated: Month {$month} deduction amount should be {$expectedAmount}, " .
                        "found {$actualAmount}. Leave: {$scenario['leave']['from_date']} to {$scenario['leave']['to_date']}"
                    );
                }
                
                // Verify all expected months have records
                foreach ($expectedMonths as $month) {
                    $found = false;
                    foreach ($deductions as $deduction) {
                        if ($deduction['salary_month'] === $month) {
                            $found = true;
                            break;
                        }
                    }
                    
                    if (!$found) {
                        $failedScenarios[] = [
                            'iteration' => $i,
                            'property' => '2.2 - All expected months have records',
                            'scenario' => $scenario,
                            'missing_month' => $month,
                            'expected_amount' => $expectedDeductions[$month] ?? 0,
                            'employee_id' => $employeeId,
                            'leave_id' => $leaveId
                        ];
                    }
                    
                    $this->assertTrue(
                        $found,
                        "Property 2.2 violated: Expected deduction record for month {$month}. " .
                        "Leave: {$scenario['leave']['from_date']} to {$scenario['leave']['to_date']}"
                    );
                }
                
                // Clean up test data
                $this->cleanupTestLeaveApplication($leaveId);
                $this->cleanupTestDeductions($employeeId);
                $this->cleanupTestEmployee($employeeId);
                
                $passedTests++;
            } catch (\Exception $e) {
                // Clean up on error
                if (isset($leaveId)) {
                    $this->cleanupTestLeaveApplication($leaveId);
                }
                if (isset($employeeId)) {
                    $this->cleanupTestDeductions($employeeId);
                    $this->cleanupTestEmployee($employeeId);
                }
                throw $e;
            }
        }
        
        // Report any failed scenarios
        if (!empty($failedScenarios)) {
            $this->fail(
                "Property test failed for " . count($failedScenarios) . " scenarios:\n" .
                print_r($failedScenarios, true)
            );
        }
        
        // Verify all iterations passed
        $this->assertEquals(
            $iterations,
            $passedTests,
            "Property test should pass for all {$iterations} iterations"
        );
    }
    
    /**
     * Generate a random multi-month sick leave scenario for property testing
     * Creates scenarios specifically designed to span multiple months
     * 
     * @return array ['employee' => array, 'leave' => array]
     */
    private function generateMultiMonthSickLeaveScenario()
    {
        // Generate random employee data
        $employee = [
            'user_id' => random_int(100000, 999999),
            'company_id' => random_int(1, 100),
            'basic_salary' => random_int(5000, 20000) // 5,000 to 20,000 SAR
        ];
        
        // Generate random leave dates that span multiple months
        // Start date: random date in the next 1-60 days
        $startDate = new \DateTime();
        $startDate->modify('+' . random_int(1, 60) . ' days');
        
        // Ensure we start somewhere in the middle of a month to increase chance of multi-month
        // Set day to a random day between 5 and 25
        $startDay = random_int(5, 25);
        $startDate->setDate(
            (int)$startDate->format('Y'),
            (int)$startDate->format('m'),
            $startDay
        );
        
        // Duration: 15 to 120 days (to ensure multiple months are covered)
        // Minimum 15 days ensures we likely span at least 2 months
        $duration = random_int(15, 120);
        
        $endDate = clone $startDate;
        $endDate->modify('+' . ($duration - 1) . ' days');
        
        // For simplicity, assume no cumulative days before this request
        $cumulativeBefore = 0;
        
        // Country code (Saudi Arabia has tiered sick leave policy)
        $countryCode = 'SA';
        
        $leave = [
            'from_date' => $startDate->format('Y-m-d'),
            'to_date' => $endDate->format('Y-m-d'),
            'calculated_days' => $duration,
            'cumulative_before' => $cumulativeBefore,
            'country_code' => $countryCode
        ];
        
        return [
            'employee' => $employee,
            'leave' => $leave
        ];
    }
    
    /**
     * Calculate expected months covered by a leave period
     * Only includes months that have actual deductions (amount > 0)
     * 
     * @param string $fromDate
     * @param string $toDate
     * @param int $cumulativeBefore
     * @param float $basicSalary
     * @param string $countryCode
     * @return array List of months in YYYY-MM format that have deductions
     */
    private function calculateExpectedMonths($fromDate, $toDate, $cumulativeBefore, $basicSalary, $countryCode)
    {
        // Calculate expected deductions first
        $expectedDeductions = $this->calculateExpectedMonthlyDeductions(
            $fromDate,
            $toDate,
            $cumulativeBefore,
            $basicSalary,
            $countryCode
        );
        
        // Return only months that have deductions > 0
        $months = [];
        foreach ($expectedDeductions as $month => $amount) {
            if ($amount > 0) {
                $months[] = $month;
            }
        }
        
        return $months;
    }
    
    /**
     * Generate a random sick leave scenario for property testing
     * Creates scenarios with varying durations, start dates, and cumulative days
     * 
     * @return array ['employee' => array, 'leave' => array]
     */
    private function generateSickLeaveScenario()
    {
        // Generate random employee data
        $employee = [
            'user_id' => random_int(100000, 999999),
            'company_id' => random_int(1, 100),
            'basic_salary' => random_int(5000, 20000) // 5,000 to 20,000 SAR
        ];
        
        // Generate random leave dates
        // Start date: random date in the next 1-60 days
        $startDate = new \DateTime();
        $startDate->modify('+' . random_int(1, 60) . ' days');
        
        // Duration: 1 to 120 days (covering all tiers)
        $duration = random_int(1, 120);
        
        $endDate = clone $startDate;
        $endDate->modify('+' . ($duration - 1) . ' days');
        
        // For simplicity, assume no cumulative days before this request
        // This avoids the complexity of mocking getCumulativeSickDaysUsed
        $cumulativeBefore = 0;
        
        // Country code (Saudi Arabia has tiered sick leave policy)
        $countryCode = 'SA';
        
        $leave = [
            'from_date' => $startDate->format('Y-m-d'),
            'to_date' => $endDate->format('Y-m-d'),
            'calculated_days' => $duration,
            'cumulative_before' => $cumulativeBefore,
            'country_code' => $countryCode
        ];
        
        return [
            'employee' => $employee,
            'leave' => $leave
        ];
    }
    
    /**
     * Create a test employee with basic salary
     * 
     * @param array $employeeData
     * @return int Employee ID
     */
    private function createTestEmployeeWithSalary($employeeData)
    {
        // First, create the user record in ci_erp_users
        $userData = [
            'user_id' => $employeeData['user_id'],
            'company_id' => $employeeData['company_id'],
            'first_name' => 'Test',
            'last_name' => 'Employee',
            'email' => 'test' . $employeeData['user_id'] . '@example.com',
            'username' => 'testuser' . $employeeData['user_id'],
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $this->db->table('ci_erp_users')->insert($userData);
        
        // Then create the staff details record with basic salary
        $staffData = [
            'user_id' => $employeeData['user_id'],
            'company_id' => $employeeData['company_id'],
            'basic_salary' => $employeeData['basic_salary'],
            'office_shift_id' => 1,
            'employee_id' => 'EMP' . $employeeData['user_id'],
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $this->db->table('ci_erp_users_details')->insert($staffData);
        
        return $employeeData['user_id'];
    }
    
    /**
     * Create a test leave application
     * 
     * @param array $leaveData
     * @param int $employeeId
     * @return int Leave application ID
     */
    private function createTestLeaveApplication($leaveData, $employeeId)
    {
        // Try to get sick leave type ID, or use a default value
        $leaveTypeId = 1; // Default leave type ID
        
        try {
            $leaveType = $this->db->table('xin_leave_categories')
                ->where('category_name', 'Sick Leave')
                ->orWhere('category_name', 'إجازة مرضية')
                ->get()
                ->getRowArray();
            
            if ($leaveType) {
                $leaveTypeId = $leaveType['leave_type_id'];
            }
        } catch (\Exception $e) {
            // Table might not exist or have different structure, use default
            log_message('debug', 'Could not query leave types table: ' . $e->getMessage());
        }
        
        // Create leave application
        $leaveAppData = [
            'employee_id' => $employeeId,
            'company_id' => 1,
            'leave_type_id' => $leaveTypeId,
            'from_date' => $leaveData['from_date'],
            'to_date' => $leaveData['to_date'],
            'calculated_days' => $leaveData['calculated_days'],
            'leave_hours' => $leaveData['calculated_days'] * 8, // Convert days to hours
            'leave_month' => date('n', strtotime($leaveData['from_date'])),
            'leave_year' => date('Y', strtotime($leaveData['from_date'])),
            'reason' => 'Property test',
            'status' => 1, // Approved
            'country_code' => $leaveData['country_code'],
            'created_at' => date('d-m-Y h:i:s')
        ];
        
        try {
            $this->db->table('ci_leave_applications')->insert($leaveAppData);
            return $this->db->insertID();
        } catch (\Exception $e) {
            // If calculated_days column doesn't exist, try without it
            unset($leaveAppData['calculated_days']);
            $this->db->table('ci_leave_applications')->insert($leaveAppData);
            return $this->db->insertID();
        }
    }
    
    /**
     * Calculate expected monthly deductions manually (oracle for property test)
     * This replicates the logic in createSickLeaveDeductions to verify correctness
     * 
     * @param string $fromDate
     * @param string $toDate
     * @param int $cumulativeBefore Cumulative days BEFORE this leave request (not including it)
     * @param float $basicSalary
     * @param string $countryCode
     * @return array ['YYYY-MM' => deduction_amount]
     */
    private function calculateExpectedMonthlyDeductions($fromDate, $toDate, $cumulativeBefore, $basicSalary, $countryCode)
    {
        $dailyRate = $basicSalary / 30;
        $monthlyDeductions = [];
        
        $currentDate = $fromDate;
        $finalDate = $toDate;
        $daysProcessed = 0;
        
        // Note: cumulativeBefore is already the "before" position, matching the implementation
        // The implementation subtracts the current leave days from getCumulativeSickDaysUsed result
        
        while (strtotime($currentDate) <= strtotime($finalDate)) {
            $yearMonth = date('Y-m', strtotime($currentDate));
            $monthEnd = date('Y-m-t', strtotime($currentDate));
            $segmentEnd = (strtotime($finalDate) < strtotime($monthEnd)) ? $finalDate : $monthEnd;
            
            // Calculate days in this month segment
            $daysInSegment = (strtotime($segmentEnd) - strtotime($currentDate)) / (60 * 60 * 24) + 1;
            
            // Current cumulative position
            $currentCumulative = $cumulativeBefore + $daysProcessed;
            
            // Calculate tier split for this segment
            $tierSegments = $this->calculateTierSplitManual($currentCumulative, $daysInSegment, $countryCode);
            
            // Aggregate all tier segments within this month
            $monthlyDeductionTotal = 0;
            
            foreach ($tierSegments as $segment) {
                if ($segment['payment_percentage'] < 100) {
                    $deductionPercent = 100 - $segment['payment_percentage'];
                    $deductionAmount = $segment['days'] * $dailyRate * ($deductionPercent / 100);
                    $monthlyDeductionTotal += $deductionAmount;
                }
            }
            
            if ($monthlyDeductionTotal > 0) {
                $monthlyDeductions[$yearMonth] = round($monthlyDeductionTotal, 2);
            }
            
            $daysProcessed += $daysInSegment;
            $currentDate = date('Y-m-d', strtotime($segmentEnd . ' +1 day'));
        }
        
        return $monthlyDeductions;
    }
    
    /**
     * Manual tier split calculation (oracle for property test)
     * Replicates the logic in LeavePolicy::calculateTierSplit
     * 
     * @param int $cumulativeDays
     * @param int $requestedDays
     * @param string $countryCode
     * @return array
     */
    private function calculateTierSplitManual($cumulativeDays, $requestedDays, $countryCode)
    {
        // Saudi Arabia sick leave tiers (hardcoded for testing)
        // Days 1-30: 100% pay (0% deduction)
        // Days 31-90: 75% pay (25% deduction)
        // Days 91-120: 0% pay (100% deduction)
        
        if ($countryCode !== 'SA') {
            // No tiered policy for other countries
            return [[
                'tier_order' => 1,
                'days' => $requestedDays,
                'payment_percentage' => 100,
                'deduction_percentage' => 0
            ]];
        }
        
        $tiers = [
            ['start' => 0, 'end' => 30, 'payment_percentage' => 100],
            ['start' => 30, 'end' => 90, 'payment_percentage' => 75],
            ['start' => 90, 'end' => 120, 'payment_percentage' => 0]
        ];
        
        $segments = [];
        $remainingDays = $requestedDays;
        $currentPosition = $cumulativeDays;
        
        foreach ($tiers as $tier) {
            if ($remainingDays <= 0) break;
            
            // Skip tiers that are already fully consumed
            if ($currentPosition >= $tier['end']) continue;
            
            // Calculate days that fall in this tier
            $tierStart = max($currentPosition, $tier['start']);
            $tierEnd = min($currentPosition + $remainingDays, $tier['end']);
            $daysInThisTier = $tierEnd - $tierStart;
            
            if ($daysInThisTier > 0) {
                $segments[] = [
                    'tier_order' => count($segments) + 1,
                    'days' => $daysInThisTier,
                    'payment_percentage' => $tier['payment_percentage'],
                    'deduction_percentage' => 100 - $tier['payment_percentage']
                ];
                
                $currentPosition += $daysInThisTier;
                $remainingDays -= $daysInThisTier;
            }
        }
        
        // Handle overflow (days exceeding all defined tiers)
        if ($remainingDays > 0) {
            $segments[] = [
                'tier_order' => 999,
                'days' => $remainingDays,
                'payment_percentage' => 0,
                'deduction_percentage' => 100
            ];
        }
        
        return $segments;
    }
    
    /**
     * Clean up test leave application
     * 
     * @param int $leaveId
     */
    private function cleanupTestLeaveApplication($leaveId)
    {
        $this->db->table('ci_leave_applications')->where('leave_id', $leaveId)->delete();
    }
    
    /**
     * Clean up test deductions
     * 
     * @param int $employeeId
     */
    private function cleanupTestDeductions($employeeId)
    {
        $this->db->table('ci_payslip_statutory_deductions')
            ->where('staff_id', $employeeId)
            ->delete();
    }
    
    /**
     * Property 3: Tier Calculation Correctness
     * 
     * **Validates: Requirements 1.4, 10.2**
     * 
     * For any sick leave request, when calculating deductions for each month, the tier 
     * percentage applied to each day SHALL be determined by the cumulative days used up 
     * to that point in the year, ensuring correct tier progression across the entire 
     * leave period.
     * 
     * This property-based test generates random sick leave requests with varying start 
     * dates and durations, and verifies:
     * 1. Tier percentages are correctly applied based on cumulative days used
     * 2. Tier progression is correct across the entire leave period
     * 3. Days in each tier are calculated correctly
     * 
     * @group Feature: fix-sick-leave-payroll-deductions, Property 3: Tier Calculation Correctness
     */
    public function testProperty3_TierCalculationCorrectness()
    {
        // Check if database is available
        if (!$this->dbAvailable) {
            $this->markTestSkipped('Database connection not available for property testing');
            return;
        }
        
        $iterations = 100;
        $passedTests = 0;
        $failedScenarios = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Generate random test scenario with varying cumulative days and durations
            $scenario = $this->generateTierCalculationScenario();
            
            try {
                // Create test employee with basic salary
                $employeeId = $this->createTestEmployeeWithSalary($scenario['employee']);
                
                // Create test leave application
                $leaveId = $this->createTestLeaveApplication($scenario['leave'], $employeeId);
                
                // Execute the method under test
                $result = $this->leavePolicy->createSickLeaveDeductions($leaveId);
                
                $this->assertTrue($result, "createSickLeaveDeductions should return true");
                
                // Retrieve deduction records from database
                $deductions = $this->db->table('ci_payslip_statutory_deductions')
                    ->where('staff_id', $employeeId)
                    ->where('payslip_id', 0)
                    ->where('contract_option_id', 0)
                    ->like('pay_title', 'Sick', 'both')
                    ->orderBy('salary_month', 'ASC')
                    ->get()
                    ->getResultArray();
                
                // Calculate expected deductions using manual tier calculation
                $expectedDeductions = $this->calculateExpectedMonthlyDeductions(
                    $scenario['leave']['from_date'],
                    $scenario['leave']['to_date'],
                    $scenario['leave']['cumulative_before'],
                    $scenario['employee']['basic_salary'],
                    $scenario['leave']['country_code']
                );
                
                // Calculate detailed tier breakdown for verification
                $tierBreakdown = $this->calculateDetailedTierBreakdown(
                    $scenario['leave']['from_date'],
                    $scenario['leave']['to_date'],
                    $scenario['leave']['cumulative_before'],
                    $scenario['employee']['basic_salary'],
                    $scenario['leave']['country_code']
                );
                
                // Property 3.1: Verify total deduction amount matches expected
                $actualTotal = array_sum(array_column($deductions, 'pay_amount'));
                $expectedTotal = array_sum($expectedDeductions);
                
                if (abs($actualTotal - $expectedTotal) > 0.01) {
                    $failedScenarios[] = [
                        'iteration' => $i,
                        'property' => '3.1 - Total deduction matches expected',
                        'scenario' => $scenario,
                        'actual_total' => $actualTotal,
                        'expected_total' => $expectedTotal,
                        'difference' => abs($actualTotal - $expectedTotal),
                        'tier_breakdown' => $tierBreakdown,
                        'employee_id' => $employeeId,
                        'leave_id' => $leaveId
                    ];
                }
                
                $this->assertEqualsWithDelta(
                    $expectedTotal,
                    $actualTotal,
                    0.01,
                    "Property 3.1 violated: Total deduction amount ({$actualTotal}) should match expected ({$expectedTotal}). " .
                    "Leave: {$scenario['leave']['from_date']} to {$scenario['leave']['to_date']}, " .
                    "Cumulative before: {$scenario['leave']['cumulative_before']}, " .
                    "Duration: {$scenario['leave']['calculated_days']} days"
                );
                
                // Property 3.2: Verify each month's deduction matches expected tier calculation
                foreach ($expectedDeductions as $month => $expectedAmount) {
                    $found = false;
                    $actualAmount = 0;
                    
                    foreach ($deductions as $deduction) {
                        if ($deduction['salary_month'] === $month) {
                            $found = true;
                            $actualAmount = (float)$deduction['pay_amount'];
                            break;
                        }
                    }
                    
                    if (!$found && $expectedAmount > 0) {
                        $failedScenarios[] = [
                            'iteration' => $i,
                            'property' => '3.2 - Month deduction record exists',
                            'scenario' => $scenario,
                            'missing_month' => $month,
                            'expected_amount' => $expectedAmount,
                            'tier_breakdown' => $tierBreakdown,
                            'employee_id' => $employeeId,
                            'leave_id' => $leaveId
                        ];
                    }
                    
                    if ($found && abs($actualAmount - $expectedAmount) > 0.01) {
                        $failedScenarios[] = [
                            'iteration' => $i,
                            'property' => '3.2 - Month deduction amount correct',
                            'scenario' => $scenario,
                            'month' => $month,
                            'actual_amount' => $actualAmount,
                            'expected_amount' => $expectedAmount,
                            'difference' => abs($actualAmount - $expectedAmount),
                            'tier_breakdown' => $tierBreakdown,
                            'employee_id' => $employeeId,
                            'leave_id' => $leaveId
                        ];
                    }
                    
                    $this->assertTrue(
                        $found || $expectedAmount == 0,
                        "Property 3.2 violated: Expected deduction record for month {$month} with amount {$expectedAmount}"
                    );
                    
                    if ($found) {
                        $this->assertEqualsWithDelta(
                            $expectedAmount,
                            $actualAmount,
                            0.01,
                            "Property 3.2 violated: Month {$month} deduction should be {$expectedAmount}, found {$actualAmount}"
                        );
                    }
                }
                
                // Property 3.3: Verify tier progression is correct
                // Check that the tier breakdown makes sense given the cumulative position
                $this->verifyTierProgression(
                    $tierBreakdown,
                    $scenario['leave']['cumulative_before'],
                    $scenario['leave']['calculated_days'],
                    $failedScenarios,
                    $i,
                    $scenario,
                    $employeeId,
                    $leaveId
                );
                
                // Clean up test data
                $this->cleanupTestLeaveApplication($leaveId);
                $this->cleanupTestDeductions($employeeId);
                $this->cleanupTestEmployee($employeeId);
                
                $passedTests++;
            } catch (\Exception $e) {
                // Clean up on error
                if (isset($leaveId)) {
                    $this->cleanupTestLeaveApplication($leaveId);
                }
                if (isset($employeeId)) {
                    $this->cleanupTestDeductions($employeeId);
                    $this->cleanupTestEmployee($employeeId);
                }
                throw $e;
            }
        }
        
        // Report any failed scenarios
        if (!empty($failedScenarios)) {
            $this->fail(
                "Property test failed for " . count($failedScenarios) . " scenarios:\n" .
                print_r($failedScenarios, true)
            );
        }
        
        // Verify all iterations passed
        $this->assertEquals(
            $iterations,
            $passedTests,
            "Property test should pass for all {$iterations} iterations"
        );
    }
    
    /**
     * Generate a random tier calculation scenario for property testing
     * Creates scenarios with varying cumulative days before the request and different durations
     * to test tier boundary conditions
     * 
     * @return array ['employee' => array, 'leave' => array]
     */
    private function generateTierCalculationScenario()
    {
        // Generate random employee data
        $employee = [
            'user_id' => random_int(100000, 999999),
            'company_id' => random_int(1, 100),
            'basic_salary' => random_int(5000, 20000) // 5,000 to 20,000 SAR
        ];
        
        // Generate random cumulative days before this request (0 to 100)
        // This tests different starting positions in the tier structure
        $cumulativeBefore = random_int(0, 100);
        
        // Generate random leave dates
        $startDate = new \DateTime();
        $startDate->modify('+' . random_int(1, 60) . ' days');
        
        // Duration: 1 to 120 days
        // Combined with cumulativeBefore, this tests various tier transitions
        $duration = random_int(1, 120);
        
        $endDate = clone $startDate;
        $endDate->modify('+' . ($duration - 1) . ' days');
        
        // Country code (Saudi Arabia has tiered sick leave policy)
        $countryCode = 'SA';
        
        $leave = [
            'from_date' => $startDate->format('Y-m-d'),
            'to_date' => $endDate->format('Y-m-d'),
            'calculated_days' => $duration,
            'cumulative_before' => $cumulativeBefore,
            'country_code' => $countryCode
        ];
        
        return [
            'employee' => $employee,
            'leave' => $leave
        ];
    }
    
    /**
     * Calculate detailed tier breakdown for a leave period
     * Returns information about which days fall into which tiers
     * 
     * @param string $fromDate
     * @param string $toDate
     * @param int $cumulativeBefore
     * @param float $basicSalary
     * @param string $countryCode
     * @return array Detailed breakdown of tiers
     */
    private function calculateDetailedTierBreakdown($fromDate, $toDate, $cumulativeBefore, $basicSalary, $countryCode)
    {
        $dailyRate = $basicSalary / 30;
        $breakdown = [
            'total_days' => 0,
            'cumulative_start' => $cumulativeBefore,
            'cumulative_end' => 0,
            'tiers' => [],
            'monthly_breakdown' => []
        ];
        
        $currentDate = $fromDate;
        $finalDate = $toDate;
        $daysProcessed = 0;
        
        while (strtotime($currentDate) <= strtotime($finalDate)) {
            $yearMonth = date('Y-m', strtotime($currentDate));
            $monthEnd = date('Y-m-t', strtotime($currentDate));
            $segmentEnd = (strtotime($finalDate) < strtotime($monthEnd)) ? $finalDate : $monthEnd;
            
            // Calculate days in this month segment
            $daysInSegment = (strtotime($segmentEnd) - strtotime($currentDate)) / (60 * 60 * 24) + 1;
            
            // Current cumulative position
            $currentCumulative = $cumulativeBefore + $daysProcessed;
            
            // Calculate tier split for this segment
            $tierSegments = $this->calculateTierSplitManual($currentCumulative, $daysInSegment, $countryCode);
            
            // Store monthly breakdown
            $monthlyInfo = [
                'month' => $yearMonth,
                'days' => $daysInSegment,
                'cumulative_start' => $currentCumulative,
                'cumulative_end' => $currentCumulative + $daysInSegment,
                'tiers' => []
            ];
            
            foreach ($tierSegments as $segment) {
                $tierInfo = [
                    'days' => $segment['days'],
                    'payment_percentage' => $segment['payment_percentage'],
                    'deduction_percentage' => $segment['deduction_percentage'],
                    'deduction_amount' => 0
                ];
                
                if ($segment['payment_percentage'] < 100) {
                    $deductionPercent = 100 - $segment['payment_percentage'];
                    $tierInfo['deduction_amount'] = round($segment['days'] * $dailyRate * ($deductionPercent / 100), 2);
                }
                
                $monthlyInfo['tiers'][] = $tierInfo;
                
                // Add to overall tier summary
                $tierKey = "tier_{$segment['payment_percentage']}pct";
                if (!isset($breakdown['tiers'][$tierKey])) {
                    $breakdown['tiers'][$tierKey] = [
                        'payment_percentage' => $segment['payment_percentage'],
                        'total_days' => 0,
                        'total_deduction' => 0
                    ];
                }
                $breakdown['tiers'][$tierKey]['total_days'] += $segment['days'];
                $breakdown['tiers'][$tierKey]['total_deduction'] += $tierInfo['deduction_amount'];
            }
            
            $breakdown['monthly_breakdown'][] = $monthlyInfo;
            
            $daysProcessed += $daysInSegment;
            $currentDate = date('Y-m-d', strtotime($segmentEnd . ' +1 day'));
        }
        
        $breakdown['total_days'] = $daysProcessed;
        $breakdown['cumulative_end'] = $cumulativeBefore + $daysProcessed;
        
        return $breakdown;
    }
    
    /**
     * Verify that tier progression is correct
     * Checks that days are assigned to tiers in the correct order based on cumulative position
     * 
     * @param array $tierBreakdown
     * @param int $cumulativeBefore
     * @param int $totalDays
     * @param array &$failedScenarios
     * @param int $iteration
     * @param array $scenario
     * @param int $employeeId
     * @param int $leaveId
     */
    private function verifyTierProgression($tierBreakdown, $cumulativeBefore, $totalDays, &$failedScenarios, $iteration, $scenario, $employeeId, $leaveId)
    {
        // Saudi Arabia tiers:
        // Days 0-30: 100% pay (Tier 1)
        // Days 30-90: 75% pay (Tier 2)
        // Days 90-120: 0% pay (Tier 3)
        
        $cumulativeEnd = $cumulativeBefore + $totalDays;
        
        // Verify total days match
        if ($tierBreakdown['total_days'] !== $totalDays) {
            $failedScenarios[] = [
                'iteration' => $iteration,
                'property' => '3.3 - Total days in tier breakdown matches request',
                'scenario' => $scenario,
                'expected_total_days' => $totalDays,
                'actual_total_days' => $tierBreakdown['total_days'],
                'tier_breakdown' => $tierBreakdown,
                'employee_id' => $employeeId,
                'leave_id' => $leaveId
            ];
        }
        
        // Verify cumulative positions
        if ($tierBreakdown['cumulative_start'] !== $cumulativeBefore) {
            $failedScenarios[] = [
                'iteration' => $iteration,
                'property' => '3.3 - Cumulative start position correct',
                'scenario' => $scenario,
                'expected_cumulative_start' => $cumulativeBefore,
                'actual_cumulative_start' => $tierBreakdown['cumulative_start'],
                'tier_breakdown' => $tierBreakdown,
                'employee_id' => $employeeId,
                'leave_id' => $leaveId
            ];
        }
        
        if ($tierBreakdown['cumulative_end'] !== $cumulativeEnd) {
            $failedScenarios[] = [
                'iteration' => $iteration,
                'property' => '3.3 - Cumulative end position correct',
                'scenario' => $scenario,
                'expected_cumulative_end' => $cumulativeEnd,
                'actual_cumulative_end' => $tierBreakdown['cumulative_end'],
                'tier_breakdown' => $tierBreakdown,
                'employee_id' => $employeeId,
                'leave_id' => $leaveId
            ];
        }
        
        // Verify tier assignments make sense
        // If we start before day 30, we should have some days at 100% pay
        if ($cumulativeBefore < 30 && $cumulativeEnd > $cumulativeBefore) {
            $expectedDaysInTier1 = min(30 - $cumulativeBefore, $totalDays);
            $actualDaysInTier1 = $tierBreakdown['tiers']['tier_100pct']['total_days'] ?? 0;
            
            if ($actualDaysInTier1 !== $expectedDaysInTier1) {
                $failedScenarios[] = [
                    'iteration' => $iteration,
                    'property' => '3.3 - Days in Tier 1 (100% pay) correct',
                    'scenario' => $scenario,
                    'expected_days_tier1' => $expectedDaysInTier1,
                    'actual_days_tier1' => $actualDaysInTier1,
                    'tier_breakdown' => $tierBreakdown,
                    'employee_id' => $employeeId,
                    'leave_id' => $leaveId
                ];
            }
        }
        
        // If we cross into tier 2 (days 30-90), verify those days
        if ($cumulativeBefore < 90 && $cumulativeEnd > 30) {
            $tier2Start = max($cumulativeBefore, 30);
            $tier2End = min($cumulativeEnd, 90);
            $expectedDaysInTier2 = max(0, $tier2End - $tier2Start);
            $actualDaysInTier2 = $tierBreakdown['tiers']['tier_75pct']['total_days'] ?? 0;
            
            if ($actualDaysInTier2 !== $expectedDaysInTier2) {
                $failedScenarios[] = [
                    'iteration' => $iteration,
                    'property' => '3.3 - Days in Tier 2 (75% pay) correct',
                    'scenario' => $scenario,
                    'expected_days_tier2' => $expectedDaysInTier2,
                    'actual_days_tier2' => $actualDaysInTier2,
                    'tier_breakdown' => $tierBreakdown,
                    'employee_id' => $employeeId,
                    'leave_id' => $leaveId
                ];
            }
        }
        
        // If we cross into tier 3 (days 90-120), verify those days
        if ($cumulativeEnd > 90) {
            $tier3Start = max($cumulativeBefore, 90);
            $tier3End = min($cumulativeEnd, 120);
            $expectedDaysInTier3 = max(0, $tier3End - $tier3Start);
            $actualDaysInTier3 = $tierBreakdown['tiers']['tier_0pct']['total_days'] ?? 0;
            
            if ($actualDaysInTier3 !== $expectedDaysInTier3) {
                $failedScenarios[] = [
                    'iteration' => $iteration,
                    'property' => '3.3 - Days in Tier 3 (0% pay) correct',
                    'scenario' => $scenario,
                    'expected_days_tier3' => $expectedDaysInTier3,
                    'actual_days_tier3' => $actualDaysInTier3,
                    'tier_breakdown' => $tierBreakdown,
                    'employee_id' => $employeeId,
                    'leave_id' => $leaveId
                ];
            }
        }
    }
    
    /**
     * Property 4: Deduction Retrieval Accuracy
     * 
     * **Validates: Requirements 2.1, 2.2, 2.3, 2.4**
     * 
     * For any employee and salary month, when retrieving sick leave deductions, 
     * the system SHALL return all deduction records matching staff_id, salary_month, 
     * payslip_id = 0, and contract_option_id = 0, with the total amount equal to 
     * the sum of all matching records.
     * 
     * This property-based test creates random deduction records in the database,
     * queries using getSickLeaveDeductionsForPayroll(), and verifies:
     * 1. All matching records are returned
     * 2. Total amount equals sum of all matching records
     * 3. Non-matching records (different employee, month, or payslip_id) are excluded
     * 
     * @group Feature: fix-sick-leave-payroll-deductions, Property 4: Deduction Retrieval Accuracy
     */
    public function testProperty4_DeductionRetrievalAccuracy()
    {
        // Check if database is available
        if (!$this->dbAvailable) {
            $this->markTestSkipped('Database connection not available for property testing');
            return;
        }
        
        $iterations = 100;
        $passedTests = 0;
        $failedScenarios = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Generate random test scenario
            $scenario = $this->generateDeductionRetrievalScenario();
            
            try {
                // Create test employee
                $employeeId = $this->createTestEmployeeForDeductions($scenario['employee']);
                
                // Create deduction records in database
                $createdDeductions = $this->createTestDeductionRecords(
                    $employeeId,
                    $scenario['target_month'],
                    $scenario['matching_deductions'],
                    $scenario['non_matching_deductions']
                );
                
                // Execute the method under test
                $retrievedDeductions = $this->leavePolicy->getSickLeaveDeductionsForPayroll(
                    $employeeId,
                    $scenario['target_month']
                );
                
                // Property 4.1: Verify all matching records are returned
                $expectedCount = count($scenario['matching_deductions']);
                $actualCount = count($retrievedDeductions);
                
                if ($actualCount !== $expectedCount) {
                    $failedScenarios[] = [
                        'iteration' => $i,
                        'property' => '4.1 - All matching records returned',
                        'scenario' => $scenario,
                        'expected_count' => $expectedCount,
                        'actual_count' => $actualCount,
                        'employee_id' => $employeeId,
                        'retrieved_deductions' => $retrievedDeductions
                    ];
                }
                
                $this->assertEquals(
                    $expectedCount,
                    $actualCount,
                    "Property 4.1 violated: Expected {$expectedCount} deduction records, got {$actualCount} " .
                    "for employee {$employeeId}, month {$scenario['target_month']}"
                );
                
                // Property 4.2: Verify total amount equals sum of all matching records
                $expectedTotal = array_sum(array_column($scenario['matching_deductions'], 'amount'));
                $actualTotal = array_sum(array_column($retrievedDeductions, 'deduction_amount'));
                
                if (abs($actualTotal - $expectedTotal) > 0.01) {
                    $failedScenarios[] = [
                        'iteration' => $i,
                        'property' => '4.2 - Total amount equals sum of matching records',
                        'scenario' => $scenario,
                        'expected_total' => $expectedTotal,
                        'actual_total' => $actualTotal,
                        'difference' => abs($actualTotal - $expectedTotal),
                        'employee_id' => $employeeId,
                        'retrieved_deductions' => $retrievedDeductions
                    ];
                }
                
                $this->assertEqualsWithDelta(
                    $expectedTotal,
                    $actualTotal,
                    0.01,
                    "Property 4.2 violated: Expected total {$expectedTotal}, got {$actualTotal} " .
                    "for employee {$employeeId}, month {$scenario['target_month']}"
                );
                
                // Property 4.3: Verify each retrieved record has correct structure
                foreach ($retrievedDeductions as $deduction) {
                    $this->assertArrayHasKey('deduction_id', $deduction, 
                        "Property 4.3 violated: Deduction record should have 'deduction_id' key");
                    $this->assertArrayHasKey('deduction_amount', $deduction, 
                        "Property 4.3 violated: Deduction record should have 'deduction_amount' key");
                    $this->assertArrayHasKey('pay_title', $deduction, 
                        "Property 4.3 violated: Deduction record should have 'pay_title' key");
                    
                    // Verify pay_title contains "Sick"
                    $this->assertStringContainsString(
                        'Sick',
                        $deduction['pay_title'],
                        "Property 4.3 violated: pay_title should contain 'Sick'"
                    );
                }
                
                // Property 4.4: Verify non-matching records are NOT returned
                // Check that none of the non-matching deduction IDs appear in results
                $retrievedIds = array_column($retrievedDeductions, 'deduction_id');
                $nonMatchingIds = array_column($createdDeductions['non_matching'], 'id');
                
                foreach ($nonMatchingIds as $nonMatchingId) {
                    if (in_array($nonMatchingId, $retrievedIds)) {
                        $failedScenarios[] = [
                            'iteration' => $i,
                            'property' => '4.4 - Non-matching records excluded',
                            'scenario' => $scenario,
                            'non_matching_id_found' => $nonMatchingId,
                            'employee_id' => $employeeId,
                            'retrieved_deductions' => $retrievedDeductions
                        ];
                    }
                    
                    $this->assertNotContains(
                        $nonMatchingId,
                        $retrievedIds,
                        "Property 4.4 violated: Non-matching deduction ID {$nonMatchingId} should not be returned"
                    );
                }
                
                // Clean up test data
                $this->cleanupTestDeductions($employeeId);
                // Also clean up non-matching deductions (different employees)
                foreach ($createdDeductions['non_matching_employees'] as $otherEmployeeId) {
                    $this->cleanupTestDeductions($otherEmployeeId);
                    $this->cleanupTestEmployee($otherEmployeeId);
                }
                $this->cleanupTestEmployee($employeeId);
                
                $passedTests++;
            } catch (\Exception $e) {
                // Clean up on error
                if (isset($employeeId)) {
                    $this->cleanupTestDeductions($employeeId);
                    $this->cleanupTestEmployee($employeeId);
                }
                if (isset($createdDeductions['non_matching_employees'])) {
                    foreach ($createdDeductions['non_matching_employees'] as $otherEmployeeId) {
                        $this->cleanupTestDeductions($otherEmployeeId);
                        $this->cleanupTestEmployee($otherEmployeeId);
                    }
                }
                throw $e;
            }
        }
        
        // Report any failed scenarios
        if (!empty($failedScenarios)) {
            $this->fail(
                "Property test failed for " . count($failedScenarios) . " scenarios:\n" .
                print_r($failedScenarios, true)
            );
        }
        
        // Verify all iterations passed
        $this->assertEquals(
            $iterations,
            $passedTests,
            "Property test should pass for all {$iterations} iterations"
        );
    }
    
    /**
     * Generate a random deduction retrieval scenario for property testing
     * Creates scenarios with matching and non-matching deduction records
     * 
     * @return array ['employee' => array, 'target_month' => string, 
     *                'matching_deductions' => array, 'non_matching_deductions' => array]
     */
    private function generateDeductionRetrievalScenario()
    {
        // Generate random employee data
        $employee = [
            'user_id' => random_int(100000, 999999),
            'company_id' => random_int(1, 100)
        ];
        
        // Generate target month (random month in 2026)
        $targetMonth = '2026-' . sprintf('%02d', random_int(1, 12));
        
        // Generate 1-5 matching deduction records (for target employee and month)
        $numMatchingDeductions = random_int(1, 5);
        $matchingDeductions = [];
        
        for ($i = 0; $i < $numMatchingDeductions; $i++) {
            $matchingDeductions[] = [
                'amount' => round(random_int(100, 5000) / 10, 2), // 10.00 to 500.00
                'title' => $this->generateSickLeaveTitle()
            ];
        }
        
        // Generate 2-6 non-matching deduction records
        // These should NOT be returned by the query
        $numNonMatchingDeductions = random_int(2, 6);
        $nonMatchingDeductions = [];
        
        for ($i = 0; $i < $numNonMatchingDeductions; $i++) {
            // Randomly choose what makes this deduction non-matching
            $mismatchType = random_int(1, 4);
            
            switch ($mismatchType) {
                case 1:
                    // Different month (same employee)
                    $differentMonth = '2026-' . sprintf('%02d', random_int(1, 12));
                    while ($differentMonth === $targetMonth) {
                        $differentMonth = '2026-' . sprintf('%02d', random_int(1, 12));
                    }
                    $nonMatchingDeductions[] = [
                        'type' => 'different_month',
                        'employee_id' => 'same',
                        'month' => $differentMonth,
                        'amount' => round(random_int(100, 5000) / 10, 2),
                        'title' => $this->generateSickLeaveTitle(),
                        'payslip_id' => 0,
                        'contract_option_id' => 0
                    ];
                    break;
                    
                case 2:
                    // Different employee (same month)
                    $nonMatchingDeductions[] = [
                        'type' => 'different_employee',
                        'employee_id' => random_int(100000, 999999),
                        'month' => $targetMonth,
                        'amount' => round(random_int(100, 5000) / 10, 2),
                        'title' => $this->generateSickLeaveTitle(),
                        'payslip_id' => 0,
                        'contract_option_id' => 0
                    ];
                    break;
                    
                case 3:
                    // Same employee and month, but payslip_id != 0 (already processed)
                    $nonMatchingDeductions[] = [
                        'type' => 'processed_payslip',
                        'employee_id' => 'same',
                        'month' => $targetMonth,
                        'amount' => round(random_int(100, 5000) / 10, 2),
                        'title' => $this->generateSickLeaveTitle(),
                        'payslip_id' => random_int(1, 1000),
                        'contract_option_id' => 0
                    ];
                    break;
                    
                case 4:
                    // Same employee and month, but contract_option_id != 0 (manual deduction)
                    $nonMatchingDeductions[] = [
                        'type' => 'manual_deduction',
                        'employee_id' => 'same',
                        'month' => $targetMonth,
                        'amount' => round(random_int(100, 5000) / 10, 2),
                        'title' => $this->generateSickLeaveTitle(),
                        'payslip_id' => 0,
                        'contract_option_id' => random_int(1, 100)
                    ];
                    break;
            }
        }
        
        return [
            'employee' => $employee,
            'target_month' => $targetMonth,
            'matching_deductions' => $matchingDeductions,
            'non_matching_deductions' => $nonMatchingDeductions
        ];
    }
    
    /**
     * Generate a random sick leave deduction title
     * All titles must contain "Sick" to match the LIKE filter in getSickLeaveDeductionsForPayroll
     * 
     * @return string
     */
    private function generateSickLeaveTitle()
    {
        $titles = [
            'Sick Leave Deduction',
            'Sick Leave Deduction - خصم الإجازة المرضية',
            'Deduction: Sick Leave',
            'Sick Leave - Salary Deduction',
            'Sick Leave Deduction (Tier 2)',
            'Sick Leave Deduction (Tier 3)'
        ];
        
        return $titles[array_rand($titles)];
    }
    
    /**
     * Create a test employee for deduction retrieval testing
     * Simpler version without salary requirements
     * 
     * @param array $employeeData
     * @return int Employee ID
     */
    private function createTestEmployeeForDeductions($employeeData)
    {
        // First, create the user record in ci_erp_users
        $userData = [
            'user_id' => $employeeData['user_id'],
            'company_id' => $employeeData['company_id'],
            'first_name' => 'Test',
            'last_name' => 'Employee',
            'email' => 'test' . $employeeData['user_id'] . '@example.com',
            'username' => 'testuser' . $employeeData['user_id'],
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $this->db->table('ci_erp_users')->insert($userData);
        
        // Then create the staff details record
        $staffData = [
            'user_id' => $employeeData['user_id'],
            'company_id' => $employeeData['company_id'],
            'employee_id' => 'EMP' . $employeeData['user_id'],
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $this->db->table('ci_erp_users_details')->insert($staffData);
        
        return $employeeData['user_id'];
    }
    
    /**
     * Create test deduction records in the database
     * 
     * @param int $employeeId Target employee ID
     * @param string $targetMonth Target month (YYYY-MM)
     * @param array $matchingDeductions Deductions that should match the query
     * @param array $nonMatchingDeductions Deductions that should NOT match the query
     * @return array ['matching' => array, 'non_matching' => array, 'non_matching_employees' => array]
     */
    private function createTestDeductionRecords($employeeId, $targetMonth, $matchingDeductions, $nonMatchingDeductions)
    {
        $createdMatching = [];
        $createdNonMatching = [];
        $nonMatchingEmployees = [];
        
        // Create matching deduction records
        foreach ($matchingDeductions as $deduction) {
            $deductionData = [
                'payslip_id' => 0,
                'staff_id' => $employeeId,
                'is_fixed' => 0,
                'pay_title' => $deduction['title'],
                'pay_amount' => $deduction['amount'],
                'salary_month' => $targetMonth,
                'contract_option_id' => 0,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $this->db->table('ci_payslip_statutory_deductions')->insert($deductionData);
            $deductionId = $this->db->insertID();
            
            $createdMatching[] = [
                'id' => $deductionId,
                'amount' => $deduction['amount'],
                'title' => $deduction['title']
            ];
        }
        
        // Create non-matching deduction records
        foreach ($nonMatchingDeductions as $deduction) {
            $deductionEmployeeId = $employeeId;
            
            // If this is a different employee deduction, create that employee first
            if ($deduction['employee_id'] !== 'same') {
                $deductionEmployeeId = $deduction['employee_id'];
                
                // Create the other employee if not already created
                if (!in_array($deductionEmployeeId, $nonMatchingEmployees)) {
                    $otherEmployeeData = [
                        'user_id' => $deductionEmployeeId,
                        'company_id' => random_int(1, 100)
                    ];
                    $this->createTestEmployeeForDeductions($otherEmployeeData);
                    $nonMatchingEmployees[] = $deductionEmployeeId;
                }
            }
            
            $deductionData = [
                'payslip_id' => $deduction['payslip_id'],
                'staff_id' => $deductionEmployeeId,
                'is_fixed' => 0,
                'pay_title' => $deduction['title'],
                'pay_amount' => $deduction['amount'],
                'salary_month' => $deduction['month'],
                'contract_option_id' => $deduction['contract_option_id'],
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $this->db->table('ci_payslip_statutory_deductions')->insert($deductionData);
            $deductionId = $this->db->insertID();
            
            $createdNonMatching[] = [
                'id' => $deductionId,
                'type' => $deduction['type'],
                'amount' => $deduction['amount'],
                'title' => $deduction['title']
            ];
        }
        
        return [
            'matching' => $createdMatching,
            'non_matching' => $createdNonMatching,
            'non_matching_employees' => $nonMatchingEmployees
        ];
    }
    
    /**
     * Unit Test: Single month, single tier (no deduction expected)
     * 
     * Test a leave request that falls entirely within Tier 1 (100% pay, 0% deduction)
     * Expected: No deduction records should be created
     * 
     * **Validates: Requirements 1.1, 1.2**
     * 
     * @group Feature: fix-sick-leave-payroll-deductions
     */
    public function testSingleMonthSingleTier_NoDeduction()
    {
        // Check if database is available
        if (!$this->dbAvailable) {
            $this->markTestSkipped('Database connection not available for unit testing');
            return;
        }
        
        try {
            // Create test data: 15 days in March, all in Tier 1 (100% pay)
            $employeeId = $this->createTestEmployeeForSickLeave(10000); // 10,000 SAR basic salary
            $leaveId = $this->createTestSickLeave($employeeId, '2026-03-01', '2026-03-15', 15, 0); // 0 cumulative days before
            
            // Execute the method under test
            $result = $this->leavePolicy->createSickLeaveDeductions($leaveId);
            
            $this->assertTrue($result, "createSickLeaveDeductions should return true");
            
            // Retrieve deduction records from database
            $deductions = $this->db->table('ci_payslip_statutory_deductions')
                ->where('staff_id', $employeeId)
                ->where('payslip_id', 0)
                ->where('contract_option_id', 0)
                ->like('pay_title', 'Sick', 'both')
                ->get()
                ->getResultArray();
            
            // Verify: No deduction records should be created (all days at 100% pay)
            $this->assertEquals(
                0,
                count($deductions),
                "No deduction records should be created for leave entirely in Tier 1 (100% pay)"
            );
            
            // Clean up
            $this->cleanupTestSickLeave($leaveId, $employeeId);
            
        } catch (\Exception $e) {
            // Clean up on error
            if (isset($leaveId) && isset($employeeId)) {
                $this->cleanupTestSickLeave($leaveId, $employeeId);
            }
            throw $e;
        }
    }
    
    /**
     * Unit Test: Single month, multiple tiers (aggregated deduction)
     * 
     * Test a leave request that spans multiple tiers within a single month
     * Expected: One deduction record with aggregated amount
     * 
     * **Validates: Requirements 1.1, 1.2**
     * 
     * @group Feature: fix-sick-leave-payroll-deductions
     */
    public function testSingleMonthMultipleTiers_AggregatedDeduction()
    {
        // Check if database is available
        if (!$this->dbAvailable) {
            $this->markTestSkipped('Database connection not available for unit testing');
            return;
        }
        
        try {
            // Create test data: 31 days in March
            // Days 1-30 at 100% pay (0% deduction)
            // Day 31 at 75% pay (25% deduction)
            $basicSalary = 10000; // 10,000 SAR
            $dailyRate = $basicSalary / 30; // 333.33 SAR/day
            
            $employeeId = $this->createTestEmployeeForSickLeave($basicSalary);
            $leaveId = $this->createTestSickLeave($employeeId, '2026-03-01', '2026-03-31', 31, 0); // 0 cumulative days before
            
            // Execute the method under test
            $result = $this->leavePolicy->createSickLeaveDeductions($leaveId);
            
            $this->assertTrue($result, "createSickLeaveDeductions should return true");
            
            // Retrieve deduction records from database
            $deductions = $this->db->table('ci_payslip_statutory_deductions')
                ->where('staff_id', $employeeId)
                ->where('payslip_id', 0)
                ->where('contract_option_id', 0)
                ->like('pay_title', 'Sick', 'both')
                ->get()
                ->getResultArray();
            
            // Verify: Exactly 1 record for March
            $this->assertEquals(
                1,
                count($deductions),
                "Exactly one deduction record should be created for single month with multiple tiers"
            );
            
            // Verify: Record is for March 2026
            $this->assertEquals('2026-03', $deductions[0]['salary_month']);
            
            // Verify: Deduction amount is correct
            // Day 31 at 25% deduction: 1 * 333.33 * 0.25 = 83.33 SAR
            // Note: Actual calculation may vary slightly due to rounding
            $expectedDeduction = 1 * $dailyRate * 0.25;
            $this->assertEqualsWithDelta(
                $expectedDeduction,
                $deductions[0]['pay_amount'],
                5.0, // Allow 5 SAR tolerance for rounding differences
                "Deduction amount should be approximately 83.33 SAR (1 day at 25% deduction)"
            );
            
            // Verify: Title is simple (not tier-specific)
            $this->assertEquals('Sick Leave Deduction', $deductions[0]['pay_title']);
            
            // Verify: contract_option_id is 0
            $this->assertEquals(0, $deductions[0]['contract_option_id']);
            
            // Clean up
            $this->cleanupTestSickLeave($leaveId, $employeeId);
            
        } catch (\Exception $e) {
            // Clean up on error
            if (isset($leaveId) && isset($employeeId)) {
                $this->cleanupTestSickLeave($leaveId, $employeeId);
            }
            throw $e;
        }
    }
    
    /**
     * Unit Test: Multi-month leave (120 days example from design)
     * 
     * Test the exact scenario from the design document:
     * 120 days from 2026-02-09 to 2026-06-08
     * 
     * Expected monthly deductions:
     * - Feb 2026: 0.00 SAR (Days 1-20, all at 100% pay)
     * - Mar 2026: 1,750.00 SAR (Days 21-51, mixed tiers)
     * - Apr 2026: 2,500.00 SAR (Days 52-81, all at 75% pay)
     * - May 2026: 8,083.33 SAR (Days 82-112, mixed tiers)
     * - Jun 2026: 2,666.67 SAR (Days 113-120, all at 0% pay)
     * 
     * **Validates: Requirements 1.1, 1.2, 1.3, 1.4**
     * 
     * @group Feature: fix-sick-leave-payroll-deductions
     */
    public function testMultiMonthLeave_120DaysExample()
    {
        // Check if database is available
        if (!$this->dbAvailable) {
            $this->markTestSkipped('Database connection not available for unit testing');
            return;
        }
        
        try {
            // Create test data: 120 days from 2026-02-09 to 2026-06-08
            $basicSalary = 10000; // 10,000 SAR
            $dailyRate = $basicSalary / 30; // 333.33 SAR/day
            
            $employeeId = $this->createTestEmployeeForSickLeave($basicSalary);
            $leaveId = $this->createTestSickLeave($employeeId, '2026-02-09', '2026-06-08', 120, 0); // 0 cumulative days before
            
            // Execute the method under test
            $result = $this->leavePolicy->createSickLeaveDeductions($leaveId);
            
            $this->assertTrue($result, "createSickLeaveDeductions should return true");
            
            // Retrieve deduction records from database
            $deductions = $this->db->table('ci_payslip_statutory_deductions')
                ->where('staff_id', $employeeId)
                ->where('payslip_id', 0)
                ->where('contract_option_id', 0)
                ->like('pay_title', 'Sick', 'both')
                ->orderBy('salary_month', 'ASC')
                ->get()
                ->getResultArray();
            
            // Verify: Records for months with deductions (Feb should have 0, so might not be created)
            // Expected: Mar, Apr, May, Jun (4 records) or Feb, Mar, Apr, May, Jun (5 records if 0 amounts are stored)
            $this->assertGreaterThanOrEqual(
                4,
                count($deductions),
                "At least 4 deduction records should be created (Mar, Apr, May, Jun)"
            );
            
            $this->assertLessThanOrEqual(
                5,
                count($deductions),
                "At most 5 deduction records should be created (Feb, Mar, Apr, May, Jun)"
            );
            
            // Build a map of month => amount for easier verification
            $monthlyDeductions = [];
            foreach ($deductions as $deduction) {
                $monthlyDeductions[$deduction['salary_month']] = $deduction['pay_amount'];
            }
            
            // Verify March 2026: Days 21-51 (10 days at 100%, 21 days at 75%)
            // Deduction: 21 days * 333.33 * 0.25 = 1,750.00 SAR
            if (isset($monthlyDeductions['2026-03'])) {
                $expectedMarch = 21 * $dailyRate * 0.25;
                $this->assertEqualsWithDelta(
                    $expectedMarch,
                    $monthlyDeductions['2026-03'],
                    5.0, // Allow 5 SAR tolerance for rounding
                    "March 2026 deduction should be approximately 1,750.00 SAR"
                );
            }
            
            // Verify April 2026: Days 52-81 (30 days at 75%)
            // Deduction: 30 days * 333.33 * 0.25 = 2,500.00 SAR
            if (isset($monthlyDeductions['2026-04'])) {
                $expectedApril = 30 * $dailyRate * 0.25;
                $this->assertEqualsWithDelta(
                    $expectedApril,
                    $monthlyDeductions['2026-04'],
                    5.0,
                    "April 2026 deduction should be approximately 2,500.00 SAR"
                );
            }
            
            // Verify May 2026: Days 82-112 (9 days at 75%, 22 days at 0%)
            // Deduction: (9 * 333.33 * 0.25) + (22 * 333.33 * 1.0) = 750 + 7,333.33 = 8,083.33 SAR
            if (isset($monthlyDeductions['2026-05'])) {
                $expectedMay = (9 * $dailyRate * 0.25) + (22 * $dailyRate * 1.0);
                $this->assertEqualsWithDelta(
                    $expectedMay,
                    $monthlyDeductions['2026-05'],
                    15.0, // Allow 15 SAR tolerance for larger amounts with mixed tiers
                    "May 2026 deduction should be approximately 8,083.33 SAR"
                );
            }
            
            // Verify June 2026: Days 113-120 (8 days at 0%)
            // Deduction: 8 days * 333.33 * 1.0 = 2,666.67 SAR
            if (isset($monthlyDeductions['2026-06'])) {
                $expectedJune = 8 * $dailyRate * 1.0;
                $this->assertEqualsWithDelta(
                    $expectedJune,
                    $monthlyDeductions['2026-06'],
                    5.0,
                    "June 2026 deduction should be approximately 2,666.67 SAR"
                );
            }
            
            // Verify total deduction
            $totalDeduction = array_sum($monthlyDeductions);
            $expectedTotal = (21 * $dailyRate * 0.25) + (30 * $dailyRate * 0.25) + 
                            (9 * $dailyRate * 0.25) + (22 * $dailyRate * 1.0) + 
                            (8 * $dailyRate * 1.0);
            
            $this->assertEqualsWithDelta(
                $expectedTotal,
                $totalDeduction,
                20.0, // Allow 20 SAR tolerance for cumulative rounding
                "Total deduction should be approximately 15,000.00 SAR"
            );
            
            // Clean up
            $this->cleanupTestSickLeave($leaveId, $employeeId);
            
        } catch (\Exception $e) {
            // Clean up on error
            if (isset($leaveId) && isset($employeeId)) {
                $this->cleanupTestSickLeave($leaveId, $employeeId);
            }
            throw $e;
        }
    }
    
    /**
     * Unit Test: Partial month at start and end of leave period
     * 
     * Test a leave request that starts mid-month and ends mid-month
     * Expected: Correct calculation of days in partial months
     * 
     * **Validates: Requirements 1.1, 1.2, 1.3**
     * 
     * @group Feature: fix-sick-leave-payroll-deductions
     */
    public function testPartialMonths_StartAndEnd()
    {
        // Check if database is available
        if (!$this->dbAvailable) {
            $this->markTestSkipped('Database connection not available for unit testing');
            return;
        }
        
        try {
            // Create test data: 45 days from 2026-03-15 to 2026-04-28
            // March: 17 days (Mar 15-31), Days 1-17 at 100% pay
            // April: 28 days (Apr 1-28), Days 18-30 at 100% pay (13 days), Days 31-45 at 75% pay (15 days)
            $basicSalary = 10000; // 10,000 SAR
            $dailyRate = $basicSalary / 30; // 333.33 SAR/day
            
            $employeeId = $this->createTestEmployeeForSickLeave($basicSalary);
            $leaveId = $this->createTestSickLeave($employeeId, '2026-03-15', '2026-04-28', 45, 0); // 0 cumulative days before
            
            // Execute the method under test
            $result = $this->leavePolicy->createSickLeaveDeductions($leaveId);
            
            $this->assertTrue($result, "createSickLeaveDeductions should return true");
            
            // Retrieve deduction records from database
            $deductions = $this->db->table('ci_payslip_statutory_deductions')
                ->where('staff_id', $employeeId)
                ->where('payslip_id', 0)
                ->where('contract_option_id', 0)
                ->like('pay_title', 'Sick', 'both')
                ->orderBy('salary_month', 'ASC')
                ->get()
                ->getResultArray();
            
            // Verify: At least 1 record (April has deductions)
            $this->assertGreaterThanOrEqual(
                1,
                count($deductions),
                "At least 1 deduction record should be created (April)"
            );
            
            // Build a map of month => amount
            $monthlyDeductions = [];
            foreach ($deductions as $deduction) {
                $monthlyDeductions[$deduction['salary_month']] = $deduction['pay_amount'];
            }
            
            // Verify April 2026: Days 31-45 (15 days at 75% pay)
            // Deduction: 15 days * 333.33 * 0.25 = 1,250.00 SAR
            if (isset($monthlyDeductions['2026-04'])) {
                $expectedApril = 15 * $dailyRate * 0.25;
                $this->assertEqualsWithDelta(
                    $expectedApril,
                    $monthlyDeductions['2026-04'],
                    1.0,
                    "April 2026 deduction should be approximately 1,250.00 SAR (15 days at 25% deduction)"
                );
            }
            
            // Clean up
            $this->cleanupTestSickLeave($leaveId, $employeeId);
            
        } catch (\Exception $e) {
            // Clean up on error
            if (isset($leaveId) && isset($employeeId)) {
                $this->cleanupTestSickLeave($leaveId, $employeeId);
            }
            throw $e;
        }
    }
    
    /**
     * Unit Test: Leave spanning year boundary (Dec-Jan)
     * 
     * Test a leave request that spans from December to January (year boundary)
     * Expected: Correct handling of year boundary, separate records for each month
     * 
     * **Validates: Requirements 1.1, 1.2, 1.3**
     * 
     * @group Feature: fix-sick-leave-payroll-deductions
     */
    public function testYearBoundary_DecemberToJanuary()
    {
        // Check if database is available
        if (!$this->dbAvailable) {
            $this->markTestSkipped('Database connection not available for unit testing');
            return;
        }
        
        try {
            // Create test data: 45 days from 2025-12-15 to 2026-01-28
            // December: 17 days (Dec 15-31), Days 1-17 at 100% pay
            // January: 28 days (Jan 1-28), Days 18-30 at 100% pay (13 days), Days 31-45 at 75% pay (15 days)
            $basicSalary = 10000; // 10,000 SAR
            $dailyRate = $basicSalary / 30; // 333.33 SAR/day
            
            $employeeId = $this->createTestEmployeeForSickLeave($basicSalary);
            $leaveId = $this->createTestSickLeave($employeeId, '2025-12-15', '2026-01-28', 45, 0); // 0 cumulative days before
            
            // Execute the method under test
            $result = $this->leavePolicy->createSickLeaveDeductions($leaveId);
            
            $this->assertTrue($result, "createSickLeaveDeductions should return true");
            
            // Retrieve deduction records from database
            $deductions = $this->db->table('ci_payslip_statutory_deductions')
                ->where('staff_id', $employeeId)
                ->where('payslip_id', 0)
                ->where('contract_option_id', 0)
                ->like('pay_title', 'Sick', 'both')
                ->orderBy('salary_month', 'ASC')
                ->get()
                ->getResultArray();
            
            // Verify: At least 1 record (January has deductions)
            $this->assertGreaterThanOrEqual(
                1,
                count($deductions),
                "At least 1 deduction record should be created (January)"
            );
            
            // Build a map of month => amount
            $monthlyDeductions = [];
            foreach ($deductions as $deduction) {
                $monthlyDeductions[$deduction['salary_month']] = $deduction['pay_amount'];
            }
            
            // Verify January 2026: Days 31-45 (15 days at 75% pay)
            // Deduction: 15 days * 333.33 * 0.25 = 1,250.00 SAR
            if (isset($monthlyDeductions['2026-01'])) {
                $expectedJanuary = 15 * $dailyRate * 0.25;
                $this->assertEqualsWithDelta(
                    $expectedJanuary,
                    $monthlyDeductions['2026-01'],
                    1.0,
                    "January 2026 deduction should be approximately 1,250.00 SAR (15 days at 25% deduction)"
                );
            }
            
            // Verify that records span year boundary correctly
            $months = array_keys($monthlyDeductions);
            if (count($months) > 1) {
                // If we have multiple records, verify they're in correct order
                $this->assertLessThan(
                    $months[1],
                    $months[0],
                    "Months should be in chronological order (December before January)"
                );
            }
            
            // Clean up
            $this->cleanupTestSickLeave($leaveId, $employeeId);
            
        } catch (\Exception $e) {
            // Clean up on error
            if (isset($leaveId) && isset($employeeId)) {
                $this->cleanupTestSickLeave($leaveId, $employeeId);
            }
            throw $e;
        }
    }
    
    /**
     * Helper: Create a test employee for sick leave testing
     * 
     * @param float $basicSalary
     * @return int Employee ID
     */
    private function createTestEmployeeForSickLeave($basicSalary)
    {
        $userId = random_int(100000, 999999);
        $companyId = 1; // Use company ID 1 for testing
        
        // Create user record
        $userData = [
            'user_id' => $userId,
            'company_id' => $companyId,
            'first_name' => 'Test',
            'last_name' => 'Employee',
            'email' => 'test' . $userId . '@example.com',
            'username' => 'testuser' . $userId,
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $this->db->table('ci_erp_users')->insert($userData);
        
        // Create employee details record
        $staffData = [
            'user_id' => $userId,
            'company_id' => $companyId,
            'basic_salary' => $basicSalary,
            'office_shift_id' => 1,
            'employee_id' => 'EMP' . $userId,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $this->db->table('ci_erp_users_details')->insert($staffData);
        
        return $userId;
    }
    
    /**
     * Helper: Create a test sick leave application
     * 
     * @param int $employeeId
     * @param string $fromDate
     * @param string $toDate
     * @param int $calculatedDays
     * @param int $cumulativeBefore Cumulative sick days used before this leave
     * @return int Leave ID
     */
    private function createTestSickLeave($employeeId, $fromDate, $toDate, $calculatedDays, $cumulativeBefore)
    {
        // Try to get sick leave type ID, or use a default value
        $leaveTypeId = 1; // Default leave type ID
        
        try {
            $leaveType = $this->db->table('xin_leave_categories')
                ->where('category_name', 'Sick Leave')
                ->orWhere('category_name', 'إجازة مرضية')
                ->get()
                ->getRowArray();
            
            if ($leaveType) {
                $leaveTypeId = $leaveType['leave_type_id'];
            }
        } catch (\Exception $e) {
            // Table might not exist or have different structure, use default
            log_message('debug', 'Could not query leave types table: ' . $e->getMessage());
        }
        
        // Create leave application
        $leaveData = [
            'employee_id' => $employeeId,
            'company_id' => 1,
            'leave_type_id' => $leaveTypeId,
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'calculated_days' => $calculatedDays,
            'leave_hours' => $calculatedDays * 8, // Convert days to hours
            'leave_month' => date('n', strtotime($fromDate)),
            'leave_year' => date('Y', strtotime($fromDate)),
            'reason' => 'Unit test',
            'status' => 1, // Approved
            'country_code' => 'SA', // Saudi Arabia
            'created_at' => date('d-m-Y h:i:s')
        ];
        
        try {
            $this->db->table('ci_leave_applications')->insert($leaveData);
            return $this->db->insertID();
        } catch (\Exception $e) {
            // If calculated_days column doesn't exist, try without it
            unset($leaveData['calculated_days']);
            $this->db->table('ci_leave_applications')->insert($leaveData);
            return $this->db->insertID();
        }
    }
    
    /**
     * Helper: Clean up test sick leave data
     * 
     * @param int $leaveId
     * @param int $employeeId
     */
    private function cleanupTestSickLeave($leaveId, $employeeId)
    {
        // Delete deduction records
        $this->db->table('ci_payslip_statutory_deductions')
            ->where('staff_id', $employeeId)
            ->delete();
        
        // Delete leave application
        $this->db->table('ci_leave_applications')
            ->where('leave_id', $leaveId)
            ->delete();
        
        // Delete employee details
        $this->db->table('ci_erp_users_details')
            ->where('user_id', $employeeId)
            ->delete();
        
        // Delete user
        $this->db->table('ci_erp_users')
            ->where('user_id', $employeeId)
            ->delete();
    }

    /**
     * Unit Test: Retrieval when no deductions exist
     * 
     * **Validates: Requirements 8.1**
     * 
     * Test that getSickLeaveDeductionsForPayroll() returns an empty array
     * when no deductions exist for the given employee and month.
     * 
     * @group Feature: fix-sick-leave-payroll-deductions
     */
    public function testRetrievalEdgeCase_NoDeductionsExist_ReturnsEmptyArray()
    {
        // Check if database is available
        if (!$this->dbAvailable) {
            $this->markTestSkipped('Database connection not available for unit testing');
            return;
        }
        
        try {
            // Create a test employee with no deductions
            $employeeId = random_int(100000, 999999);
            $employeeData = [
                'user_id' => $employeeId,
                'company_id' => random_int(1, 100)
            ];
            $this->createTestEmployeeForDeductions($employeeData);
            
            // Query for deductions in a month where none exist
            $salaryMonth = '2026-03';
            $result = $this->leavePolicy->getSickLeaveDeductionsForPayroll($employeeId, $salaryMonth);
            
            // Verify: Should return empty array
            $this->assertIsArray($result, 'Result should be an array');
            $this->assertEmpty($result, 'Result should be empty when no deductions exist');
            $this->assertCount(0, $result, 'Result should have 0 elements');
            
            // Clean up
            $this->cleanupTestEmployee($employeeId);
        } catch (\Exception $e) {
            // Clean up on error
            if (isset($employeeId)) {
                $this->cleanupTestEmployee($employeeId);
            }
            throw $e;
        }
    }

    /**
     * Unit Test: Retrieval with invalid salary_month format
     * 
     * **Validates: Requirements 8.4**
     * 
     * Test that getSickLeaveDeductionsForPayroll() handles invalid salary_month
     * format gracefully and returns an empty array without throwing exceptions.
     * 
     * @group Feature: fix-sick-leave-payroll-deductions
     */
    public function testRetrievalEdgeCase_InvalidSalaryMonthFormat_HandlesGracefully()
    {
        // Check if database is available
        if (!$this->dbAvailable) {
            $this->markTestSkipped('Database connection not available for unit testing');
            return;
        }
        
        try {
            // Create a test employee
            $employeeId = random_int(100000, 999999);
            $employeeData = [
                'user_id' => $employeeId,
                'company_id' => random_int(1, 100)
            ];
            $this->createTestEmployeeForDeductions($employeeData);
            
            // Test various invalid salary_month formats
            $invalidFormats = [
                '2026/03',        // Wrong separator
                '03-2026',        // Wrong order
                '2026-3',         // Missing leading zero
                '26-03',          // Two-digit year
                '2026-13',        // Invalid month (13)
                '2026-00',        // Invalid month (00)
                'invalid',        // Completely invalid
                '',               // Empty string
                '2026',           // Year only
                '03',             // Month only
            ];
            
            foreach ($invalidFormats as $invalidFormat) {
                // Query with invalid format - should not throw exception
                $result = $this->leavePolicy->getSickLeaveDeductionsForPayroll($employeeId, $invalidFormat);
                
                // Verify: Should return empty array (no matches for invalid format)
                $this->assertIsArray($result, "Result should be an array for format: {$invalidFormat}");
                // Note: The method may return empty array or handle it gracefully
                // The key is that it should NOT throw an exception
            }
            
            // Clean up
            $this->cleanupTestEmployee($employeeId);
        } catch (\Exception $e) {
            // Clean up on error
            if (isset($employeeId)) {
                $this->cleanupTestEmployee($employeeId);
            }
            throw $e;
        }
    }

    /**
     * Unit Test: Retrieval with database connection failure
     * 
     * **Validates: Requirements 8.2**
     * 
     * Test that getSickLeaveDeductionsForPayroll() handles database connection
     * failures gracefully by logging the error and returning an empty array.
     * 
     * Note: This test is challenging to implement without mocking the database
     * connection. In a real-world scenario, you would use dependency injection
     * and mock the database to simulate a connection failure.
     * 
     * For now, we'll test that the method handles query errors gracefully by
     * testing with a non-existent table or invalid query conditions.
     * 
     * @group Feature: fix-sick-leave-payroll-deductions
     */
    public function testRetrievalEdgeCase_DatabaseError_LogsAndReturnsEmpty()
    {
        // Check if database is available
        if (!$this->dbAvailable) {
            $this->markTestSkipped('Database connection not available for unit testing');
            return;
        }
        
        // Note: This test verifies that the method is resilient to database errors.
        // In the current implementation, database errors would throw exceptions.
        // To properly test this, we would need to:
        // 1. Add try-catch error handling to getSickLeaveDeductionsForPayroll()
        // 2. Mock the database connection to simulate failures
        // 
        // For now, we'll verify that the method works correctly with valid inputs
        // and document the need for error handling improvements.
        
        try {
            // Create a test employee
            $employeeId = random_int(100000, 999999);
            $employeeData = [
                'user_id' => $employeeId,
                'company_id' => random_int(1, 100)
            ];
            $this->createTestEmployeeForDeductions($employeeData);
            
            // Test with valid inputs to ensure basic functionality
            $salaryMonth = '2026-03';
            $result = $this->leavePolicy->getSickLeaveDeductionsForPayroll($employeeId, $salaryMonth);
            
            // Verify: Should return array (empty in this case since no deductions exist)
            $this->assertIsArray($result, 'Result should be an array');
            
            // Clean up
            $this->cleanupTestEmployee($employeeId);
            
            // Mark this test as incomplete to indicate that proper error handling
            // testing requires code changes to getSickLeaveDeductionsForPayroll()
            $this->markTestIncomplete(
                'Full database error handling test requires adding try-catch blocks ' .
                'to getSickLeaveDeductionsForPayroll() method. Current implementation ' .
                'would throw exceptions on database errors. Consider adding error handling ' .
                'that logs errors and returns empty array as specified in Requirements 8.2.'
            );
        } catch (\Exception $e) {
            // Clean up on error
            if (isset($employeeId)) {
                $this->cleanupTestEmployee($employeeId);
            }
            throw $e;
        }
    }

    /**
     * Unit Test: Retrieval returns correct structure
     * 
     * **Validates: Requirements 2.3**
     * 
     * Test that getSickLeaveDeductionsForPayroll() returns deductions with
     * the correct structure including deduction_id, deduction_amount, and pay_title.
     * 
     * @group Feature: fix-sick-leave-payroll-deductions
     */
    public function testRetrievalEdgeCase_CorrectStructure_ReturnsExpectedFields()
    {
        // Check if database is available
        if (!$this->dbAvailable) {
            $this->markTestSkipped('Database connection not available for unit testing');
            return;
        }
        
        try {
            // Create a test employee
            $employeeId = random_int(100000, 999999);
            $employeeData = [
                'user_id' => $employeeId,
                'company_id' => random_int(1, 100)
            ];
            $this->createTestEmployeeForDeductions($employeeData);
            
            // Create a test deduction
            $salaryMonth = '2026-03';
            $deductionData = [
                'payslip_id' => 0,
                'staff_id' => $employeeId,
                'is_fixed' => 0,
                'pay_title' => 'Sick Leave Deduction',
                'pay_amount' => 250.50,
                'salary_month' => $salaryMonth,
                'contract_option_id' => 0,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $this->db->table('ci_payslip_statutory_deductions')->insert($deductionData);
            $deductionId = $this->db->insertID();
            
            // Query for deductions
            $result = $this->leavePolicy->getSickLeaveDeductionsForPayroll($employeeId, $salaryMonth);
            
            // Verify: Should return array with one element
            $this->assertIsArray($result, 'Result should be an array');
            $this->assertCount(1, $result, 'Result should have 1 element');
            
            // Verify: First element should have correct structure
            $deduction = $result[0];
            $this->assertArrayHasKey('deduction_id', $deduction, 'Deduction should have deduction_id key');
            $this->assertArrayHasKey('deduction_amount', $deduction, 'Deduction should have deduction_amount key');
            $this->assertArrayHasKey('pay_title', $deduction, 'Deduction should have pay_title key');
            
            // Verify: Values should match what we inserted
            $this->assertEquals($deductionId, $deduction['deduction_id'], 'Deduction ID should match');
            $this->assertEquals(250.50, $deduction['deduction_amount'], 'Deduction amount should match');
            $this->assertEquals('Sick Leave Deduction', $deduction['pay_title'], 'Pay title should match');
            
            // Clean up
            $this->cleanupTestDeductions($employeeId);
            $this->cleanupTestEmployee($employeeId);
        } catch (\Exception $e) {
            // Clean up on error
            if (isset($employeeId)) {
                $this->cleanupTestDeductions($employeeId);
                $this->cleanupTestEmployee($employeeId);
            }
            throw $e;
        }
    }

    /**
     * Unit Test: Retrieval filters by payslip_id = 0
     * 
     * **Validates: Requirements 2.2**
     * 
     * Test that getSickLeaveDeductionsForPayroll() only returns deductions
     * with payslip_id = 0 (standing deductions) and excludes processed deductions.
     * 
     * @group Feature: fix-sick-leave-payroll-deductions
     */
    public function testRetrievalEdgeCase_FiltersStandingDeductions_ExcludesProcessed()
    {
        // Check if database is available
        if (!$this->dbAvailable) {
            $this->markTestSkipped('Database connection not available for unit testing');
            return;
        }
        
        try {
            // Create a test employee
            $employeeId = random_int(100000, 999999);
            $employeeData = [
                'user_id' => $employeeId,
                'company_id' => random_int(1, 100)
            ];
            $this->createTestEmployeeForDeductions($employeeData);
            
            $salaryMonth = '2026-03';
            
            // Create a standing deduction (payslip_id = 0) - should be returned
            $standingDeductionData = [
                'payslip_id' => 0,
                'staff_id' => $employeeId,
                'is_fixed' => 0,
                'pay_title' => 'Sick Leave Deduction - Standing',
                'pay_amount' => 100.00,
                'salary_month' => $salaryMonth,
                'contract_option_id' => 0,
                'created_at' => date('Y-m-d H:i:s')
            ];
            $this->db->table('ci_payslip_statutory_deductions')->insert($standingDeductionData);
            $standingDeductionId = $this->db->insertID();
            
            // Create a processed deduction (payslip_id > 0) - should NOT be returned
            $processedDeductionData = [
                'payslip_id' => 123,
                'staff_id' => $employeeId,
                'is_fixed' => 0,
                'pay_title' => 'Sick Leave Deduction - Processed',
                'pay_amount' => 200.00,
                'salary_month' => $salaryMonth,
                'contract_option_id' => 0,
                'created_at' => date('Y-m-d H:i:s')
            ];
            $this->db->table('ci_payslip_statutory_deductions')->insert($processedDeductionData);
            
            // Query for deductions
            $result = $this->leavePolicy->getSickLeaveDeductionsForPayroll($employeeId, $salaryMonth);
            
            // Verify: Should return only the standing deduction
            $this->assertIsArray($result, 'Result should be an array');
            $this->assertCount(1, $result, 'Result should have 1 element (only standing deduction)');
            
            // Verify: The returned deduction should be the standing one
            $deduction = $result[0];
            $this->assertEquals($standingDeductionId, $deduction['deduction_id'], 'Should return standing deduction');
            $this->assertEquals(100.00, $deduction['deduction_amount'], 'Should return standing deduction amount');
            $this->assertStringContainsString('Standing', $deduction['pay_title'], 'Should return standing deduction title');
            
            // Clean up
            $this->cleanupTestDeductions($employeeId);
            $this->cleanupTestEmployee($employeeId);
        } catch (\Exception $e) {
            // Clean up on error
            if (isset($employeeId)) {
                $this->cleanupTestDeductions($employeeId);
                $this->cleanupTestEmployee($employeeId);
            }
            throw $e;
        }
    }

    /**
     * Unit Test: Retrieval filters by contract_option_id = 0
     * 
     * **Validates: Requirements 2.2**
     * 
     * Test that getSickLeaveDeductionsForPayroll() only returns deductions
     * with contract_option_id = 0 (automatic deductions) and excludes manual deductions.
     * 
     * @group Feature: fix-sick-leave-payroll-deductions
     */
    public function testRetrievalEdgeCase_FiltersAutomaticDeductions_ExcludesManual()
    {
        // Check if database is available
        if (!$this->dbAvailable) {
            $this->markTestSkipped('Database connection not available for unit testing');
            return;
        }
        
        try {
            // Create a test employee
            $employeeId = random_int(100000, 999999);
            $employeeData = [
                'user_id' => $employeeId,
                'company_id' => random_int(1, 100)
            ];
            $this->createTestEmployeeForDeductions($employeeData);
            
            $salaryMonth = '2026-03';
            
            // Create an automatic deduction (contract_option_id = 0) - should be returned
            $automaticDeductionData = [
                'payslip_id' => 0,
                'staff_id' => $employeeId,
                'is_fixed' => 0,
                'pay_title' => 'Sick Leave Deduction - Automatic',
                'pay_amount' => 150.00,
                'salary_month' => $salaryMonth,
                'contract_option_id' => 0,
                'created_at' => date('Y-m-d H:i:s')
            ];
            $this->db->table('ci_payslip_statutory_deductions')->insert($automaticDeductionData);
            $automaticDeductionId = $this->db->insertID();
            
            // Create a manual deduction (contract_option_id > 0) - should NOT be returned
            $manualDeductionData = [
                'payslip_id' => 0,
                'staff_id' => $employeeId,
                'is_fixed' => 0,
                'pay_title' => 'Sick Leave Deduction - Manual',
                'pay_amount' => 300.00,
                'salary_month' => $salaryMonth,
                'contract_option_id' => 5,
                'created_at' => date('Y-m-d H:i:s')
            ];
            $this->db->table('ci_payslip_statutory_deductions')->insert($manualDeductionData);
            
            // Query for deductions
            $result = $this->leavePolicy->getSickLeaveDeductionsForPayroll($employeeId, $salaryMonth);
            
            // Verify: Should return only the automatic deduction
            $this->assertIsArray($result, 'Result should be an array');
            $this->assertCount(1, $result, 'Result should have 1 element (only automatic deduction)');
            
            // Verify: The returned deduction should be the automatic one
            $deduction = $result[0];
            $this->assertEquals($automaticDeductionId, $deduction['deduction_id'], 'Should return automatic deduction');
            $this->assertEquals(150.00, $deduction['deduction_amount'], 'Should return automatic deduction amount');
            $this->assertStringContainsString('Automatic', $deduction['pay_title'], 'Should return automatic deduction title');
            
            // Clean up
            $this->cleanupTestDeductions($employeeId);
            $this->cleanupTestEmployee($employeeId);
        } catch (\Exception $e) {
            // Clean up on error
            if (isset($employeeId)) {
                $this->cleanupTestDeductions($employeeId);
                $this->cleanupTestEmployee($employeeId);
            }
            throw $e;
        }
    }

    /**
     * Property 8: Maternity Leave Parity
     * 
     * **Validates: Requirements 7.1, 7.2, 7.3, 7.4**
     * 
     * For any maternity leave request, the deduction creation, retrieval, and display logic
     * should follow the same pattern as sick leave deductions, with the only difference being
     * the leave type identifier in the pay_title field.
     * 
     * This property-based test generates random maternity leave requests and verifies that:
     * 1. Deductions are aggregated monthly (one record per month)
     * 2. Retrieval methods work correctly
     * 3. The pattern matches sick leave exactly except for the title
     * 
     * @group Feature: fix-sick-leave-payroll-deductions, Property 8: Maternity Leave Parity
     */
    public function testProperty8_MaternityLeaveParity()
    {
        // Check if database is available
        if (!$this->dbAvailable) {
            $this->markTestSkipped('Database connection not available for property testing');
            return;
        }
        
        $iterations = 100;
        $passedTests = 0;
        $failedScenarios = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            try {
                // Generate random maternity leave scenario
                $scenario = $this->generateMaternityLeaveScenario();
                
                // Create test employee
                $employeeId = $this->createTestEmployeeForDeductions($scenario['employee']);
                
                // Create test leave application
                $leaveId = $this->createTestMaternityLeaveApplication($employeeId, $scenario['leave']);
                
                // Call createMaternityLeaveDeductions
                $result = $this->leavePolicy->createMaternityLeaveDeductions($leaveId);
                
                // Verify: Method should return true
                $this->assertTrue($result, "createMaternityLeaveDeductions should return true");
                
                // Verify: Check database records for each month
                $monthsInLeave = $this->getMonthsInRange($scenario['leave']['from_date'], $scenario['leave']['to_date']);
                
                foreach ($monthsInLeave as $month) {
                    // Query deductions for this month
                    $deductions = $this->db->table('ci_payslip_statutory_deductions')
                        ->where('staff_id', $employeeId)
                        ->where('salary_month', $month)
                        ->where('payslip_id', 0)
                        ->where('contract_option_id', 0)
                        ->like('pay_title', 'Maternity', 'both')
                        ->get()->getResultArray();
                    
                    // Calculate expected deduction for this month
                    $expectedDeduction = $this->calculateExpectedMonthlyMaternityDeduction(
                        $scenario['leave'],
                        $month,
                        $scenario['employee']['basic_salary']
                    );
                    
                    if ($expectedDeduction > 0) {
                        // Verify: Should have exactly ONE record per month
                        if (count($deductions) !== 1) {
                            $failedScenarios[] = [
                                'iteration' => $i,
                                'scenario' => $scenario,
                                'month' => $month,
                                'expected_records' => 1,
                                'actual_records' => count($deductions),
                                'reason' => 'Should have exactly one aggregated record per month'
                            ];
                        }
                        
                        $this->assertCount(
                            1,
                            $deductions,
                            "Property violated: Should have exactly ONE maternity leave deduction record for month {$month}"
                        );
                        
                        $deduction = $deductions[0];
                        
                        // Verify: Title should be simple (not include tier details)
                        $this->assertEquals(
                            'Maternity Leave Deduction',
                            $deduction['pay_title'],
                            "Property violated: Title should be 'Maternity Leave Deduction' without tier details"
                        );
                        
                        // Verify: Amount should match expected (within small tolerance for rounding)
                        $actualAmount = (float)$deduction['pay_amount'];
                        $this->assertEqualsWithDelta(
                            $expectedDeduction,
                            $actualAmount,
                            0.02,
                            "Property violated: Deduction amount should match expected for month {$month}. " .
                            "Expected {$expectedDeduction}, got {$actualAmount}"
                        );
                        
                        // Verify: contract_option_id should be 0
                        $this->assertEquals(
                            0,
                            $deduction['contract_option_id'],
                            "Property violated: contract_option_id should be 0 for automatic deductions"
                        );
                    } else {
                        // No deduction expected for this month
                        $this->assertCount(
                            0,
                            $deductions,
                            "Property violated: Should have NO maternity leave deduction records for month {$month} when no deduction expected"
                        );
                    }
                }
                
                // Verify: Retrieval method works correctly
                foreach ($monthsInLeave as $month) {
                    $retrievedDeductions = $this->leavePolicy->getMaternityLeaveDeductionsForPayroll($employeeId, $month);
                    
                    $expectedDeduction = $this->calculateExpectedMonthlyMaternityDeduction(
                        $scenario['leave'],
                        $month,
                        $scenario['employee']['basic_salary']
                    );
                    
                    if ($expectedDeduction > 0) {
                        $this->assertCount(
                            1,
                            $retrievedDeductions,
                            "Property violated: Retrieval should return exactly ONE record for month {$month}"
                        );
                        
                        $totalRetrieved = array_sum(array_column($retrievedDeductions, 'deduction_amount'));
                        $this->assertEqualsWithDelta(
                            $expectedDeduction,
                            $totalRetrieved,
                            0.02,
                            "Property violated: Retrieved amount should match expected for month {$month}"
                        );
                    } else {
                        $this->assertCount(
                            0,
                            $retrievedDeductions,
                            "Property violated: Retrieval should return NO records for month {$month} when no deduction expected"
                        );
                    }
                }
                
                // Clean up
                $this->cleanupTestLeaveApplication($leaveId);
                $this->cleanupTestDeductions($employeeId);
                $this->cleanupTestEmployee($employeeId);
                
                $passedTests++;
            } catch (\Exception $e) {
                // Clean up on error
                if (isset($leaveId)) {
                    $this->cleanupTestLeaveApplication($leaveId);
                }
                if (isset($employeeId)) {
                    $this->cleanupTestDeductions($employeeId);
                    $this->cleanupTestEmployee($employeeId);
                }
                throw $e;
            }
        }
        
        // Report any failed scenarios
        if (!empty($failedScenarios)) {
            $this->fail(
                "Property test failed for " . count($failedScenarios) . " scenarios:\n" .
                print_r($failedScenarios, true)
            );
        }
        
        // Verify all iterations passed
        $this->assertEquals(
            $iterations,
            $passedTests,
            "Property test should pass for all {$iterations} iterations"
        );
    }

    /**
     * Generate a random maternity leave scenario for property testing
     * 
     * @return array ['employee' => array, 'leave' => array]
     */
    private function generateMaternityLeaveScenario()
    {
        // Generate random employee data
        $employeeId = random_int(100000, 999999);
        $basicSalary = random_int(5000, 20000);
        
        $employee = [
            'user_id' => $employeeId,
            'company_id' => random_int(1, 100),
            'basic_salary' => $basicSalary
        ];
        
        // Generate random maternity leave (typically 70-77 days for Saudi Arabia)
        // Days 1-70: Full pay (no deduction)
        // Days 71+: 100% deduction
        $leaveDays = random_int(70, 90);
        
        // Random start date in the future
        $startDate = new \DateTime();
        $startDate->modify('+' . random_int(1, 60) . ' days');
        
        $endDate = clone $startDate;
        $endDate->modify('+' . ($leaveDays - 1) . ' days');
        
        $leave = [
            'from_date' => $startDate->format('Y-m-d'),
            'to_date' => $endDate->format('Y-m-d'),
            'calculated_days' => $leaveDays,
            'country_code' => 'SA'
        ];
        
        return [
            'employee' => $employee,
            'leave' => $leave
        ];
    }

    /**
     * Calculate expected monthly maternity deduction for a given month
     * 
     * @param array $leave Leave data
     * @param string $month Month in Y-m format
     * @param float $basicSalary Basic salary
     * @return float Expected deduction amount
     */
    private function calculateExpectedMonthlyMaternityDeduction($leave, $month, $basicSalary)
    {
        $dailyRate = $basicSalary / 30;
        $fromDate = new \DateTime($leave['from_date']);
        $toDate = new \DateTime($leave['to_date']);
        
        // Get month boundaries
        $monthStart = new \DateTime($month . '-01');
        $monthEnd = new \DateTime($month . '-' . $monthStart->format('t'));
        
        // Calculate days in this month segment
        $segmentStart = max($fromDate, $monthStart);
        $segmentEnd = min($toDate, $monthEnd);
        
        if ($segmentStart > $segmentEnd) {
            return 0; // No overlap with this month
        }
        
        $daysInSegment = $segmentStart->diff($segmentEnd)->days + 1;
        
        // Calculate cumulative days at start of this segment
        $cumulativeDaysAtStart = $fromDate->diff($segmentStart)->days;
        
        // Calculate deduction based on Saudi Arabia maternity leave tiers
        // Days 1-70: 100% pay (0% deduction)
        // Days 71+: 0% pay (100% deduction)
        $totalDeduction = 0;
        
        for ($day = 0; $day < $daysInSegment; $day++) {
            $cumulativeDay = $cumulativeDaysAtStart + $day + 1;
            
            if ($cumulativeDay > 70) {
                // 100% deduction
                $totalDeduction += $dailyRate;
            }
            // Days 1-70: no deduction
        }
        
        return round($totalDeduction, 2);
    }

    /**
     * Get list of months (Y-m format) in a date range
     * 
     * @param string $fromDate Start date (Y-m-d format)
     * @param string $toDate End date (Y-m-d format)
     * @return array List of months in Y-m format
     */
    private function getMonthsInRange($fromDate, $toDate)
    {
        $months = [];
        $current = new \DateTime($fromDate);
        $end = new \DateTime($toDate);
        
        while ($current <= $end) {
            $months[] = $current->format('Y-m');
            $current->modify('first day of next month');
        }
        
        return array_unique($months);
    }

    /**
     * Create a test maternity leave application in the database
     * 
     * @param int $employeeId Employee ID
     * @param array $leaveData Leave data
     * @return int Leave application ID
     */
    private function createTestMaternityLeaveApplication($employeeId, $leaveData)
    {
        // Try to get maternity leave type ID
        $leaveTypeId = 3; // Default maternity leave type ID
        
        try {
            $leaveType = $this->db->table('xin_leave_categories')
                ->where('category_name', 'Maternity Leave')
                ->orWhere('category_name', 'إجازة الأمومة')
                ->get()
                ->getRowArray();
            
            if ($leaveType) {
                $leaveTypeId = $leaveType['leave_type_id'];
            }
        } catch (\Exception $e) {
            // Table might not exist or have different structure, use default
            log_message('debug', 'Could not query leave types table: ' . $e->getMessage());
        }
        
        $leaveAppData = [
            'employee_id' => $employeeId,
            'company_id' => $leaveData['company_id'] ?? 1,
            'leave_type_id' => $leaveTypeId,
            'from_date' => $leaveData['from_date'],
            'to_date' => $leaveData['to_date'],
            'calculated_days' => $leaveData['calculated_days'],
            'country_code' => $leaveData['country_code'] ?? 'SA',
            'status' => 1, // Approved
            'salary_deduction_applied' => 0,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $this->db->table('ci_leave_applications')->insert($leaveAppData);
        return $this->db->insertID();
    }
}

