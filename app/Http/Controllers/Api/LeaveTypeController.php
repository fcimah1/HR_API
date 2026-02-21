<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LeaveTypeService;
use App\Services\SimplePermissionService;
use App\DTOs\Leave\CreateLeaveTypeDTO;
use App\DTOs\Leave\UpdateLeaveTypeDTO;
use App\DTOs\LeaveType\LeaveTypeFilterDTO;
use App\Http\Requests\Leave\CreateLeaveTypeRequest;
use App\Http\Requests\Leave\UpdateLeaveTypeRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="Leave Type Management",
 *     description="Leave types management"
 * )
 */
class LeaveTypeController extends Controller
{
    protected $leaveTypeService;
    protected $permissionService;
    public function __construct(
        LeaveTypeService $leaveTypeService,
        SimplePermissionService $permissionService
    ) {
        $this->leaveTypeService = $leaveTypeService;
        $this->permissionService = $permissionService;
    }
    /**
     * @OA\Get(
     *     path="/api/leave-types",
     *     summary="جلب جميع أنواع الإجازات مع البحث والترقيم",
     *     tags={"Leave Type Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string"),
     *         description="مصطلح البحث عن اسم نوع الإجازة أو الوصف"
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="integer", default=15),
     *         description="عدد العناصر في كل صفحة"
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="integer", default=1),
     *         description="رقم الصفحة"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="تم جلب أنواع الإجازات بنجاح",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم جلب أنواع الإجازات بنجاح"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="غير مصرح - يجب تسجيل الدخول",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="غير مصرح لك")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="غير مصرح لك بعرض أنواع الإجازات",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="غير مصرح لك بعرض أنواع الإجازات")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="فشل التحقق من البيانات",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="فشل التحقق من البيانات"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="حدث خطأ في الخادم",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="حدث خطأ في الخادم")
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        try {
            $user = Auth::user();

            // التحقق من الصلاحيات
            if (!$this->permissionService->checkPermission($user, 'leave_type1')) {
                Log::info('LeaveTypeController::index', [
                    'success' => false,
                    'user_id' => $user->user_id,
                    'company_id' => $effectiveCompanyId ?? null,
                    'message' => 'غير مصرح لك بعرض أنواع الإجازات'
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح لك بعرض أنواع الإجازات'
                ], 403);
            }
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);

            $filters = LeaveTypeFilterDTO::fromRequest($request);
            // جلب البيانات مع الترقيم وفلترة القيود
            $leaveTypes = $this->leaveTypeService->getActiveLeaveTypes($effectiveCompanyId, $filters->toArray(), $user);

            Log::info('LeaveTypeController::index', [
                'success' => true,
                'user_id' => $user->user_id,
                'company_id' => $effectiveCompanyId ?? null,
                'message' => 'تم جلب أنواع الإجازات بنجاح'
            ]);
            return response()->json([
                'success' => true,
                'data' => $leaveTypes
            ]);
        } catch (\Exception $e) {
            Log::error('LeaveTypeController::index - Error: ' . $e->getMessage(), [
                'user_id' => $user->id ?? null,
                'company_id' => $effectiveCompanyId ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء استرجاع أنواع الإجازات',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/leave-types",
     *     summary="إنشاء نوع إجازة جديد (HR/Admin only)",
     *     tags={"Leave Type Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"leave_type_name","leave_days"},
     *             @OA\Property(property="leave_type_name", type="string", example="إجازة دراسية"),
     *             @OA\Property(property="requires_approval", type="boolean", example=true),
     *             @OA\Property(property="is_paid_leave", type="boolean", example=true),
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="تم إنشاء نوع الإجازة بنجاح"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="غير مصرح - يجب تسجيل الدخول"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="غير مصرح - لا تمتلك الصلاحيات اللازمة"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="فشل التحقق من البيانات"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="حدث خطأ في الخادم"
     *     )
     * )
     */
    public function storeLeaveType(CreateLeaveTypeRequest $request)
    {
        try {
            $user = Auth::user();

            $isUserHasThisPermission = $this->permissionService->checkPermission($user, 'leave_type2');
            if (!$isUserHasThisPermission) {
                Log::info('LeaveTypeController::storeLeaveType', [
                    'success' => false,
                    'user_id' => $user->user_id,
                    'company_id' => $effectiveCompanyId ?? null,
                    'message' => 'غير مصرح لك بإنشاء أنواع إجازات جديدة'
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح لك بإنشاء أنواع إجازات جديدة'
                ], 403);
            }

            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);

            $dto = CreateLeaveTypeDTO::fromRequest(
                $request->validated(),
                $effectiveCompanyId,
            );

            $leaveType = $this->leaveTypeService->createLeaveType($dto);

            Log::info('LeaveTypeController::storeLeaveType', [
                'success' => true,
                'user_id' => $user->user_id,
                'company_id' => $effectiveCompanyId ?? null,
                'message' => 'تم إنشاء نوع الإجازة بنجاح'
            ]);
            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء نوع الإجازة بنجاح',
                'data' => $leaveType,
                'created by' => $user->full_name
            ], 201);
        } catch (\Exception $e) {
            Log::error('LeaveTypeController::storeLeaveType failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->user_id,
                'company_id' => $effectiveCompanyId ?? null,
                'created by' => $user->full_name
            ]);
            return response()->json([
                'success' => false,
                'message' => 'فشل في إنشاء نوع الإجازة',
                'error' => $e->getMessage(),
                'created by' => $user->full_name
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/leave-types/{id}",
     *     summary="جلب تفاصيل نوع إجازة معين",
     *     tags={"Leave Type Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="تم جلب تفاصيل نوع الإجازة بنجاح"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="نوع الإجازة غير موجود"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="غير مصرح - يجب تسجيل الدخول"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="غير مصرح - لا تمتلك الصلاحيات اللازمة"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="فشل التحقق من البيانات"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="حدث خطأ في الخادم"
     *     )
     * )
     */
    public function showLeaveType(int $id)
    {
        try {
            $user = Auth::user();
            $isUserHasThisPermission = $this->permissionService->checkPermission($user, 'leave_type1');
            if (!$isUserHasThisPermission) {
                Log::info('LeaveTypeController::showLeaveType', [
                    'success' => false,
                    'user_id' => $user->user_id,
                    'company_id' => $effectiveCompanyId ?? null,
                    'message' => 'غير مصرح لك بعرض أنواع الإجازات'
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح لك بعرض أنواع الإجازات'
                ], 403);
            }

            $leaveType = $this->leaveTypeService->getLeaveType($id);

            if ($user->user_type !== 'company') {
                $restrictedTypes = $this->permissionService->getRestrictedValues(
                    $user->user_id,
                    $this->permissionService->getEffectiveCompanyId($user),
                    'leave_type_'
                );

                if (in_array($leaveType['leave_type_id'], $restrictedTypes)) {
                    Log::info('LeaveTypeController::showLeaveType', [
                        'success' => false,
                        'user_id' => $user->user_id,
                        'company_id' => $effectiveCompanyId ?? null,
                        'message' => 'غير مصرح لك بعرض هذا النوع من الإجازة'
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => 'غير مصرح لك بعرض هذا النوع من الإجازة'
                    ], 403);
                }
            }

            Log::info('LeaveTypeController::showLeaveType', [
                'success' => true,
                'user_id' => $user->user_id,
                'company_id' => $effectiveCompanyId ?? null,
                'message' => 'تم جلب نوع الإجازة بنجاح'
            ]);
            return response()->json([
                'success' => true,
                'data' => $leaveType
            ]);
        } catch (\Exception $e) {
            Log::error('LeaveTypeController::showLeaveType failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->user_id,
                'company_id' => $effectiveCompanyId ?? null,
                'created by' => $user->full_name
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/leave-types/{id}",
     *     summary="تحديث نوع إجازة",
     *     tags={"Leave Type Management"},
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
     *             required={"leave_type_name"},
     *             @OA\Property(property="leave_type_name",       type="string",  example="إجازة دراسية",   description="اسم نوع الإجازة"),
     *             @OA\Property(property="requires_approval",     type="boolean", example=true,             description="هل تتطلب الإجازة موافقة؟"),
     *             @OA\Property(property="is_paid_leave",         type="boolean", example=true,             description="هل هي إجازة مدفوعة؟"),
     *             @OA\Property(property="enable_leave_accrual",  type="boolean", example=false,            description="تمكين استحقاق الإجازة"),
     *             @OA\Property(property="is_carry",              type="boolean", example=false,            description="السماح بترحيل رصيد الإجازة"),
     *             @OA\Property(property="carry_limit",           type="number",  example=10,               description="الحد المتجاوز للترحيل"),
     *             @OA\Property(property="is_negative_quota",     type="boolean", example=false,            description="السماح برصيد الإجازة السالب"),
     *             @OA\Property(property="negative_limit",        type="number",  example=5,                description="رصيد الإجازة المستحق المسموح به"),
     *             @OA\Property(property="is_quota",              type="boolean", example=true,             description="تفعيل تخصيص النسبة السنوية"),
     *             @OA\Property(
     *                 property="quota_assign",
     *                 type="object",
     *                 description="تخصيص عدد أيام الإجازة لكل سنة خدمة (مفتاح=index السنة 0-based، قيمة=عدد الأيام). يدعم حتى 50 سنة.",
     *                 example={"0": 15, "1": 18, "2": 21, "49": 30}
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="تم تحديث نوع الإجازة بنجاح"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="غير مصرح - يجب تسجيل الدخول"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="غير مصرح - لا تمتلك الصلاحيات اللازمة"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="فشل التحقق من البيانات"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="حدث خطأ في الخادم"
     *     )
     * )
     */
    public function updateLeaveType(UpdateLeaveTypeRequest $request, int $id)
    {
        try {
            $user = Auth::user();
            $isUserHasThisPermission = $this->permissionService->checkPermission($user, 'leave_type3');
            if (!$isUserHasThisPermission) {
                Log::info('LeaveTypeController::updateLeaveType', [
                    'success' => false,
                    'user_id' => $user->user_id,
                    'company_id' => $effectiveCompanyId ?? null,
                    'message' => 'غير مصرح لك بتعديل أنواع الإجازات'
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح لك بتعديل أنواع الإجازات'
                ], 403);
            }

            // تعيين company_id مبكراً ليكون متاحاً في جميع مسارات الكود (Log, catch)
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);

            // Check operation restrictions
            if (!$this->permissionService->isCompanyOwner($user)) {
                $restrictedTypes = $this->permissionService->getRestrictedValues(
                    $user->user_id,
                    $effectiveCompanyId,
                    'leave_type_'
                );

                // Check if the ID we are updating is in restricted list
                if (in_array($id, $restrictedTypes)) {
                    Log::info('LeaveTypeController::updateLeaveType', [
                        'success' => false,
                        'user_id' => $user->user_id,
                        'company_id' => $effectiveCompanyId,
                        'message' => 'غير مصرح لك بتعديل نوع الإجازة هذا (قيود العمليات)'
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => 'غير مصرح لك بتعديل نوع الإجازة هذا (قيود العمليات)'
                    ], 403);
                }
            }

            // ربط معرّف نوع الإجازة بالبيانات المُدخلة ثم بناء الـ DTO
            $data = $request->validated();
            $data['leave_type_id'] = $id;

            $dto = UpdateLeaveTypeDTO::fromRequest($data);

            $leaveType = $this->leaveTypeService->updateLeaveType($dto);

            Log::info('LeaveTypeController::updateLeaveType', [
                'success' => true,
                'user_id' => $user->user_id,
                'company_id' => $effectiveCompanyId,
                'leave_type_id' => $id,
                'message' => 'تم تحديث نوع الإجازة بنجاح'
            ]);
            return response()->json([
                'success' => true,
                'message' => 'تم تحديث نوع الإجازة بنجاح',
                'data' => $leaveType,
                'updated_by' => $user->full_name
            ]);
        } catch (\Exception $e) {
            Log::error('LeaveTypeController::updateLeaveType failed', [
                'error' => $e->getMessage(),
                'leave_type_id' => $id,
                'user_id' => $user->user_id ?? null,
                'company_id' => $effectiveCompanyId ?? null,
                'updated_by' => $user->full_name ?? null
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/leave-types/{id}",
     *     summary="حذف نوع إجازة",
     *     tags={"Leave Type Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="تم حذف نوع الإجازة بنجاح"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="غير مصرح - يجب تسجيل الدخول"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="غير مصرح - لا تمتلك الصلاحيات اللازمة"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="فشل التحقق من البيانات"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="حدث خطأ في الخادم"
     *     )
     * )
     */
    public function destroyLeaveType(int $id)
    {
        try {
            $user = Auth::user();
            $isUserHasThisPermission = $this->permissionService->checkPermission($user, 'leave_type4');
            if (!$isUserHasThisPermission) {
                Log::info('LeaveTypeController::destroy', [
                    'success' => false,
                    'user_id' => $user->user_id,
                    'company_id' => $effectiveCompanyId ?? null,
                    'message' => 'غير مصرح لك بحذف أنواع الإجازات'
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح لك بحذف أنواع الإجازات'
                ], 403);
            }

            // Check operation restrictions
            if (!$this->permissionService->isCompanyOwner($user)) {
                $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
                $restrictedTypes = $this->permissionService->getRestrictedValues(
                    $user->user_id,
                    $effectiveCompanyId,
                    'leave_type_'
                );

                if (in_array($id, $restrictedTypes)) {
                    Log::info('LeaveTypeController::destroy', [
                        'success' => false,
                        'user_id' => $user->user_id,
                        'company_id' => $effectiveCompanyId ?? null,
                        'message' => 'غير مصرح لك بحذف نوع الإجازة هذا (قيود العمليات)'
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => 'غير مصرح لك بحذف نوع الإجازة هذا (قيود العمليات)'
                    ], 403);
                }
            }

            $this->leaveTypeService->deleteLeaveType($id, $user);

            Log::info('LeaveTypeController::destroy', [
                'success' => true,
                'user_id' => $user->user_id,
                'company_id' => $effectiveCompanyId ?? null,
                'message' => 'تم حذف نوع الإجازة بنجاح'
            ]);
            return response()->json([
                'success' => true,
                'message' => 'تم حذف نوع الإجازة بنجاح'
            ]);
        } catch (\Exception $e) {
            Log::error('LeaveTypeController::destroyLeaveType failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->user_id,
                'company_id' => $effectiveCompanyId ?? null,
                'created by' => $user->full_name
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }
}
