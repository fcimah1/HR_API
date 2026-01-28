<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\CreateEmployeeRequest;
use App\Http\Requests\Employee\UpdateEmployeeRequest;
use App\Http\Requests\Employee\ChangePasswordRequest;
use App\Http\Requests\Employee\UploadProfileImageRequest;
use App\Http\Requests\Employee\UploadDocumentRequest;
use App\Http\Requests\Employee\UpdateProfileInfoRequest;
use App\Http\Requests\Employee\UpdateCVRequest;
use App\Http\Requests\Employee\UpdateSocialLinksRequest;
use App\Http\Requests\Employee\UpdateBankInfoRequest;
use App\Http\Requests\Employee\AddFamilyDataRequest;
use App\Http\Resources\EmployeeResource;
use App\Http\Resources\EmployeeListResource;
use App\Http\Resources\EmployeeStatisticsResource;
use App\Services\EmployeeManagementService;
use App\Services\SimplePermissionService;
use App\Services\FileUploadService;
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
        private readonly FileUploadService $fileUploadService,

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
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=422, description="بيانات غير صحيحة"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            // Create filter DTO from request
            $filtersData = $request->all();
            $filtersData['company_id'] = $this->permissionService->getEffectiveCompanyId($user);
            $filters = EmployeeFilterDTO::fromArray($filtersData);

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
     *             required={"first_name", "last_name", "email", "username",
     *             "password", "department_id", "designation_id", "basic_salary", "currency", "shift", "role", "direct_supervisor"},
     *             @OA\Property(property="first_name", type="string", example="محمد"),
     *             @OA\Property(property="last_name", type="string", example="أحمد"),
     *             @OA\Property(property="email", type="string", format="email", example="employee@company.com"),
     *             @OA\Property(property="username", type="string", example="mohammed.ahmed"),
     *             @OA\Property(property="password", type="string", example="password123"),
     *             @OA\Property(property="contact_number", type="string", example="01234567890"),
     *             @OA\Property(property="gender", type="string", enum={"Male", "Female"}, example="Male"),
     *             @OA\Property(property="department_id", type="integer", example=1),
     *             @OA\Property(property="designation_id", type="integer", example=1),
     *             @OA\Property(property="basic_salary", type="number", format="float", example=5000.00),
     *             @OA\Property(property="currency_id", type="integer", example=1),
     *             @OA\Property(property="shift_id", type="integer", example=1),
     *             @OA\Property(property="user_role_id", type="integer", example=1),
     *             @OA\Property(property="reporting_manager", type="integer", example=1)
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
     *                 @OA\Property(property="reporting_manager", type="integer", example=1),
     *                 @OA\Property(property="username", type="string", example="ahmed.ahmed"),
     *                 @OA\Property(property="role", type="string", example="employee"),
     *                 @OA\Property(property="shift", type="string", example="day"),
     *                 @OA\Property(property="designation_id", type="integer", example=1),
     *                 @OA\Property(property="department_id", type="integer", example=1),
     *                 @OA\Property(property="is_active", type="boolean", example=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=422, description="بيانات غير صحيحة"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=403, description="ليس لديك صلاحية لإضافة موظفين"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function store(CreateEmployeeRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();


            // Create DTO from validated request
            $data = $request->validated();
            $data['company_id'] = $this->permissionService->getEffectiveCompanyId($user);

            $createData = CreateEmployeeDTO::fromArray($data);

            // Create employee
            $employee = $this->employeeService->createEmployee($user, $createData);

            if (!$employee) {
                Log::error('فشل في إنشاء الموظف', [
                    'user_id' => $user->user_id,
                    'createData' => $createData,
                    'message' => 'فشل في إنشاء الموظف',
                ]);
                return $this->serverErrorResponse('فشل في إنشاء الموظف');
            }

            return $this->successResponse(
                new EmployeeResource($employee),
                'تم إنشاء الموظف بنجاح',
                201
            );
        } catch (ValidationException $e) {
            Log::error('فشل في إنشاء الموظف', [
                'user_id' => $user->user_id ?? 'unknown',
                'createData' => $createData ?? $request->all(),
                'message' => $e->getMessage(),
            ]);
            return $this->validationErrorResponse($e);
        } catch (\Exception $e) {
            Log::error('فشل في إنشاء الموظف', [
                'user_id' => $user->user_id ?? 'unknown',
                'createData' => $createData ?? $request->all(),
                'message' => $e->getMessage(),
            ]);
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
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=404, description="الموظف أو البيانات غير موجودة أو ليس لديك صلاحية لجلب بيانات الموظف"),
     *     @OA\Response(response=422, description="بيانات غير صحيحة"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
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
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=404, description="الموظف أو البيانات غير موجودة أو ليس لديك صلاحية لتعديل الموظف"),
     *     @OA\Response(response=422, description="بيانات غير صحيحة"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
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
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=404, description="الموظف أو البيانات غير موجودة أو ليس لديك صلاحية لحذف الموظف"),
     *     @OA\Response(response=422, description="بيانات غير صحيحة"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $user = Auth::user();

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
     *     @OA\Response(response=400, description="نص البحث مطلوب"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=422, description="بيانات غير صحيحة"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
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

    // /**
    //  * @OA\Get(
    //  *     path="/api/employees/statistics",
    //  *     summary="Get employee statistics",
    //  *     description="Retrieve comprehensive employee statistics and analytics",
    //  *     tags={"Employee"},
    //  *     security={{"bearerAuth":{}}},
    //  *     @OA\Response(
    //  *         response=200,
    //  *         description="Statistics retrieved successfully",
    //  *         @OA\JsonContent(
    //  *             @OA\Property(property="success", type="boolean", example=true),
    //  *             @OA\Property(property="message", type="string", example="تم جلب الإحصائيات بنجاح"),
    //  *             @OA\Property(property="data", type="object",
    //  *                 @OA\Property(property="total_employees", type="integer", example=150),
    //  *                 @OA\Property(property="active_employees", type="integer", example=140),
    //  *                 @OA\Property(property="inactive_employees", type="integer", example=10),
    //  *                 @OA\Property(property="departments_count", type="integer", example=8),
    //  *                 @OA\Property(property="designations_count", type="integer", example=15),
    //  *                 @OA\Property(property="average_salary", type="number", format="float", example=4500.50),
    //  *                 @OA\Property(property="by_department", type="array", @OA\Items(type="object")),
    //  *                 @OA\Property(property="by_designation", type="array", @OA\Items(type="object"))
    //  *             )
    //  *         )
    //  *     ),
    //  *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
    //  *     @OA\Response(response=403, description="ليس لديك صلاحية لعرض الإحصائيات"),
    //  *     @OA\Response(response=422, description="بيانات غير صحيحة"),
    //  *     @OA\Response(response=500, description="خطأ في الخادم")
    //  * )
    //  */
    // public function statistics(): JsonResponse
    // {
    //     try {
    //         $user = Auth::user();

    //         // Get statistics
    //         $statistics = $this->employeeService->getEmployeeStatistics($user);

    //         return $this->successResponse(
    //             new EmployeeStatisticsResource($statistics),
    //             'تم جلب الإحصائيات بنجاح'
    //         );
    //     } catch (\Exception $e) {
    //         return $this->handleException($e, 'EmployeeController::statistics');
    //     }
    // }

    // /**
    //  * @OA\Get(
    //  *     path="/api/employees/{id}/documents",
    //  *     summary="Get employee documents",
    //  *     description="Retrieve all documents uploaded for specific employee",
    //  *     tags={"Employee"},
    //  *     security={{"bearerAuth":{}}},
    //  *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
    //  *     @OA\Response(
    //  *         response=200,
    //  *         description="Documents retrieved successfully",
    //  *         @OA\JsonContent(
    //  *             @OA\Property(property="success", type="boolean", example=true),
    //  *             @OA\Property(property="message", type="string", example="تم جلب المستندات بنجاح"),
    //  *             @OA\Property(property="data", type="object",
    //  *                 @OA\Property(property="documents", type="array", @OA\Items(type="object",
    //  *                     @OA\Property(property="id", type="integer", example=1),
    //  *                     @OA\Property(property="document_type", type="string", example="CV"),
    //  *                     @OA\Property(property="file_name", type="string", example="cv_mohammed.pdf"),
    //  *                     @OA\Property(property="file_path", type="string", example="/storage/documents/cv_mohammed.pdf"),
    //  *                     @OA\Property(property="uploaded_at", type="string", format="date-time")
    //  *                 )),
    //  *                 @OA\Property(property="total", type="integer", example=5)
    //  *             )
    //  *         )
    //  *     ),
    //  *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
    //  *     @OA\Response(response=404, description="الموظف أو البيانات غير موجودة أو ليس لديك صلاحية لجلب مستندات الموظف"),
    //  *     @OA\Response(response=422, description="بيانات غير صحيحة"),
    //  *     @OA\Response(response=500, description="خطأ في الخادم")
    //  * )
    //  */
    // public function getEmployeeDocuments(int $id): JsonResponse
    // {
    //     try {
    //         $user = Auth::user();

    //         // Get employee documents using service
    //         $result = $this->employeeService->getEmployeeDocuments($user, $id);

    //         if (!$result) {
    //             return $this->notFoundResponse('الموظف غير موجود أو ليس لديك صلاحية لعرض مستنداته');
    //         }

    //         return $this->successResponse($result, 'تم جلب المستندات بنجاح');
    //     } catch (\Exception $e) {
    //         return $this->handleException($e, 'EmployeeController::getEmployeeDocuments');
    //     }
    // }

    // /**
    //  * @OA\Get(
    //  *     path="/api/employees/{id}/leave-balance",
    //  *     summary="Get employee leave balance",
    //  *     description="Retrieve current leave balance for specific employee",
    //  *     tags={"Employee"},
    //  *     security={{"bearerAuth":{}}},
    //  *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
    //  *     @OA\Response(
    //  *         response=200,
    //  *         description="Leave balance retrieved successfully",
    //  *         @OA\JsonContent(
    //  *             @OA\Property(property="success", type="boolean", example=true),
    //  *             @OA\Property(property="message", type="string", example="تم جلب رصيد الإجازات بنجاح"),
    //  *             @OA\Property(property="data", type="object",
    //  *                 @OA\Property(property="annual_leave", type="object",
    //  *                     @OA\Property(property="total", type="integer", example=30),
    //  *                     @OA\Property(property="used", type="integer", example=12),
    //  *                     @OA\Property(property="remaining", type="integer", example=18)
    //  *                 ),
    //  *                 @OA\Property(property="sick_leave", type="object",
    //  *                     @OA\Property(property="total", type="integer", example=15),
    //  *                     @OA\Property(property="used", type="integer", example=3),
    //  *                     @OA\Property(property="remaining", type="integer", example=12)
    //  *                 ),
    //  *                 @OA\Property(property="emergency_leave", type="object",
    //  *                     @OA\Property(property="total", type="integer", example=5),
    //  *                     @OA\Property(property="used", type="integer", example=1),
    //  *                     @OA\Property(property="remaining", type="integer", example=4)
    //  *                 )
    //  *             )
    //  *         )
    //  *     ),
    //  *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
    //  *     @OA\Response(response=404, description="الموظف أو البيانات غير موجودة أو ليس لديك صلاحية لجلب رصيد إجازات الموظف"),
    //  *     @OA\Response(response=422, description="بيانات غير صحيحة"),
    //  *     @OA\Response(response=500, description="خطأ في الخادم")
    //  * )
    //  */
    // public function getEmployeeLeaveBalance(int $id): JsonResponse
    // {
    //     try {
    //         $user = Auth::user();

    //         // Get employee leave balance using service
    //         $result = $this->employeeService->getEmployeeLeaveBalance($user, $id);

    //         if (!$result) {
    //             return $this->notFoundResponse('الموظف غير موجود أو ليس لديك صلاحية لعرض رصيد إجازاته');
    //         }

    //         return $this->successResponse($result, 'تم جلب رصيد الإجازات بنجاح');
    //     } catch (\Exception $e) {
    //         return $this->handleException($e, 'EmployeeController::getEmployeeLeaveBalance');
    //     }
    // }

    // /**
    //  * @OA\Get(
    //  *     path="/api/employees/{id}/attendance",
    //  *     summary="Get employee attendance records",
    //  *     description="Retrieve recent attendance records for specific employee",
    //  *     tags={"Employee"},
    //  *     security={{"bearerAuth":{}}},
    //  *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
    //  *     @OA\Parameter(name="limit", in="query", description="Number of records to return", @OA\Schema(type="integer", default=30)),
    //  *     @OA\Response(
    //  *         response=200,
    //  *         description="Attendance records retrieved successfully",
    //  *         @OA\JsonContent(
    //  *             @OA\Property(property="success", type="boolean", example=true),
    //  *             @OA\Property(property="message", type="string", example="تم جلب سجل الحضور بنجاح"),
    //  *             @OA\Property(property="data", type="object",
    //  *                 @OA\Property(property="attendance", type="array", @OA\Items(type="object",
    //  *                     @OA\Property(property="date", type="string", format="date", example="2024-01-15"),
    //  *                     @OA\Property(property="check_in", type="string", format="time", example="08:30:00"),
    //  *                     @OA\Property(property="check_out", type="string", format="time", example="17:00:00"),
    //  *                     @OA\Property(property="hours_worked", type="number", format="float", example=8.5),
    //  *                     @OA\Property(property="status", type="string", example="present")
    //  *                 )),
    //  *                 @OA\Property(property="summary", type="object",
    //  *                     @OA\Property(property="total_days", type="integer", example=30),
    //  *                     @OA\Property(property="present_days", type="integer", example=28),
    //  *                     @OA\Property(property="absent_days", type="integer", example=2),
    //  *                     @OA\Property(property="average_hours", type="number", format="float", example=8.2)
    //  *                 )
    //  *             )
    //  *         )
    //  *     ),
    //  *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
    //  *     @OA\Response(response=404, description="الموظف أو البيانات غير موجودة أو ليس لديك صلاحية لجلب سجل حضور الموظف"),
    //  *     @OA\Response(response=422, description="بيانات غير صحيحة"),
    //  *     @OA\Response(response=500, description="خطأ في الخادم")
    //  * )
    //  */
    // public function getEmployeeAttendance(Request $request, int $id): JsonResponse
    // {
    //     try {
    //         $user = Auth::user();
    //         $options = [
    //             'limit' => $request->get('limit', 30)
    //         ];

    //         // Get employee attendance using service
    //         $result = $this->employeeService->getEmployeeAttendance($user, $id, $options);

    //         if (!$result) {
    //             return $this->notFoundResponse('الموظف غير موجود أو ليس لديك صلاحية لعرض سجل حضوره');
    //         }

    //         return $this->successResponse($result, 'تم جلب سجل الحضور بنجاح');
    //     } catch (\Exception $e) {
    //         return $this->handleException($e, 'EmployeeController::getEmployeeAttendance');
    //     }
    // }

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
     *     ),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=404, description="الموظف أو البيانات غير موجودة أو ليس لديك صلاحية لجلب الموظفين المناوبين"),
     *     @OA\Response(response=422, description="بيانات غير صحيحة"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
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
     *         description="غير مصرح - يجب تسجيل الدخول",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="غير مصرح - يجب تسجيل الدخول")
     *         )
     *     ),
     *     @OA\Response(response=422, description="بيانات غير صحيحة"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
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
     *     ),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=404, description="الموظف أو البيانات غير موجودة أو ليس لديك صلاحية لجلب الموظفين التابعين"),
     *     @OA\Response(response=422, description="بيانات غير صحيحة"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
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
     *     ),
     *     @OA\Response(response=422, description="بيانات غير صحيحة"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
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


    /**
     * @OA\Put(
     *     path="/api/employees/{id}/change-password",
     *     summary="Change employee password",
     *     description="Update employee password",
     *     tags={"Employee"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"password", "confirm_password"},
     *             @OA\Property(property="password", type="string", format="password", example="newpassword123"),
     *             @OA\Property(property="confirm_password", type="string", format="password", example="newpassword123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Password changed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم تغيير كلمة المرور بنجاح")
     *         )
     *     ),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=404, description="الموظف أو البيانات غير موجودة أو ليس لديك صلاحية لتغيير كلمة المرور"),
     *     @OA\Response(response=422, description="بيانات غير صحيحة"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function changePassword(ChangePasswordRequest $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();

            $success = $this->employeeService->changeEmployeePassword($user, $id, $request->password);

            if (!$success) {
                return $this->notFoundResponse('الموظف غير موجود أو ليس لديك صلاحية لتعديل كلمة المرور');
            }

            return $this->successResponse(null, 'تم تغيير كلمة المرور بنجاح');
        } catch (\Exception $e) {
            return $this->handleException($e, 'EmployeeController::changePassword');
        }
    }

    /**
     * @OA\Post(
     *     path="/api/employees/{id}/upload-profile-image",
     *     summary="Upload employee profile image",
     *     description="Upload or update employee profile image",
     *     tags={"Employee"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"profile_image"},
     *                 @OA\Property(property="profile_image", type="string", format="binary", description="Profile image file")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Profile image uploaded successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم رفع صورة الملف الشخصي بنجاح"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="profile_image_url", type="string", example="/storage/profiles/employee_123.jpg")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=404, description="الموظف أو البيانات غير موجودة أو ليس لديك صلاحية لرفع الصورة الشخصية"),
     *     @OA\Response(response=422, description="بيانات غير صحيحة"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function uploadProfileImage(UploadProfileImageRequest $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();

            $result = $this->employeeService->uploadEmployeeProfileImage($user, $id, $request->file('profile_image'));

            if (!$result) {
                return $this->notFoundResponse('الموظف غير موجود أو ليس لديك صلاحية لتعديل الصورة الشخصية');
            }

            return $this->successResponse($result, 'تم رفع صورة الملف الشخصي بنجاح');
        } catch (\Exception $e) {
            return $this->handleException($e, 'EmployeeController::uploadProfileImage');
        }
    }

    /**
     * @OA\Post(
     *     path="/api/employees/{id}/upload-document",
     *     summary="Upload employee document",
     *     description="Upload a document for employee",
     *     tags={"Employee"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"document_name", "document_type", "document_file"},
     *                 @OA\Property(property="document_name", type="string", example="CV"),
     *                 @OA\Property(property="document_type", type="string", example="resume"),
     *                 @OA\Property(property="document_file", type="string", format="binary", description="Document file"),
     *                 @OA\Property(property="expiration_date", type="string", format="date", example="2025-12-31")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Document uploaded successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم رفع المستند بنجاح"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="document_id", type="integer", example=1),
     *                 @OA\Property(property="document_url", type="string", example="/storage/documents/cv_123.pdf")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=404, description="الموظف أو البيانات غير موجودة أو ليس لديك صلاحية لرفع المستندات"),
     *     @OA\Response(response=422, description="بيانات غير صحيحة"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function uploadDocument(UploadDocumentRequest $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();

            $result = $this->employeeService->uploadEmployeeDocument($user, $id, [
                'document_name' => $request->document_name,
                'document_type' => $request->document_type,
                'document_file' => $request->file('document_file'),
                'expiration_date' => $request->expiration_date
            ]);

            if (!$result) {
                return $this->notFoundResponse('الموظف غير موجود أو ليس لديك صلاحية لرفع المستندات');
            }

            return $this->successResponse($result, 'تم رفع المستند بنجاح');
        } catch (\Exception $e) {
            return $this->handleException($e, 'EmployeeController::uploadDocument');
        }
    }

    /**
     * @OA\Put(
     *     path="/api/employees/{id}/update-profile-info",
     *     summary="Update employee profile info",
     *     description="Update employee username and email",
     *     tags={"Employee"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="username", type="string", example="new.username"),
     *             @OA\Property(property="email", type="string", format="email", example="newemail@company.com")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Profile info updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم تحديث معلومات الملف الشخصي بنجاح")
     *         )
     *     ),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=404, description="الموظف أو البيانات غير موجودة أو ليس لديك صلاحية لتعديل معلومات الملف الشخصي"),
     *     @OA\Response(response=422, description="بيانات غير صحيحة"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function updateProfileInfo(UpdateProfileInfoRequest $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();

            $success = $this->employeeService->updateEmployeeProfileInfo($user, $id, $request->only(['username', 'email']));

            if (!$success) {
                Log::error('not found', [
                    "success" => false,
                    'message' => 'not found',
                    'error' => $success
                ]);
                return $this->notFoundResponse('الموظف غير موجود أو ليس لديك صلاحية لتعديل معلومات الملف الشخصي');
            }

            return $this->successResponse(null, 'تم تحديث معلومات الملف الشخصي بنجاح');
        } catch (\Exception $e) {
            return $this->handleException($e, 'EmployeeController::updateProfileInfo');
        }
    }

    /**
     * @OA\Put(
     *     path="/api/employees/{id}/update-cv",
     *     summary="Update employee CV",
     *     description="Update employee bio and experience",
     *     tags={"Employee"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="bio", type="string", example="مطور برمجيات خبرة 5 سنوات"),
     *             @OA\Property(property="experience", type="string", enum={"بدون","سنة","سنتان","سنوات 3","سنوات 4","سنوات 5","سنوات 6","سنوات 7","سنوات 8","سنوات 9","سنوات 10","أكثر من 10+"}, example="سنوات 5")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="CV updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم تحديث السيرة الذاتية بنجاح")
     *         )
     *     ),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=404, description="الموظف أو البيانات غير موجودة أو ليس لديك صلاحية لتعديل السيرة الذاتية"),
     *     @OA\Response(response=422, description="بيانات غير صحيحة"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function updateCV(UpdateCVRequest $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();

            $success = $this->employeeService->updateEmployeeCV($user, $id, $request->only(['bio', 'experience']));

            if (!$success) {
                return $this->notFoundResponse('الموظف غير موجود أو ليس لديك صلاحية لتعديل السيرة الذاتية');
            }

            return $this->successResponse(null, 'تم تحديث السيرة الذاتية بنجاح');
        } catch (\Exception $e) {
            return $this->handleException($e, 'EmployeeController::updateCV');
        }
    }

    /**
     * @OA\Put(
     *     path="/api/employees/{id}/update-social-links",
     *     summary="Update employee social links",
     *     description="Update employee social media profiles",
     *     tags={"Employee"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="fb_profile", type="string", example="https://facebook.com/username"),
     *             @OA\Property(property="twitter_profile", type="string", example="https://twitter.com/username"),
     *             @OA\Property(property="gplus_profile", type="string", example="https://plus.google.com/username"),
     *             @OA\Property(property="linkedin_profile", type="string", example="https://linkedin.com/in/username")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Social links updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم تحديث الروابط الاجتماعية بنجاح")
     *         )
     *     ),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=404, description="الموظف أو البيانات غير موجودة أو ليس لديك صلاحية لتعديل الروابط الاجتماعية"),
     *     @OA\Response(response=422, description="بيانات غير صحيحة"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function updateSocialLinks(UpdateSocialLinksRequest $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();

            $success = $this->employeeService->updateEmployeeSocialLinks($user, $id, $request->only([
                'fb_profile',
                'twitter_profile',
                'gplus_profile',
                'linkedin_profile'
            ]));

            if (!$success) {
                return $this->notFoundResponse('الموظف غير موجود أو ليس لديك صلاحية لتعديل الروابط الاجتماعية');
            }

            return $this->successResponse(null, 'تم تحديث الروابط الاجتماعية بنجاح');
        } catch (\Exception $e) {
            return $this->handleException($e, 'EmployeeController::updateSocialLinks');
        }
    }

    /**
     * @OA\Put(
     *     path="/api/employees/{id}/update-bank-info",
     *     summary="Update employee bank information",
     *     description="Update employee bank account details",
     *     tags={"Employee"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="account_number", type="string", example="1234567890"),
     *             @OA\Property(property="bank_name", type="int", example="11"),
     *             @OA\Property(property="iban", type="string", example="SA1234567890123456789012"),
     *             @OA\Property(property="bank_branch", type="string", example="فرع الرياض الرئيسي")
     *         )
     *     ),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=404, description="الموظف أو البيانات غير موجودة أو ليس لديك صلاحية لتعديل البيانات البنكيه"),
     *     @OA\Response(response=422, description="بيانات غير صحيحة"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function updateBankInfo(UpdateBankInfoRequest $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();

            $success = $this->employeeService->updateEmployeeBankInfo($user, $id, $request->only([
                'account_number',
                'bank_name',
                'iban',
                'bank_branch'
            ]));

            if (!$success) {
                Log::error('EmployeeController::updateBankInfo failed', [
                    'user_id' => $user->user_id,
                    'employee_id' => $id,
                ]);
                return $this->notFoundResponse('الموظف غير موجود أو ليس لديك صلاحية لتعديل المعلومات البنكية');
            }

            return $this->successResponse(null, 'تم تحديث المعلومات البنكية بنجاح');
        } catch (\Exception $e) {
            Log::error('EmployeeController::updateBankInfo failed', [
                'user_id' => $user?->user_id,
                'employee_id' => $id,
            ]);
            return $this->handleException($e, 'EmployeeController::updateBankInfo');
        }
    }

    /**
     * @OA\Put(
     *     path="/api/employees/{id}/add-family-data",
     *     summary="Add employee family data",
     *     description="Add employee family/emergency contact information",
     *     tags={"Employee"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="relative_full_name", type="string", example="أحمد محمد العلي"),
     *             @OA\Property(property="relative_email", type="string", format="email", example="relative@email.com"),
     *             @OA\Property(property="relative_phone", type="string", example="0501234567"),
     *             @OA\Property(property="relative_place", type="integer", example="1"),
     *             @OA\Property(property="relative_address", type="string", example="حي النخيل، شارع الملك فهد"),
     *             @OA\Property(property="relative_relation", type="integer", example="1")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Family data added successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم إضافة بيانات العائلة بنجاح")
     *         )
     *     ),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=404, description="الموظف أو البيانات غير موجودة أو ليس لديك صلاحية لإضافة بيانات العائلة"),
     *     @OA\Response(response=422, description="بيانات غير صحيحة"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function addFamilyData(AddFamilyDataRequest $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();

            $success = $this->employeeService->addEmployeeFamilyData($user, $id, $request->only([
                'relative_full_name',
                'relative_email',
                'relative_phone',
                'relative_place',
                'relative_address',
                'relative_relation'
            ]));

            if (!$success) {
                return $this->notFoundResponse('الموظف غير موجود أو ليس لديك صلاحية لإضافة بيانات العائلة');
            }

            return $this->successResponse(null, 'تم إضافة بيانات العائلة بنجاح');
        } catch (\Exception $e) {
            return $this->handleException($e, 'EmployeeController::addFamilyData');
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/employees/{id}/delete-family-data/{contactId}",
     *     summary="Delete employee family data",
     *     description="Delete a specific family/emergency contact record",
     *     tags={"Employee"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="contactId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Family data deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم حذف بيانات العائلة بنجاح")
     *         )
     *     ),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=404, description="الموظف أو البيانات غير موجودة أو ليس لديك صلاحية للحذف"),
     *     @OA\Response(response=422, description="بيانات غير صحيحة"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function deleteFamilyData(int $id, int $contactId): JsonResponse
    {
        try {
            $user = Auth::user();

            $success = $this->employeeService->deleteEmployeeFamilyData($user, $id, $contactId);

            if (!$success) {
                return $this->notFoundResponse('الموظف أو البيانات غير موجودة أو ليس لديك صلاحية للحذف');
            }

            return $this->successResponse(null, 'تم حذف بيانات العائلة بنجاح');
        } catch (\Exception $e) {
            return $this->handleException($e, 'EmployeeController::deleteFamilyData');
        }
    }


}
