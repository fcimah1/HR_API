<?php

namespace Tests\Security;

use Tests\TestCase;
use App\Models\User;
use App\Models\UserDetails;
use App\Models\Department;
use App\Models\Designation;
use Laravel\Passport\Passport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

class EmployeeSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected $companyUser;
    protected $hrUser;
    protected $regularEmployee;
    protected $otherCompanyUser;
    protected $department;
    protected $designation;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->department = Department::factory()->create(['company_id' => 1]);
        $this->designation = Designation::factory()->create(['company_id' => 1]);
        
        $this->companyUser = User::factory()->create([
            'user_type' => 'company',
            'company_id' => 1,
            'is_active' => 1
        ]);
        
        $this->hrUser = User::factory()->create([
            'user_type' => 'staff',
            'company_id' => 1,
            'is_active' => 1
        ]);
        
        UserDetails::factory()->create([
            'user_id' => $this->hrUser->user_id,
            'department_id' => $this->department->department_id,
            'designation_id' => $this->designation->designation_id
        ]);
        
        $this->regularEmployee = User::factory()->create([
            'user_type' => 'staff',
            'company_id' => 1,
            'is_active' => 1
        ]);
        
        UserDetails::factory()->create([
            'user_id' => $this->regularEmployee->user_id,
            'department_id' => $this->department->department_id,
            'designation_id' => $this->designation->designation_id
        ]);
        
        // User from different company
        $this->otherCompanyUser = User::factory()->create([
            'user_type' => 'company',
            'company_id' => 2,
            'is_active' => 1
        ]);
    }

    /** @test */
    public function test_authentication_required_for_all_endpoints()
    {
        $endpoints = [
            ['GET', '/api/employees'],
            ['POST', '/api/employees'],
            ['GET', '/api/employees/1'],
            ['PUT', '/api/employees/1'],
            ['DELETE', '/api/employees/1'],
            ['GET', '/api/employees/search'],
            ['GET', '/api/employees/statistics'],
        ];
        
        foreach ($endpoints as [$method, $endpoint]) {
            $response = $this->json($method, $endpoint);
            
            $this->assertEquals(401, $response->status(), 
                "Endpoint {$method} {$endpoint} should require authentication but returned {$response->status()}");
        }
    }

    /** @test */
    public function test_company_isolation_security()
    {
        Passport::actingAs($this->otherCompanyUser);
        
        // Should not access employees from company 1
        $response = $this->getJson("/api/employees/{$this->regularEmployee->user_id}");
        $this->assertContains($response->status(), [403, 404], 
            'Users should not access employees from other companies');
        
        // Should not update employees from company 1
        $response = $this->putJson("/api/employees/{$this->regularEmployee->user_id}", [
            'first_name' => 'Hacked Name'
        ]);
        $this->assertContains($response->status(), [403, 404], 
            'Users should not update employees from other companies');
        
        // Should not delete employees from company 1
        $response = $this->deleteJson("/api/employees/{$this->regularEmployee->user_id}");
        $this->assertContains($response->status(), [403, 404], 
            'Users should not delete employees from other companies');
    }

    /** @test */
    public function test_permission_based_access_control()
    {
        // Regular employee should have limited access
        Passport::actingAs($this->regularEmployee);
        
        // Should not access other employees
        $response = $this->getJson("/api/employees/{$this->hrUser->user_id}");
        $this->assertContains($response->status(), [403, 404], 
            'Regular employees should not access other employee details');
        
        // Should not access statistics
        $response = $this->getJson('/api/employees/statistics');
        $this->assertEquals(403, $response->status(), 
            'Regular employees should not access statistics');
        
        // Should not create employees
        $response = $this->postJson('/api/employees', [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@test.com',
            'password' => 'password123'
        ]);
        $this->assertEquals(403, $response->status(), 
            'Regular employees should not create other employees');
        
        // Should not update other employees
        $response = $this->putJson("/api/employees/{$this->hrUser->user_id}", [
            'first_name' => 'Updated Name'
        ]);
        $this->assertEquals(403, $response->status(), 
            'Regular employees should not update other employees');
    }

    /** @test */
    public function test_sql_injection_protection()
    {
        Passport::actingAs($this->companyUser);
        
        // Test SQL injection in search
        $maliciousQueries = [
            "'; DROP TABLE ci_erp_users; --",
            "' OR '1'='1",
            "'; UPDATE ci_erp_users SET password='hacked'; --",
            "' UNION SELECT * FROM ci_erp_users WHERE '1'='1"
        ];
        
        foreach ($maliciousQueries as $query) {
            $response = $this->getJson('/api/employees/search?q=' . urlencode($query));
            
            // Should either return safe results or validation error, not 500
            $this->assertContains($response->status(), [200, 400, 422], 
                "SQL injection attempt should be handled safely, got {$response->status()} for query: {$query}");
            
            // Verify database is still intact
            $this->assertDatabaseHas('ci_erp_users', [
                'user_id' => $this->regularEmployee->user_id
            ]);
        }
    }

    /** @test */
    public function test_password_security()
    {
        Passport::actingAs($this->companyUser);
        
        // Create employee with password
        $response = $this->postJson('/api/employees', [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@test.com',
            'password' => 'password123',
            'user_type' => 'staff',
            'department_id' => $this->department->department_id,
            'designation_id' => $this->designation->designation_id
        ]);
        
        if ($response->status() === 201) {
            $employeeId = $response->json('data.user_id');
            
            // Retrieve employee data
            $getResponse = $this->getJson("/api/employees/{$employeeId}");
            $getResponse->assertStatus(200);
            
            $responseData = $getResponse->json('data');
            
            // Password should not be returned in API responses
            $this->assertArrayNotHasKey('password', $responseData, 
                'Password field should not be returned in API responses');
            
            // Verify password is actually hashed in database
            $user = User::find($employeeId);
            $this->assertNotEquals('password123', $user->password, 
                'Password should be hashed in database');
            $this->assertTrue(Hash::check('password123', $user->password), 
                'Password should be properly hashed');
        }
    }

    /** @test */
    public function test_input_validation_security()
    {
        Passport::actingAs($this->companyUser);
        
        // Test various malicious inputs
        $maliciousInputs = [
            [
                'first_name' => str_repeat('A', 1000), // Very long string
                'expected_status' => 422
            ],
            [
                'email' => 'not-an-email', // Invalid email
                'expected_status' => 422
            ],
            [
                'first_name' => null, // Null required field
                'expected_status' => 422
            ],
            [
                'user_type' => 'invalid_type', // Invalid enum value
                'expected_status' => 422
            ]
        ];
        
        foreach ($maliciousInputs as $input) {
            $data = array_merge([
                'first_name' => 'Test',
                'last_name' => 'User',
                'email' => 'test@test.com',
                'password' => 'password123',
                'user_type' => 'staff'
            ], $input);
            
            unset($data['expected_status']);
            $expectedStatus = $input['expected_status'];
            
            $response = $this->postJson('/api/employees', $data);
            
            $this->assertEquals($expectedStatus, $response->status(), 
                'Input validation should reject malicious input: ' . json_encode($input));
        }
    }

    /** @test */
    public function test_information_disclosure_prevention()
    {
        Passport::actingAs($this->companyUser);
        
        // Test that error messages don't reveal sensitive information
        $response = $this->getJson('/api/employees/99999');
        $response->assertStatus(404);
        
        $errorMessage = $response->json('message');
        
        // Error message should not reveal database structure or sensitive info
        $this->assertStringNotContainsString('ci_erp_users', $errorMessage, 
            'Error messages should not reveal database table names');
        $this->assertStringNotContainsString('SQL', $errorMessage, 
            'Error messages should not reveal SQL information');
        $this->assertStringNotContainsString('database', $errorMessage, 
            'Error messages should not reveal database information');
    }
}