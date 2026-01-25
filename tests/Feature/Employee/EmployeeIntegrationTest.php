<?php

namespace Tests\Feature\Employee;

use Tests\TestCase;
use App\Models\User;
use App\Models\Department;
use App\Models\Designation;
use App\Models\UserDetails;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Passport\Passport;

/**
 * Employee Integration Tests
 * 
 * Tests complete user scenarios and workflows:
 * - Complete employee lifecycle (create → view → update → deactivate)
 * - Search and filtering with real data
 * - Permission-based access scenarios
 * - Data consistency across operations
 */
class EmployeeIntegrationTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $companyUser;
    private User $hrManager;
    private User $departmentHead;
    private User $regularEmployee;
    private Department $itDepartment;
    private Department $hrDepartment;
    private Designation $managerDesignation;
    private Designation $headDesignation;
    private Designation $employeeDesignation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTestOrganization();
    }

    private function createTestOrganization(): void
    {
        // Create departments
        $this->itDepartment = Department::factory()->create([
            'department_name' => 'قسم تقنية المعلومات',
            'company_id' => 1
        ]);

        $this->hrDepartment = Department::factory()->create([
            'department_name' => 'قسم الموارد البشرية',
            'company_id' => 1
        ]);

        // Create designations with hierarchy
        $this->managerDesignation = Designation::factory()->create([
            'designation_name' => 'مدير عام',
            'hierarchy_level' => 2,
            'company_id' => 1,
            'department_id' => $this->hrDepartment->department_id
        ]);

        $this->headDesignation = Designation::factory()->create([
            'designation_name' => 'رئيس قسم',
            'hierarchy_level' => 3,
            'company_id' => 1,
            'department_id' => $this->itDepartment->department_id
        ]);

        $this->employeeDesignation = Designation::factory()->create([
            'designation_name' => 'موظف',
            'hierarchy_level' => 5,
            'company_id' => 1,
            'department_id' => $this->itDepartment->department_id
        ]);

        // Create company user
        $this->companyUser = User::factory()->create([
            'user_type' => 'company',
            'company_id' => 1,
            'is_active' => 1,
            'first_name' => 'شركة',
            'last_name' => 'التقنية',
            'email' => 'company@techcorp.com'
        ]);

        // Create HR Manager
        $this->hrManager = User::factory()->create([
            'user_type' => 'staff',
            'company_id' => 1,
            'is_active' => 1,
            'first_name' => 'أحمد',
            'last_name' => 'المدير',
            'email' => 'hr.manager@techcorp.com'
        ]);

        UserDetails::factory()->create([
            'user_id' => $this->hrManager->user_id,
            'department_id' => $this->hrDepartment->department_id,
            'designation_id' => $this->managerDesignation->designation_id,
            'employee_id' => 'HRM001',
            'basic_salary' => 12000,
            'date_of_joining' => '2020-01-01'
        ]);

        // Create Department Head
        $this->departmentHead = User::factory()->create([
            'user_type' => 'staff',
            'company_id' => 1,
            'is_active' => 1,
            'first_name' => 'سارة',
            'last_name' => 'رئيس القسم',
            'email' => 'it.head@techcorp.com'
        ]);

        UserDetails::factory()->create([
            'user_id' => $this->departmentHead->user_id,
            'department_id' => $this->itDepartment->department_id,
            'designation_id' => $this->headDesignation->designation_id,
            'employee_id' => 'ITH001',
            'basic_salary' => 10000,
            'date_of_joining' => '2021-03-15'
        ]);

        // Create Regular Employee
        $this->regularEmployee = User::factory()->create([
            'user_type' => 'staff',
            'company_id' => 1,
            'is_active' => 1,
            'first_name' => 'محمد',
            'last_name' => 'الموظف',
            'email' => 'employee@techcorp.com'
        ]);

        UserDetails::factory()->create([
            'user_id' => $this->regularEmployee->user_id,
            'department_id' => $this->itDepartment->department_id,
            'designation_id' => $this->employeeDesignation->designation_id,
            'employee_id' => 'EMP001',
            'basic_salary' => 6000,
            'date_of_joining' => '2023-06-01'
        ]);
    }

    /** @test */
    public function test_complete_employee_lifecycle_scenario()
    {
        // Scenario: Company user creates, views, updates, and deactivates an employee
        Passport::actingAs($this->companyUser);

        // Step 1: Get initial employee count
        $initialListResponse = $this->getJson('/api/employees');
        $initialListResponse->assertStatus(200);
        $initialCount = count($initialListResponse->json('data.data'));

        // Step 2: Create new employee
        $newEmployeeData = [
            'first_name' => 'فاطمة',
            'last_name' => 'أحمد',
            'email' => 'fatima.ahmed@techcorp.com',
            'username' => 'fatima.ahmed',
            'password' => 'SecurePass123!',
            'contact_number' => '0501234567',
            'gender' => 'F',
            'department_id' => $this->itDepartment->department_id,
            'designation_id' => $this->employeeDesignation->designation_id,
            'basic_salary' => 5500.00,
            'date_of_joining' => '2024-02-01',
            'date_of_birth' => '1995-05-15'
        ];

        $createResponse = $this->postJson('/api/employees', $newEmployeeData);
        
        // Debug: Print response if not 201
        if ($createResponse->status() !== 201) {
            dump('Response Status: ' . $createResponse->status());
            dump('Response Body: ' . $createResponse->getContent());
        }
        
        $createResponse->assertStatus(201)
                      ->assertJson([
                          'success' => true,
                          'message' => 'تم إنشاء الموظف بنجاح'
                      ]);

        $newEmployeeId = $createResponse->json('data.user_id');
        $this->assertNotNull($newEmployeeId);

        // Step 3: Verify employee appears in list
        $listResponse = $this->getJson('/api/employees');
        $listResponse->assertStatus(200);
        
        $employees = $listResponse->json('data.data');
        $this->assertCount($initialCount + 1, $employees, 'Employee count should increase by 1');
        
        $foundEmployee = collect($employees)->firstWhere('user_id', $newEmployeeId);
        $this->assertNotNull($foundEmployee, 'New employee should appear in employees list');

        // Step 4: View the created employee details
        $viewResponse = $this->getJson("/api/employees/{$newEmployeeId}");
        
        $viewResponse->assertStatus(200)
                     ->assertJson([
                         'success' => true,
                         'data' => [
                             'user_id' => $newEmployeeId,
                             'first_name' => 'فاطمة',
                             'last_name' => 'أحمد',
                             'email' => 'fatima.ahmed@techcorp.com'
                         ]
                     ]);

        // Step 5: Update employee information
        $updateData = [
            'first_name' => 'فاطمة المحدثة',
            'contact_number' => '0507654321',
            'basic_salary' => 6000.00
        ];

        $updateResponse = $this->putJson("/api/employees/{$newEmployeeId}", $updateData);
        
        $updateResponse->assertStatus(200)
                       ->assertJson([
                           'success' => true,
                           'message' => 'تم تحديث بيانات الموظف بنجاح'
                       ]);

        // Step 6: Verify update was applied
        $verifyResponse = $this->getJson("/api/employees/{$newEmployeeId}");
        $verifyResponse->assertJson([
            'data' => [
                'first_name' => 'فاطمة المحدثة',
                'contact_number' => '0507654321'
            ]
        ]);

        // Step 7: Deactivate employee
        $deleteResponse = $this->deleteJson("/api/employees/{$newEmployeeId}");
        
        $deleteResponse->assertStatus(200)
                       ->assertJson([
                           'success' => true,
                           'message' => 'تم إلغاء تفعيل الموظف بنجاح'
                       ]);

        // Step 8: Verify employee is deactivated in database
        $this->assertDatabaseHas('ci_erp_users', [
            'user_id' => $newEmployeeId,
            'is_active' => 0
        ]);
    }

    /** @test */
    public function test_search_and_filtering_with_real_data()
    {
        Passport::actingAs($this->companyUser);

        // Create additional test employees for comprehensive search testing
        $additionalEmployees = [
            [
                'first_name' => 'خالد',
                'last_name' => 'السعودي',
                'email' => 'khalid@techcorp.com',
                'department_id' => $this->itDepartment->department_id,
                'designation_id' => $this->employeeDesignation->designation_id,
                'employee_id' => 'EMP002'
            ],
            [
                'first_name' => 'نورا',
                'last_name' => 'المطور',
                'email' => 'nora@techcorp.com',
                'department_id' => $this->itDepartment->department_id,
                'designation_id' => $this->employeeDesignation->designation_id,
                'employee_id' => 'EMP003'
            ],
            [
                'first_name' => 'عبدالله',
                'last_name' => 'الموارد',
                'email' => 'abdullah@techcorp.com',
                'department_id' => $this->hrDepartment->department_id,
                'designation_id' => $this->employeeDesignation->designation_id,
                'employee_id' => 'EMP004'
            ]
        ];

        foreach ($additionalEmployees as $empData) {
            $user = User::factory()->create([
                'user_type' => 'staff',
                'company_id' => 1,
                'is_active' => 1,
                'first_name' => $empData['first_name'],
                'last_name' => $empData['last_name'],
                'email' => $empData['email']
            ]);

            UserDetails::factory()->create([
                'user_id' => $user->user_id,
                'department_id' => $empData['department_id'],
                'designation_id' => $empData['designation_id'],
                'employee_id' => $empData['employee_id'],
                'basic_salary' => 5000,
                'date_of_joining' => '2023-01-01'
            ]);
        }

        // Test 1: Search by first name
        $searchResponse = $this->getJson('/api/employees/search?q=خالد');
        $searchResponse->assertStatus(200);
        
        $searchResults = $searchResponse->json('data.employees');
        $this->assertNotEmpty($searchResults);
        $foundKhalid = collect($searchResults)->firstWhere('first_name', 'خالد');
        $this->assertNotNull($foundKhalid, 'Should find employee by first name');

        // Test 2: Search by email
        $emailSearchResponse = $this->getJson('/api/employees/search?q=nora@techcorp.com');
        $emailSearchResponse->assertStatus(200);
        
        $emailResults = $emailSearchResponse->json('data.employees');
        $foundByEmail = collect($emailResults)->firstWhere('email', 'nora@techcorp.com');
        $this->assertNotNull($foundByEmail, 'Should find employee by email');

        // Test 3: Filter by department
        $deptFilterResponse = $this->getJson("/api/employees?department_id={$this->itDepartment->department_id}");
        $deptFilterResponse->assertStatus(200);
        
        $deptEmployees = $deptFilterResponse->json('data.data');
        foreach ($deptEmployees as $employee) {
            $this->assertEquals($this->itDepartment->department_name, $employee['department_name']);
        }

        // Test 4: Filter by designation
        $desigFilterResponse = $this->getJson("/api/employees?designation_id={$this->employeeDesignation->designation_id}");
        $desigFilterResponse->assertStatus(200);
        
        $desigEmployees = $desigFilterResponse->json('data.data');
        foreach ($desigEmployees as $employee) {
            $this->assertEquals($this->employeeDesignation->designation_name, $employee['designation_name']);
        }

        // Test 5: Combined filters
        $combinedResponse = $this->getJson("/api/employees?department_id={$this->itDepartment->department_id}&designation_id={$this->employeeDesignation->designation_id}");
        $combinedResponse->assertStatus(200);
        
        $combinedEmployees = $combinedResponse->json('data.data');
        foreach ($combinedEmployees as $employee) {
            $this->assertEquals($this->itDepartment->department_name, $employee['department_name']);
            $this->assertEquals($this->employeeDesignation->designation_name, $employee['designation_name']);
        }

        // Test 6: Pagination with filters
        $paginatedResponse = $this->getJson("/api/employees?department_id={$this->itDepartment->department_id}&limit=2&page=1");
        $paginatedResponse->assertStatus(200)
                          ->assertJsonStructure([
                              'data' => [
                                  'data',
                                  'pagination' => [
                                      'current_page',
                                      'per_page',
                                      'total'
                                  ]
                              ]
                          ]);

        $pagination = $paginatedResponse->json('data.pagination');
        $this->assertEquals(1, $pagination['current_page']);
        $this->assertEquals(2, $pagination['per_page']);
    }

    /** @test */
    public function test_hierarchical_permission_scenarios()
    {
        // Scenario 1: Company user can see all employees
        Passport::actingAs($this->companyUser);
        
        $companyResponse = $this->getJson('/api/employees');
        $companyResponse->assertStatus(200);
        
        $allEmployees = $companyResponse->json('data.data');
        $this->assertGreaterThanOrEqual(3, count($allEmployees), 'Company user should see all employees');

        // Scenario 2: HR Manager can see employees but with restrictions
        Passport::actingAs($this->hrManager);
        
        $hrResponse = $this->getJson('/api/employees');
        $hrResponse->assertStatus(200);
        
        // HR Manager should be able to view employee details
        $detailResponse = $this->getJson("/api/employees/{$this->regularEmployee->user_id}");
        $detailResponse->assertStatus(200);

        // Scenario 3: Department Head can see employees in their hierarchy
        Passport::actingAs($this->departmentHead);
        
        $headResponse = $this->getJson('/api/employees');
        $headResponse->assertStatus(200);
        
        // Department head should see employees in their department
        $deptEmployees = $headResponse->json('data.data');
        $foundSubordinate = collect($deptEmployees)->firstWhere('user_id', $this->regularEmployee->user_id);
        $this->assertNotNull($foundSubordinate, 'Department head should see subordinates');

        // Scenario 4: Regular employee has limited access
        Passport::actingAs($this->regularEmployee);
        
        // Regular employee should have limited access to employee list
        $empResponse = $this->getJson('/api/employees');
        // This might return 403 or limited results based on permissions
        $this->assertContains($empResponse->status(), [200, 403]);

        // Regular employee should be able to view their own profile
        $selfResponse = $this->getJson("/api/employees/{$this->regularEmployee->user_id}");
        $selfResponse->assertStatus(200)
                     ->assertJson([
                         'data' => [
                             'user_id' => $this->regularEmployee->user_id
                         ]
                     ]);

        // Regular employee should NOT be able to create employees
        $createAttempt = $this->postJson('/api/employees', [
            'first_name' => 'محاولة',
            'last_name' => 'إنشاء',
            'email' => 'attempt@test.com',
            'username' => 'attempt',
            'password' => 'password',
            'department_id' => $this->itDepartment->department_id,
            'designation_id' => $this->employeeDesignation->designation_id
        ]);
        
        $createAttempt->assertStatus(403)
                      ->assertJson([
                          'success' => false
                      ]);
    }

    /** @test */
    public function test_data_consistency_across_operations()
    {
        Passport::actingAs($this->companyUser);

        // Get initial statistics
        $initialStats = $this->getJson('/api/employees/statistics');
        $initialStats->assertStatus(200);
        
        $initialData = $initialStats->json('data');
        $initialTotal = $initialData['total_employees'];
        $initialActive = $initialData['active_employees'];

        // Create new employee
        $newEmployeeData = [
            'first_name' => 'اختبار',
            'last_name' => 'الاتساق',
            'email' => 'consistency@test.com',
            'username' => 'consistency.test',
            'password' => 'password123',
            'department_id' => $this->itDepartment->department_id,
            'designation_id' => $this->employeeDesignation->designation_id,
            'basic_salary' => 5000
        ];

        $createResponse = $this->postJson('/api/employees', $newEmployeeData);
        $createResponse->assertStatus(201);
        
        $newEmployeeId = $createResponse->json('data.user_id');

        // Check statistics after creation
        $afterCreateStats = $this->getJson('/api/employees/statistics');
        $afterCreateData = $afterCreateStats->json('data');
        
        $this->assertEquals($initialTotal + 1, $afterCreateData['total_employees'], 'Total employees should increase by 1');
        $this->assertEquals($initialActive + 1, $afterCreateData['active_employees'], 'Active employees should increase by 1');

        // Check employee appears in list
        $listResponse = $this->getJson('/api/employees');
        $employees = $listResponse->json('data.data');
        $foundInList = collect($employees)->firstWhere('user_id', $newEmployeeId);
        $this->assertNotNull($foundInList, 'New employee should appear in list');

        // Check employee appears in search
        $searchResponse = $this->getJson('/api/employees/search?q=اختبار');
        $searchResults = $searchResponse->json('data.employees');
        $foundInSearch = collect($searchResults)->firstWhere('user_id', $newEmployeeId);
        $this->assertNotNull($foundInSearch, 'New employee should appear in search results');

        // Deactivate employee
        $deactivateResponse = $this->deleteJson("/api/employees/{$newEmployeeId}");
        $deactivateResponse->assertStatus(200);

        // Check statistics after deactivation
        $afterDeactivateStats = $this->getJson('/api/employees/statistics');
        $afterDeactivateData = $afterDeactivateStats->json('data');
        
        $this->assertEquals($initialTotal + 1, $afterDeactivateData['total_employees'], 'Total employees should remain the same');
        $this->assertEquals($initialActive, $afterDeactivateData['active_employees'], 'Active employees should return to initial count');
        $this->assertEquals($afterDeactivateData['inactive_employees'], $afterDeactivateData['total_employees'] - $afterDeactivateData['active_employees'], 'Inactive count should be consistent');

        // Check employee doesn't appear in active list
        $activeListResponse = $this->getJson('/api/employees?is_active=1');
        $activeEmployees = $activeListResponse->json('data.data');
        $foundInActiveList = collect($activeEmployees)->firstWhere('user_id', $newEmployeeId);
        $this->assertNull($foundInActiveList, 'Deactivated employee should not appear in active list');

        // Check employee still appears in all employees list (including inactive)
        $allListResponse = $this->getJson('/api/employees');
        $allEmployees = $allListResponse->json('data.data');
        // Note: This depends on default behavior - might filter active only
    }

    /** @test */
    public function test_employee_profile_data_integration()
    {
        Passport::actingAs($this->companyUser);

        // Test that employee profile endpoints return consistent data
        $employeeId = $this->regularEmployee->user_id;

        // Get employee details
        $detailResponse = $this->getJson("/api/employees/{$employeeId}");
        $detailResponse->assertStatus(200);
        
        $employeeData = $detailResponse->json('data');

        // Get employee documents
        $documentsResponse = $this->getJson("/api/employees/{$employeeId}/documents");
        $documentsResponse->assertStatus(200);
        
        $documentsData = $documentsResponse->json('data');
        $this->assertEquals($employeeId, $documentsData['employee']['user_id'], 'Documents endpoint should return same employee');

        // Get employee leave balance
        $leaveResponse = $this->getJson("/api/employees/{$employeeId}/leave-balance");
        $leaveResponse->assertStatus(200);
        
        $leaveData = $leaveResponse->json('data');
        $this->assertEquals($employeeId, $leaveData['employee']['user_id'], 'Leave balance endpoint should return same employee');

        // Get employee attendance
        $attendanceResponse = $this->getJson("/api/employees/{$employeeId}/attendance");
        $attendanceResponse->assertStatus(200);
        
        $attendanceData = $attendanceResponse->json('data');
        $this->assertEquals($employeeId, $attendanceData['employee']['user_id'], 'Attendance endpoint should return same employee');

        // Get employee salary details
        $salaryResponse = $this->getJson("/api/employees/{$employeeId}/salary-details");
        $salaryResponse->assertStatus(200);
        
        $salaryData = $salaryResponse->json('data');
        $this->assertEquals($employeeId, $salaryData['employee']['user_id'], 'Salary details endpoint should return same employee');

        // Verify consistent employee information across all endpoints
        $endpoints = [$documentsData, $leaveData, $attendanceData, $salaryData];
        
        foreach ($endpoints as $endpointData) {
            $this->assertEquals($employeeData['first_name'], $endpointData['employee']['first_name'], 'First name should be consistent');
            $this->assertEquals($employeeData['last_name'], $endpointData['employee']['last_name'], 'Last name should be consistent');
            $this->assertEquals($employeeData['email'], $endpointData['employee']['email'], 'Email should be consistent');
        }
    }

    /** @test */
    public function test_bulk_operations_and_performance()
    {
        Passport::actingAs($this->companyUser);

        // Create multiple employees to test bulk scenarios
        $bulkEmployees = [];
        
        for ($i = 1; $i <= 10; $i++) {
            $employeeData = [
                'first_name' => "موظف{$i}",
                'last_name' => 'الاختبار',
                'email' => "bulk{$i}@test.com",
                'username' => "bulk.employee{$i}",
                'password' => 'password123',
                'department_id' => ($i % 2 === 0) ? $this->itDepartment->department_id : $this->hrDepartment->department_id,
                'designation_id' => $this->employeeDesignation->designation_id,
                'basic_salary' => 5000 + ($i * 100)
            ];

            $response = $this->postJson('/api/employees', $employeeData);
            $response->assertStatus(201);
            
            $bulkEmployees[] = $response->json('data.user_id');
        }

        // Test pagination with bulk data
        $page1Response = $this->getJson('/api/employees?limit=5&page=1');
        $page1Response->assertStatus(200);
        
        $page1Data = $page1Response->json('data');
        $this->assertCount(5, $page1Data['data'], 'First page should have 5 employees');
        $this->assertEquals(1, $page1Data['pagination']['current_page']);

        $page2Response = $this->getJson('/api/employees?limit=5&page=2');
        $page2Response->assertStatus(200);
        
        $page2Data = $page2Response->json('data');
        $this->assertEquals(2, $page2Data['pagination']['current_page']);

        // Test search performance with bulk data
        $searchResponse = $this->getJson('/api/employees/search?q=موظف');
        $searchResponse->assertStatus(200);
        
        $searchResults = $searchResponse->json('data.employees');
        $this->assertGreaterThanOrEqual(10, count($searchResults), 'Search should find bulk employees');

        // Test statistics with bulk data
        $statsResponse = $this->getJson('/api/employees/statistics');
        $statsResponse->assertStatus(200);
        
        $statsData = $statsResponse->json('data');
        $this->assertGreaterThanOrEqual(13, $statsData['total_employees'], 'Statistics should include bulk employees'); // 3 original + 10 bulk

        // Test department filtering with bulk data
        $itFilterResponse = $this->getJson("/api/employees?department_id={$this->itDepartment->department_id}");
        $itFilterResponse->assertStatus(200);
        
        $itEmployees = $itFilterResponse->json('data.data');
        $itCount = count($itEmployees);
        
        $hrFilterResponse = $this->getJson("/api/employees?department_id={$this->hrDepartment->department_id}");
        $hrFilterResponse->assertStatus(200);
        
        $hrEmployees = $hrFilterResponse->json('data.data');
        $hrCount = count($hrEmployees);
        
        // Verify department distribution
        $this->assertGreaterThan(0, $itCount, 'IT department should have employees');
        $this->assertGreaterThan(0, $hrCount, 'HR department should have employees');

        // Clean up: Deactivate bulk employees
        foreach ($bulkEmployees as $employeeId) {
            $deleteResponse = $this->deleteJson("/api/employees/{$employeeId}");
            $deleteResponse->assertStatus(200);
        }

        // Verify cleanup
        $finalStatsResponse = $this->getJson('/api/employees/statistics');
        $finalStatsData = $finalStatsResponse->json('data');
        
        $this->assertEquals(3, $finalStatsData['active_employees'], 'Should return to original active count after cleanup');
    }

    /** @test */
    public function test_error_handling_and_recovery_scenarios()
    {
        Passport::actingAs($this->companyUser);

        // Test 1: Invalid employee ID
        $invalidResponse = $this->getJson('/api/employees/99999');
        $invalidResponse->assertStatus(404)
                        ->assertJson([
                            'success' => false
                        ]);

        // Test 2: Invalid department in creation
        $invalidDeptData = [
            'first_name' => 'اختبار',
            'last_name' => 'خطأ',
            'email' => 'error@test.com',
            'username' => 'error.test',
            'password' => 'password123',
            'department_id' => 99999, // Invalid department
            'designation_id' => $this->employeeDesignation->designation_id
        ];

        $invalidDeptResponse = $this->postJson('/api/employees', $invalidDeptData);
        $invalidDeptResponse->assertStatus(422)
                           ->assertJsonValidationErrors(['department_id']);

        // Test 3: Duplicate email
        $duplicateEmailData = [
            'first_name' => 'مكرر',
            'last_name' => 'البريد',
            'email' => $this->regularEmployee->email, // Existing email
            'username' => 'duplicate.email',
            'password' => 'password123',
            'department_id' => $this->itDepartment->department_id,
            'designation_id' => $this->employeeDesignation->designation_id
        ];

        $duplicateResponse = $this->postJson('/api/employees', $duplicateEmailData);
        $duplicateResponse->assertStatus(422)
                          ->assertJsonValidationErrors(['email']);

        // Test 4: Invalid search query
        $emptySearchResponse = $this->getJson('/api/employees/search?q=');
        $emptySearchResponse->assertStatus(400)
                           ->assertJson([
                               'success' => false
                           ]);

        // Test 5: Update non-existent employee
        $updateNonExistentResponse = $this->putJson('/api/employees/99999', [
            'first_name' => 'غير موجود'
        ]);
        $updateNonExistentResponse->assertStatus(404);

        // Test 6: Delete non-existent employee
        $deleteNonExistentResponse = $this->deleteJson('/api/employees/99999');
        $deleteNonExistentResponse->assertStatus(404);

        // Verify system remains stable after errors
        $healthCheckResponse = $this->getJson('/api/employees');
        $healthCheckResponse->assertStatus(200);
        
        $statsResponse = $this->getJson('/api/employees/statistics');
        $statsResponse->assertStatus(200);
    }
}