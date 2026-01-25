<?php

namespace Tests\Feature\Employee;

use Tests\TestCase;
use App\Models\User;
use App\Services\SimplePermissionService;
use App\Services\EmployeeManagementService;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Facades\Auth;

/**
 * Property-based tests for Employee API Response formatting
 * 
 * Tests Properties 40 and 41 from the design document:
 * - Property 40: تنسيق الاستجابات الناجحة (Successful Response Formatting)
 * - Property 41: رسائل الخطأ الواضحة (Clear Error Messages)
 */
class EmployeeApiResponsePropertyTest extends TestCase
{
    use WithoutMiddleware;

    /**
     * Property 40: تنسيق الاستجابات الناجحة
     * 
     * Tests that all successful API responses follow the standardized format:
     * - Contains 'success' field (boolean true)
     * - Contains 'message' field (string in Arabic)
     * - Contains 'data' field when applicable
     * - Uses appropriate HTTP status codes
     * 
     * @test
     */
    public function property_successful_responses_have_consistent_format()
    {
        // Test the ApiResponseTrait methods directly
        $iterations = 20;
        
        for ($i = 0; $i < $iterations; $i++) {
            // Create a mock controller that uses ApiResponseTrait
            $controller = new class {
                use \App\Traits\ApiResponseTrait;
                
                public function testSuccessResponse() {
                    return $this->successResponse(['test' => 'data'], 'تم بنجاح');
                }
                
                public function testPaginatedResponse() {
                    // Mock paginated data
                    $mockPaginator = new \Illuminate\Pagination\LengthAwarePaginator(
                        collect([['id' => 1], ['id' => 2]]),
                        10,
                        5,
                        1
                    );
                    return $this->paginatedResponse($mockPaginator, 'تم جلب البيانات بنجاح');
                }
            };
            
            // Test success response
            $response = $controller->testSuccessResponse();
            $data = json_decode($response->getContent(), true);
            
            // Property: All successful responses must have consistent structure
            $this->assertTrue(
                isset($data['success']) && $data['success'] === true,
                "Response must have 'success' field set to true. Response: " . json_encode($data)
            );
            
            $this->assertTrue(
                isset($data['message']) && is_string($data['message']) && !empty($data['message']),
                "Response must have non-empty 'message' field. Response: " . json_encode($data)
            );
            
            // Check that message is in Arabic (contains Arabic characters)
            $this->assertTrue(
                preg_match('/[\x{0600}-\x{06FF}]/u', $data['message']) > 0,
                "Message should be in Arabic. Message: " . $data['message']
            );
            
            // For successful responses, status should be 2xx
            $this->assertTrue(
                $response->getStatusCode() >= 200 && $response->getStatusCode() < 300,
                "Successful responses should have 2xx status code. Got: " . $response->getStatusCode()
            );
            
            // Test paginated response
            $paginatedResponse = $controller->testPaginatedResponse();
            $paginatedData = json_decode($paginatedResponse->getContent(), true);
            
            $this->assertTrue(
                isset($paginatedData['success']) && $paginatedData['success'] === true,
                "Paginated response must have 'success' field set to true"
            );
            
            $this->assertTrue(
                isset($paginatedData['data']['pagination']),
                "Paginated response must have pagination metadata"
            );
        }
        
        $this->addToAssertionCount($iterations * 6); // 6 assertions per iteration
    }

    /**
     * Property 41: رسائل الخطأ الواضحة
     * 
     * Tests that all error responses have clear, descriptive Arabic messages:
     * - Contains 'success' field (boolean false)
     * - Contains 'message' field with clear Arabic error description
     * - Uses appropriate HTTP status codes
     * - May contain 'errors' field for validation errors
     * 
     * @test
     */
    public function property_error_responses_have_clear_arabic_messages()
    {
        // Test the ApiResponseTrait error methods directly
        $iterations = 20;
        
        for ($i = 0; $i < $iterations; $i++) {
            // Create a mock controller that uses ApiResponseTrait
            $controller = new class {
                use \App\Traits\ApiResponseTrait;
                
                public function testErrorResponse() {
                    return $this->errorResponse('حدث خطأ في النظام', 400);
                }
                
                public function testForbiddenResponse() {
                    return $this->forbiddenResponse('ليس لديك صلاحية للوصول');
                }
                
                public function testNotFoundResponse() {
                    return $this->notFoundResponse('العنصر غير موجود');
                }
                
                public function testValidationErrorResponse() {
                    $exception = new \Illuminate\Validation\ValidationException(
                        \Illuminate\Support\Facades\Validator::make([], ['name' => 'required'])
                    );
                    return $this->validationErrorResponse($exception);
                }
            };
            
            // Test different error response types
            $errorMethods = ['testErrorResponse', 'testForbiddenResponse', 'testNotFoundResponse'];
            $method = $errorMethods[array_rand($errorMethods)];
            
            $response = $controller->$method();
            $data = json_decode($response->getContent(), true);
            
            // Property: All error responses must have consistent structure
            $this->assertTrue(
                isset($data['success']) && $data['success'] === false,
                "Error response must have 'success' field set to false. Response: " . json_encode($data)
            );
            
            $this->assertTrue(
                isset($data['message']) && is_string($data['message']) && !empty($data['message']),
                "Error response must have non-empty 'message' field. Response: " . json_encode($data)
            );
            
            // Check that error message is in Arabic (contains Arabic characters)
            $this->assertTrue(
                preg_match('/[\x{0600}-\x{06FF}]/u', $data['message']) > 0,
                "Error message should be in Arabic. Message: " . $data['message']
            );
            
            // Error message should be descriptive (not just generic)
            $this->assertGreaterThan(
                5,
                mb_strlen($data['message']),
                "Error message should be descriptive, not just a short generic message. Message: " . $data['message']
            );
            
            // Check appropriate status codes for different error types
            $statusCode = $response->getStatusCode();
            $this->assertTrue(
                in_array($statusCode, [400, 401, 403, 404, 422, 500]),
                "Error response should use appropriate HTTP status code. Got: " . $statusCode
            );
        }
        
        $this->addToAssertionCount($iterations * 5); // 5 assertions per iteration
    }

    /**
     * Property: Response format consistency across all endpoints
     * 
     * Tests that response format is consistent regardless of the endpoint called
     * 
     * @test
     */
    public function property_response_format_consistency_across_endpoints()
    {
        // Test the ApiResponseTrait methods for consistency
        $iterations = 15;
        
        for ($i = 0; $i < $iterations; $i++) {
            // Create a mock controller that uses ApiResponseTrait
            $controller = new class {
                use \App\Traits\ApiResponseTrait;
                
                public function getRandomResponse($type) {
                    switch ($type) {
                        case 'success':
                            return $this->successResponse(['data' => 'test'], 'تم بنجاح');
                        case 'error':
                            return $this->errorResponse('حدث خطأ', 400);
                        case 'forbidden':
                            return $this->forbiddenResponse('ليس لديك صلاحية');
                        case 'notfound':
                            return $this->notFoundResponse('غير موجود');
                        default:
                            return $this->successResponse(null, 'رسالة افتراضية');
                    }
                }
            };
            
            $responseTypes = ['success', 'error', 'forbidden', 'notfound'];
            $type = $responseTypes[array_rand($responseTypes)];
            
            $response = $controller->getRandomResponse($type);
            $data = json_decode($response->getContent(), true);
            
            // Property: All responses must have the basic structure
            $this->assertTrue(
                isset($data['success']) && is_bool($data['success']),
                "All responses must have boolean 'success' field. Type: $type"
            );
            
            $this->assertTrue(
                isset($data['message']) && is_string($data['message']),
                "All responses must have string 'message' field. Type: $type"
            );
            
            // Check Arabic message
            $this->assertTrue(
                preg_match('/[\x{0600}-\x{06FF}]/u', $data['message']) > 0,
                "All messages should be in Arabic. Type: $type, Message: " . $data['message']
            );
        }
        
        $this->addToAssertionCount($iterations * 3); // 3 assertions per iteration
    }

    /**
     * Property: Pagination responses have consistent structure
     * 
     * Tests that paginated responses follow the expected format
     * 
     * @test
     */
    public function property_pagination_responses_have_consistent_structure()
    {
        // Test the ApiResponseTrait pagination method directly
        $iterations = 10;
        
        for ($i = 0; $i < $iterations; $i++) {
            // Create a mock controller that uses ApiResponseTrait
            $controller = new class {
                use \App\Traits\ApiResponseTrait;
                
                public function testPaginatedResponse($page, $perPage, $total) {
                    // Create mock paginated data
                    $items = collect();
                    for ($j = 0; $j < min($perPage, $total); $j++) {
                        $items->push(['id' => $j + 1, 'name' => 'Item ' . ($j + 1)]);
                    }
                    
                    $mockPaginator = new \Illuminate\Pagination\LengthAwarePaginator(
                        $items,
                        $total,
                        $perPage,
                        $page,
                        ['path' => '/api/employees']
                    );
                    
                    return $this->paginatedResponse($mockPaginator, 'تم جلب البيانات بنجاح');
                }
            };
            
            // Generate random pagination parameters
            $page = rand(1, 5);
            $perPage = rand(5, 20);
            $total = rand(50, 200);
            
            $response = $controller->testPaginatedResponse($page, $perPage, $total);
            $data = json_decode($response->getContent(), true);
            
            // Property: Paginated responses must have pagination structure
            $this->assertTrue(
                isset($data['data']['pagination']),
                "Paginated responses must have pagination metadata"
            );
            
            $pagination = $data['data']['pagination'];
            $requiredPaginationFields = [
                'current_page', 'last_page', 'per_page', 'total', 'from', 'to'
            ];
            
            foreach ($requiredPaginationFields as $field) {
                $this->assertTrue(
                    isset($pagination[$field]),
                    "Pagination must include '$field' field"
                );
                
                $this->assertTrue(
                    is_numeric($pagination[$field]) || is_null($pagination[$field]),
                    "Pagination field '$field' must be numeric or null"
                );
            }
            
            // Property: Data should be an array
            $this->assertTrue(
                isset($data['data']['data']) && is_array($data['data']['data']),
                "Paginated response data should be an array"
            );
            
            // Property: Success field should be true
            $this->assertTrue(
                isset($data['success']) && $data['success'] === true,
                "Paginated response should have success=true"
            );
            
            // Property: Message should be in Arabic
            $this->assertTrue(
                preg_match('/[\x{0600}-\x{06FF}]/u', $data['message']) > 0,
                "Paginated response message should be in Arabic"
            );
        }
        
        $this->addToAssertionCount($iterations * 10); // Multiple assertions per iteration
    }
}