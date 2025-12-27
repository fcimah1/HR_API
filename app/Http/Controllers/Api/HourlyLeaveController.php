<?php

namespace App\Http\Controllers\Api;

use App\DTOs\Leave\CreateHourlyLeaveDTO;
use App\DTOs\Leave\HourlyLeaveFilterDTO;
use App\DTOs\Leave\ApproveOrRejectHourlyLeaveDTO;
use App\DTOs\Leave\UpdateHourlyLeaveDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\HourlyLeave\GetHourlyLeaveRequest;
use App\Http\Requests\HourlyLeave\CancelHourlyLeaveRequest;
use App\Http\Requests\HourlyLeave\ApproveOrRejectHourlyLeaveRequest;
use App\Http\Requests\HourlyLeave\CreateHourlyLeaveRequest;
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
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="غير مصرح - لم يتم توفير رمز المصادقة",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="محظور - لا تملك صلاحية الوصول",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="غير مصرح لك بعرض طلبات الإستئذان")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="بيانات غير صالحة",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="البيانات المقدمة غير صالحة"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="خطأ في الخادم",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="حدث خطأ في الخادم")
     *         )
     *     )
     * )
     */
    public function index(GetHourlyLeaveRequest $request)
    {
        try {
            $user = Auth::user();
            
            // Check permissions - either leave7 (full approval) or leave2 (view + hierarchy approval)
            $hasFullApprovalPermission = $this->simplePermissionService->checkPermission($user, 'leave7');
            $hasViewPermission = $this->simplePermissionService->checkPermission($user, 'leave2');
            
            if (!$hasFullApprovalPermission && !$hasViewPermission) {
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
     *         description="معرف طلب الإستئذان",
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
     *         response=401,
     *         description="غير مصرح - لم يتم توفير رمز المصادقة",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="محظور - لا تملك صلاحية الوصول",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="غير مصرح لك بعرض تفاصيل طلبات الإستئذان")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="الطلب غير موجود",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="طلب الإستئذان غير موجود")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="خطأ في الخادم",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="خطأ في جلب طلب الإستئذان")
     *         )
     *     )
     * )
     */
    public function show(int $id, Request $request)
    {
        try {
            $user = Auth::user();

            // التحقق من الصلاحيات
            $isUserHasThisPermission = $this->simplePermissionService->checkPermission($user, 'leave2');
            if (!$isUserHasThisPermission) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح لك بعرض تفاصيل طلبات الإستئذان'
                ], 403);
            }

            // الحصول على معرف الشركة الفعلي من attributes
            $effectiveCompanyId = $request->attributes->get('effective_company_id');

            $application = $this->hourlyLeaveService->getHourlyLeaveById(
                $id, 
                $effectiveCompanyId, 
                $user->user_id, // إضافة user_id للموظفين
                $user
            );

            if (!$application) {
                Log::info('HourlyLeaveController::show', [
                    'success' => false,
                    'created_by' => $user->full_name
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'طلب الإجازة غير موجود أو ليس لديك صلاحية لعرضه'
                ], 404);
            }
            
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
     *             @OA\Property(property="employee_id", type="integer", example=37, description="معرف الموظف البديل (اختياري) - يجب أن يكون من نفس الشركة: 36,37,118,702,703,725,726,744"),
     *             @OA\Property(property="leave_type_id", type="integer", example=199, description="معرف نوع الإجازة"),
     *             @OA\Property(property="duty_employee_id", type="integer", example=37, description="معرف الموظف البديل (اختياري) - يجب أن يكون من نفس الشركة: 36,37,118,702,703,725,726,744"),
     *             @OA\Property(property="date", type="string", format="date", example="2025-12-01", description="تاريخ الإجازة"),
     *             @OA\Property(property="clock_in_m", type="string", example="01:00 PM", description="وقت بداية الإجازة"),
     *             @OA\Property(property="clock_out_m", type="string", example="02:00 PM", description="وقت نهاية الإجازة"),
     *             @OA\Property(property="reason", type="string", example="استراحة للراحة والاستجمام", description="سبب الإجازة (10 أحرف على الأقل)"),
     *             @OA\Property(property="remarks", type="string", example="ملاحظات إضافية", description="ملاحظات (اختياري)"),
     *             @OA\Property(property="is_deducted", type="integer", enum={"0","1"}, example=1, description="هل تخصم من الرصيد؟ 0=لا، 1=نعم"),
     *             @OA\Property(property="place", type="integer", enum={"0","1"}, example=0, description="مكان الإجازة: 0=خارج الشركة، 1=داخل الشركة")
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
     *         response=401,
     *         description="غير مصرح - لم يتم توفير رمز المصادقة",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="محظور - لا تملك صلاحية الوصول",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="غير مصرح لك بإنشاء طلبات استئذان")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error: Unprocessable Content",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="فشل التحقق من البيانات"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="خطأ في الخادم",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="فشل في إنشاء طلب استئذان")
     *         )
     *     )
     * )
     */
    public function store(CreateHourlyLeaveRequest $request)
    {
        try {
            $user = Auth::user();

            // التحقق من الصلاحيات
            if (!$this->simplePermissionService->checkPermission($user, 'leave3')) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح لك بإنشاء طلبات استئذان'
                ], 403);
            }

            // الحصول على معرف الشركة الفعلي
            $effectiveCompanyId = $request->attributes->get('effective_company_id');

            // استخدام employee_id من الطلب إذا تم تحديده، وإلا استخدام المستخدم الحالي
            $targetEmployeeId = $request->validated()['employee_id'] ?? $user->user_id;

            // إنشاء DTO من الطلب
            $dto = CreateHourlyLeaveDTO::fromRequest(
                $request->validated(),
                $effectiveCompanyId,
                $targetEmployeeId,  // الموظف المستهدف (قد يكون مختلف عن creator)
                $user->user_id      // createdBy - من يقوم بإنشاء الطلب
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
     *         description="معرف طلب الإستئذان",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="date", type="string", format="date", example="2025-12-01", description="تاريخ الإجازة"),
     *             @OA\Property(property="clock_in_m", type="string", example="01:00 PM", description="وقت بداية الإجازة"),
     *             @OA\Property(property="clock_out_m", type="string", example="02:00 PM", description="وقت نهاية الإجازة"),
     *             @OA\Property(property="reason", type="string", example="استراحة للراحة والاستجمام", description="سبب الإجازة (10 أحرف على الأقل)"),
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
     *         response=401,
     *         description="غير مصرح - لم يتم توفير رمز المصادقة",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="محظور - لا تملك صلاحية الوصول",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="غير مصرح لك بتحديث طلبات الإستئذان")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="الطلب غير موجود",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="طلب الإستئذان غير موجود")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="بيانات غير صالحة",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="فشل التحقق من البيانات"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="خطأ في الخادم",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="فشل في تحديث طلب الإستئذان")
     *         )
     *     )
     * )
     */
    public function update(int $id, UpdateHourlyLeaveRequest $request)
    {
        try {
            $user = Auth::user();

            // Check permissions - either leave7 (full approval) or leave4 (edit + hierarchy approval)
            $hasFullApprovalPermission = $this->simplePermissionService->checkPermission($user, 'leave7');
            $hasEditPermission = $this->simplePermissionService->checkPermission($user, 'leave4');
            
            if (!$hasFullApprovalPermission && !$hasEditPermission) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح لك بتعديل طلبات الإستئذان'
                ], 403);
            }

            // إنشاء DTO من الطلب
            $dto = UpdateHourlyLeaveDTO::fromRequest($request->validated());

            // تحديث طلب الإستئذان
            $application = $this->hourlyLeaveService->updateHourlyLeave($id, $dto, $user);

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
     *         response=401,
     *         description="غير مصرح - لم يتم توفير رمز المصادقة",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="محظور - لا تملك صلاحية الوصول",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="غير مصرح لك بإلغاء طلبات الإستئذان")
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
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="خطأ في الخادم",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="فشل في إلغاء طلب الإستئذان")
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
            
            return response()->json([
                'success' => true,
                'message' => 'تم إلغاء طلب الإستئذان بنجاح',
                'create_by' => $user->full_name
            ], );
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
     *         description="معرف طلب الإستئذان",
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
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="غير مصرح - لم يتم توفير رمز المصادقة",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="محظور - لا تملك صلاحية الوصول",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="غير مصرح لك بمراجعة طلبات الإستئذان")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="الطلب غير موجود",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="طلب الإستئذان غير موجود")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="بيانات غير صالحة",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="فشل التحقق من البيانات"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="خطأ في الخادم",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="فشل في مراجعة طلب الإستئذان")
     *         )
     *     )
     * )
     */
    public function approveOrReject(int $id, ApproveOrRejectHourlyLeaveRequest $request)
    {
        $user = Auth::user();
        try {
            // Check permissions - either leave7 (full approval) or leave2 (view + hierarchy approval)
            $hasFullApprovalPermission = $this->simplePermissionService->checkPermission($user, 'leave7');
            $hasViewPermission = $this->simplePermissionService->checkPermission($user, 'leave2');
            
            if (!$hasFullApprovalPermission && !$hasViewPermission) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح لك بمراجعة طلبات الإستئذان'
                ], 403);
            }

            $action = $request->input('action'); // approve or reject

            // إنشاء DTO
            $dto = ApproveOrRejectHourlyLeaveDTO::fromRequest(
                $request->validated(),
                $id,
                $user->user_id
            );

            // معالجة الطلب
            $application = $this->hourlyLeaveService->approveOrRejectHourlyLeave($id, $dto);

            if ($action === 'approve') {

                return response()->json([
                    'success' => true,
                    'message' => 'تم الموافقة على طلب الإستئذان بنجاح',
                    'data' => new HourlyLeaveResource($application)
                ]);
            } else {

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

    /**
     * @OA\Get(
     *     path="/api/hourly-leaves/enums",
     *     tags={"Hourly Leave Management"},
     *     summary="الحصول على قوائم التعداد الخاصة بالإستئذان بالساعات",
     *     description="يُرجع قوائم التعداد للحالات وأنواع الإجازات المستخدمة في نظام الإستئذان بالساعات",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="تم جلب قوائم الإستئذان الساعات بنجاح",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم جلب قوائم الإستئذان الساعات بنجاح"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="statuses_string", type="array", description="الحالات النصية",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="value", type="string", example="pending"),
     *                         @OA\Property(property="case_name", type="string", example="PENDING"),
     *                         @OA\Property(property="case_name_ar", type="string", example="قيد الانتظار")
     *                     )
     *                 ),
     *                 @OA\Property(property="statuses_numeric", type="array", description="الحالات الرقمية",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="value", type="integer", example=1),
     *                         @OA\Property(property="case_name", type="string", example="PENDING"),
     *                         @OA\Property(property="case_name_ar", type="string", example="قيد الانتظار")
     *                     )
     *                 ),
     *                 @OA\Property(property="leave_types", type="array", description="أنواع الإجازات",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="constants_id", type="integer", example=286),
     *                         @OA\Property(property="company_id", type="integer", example=142),
     *                         @OA\Property(property="type", type="string", example="leave_type"),
     *                         @OA\Property(property="category_name", type="string", example="مرضيه")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="غير مصرح - لم يتم توفير رمز المصادقة",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="خطأ في الخادم",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="حدث خطأ أثناء جلب القوائم"),
     *             @OA\Property(property="error", type="string", example="Database connection failed")
     *         )
     *     )
     * )
     */
    
    public function getEnums()
    {
        try {
            $enums = $this->hourlyLeaveService->getHourlyLeaveEnums();
        
            return response()->json([
                'success' => true,
                'message' => 'تم جلب قوائم الإستئذان الساعات بنجاح',
                'data' => $enums
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب القوائم',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    
}

