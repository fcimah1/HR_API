<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\CreateEmployeeRequest;
use App\Http\Requests\Employee\UpdateEmployeeRequest;
use App\Http\Resources\EmployeeResource;
use App\Http\Resources\EmployeeListResource;
use App\Http\Resources\EmployeeStatisticsResource;
use App\Services\EmployeeManagementService;
use App\Services\SimplePermissionService;
use App\DTOs\Employee\EmployeeFilterDTO;
use App\DTOs\Employee\CreateEmployeeDTO;
use App\DTOs\Employee\UpdateEmployeeDTO;
use App\Http\Requests\Employee\GetBackupEmployeesRequest;
use App\Services\EmployeeService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;


class EmployeeController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly EmployeeManagementService $employeeService,
        private readonly SimplePermissionService $permissionService,
        private readonly EmployeeService $employeeManagementService,

    ) {}

    /**
     * @OA\Get(
     *     path="/api/employees",
     *     summary="Get employees list with filtering and pagination",
     *     description="Retrieve paginated list of employees with advanced filtering options",
     *     tags={"Employee"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="search", in="query", description="Search in name, email, employee_id", @OA\Schema(type="string")),
     *     @OA\Parameter(name="department_id", in="query", description="Filter by department", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="designation_id", in="query", description="Filter by designation", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="is_active", in="query", description="Filter by active status", @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="from_date", in="query", description="Filter by joining date from", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="to_date", in="query", description="Filter by joining date to", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="page", in="query", description="Page number", @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="limit", in="query", description="Items per page", @OA\Schema(type="integer", default=20)),
     *     @OA\Response(
     *         response=200,
     *         description="Employees retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم جلب الموظفين بنجاح"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="data", type="array", @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="user_id", type="integer", example=1),
     *                     @OA\Property(property="first_name", type="string", example="أحمد"),
     *                     @OA\Property(property="last_name", type="string", example="محمد"),
     *                     @OA\Property(property="email", type="string", example="ahmed@company.com"),
     *                     @OA\Property(property="user_type", type="string", example="staff"),
     *                     @OA\Property(property="is_active", type="boolean", example=true)
     *                 )),
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="last_page", type="integer", example=5),
     *                 @OA\Property(property="per_page", type="integer", example=20),
     *                 @OA\Property(property="total", type="integer", example=100)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=403, description="ليس لديك صلاحية لعرض الموظفين")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Check permission
            if (!$this->permissionService->checkPermission($user, 'employee.view')) {
                return $this->forbiddenResponse('ليس لديك صلاحية لعرض الموظفين');
            }
            
            // Create filter DTO from request
            $filters = EmployeeFilterDTO::fromArray($request->all());
            
            // Get employees list
            $employees = $this->employeeService->getEmployeesList($user, $filters);
            
            return $this->paginatedResponse(
                $employees, 
                'تم جلب الموظفين بنجاح', 
                EmployeeListResource::class
            );
            
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Exception $e) {
            return $this->handleException($e, 'EmployeeController::index');
        }
    }

    /**
     * @OA\Post(
     *     path="/api/employees",
     *     summary="Create new employee",
     *     description="Create a new employee with complete profile information",
     *     tags={"Employee"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"first_name", "last_name", "email", "username", "password", "department_id", "designation_id"},
     *             @OA\Property(property="first_name", type="string", example="محمد"),
     *             @OA\Property(property="last_name", type="string", example="أحمد"),
     *             @OA\Property(property="email", type="string", format="email", example="employee@company.com"),
     *             @OA\Property(property="username", type="string", example="mohammed.ahmed"),
     *             @OA\Property(property="password", type="string", example="password123"),
     *             @OA\Property(property="contact_number", type="string", example="01234567890"),
     *             @OA\Property(property="gender", type="string", enum={"M", "F"}, example="M"),
     *             @OA\Property(property="department_id", type="integer", example=1),
     *             @OA\Property(property="designation_id", type="integer", example=1),
     *             @OA\Property(property="basic_salary", type="number", format="float", example=5000.00),
     *             @OA\Property(property="date_of_joining", type="string", format="date", example="2024-01-15"),
     *             @OA\Property(property="date_of_birth", type="string", format="date", example="1990-01-01")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Employee created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم إنشاء الموظف بنجاح"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="user_id", type="integer", example=1),
     *                 @OA\Property(property="first_name", type="string", example="أحمد"),
     *                 @OA\Property(property="last_name", type="string", example="محمد"),
     *                 @OA\Property(property="email", type="string", example="ahmed@company.com"),
     *                 @OA\Property(property="user_type", type="string", example="staff"),
     *                 @OA\Property(property="is_active", type="boolean", example=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation errors"),
     *     @OA\Response(response=403, description="ليس لديك صلاحية لإضافة موظفين")
     * )
     */
    public function store(CreateEmployeeRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Check permission
            if (!$this->permissionService->checkPermission($user, 'employee.create')) {
                return $this->forbiddenResponse('ليس لديك صلاحية لإضافة موظفين');
            }
            
            // Create DTO from validated request
            $createData = CreateEmployeeDTO::fromArray($request->validated());
            
            // Create employee
            $employee = $this->employeeService->createEmployee($user, $createData);
            
            if (!$employee) {
                return $this->serverErrorResponse('فشل في إنشاء الموظف');
            }
            
            return $this->successResponse(
                new EmployeeResource($employee),
                'تم إنشاء الموظف بنجاح',
                201
            );
            
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Exception $e) {
            return $this->handleException($e, 'EmployeeController::store');
        }
    }

    /**
     * @OA\Get(
     *     path="/api/employees/{id}",
     *     summary="Get employee details",
     *     description="Retrieve detailed information about a specific employee",
     *     tags={"Employee"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Employee details retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم جلب بيانات الموظف بنجاح"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="user_id", type="integer", example=1),
     *                 @OA\Property(property="first_name", type="string", example="أحمد"),
     *                 @OA\Property(property="last_name", type="string", example="محمد"),
     *                 @OA\Property(property="email", type="string", example="ahmed@company.com"),
     *                 @OA\Property(property="user_type", type="string", example="staff"),
     *                 @OA\Property(property="is_active", type="boolean", example=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="الموظف غير موجود"),
     *     @OA\Response(response=403, description="ليس لديك صلاحية لعرض بيانات هذا الموظف")
     * )
     */
    public function show(int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Get employee details
            $employee = $this->employeeService->getEmployeeDetails($user, $id);
            
            if (!$employee) {
                return $this->notFoundResponse('الموظف غير موجود أو ليس لديك صلاحية لعرض بياناته');
            }
            
            return $this->successResponse(
                new EmployeeResource($employee),
                'تم جلب بيانات الموظف بنجاح'
            );
            
        } catch (\Exception $e) {
            return $this->handleException($e, 'EmployeeController::show');
        }
    }

    /**
     * @OA\Put(
     *     path="/api/employees/{id}",
     *     summary="Update employee information",
     *     description="Update existing employee profile information",
     *     tags={"Employee"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="first_name", type="string", example="محمد"),
     *             @OA\Property(property="last_name", type="string", example="أحمد"),
     *             @OA\Property(property="email", type="string", format="email", example="employee@company.com"),
     *             @OA\Property(property="contact_number", type="string", example="01234567890"),
     *             @OA\Property(property="department_id", type="integer", example=1),
     *             @OA\Property(property="designation_id", type="integer", example=1),
     *             @OA\Property(property="basic_salary", type="number", format="float", example=5500.00),
     *             @OA\Property(property="is_active", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Employee updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم تحديث بيانات الموظف بنجاح"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="user_id", type="integer", example=1),
     *                 @OA\Property(property="first_name", type="string", example="أحمد"),
     *                 @OA\Property(property="last_name", type="string", example="محمد"),
     *                 @OA\Property(property="email", type="string", example="ahmed@company.com"),
     *                 @OA\Property(property="user_type", type="string", example="staff"),
     *                 @OA\Property(property="is_active", type="boolean", example=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="الموظف غير موجود"),
     *     @OA\Response(response=403, description="ليس لديك صلاحية لتعديل هذا الموظف")
     * )
     */
    public function update(UpdateEmployeeRequest $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Create DTO from validated request
            $updateData = UpdateEmployeeDTO::fromArray($request->validated());
            
            // Update employee
            $employee = $this->employeeService->updateEmployee($user, $id, $updateData);
            
            if (!$employee) {
                return $this->notFoundResponse('الموظف غير موجود أو ليس لديك صلاحية لتعديله');
            }
            
            return $this->successResponse(
                new EmployeeResource($employee),
                'تم تحديث بيانات الموظف بنجاح'
            );
            
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Exception $e) {
            return $this->handleException($e, 'EmployeeController::update');
        }
    }

    /**
     * Remove the specified employee.
     *
     * @OA\Delete(
     *     path="/api/employees/{id}",
     *     operationId="deleteEmployee",
     *     tags={"Employee"},
     *     summary="Delete employee",
     *     description="Soft deletes an employee (sets is_active to false)",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Employee ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Employee deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم حذف الموظف بنجاح")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Insufficient permissions"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Employee not found"
     *     )
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Check permission
            if (!$this->permissionService->checkPermission($user, 'employee.delete')) {
                return $this->forbiddenResponse('ليس لديك صلاحية لحذف الموظفين');
            }
            
            // Deactivate employee
            $success = $this->employeeService->deactivateEmployee($user, $id);
            
            if (!$success) {
                return $this->notFoundResponse('الموظف غير موجود أو ليس لديك صلاحية لحذفه');
            }
            
            return $this->successResponse(null, 'تم إلغاء تفعيل الموظف بنجاح');
            
        } catch (\Exception $e) {
            return $this->handleException($e, 'EmployeeController::destroy');
        }
    }

    /**
     * @OA\Get(
     *     path="/api/employees/search",
     *     summary="Search employees",
     *     description="Quick search employees by name, email, or employee ID",
     *     tags={"Employee"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="q", in="query", required=true, description="Search query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="limit", in="query", description="Number of results", @OA\Schema(type="integer", default=50)),
     *     @OA\Response(
     *         response=200,
     *         description="Search results retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم البحث بنجاح"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="employees", type="array", @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="user_id", type="integer", example=1),
     *                     @OA\Property(property="first_name", type="string", example="أحمد"),
     *                     @OA\Property(property="last_name", type="string", example="محمد"),
     *                     @OA\Property(property="email", type="string", example="ahmed@company.com"),
     *                     @OA\Property(property="user_type", type="string", example="staff"),
     *                     @OA\Property(property="is_active", type="boolean", example=true)
     *                 )),
     *                 @OA\Property(property="total", type="integer", example=15),
     *                 @OA\Property(property="query", type="string", example="محمد")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=400, description="نص البحث مطلوب")
     * )
     */
    public function search(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $query = $request->get('q');
            
            // Validate required parameter and check for empty/whitespace
            if (!$request->has('q') || empty(trim($query))) {
                return $this->errorResponse('نص البحث مطلوب', 400);
            }
            
            // Check permission
            if (!$this->permissionService->checkPermission($user, 'employee.view')) {
                return $this->forbiddenResponse('ليس لديك صلاحية للبحث في الموظفين');
            }
            
            $options = [
                'limit' => $request->get('limit', 50)
            ];
            
            // Search employees
            $results = $this->employeeService->searchEmployees($user, $query, $options);
            
            return $this->successResponse([
                'employees' => EmployeeListResource::collection($results['employees']),
                'total' => $results['total'],
                'query' => $results['query']
            ], 'تم البحث بنجاح');
            
        } catch (\Exception $e) {
            return $this->handleException($e, 'EmployeeController::search');
        }
    }

    /**
     * @OA\Get(
     *     path="/api/employees/statistics",
     *     summary="Get employee statistics",
     *     description="Retrieve comprehensive employee statistics and analytics",
     *     tags={"Employee"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Statistics retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم جلب الإحصائيات بنجاح"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="total_employees", type="integer", example=150),
     *                 @OA\Property(property="active_employees", type="integer", example=140),
     *                 @OA\Property(property="inactive_employees", type="integer", example=10),
     *                 @OA\Property(property="departments_count", type="integer", example=8),
     *                 @OA\Property(property="designations_count", type="integer", example=15),
     *                 @OA\Property(property="average_salary", type="number", format="float", example=4500.50),
     *                 @OA\Property(property="by_department", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="by_designation", type="array", @OA\Items(type="object"))
     *             )
     *         )
     *     ),
     *     @OA\Response(response=403, description="ليس لديك صلاحية لعرض الإحصائيات")
     * )
     */
    public function statistics(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Check permission
            if (!$this->permissionService->checkPermission($user, 'employee.statistics')) {
                return $this->forbiddenResponse('ليس لديك صلاحية لعرض الإحصائيات');
            }
            
            // Get statistics
            $statistics = $this->employeeService->getEmployeeStatistics($user);
            
            return $this->successResponse(
                new EmployeeStatisticsResource($statistics),
                'تم جلب الإحصائيات بنجاح'
            );
            
        } catch (\Exception $e) {
            return $this->handleException($e, 'EmployeeController::statistics');
        }
    }

    /**
     * @OA\Get(
     *     path="/api/employees/{id}/documents",
     *     summary="Get employee documents",
     *     description="Retrieve all documents uploaded for specific employee",
     *     tags={"Employee"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Documents retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم جلب المستندات بنجاح"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="documents", type="array", @OA\Items(type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="document_type", type="string", example="CV"),
     *                     @OA\Property(property="file_name", type="string", example="cv_mohammed.pdf"),
     *                     @OA\Property(property="file_path", type="string", example="/storage/documents/cv_mohammed.pdf"),
     *                     @OA\Property(property="uploaded_at", type="string", format="date-time")
     *                 )),
     *                 @OA\Property(property="total", type="integer", example=5)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="الموظف غير موجود"),
     *     @OA\Response(response=403, description="ليس لديك صلاحية لعرض مستندات هذا الموظف")
     * )
     */
    public function getEmployeeDocuments(int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Get employee documents using service
            $result = $this->employeeService->getEmployeeDocuments($user, $id);
            
            if (!$result) {
                return $this->notFoundResponse('الموظف غير موجود أو ليس لديك صلاحية لعرض مستنداته');
            }
            
            return $this->successResponse($result, 'تم جلب المستندات بنجاح');
            
        } catch (\Exception $e) {
            return $this->handleException($e, 'EmployeeController::getEmployeeDocuments');
        }
    }

    /**
     * @OA\Get(
     *     path="/api/employees/{id}/leave-balance",
     *     summary="Get employee leave balance",
     *     description="Retrieve current leave balance for specific employee",
     *     tags={"Employee"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Leave balance retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم جلب رصيد الإجازات بنجاح"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="annual_leave", type="object",
     *                     @OA\Property(property="total", type="integer", example=30),
     *                     @OA\Property(property="used", type="integer", example=12),
     *                     @OA\Property(property="remaining", type="integer", example=18)
     *                 ),
     *                 @OA\Property(property="sick_leave", type="object",
     *                     @OA\Property(property="total", type="integer", example=15),
     *                     @OA\Property(property="used", type="integer", example=3),
     *                     @OA\Property(property="remaining", type="integer", example=12)
     *                 ),
     *                 @OA\Property(property="emergency_leave", type="object",
     *                     @OA\Property(property="total", type="integer", example=5),
     *                     @OA\Property(property="used", type="integer", example=1),
     *                     @OA\Property(property="remaining", type="integer", example=4)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="الموظف غير موجود"),
     *     @OA\Response(response=403, description="ليس لديك صلاحية لعرض رصيد إجازات هذا الموظف")
     * )
     */
    public function getEmployeeLeaveBalance(int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Get employee leave balance using service
            $result = $this->employeeService->getEmployeeLeaveBalance($user, $id);
            
            if (!$result) {
                return $this->notFoundResponse('الموظف غير موجود أو ليس لديك صلاحية لعرض رصيد إجازاته');
            }
            
            return $this->successResponse($result, 'تم جلب رصيد الإجازات بنجاح');
            
        } catch (\Exception $e) {
            return $this->handleException($e, 'EmployeeController::getEmployeeLeaveBalance');
        }
    }

    /**
     * @OA\Get(
     *     path="/api/employees/{id}/attendance",
     *     summary="Get employee attendance records",
     *     description="Retrieve recent attendance records for specific employee",
     *     tags={"Employee"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="limit", in="query", description="Number of records to return", @OA\Schema(type="integer", default=30)),
     *     @OA\Response(
     *         response=200,
     *         description="Attendance records retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم جلب سجل الحضور بنجاح"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="attendance", type="array", @OA\Items(type="object",
     *                     @OA\Property(property="date", type="string", format="date", example="2024-01-15"),
     *                     @OA\Property(property="check_in", type="string", format="time", example="08:30:00"),
     *                     @OA\Property(property="check_out", type="string", format="time", example="17:00:00"),
     *                     @OA\Property(property="hours_worked", type="number", format="float", example=8.5),
     *                     @OA\Property(property="status", type="string", example="present")
     *                 )),
     *                 @OA\Property(property="summary", type="object",
     *                     @OA\Property(property="total_days", type="integer", example=30),
     *                     @OA\Property(property="present_days", type="integer", example=28),
     *                     @OA\Property(property="absent_days", type="integer", example=2),
     *                     @OA\Property(property="average_hours", type="number", format="float", example=8.2)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="الموظف غير موجود"),
     *     @OA\Response(response=403, description="ليس لديك صلاحية لعرض سجل حضور هذا الموظف")
     * )
     */
    public function getEmployeeAttendance(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $options = [
                'limit' => $request->get('limit', 30)
            ];
            
            // Get employee attendance using service
            $result = $this->employeeService->getEmployeeAttendance($user, $id, $options);
            
            if (!$result) {
                return $this->notFoundResponse('الموظف غير موجود أو ليس لديك صلاحية لعرض سجل حضوره');
            }
            
            return $this->successResponse($result, 'تم جلب سجل الحضور بنجاح');
            
        } catch (\Exception $e) {
            return $this->handleException($e, 'EmployeeController::getEmployeeAttendance');
        }
    }

    /**
     * @OA\Get(
     *     path="/api/employees/{id}/salary-details",
     *     summary="Get employee salary details",
     *     description="Retrieve salary history and details for specific employee",
     *     tags={"Employee"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="limit", in="query", description="Number of records to return", @OA\Schema(type="integer", default=12)),
     *     @OA\Response(
     *         response=200,
     *         description="Salary details retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم جلب تفاصيل الراتب بنجاح"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="current_salary", type="object",
     *                     @OA\Property(property="basic_salary", type="number", format="float", example=5000.00),
     *                     @OA\Property(property="allowances", type="number", format="float", example=1000.00),
     *                     @OA\Property(property="deductions", type="number", format="float", example=200.00),
     *                     @OA\Property(property="net_salary", type="number", format="float", example=5800.00)
     *                 ),
     *                 @OA\Property(property="salary_history", type="array", @OA\Items(type="object",
     *                     @OA\Property(property="month", type="string", example="2024-01"),
     *                     @OA\Property(property="basic_salary", type="number", format="float", example=5000.00),
     *                     @OA\Property(property="gross_salary", type="number", format="float", example=6000.00),
     *                     @OA\Property(property="net_salary", type="number", format="float", example=5800.00),
     *                     @OA\Property(property="status", type="string", example="paid")
     *                 ))
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="الموظف غير موجود"),
     *     @OA\Response(response=403, description="ليس لديك صلاحية لعرض تفاصيل راتب هذا الموظف")
     * )
     */
    public function getEmployeeSalaryDetails(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Check if user can view salaries
            if (!$this->permissionService->canViewSalaries($user)) {
                return $this->forbiddenResponse('ليس لديك صلاحية لعرض تفاصيل الرواتب');
            }
            
            $options = [
                'limit' => $request->get('limit', 12)
            ];
            
            // Get employee salary details using service
            $result = $this->employeeService->getEmployeeSalaryDetails($user, $id, $options);
            
            if (!$result) {
                return $this->notFoundResponse('الموظف غير موجود أو ليس لديك صلاحية لعرض تفاصيل راتبه');
            }
            
            return $this->successResponse($result, 'تم جلب تفاصيل الراتب بنجاح');
            
        } catch (\Exception $e) {
            return $this->handleException($e, 'EmployeeController::getEmployeeSalaryDetails');
        }
    }

    //=================================================================================

        
    // get employees for duty employee
    /**
     * @OA\Get(
     *     path="/api/employees/employees-for-duty-employee",
     *     summary="Get employees for duty employee",
     *     tags={"Employee Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="employee_id",
     *         in="query",
     *         description="Filter by employee ID (managers/HR only)",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search by employee name, email, or company name",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Employees for duty employee retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - No permission to view employees",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="غير مصرح لك بعرض الموظفين")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="فشل في الحصول على الموظفين")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Employees for duty employee not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="لم يتم العثور على موظفين")
     *         )
     *     )
     * )
     */
    public function getEmployeesForDutyEmployee(Request $request)
    {
        try {
            $user = Auth::user();
            // الحصول على عوامل التصفية من الطلب
            $employeeId = $request->query('employee_id');
            $search = $request->query('search');
            if ($user->user_type == 'company') {
                // مدير الشركة: يرى جميع طلبات شركته
                $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
                $companyId = $effectiveCompanyId;
                $departmentId = null;
            } else {
                $companyId = $user->company_id;
                $departmentId = $user->user_details->department_id;
            }

            // الحصول على الموظفين مع تطبيق عوامل التصفية
            $employees = $this->employeeManagementService->getEmployeesForDutyEmployee(
                $companyId,
                $search,
                $employeeId,
                $departmentId
            );

            return response()->json([
                'success' => true,
                'data' => $employees
            ], 200);
        } catch (\Exception $e) {
            Log::error('EmployeeController::getEmployeesForDutyEmployee failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $user->user_id ?? null
            ]);

            return response()->json([
                'success' => false,
                'message' => 'فشل في الحصول على موظفين: ' . $e->getMessage()
            ], 500);
        }
    }


    /**
     * @OA\Get(
     *     path="/api/employees/duty-employees",
     *     summary="Get duty employees based on target employee's department",
     *     description="Returns employees in the same department as the target employee, regardless of hierarchy.",
     *     tags={"Employee Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="target_employee_id",
     *         in="query",
     *         required=false,
     *         description="ID of the employee who needs a duty",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         required=false,
     *         description="Search by name, email, etc.",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="employee_id",
     *         in="query",
     *         required=false,
     *         description="Filter by specific employee ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Duty employees retrieved successfully"
     *     )
     * )
     */

    public function getDutyEmployeesForEmployee(GetBackupEmployeesRequest $request)
    {
        try {
            $user = Auth::user();
            $targetEmployeeId = $request->input('target_employee_id');
            $search = $request->input('search');
            $employeeId = $request->input('employee_id');

            $dutyEmployees = $this->employeeManagementService->getBackupEmployees($user, $targetEmployeeId, $search, $employeeId);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب الموظفين المناوبين بنجاح',
                'data' => $dutyEmployees
            ]);
        } catch (\Exception $e) {
            Log::error('EmployeeController::getDutyEmployeesForEmployee failed', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            $statusCode = $e->getCode() === 403 ? 403 : 500;
            if ($e->getMessage() === 'الموظف غير موجود') $statusCode = 404;

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    // get employees for notify (employees who can receive notifications)
    /**
     * @OA\Get(
     *     path="/api/employees/employees-for-notify",
     *     summary="Get employees who can receive notifications",
     *     description="Returns employees based on CanNotifyUser rules: company users, hierarchy level 1 users, or higher hierarchy managers in the same department",
     *     tags={"Employee Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search by employee name or email",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Employees for notification retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="user_id", type="integer", example=24),
     *                 @OA\Property(property="first_name", type="string", example="أحمد"),
     *                 @OA\Property(property="last_name", type="string", example="محمد"),
     *                 @OA\Property(property="full_name", type="string", example="أحمد محمد"),
     *                 @OA\Property(property="email", type="string", example="ahmed@example.com"),
     *                 @OA\Property(property="user_type", type="string", example="company"),
     *                 @OA\Property(property="hierarchy_level", type="integer", example=1)
     *             ))
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="فشل في الحصول على الموظفين")
     *         )
     *     )
     * )
     */
    public function getEmployeesForNotify(Request $request)
    {
        try {
            $user = Auth::user();
            $search = $request->query('search');

            // الحصول على معرف الشركة الفعلي
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);

            // الحصول على معلومات المستخدم الحالي من Service
            $userInfo = $this->employeeManagementService->getUserWithHierarchyInfo($user->user_id);
            $currentHierarchyLevel = $userInfo['hierarchy_level'] ?? null;
            $currentDepartmentId = $userInfo['department_id'] ?? null;

            // استخدام Service للحصول على الموظفين
            $employees = $this->employeeManagementService->getEmployeesForNotify(
                $effectiveCompanyId,
                $user->user_id,
                $currentHierarchyLevel,
                $currentDepartmentId,
                $search
            );

            return response()->json([
                'success' => true,
                'data' => $employees
            ], 200);
        } catch (\Exception $e) {
            Log::error('EmployeeController::getEmployeesForNotify failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $user->user_id ?? null
            ]);

            return response()->json([
                'success' => false,
                'message' => 'فشل في الحصول على الموظفين: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/employees/subordinates",
     *     summary="Get subordinates based on hierarchy and restrictions",
     *     description="Get list of employees that the current user can manage/view based on hierarchy level and operation restrictions",
     *     tags={"Employee Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Subordinates retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم جلب الموظفين التابعين بنجاح"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function getSubordinates(Request $request)
    {
        try {
            $user = Auth::user();
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);

            // Get subordinates using the centralized permission logic
            $subordinates = $this->permissionService->getEmployeesByHierarchy(
                $user->user_id,
                $effectiveCompanyId,
                true // Include self
            );

            // Accessing properties safely since getEmployeesByHierarchy might return objects or arrays
            // But based on implementation it returns array of objects/arrays.
            // Let's format specifically if needed, but the service returns a good structure.
            // We might just return directly.

            return response()->json([
                'success' => true,
                'message' => 'تم جلب الموظفين التابعين بنجاح',
                'data' => $subordinates
            ], 200);
        } catch (\Exception $e) {
            Log::error('EmployeeController::getSubordinates failed', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب الموظفين',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Get approval levels (approvers) for an employee.
     *
     * @OA\Get(
     *     path="/api/employees/approval-levels",
     *     summary="Get approval levels for an employee",
     *     description="Returns the configured approval chain with user details. If employee_id is not provided, returns approval levels for the authenticated user.",
     *     tags={"Employee Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="employee_id",
     *         in="query",
     *         required=false,
     *         description="Employee ID to get approval levels for. If not provided, uses authenticated user.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Approval levels retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم جلب مستويات الاعتماد بنجاح"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="employee", type="object",
     *                     @OA\Property(property="user_id", type="integer", example=24),
     *                     @OA\Property(property="full_name", type="string", example="محمد أحمد"),
     *                     @OA\Property(property="email", type="string", example="m.ahmed@example.com"),
     *                     @OA\Property(property="designation", type="string", example="مهندس"),
     *                     @OA\Property(property="hierarchy_level", type="integer", example=4)
     *                 ),
     *                 @OA\Property(property="approval_levels", type="array", @OA\Items(
     *                     @OA\Property(property="level", type="integer", example=1),
     *                     @OA\Property(property="user_id", type="integer", example=15),
     *                     @OA\Property(property="full_name", type="string", example="خالد عبدالله"),
     *                     @OA\Property(property="email", type="string", example="k.abdullah@example.com"),
     *                     @OA\Property(property="designation", type="string", example="مدير القسم"),
     *                     @OA\Property(property="hierarchy_level", type="integer", example=2)
     *                 )),
     *                 @OA\Property(property="reporting_manager", type="object", nullable=true),
     *                 @OA\Property(property="total_levels", type="integer", example=2)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - No permission to view employee approval levels",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="ليس لديك صلاحية لعرض بيانات هذا الموظف")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Employee not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="الموظف غير موجود")
     *         )
     *     )
     * )
     */
    public function getApprovalLevels(Request $request)
    {
        try {
            $user = Auth::user();
            $targetEmployeeId = $request->query('employee_id');

            // Parse as integer if provided
            $targetEmployeeId = $targetEmployeeId ? (int) $targetEmployeeId : null;

            $approvalLevels = $this->employeeManagementService->getApprovalLevels($user, $targetEmployeeId);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب مستويات الاعتماد بنجاح',
                'data' => $approvalLevels
            ], 200);
        } catch (\Exception $e) {
            Log::error('EmployeeController::getApprovalLevels failed', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'target_employee_id' => $request->query('employee_id')
            ]);

            $statusCode = 500;
            if (str_contains($e->getMessage(), 'غير موجود')) {
                $statusCode = 404;
            } elseif (str_contains($e->getMessage(), 'ليس لديك صلاحية')) {
                $statusCode = 403;
            }

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $statusCode);
        }
    }

}

/**
 * @OA\Schema(
 *     schema="Employee",
 *     type="object",
 *     title="Employee",
 *     description="Employee model",
 *     @OA\Property(property="user_id", type="integer", example=1, description="Employee ID"),
 *     @OA\Property(property="first_name", type="string", example="أحمد", description="First name"),
 *     @OA\Property(property="last_name", type="string", example="محمد", description="Last name"),
 *     @OA\Property(property="email", type="string", example="ahmed@company.com", description="Email address"),
 *     @OA\Property(property="username", type="string", example="ahmed.mohamed", description="Username"),
 *     @OA\Property(property="user_type", type="string", example="staff", description="User type"),
 *     @OA\Property(property="company_id", type="integer", example=1, description="Company ID"),
 *     @OA\Property(property="is_active", type="boolean", example=true, description="Active status"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-01T00:00:00Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2024-01-01T00:00:00Z"),
 *     @OA\Property(
 *         property="user_details",
 *         type="object",
 *         @OA\Property(property="employee_id", type="string", example="EMP001", description="Employee code"),
 *         @OA\Property(property="department_id", type="integer", example=1, description="Department ID"),
 *         @OA\Property(property="designation_id", type="integer", example=1, description="Designation ID"),
 *         @OA\Property(property="basic_salary", type="number", format="float", example=5000.00, description="Basic salary"),
 *         @OA\Property(property="hire_date", type="string", format="date", example="2024-01-01", description="Hire date"),
 *         @OA\Property(property="phone", type="string", example="+966501234567", description="Phone number"),
 *         @OA\Property(property="address", type="string", example="الرياض، المملكة العربية السعودية", description="Address")
 *     ),
 *     @OA\Property(
 *         property="department",
 *         type="object",
 *         @OA\Property(property="department_id", type="integer", example=1),
 *         @OA\Property(property="department_name", type="string", example="الموارد البشرية")
 *     ),
 *     @OA\Property(
 *         property="designation",
 *         type="object",
 *         @OA\Property(property="designation_id", type="integer", example=1),
 *         @OA\Property(property="designation_name", type="string", example="مطور برمجيات")
 *     )
 * )
 * 
 * @OA\Schema(
 *     schema="EmployeeCreate",
 *     type="object",
 *     title="Employee Create Request",
 *     description="Employee creation request model",
 *     required={"first_name", "last_name", "email", "username", "password", "department_id", "designation_id"},
 *     @OA\Property(property="first_name", type="string", example="أحمد", description="First name"),
 *     @OA\Property(property="last_name", type="string", example="محمد", description="Last name"),
 *     @OA\Property(property="email", type="string", format="email", example="ahmed@company.com", description="Email address"),
 *     @OA\Property(property="username", type="string", example="ahmed.mohamed", description="Username"),
 *     @OA\Property(property="password", type="string", format="password", example="password123", description="Password"),
 *     @OA\Property(property="department_id", type="integer", example=1, description="Department ID"),
 *     @OA\Property(property="designation_id", type="integer", example=1, description="Designation ID"),
 *     @OA\Property(property="employee_id", type="string", example="EMP001", description="Employee code"),
 *     @OA\Property(property="basic_salary", type="number", format="float", example=5000.00, description="Basic salary"),
 *     @OA\Property(property="hire_date", type="string", format="date", example="2024-01-01", description="Hire date"),
 *     @OA\Property(property="phone", type="string", example="+966501234567", description="Phone number"),
 *     @OA\Property(property="address", type="string", example="الرياض، المملكة العربية السعودية", description="Address"),
 *     @OA\Property(property="is_active", type="boolean", example=true, description="Active status")
 * )
 * 
 * @OA\Schema(
 *     schema="EmployeeUpdate",
 *     type="object",
 *     title="Employee Update Request",
 *     description="Employee update request model",
 *     @OA\Property(property="first_name", type="string", example="أحمد", description="First name"),
 *     @OA\Property(property="last_name", type="string", example="محمد", description="Last name"),
 *     @OA\Property(property="email", type="string", format="email", example="ahmed@company.com", description="Email address"),
 *     @OA\Property(property="username", type="string", example="ahmed.mohamed", description="Username"),
 *     @OA\Property(property="department_id", type="integer", example=1, description="Department ID"),
 *     @OA\Property(property="designation_id", type="integer", example=1, description="Designation ID"),
 *     @OA\Property(property="employee_id", type="string", example="EMP001", description="Employee code"),
 *     @OA\Property(property="basic_salary", type="number", format="float", example=5000.00, description="Basic salary"),
 *     @OA\Property(property="hire_date", type="string", format="date", example="2024-01-01", description="Hire date"),
 *     @OA\Property(property="phone", type="string", example="+966501234567", description="Phone number"),
 *     @OA\Property(property="address", type="string", example="الرياض، المملكة العربية السعودية", description="Address"),
 *     @OA\Property(property="is_active", type="boolean", example=true, description="Active status")
 * )
 * 
 * @OA\Schema(
 *     schema="EmployeeStatistics",
 *     type="object",
 *     title="Employee Statistics",
 *     description="Employee statistics model",
 *     @OA\Property(property="total_employees", type="integer", example=100, description="Total number of employees"),
 *     @OA\Property(property="active_employees", type="integer", example=95, description="Number of active employees"),
 *     @OA\Property(property="inactive_employees", type="integer", example=5, description="Number of inactive employees"),
 *     @OA\Property(
 *         property="by_department",
 *         type="array",
 *         description="Employee count by department",
 *         @OA\Items(
 *             type="object",
 *             @OA\Property(property="department_name", type="string", example="الموارد البشرية"),
 *             @OA\Property(property="count", type="integer", example=10)
 *         )
 *     ),
 *     @OA\Property(
 *         property="by_designation",
 *         type="array",
 *         description="Employee count by designation",
 *         @OA\Items(
 *             type="object",
 *             @OA\Property(property="designation_name", type="string", example="مطور برمجيات"),
 *             @OA\Property(property="count", type="integer", example=15)
 *         )
 *     )
 * )
 */