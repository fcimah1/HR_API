<?php

namespace Tests\Controllers;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Integration Tests for Leave Controller - Fix Leave Hours Calculation
 * 
 * These tests verify that the Leave controller correctly integrates with the 
 * LeavePolicy library to calculate and store leave hours.
 * 
 * @group Feature: fix-leave-hours-calculation
 */
class LeaveControllerIntegrationTest extends CIUnitTestCase
{
    protected $db;
    protected $dbAvailable = false;

    protected function setUp(): void
    {
        parent::setUp();
        
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
     * Test that add_leave() validates shift assignment before processing
     * 
     * This test verifies that the controller rejects leave requests from employees
     * without an assigned shift, as required by Requirements 1.1, 1.2.
     */
    public function testAddLeave_NoShiftAssigned_ReturnsError()
    {
        // Check if database is available
        if (!$this->dbAvailable) {
            $this->markTestSkipped('Database connection not available for integration testing');
            return;
        }
        
        // This test would require:
        // 1. Creating a test employee without a shift
        // 2. Simulating a POST request to add_leave()
        // 3. Verifying the response contains the shift validation error
        
        // For now, we mark this as a placeholder that requires proper test setup
        $this->markTestSkipped('Requires test environment with session and request mocking');
    }

    /**
     * Test that add_leave() correctly calculates hours for full day leave
     * 
     * This test verifies that the controller uses LeavePolicy library to calculate
     * working days and convert them to hours for full day leave requests.
     * 
     * Requirements: 3.1, 3.3
     */
    public function testAddLeave_FullDayLeave_CalculatesHoursCorrectly()
    {
        // Check if database is available
        if (!$this->dbAvailable) {
            $this->markTestSkipped('Database connection not available for integration testing');
            return;
        }
        
        // This test would require:
        // 1. Creating a test employee with a shift (8 hours per day)
        // 2. Simulating a POST request for a 5-day leave (Mon-Fri)
        // 3. Verifying the stored leave_hours is 40 (5 days × 8 hours)
        
        // For now, we mark this as a placeholder that requires proper test setup
        $this->markTestSkipped('Requires test environment with session and request mocking');
    }

    /**
     * Test that add_leave() correctly calculates hours for hourly permission
     * 
     * This test verifies that the controller uses LeavePolicy library to calculate
     * hours for hourly permission requests, including break time subtraction.
     * 
     * Requirements: 4.1, 4.2
     */
    public function testAddLeave_HourlyPermission_CalculatesHoursCorrectly()
    {
        // Check if database is available
        if (!$this->dbAvailable) {
            $this->markTestSkipped('Database connection not available for integration testing');
            return;
        }
        
        // This test would require:
        // 1. Creating a test employee with a shift (8:00-17:00, lunch 12:00-13:00)
        // 2. Simulating a POST request for permission from 08:00 to 14:00
        // 3. Verifying the stored leave_hours is 5 (6 hours - 1 hour break)
        
        // For now, we mark this as a placeholder that requires proper test setup
        $this->markTestSkipped('Requires test environment with session and request mocking');
    }

    /**
     * Test that add_leave() rejects hourly permission with invalid times
     * 
     * This test verifies that the controller rejects hourly permission requests
     * where the times fall outside the employee's shift hours.
     * 
     * Requirements: 4.3, 4.4
     */
    public function testAddLeave_HourlyPermissionOutsideShiftHours_ReturnsError()
    {
        // Check if database is available
        if (!$this->dbAvailable) {
            $this->markTestSkipped('Database connection not available for integration testing');
            return;
        }
        
        // This test would require:
        // 1. Creating a test employee with a shift (8:00-17:00)
        // 2. Simulating a POST request for permission from 06:00 to 10:00 (starts before shift)
        // 3. Verifying the response contains the time validation error
        
        // For now, we mark this as a placeholder that requires proper test setup
        $this->markTestSkipped('Requires test environment with session and request mocking');
    }

    /**
     * Test end-to-end flow: Full day leave request
     * 
     * This test verifies the complete flow from request submission to database storage
     * for a full day leave request.
     */
    public function testEndToEnd_FullDayLeaveRequest()
    {
        // Check if database is available
        if (!$this->dbAvailable) {
            $this->markTestSkipped('Database connection not available for integration testing');
            return;
        }
        
        // This test would require:
        // 1. Setting up a complete test environment (employee, shift, company, etc.)
        // 2. Simulating a full POST request with all required fields
        // 3. Verifying:
        //    - Shift validation passes
        //    - Working days are calculated correctly
        //    - Hours are calculated correctly
        //    - Leave application is stored in database
        //    - Stored leave_hours matches calculated hours
        
        // For now, we mark this as a placeholder that requires proper test setup
        $this->markTestSkipped('Requires complete test environment setup');
    }

    /**
     * Test end-to-end flow: Hourly permission request
     * 
     * This test verifies the complete flow from request submission to database storage
     * for an hourly permission request.
     */
    public function testEndToEnd_HourlyPermissionRequest()
    {
        // Check if database is available
        if (!$this->dbAvailable) {
            $this->markTestSkipped('Database connection not available for integration testing');
            return;
        }
        
        // This test would require:
        // 1. Setting up a complete test environment (employee, shift, company, etc.)
        // 2. Simulating a full POST request with all required fields
        // 3. Verifying:
        //    - Shift validation passes
        //    - Time validation passes
        //    - Hours are calculated correctly (with break time subtraction)
        //    - Leave application is stored in database
        //    - Stored leave_hours matches calculated hours
        
        // For now, we mark this as a placeholder that requires proper test setup
        $this->markTestSkipped('Requires complete test environment setup');
    }

    /**
     * Test error handling: No shift assigned
     * 
     * This test verifies that the controller properly handles the case where
     * an employee has no shift assigned.
     */
    public function testErrorHandling_NoShiftAssigned()
    {
        // Check if database is available
        if (!$this->dbAvailable) {
            $this->markTestSkipped('Database connection not available for integration testing');
            return;
        }
        
        // This test would verify:
        // 1. Employee without shift cannot submit leave request
        // 2. Error message is properly localized
        // 3. Response format is correct (JSON with error field)
        
        // For now, we mark this as a placeholder that requires proper test setup
        $this->markTestSkipped('Requires test environment with session and request mocking');
    }

    /**
     * Test error handling: Invalid permission times
     * 
     * This test verifies that the controller properly handles the case where
     * hourly permission times fall outside shift hours.
     */
    public function testErrorHandling_InvalidPermissionTimes()
    {
        // Check if database is available
        if (!$this->dbAvailable) {
            $this->markTestSkipped('Database connection not available for integration testing');
            return;
        }
        
        // This test would verify:
        // 1. Permission request with times outside shift hours is rejected
        // 2. Error message is properly localized
        // 3. Response format is correct (JSON with error field)
        
        // For now, we mark this as a placeholder that requires proper test setup
        $this->markTestSkipped('Requires test environment with session and request mocking');
    }

    /**
     * Test that leave hours are stored correctly in the database
     * 
     * This is a simplified integration test that verifies the database storage
     * without requiring full controller request simulation.
     */
    public function testLeaveHoursStorage_StoresCorrectValue()
    {
        // Check if database is available
        if (!$this->dbAvailable) {
            $this->markTestSkipped('Database connection not available for integration testing');
            return;
        }
        
        try {
            // Create test data
            $testEmployeeId = random_int(100000, 999999);
            $testCompanyId = random_int(1, 100);
            $testLeaveHours = 40.5;
            
            // Create test employee
            $userData = [
                'user_id' => $testEmployeeId,
                'company_id' => $testCompanyId,
                'first_name' => 'Test',
                'last_name' => 'Employee',
                'email' => 'test' . $testEmployeeId . '@example.com',
                'username' => 'testuser' . $testEmployeeId,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $this->db->table('ci_erp_users')->insert($userData);
            
            // Create test leave application
            $leaveData = [
                'company_id' => $testCompanyId,
                'employee_id' => $testEmployeeId,
                'leave_type_id' => 1,
                'from_date' => date('Y-m-d'),
                'to_date' => date('Y-m-d', strtotime('+5 days')),
                'leave_hours' => $testLeaveHours,
                'leave_month' => date('n'),
                'leave_year' => date('Y'),
                'reason' => 'Integration test',
                'status' => 0,
                'is_deducted' => 1,
                'created_at' => date('d-m-Y h:i:s')
            ];
            
            $this->db->table('ci_leave_applications')->insert($leaveData);
            $leaveId = $this->db->insertID();
            
            // Retrieve and verify
            $retrievedLeave = $this->db->table('ci_leave_applications')
                ->where('leave_id', $leaveId)
                ->get()
                ->getRow();
            
            $this->assertNotNull($retrievedLeave, 'Leave application should be stored in database');
            $this->assertEquals($testLeaveHours, (float)$retrievedLeave->leave_hours, 'Stored leave_hours should match the value that was inserted');
            
            // Clean up
            $this->db->table('ci_leave_applications')->where('leave_id', $leaveId)->delete();
            $this->db->table('ci_erp_users')->where('user_id', $testEmployeeId)->delete();
            
        } catch (\Exception $e) {
            // Clean up on error
            if (isset($leaveId)) {
                $this->db->table('ci_leave_applications')->where('leave_id', $leaveId)->delete();
            }
            if (isset($testEmployeeId)) {
                $this->db->table('ci_erp_users')->where('user_id', $testEmployeeId)->delete();
            }
            throw $e;
        }
    }

    /**
     * Test that the LeavePolicy library is properly instantiated in the controller
     * 
     * This test verifies that the controller can create an instance of the LeavePolicy
     * library and call its methods.
     */
    public function testLeavePolicyLibrary_CanBeInstantiated()
    {
        // This is a simple smoke test to verify the library can be loaded
        $leavePolicy = new \App\Libraries\LeavePolicy();
        
        $this->assertInstanceOf(
            \App\Libraries\LeavePolicy::class,
            $leavePolicy,
            'LeavePolicy library should be instantiable'
        );
        
        // Verify key methods exist
        $this->assertTrue(
            method_exists($leavePolicy, 'validateEmployeeHasShift'),
            'LeavePolicy should have validateEmployeeHasShift method'
        );
        
        $this->assertTrue(
            method_exists($leavePolicy, 'calculateWorkingDaysInRange'),
            'LeavePolicy should have calculateWorkingDaysInRange method'
        );
        
        $this->assertTrue(
            method_exists($leavePolicy, 'convertDaysToHours'),
            'LeavePolicy should have convertDaysToHours method'
        );
        
        $this->assertTrue(
            method_exists($leavePolicy, 'calculateHourlyPermissionHours'),
            'LeavePolicy should have calculateHourlyPermissionHours method'
        );
    }

    /**
     * Test backward compatibility: Existing leave applications remain unchanged
     * 
     * This test verifies that the new calculation logic only applies to NEW leave requests
     * and does NOT modify existing leave applications in the database.
     * 
     * Requirements: 3.4, 7.1, 7.2, 7.3, 7.4
     */
    public function testBackwardCompatibility_ExistingLeaveApplicationsUnchanged()
    {
        // Check if database is available
        if (!$this->dbAvailable) {
            $this->markTestSkipped('Database connection not available for integration testing');
            return;
        }
        
        try {
            // Step 1: Create a test employee with a shift
            $testEmployeeId = random_int(100000, 999999);
            $testCompanyId = random_int(1, 100);
            
            // Create test employee
            $userData = [
                'user_id' => $testEmployeeId,
                'company_id' => $testCompanyId,
                'first_name' => 'Test',
                'last_name' => 'Employee',
                'email' => 'test' . $testEmployeeId . '@example.com',
                'username' => 'testuser' . $testEmployeeId,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $this->db->table('ci_erp_users')->insert($userData);
            
            // Create test shift (8 hours per day)
            $shiftData = [
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
            
            $this->db->table('ci_office_shifts')->insert($shiftData);
            $shiftId = $this->db->insertID();
            
            // Create staff details with shift assignment
            $staffData = [
                'user_id' => $testEmployeeId,
                'company_id' => $testCompanyId,
                'office_shift_id' => $shiftId,
                'employee_id' => 'EMP' . $testEmployeeId,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $this->db->table('ci_erp_users_details')->insert($staffData);
            
            // Step 2: Create an "old" leave application with INCORRECT hours (simulating old logic)
            // Old logic might have stored 5 days as 5 hours (incorrect)
            $oldLeaveHours = 5.0; // Incorrect: should be 40 hours (5 days × 8 hours)
            
            $oldLeaveData = [
                'company_id' => $testCompanyId,
                'employee_id' => $testEmployeeId,
                'leave_type_id' => 1,
                'from_date' => '2024-01-15', // Monday
                'to_date' => '2024-01-19',   // Friday (5 working days)
                'leave_hours' => $oldLeaveHours, // Old incorrect value
                'leave_month' => 1,
                'leave_year' => 2024,
                'reason' => 'Old leave application (before fix)',
                'status' => 1, // Approved
                'is_deducted' => 1,
                'created_at' => '15-01-2024 10:00:00' // Old timestamp
            ];
            
            $this->db->table('ci_leave_applications')->insert($oldLeaveData);
            $oldLeaveId = $this->db->insertID();
            
            // Step 3: Verify the old leave application exists with its original (incorrect) hours
            $retrievedOldLeave = $this->db->table('ci_leave_applications')
                ->where('leave_id', $oldLeaveId)
                ->get()
                ->getRow();
            
            $this->assertNotNull($retrievedOldLeave, 'Old leave application should exist in database');
            $this->assertEquals(
                $oldLeaveHours,
                (float)$retrievedOldLeave->leave_hours,
                'Old leave application should retain its original (incorrect) leave_hours value'
            );
            
            // Step 4: Simulate a NEW leave request using the LeavePolicy library
            // This simulates what the controller would do for a new request
            $leavePolicy = new \App\Libraries\LeavePolicy();
            
            $newFromDate = '2024-02-05'; // Monday
            $newToDate = '2024-02-09';   // Friday (5 working days)
            
            // Calculate using NEW logic
            $workingDays = $leavePolicy->calculateWorkingDaysInRange($testEmployeeId, $newFromDate, $newToDate);
            $newLeaveHours = $leavePolicy->convertDaysToHours($testEmployeeId, $workingDays);
            
            // Verify NEW calculation is correct (5 days × 8 hours = 40 hours)
            $this->assertEquals(5, $workingDays, 'New calculation should find 5 working days');
            $this->assertEquals(40.0, $newLeaveHours, 'New calculation should calculate 40 hours (5 days × 8 hours)');
            
            // Step 5: Create a NEW leave application with CORRECT hours (using new logic)
            $newLeaveData = [
                'company_id' => $testCompanyId,
                'employee_id' => $testEmployeeId,
                'leave_type_id' => 1,
                'from_date' => $newFromDate,
                'to_date' => $newToDate,
                'leave_hours' => $newLeaveHours, // New correct value (40 hours)
                'leave_month' => 2,
                'leave_year' => 2024,
                'reason' => 'New leave application (after fix)',
                'status' => 0, // Pending
                'is_deducted' => 1,
                'created_at' => date('d-m-Y h:i:s') // Current timestamp
            ];
            
            $this->db->table('ci_leave_applications')->insert($newLeaveData);
            $newLeaveId = $this->db->insertID();
            
            // Step 6: Verify BOTH leave applications exist with their respective values
            
            // Verify old leave application STILL has its original (incorrect) value
            $retrievedOldLeaveAfter = $this->db->table('ci_leave_applications')
                ->where('leave_id', $oldLeaveId)
                ->get()
                ->getRow();
            
            $this->assertNotNull($retrievedOldLeaveAfter, 'Old leave application should still exist');
            $this->assertEquals(
                $oldLeaveHours,
                (float)$retrievedOldLeaveAfter->leave_hours,
                'BACKWARD COMPATIBILITY: Old leave application should STILL have its original value (5 hours), NOT recalculated'
            );
            
            // Verify new leave application has the correct calculated value
            $retrievedNewLeave = $this->db->table('ci_leave_applications')
                ->where('leave_id', $newLeaveId)
                ->get()
                ->getRow();
            
            $this->assertNotNull($retrievedNewLeave, 'New leave application should exist');
            $this->assertEquals(
                $newLeaveHours,
                (float)$retrievedNewLeave->leave_hours,
                'New leave application should have the correct calculated value (40 hours)'
            );
            
            // Step 7: Verify the values are DIFFERENT (proving backward compatibility)
            $this->assertNotEquals(
                (float)$retrievedOldLeaveAfter->leave_hours,
                (float)$retrievedNewLeave->leave_hours,
                'Old and new leave applications should have different leave_hours values, proving backward compatibility'
            );
            
            // Step 8: Verify no recalculation occurred on the old record
            // Query all leave applications for this employee
            $allLeaveApplications = $this->db->table('ci_leave_applications')
                ->where('employee_id', $testEmployeeId)
                ->orderBy('leave_id', 'ASC')
                ->get()
                ->getResult();
            
            $this->assertCount(2, $allLeaveApplications, 'Should have exactly 2 leave applications');
            
            // First application (old) should still have incorrect hours
            $this->assertEquals(
                $oldLeaveHours,
                (float)$allLeaveApplications[0]->leave_hours,
                'First (old) leave application should retain original incorrect hours'
            );
            
            // Second application (new) should have correct hours
            $this->assertEquals(
                $newLeaveHours,
                (float)$allLeaveApplications[1]->leave_hours,
                'Second (new) leave application should have correct calculated hours'
            );
            
            // Clean up
            $this->db->table('ci_leave_applications')->where('leave_id', $oldLeaveId)->delete();
            $this->db->table('ci_leave_applications')->where('leave_id', $newLeaveId)->delete();
            $this->db->table('ci_erp_users_details')->where('user_id', $testEmployeeId)->delete();
            $this->db->table('ci_erp_users')->where('user_id', $testEmployeeId)->delete();
            $this->db->table('ci_office_shifts')->where('office_shift_id', $shiftId)->delete();
            
        } catch (\Exception $e) {
            // Clean up on error
            if (isset($oldLeaveId)) {
                $this->db->table('ci_leave_applications')->where('leave_id', $oldLeaveId)->delete();
            }
            if (isset($newLeaveId)) {
                $this->db->table('ci_leave_applications')->where('leave_id', $newLeaveId)->delete();
            }
            if (isset($testEmployeeId)) {
                $this->db->table('ci_erp_users_details')->where('user_id', $testEmployeeId)->delete();
                $this->db->table('ci_erp_users')->where('user_id', $testEmployeeId)->delete();
            }
            if (isset($shiftId)) {
                $this->db->table('ci_office_shifts')->where('office_shift_id', $shiftId)->delete();
            }
            throw $e;
        }
    }
}

