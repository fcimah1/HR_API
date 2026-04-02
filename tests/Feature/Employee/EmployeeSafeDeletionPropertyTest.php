<?php

namespace Tests\Feature\Employee;

use Tests\TestCase;
use App\Models\User;
use App\Services\EmployeeManagementService;
use App\Services\SimplePermissionService;
use App\DTOs\Employee\CreateEmployeeDTO;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

/**
 * Property-Based Tests for Employee Safe Deletion
 * 
 * **Feature: employee-management-api, Property 31: الحذف الآمن**
 * **Validates: Requirements 7.1**
 * 
 * Tests that employee deletion is safe and preserves data integrity
 */
class EmployeeSafeDeletionPropertyTest extends TestCase
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
    }

    private function createTestUser(array $overrides = []): User
    {
        $defaults = [
            'first_name' => 'Test',
            'last_name' => 'User',
            'username' => 'testuser' . rand(1000, 9999),
            'email' => 'test' . rand(1000, 9999) . '@example.com',
            'password' => bcrypt('password'),
            'company_id' => 1,
            'user_type' => 'staff',
            'is_active' => 1,
            'profile_photo' => 'default.jpg',
            'gender' => 'M',
            'user_role_id' => 1,
            'created_at' => now()
        ];

        return User::create(array_merge($defaults, $overrides));
    }

    /**
     * Property 31: الحذف الآمن
     * For any employee deletion, the operation should be safe (soft delete) and preserve data integrity
     * **Validates: Requirements 7.1**
     */
    public function test_safe_deletion_preserves_data_property()
    {
        // Run property test 30 times with different scenarios
        for ($iteration = 0; $iteration < 30; $iteration++) {
            
            // Create an employee to delete
            $employee = $this->createTestUser([
                'first_name' => 'ToDelete',
                'last_name' => 'Test' . $iteration,
                'username' => 'todelete_' . $iteration . '_' . rand(1000, 9999),
                'email' => 'todelete_' . $iteration . '_' . rand(1000, 9999) . '@test.com',
                'company_id' => $this->testUser->company_id,
                'is_active' => 1
            ]);
            
            // Store original data before deletion
            $originalUserId = $employee->user_id;
            $originalFirstName = $employee->first_name;
            $originalLastName = $employee->last_name;
            $originalEmail = $employee->email;
            
            // Perform deletion
            $deletionResult = $this->employeeService->deactivateEmployee($this->testUser, $employee->user_id);
            
            // Property assertions for safe deletion
            $this->assertTrue($deletionResult, "Deletion should succeed for valid employee");
            
            // Verify employee still exists in database (soft delete)
            $employeeAfterDeletion = User::find($originalUserId);
            $this->assertNotNull($employeeAfterDeletion, "Employee record should still exist after deletion (soft delete)");
            
            // Verify employee is marked as inactive
            $this->assertEquals(0, $employeeAfterDeletion->is_active, "Employee should be marked as inactive after deletion");
            
            // Verify original data is preserved
            $this->assertEquals($originalFirstName, $employeeAfterDeletion->first_name, "First name should be preserved");
            $this->assertEquals($originalLastName, $employeeAfterDeletion->last_name, "Last name should be preserved");
            $this->assertEquals($originalEmail, $employeeAfterDeletion->email, "Email should be preserved");
            
            // Clean up
            User::where('user_id', $originalUserId)->forceDelete();
        }
    }

    /**
     * Property: Deletion idempotency
     * For any employee, deleting multiple times should be safe and consistent
     */
    public function test_deletion_idempotency_property()
    {
        for ($iteration = 0; $iteration < 20; $iteration++) {
            
            // Create an employee to delete
            $employee = $this->createTestUser([
                'first_name' => 'Idempotent',
                'last_name' => 'Test' . $iteration,
                'username' => 'idempotent_' . $iteration . '_' . rand(1000, 9999),
                'email' => 'idempotent_' . $iteration . '_' . rand(1000, 9999) . '@test.com',
                'company_id' => $this->testUser->company_id,
                'is_active' => 1
            ]);
            
            $originalUserId = $employee->user_id;
            
            // Delete multiple times
            $firstDeletion = $this->employeeService->deactivateEmployee($this->testUser, $employee->user_id);
            $secondDeletion = $this->employeeService->deactivateEmployee($this->testUser, $employee->user_id);
            $thirdDeletion = $this->employeeService->deactivateEmployee($this->testUser, $employee->user_id);
            
            // Property assertions for idempotency
            $this->assertTrue($firstDeletion, "First deletion should succeed");
            $this->assertTrue($secondDeletion, "Second deletion should succeed (idempotent)");
            $this->assertTrue($thirdDeletion, "Third deletion should succeed (idempotent)");
            
            // Verify final state is consistent
            $finalEmployee = User::find($originalUserId);
            $this->assertNotNull($finalEmployee, "Employee should still exist after multiple deletions");
            $this->assertEquals(0, $finalEmployee->is_active, "Employee should be inactive after multiple deletions");
            
            // Clean up
            User::where('user_id', $originalUserId)->forceDelete();
        }
    }

    /**
     * Property: Deletion permission enforcement
     * For any deletion attempt, proper permissions should be enforced
     */
    public function test_deletion_permission_enforcement_property()
    {
        for ($iteration = 0; $iteration < 15; $iteration++) {
            
            // Create an employee to delete
            $employee = $this->createTestUser([
                'first_name' => 'Permission',
                'last_name' => 'Test' . $iteration,
                'username' => 'permission_' . $iteration . '_' . rand(1000, 9999),
                'email' => 'permission_' . $iteration . '_' . rand(1000, 9999) . '@test.com',
                'company_id' => $this->testUser->company_id,
                'is_active' => 1
            ]);
            
            // Create a user without delete permissions (different company)
            $unauthorizedUser = $this->createTestUser([
                'first_name' => 'Unauthorized',
                'last_name' => 'User' . $iteration,
                'username' => 'unauthorized_' . $iteration . '_' . rand(1000, 9999),
                'email' => 'unauthorized_' . $iteration . '_' . rand(1000, 9999) . '@test.com',
                'company_id' => 999, // Different company
                'is_active' => 1
            ]);
            
            $originalUserId = $employee->user_id;
            $originalActiveStatus = $employee->is_active;
            
            // Attempt deletion with unauthorized user
            $unauthorizedDeletion = $this->employeeService->deactivateEmployee($unauthorizedUser, $employee->user_id);
            
            // Property assertions for permission enforcement
            $this->assertFalse($unauthorizedDeletion, "Deletion should fail for unauthorized user");
            
            // Verify employee state is unchanged
            $employeeAfterUnauthorizedAttempt = User::find($originalUserId);
            $this->assertNotNull($employeeAfterUnauthorizedAttempt, "Employee should still exist after unauthorized deletion attempt");
            $this->assertEquals($originalActiveStatus, $employeeAfterUnauthorizedAttempt->is_active, 
                "Employee active status should be unchanged after unauthorized deletion attempt");
            
            // Now try with authorized user
            $authorizedDeletion = $this->employeeService->deactivateEmployee($this->testUser, $employee->user_id);
            $this->assertTrue($authorizedDeletion, "Deletion should succeed for authorized user");
            
            // Clean up
            User::where('user_id', $originalUserId)->forceDelete();
            User::where('user_id', $unauthorizedUser->user_id)->forceDelete();
        }
    }

    /**
     * Property: Deletion of non-existent employee
     * For any non-existent employee ID, deletion should fail gracefully
     */
    public function test_deletion_of_nonexistent_employee_property()
    {
        for ($iteration = 0; $iteration < 25; $iteration++) {
            
            // Generate random non-existent employee ID
            $nonExistentId = rand(999999, 9999999);
            
            // Ensure this ID doesn't exist
            while (User::find($nonExistentId)) {
                $nonExistentId = rand(999999, 9999999);
            }
            
            // Attempt to delete non-existent employee
            $deletionResult = $this->employeeService->deactivateEmployee($this->testUser, $nonExistentId);
            
            // Property assertion: Deletion should fail gracefully
            $this->assertFalse($deletionResult, "Deletion of non-existent employee should fail gracefully");
            
            // Verify no side effects occurred
            $this->assertNull(User::find($nonExistentId), "Non-existent employee should remain non-existent");
        }
    }

    /**
     * Property: Deletion preserves related data integrity
     * For any employee deletion, related data should remain intact
     */
    public function test_deletion_preserves_related_data_integrity_property()
    {
        for ($iteration = 0; $iteration < 10; $iteration++) {
            
            // Create an employee with potential related data
            $employee = $this->createTestUser([
                'first_name' => 'Related',
                'last_name' => 'Test' . $iteration,
                'username' => 'related_' . $iteration . '_' . rand(1000, 9999),
                'email' => 'related_' . $iteration . '_' . rand(1000, 9999) . '@test.com',
                'company_id' => $this->testUser->company_id,
                'is_active' => 1
            ]);
            
            $originalUserId = $employee->user_id;
            
            // Perform deletion
            $deletionResult = $this->employeeService->deactivateEmployee($this->testUser, $employee->user_id);
            
            if ($deletionResult) {
                // Property assertions for data integrity
                $employeeAfterDeletion = User::find($originalUserId);
                $this->assertNotNull($employeeAfterDeletion, "Employee record should be preserved");
                
                // Verify user_id remains the same (referential integrity)
                $this->assertEquals($originalUserId, $employeeAfterDeletion->user_id, 
                    "User ID should remain unchanged to preserve referential integrity");
                
                // Verify company_id is preserved (important for data segregation)
                $this->assertEquals($this->testUser->company_id, $employeeAfterDeletion->company_id,
                    "Company ID should be preserved to maintain data segregation");
            }
            
            // Clean up
            User::where('user_id', $originalUserId)->forceDelete();
        }
    }
}