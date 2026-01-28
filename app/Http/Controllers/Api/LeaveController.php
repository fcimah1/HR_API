<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LeaveService;
use App\Models\User;
use App\DTOs\Leave\LeaveApplicationFilterDTO;
use App\DTOs\Leave\CreateLeaveApplicationDTO;
use App\DTOs\Leave\UpdateLeaveApplicationDTO;
use App\Http\Requests\Leave\ApproveLeaveApplicationRequest;
use App\Http\Requests\Leave\CheckLeaveBalanceRequest;
use App\Http\Requests\Leave\CreateLeaveApplicationRequest;
use App\Http\Requests\Leave\UpdateLeaveApplicationRequest;
use App\Services\SimplePermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="Leave Management",
 *     description="Leave applications management"
 * )
 */
class LeaveController extends Controller
{
    public $simplePermissionService;
    public function __construct(
        private  LeaveService $leaveService,
        private  SimplePermissionService $permissionService
    ) {
        $this->simplePermissionService = $permissionService;
    }
    /**
     * @OA\Get(
     *     path="/api/leaves/applications",
     *     summary="Get leave applications",
     *     tags={"Leave Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="employee_id",
     *         in="query",
     *         description="Filter by employee ID (for managers/HR only)",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by status (pending/approved/rejected)",
     *         @OA\Schema(type="string", enum={"pending", "approved", "rejected"})
     *     ),
     *     @OA\Parameter(
     *         name="leave_type_id",
     *         in="query",
     *         description="Filter by leave type ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="from_date",
     *         in="query",
     *         description="Filter from date",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="to_date",
     *         in="query",
     *         description="Filter to date",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search in employee name or leave type",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page",
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Applications retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم جلب طلبات الإجازات بنجاح"),
     *             @OA\Property(property="created by", type="string", example="أحمد علي"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="leave_id", type="integer", example=123),
     *                     @OA\Property(property="employee_id", type="integer", example=37),
     *                     @OA\Property(property="duty_employee_id", type="integer", nullable=true, example=118),
     *                     @OA\Property(property="leave_type_id", type="integer", example=323),
     *                     @OA\Property(property="from_date", type="string", format="date", example="2025-12-01"),
     *                     @OA\Property(property="to_date", type="string", format="date", example="2025-12-07"),
     *                     @OA\Property(property="leave_hours", type="number", format="float", nullable=true, example=64.0),
     *                     @OA\Property(property="is_half_day", type="boolean", nullable=true, example=false),
     *                     @OA\Property(property="status", type="string", enum={"pending","approved","rejected"}, example="pending"),
     *                     @OA\Property(property="reason", type="string", example="إجازة سنوية"),
     *                     @OA\Property(property="remarks", type="string", nullable=true, example="ملاحظات"),
     *                     @OA\Property(property="company_id", type="integer", example=36),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-01 08:30:00"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-01 08:30:00"),
     *                     @OA\Property(
     *                         property="employee",
     *                         type="object",
     *                         @OA\Property(property="user_id", type="integer", example=37),
     *                         @OA\Property(property="full_name", type="string", example="محمد أحمد"),
     *                         @OA\Property(property="email", type="string", example="m.ahmed@example.com")
     *                     ),
     *                     @OA\Property(
     *                         property="dutyEmployee",
     *                         type="object",
     *                         nullable=true,
     *                         @OA\Property(property="user_id", type="integer", example=118),
     *                         @OA\Property(property="full_name", type="string", example="خالد سالم"),
     *                         @OA\Property(property="email", type="string", example="k.salem@example.com")
     *                     ),
     *                     @OA\Property(
     *                         property="leaveType",
     *                         type="object",
     *                         @OA\Property(property="constants_id", type="integer", example=323),
     *                         @OA\Property(property="category_name", type="string", example="سنوية"),
     *                         @OA\Property(property="field_two", type="number", example=21, description="عدد الأيام")
     *                     ),
     *                     @OA\Property(
     *                         property="approvals",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="status", type="string", example="approved"),
     *                             @OA\Property(property="remarks", type="string", nullable=true, example="موافق"),
     *                             @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-02 09:00:00"),
     *                             @OA\Property(
     *                                 property="staff",
     *                                 type="object",
     *                                 @OA\Property(property="user_id", type="integer", example=55),
     *                                 @OA\Property(property="full_name", type="string", example="مدير القسم"),
     *                                 @OA\Property(property="email", type="string", example="manager@example.com")
     *                             )
     *                         )
     *                     )
     *                 )
     *             ),
     *             @OA\Property(property="total", type="integer", example=120),
     *             @OA\Property(property="per_page", type="integer", example=15),
     *             @OA\Property(property="current_page", type="integer", example=1),
     *             @OA\Property(property="last_page", type="integer", example=8),
     *             @OA\Property(property="from", type="integer", example=1),
     *             @OA\Property(property="to", type="integer", example=15)
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
     *         response=403,
     *         description="Forbidden - No permission",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="غير مصرح لك بعرض طلبات الإجازات")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string")
     *         )
     *     )
     * )
     */
    public function getApplications(Request $request)
    {
        try {
            $user = Auth::user();
            $isUserHasThisPermission = $this->simplePermissionService->checkPermission($user, 'leave2');
            if (!$isUserHasThisPermission) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح لك بعرض طلبات الإجازات'
                ], 403);
            }
            $filters = LeaveApplicationFilterDTO::fromRequest($request->all());
            $result = $this->leaveService->getPaginatedApplications($filters, $user);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب طلبات الإجازات بنجاح',
                'created by' => $user->full_name,
                ...$result
            ]);
        } catch (\Exception $e) {
            Log::error('LeaveController::getApplications failed', [
                'error' => $e->getMessage(),
                'created by' => $user->full_name
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 403);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/leaves/applications",
     *     summary="Create a new leave application",
     *     tags={"Leave Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"leave_type_id","from_date","to_date","reason"},
     *             @OA\Property(property="employee_id", type="integer", example=37, description="معرف الموظف المستهدف (اختياري). إذا لم يتم تحديده، سيكون الطلب للمستخدم الحالي. للطلب لموظف آخر، يجب أن يكون لديك مستوى هرمي أعلى في نفس القسم."),
     *             @OA\Property(property="leave_type_id", type="integer", example=323, description="معرف نوع الإجازة - استخدم 311-316 أو 194,195,199,320-323"),
     *             @OA\Property(property="from_date", type="string", format="date", example="2025-12-01", description="تاريخ بداية الإجازة"),
     *             @OA\Property(property="to_date", type="string", format="date", example="2025-12-07", description="تاريخ نهاية الإجازة"),
     *             @OA\Property(property="reason", type="string", example="إجازة سنوية للراحة والاستجمام", description="سبب الإجازة"),
     *             @OA\Property(property="duty_employee_id", type="integer", example=37, description="معرف الموظف البديل (اختياري) - يجب أن يكون من نفس الشركة: 36,37,118,702,703,725,726,744"),
     *             @OA\Property(property="is_half_day", type="boolean", example=false, description="هل الإجازة نصف يوم؟"),
     *             @OA\Property(property="remarks", type="string", example="ملاحظات إضافية", description="ملاحظات (اختياري)"),
     *             @OA\Property(property="is_deducted", type="integer", enum={"0","1"}, example=1, description="هل تخصم من الرصيد؟ 0=لا، 1=نعم"),
     *             @OA\Property(property="place", type="integer", enum={"0","1"}, example=0, description="مكان الإجازة: 0=خارج الشركة، 1=داخل الشركة")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Leave application created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم إنشاء طلب الإجازة بنجاح"),
     *             @OA\Property(property="data", type="object")
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
     *         response=403,
     *         description="Forbidden - No permission",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="غير مصرح لك بإنشاء طلبات الإجازات")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="فشل التحقق من البيانات"),
     *             @OA\Property(property="errors", type="object",
     *                 @OA\Property(property="leave_type_id", type="array", @OA\Items(type="string", example="نوع الإجازة غير متاح لشركتك")),
     *                 @OA\Property(property="duty_employee_id", type="array", @OA\Items(type="string", example="الموظف البديل يجب أن يكون من نفس الشركة ونشط")),
     *                 @OA\Property(property="reason", type="array", @OA\Items(type="string", example="سبب الإجازة مطلوب"))
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="فشل في إنشاء طلب الإجازة")
     *         )
     *     )
     * )
     */
    public function createApplication(CreateLeaveApplicationRequest $request)
    {
        try {
            $user = Auth::user();
            $isUserHasThisPermission = $this->simplePermissionService->checkPermission($user, 'leave3');
            if (!$isUserHasThisPermission) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح لك بإنشاء طلبات الإجازات'
                ], 403);
            }
            // الحصول على معرف الشركة الفعلي من attributes
            $effectiveCompanyId = $request->attributes->get('effective_company_id');

            // استخدام employee_id من الطلب إذا تم تحديده، وإلا استخدام المستخدم الحالي
            $targetEmployeeId = $request->validated()['employee_id'] ?? $user->user_id;
            Log::info('LeaveController::createApplication', [
                'success' => true,
                'dto' => $request->validated(),
                'employee_id' => $targetEmployeeId,
                'requested_by' => $user->full_name
            ]);
            $dto = CreateLeaveApplicationDTO::fromRequest(
                $request->validated(),
                $effectiveCompanyId,
                $targetEmployeeId,  // الموظف المستهدف (قد يكون مختلف عن creator)
                $user->user_id      // createdBy - من يقوم بإنشاء الطلب
            );
            Log::info('LeaveController::createApplication', [
                'success' => true,
                'dto' => $dto,
                'employee_id' => $targetEmployeeId,
                'requested_by' => $user->full_name
            ]);
            $application = $this->leaveService->createApplication($dto);
            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء طلب الإجازة بنجاح',
                'data' => $application
            ], 201);
        } catch (\Exception $e) {
            Log::error('LeaveController::createApplication failed', [
                'error' => $e->getMessage(),
                'created by' => $user->full_name
            ]);
            return response()->json([
                'success' => false,
                'message' => 'فشل في إنشاء طلب الإجازة',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/leaves/applications/{id}",
     *     summary="Get a specific leave application",
     *     tags={"Leave Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Leave application ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Leave application retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم جلب تفاصيل طلب الإجازة بنجاح"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="leave_id", type="integer", example=123),
     *                 @OA\Property(property="employee_id", type="integer", example=37),
     *                 @OA\Property(property="duty_employee_id", type="integer", nullable=true, example=118),
     *                 @OA\Property(property="leave_type_id", type="integer", example=323),
     *                 @OA\Property(property="from_date", type="string", format="date", example="2025-12-01"),
     *                 @OA\Property(property="to_date", type="string", format="date", example="2025-12-07"),
     *                 @OA\Property(property="leave_hours", type="number", format="float", nullable=true, example=64.0),
     *                 @OA\Property(property="is_half_day", type="boolean", nullable=true, example=false),
     *                 @OA\Property(property="status", type="string", enum={"pending","approved","rejected"}, example="pending"),
     *                 @OA\Property(property="reason", type="string", example="إجازة سنوية"),
     *                 @OA\Property(property="remarks", type="string", nullable=true, example="ملاحظات"),
     *                 @OA\Property(property="company_id", type="integer", example=36),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-01 08:30:00"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-01 08:30:00"),
     *                 @OA\Property(
     *                     property="employee",
     *                     type="object",
     *                     @OA\Property(property="user_id", type="integer", example=37),
     *                     @OA\Property(property="full_name", type="string", example="محمد أحمد"),
     *                     @OA\Property(property="email", type="string", example="m.ahmed@example.com")
     *                 ),
     *                 @OA\Property(
     *                     property="dutyEmployee",
     *                     type="object",
     *                     nullable=true,
     *                     @OA\Property(property="user_id", type="integer", example=118),
     *                     @OA\Property(property="full_name", type="string", example="خالد سالم"),
     *                     @OA\Property(property="email", type="string", example="k.salem@example.com")
     *                 ),
     *                 @OA\Property(
     *                     property="leaveType",
     *                     type="object",
     *                     @OA\Property(property="constants_id", type="integer", example=323),
     *                     @OA\Property(property="category_name", type="string", example="سنوية"),
     *                     @OA\Property(property="field_two", type="number", example=21, description="عدد الأيام")
     *                 )
     *             )
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
     *         response=403,
     *         description="Forbidden - No permission",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="غير مصرح لك بعرض تفاصيل طلبات الإجازات")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Leave application not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="طلب الإجازة غير موجود")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="فشل في جلب تفاصيل طلب الإجازة")
     *         )
     *     )
     * )
     */
    public function showApplication(int $id, Request $request)
    {
        try {
            $user = Auth::user();

            // التحقق من الصلاحيات
            $isUserHasThisPermission = $this->simplePermissionService->checkPermission($user, 'leave2');
            if (!$isUserHasThisPermission) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح لك بعرض تفاصيل طلبات الإجازات'
                ], 403);
            }

            // الحصول على معرف الشركة الفعلي من attributes
            $effectiveCompanyId = $request->attributes->get('effective_company_id');

            $application = $this->leaveService->getApplicationById(
                $id, 
                $effectiveCompanyId, 
                $user->user_id, // إضافة user_id للموظفين
                $user
            );

            if (!$application) {
                Log::info('LeaveController::showApplication - Application not found or no permission', [
                    'application_id' => $id,
                    'user_id' => $user->user_id,
                    'user_type' => $user->user_type,
                    'user_name' => $user->full_name,
                    'effective_company_id' => $effectiveCompanyId
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'طلب الإجازة غير موجود أو ليس لديك صلاحية لعرضه'
                ], 404);
            }

            Log::info('LeaveController::showApplication', [
                'success' => true,
                'created by' => $user->full_name
            ]);

            return response()->json([
                'success' => true,
                'data' => $application
            ]);
        } catch (\Exception $e) {
            Log::error('LeaveController::showApplication failed', [
                'error' => $e->getMessage(),
                'application_id' => $id ?? null,
                'user_id' => $user->user_id ?? null,
                'user_type' => $user->user_type ?? null,
                'user_name' => $user->full_name ?? null,
                'trace' => $e->getTraceAsString()
            ]);
            
            // Handle specific model not found error
            if (str_contains($e->getMessage(), 'No query results for model')) {
                return response()->json([
                    'success' => false,
                    'message' => 'طلب الإجازة غير موجود'
                ], 404);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'خطأ في جلب طلب الإجازة',
                'error' => config('app.debug') ? $e->getMessage() : 'حدث خطأ داخلي'
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/leaves/applications/{id}",
     *     summary="Update a leave application",
     *     tags={"Leave Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Leave application ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="from_date", type="string", format="date", example="2025-12-01"),
     *             @OA\Property(property="to_date", type="string", format="date", example="2025-12-07"),
     *             @OA\Property(property="reason", type="string", example="سبب معدل للإجازة"),
     *             @OA\Property(property="remarks", type="string", example="ملاحظات معدلة")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Leave application updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم تحديث طلب الإجازة بنجاح"),
     *             @OA\Property(property="data", type="object")
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
     *         response=403,
     *         description="Forbidden - No permission",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="غير مصرح لك بتعديل طلبات الإجازات")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Leave application not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="طلب الإجازة غير موجود")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="فشل التحقق من البيانات"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="فشل في تحديث طلب الإجازة")
     *         )
     *     )
     * )
     */
    public function updateApplication(UpdateLeaveApplicationRequest $request, int $id)
    {

        try {
            $user = Auth::user();
            $isUserHasThisPermission = $this->simplePermissionService->checkPermission($user, 'leave4');
            if (!$isUserHasThisPermission) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح لك بتعديل طلبات الإجازات'
                ], 403);
            }
            $dto = UpdateLeaveApplicationDTO::fromRequest($request->validated());
            Log::info('LeaveController::updateApplication', [
                'success' => true,
                'dto' => $dto,
                'created by' => $user->full_name
            ]);
            $application = $this->leaveService->update_Application($id, $dto, $user);

            Log::info('LeaveController::updateApplication', [
                'success' => true,
                'created by' => $user->full_name
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث طلب الإجازة بنجاح',
                'data' => $application
            ]);
        } catch (\Exception $e) {
            Log::error('LeaveController::updateApplication failed', [
                'error' => $e->getMessage(),
                'created by' => $user->full_name
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/leaves/applications/{id}/cancel",
     *     summary="Cancel a leave application (mark as rejected)",
     *     description="Cancels a leave application by marking it as rejected. The application remains in the database for audit purposes with status 'rejected' and remarks indicating it was cancelled by the employee.",
     *     tags={"Leave Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Leave application ID to cancel",
     *         @OA\Schema(type="integer", example=123)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Leave application cancelled successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم إلغاء طلب الإجازة بنجاح")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Leave application not found or cannot be cancelled",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="طلب الإجازة غير موجود أو لا يمكن إلغاؤه")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Cannot cancel processed application",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="لا يمكن إلغاء طلب تم الموافقة عليه مسبقاً")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized - Invalid or missing token"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - No permission to cancel applications",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="غير مصرح لك بإلغاء طلبات الإجازات")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="فشل في إلغاء طلب الإجازة")
     *         )
     *     )
     * )
     */
    public function cancelApplication(int $id)
    {
        try {
            $user = Auth::user();
            $isUserHasThisPermission = $this->simplePermissionService->checkPermission($user, 'leave6');
            if (!$isUserHasThisPermission) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح لك بإلغاء طلبات الإجازات'
                ], 403);
            }
            $this->leaveService->cancelApplication($id, $user);

            Log::info('LeaveController::cancelApplication', [
                'success' => true,
                'message' => 'تم إلغاء طلب الإجازة بنجاح',
                'create_by' => $user->full_name
            ]);
            return response()->json([
                'success' => true,
                'message' => 'تم إلغاء طلب الإجازة بنجاح',
                'create_by' => $user->full_name
            ]);
        } catch (\Exception $e) {
            Log::info('LeaveController::cancelApplication', [
                'success' => false,
                'message' => $e->getMessage(),
                'create_by' => $user->full_name
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }


    /**
     * @OA\Post(
     *     path="/api/leaves/applications/{id}/approve-or-reject",
     *     summary="Approve or Reject leave application (Managers/HR only)",
     *     description="الموافقة على أو رفض طلب الإجازة",
     *     tags={"Leave Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="action",
     *         in="query",
     *         required=true,
     *         @OA\Schema(type="string", enum={"approve", "reject"}),
     *         description="الإجراء: approve للموافقة أو reject للرفض"
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="remarks", type="string", example="موافق على الطلب", description="ملاحظات (اختياري)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Leave application approved or rejected successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم الموافقة على طلب الإجازة بنجاح"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 description="Updated leave application details"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad Request - Invalid action or already processed application",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="تم الموافقة على هذا الطلب مسبقاً أو تم رفضه")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - No permission to approve/reject",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="ليس لديك صلاحية للموافقة على طلب هذا الموظف")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Not Found - Leave application not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="طلب الإجازة غير موجود")
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
     *         response=422,
     *         description="Validation Error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The given data was invalid"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="فشل في مراجعة طلب الإجازة"),
     *             @OA\Property(property="error", type="string", example="حدث خطأ داخلي")
     *         )
     *     )
     * )
     */
    public function approveApplication(ApproveLeaveApplicationRequest $request, int $id)
    {
        $user = Auth::user();
        try {
            // Check permissions - either leave7 (full approval) or leave2 (view + hierarchy approval)
            $hasFullApprovalPermission = $this->simplePermissionService->checkPermission($user, 'leave7');
            $hasViewPermission = $this->simplePermissionService->checkPermission($user, 'leave2');
            
            if (!$hasFullApprovalPermission && !$hasViewPermission) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح لك بمراجعة طلبات الإجازات'
                ], 403);
            }

            $action = $request->input('action'); // approve or reject

            Log::info('LeaveController::Request received', [
                'request' => $request->all(),
                'application_id' => $id,
                'action' => $action,
                'created by' => $user->full_name
            ]);

            if ($action === 'approve') {
                // استدعاء خدمة الموافقة على الطلب
                $application = $this->leaveService->approveApplication($id, $request);

                Log::info('LeaveController::Approved', [
                    'success' => true,
                    'application' => $application,
                    'created by' => $user->full_name
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'تم الموافقة على طلب الإجازة بنجاح',
                    'data' => $application
                ]);
            } else {
                // استدعاء خدمة رفض الطلب
                $effectiveCompanyId = $request->attributes->get('effective_company_id') ?? $user->company_id;
                $remarks = $request->input('remarks', 'تم رفض الطلب');

                $application = $this->leaveService->rejectApplication($id, $effectiveCompanyId, $user->user_id, $remarks);

                if (!$application) {
                    return response()->json([
                        'success' => false,
                        'message' => 'طلب الإجازة غير موجود'
                    ], 404);
                }

                Log::info('LeaveController::Rejected', [
                    'success' => true,
                    'application' => $application,
                    'created by' => $user->full_name
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'تم رفض طلب الإجازة بنجاح',
                    'data' => $application
                ]);
            }
        } catch (\Exception $e) {
            Log::error('LeaveController::approveApplication failed', [
                'message' => 'فشل في مراجعة طلب الإجازة',
                'error' => $e->getMessage(),
                'created by' => $user->full_name
            ]);
            
            // Handle specific permission/authorization errors
            if (str_contains($e->getMessage(), 'صلاحية') || 
                str_contains($e->getMessage(), 'ليس لديك') ||
                str_contains($e->getMessage(), 'permission')) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 403);
            }
            
            // Handle specific not found errors
            if (str_contains($e->getMessage(), 'غير موجود') ||
                str_contains($e->getMessage(), 'لم يتم العثور')) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 404);
            }
            
            // Handle business logic errors (already processed, invalid status, etc.)
            if (str_contains($e->getMessage(), 'تم الموافقة') ||
                str_contains($e->getMessage(), 'تم رفض') ||
                str_contains($e->getMessage(), 'مسبقاً') ||
                str_contains($e->getMessage(), 'لا يمكن') ||
                str_contains($e->getMessage(), 'فشل في الموافقة') ||
                str_contains($e->getMessage(), 'فشل في رفض')) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 400);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'فشل في مراجعة طلب الإجازة',
                'error' => config('app.debug') ? $e->getMessage() : 'حدث خطأ داخلي'
            ], 500);
        }
    }


    /**
     * @OA\Get(
     *     path="/api/leaves/check-balance",
     *     summary="Check leave balance for an employee",
     *     description="Check if an employee has sufficient leave balance for the requested dates",
     *     tags={"Leave Management"},
     *     security={{"bearerAuth":{}}},
     *    
     *     @OA\Parameter(
     *         name="leave_type_id",
     *         in="query",
     *         required=false,
     *         description="ID of the leave type",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="employee_id",
     *         in="query",
     *         required=false,
     *         description="معرف الموظف المستهدف (للشركة/المدير لعرض رصيد موظف آخر)",
     *         @OA\Schema(type="integer")
     *     ),
     *  
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="has_sufficient_balance", type="boolean"),
     *                 @OA\Property(property="available_balance", type="number", format="float"),
     *                 @OA\Property(property="requested_days", type="number", format="float"),
     *                 @OA\Property(property="message", type="string")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated")
     *         )
     *     )
     * )
     */
    public function checkLeaveBalance(CheckLeaveBalanceRequest $request)
    {
        try {
            $user = Auth::user();
            $leaveTypeId = $request->input('leave_type_id');
            $requestedEmployeeId = $request->input('employee_id');

            // تحديد الموظف المستهدف: افتراضيًا المستخدم الحالي
            $targetEmployee = $user;

            if (!is_null($requestedEmployeeId)) {
                $requestedEmployeeId = (int) $requestedEmployeeId;

                // إذا كان يطلب موظفًا غير نفسه، نتحقق من الصلاحيات
                if ($requestedEmployeeId !== $user->user_id) {
                    $targetEmployee = User::findOrFail($requestedEmployeeId);

                    if (!$targetEmployee) {
                        return response()->json([
                            'success' => false,
                            'message' => 'الموظف المطلوب غير موجود',
                        ], 404);
                    }

                    if (!$this->permissionService->canViewEmployeeRequests($user, $targetEmployee)) {
                        return response()->json([
                            'success' => false,
                            'message' => 'ليس لديك صلاحية لعرض ملخص رصيد هذا الموظف',
                        ], 403);
                    }
                }
            }

            // الحصول على معرف الشركة الفعلي للموظف المستهدف
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($targetEmployee);

            $summary = $this->leaveService->getDetailedLeaveSummary(
                $targetEmployee->user_id,
                $effectiveCompanyId,
                $leaveTypeId !== null ? (int) $leaveTypeId : null
            );
            Log::info('LeaveController::checkLeaveBalance:summary', [
                'success' => true,
                'data' => $summary,
                'message' => $leaveTypeId === null
                    ? 'تم جلب ملخص رصيد جميع أنواع الإجازات بنجاح'
                    : 'تم جلب ملخص رصيد الإجازة لهذا النوع بنجاح',
            ]);

            return response()->json([
                'success' => true,
                'data' => $summary,
                'message' => $leaveTypeId === null
                    ? 'تم جلب ملخص رصيد جميع أنواع الإجازات بنجاح'
                    : 'تم جلب ملخص رصيد الإجازة لهذا النوع بنجاح',
            ]);
        } catch (\Exception $e) {
            Log::error('LeaveController::checkLeaveBalance failed', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب رصيد الإجازات',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/leaves/stats",
     *     summary="Get leave statistics (Managers/HR only)",
     *     tags={"Leave Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Leave statistics retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="created by", type="string", example="Mohamed Ahmed")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - No permission to view statistics",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="غير مصرح لك بعرض الإحصائيات")
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
     *             @OA\Property(property="message", type="string", example="فشل في عرض الإحصائيات")
     *         )
     *     )
     * )
     */
    public function getStats()
    {
        $user = Auth::user();
        try {

            $companyId = $this->permissionService->getEffectiveCompanyId($user);

            $stats = $this->leaveService->getLeaveStatistics($companyId);

            return response()->json([
                'success' => true,
                'data' => $stats,
                'created by' => $user->full_name
            ]);
        } catch (\Exception $e) {
            Log::error('LeaveController::getStats failed', [
                'error' => $e->getMessage(),
                'created by' => $user->full_name
            ]);
            return response()->json([
                'success' => false,
                'message' => 'فشل في عرض الإحصائيات',
                'error' => $e->getMessage(),
                'created by' => $user->full_name
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/leaves/monthly-statistics",
     *     summary="Get monthly leave statistics for an employee",
     *     description="Returns detailed monthly breakdown of leave hours (granted, used, remaining) for each leave type. Supports leave accrual system.",
     *     tags={"Leave Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="employee_id",
     *         in="query",
     *         required=false,
     *         description="معرف الموظف المستهدف (للشركة/المدير لعرض إحصائيات موظف آخر)",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Monthly statistics retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم جلب الإحصائيات الشهرية بنجاح"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="employee_id", type="integer"),
     *                 @OA\Property(property="company_id", type="integer"),
     *                 @OA\Property(property="year", type="integer"),
     *                 @OA\Property(property="leave_types", type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="leave_type_id", type="integer"),
     *                         @OA\Property(property="leave_type_name", type="string"),
     *                         @OA\Property(property="assigned_hours", type="number"),
     *                         @OA\Property(property="enable_leave_accrual", type="boolean"),
     *                         @OA\Property(property="monthly_breakdown", type="object",
     *                             @OA\Property(property="1", type="object",
     *                                 @OA\Property(property="month_name", type="string", example="Jan"),
     *                                 @OA\Property(property="granted", type="number", example=13.33),
     *                                 @OA\Property(property="used", type="number", example=8.0),
     *                                 @OA\Property(property="remaining", type="number", example=5.33)
     *                             )
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - No permission to view this employee's statistics"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Employee not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="الموظف غير موجود")
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
     *             @OA\Property(property="message", type="string", example="حدث خطأ أثناء جلب الإحصائيات الشهرية")
     *         )
     *     )
     * )
     */
    public function getMonthlyStatistics(Request $request)
    {
        try {
            $user = Auth::user();
            $requestedEmployeeId = $request->input('employee_id');

            // تحديد الموظف المستهدف: افتراضيًا المستخدم الحالي
            $targetEmployee = $user;

            if (!is_null($requestedEmployeeId)) {
                $requestedEmployeeId = (int) $requestedEmployeeId;

                // إذا كان يطلب موظفًا غير نفسه، نتحقق من الصلاحيات
                if ($requestedEmployeeId !== $user->user_id) {
                    $targetEmployee = User::findOrFail($requestedEmployeeId);

                    if (!$targetEmployee) {
                        return response()->json([
                            'success' => false,
                            'message' => 'الموظف المطلوب غير موجود',
                        ], 404);
                    }

                    if (!$this->permissionService->canViewEmployeeRequests($user, $targetEmployee)) {
                        return response()->json([
                            'success' => false,
                            'message' => 'ليس لديك صلاحية لعرض إحصائيات هذا الموظف',
                        ], 403);
                    }
                }
            }

            // الحصول على معرف الشركة الفعلي للموظف المستهدف
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($targetEmployee);

            $statistics = $this->leaveService->getMonthlyLeaveStatistics(
                $targetEmployee->user_id,
                $effectiveCompanyId
            );

            Log::info('LeaveController::getMonthlyStatistics', [
                'success' => true,
                'employee_id' => $targetEmployee->user_id,
                'requested_by' => $user->full_name
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب الإحصائيات الشهرية بنجاح',
                'data' => $statistics
            ]);
        } catch (\Exception $e) {
            Log::error('LeaveController::getMonthlyStatistics failed', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب الإحصائيات الشهرية',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/leaves/enums",
     *     summary="Get leave enums as string and numeric values",
     *     tags={"Leave Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="statuses", type="array", @OA\Items(type="string"))
     *             )
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
     *             @OA\Property(property="message", type="string", example="حدث خطأ أثناء جلب حالات القوائم")
     *         )
     *     )
     * )
     */
    public function getLeaveEnums()
    {
        try {
            $enums = $this->leaveService->getLeaveEnums();

            return response()->json([
                'success' => true,
                'message' => 'تم جلب قوائم حالات الإجازات بنجاح',
                'data' => $enums
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب حالات القوائم',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
