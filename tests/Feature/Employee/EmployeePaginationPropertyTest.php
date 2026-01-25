<?php

namespace Tests\Feature\Employee;

use Tests\TestCase;
use App\Models\User;
use App\Services\EmployeeManagementService;
use App\Services\SimplePermissionService;
use App\DTOs\Employee\EmployeeFilterDTO;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

/**
 * Property-Based Tests for Employee Pagination Support
 * 
 * **Feature: employee-management-api, Property 5: دعم التصفح**
 * **Validates: Requirements 1.5**
 * 
 * Tests that pagination works correctly across all valid inputs
 */
class EmployeePaginationPropertyTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private EmployeeManagementService $employeeService;
    private SimplePermissionService $permissionService;
    private User $testUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->employeeService = app(EmployeeManagementService::class);
        $this->permissionService = app(SimplePermissionService::class);
        
        // Create test user with all required fields
        $this->testUser = $this->createTestUser();
        
        // Create user details for test user (not needed for company owner but for consistency)
        // Company owners don't need user_details but we create it anyway for testing
    }

    private function createTestUser(array $overrides = []): User
    {
        $defaults = [
            'first_name' => 'Test',
            'last_name' => 'User',
            'username' => 'testuser' . rand(1000, 9999),
            'email' => 'test' . rand(1000, 9999) . '@example.com',
            'password' => bcrypt('password'),
            'company_id' => 0, // Company owner
            'user_type' => 'company', // Company owner
            'is_active' => 1,
            'profile_photo' => 'default.jpg',
            'gender' => 'M',
            'user_role_id' => 0, // Company owner
            'created_at' => now()->format('Y-m-d H:i:s')
        ];

        return User::create(array_merge($defaults, $overrides));
    }

    /**
     * Property 5: دعم التصفح
     * For any valid page number and limit, pagination should return consistent results
     * **Validates: Requirements 1.5**
     */
    public function test_pagination_consistency_property()
    {
        // Run property test 30 times with different inputs (reduced for performance)
        for ($iteration = 0; $iteration < 30; $iteration++) {
            
            // Generate random but valid pagination parameters
            $totalEmployees = rand(3, 15);
            $limit = rand(2, 5);
            $page = rand(1, max(1, ceil($totalEmployees / $limit)));
            
            // Create employees for this test
            $employees = collect();
            for ($i = 0; $i < $totalEmployees; $i++) {
                $employee = $this->createTestUser([
                    'first_name' => 'Employee' . $i,
                    'last_name' => 'Test' . $i,
                    'username' => 'emp' . $i . '_' . $iteration . '_' . rand(1000, 9999),
                    'email' => 'emp' . $i . '_' . $iteration . '_' . rand(1000, 9999) . '@test.com',
                    'company_id' => $this->testUser->user_id, // Company owner's user_id is the company_id for employees
                    'user_type' => 'staff', // Override to make them staff
                    'user_role_id' => 1, // Override to make them staff
                ]);
                
                // Create user details for each employee
                \App\Models\UserDetails::factory()->create([
                    'company_id' => $this->testUser->user_id, // Company owner's user_id is the company_id for employees
                    'user_id' => $employee->user_id,
                    'employee_id' => 'EMP' . str_pad($employee->user_id, 4, '0', STR_PAD_LEFT),
                    'reporting_manager' => $this->testUser->user_id,
                ]);
                
                $employees->push($employee);
            }
            
            // Create filter DTO with pagination parameters
            $filters = EmployeeFilterDTO::fromArray([
                'page' => $page,
                'limit' => $limit
            ]);
            
            // Get paginated results
            $result = $this->employeeService->getEmployeesList($this->testUser, $filters);
            
            // Property assertions
            $this->assertLessThanOrEqual($limit, $result->count(), 
                "Page should not exceed limit. Page: $page, Limit: $limit, Count: {$result->count()}");
            
            $this->assertEquals($page, $result->currentPage(), 
                "Current page should match requested page. Expected: $page, Got: {$result->currentPage()}");
            
            $this->assertEquals($limit, $result->perPage(), 
                "Per page should match requested limit. Expected: $limit, Got: {$result->perPage()}");
            
            $this->assertEquals($totalEmployees, $result->total(), // Only staff employees, not company owner
                "Total should match actual employee count. Expected: " . $totalEmployees . ", Got: {$result->total()}");
            
            // Clean up for next iteration
            \App\Models\UserDetails::whereIn('user_id', $employees->pluck('user_id'))->delete();
            User::whereIn('user_id', $employees->pluck('user_id'))->delete();
        }
    }

    /**
     * Property: Pagination boundary conditions
     * For boundary values (first page, last page, empty results), pagination should behave correctly
     */
    public function test_pagination_boundary_conditions_property()
    {
        for ($iteration = 0; $iteration < 15; $iteration++) {
            
            $totalEmployees = rand(0, 10);
            $limit = rand(2, 5);
            
            // Create employees
            $createdEmployees = collect();
            if ($totalEmployees > 0) {
                for ($i = 0; $i < $totalEmployees; $i++) {
                    $createdEmployees->push($this->createTestUser([
                        'first_name' => 'Boundary' . $i,
                        'last_name' => 'Test' . $i,
                        'username' => 'boundary' . $i . '_' . $iteration . '_' . rand(1000, 9999),
                        'email' => 'boundary' . $i . '_' . $iteration . '_' . rand(1000, 9999) . '@test.com',
                        'company_id' => $this->testUser->user_id,
                    ]));
                }
            }
            
            $actualTotal = $totalEmployees; // Only staff employees, not company owner
            
            // Test first page
            $firstPageFilters = EmployeeFilterDTO::fromArray(['page' => 1, 'limit' => $limit]);
            $firstPageResult = $this->employeeService->getEmployeesList($this->testUser, $firstPageFilters);
            
            $this->assertEquals(1, $firstPageResult->currentPage(), "First page should be page 1");
            $this->assertGreaterThanOrEqual(0, $firstPageResult->count(), "First page should have non-negative items");
            
            if ($actualTotal > 0) {
                $this->assertGreaterThan(0, $firstPageResult->count(), "First page should have items when employees exist");
            }
            
            // Test last page if there are multiple pages
            $lastPage = max(1, ceil($actualTotal / $limit));
            if ($lastPage > 1) {
                $lastPageFilters = EmployeeFilterDTO::fromArray(['page' => $lastPage, 'limit' => $limit]);
                $lastPageResult = $this->employeeService->getEmployeesList($this->testUser, $lastPageFilters);
                
                $this->assertEquals($lastPage, $lastPageResult->currentPage(), "Should be on last page");
                $this->assertGreaterThan(0, $lastPageResult->count(), "Last page should have items");
            }
            
            // Clean up
            if ($createdEmployees->isNotEmpty()) {
                \App\Models\UserDetails::whereIn('user_id', $createdEmployees->pluck('user_id'))->delete(); User::whereIn('user_id', $createdEmployees->pluck('user_id'))->delete();
            }
        }
    }

    /**
     * Property: Pagination metadata consistency
     * For any valid pagination request, metadata should be mathematically correct
     */
    public function test_pagination_metadata_consistency_property()
    {
        for ($iteration = 0; $iteration < 20; $iteration++) {
            
            $totalEmployees = rand(0, 12);
            $limit = rand(1, 6);
            $page = rand(1, max(1, ceil(($totalEmployees + 1) / $limit)));
            
            // Create employees
            $createdEmployees = collect();
            if ($totalEmployees > 0) {
                for ($i = 0; $i < $totalEmployees; $i++) {
                    $createdEmployees->push($this->createTestUser([
                        'first_name' => 'Meta' . $i,
                        'last_name' => 'Test' . $i,
                        'username' => 'meta' . $i . '_' . $iteration . '_' . rand(1000, 9999),
                        'email' => 'meta' . $i . '_' . $iteration . '_' . rand(1000, 9999) . '@test.com',
                        'company_id' => $this->testUser->user_id,
                    ]));
                }
            }
            
            $filters = EmployeeFilterDTO::fromArray(['page' => $page, 'limit' => $limit]);
            $result = $this->employeeService->getEmployeesList($this->testUser, $filters);
            
            $actualTotal = $totalEmployees; // Only staff employees, not company owner
            
            // Verify mathematical relationships
            $expectedLastPage = max(1, ceil($actualTotal / $limit));
            $this->assertEquals($expectedLastPage, $result->lastPage(),
                "Last page calculation should be correct");
            
            // Verify page bounds
            $this->assertGreaterThanOrEqual(1, $result->currentPage(),
                "Current page should be at least 1");
            $this->assertLessThanOrEqual($result->lastPage(), $result->currentPage(),
                "Current page should not exceed last page");
            
            // Clean up
            if ($createdEmployees->isNotEmpty()) {
                \App\Models\UserDetails::whereIn('user_id', $createdEmployees->pluck('user_id'))->delete(); User::whereIn('user_id', $createdEmployees->pluck('user_id'))->delete();
            }
        }
    }
}
