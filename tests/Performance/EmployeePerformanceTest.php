<?php

namespace Tests\Performance;

use Tests\TestCase;
use App\Models\User;
use App\Models\UserDetails;
use App\Models\Department;
use App\Models\Designation;
use Laravel\Passport\Passport;
use Illuminate\Foundation\Testing\RefreshDatabase;

class EmployeePerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected $companyUser;
    protected $departments;
    protected $designations;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test company user
        $this->companyUser = User::factory()->create([
            'user_type' => 'company',
            'company_id' => 1,
            'is_active' => 1
        ]);
        
        // Create departments
        $this->departments = Department::factory()->count(5)->create([
            'company_id' => 1
        ]);
        
        // Create designations
        $this->designations = Designation::factory()->count(10)->create([
            'company_id' => 1
        ]);
        
        // Create large number of employees for performance testing
        $this->createLargeDataset();
    }

    protected function createLargeDataset()
    {
        // Create 50 employees for performance testing (reduced for faster tests)
        $employees = User::factory()->count(50)->create([
            'user_type' => 'staff',
            'company_id' => 1,
            'is_active' => 1
        ]);
        
        foreach ($employees as $employee) {
            UserDetails::factory()->create([
                'user_id' => $employee->user_id,
                'department_id' => $this->departments->random()->department_id,
                'designation_id' => $this->designations->random()->designation_id,
                'basic_salary' => rand(3000, 10000)
            ]);
        }
    }

    /** @test */
    public function test_employee_list_performance_with_large_dataset()
    {
        Passport::actingAs($this->companyUser);
        
        $startTime = microtime(true);
        
        $response = $this->getJson('/api/employees');
        
        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
        
        $response->assertStatus(200);
        
        // Assert response time is under 2 seconds (2000ms)
        $this->assertLessThan(2000, $executionTime, 
            "Employee list endpoint took {$executionTime}ms, which exceeds 2000ms limit");
        
        // Assert we got some employees (at least 1)
        $employees = $response->json('data');
        $this->assertGreaterThanOrEqual(1, count($employees));
        
        echo "\nEmployee list performance: {$executionTime}ms for " . count($employees) . " employees\n";
    }

    /** @test */
    public function test_statistics_performance_with_large_dataset()
    {
        Passport::actingAs($this->companyUser);
        
        $startTime = microtime(true);
        
        $response = $this->getJson('/api/employees/statistics');
        
        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000;
        
        $response->assertStatus(200);
        
        // Statistics should complete within 3 seconds even with complex calculations
        $this->assertLessThan(3000, $executionTime, 
            "Statistics endpoint took {$executionTime}ms, which exceeds 3000ms limit");
        
        $stats = $response->json('data');
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_employees', $stats);
        
        echo "\nStatistics performance: {$executionTime}ms for {$stats['total_employees']} employees\n";
    }

    /** @test */
    public function test_search_performance_with_large_dataset()
    {
        Passport::actingAs($this->companyUser);
        
        // Test search performance with different query types
        $searchQueries = [
            'موظف', // Common Arabic term
            'test', // Common English term
            '@test.com' // Email pattern
        ];
        
        foreach ($searchQueries as $query) {
            $startTime = microtime(true);
            
            $response = $this->getJson('/api/employees/search?q=' . urlencode($query));
            
            $endTime = microtime(true);
            $executionTime = ($endTime - $startTime) * 1000;
            
            $response->assertStatus(200);
            
            // Search should complete within 1.5 seconds
            $this->assertLessThan(1500, $executionTime, 
                "Search for '{$query}' took {$executionTime}ms, which exceeds 1500ms limit");
            
            echo "\nSearch performance for '{$query}': {$executionTime}ms\n";
        }
    }
}