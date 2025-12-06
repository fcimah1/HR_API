<?php

namespace App\Http\Controllers\Api;

use App\DTOs\Leave\CreateHourlyLeaveDTO;
use App\DTOs\Leave\HourlyLeaveFilterDTO;
use App\DTOs\Leave\CancelHourlyLeaveDTO;
use App\DTOs\Leave\ApproveOrRejectHourlyLeaveDTO;
use App\DTOs\Leave\UpdateHourlyLeaveDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Leave\CreateHourlyLeaveRequest;
use App\Http\Requests\HourlyLeave\GetHourlyLeaveRequest;
use App\Http\Requests\HourlyLeave\CancelHourlyLeaveRequest;
use App\Http\Requests\HourlyLeave\ApproveOrRejectHourlyLeaveRequest;
use App\Http\Requests\HourlyLeave\UpdateHourlyLeaveRequest;
use App\Http\Resources\HourlyLeaveResource;
use App\Services\HourlyLeaveService;
use App\Services\SimplePermissionService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="Hourly Leave Management",
 *     description="إدارة طلبات الإستئذان بالساعات"
 * )
 */
class HourlyLeaveController extends Controller
{
    public $simplePermissionService;
    
    public function __construct(
        private HourlyLeaveService $hourlyLeaveService,
        private SimplePermissionService $permissionService
    ) {
        $this->simplePermissionService = $permissionService;
    }

    /**
     * @OA\Get(
     *     path="/api/hourly-leaves",
     *     tags={"Hourly Leave Management"},
     *     summary="الحصول على قائمة طلبات الإستئذان بالساعات",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="employee_id",
     *         in="query",
     *         description="فلترة حسب معرف الموظف (للمديرين/HR فقط)",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="فلترة حسب الحالة (pending/approved/rejected)",
     *         @OA\Schema(type="string", enum={"pending", "approved", "rejected"})
     *     ),
     *     @OA\Parameter(
     *         name="leave_type_id",
     *         in="query",
     *         description="فلترة حسب نوع الإجازة",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="from_date",
     *         in="query",
     *         description="فلترة من تاريخ",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="to_date",
     *         in="query",
     *         description="فلترة إلى تاريخ",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="البحث في اسم الموظف أو نوع الإجازة",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="عدد العناصر في الصفحة",
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="رقم الصفحة",
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="تم جلب طلبات الإستئذان بنجاح",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم جلب طلبات الإستئذان بنجاح"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="pagination", type="object")
     *         )
     *     )
     * )
     */
    public function index(GetHourlyLeaveRequest $request)
    {
        try {
            $user = Auth::user();
            
            // التحقق من الصلاحيات
            if (!$this->simplePermissionService->checkPermission($user, 'leave2')) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح لك بعرض طلبات الإستئذان'
                ], 403);
            }

            $filters = HourlyLeaveFilterDTO::fromRequest($request->validated());
            $result = $this->hourlyLeaveService->getPaginatedHourlyLeaves($filters, $user);

            // استخدام HourlyLeaveResource::collection() مباشرة
            return HourlyLeaveResource::collection($result['data'])->additional([
                'success' => true,
                'message' => 'تم جلب طلبات الإستئذان بنجاح',
                'created_by' => $user->full_name,
                'pagination' => [
                    'total' => $result['total'],
                    'per_page' => $result['per_page'],
                    'current_page' => $result['current_page'],
                    'last_page' => $result['last_page'],
                    'from' => $result['from'],
                    'to' => $result['to'],
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('HourlyLeaveController::index failed', [
                'error' => $e->getMessage(),
                'created_by' => $user->full_name ?? null
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 403);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/hourly-leaves/{id}",
     *     tags={"Hourly Leave Management"},
     *     summary="الحصول على تفاصيل طلب إستئذان بالساعات",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="تم جلب تفاصيل الطلب بنجاح",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="الطلب غير موجود"
     *     )
     * )
     */
    public function show(int $id, Request $request)
    {
        try {
            $user = Auth::user();

            // التحقق من الصلاحيات
            if (!$this->simplePermissionService->checkPermission($user, 'leave2')) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح لك بعرض تفاصيل طلبات الإستئذان'
                ], 403);
            }

            // الحصول على معرف الشركة الفعلي من attributes
            $effectiveCompanyId = $request->attributes->get('effective_company_id');

            $application = $this->hourlyLeaveService->getHourlyLeaveById($id, $effectiveCompanyId);

            if (!$application) {
                Log::info('HourlyLeaveController::show', [
                    'success' => false,
                    'created_by' => $user->full_name
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'طلب الإستئذان غير موجود'
                ], 404);
            }

            Log::info('HourlyLeaveController::show', [
                'success' => true,
                'created_by' => $user->full_name
            ]);

            return response()->json([
                'success' => true,
                'data' => new HourlyLeaveResource($application)
            ]);
        } catch (\Exception $e) {
            Log::error('HourlyLeaveController::show failed', [
                'error' => $e->getMessage(),
                'created_by' => $user->full_name ?? null
            ]);
            return response()->json([
                'success' => false,
                'message' => 'خطأ في جلب طلب الإستئذان',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/hourly-leaves",
     *     tags={"Hourly Leave Management"},
     *     summary="إنشاء طلب إستئذان بالساعات",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"leave_type_id","date","clock_in_m","clock_out_m","reason"},
     *             @OA\Property(property="leave_type_id", type="integer", example=199, description="معرف نوع الإجازة"),
     *             @OA\Property(property="duty_employee_id", type="integer", example=37, description="معرف الموظف البديل (اختياري) - يجب أن يكون من نفس الشركة: 36,37,118,702,703,725,726,744"),
     *             @OA\Property(property="date", type="string", format="date", example="2025-12-01", description="تاريخ الإجازة"),
     *             @OA\Property(property="clock_in_m", type="string", example="01:00 PM", description="وقت بداية الإجازة"),
     *             @OA\Property(property="clock_out_m", type="string", example="02:00 PM", description="وقت نهاية الإجازة"),
     *             @OA\Property(property="reason", type="string", example="استراحة للراحة والاستجمام", description="سبب الإجازة (10 أحرف على الأقل)"),
     *             @OA\Property(property="remarks", type="string", example="ملاحظات إضافية", description="ملاحظات (اختياري)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="تم إنشاء طلب الإستئذان بنجاح",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم إنشاء طلب استئذان بنجاح"),
     *             @OA\Property(property="data", type="object")
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
     *     )
     * )
     */
    public function store(CreateHourlyLeaveRequest $request)
    {
        try {
            $user = Auth::user();
            
            Log::info('HourlyLeaveController::store', [
                'user_id' => $user->user_id,
                'request' => $request->all()
            ]);

            // التحقق من الصلاحيات
            if (!$this->simplePermissionService->checkPermission($user, 'leave3')) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح لك بإنشاء طلبات استئذان'
                ], 403);
            }

            // الحصول على معرف الشركة الفعلي
            $effectiveCompanyId = $request->attributes->get('effective_company_id');

            // إنشاء DTO من الطلب
            $dto = CreateHourlyLeaveDTO::fromRequest(
                $request->validated(),
                $effectiveCompanyId,
                $user->user_id
            );

            // إنشاء طلب الإستئذان
            $application = $this->hourlyLeaveService->createHourlyLeave($dto);

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء طلب استئذان بنجاح',
                'data' => new HourlyLeaveResource($application)
            ], 201);

        } catch (\Exception $e) {
            Log::error('HourlyLeaveController::store failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $user->user_id ?? null
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'فشل في إنشاء طلب استئذان: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * @OA\Put(
     *     path="/api/hourly-leaves/{id}",
     *     tags={"Hourly Leave Management"},
     *     summary="تحديث طلب إستئذان بالساعات",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="date", type="string", format="date", example="2025-12-01", description="تاريخ الإجازة"),
     *             @OA\Property(property="clock_in_m", type="string", example="01:00 PM", description="وقت بداية الإجازة"),
     *             @OA\Property(property="clock_out_m", type="string", example="02:00 PM", description="وقت نهاية الإجازة"),
     *             @OA\Property(property="reason", type="string", example="استراحة للراحة والاستجمام", description="سبب الإجازة (10 أحرف على الأقل)"),
     *             @OA\Property(property="duty_employee_id", type="integer", example=37, description="معرف الموظف البديل (اختياري)"),
     *             @OA\Property(property="remarks", type="string", example="ملاحظات إضافية", description="ملاحظات (اختياري)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="تم تحديث طلب الإستئذان بنجاح",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم تحديث طلب الإستئذان بنجاح"),
     *             @OA\Property(property="data", type="object")
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
     *     )
     * )
     */
    public function update(int $id, UpdateHourlyLeaveRequest $request)
    {
        try {
            $user = Auth::user();

            // التحقق من الصلاحيات
            if (!$this->simplePermissionService->checkPermission($user, 'leave4')) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح لك بتعديل طلبات الإستئذان'
                ], 403);
            }

            // إنشاء DTO من الطلب
            $dto = UpdateHourlyLeaveDTO::fromRequest($request->validated());

            Log::info('HourlyLeaveController::update', [
                'application_id' => $id,
                'dto' => $dto,
                'created_by' => $user->full_name
            ]);

            // تحديث طلب الإستئذان
            $application = $this->hourlyLeaveService->updateHourlyLeave($id, $dto, $user);

            Log::info('HourlyLeaveController::update', [
                'success' => true,
                'created_by' => $user->full_name
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث طلب الإستئذان بنجاح',
                'data' => new HourlyLeaveResource($application)
            ]);
        } catch (\Exception $e) {
            Log::error('HourlyLeaveController::update failed', [
                'error' => $e->getMessage(),
                'created_by' => $user->full_name ?? null
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/hourly-leaves/{id}/cancel",
     *     tags={"Hourly Leave Management"},
     *     summary="إلغاء طلب إستئذان بالساعات",
     *     description="إلغاء طلب إستئذان بالساعات عن طريق وضع علامة كرفض. يبقى الطلب في قاعدة البيانات لأغراض التدقيق مع حالة 'rejected' وملاحظات تشير إلى أنه تم إلغاؤه من قبل الموظف.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="معرف طلب الإستئذان للإلغاء",
     *         @OA\Schema(type="integer", example=123)
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="reason", type="string", example="سبب الإلغاء", description="سبب الإلغاء (اختياري)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="تم إلغاء طلب الإستئذان بنجاح",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم إلغاء طلب الإستئذان بنجاح")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="طلب الإستئذان غير موجود أو لا يمكن إلغاؤه",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="طلب الإستئذان غير موجود أو لا يمكن إلغاؤه")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="لا يمكن إلغاء طلب تمت معالجته",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="لا يمكن إلغاء طلب تم الموافقة عليه مسبقاً")
     *         )
     *     )
     * )
     */
    public function cancel(int $id, CancelHourlyLeaveRequest $request)
    {
        try {
            $user = Auth::user();
            
            // التحقق من الصلاحيات
            if (!$this->simplePermissionService->checkPermission($user, 'leave6')) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح لك بإلغاء طلبات الإستئذان'
                ], 403);
            }
            
            $this->hourlyLeaveService->cancelHourlyLeave($id, $user);

            Log::info('HourlyLeaveController::cancel', [
                'success' => true,
                'message' => 'تم إلغاء طلب الإستئذان بنجاح',
                'create_by' => $user->full_name
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'تم إلغاء طلب الإستئذان بنجاح',
                'create_by' => $user->full_name
            ]);
        } catch (\Exception $e) {
            Log::info('HourlyLeaveController::cancel', [
                'success' => false,
                'message' => $e->getMessage(),
                'create_by' => $user->full_name ?? null
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/hourly-leaves/{id}/approve-or-reject",
     *     tags={"Hourly Leave Management"},
     *     summary="الموافقة على أو رفض طلب إستئذان بالساعات (للمديرين/HR فقط)",
     *     description="الموافقة على أو رفض طلب إستئذان بالساعات",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"action"},
     *             @OA\Property(property="action", type="string", enum={"approve", "reject"}, description="الإجراء: approve للموافقة أو reject للرفض"),
     *             @OA\Property(property="remarks", type="string", example="موافق على الطلب", description="ملاحظات (اختياري)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="تمت معالجة الطلب بنجاح",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function approveOrReject(int $id, ApproveOrRejectHourlyLeaveRequest $request)
    {
        $user = Auth::user();
        try {
            // التحقق من الصلاحيات
            if (!$this->simplePermissionService->checkPermission($user, 'leave7')) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح لك بمراجعة طلبات الإستئذان'
                ], 403);
            }

            $action = $request->input('action'); // approve or reject

            Log::info('HourlyLeaveController::approveOrReject Request received', [
                'request' => $request->all(),
                'application_id' => $id,
                'action' => $action,
                'created_by' => $user->full_name
            ]);

            // إنشاء DTO
            $dto = ApproveOrRejectHourlyLeaveDTO::fromRequest(
                $request->validated(),
                $id,
                $user->user_id
            );

            // معالجة الطلب
            $application = $this->hourlyLeaveService->approveOrRejectHourlyLeave($id, $dto);

            if ($action === 'approve') {
                Log::info('HourlyLeaveController::approveOrReject Approved', [
                    'success' => true,
                    'application' => $application,
                    'created_by' => $user->full_name
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'تم الموافقة على طلب الإستئذان بنجاح',
                    'data' => new HourlyLeaveResource($application)
                ]);
            } else {
                Log::info('HourlyLeaveController::approveOrReject Rejected', [
                    'success' => true,
                    'application' => $application,
                    'created_by' => $user->full_name
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'تم رفض طلب الإستئذان بنجاح',
                    'data' => new HourlyLeaveResource($application)
                ]);
            }
        } catch (\Exception $e) {
            Log::error('HourlyLeaveController::approveOrReject failed', [
                'message' => 'فشل في مراجعة طلب الإستئذان',
                'error' => $e->getMessage(),
                'created_by' => $user->full_name ?? null
            ]);
            return response()->json([
                'success' => false,
                'message' => 'فشل في مراجعة طلب الإستئذان',
                'error' => $e->getMessage(),
                'created_by' => $user->full_name ?? null
            ], 500);
        }
    }
}

