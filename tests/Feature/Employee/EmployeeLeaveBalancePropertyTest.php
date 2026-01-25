<?php

namespace Tests\Feature\Employee;

use Tests\TestCase;
use App\Models\User;
use App\Services\EmployeeManagementService;
use App\Services\SimplePermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

/**
 * Property-Based Tests for Employee Leave Balance Functionality
 * 
 * Tests Property 15: تضمين رصيد الإجازات
 * Verifies Requirement 3.4: يجب أن يتضمن الملف الشخصي للموظف رصيد الإجازات المتاح
 */
class EmployeeLeaveBalancePropertyTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private EmployeeManagementService $employeeService;
    private SimplePermissionService $permissionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->employeeService = app(EmployeeManagementService::class);
        $this->permissionService = app(SimplePermissionService::class);
    }

    /**
     * Property 15: تضمين رصيد الإجازات
     * 
     * خاصية: لكل موظف، يجب أن تحتوي استجابة getEmployeeLeaveBalance على:
     * 1. معلومات الموظف الأساسية
     * 2. أنواع الإجازات مع الأرصدة المفصلة
     * 3. ملخص إحصائي شامل
     * 4. سجل الإجازات الأخيرة
     * 5. حسابات صحيحة للأرصدة المتبقية
     * 
     * @test
     */
    public function property_employee_leave_balance_structure()
    {
        // Property-based testing with 100 iterations
        for ($i = 0; $i < 100; $i++) {
            // Arrange: Create test users with different genders
            $companyOwner = User::factory()->create([
                'first_name' => 'مدير',
                'last_name' => 'الشركة',
                'username' => 'company_owner_' . $i,
                'user_type' => 'company',
                'company_id' => 0,
                'user_role_id' => 0,
                'is_active' => 1
            ]);

            $gender = $i % 2 === 0 ? 'M' : 'F'; // Alternate between male and female
            $employee = User::factory()->create([
                'first_name' => 'موظف',
                'last_name' => 'تجريبي_' . $i,
                'username' => 'emp_' . $i . '_' . time(),
                'user_type' => 'staff',
                'company_id' => $companyOwner->user_id,
                'user_role_id' => 1,
                'is_active' => 1,
                'gender' => $gender
            ]);

            // Create user details for the employee
            \App\Models\UserDetails::factory()->create([
                'company_id' => $companyOwner->user_id,
                'user_id' => $employee->user_id,
                'employee_id' => 'EMP' . str_pad($employee->user_id, 4, '0', STR_PAD_LEFT),
                'reporting_manager' => $companyOwner->user_id,
            ]);

            // Act: Get employee leave balance
            $result = $this->employeeService->getEmployeeLeaveBalance($companyOwner, $employee->user_id);

            // Assert: Property verification
            $this->assertNotNull($result, "Leave balance result should not be null for iteration {$i}");
            $this->assertIsArray($result, "Leave balance result should be an array for iteration {$i}");

            // Property 1: Employee information must be included
            $this->assertArrayHasKey('employee', $result, "Employee info must be included for iteration {$i}");
            $this->assertArrayHasKey('id', $result['employee'], "Employee ID must be included for iteration {$i}");
            $this->assertArrayHasKey('name', $result['employee'], "Employee name must be included for iteration {$i}");
            $this->assertArrayHasKey('employee_id', $result['employee'], "Employee ID number must be included for iteration {$i}");

            // Property 2: Year must be included and valid
            $this->assertArrayHasKey('year', $result, "Year must be included for iteration {$i}");
            $this->assertEquals(now()->year, $result['year'], "Year must be current year for iteration {$i}");

            // Property 3: Leave types must be included
            $this->assertArrayHasKey('leave_types', $result, "Leave types must be included for iteration {$i}");
            $this->assertIsArray($result['leave_types'], "Leave types must be an array for iteration {$i}");

            // Property 4: Required leave types must exist
            $requiredLeaveTypes = ['annual_leave', 'sick_leave', 'emergency_leave', 'maternity_leave', 'paternity_leave'];
            foreach ($requiredLeaveTypes as $leaveType) {
                $this->assertArrayHasKey($leaveType, $result['leave_types'], 
                    "Leave type {$leaveType} must exist for iteration {$i}");
            }

            // Property 5: Each leave type must have required fields
            foreach ($result['leave_types'] as $leaveTypeName => $leaveType) {
                $this->assertArrayHasKey('name', $leaveType, "Leave type name required for {$leaveTypeName} in iteration {$i}");
                $this->assertArrayHasKey('total', $leaveType, "Total days required for {$leaveTypeName} in iteration {$i}");
                $this->assertArrayHasKey('used', $leaveType, "Used days required for {$leaveTypeName} in iteration {$i}");
                $this->assertArrayHasKey('pending', $leaveType, "Pending days required for {$leaveTypeName} in iteration {$i}");
                $this->assertArrayHasKey('remaining', $leaveType, "Remaining days required for {$leaveTypeName} in iteration {$i}");

                // Property 6: Numeric values must be non-negative
                $this->assertGreaterThanOrEqual(0, $leaveType['total'], 
                    "Total days must be non-negative for {$leaveTypeName} in iteration {$i}");
                $this->assertGreaterThanOrEqual(0, $leaveType['used'], 
                    "Used days must be non-negative for {$leaveTypeName} in iteration {$i}");
                $this->assertGreaterThanOrEqual(0, $leaveType['pending'], 
                    "Pending days must be non-negative for {$leaveTypeName} in iteration {$i}");
                $this->assertGreaterThanOrEqual(0, $leaveType['remaining'], 
                    "Remaining days must be non-negative for {$leaveTypeName} in iteration {$i}");

                // Property 7: Remaining calculation must be correct
                $expectedRemaining = max(0, $leaveType['total'] - $leaveType['used'] - $leaveType['pending']);
                $this->assertEquals($expectedRemaining, $leaveType['remaining'], 
                    "Remaining calculation must be correct for {$leaveTypeName} in iteration {$i}");

                // Property 8: Used + Pending should not exceed Total
                $this->assertLessThanOrEqual($leaveType['total'], $leaveType['used'] + $leaveType['pending'], 
                    "Used + Pending should not exceed Total for {$leaveTypeName} in iteration {$i}");
            }

            // Property 9: Gender-specific leave types validation
            if ($gender === 'F') {
                $this->assertGreaterThan(0, $result['leave_types']['maternity_leave']['total'], 
                    "Female employees should have maternity leave for iteration {$i}");
                $this->assertEquals(0, $result['leave_types']['paternity_leave']['total'], 
                    "Female employees should not have paternity leave for iteration {$i}");
            } else {
                $this->assertEquals(0, $result['leave_types']['maternity_leave']['total'], 
                    "Male employees should not have maternity leave for iteration {$i}");
                $this->assertGreaterThan(0, $result['leave_types']['paternity_leave']['total'], 
                    "Male employees should have paternity leave for iteration {$i}");
            }

            // Property 10: Summary must be included with correct calculations
            $this->assertArrayHasKey('summary', $result, "Summary must be included for iteration {$i}");
            $summary = $result['summary'];
            
            $this->assertArrayHasKey('total_allocated', $summary, "Total allocated must be included for iteration {$i}");
            $this->assertArrayHasKey('total_used', $summary, "Total used must be included for iteration {$i}");
            $this->assertArrayHasKey('total_pending', $summary, "Total pending must be included for iteration {$i}");
            $this->assertArrayHasKey('total_remaining', $summary, "Total remaining must be included for iteration {$i}");
            $this->assertArrayHasKey('utilization_rate', $summary, "Utilization rate must be included for iteration {$i}");

            // Property 11: Summary calculations must be correct
            $expectedTotalAllocated = array_sum(array_column($result['leave_types'], 'total'));
            $expectedTotalUsed = array_sum(array_column($result['leave_types'], 'used'));
            $expectedTotalPending = array_sum(array_column($result['leave_types'], 'pending'));
            $expectedTotalRemaining = array_sum(array_column($result['leave_types'], 'remaining'));

            $this->assertEquals($expectedTotalAllocated, $summary['total_allocated'], 
                "Total allocated calculation must be correct for iteration {$i}");
            $this->assertEquals($expectedTotalUsed, $summary['total_used'], 
                "Total used calculation must be correct for iteration {$i}");
            $this->assertEquals($expectedTotalPending, $summary['total_pending'], 
                "Total pending calculation must be correct for iteration {$i}");
            $this->assertEquals($expectedTotalRemaining, $summary['total_remaining'], 
                "Total remaining calculation must be correct for iteration {$i}");

            // Property 12: Utilization rate must be valid percentage
            $expectedUtilizationRate = $expectedTotalAllocated > 0 ? 
                round(($expectedTotalUsed / $expectedTotalAllocated) * 100, 1) : 0;
            $this->assertEquals($expectedUtilizationRate, $summary['utilization_rate'], 
                "Utilization rate calculation must be correct for iteration {$i}");
            $this->assertGreaterThanOrEqual(0, $summary['utilization_rate'], 
                "Utilization rate must be non-negative for iteration {$i}");
            $this->assertLessThanOrEqual(100, $summary['utilization_rate'], 
                "Utilization rate must not exceed 100% for iteration {$i}");

            // Property 13: Recent leaves must be included
            $this->assertArrayHasKey('recent_leaves', $result, "Recent leaves must be included for iteration {$i}");
            $this->assertIsArray($result['recent_leaves'], "Recent leaves must be an array for iteration {$i}");

            // Property 14: Each recent leave must have required fields
            foreach ($result['recent_leaves'] as $leaveIndex => $leave) {
                $this->assertArrayHasKey('type', $leave, "Leave type required for leave {$leaveIndex} in iteration {$i}");
                $this->assertArrayHasKey('start_date', $leave, "Start date required for leave {$leaveIndex} in iteration {$i}");
                $this->assertArrayHasKey('end_date', $leave, "End date required for leave {$leaveIndex} in iteration {$i}");
                $this->assertArrayHasKey('days', $leave, "Days required for leave {$leaveIndex} in iteration {$i}");
                $this->assertArrayHasKey('status', $leave, "Status required for leave {$leaveIndex} in iteration {$i}");
                $this->assertArrayHasKey('reason', $leave, "Reason required for leave {$leaveIndex} in iteration {$i}");

                // Property 15: Leave dates must be valid
                $this->assertNotFalse(\DateTime::createFromFormat('Y-m-d', $leave['start_date']), 
                    "Start date must be valid for leave {$leaveIndex} in iteration {$i}");
                $this->assertNotFalse(\DateTime::createFromFormat('Y-m-d', $leave['end_date']), 
                    "End date must be valid for leave {$leaveIndex} in iteration {$i}");

                // Property 16: End date must be after or equal to start date
                $startDate = \DateTime::createFromFormat('Y-m-d', $leave['start_date']);
                $endDate = \DateTime::createFromFormat('Y-m-d', $leave['end_date']);
                $this->assertLessThanOrEqual($endDate, $startDate, 
                    "Start date must be before or equal to end date for leave {$leaveIndex} in iteration {$i}");
            }
        }
    }

    /**
     * Property: Permission-based access control for leave balance
     * 
     * خاصية: يجب أن يتم التحقق من الصلاحيات قبل إرجاع رصيد الإجازات
     * 
     * @test
     */
    public function property_leave_balance_permission_based_access()
    {
        // Property-based testing with 50 iterations
        for ($i = 0; $i < 50; $i++) {
            // Arrange: Create company owner and employees
            $companyOwner = User::factory()->create([
                'first_name' => 'مدير',
                'last_name' => 'الشركة',
                'username' => 'company_owner_' . $i,
                'user_type' => 'company',
                'company_id' => 0,
                'user_role_id' => 0,
                'is_active' => 1
            ]);

            $employee1 = User::factory()->create([
                'first_name' => 'موظف',
                'last_name' => 'أول_' . $i,
                'username' => 'emp1_' . $i . '_' . time(),
                'user_type' => 'staff',
                'company_id' => $companyOwner->user_id,
                'user_role_id' => 1,
                'is_active' => 1,
                'gender' => 'M'
            ]);

            $employee2 = User::factory()->create([
                'first_name' => 'موظف',
                'last_name' => 'ثاني_' . $i,
                'username' => 'emp2_' . $i . '_' . time(),
                'user_type' => 'staff',
                'company_id' => $companyOwner->user_id,
                'user_role_id' => 1,
                'is_active' => 1,
                'gender' => 'F'
            ]);

            // Create user details
            foreach ([$employee1, $employee2] as $emp) {
                \App\Models\UserDetails::factory()->create([
                    'company_id' => $companyOwner->user_id,
                    'user_id' => $emp->user_id,
                    'employee_id' => 'EMP' . str_pad($emp->user_id, 4, '0', STR_PAD_LEFT),
                    'reporting_manager' => $companyOwner->user_id,
                ]);
            }

            // Property 1: Company owner can access any employee's leave balance
            $result = $this->employeeService->getEmployeeLeaveBalance($companyOwner, $employee1->user_id);
            $this->assertNotNull($result, "Company owner should access employee leave balance for iteration {$i}");

            // Property 2: Employee can access their own leave balance
            $result = $this->employeeService->getEmployeeLeaveBalance($employee1, $employee1->user_id);
            $this->assertNotNull($result, "Employee should access their own leave balance for iteration {$i}");

            // Property 3: Employee cannot access other employee's leave balance (without proper hierarchy)
            $result = $this->employeeService->getEmployeeLeaveBalance($employee1, $employee2->user_id);
            $this->assertNull($result, "Employee should not access other employee's leave balance for iteration {$i}");

            // Property 4: Non-existent employee returns null
            $nonExistentId = 999999;
            $result = $this->employeeService->getEmployeeLeaveBalance($companyOwner, $nonExistentId);
            $this->assertNull($result, "Non-existent employee should return null for iteration {$i}");
        }
    }

    /**
     * Property: Leave balance data consistency and business rules
     * 
     * خاصية: يجب أن تكون بيانات رصيد الإجازات متسقة ومتوافقة مع القواعد التجارية
     * 
     * @test
     */
    public function property_leave_balance_business_rules()
    {
        // Property-based testing with 30 iterations
        for ($i = 0; $i < 30; $i++) {
            // Arrange
            $companyOwner = User::factory()->create([
                'first_name' => 'مدير',
                'last_name' => 'الشركة',
                'username' => 'company_owner_' . $i,
                'user_type' => 'company',
                'company_id' => 0,
                'user_role_id' => 0,
                'is_active' => 1
            ]);

            $employee = User::factory()->create([
                'first_name' => 'موظف',
                'last_name' => 'تجريبي_' . $i,
                'username' => 'test_emp_' . $i . '_' . time(),
                'user_type' => 'staff',
                'company_id' => $companyOwner->user_id,
                'user_role_id' => 1,
                'is_active' => 1,
                'gender' => $i % 2 === 0 ? 'M' : 'F'
            ]);

            \App\Models\UserDetails::factory()->create([
                'company_id' => $companyOwner->user_id,
                'user_id' => $employee->user_id,
                'employee_id' => 'EMP' . str_pad($employee->user_id, 4, '0', STR_PAD_LEFT),
                'reporting_manager' => $companyOwner->user_id,
            ]);

            // Act
            $result = $this->employeeService->getEmployeeLeaveBalance($companyOwner, $employee->user_id);

            // Assert: Business rules validation
            $this->assertNotNull($result, "Result should not be null for iteration {$i}");

            // Property 1: Standard leave types must have expected totals
            $this->assertEquals(30, $result['leave_types']['annual_leave']['total'], 
                "Annual leave should be 30 days for iteration {$i}");
            $this->assertEquals(15, $result['leave_types']['sick_leave']['total'], 
                "Sick leave should be 15 days for iteration {$i}");
            $this->assertEquals(5, $result['leave_types']['emergency_leave']['total'], 
                "Emergency leave should be 5 days for iteration {$i}");

            // Property 2: Gender-specific leave allocation rules
            if ($employee->gender === 'F') {
                $this->assertEquals(90, $result['leave_types']['maternity_leave']['total'], 
                    "Maternity leave should be 90 days for female employees in iteration {$i}");
                $this->assertEquals(0, $result['leave_types']['paternity_leave']['total'], 
                    "Paternity leave should be 0 for female employees in iteration {$i}");
            } else {
                $this->assertEquals(0, $result['leave_types']['maternity_leave']['total'], 
                    "Maternity leave should be 0 for male employees in iteration {$i}");
                $this->assertEquals(7, $result['leave_types']['paternity_leave']['total'], 
                    "Paternity leave should be 7 days for male employees in iteration {$i}");
            }

            // Property 3: Leave names must be in Arabic
            $expectedNames = [
                'annual_leave' => 'الإجازة السنوية',
                'sick_leave' => 'الإجازة المرضية',
                'emergency_leave' => 'الإجازة الطارئة',
                'maternity_leave' => 'إجازة الأمومة',
                'paternity_leave' => 'إجازة الأبوة'
            ];

            foreach ($expectedNames as $leaveType => $expectedName) {
                $this->assertEquals($expectedName, $result['leave_types'][$leaveType]['name'], 
                    "Leave type {$leaveType} name must be in Arabic for iteration {$i}");
            }

            // Property 4: Employee ID format must be consistent
            $expectedEmployeeId = 'EMP' . str_pad($employee->user_id, 4, '0', STR_PAD_LEFT);
            $this->assertEquals($expectedEmployeeId, $result['employee']['employee_id'], 
                "Employee ID format must be consistent for iteration {$i}");

            // Property 5: Summary totals must equal sum of individual leave types
            $calculatedTotalAllocated = 0;
            $calculatedTotalUsed = 0;
            $calculatedTotalPending = 0;
            $calculatedTotalRemaining = 0;

            foreach ($result['leave_types'] as $leaveType) {
                $calculatedTotalAllocated += $leaveType['total'];
                $calculatedTotalUsed += $leaveType['used'];
                $calculatedTotalPending += $leaveType['pending'];
                $calculatedTotalRemaining += $leaveType['remaining'];
            }

            $this->assertEquals($calculatedTotalAllocated, $result['summary']['total_allocated'], 
                "Summary total allocated must equal sum for iteration {$i}");
            $this->assertEquals($calculatedTotalUsed, $result['summary']['total_used'], 
                "Summary total used must equal sum for iteration {$i}");
            $this->assertEquals($calculatedTotalPending, $result['summary']['total_pending'], 
                "Summary total pending must equal sum for iteration {$i}");
            $this->assertEquals($calculatedTotalRemaining, $result['summary']['total_remaining'], 
                "Summary total remaining must equal sum for iteration {$i}");
        }
    }
}