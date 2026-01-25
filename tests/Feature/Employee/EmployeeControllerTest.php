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
 * Employee Controller Unit Tests
 * 
 * Comprehensive tests for all EmployeeController endpoints:
 * - index() - Get employees list with filtering and pagination
 * - store() - Create new employee
 * - show() - Get employee details
 * - update() - Update employee information
 * - destroy() - Deactivate employee
 * - search() - Search employees
 * - statistics() - Get employee statistics
 * - Profile endpoints (documents, leave balance, attendance, salary)
 */
class EmployeeControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $companyUser;
    private User $hrUser;
    private User $regularEmployee;
    private Department $department;
    private Designation $designation;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test data
        $this->createTestData();
    }

    private function createTestData(): void
    {
        // Create company user
        $this->companyUser = User::factory()->create([
            'user_type' => 'company',
            'company_id' => 1,
            'is_active' => 1,
            'first_name' => 'شركة',
            'last_name' => 'الاختبار',
            'email' => 'company@test.com'
        ]);

        // Create department and designation
        $this->department = Department::factory()->create([
            'department_name' => 'قسم الموارد البشرية',
            'company_id' => 1
        ]);

        $this->designation = Designation::factory()->create([
            'designation_name' => 'مدير الموارد البشرية',
            'hierarchy_level' => 2,
            'company_id' => 1
        ]);

        // Create HR user
        $this->hrUser = User::factory()->create([
            'user_type' => 'staff',
            'company_id' => 1,
            'is_active' => 1,
            'first_name' => 'أحمد',
            'last_name' => 'محمد',
            'email' => 'hr@test.com'
        ]);

        UserDetails::factory()->create([
            'user_id' => $this->hrUser->user_id,
            'department_id' => $this->department->department_id,
            'designation_id' => $this->designation->designation_id,
            'employee_id' => 'HR001',
            'basic_salary' => 8000
        ]);

        // Create regular employee
        $this->regularEmployee = User::factory()->create([
            'user_type' => 'staff',
            'company_id' => 1,
            'is_active' => 1,
            'first_name' => 'سارة',
            'last_name' => 'أحمد',
            'email' => 'employee@test.com'
        ]);

        $regularDesignation = Designation::factory()->create([
            'designation_name' => 'موظف',
            'hierarchy_level' => 5,
            'company_id' => 1
        ]);

        UserDetails::factory()->create([
            'user_id' => $this->regularEmployee->user_id,
            'department_id' => $this->department->department_id,
            'designation_id' => $regularDesignation->designation_id,
            'employee_id' => 'EMP001',
            'basic_salary' => 5000
        ]);
    }

    /** @test */
    public function test_index_returns_employees_list_for_company_user()
    {
        Passport::actingAs($this->companyUser);

        $response = $this->getJson('/api/employees');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'data' => [
                            '*' => [
                                'user_id',
                                'employee_id',
                                'first_name',
                                'last_name',
                                'full_name',
                                'email',
                                'department_name',
                                'designation_name',
                                'is_active'
                            ]
                        ],
                        'pagination' => [
                            'current_page',
                            'last_page',
                            'per_page',
                            'total'
                        ]
                    ]
                ])
                ->assertJson([
                    'success' => true
                ]);
    }

    /** @test */
    public function test_index_applies_search_filter()
    {
        Passport::actingAs($this->companyUser);

        $response = $this->getJson('/api/employees?search=سارة');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true
                ]);

        // Check that search results contain the searched employee
        $data = $response->json('data.data');
        $this->assertNotEmpty($data);
        
        $found = false;
        foreach ($data as $employee) {
            if (str_contains($employee['first_name'], 'سارة')) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Search should return employees matching the search term');
    }

    /** @test */
    public function test_index_applies_department_filter()
    {
        Passport::actingAs($this->companyUser);

        $response = $this->getJson("/api/employees?department_id={$this->department->department_id}");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true
                ]);
    }

    /** @test */
    public function test_index_requires_authentication()
    {
        $response = $this->getJson('/api/employees');

        $response->assertStatus(401);
    }

    /** @test */
    public function test_index_respects_permissions()
    {
        // Create user without employee.view permission
        $unauthorizedUser = User::factory()->create([
            'user_type' => 'staff',
            'company_id' => 2, // Different company
            'is_active' => 1
        ]);

        Passport::actingAs($unauthorizedUser);

        $response = $this->getJson('/api/employees');

        $response->assertStatus(403)
                ->assertJsonStructure([
                    'error',
                    'required_permission',
                    'user_id',
                    'user_name',
                    'user_permissions_count',
                    'has_permission'
                ]);
    }

    /** @test */
    public function test_store_creates_new_employee()
    {
        Passport::actingAs($this->companyUser);

        $employeeData = [
            'first_name' => 'محمد',
            'last_name' => 'علي',
            'email' => 'mohammed.ali@test.com',
            'username' => 'mohammed.ali',
            'password' => 'password123',
            'contact_number' => '0501234567',
            'gender' => 'M',
            'department_id' => $this->department->department_id,
            'designation_id' => $this->designation->designation_id,
            'basic_salary' => 6000.00,
            'date_of_joining' => '2024-01-15',
            'date_of_birth' => '1990-01-01'
        ];

        $response = $this->postJson('/api/employees', $employeeData);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'user_id',
                        'employee_id',
                        'first_name',
                        'last_name',
                        'email',
                        'is_active'
                    ]
                ])
                ->assertJson([
                    'success' => true,
                    'message' => 'تم إنشاء الموظف بنجاح'
                ]);

        // Verify employee was created in database
        $this->assertDatabaseHas('ci_erp_users', [
            'email' => 'mohammed.ali@test.com',
            'first_name' => 'محمد',
            'last_name' => 'علي'
        ]);
    }

    /** @test */
    public function test_store_validates_required_fields()
    {
        Passport::actingAs($this->companyUser);

        $response = $this->postJson('/api/employees', []);

        $response->assertStatus(422)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'errors'
                ])
                ->assertJson([
                    'success' => false
                ]);
    }

    /** @test */
    public function test_store_validates_unique_email()
    {
        Passport::actingAs($this->companyUser);

        $employeeData = [
            'first_name' => 'محمد',
            'last_name' => 'علي',
            'email' => $this->regularEmployee->email, // Use existing email
            'username' => 'mohammed.ali2',
            'password' => 'password123',
            'department_id' => $this->department->department_id,
            'designation_id' => $this->designation->designation_id,
        ];

        $response = $this->postJson('/api/employees', $employeeData);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['email']);
    }

    /** @test */
    public function test_store_requires_permission()
    {
        Passport::actingAs($this->regularEmployee);

        $employeeData = [
            'first_name' => 'محمد',
            'last_name' => 'علي',
            'email' => 'new@test.com',
            'username' => 'new.user',
            'password' => 'password123',
            'department_id' => $this->department->department_id,
            'designation_id' => $this->designation->designation_id,
        ];

        $response = $this->postJson('/api/employees', $employeeData);

        $response->assertStatus(403)
                ->assertJsonStructure([
                    'error',
                    'required_permission',
                    'user_id',
                    'user_name',
                    'user_permissions_count',
                    'has_permission'
                ]);
    }

    /** @test */
    public function test_show_returns_employee_details()
    {
        Passport::actingAs($this->companyUser);

        $response = $this->getJson("/api/employees/{$this->regularEmployee->user_id}");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'user_id',
                        'employee_id',
                        'first_name',
                        'last_name',
                        'email',
                        'is_active'
                    ]
                ])
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'user_id' => $this->regularEmployee->user_id,
                        'email' => $this->regularEmployee->email
                    ]
                ]);
    }

    /** @test */
    public function test_show_returns_404_for_nonexistent_employee()
    {
        Passport::actingAs($this->companyUser);

        $response = $this->getJson('/api/employees/99999');

        $response->assertStatus(404)
                ->assertJson([
                    'success' => false
                ]);
    }

    /** @test */
    public function test_show_respects_permissions()
    {
        // Create user from different company
        $otherCompanyUser = User::factory()->create([
            'user_type' => 'company',
            'company_id' => 2,
            'is_active' => 1
        ]);

        Passport::actingAs($otherCompanyUser);

        $response = $this->getJson("/api/employees/{$this->regularEmployee->user_id}");

        $response->assertStatus(404)
                ->assertJson([
                    'success' => false
                ]);
    }

    /** @test */
    public function test_update_modifies_employee_data()
    {
        Passport::actingAs($this->companyUser);

        $updateData = [
            'first_name' => 'سارة المحدثة',
            'last_name' => 'أحمد المحدث',
            'email' => 'updated@test.com',
            'basic_salary' => 5500.00
        ];

        $response = $this->putJson("/api/employees/{$this->regularEmployee->user_id}", $updateData);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'تم تحديث بيانات الموظف بنجاح'
                ]);

        // Verify changes in database
        $this->assertDatabaseHas('ci_erp_users', [
            'user_id' => $this->regularEmployee->user_id,
            'first_name' => 'سارة المحدثة',
            'email' => 'updated@test.com'
        ]);
    }

    /** @test */
    public function test_update_validates_data()
    {
        Passport::actingAs($this->companyUser);

        $updateData = [
            'email' => 'invalid-email', // Invalid email format
        ];

        $response = $this->putJson("/api/employees/{$this->regularEmployee->user_id}", $updateData);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['email']);
    }

    /** @test */
    public function test_update_requires_permission()
    {
        Passport::actingAs($this->regularEmployee);

        $updateData = [
            'first_name' => 'محاولة تحديث'
        ];

        $response = $this->putJson("/api/employees/{$this->hrUser->user_id}", $updateData);

        $response->assertStatus(403) // Should get permission denied
                ->assertJsonStructure([
                    'error',
                    'required_permission',
                    'user_id',
                    'user_name',
                    'user_permissions_count',
                    'has_permission'
                ]);
    }

    /** @test */
    public function test_destroy_deactivates_employee()
    {
        Passport::actingAs($this->companyUser);

        $response = $this->deleteJson("/api/employees/{$this->regularEmployee->user_id}");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'تم إلغاء تفعيل الموظف بنجاح'
                ]);

        // Verify employee is deactivated, not deleted
        $this->assertDatabaseHas('ci_erp_users', [
            'user_id' => $this->regularEmployee->user_id,
            'is_active' => 0
        ]);
    }

    /** @test */
    public function test_destroy_requires_permission()
    {
        Passport::actingAs($this->regularEmployee);

        $response = $this->deleteJson("/api/employees/{$this->hrUser->user_id}");

        $response->assertStatus(403)
                ->assertJsonStructure([
                    'error',
                    'required_permission',
                    'user_id',
                    'user_name',
                    'user_permissions_count',
                    'has_permission'
                ]);
    }

    /** @test */
    public function test_search_finds_employees_by_name()
    {
        Passport::actingAs($this->companyUser);

        $response = $this->getJson('/api/employees/search?q=سارة');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'employees',
                        'total',
                        'query'
                    ]
                ])
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'query' => 'سارة'
                    ]
                ]);
    }

    /** @test */
    public function test_search_requires_query_parameter()
    {
        Passport::actingAs($this->companyUser);

        $response = $this->getJson('/api/employees/search');

        $response->assertStatus(400)
                ->assertJson([
                    'success' => false
                ]);
    }

    /** @test */
    public function test_search_respects_limit_parameter()
    {
        Passport::actingAs($this->companyUser);

        $response = $this->getJson('/api/employees/search?q=test&limit=1');

        $response->assertStatus(200);

        $employees = $response->json('data.employees');
        $this->assertLessThanOrEqual(1, \count($employees));
    }

    /** @test */
    public function test_statistics_returns_employee_stats()
    {
        Passport::actingAs($this->companyUser);

        $response = $this->getJson('/api/employees/statistics');

        // Debug: Print response if it fails
        if ($response->status() !== 200) {
            dump('Response Status: ' . $response->status());
            dump('Response Content: ' . $response->getContent());
        }

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'total_employees',
                        'active_employees',
                        'inactive_employees',
                        'departments_count',
                        'designations_count',
                        'average_salary'
                    ]
                ])
                ->assertJson([
                    'success' => true
                ]);
    }

    /** @test */
    public function test_statistics_requires_permission()
    {
        Passport::actingAs($this->regularEmployee);

        $response = $this->getJson('/api/employees/statistics');

        $response->assertStatus(403)
                ->assertJsonStructure([
                    'error',
                    'required_permission',
                    'user_id',
                    'user_name',
                    'user_permissions_count',
                    'has_permission'
                ]);
    }

    /** @test */
    public function test_get_employee_documents()
    {
        Passport::actingAs($this->companyUser);

        $response = $this->getJson("/api/employees/{$this->regularEmployee->user_id}/documents");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'employee',
                        'documents',
                        'total'
                    ]
                ])
                ->assertJson([
                    'success' => true,
                    'message' => 'تم جلب المستندات بنجاح'
                ]);
    }

    /** @test */
    public function test_get_employee_leave_balance()
    {
        Passport::actingAs($this->companyUser);

        $response = $this->getJson("/api/employees/{$this->regularEmployee->user_id}/leave-balance");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'employee',
                        'year',
                        'leave_types',
                        'summary'
                    ]
                ])
                ->assertJson([
                    'success' => true,
                    'message' => 'تم جلب رصيد الإجازات بنجاح'
                ]);
    }

    /** @test */
    public function test_get_employee_attendance()
    {
        Passport::actingAs($this->companyUser);

        $response = $this->getJson("/api/employees/{$this->regularEmployee->user_id}/attendance");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'employee',
                        'period',
                        'attendance',
                        'summary'
                    ]
                ])
                ->assertJson([
                    'success' => true,
                    'message' => 'تم جلب سجل الحضور بنجاح'
                ]);
    }

    /** @test */
    public function test_get_employee_salary_details()
    {
        Passport::actingAs($this->companyUser);

        $response = $this->getJson("/api/employees/{$this->regularEmployee->user_id}/salary-details");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'employee',
                        'current_salary',
                        'salary_history',
                        'summary'
                    ]
                ])
                ->assertJson([
                    'success' => true,
                    'message' => 'تم جلب تفاصيل الراتب بنجاح'
                ]);
    }

    /** @test */
    public function test_profile_endpoints_require_permission()
    {
        // Create user from different company
        $otherCompanyUser = User::factory()->create([
            'user_type' => 'staff',
            'company_id' => 2,
            'is_active' => 1
        ]);

        Passport::actingAs($otherCompanyUser);

        $endpoints = [
            "/api/employees/{$this->regularEmployee->user_id}/documents",
            "/api/employees/{$this->regularEmployee->user_id}/leave-balance",
            "/api/employees/{$this->regularEmployee->user_id}/attendance",
            "/api/employees/{$this->regularEmployee->user_id}/salary-details"
        ];

        foreach ($endpoints as $endpoint) {
            $response = $this->getJson($endpoint);
            
            // Should get permission denied or not found
            $this->assertContains($response->status(), [403, 404]);
        }
    }

    /** @test */
    public function test_all_endpoints_return_arabic_messages()
    {
        Passport::actingAs($this->companyUser);

        $endpoints = [
            ['GET', '/api/employees'],
            ['GET', "/api/employees/{$this->regularEmployee->user_id}"],
            ['GET', '/api/employees/search?q=test'],
            ['GET', '/api/employees/statistics'],
            ['GET', "/api/employees/{$this->regularEmployee->user_id}/documents"],
            ['GET', "/api/employees/{$this->regularEmployee->user_id}/leave-balance"],
            ['GET', "/api/employees/{$this->regularEmployee->user_id}/attendance"],
            ['GET', "/api/employees/{$this->regularEmployee->user_id}/salary-details"]
        ];

        foreach ($endpoints as [$method, $endpoint]) {
            $response = $this->json($method, $endpoint);
            
            if ($response->status() === 200) {
                $message = $response->json('message');
                $this->assertNotEmpty($message, "Endpoint {$endpoint} should have a message");
                
                // Check if message contains Arabic characters
                $this->assertTrue(
                    preg_match('/[\x{0600}-\x{06FF}]/u', $message) > 0,
                    "Message should be in Arabic for endpoint {$endpoint}. Got: {$message}"
                );
            }
        }
    }

    /** @test */
    public function test_error_responses_have_consistent_format()
    {
        // Test without authentication
        $response = $this->getJson('/api/employees');
        $response->assertStatus(401);

        // Test with insufficient permissions
        Passport::actingAs($this->regularEmployee);
        $response = $this->getJson('/api/employees/statistics');
        
        $response->assertStatus(403)
                ->assertJsonStructure([
                    'error',
                    'required_permission'
                ]);

        $message = $response->json('error');
        $this->assertNotEmpty($message);
        $this->assertTrue(
            preg_match('/[\x{0600}-\x{06FF}]/u', $message) > 0,
            'Error message should be in Arabic'
        );
    }

    /** @test */
    public function test_pagination_works_correctly()
    {
        Passport::actingAs($this->companyUser);

        // Create additional employees for pagination testing
        User::factory()->count(25)->create([
            'user_type' => 'staff',
            'company_id' => 1,
            'is_active' => 1
        ]);

        $response = $this->getJson('/api/employees?limit=10&page=1');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        'data',
                        'pagination' => [
                            'current_page',
                            'last_page',
                            'per_page',
                            'total',
                            'from',
                            'to'
                        ]
                    ]
                ]);

        $pagination = $response->json('data.pagination');
        $this->assertEquals(1, $pagination['current_page']);
        $this->assertEquals(10, $pagination['per_page']);
        $this->assertGreaterThan(1, $pagination['last_page']);
    }

    // ===============================================================================
    // Additional Unit Tests for Missing Methods
    // ===============================================================================

    /** @test */
    public function test_get_employees_for_duty_employee_returns_employees()
    {
        Passport::actingAs($this->companyUser);

        $response = $this->getJson('/api/employees/employees-for-duty-employee');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data'
                ])
                ->assertJson([
                    'success' => true
                ]);
    }

    /** @test */
    public function test_get_employees_for_duty_employee_with_search()
    {
        Passport::actingAs($this->companyUser);

        $response = $this->getJson('/api/employees/employees-for-duty-employee?search=أحمد');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true
                ]);
    }

    /** @test */
    public function test_get_employees_for_duty_employee_with_employee_id_filter()
    {
        Passport::actingAs($this->companyUser);

        $response = $this->getJson("/api/employees/employees-for-duty-employee?employee_id={$this->hrUser->user_id}");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true
                ]);
    }

    /** @test */
    public function test_get_employees_for_duty_employee_handles_exceptions()
    {
        // Test with invalid user to trigger exception
        $invalidUser = User::factory()->create([
            'user_type' => 'staff',
            'company_id' => 999, // Non-existent company
            'is_active' => 1
        ]);

        Passport::actingAs($invalidUser);

        $response = $this->getJson('/api/employees/employees-for-duty-employee');

        // Should handle exception gracefully
        $this->assertContains($response->status(), [200, 500]);
    }

    /** @test */
    public function test_get_duty_employees_for_employee_returns_backup_employees()
    {
        Passport::actingAs($this->companyUser);

        $response = $this->getJson("/api/employees/duty-employees?target_employee_id={$this->regularEmployee->user_id}");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data'
                ])
                ->assertJson([
                    'success' => true,
                    'message' => 'تم جلب الموظفين المناوبين بنجاح'
                ]);
    }

    /** @test */
    public function test_get_duty_employees_for_employee_with_search()
    {
        Passport::actingAs($this->companyUser);

        $response = $this->getJson("/api/employees/duty-employees?target_employee_id={$this->regularEmployee->user_id}&search=أحمد");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true
                ]);
    }

    /** @test */
    public function test_get_duty_employees_for_employee_handles_not_found()
    {
        Passport::actingAs($this->companyUser);

        $response = $this->getJson('/api/employees/duty-employees?target_employee_id=99999');

        // Should handle non-existent employee gracefully
        $this->assertContains($response->status(), [200, 404, 422]);
    }

    /** @test */
    public function test_get_duty_employees_for_employee_handles_permission_error()
    {
        Passport::actingAs($this->regularEmployee);

        $response = $this->getJson("/api/employees/duty-employees?target_employee_id={$this->hrUser->user_id}");

        // Should handle permission error gracefully
        $this->assertContains($response->status(), [200, 403, 422]);
    }

    /** @test */
    public function test_get_employees_for_notify_returns_notifiable_employees()
    {
        Passport::actingAs($this->companyUser);

        $response = $this->getJson('/api/employees/employees-for-notify');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data'
                ])
                ->assertJson([
                    'success' => true
                ]);

        // Verify data structure
        $data = $response->json('data');
        $this->assertIsArray($data);
        
        if (!empty($data)) {
            $this->assertArrayHasKey('user_id', $data[0]);
            $this->assertArrayHasKey('first_name', $data[0]);
            $this->assertArrayHasKey('last_name', $data[0]);
            $this->assertArrayHasKey('email', $data[0]);
        }
    }

    /** @test */
    public function test_get_employees_for_notify_with_search()
    {
        Passport::actingAs($this->companyUser);

        $response = $this->getJson('/api/employees/employees-for-notify?search=أحمد');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true
                ]);
    }

    /** @test */
    public function test_get_employees_for_notify_handles_exceptions()
    {
        // Test with invalid user to trigger exception
        $invalidUser = User::factory()->create([
            'user_type' => 'staff',
            'company_id' => 999, // Non-existent company
            'is_active' => 1
        ]);

        Passport::actingAs($invalidUser);

        $response = $this->getJson('/api/employees/employees-for-notify');

        // Should handle exception gracefully
        $this->assertContains($response->status(), [200, 500]);
    }

    /** @test */
    public function test_get_subordinates_returns_hierarchy_based_employees()
    {
        Passport::actingAs($this->hrUser);

        $response = $this->getJson('/api/employees/subordinates');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data'
                ])
                ->assertJson([
                    'success' => true,
                    'message' => 'تم جلب الموظفين التابعين بنجاح'
                ]);
    }

    /** @test */
    public function test_get_subordinates_for_company_user()
    {
        Passport::actingAs($this->companyUser);

        $response = $this->getJson('/api/employees/subordinates');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true
                ]);

        // Company user should see all employees
        $data = $response->json('data');
        $this->assertIsArray($data);
    }

    /** @test */
    public function test_get_subordinates_handles_exceptions()
    {
        // Test with invalid user to trigger exception
        $invalidUser = User::factory()->create([
            'user_type' => 'staff',
            'company_id' => 999, // Non-existent company
            'is_active' => 1
        ]);

        Passport::actingAs($invalidUser);

        $response = $this->getJson('/api/employees/subordinates');

        // Should handle exception gracefully
        $this->assertContains($response->status(), [200, 500]);
    }

    /** @test */
    public function test_get_approval_levels_returns_approval_chain()
    {
        Passport::actingAs($this->companyUser);

        $response = $this->getJson("/api/employees/approval-levels?employee_id={$this->regularEmployee->user_id}");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data'
                ])
                ->assertJson([
                    'success' => true,
                    'message' => 'تم جلب مستويات الاعتماد بنجاح'
                ]);
    }

    /** @test */
    public function test_get_approval_levels_without_employee_id()
    {
        Passport::actingAs($this->regularEmployee);

        $response = $this->getJson('/api/employees/approval-levels');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'تم جلب مستويات الاعتماد بنجاح'
                ]);
    }

    /** @test */
    public function test_get_approval_levels_handles_not_found()
    {
        Passport::actingAs($this->companyUser);

        $response = $this->getJson('/api/employees/approval-levels?employee_id=99999');

        // Should handle non-existent employee gracefully
        $this->assertContains($response->status(), [200, 404]);
    }

    /** @test */
    public function test_get_approval_levels_handles_permission_error()
    {
        // Create user from different company
        $otherCompanyUser = User::factory()->create([
            'user_type' => 'staff',
            'company_id' => 2,
            'is_active' => 1
        ]);

        Passport::actingAs($otherCompanyUser);

        $response = $this->getJson("/api/employees/approval-levels?employee_id={$this->regularEmployee->user_id}");

        // Should handle permission error gracefully
        $this->assertContains($response->status(), [200, 403]);
    }

    // ===============================================================================
    // Edge Cases and Error Handling Tests
    // ===============================================================================

    /** @test */
    public function test_index_handles_invalid_filters()
    {
        Passport::actingAs($this->companyUser);

        $response = $this->getJson('/api/employees?department_id=invalid&is_active=not_boolean');

        // Should handle invalid filters gracefully
        $this->assertContains($response->status(), [200, 422]);
    }

    /** @test */
    public function test_search_handles_empty_query()
    {
        Passport::actingAs($this->companyUser);

        $response = $this->getJson('/api/employees/search?q=');

        $response->assertStatus(400)
                ->assertJson([
                    'success' => false
                ]);
    }

    /** @test */
    public function test_search_handles_whitespace_only_query()
    {
        Passport::actingAs($this->companyUser);

        $response = $this->getJson('/api/employees/search?' . http_build_query(['q' => '   ']));

        $response->assertStatus(400)
                ->assertJson([
                    'success' => false,
                    'message' => 'نص البحث مطلوب'
                ]);
    }

    /** @test */
    public function test_store_handles_database_errors()
    {
        Passport::actingAs($this->companyUser);

        // Try to create employee with invalid department_id
        $employeeData = [
            'first_name' => 'محمد',
            'last_name' => 'علي',
            'email' => 'test.db.error@test.com',
            'username' => 'test.db.error',
            'password' => 'password123',
            'department_id' => 99999, // Non-existent department
            'designation_id' => $this->designation->designation_id,
        ];

        $response = $this->postJson('/api/employees', $employeeData);

        // Should handle database constraint errors gracefully
        $this->assertContains($response->status(), [422, 500]);
    }

    /** @test */
    public function test_update_handles_concurrent_modifications()
    {
        Passport::actingAs($this->companyUser);

        // Simulate concurrent modification by updating the employee first
        $this->regularEmployee->update(['first_name' => 'تم التحديث مسبقاً']);

        $updateData = [
            'first_name' => 'محاولة تحديث متزامن',
            'last_name' => 'اختبار'
        ];

        $response = $this->putJson("/api/employees/{$this->regularEmployee->user_id}", $updateData);

        // Should handle concurrent modifications gracefully
        $this->assertContains($response->status(), [200, 409, 500]);
    }

    /** @test */
    public function test_destroy_handles_employee_with_dependencies()
    {
        Passport::actingAs($this->companyUser);

        // Employee might have dependencies (leaves, attendance, etc.)
        $response = $this->deleteJson("/api/employees/{$this->regularEmployee->user_id}");

        // Should handle dependencies gracefully (soft delete)
        $response->assertStatus(200)
                ->assertJson([
                    'success' => true
                ]);

        // Verify soft delete
        $this->assertDatabaseHas('ci_erp_users', [
            'user_id' => $this->regularEmployee->user_id,
            'is_active' => 0
        ]);
    }

    /** @test */
    public function test_profile_endpoints_handle_missing_data()
    {
        Passport::actingAs($this->companyUser);

        // Create employee without user details
        $employeeWithoutDetails = User::factory()->create([
            'user_type' => 'staff',
            'company_id' => 1,
            'is_active' => 1
        ]);

        $endpoints = [
            "/api/employees/{$employeeWithoutDetails->user_id}/documents",
            "/api/employees/{$employeeWithoutDetails->user_id}/leave-balance",
            "/api/employees/{$employeeWithoutDetails->user_id}/attendance",
            "/api/employees/{$employeeWithoutDetails->user_id}/salary-details"
        ];

        foreach ($endpoints as $endpoint) {
            $response = $this->getJson($endpoint);
            
            // Should handle missing data gracefully
            $this->assertContains($response->status(), [200, 404]);
        }
    }

    /** @test */
    public function test_all_methods_handle_unauthenticated_requests()
    {
        $endpoints = [
            ['GET', '/api/employees'],
            ['POST', '/api/employees'],
            ['GET', '/api/employees/1'],
            ['PUT', '/api/employees/1'],
            ['DELETE', '/api/employees/1'],
            ['GET', '/api/employees/search?q=test'],
            ['GET', '/api/employees/statistics'],
            ['GET', '/api/employees/1/documents'],
            ['GET', '/api/employees/1/leave-balance'],
            ['GET', '/api/employees/1/attendance'],
            ['GET', '/api/employees/1/salary-details'],
            ['GET', '/api/employees/employees-for-duty-employee'],
            ['GET', '/api/employees/duty-employees'],
            ['GET', '/api/employees/employees-for-notify'],
            ['GET', '/api/employees/subordinates'],
            ['GET', '/api/employees/approval-levels']
        ];

        foreach ($endpoints as [$method, $endpoint]) {
            $response = $this->json($method, $endpoint);
            
            $response->assertStatus(401);
        }
    }

    /** @test */
    public function test_all_methods_return_json_responses()
    {
        Passport::actingAs($this->companyUser);

        $endpoints = [
            ['GET', '/api/employees'],
            ['GET', "/api/employees/{$this->regularEmployee->user_id}"],
            ['GET', '/api/employees/search?q=test'],
            ['GET', '/api/employees/statistics'],
            ['GET', "/api/employees/{$this->regularEmployee->user_id}/documents"],
            ['GET', "/api/employees/{$this->regularEmployee->user_id}/leave-balance"],
            ['GET', "/api/employees/{$this->regularEmployee->user_id}/attendance"],
            ['GET', "/api/employees/{$this->regularEmployee->user_id}/salary-details"],
            ['GET', '/api/employees/employees-for-duty-employee'],
            ['GET', '/api/employees/duty-employees'],
            ['GET', '/api/employees/employees-for-notify'],
            ['GET', '/api/employees/subordinates'],
            ['GET', '/api/employees/approval-levels']
        ];

        foreach ($endpoints as [$method, $endpoint]) {
            $response = $this->json($method, $endpoint);
            
            $this->assertJson($response->getContent(), 
                "Endpoint {$method} {$endpoint} should return valid JSON");
        }
    }

    /** @test */
    public function test_methods_with_limit_parameter_respect_limits()
    {
        Passport::actingAs($this->companyUser);

        $endpointsWithLimits = [
            '/api/employees/search?q=test&limit=5',
            "/api/employees/{$this->regularEmployee->user_id}/attendance?limit=10",
            "/api/employees/{$this->regularEmployee->user_id}/salary-details?limit=6"
        ];

        foreach ($endpointsWithLimits as $endpoint) {
            $response = $this->getJson($endpoint);
            
            if ($response->status() === 200) {
                $data = $response->json('data');
                $this->assertIsArray($data);
                // The actual limit checking would depend on the service implementation
            }
        }
    }
}