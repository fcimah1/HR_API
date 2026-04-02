<?php

namespace Tests\Feature\Employee;

use Tests\TestCase;
use App\Models\User;
use App\Services\EmployeeManagementService;
use App\Services\SimplePermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

/**
 * Property-Based Tests for Employee Documents Functionality
 * 
 * Tests Property 14: تضمين المستندات
 * Verifies Requirement 3.3: يجب أن يتضمن الملف الشخصي للموظف قائمة بالمستندات المرفوعة
 */
class EmployeeDocumentsPropertyTest extends TestCase
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
     * Property 14: تضمين المستندات
     * 
     * خاصية: لكل موظف، يجب أن تحتوي استجابة getEmployeeDocuments على:
     * 1. معلومات الموظف الأساسية
     * 2. قائمة المستندات مع التفاصيل المطلوبة
     * 3. ملخص إحصائي للمستندات
     * 4. التحقق من الصلاحيات قبل الإرجاع
     * 
     * @test
     */
    public function property_employee_documents_inclusion_structure()
    {
        // Property-based testing with 100 iterations
        for ($i = 0; $i < 100; $i++) {
            // Arrange: Create test users with different roles
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
                'username' => 'emp_' . $i . '_' . time(),
                'user_type' => 'staff',
                'company_id' => $companyOwner->user_id,
                'user_role_id' => 1,
                'is_active' => 1
            ]);

            // Create user details for the employee
            \App\Models\UserDetails::factory()->create([
                'company_id' => $companyOwner->user_id,
                'user_id' => $employee->user_id,
                'employee_id' => 'EMP' . str_pad($employee->user_id, 4, '0', STR_PAD_LEFT),
                'reporting_manager' => $companyOwner->user_id,
            ]);

            // Act: Get employee documents
            $result = $this->employeeService->getEmployeeDocuments($companyOwner, $employee->user_id);

            // Assert: Property verification
            $this->assertNotNull($result, "Documents result should not be null for iteration {$i}");
            $this->assertIsArray($result, "Documents result should be an array for iteration {$i}");

            // Property 1: Employee information must be included
            $this->assertArrayHasKey('employee', $result, "Employee info must be included for iteration {$i}");
            $this->assertArrayHasKey('id', $result['employee'], "Employee ID must be included for iteration {$i}");
            $this->assertArrayHasKey('name', $result['employee'], "Employee name must be included for iteration {$i}");
            $this->assertArrayHasKey('employee_id', $result['employee'], "Employee ID number must be included for iteration {$i}");

            // Property 2: Documents list must be included
            $this->assertArrayHasKey('documents', $result, "Documents list must be included for iteration {$i}");
            $this->assertIsArray($result['documents'], "Documents must be an array for iteration {$i}");

            // Property 3: Each document must have required fields
            foreach ($result['documents'] as $docIndex => $document) {
                $this->assertArrayHasKey('id', $document, "Document ID required for doc {$docIndex} in iteration {$i}");
                $this->assertArrayHasKey('document_type', $document, "Document type required for doc {$docIndex} in iteration {$i}");
                $this->assertArrayHasKey('file_name', $document, "File name required for doc {$docIndex} in iteration {$i}");
                $this->assertArrayHasKey('file_path', $document, "File path required for doc {$docIndex} in iteration {$i}");
                $this->assertArrayHasKey('file_size', $document, "File size required for doc {$docIndex} in iteration {$i}");
                $this->assertArrayHasKey('uploaded_at', $document, "Upload date required for doc {$docIndex} in iteration {$i}");
                $this->assertArrayHasKey('uploaded_by', $document, "Uploader info required for doc {$docIndex} in iteration {$i}");

                // Property 4: Document types must be valid
                $validTypes = ['CV', 'ID_COPY', 'CONTRACT', 'CERTIFICATE'];
                $this->assertContains($document['document_type'], $validTypes, 
                    "Document type must be valid for doc {$docIndex} in iteration {$i}");

                // Property 5: File paths must be properly formatted
                $this->assertStringStartsWith('/storage/documents/', $document['file_path'], 
                    "File path must start with /storage/documents/ for doc {$docIndex} in iteration {$i}");

                // Property 6: File names must contain employee username
                $this->assertStringContainsString($employee->username, $document['file_name'], 
                    "File name must contain employee username for doc {$docIndex} in iteration {$i}");
            }

            // Property 7: Total count must match documents array length
            $this->assertArrayHasKey('total', $result, "Total count must be included for iteration {$i}");
            $this->assertEquals(count($result['documents']), $result['total'], 
                "Total count must match documents array length for iteration {$i}");

            // Property 8: Summary must be included with required fields
            $this->assertArrayHasKey('summary', $result, "Summary must be included for iteration {$i}");
            $this->assertArrayHasKey('total_size', $result['summary'], "Total size must be included for iteration {$i}");
            $this->assertArrayHasKey('document_types', $result['summary'], "Document types must be included for iteration {$i}");
            $this->assertIsArray($result['summary']['document_types'], "Document types must be an array for iteration {$i}");

            // Property 9: Document types in summary must be unique
            $documentTypes = array_column($result['documents'], 'document_type');
            $uniqueTypes = array_unique($documentTypes);
            $this->assertEquals(count($uniqueTypes), count($result['summary']['document_types']), 
                "Document types in summary must be unique for iteration {$i}");

            // Property 10: Employee ID in result must match requested employee
            $this->assertEquals($employee->user_id, $result['employee']['id'], 
                "Employee ID in result must match requested employee for iteration {$i}");
        }
    }

    /**
     * Property: Permission-based access control for documents
     * 
     * خاصية: يجب أن يتم التحقق من الصلاحيات قبل إرجاع المستندات
     * 
     * @test
     */
    public function property_documents_permission_based_access()
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
                'is_active' => 1
            ]);

            $employee2 = User::factory()->create([
                'first_name' => 'موظف',
                'last_name' => 'ثاني_' . $i,
                'username' => 'emp2_' . $i . '_' . time(),
                'user_type' => 'staff',
                'company_id' => $companyOwner->user_id,
                'user_role_id' => 1,
                'is_active' => 1
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

            // Property 1: Company owner can access any employee's documents
            $result = $this->employeeService->getEmployeeDocuments($companyOwner, $employee1->user_id);
            $this->assertNotNull($result, "Company owner should access employee documents for iteration {$i}");

            // Property 2: Employee can access their own documents
            $result = $this->employeeService->getEmployeeDocuments($employee1, $employee1->user_id);
            $this->assertNotNull($result, "Employee should access their own documents for iteration {$i}");

            // Property 3: Employee cannot access other employee's documents (without proper hierarchy)
            $result = $this->employeeService->getEmployeeDocuments($employee1, $employee2->user_id);
            $this->assertNull($result, "Employee should not access other employee's documents for iteration {$i}");

            // Property 4: Non-existent employee returns null
            $nonExistentId = 999999;
            $result = $this->employeeService->getEmployeeDocuments($companyOwner, $nonExistentId);
            $this->assertNull($result, "Non-existent employee should return null for iteration {$i}");
        }
    }

    /**
     * Property: Document data consistency and format validation
     * 
     * خاصية: يجب أن تكون بيانات المستندات متسقة ومنسقة بشكل صحيح
     * 
     * @test
     */
    public function property_documents_data_consistency()
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
                'is_active' => 1
            ]);

            \App\Models\UserDetails::factory()->create([
                'company_id' => $companyOwner->user_id,
                'user_id' => $employee->user_id,
                'employee_id' => 'EMP' . str_pad($employee->user_id, 4, '0', STR_PAD_LEFT),
                'reporting_manager' => $companyOwner->user_id,
            ]);

            // Act
            $result = $this->employeeService->getEmployeeDocuments($companyOwner, $employee->user_id);

            // Assert: Data consistency properties
            $this->assertNotNull($result, "Result should not be null for iteration {$i}");

            // Property 1: All document IDs must be unique
            $documentIds = array_column($result['documents'], 'id');
            $uniqueIds = array_unique($documentIds);
            $this->assertEquals(count($documentIds), count($uniqueIds), 
                "All document IDs must be unique for iteration {$i}");

            // Property 2: All file sizes must be valid format
            foreach ($result['documents'] as $docIndex => $document) {
                $this->assertMatchesRegularExpression('/^\d+\s+KB$/', $document['file_size'], 
                    "File size must be in 'X KB' format for doc {$docIndex} in iteration {$i}");
            }

            // Property 3: All upload dates must be valid ISO format
            foreach ($result['documents'] as $docIndex => $document) {
                $this->assertNotFalse(\DateTime::createFromFormat(\DateTime::ATOM, $document['uploaded_at']), 
                    "Upload date must be valid ISO format for doc {$docIndex} in iteration {$i}");
            }

            // Property 4: Total size calculation must be consistent
            $calculatedSize = 0;
            foreach ($result['documents'] as $document) {
                $size = (int) str_replace(' KB', '', $document['file_size']);
                $calculatedSize += $size;
            }
            $expectedTotalSize = $calculatedSize . ' KB';
            $this->assertEquals($expectedTotalSize, $result['summary']['total_size'], 
                "Total size calculation must be consistent for iteration {$i}");

            // Property 5: Employee name must be properly formatted
            $expectedName = $employee->first_name . ' ' . $employee->last_name;
            $this->assertEquals($expectedName, $result['employee']['name'], 
                "Employee name must be properly formatted for iteration {$i}");
        }
    }
}