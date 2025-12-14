<?php

namespace App\Http\Controllers\Api;

use App\DTOs\Transfer\CreateTransferDTO;
use App\DTOs\Transfer\TransferFilterDTO;
use App\DTOs\Transfer\ApproveRejectTransferDTO;
use App\DTOs\Transfer\UpdateTransferDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Transfer\CreateTransferRequest;
use App\Http\Requests\Transfer\GetTransferRequest;
use App\Http\Requests\Transfer\ApproveRejectTransferRequest;
use App\Http\Requests\Transfer\UpdateTransferRequest;
use App\Http\Resources\TransferResource;
use App\Models\User;
use App\Services\TransferService;
use App\Services\SimplePermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="Transfer Management",
 *     description="إدارة النقل والتحويلات - Transfer management endpoints"
 * )
 */
class TransferController extends Controller
{
    public function __construct(
        private TransferService $transferService,
        private SimplePermissionService $permissionService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/transfers",
     *     summary="Get transfer requests list",
     *     description="الحصول على قائمة طلبات النقل مع إمكانية الفلترة والبحث - يشمل النقل الداخلي، بين الفروع، وبين الشركات",
     *     tags={"Transfer Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="employee_id",
     *         in="query",
     *         description="Filter by employee ID - فلترة حسب معرف الموظف",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by status - فلترة حسب الحالة",
     *         @OA\Schema(type="string", enum={"pending", "approved", "rejected"})
     *     ),
     *     @OA\Parameter(
     *         name="department_id",
     *         in="query",
     *         description="Filter by department ID - فلترة حسب القسم",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="transfer_type",
     *         in="query",
     *         description="Filter by transfer type - فلترة حسب نوع النقل (internal/branch/intercompany)",
     *         @OA\Schema(type="string", enum={"internal", "branch", "intercompany"})
     *     ),
     *     @OA\Parameter(
     *         name="from_date",
     *         in="query",
     *         description="Filter from date - فلترة من تاريخ",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="to_date",
     *         in="query",
     *         description="Filter to date - فلترة إلى تاريخ",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search in employee name - البحث في اسم الموظف",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page - عدد العناصر في الصفحة",
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number - رقم الصفحة",
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Transfers retrieved successfully - تم جلب طلبات النقل بنجاح",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم جلب طلبات النقل بنجاح"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="transfer_id", type="integer", example=1),
     *                     @OA\Property(property="employee_id", type="integer", example=37),
     *                     @OA\Property(property="transfer_date", type="string", format="date", example="2025-01-15"),
     *                     @OA\Property(property="transfer_type", type="string", example="internal"),
     *                     @OA\Property(property="transfer_type_text", type="string", example="نقل داخلي"),
     *                     @OA\Property(property="old_department", type="integer", example=5),
     *                     @OA\Property(property="transfer_department", type="integer", example=10),
     *                     @OA\Property(property="old_designation", type="integer", example=15),
     *                     @OA\Property(property="transfer_designation", type="integer", example=20),
     *                     @OA\Property(property="old_salary", type="number", example=5000),
     *                     @OA\Property(property="transfer_salary", type="number", example=5500),
     *                     @OA\Property(property="status", type="string", example="pending"),
     *                     @OA\Property(property="status_text", type="string", example="قيد المراجعة"),
     *                     @OA\Property(property="old_branch_id", type="integer", nullable=true, example=1),
     *                     @OA\Property(property="new_branch_id", type="integer", nullable=true, example=2),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(
     *                         property="employee",
     *                         type="object",
     *                         @OA\Property(property="user_id", type="integer", example=37),
     *                         @OA\Property(property="full_name", type="string", example="محمد أحمد")
     *                     )
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="pagination",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="last_page", type="integer", example=5),
     *                 @OA\Property(property="per_page", type="integer", example=15),
     *                 @OA\Property(property="total", type="integer", example=75)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated - غير مصرح"),
     *     @OA\Response(response=500, description="Server error - خطأ في الخادم")
     * )
     */
    public function index(GetTransferRequest $request)
    {
        try {
            $user = Auth::user();
            Log::info('TransferController::index - Request received', [
                'user_id' => $user->user_id,
                'user_type' => $user->user_type,
            ]);
            $filters = TransferFilterDTO::fromRequest($request->validated());
            $result = $this->transferService->getPaginatedTransfers($filters, $user);
            return response()->json([
                'success' => true,
                'message' => 'تم جلب طلبات النقل بنجاح',
                'data' => TransferResource::collection($result['data']),
                'pagination' => $result['pagination'],
            ]);
        } catch (\Exception $e) {
            Log::error('TransferController::index - Error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب طلبات النقل',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/transfers/{id}",
     *     summary="Get a specific transfer request",
     *     description="عرض تفاصيل طلب نقل محدد",
     *     tags={"Transfer Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Transfer ID - معرف طلب النقل",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Transfer retrieved successfully - تم جلب طلب النقل بنجاح",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم جلب طلب النقل بنجاح"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="transfer_id", type="integer", example=1),
     *                 @OA\Property(property="employee_id", type="integer", example=37),
     *                 @OA\Property(property="transfer_date", type="string", format="date"),
     *                 @OA\Property(property="transfer_type", type="string"),
     *                 @OA\Property(property="transfer_type_text", type="string"),
     *                 @OA\Property(property="old_department", type="integer"),
     *                 @OA\Property(property="transfer_department", type="integer"),
     *                 @OA\Property(property="old_designation", type="integer"),
     *                 @OA\Property(property="transfer_designation", type="integer"),
     *                 @OA\Property(property="old_salary", type="number"),
     *                 @OA\Property(property="transfer_salary", type="number"),
     *                 @OA\Property(property="status", type="string"),
     *                 @OA\Property(property="status_text", type="string"),
     *                 @OA\Property(property="description", type="string"),
     *                 @OA\Property(
     *                     property="employee",
     *                     type="object",
     *                     @OA\Property(property="user_id", type="integer"),
     *                     @OA\Property(property="full_name", type="string")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated - غير مصرح"),
     *     @OA\Response(
     *         response=404,
     *         description="Transfer not found - طلب النقل غير موجود",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="طلب النقل غير موجود أو ليس لديك صلاحية للوصول إليه")
     *         )
     *     ),
     *     @OA\Response(response=500, description="Server error - خطأ في الخادم")
     * )
     */
    public function show(int $id, Request $request)
    {
        try {
            $user = Auth::user();
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
            $transfer = $this->transferService->getTransferById($id, $effectiveCompanyId, null, $user);
            if (!$transfer) {
                return response()->json([
                    'success' => false,
                    'message' => 'طلب النقل غير موجود أو ليس لديك صلاحية للوصول إليه',
                ], 404);
            }
            return response()->json([
                'success' => true,
                'message' => 'تم جلب طلب النقل بنجاح',
                'data' => new TransferResource($transfer),
            ]);
        } catch (\Exception $e) {
            Log::error('TransferController::show - Error', ['transfer_id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب طلب النقل',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/transfers",
     *     summary="Create a new transfer request",
     *     description="إنشاء طلب نقل جديد - يدعم النقل الداخلي (تغيير القسم/المسمى)، النقل بين الفروع، والنقل بين الشركات",
     *     tags={"Transfer Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"employee_id", "transfer_date"},
     *             @OA\Property(property="employee_id", type="integer", example=37, description="معرف الموظف المطلوب نقله - مطلوب"),
     *             @OA\Property(property="transfer_date", type="string", format="date", example="2025-01-15", description="تاريخ النقل الفعلي - مطلوب"),
     *             @OA\Property(property="transfer_type", type="string", enum={"internal", "branch", "intercompany"}, example="internal", description="نوع النقل: internal (داخلي)، branch (بين الفروع)، intercompany (بين الشركات)"),
     *             @OA\Property(property="transfer_department", type="integer", example=10, description="القسم الجديد (للنقل الداخلي)"),
     *             @OA\Property(property="transfer_designation", type="integer", example=20, description="المسمى الوظيفي الجديد (للنقل الداخلي)"),
     *             @OA\Property(property="transfer_salary", type="number", example=5500, description="الراتب الجديد"),
     *             @OA\Property(property="new_branch_id", type="integer", example=2, description="معرف الفرع الجديد (للنقل بين الفروع)"),
     *             @OA\Property(property="new_company_id", type="integer", example=3, description="معرف الشركة الجديدة (للنقل بين الشركات)"),
     *             @OA\Property(property="description", type="string", example="نقل بسبب إعادة الهيكلة", description="سبب/وصف النقل")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Transfer created successfully - تم إنشاء طلب النقل بنجاح",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم إنشاء طلب النقل بنجاح"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated - غير مصرح"),
     *     @OA\Response(
     *         response=404,
     *         description="Employee not found - الموظف غير موجود",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="الموظف غير موجود")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error - خطأ في التحقق",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="فشل التحقق من البيانات"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(response=500, description="Server error - خطأ في الخادم")
     * )
     */
    public function store(CreateTransferRequest $request)
    {
        try {
            $user = Auth::user();
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
            $employeeId = $request->validated()['employee_id'];
            $employee = User::find($employeeId);
            if (!$employee) {
                return response()->json([
                    'success' => false,
                    'message' => 'الموظف غير موجود',
                ], 404);
            }
            Log::info('TransferController::store - Creating transfer', [
                'user_id' => $user->user_id,
                'employee_id' => $employeeId,
            ]);
            $data = $request->validated();
            $data['old_salary'] = $data['old_salary'] ?? $employee->salary;
            $data['old_designation'] = $data['old_designation'] ?? $employee->designation_id;
            $data['old_department'] = $data['old_department'] ?? $employee->department_id;
            $dto = CreateTransferDTO::fromRequest(
                $data,
                $effectiveCompanyId,
                $employeeId,
                $user->user_id
            );
            $transfer = $this->transferService->createTransfer($dto);
            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء طلب النقل بنجاح',
                'data' => new TransferResource($transfer),
            ], 201);
        } catch (\Exception $e) {
            Log::error('TransferController::store - Error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'فشل في إنشاء طلب النقل',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/transfers/{id}",
     *     summary="Update a transfer request",
     *     description="تحديث طلب نقل - يمكن فقط تعديل الطلبات المعلقة",
     *     tags={"Transfer Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Transfer ID - معرف طلب النقل",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="transfer_date", type="string", format="date", example="2025-01-20", description="تاريخ النقل الجديد"),
     *             @OA\Property(property="transfer_department", type="integer", example=15, description="القسم الجديد"),
     *             @OA\Property(property="transfer_designation", type="integer", example=25, description="المسمى الوظيفي الجديد"),
     *             @OA\Property(property="transfer_salary", type="number", example=6000, description="الراتب الجديد"),
     *             @OA\Property(property="description", type="string", example="وصف معدل", description="السبب/الوصف الجديد")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Transfer updated successfully - تم تحديث طلب النقل بنجاح",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم تحديث طلب النقل بنجاح"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Cannot update processed transfer - لا يمكن تعديل طلب تمت معالجته"),
     *     @OA\Response(response=401, description="Unauthenticated - غير مصرح"),
     *     @OA\Response(response=403, description="Forbidden - ليس لديك صلاحية"),
     *     @OA\Response(response=404, description="Not found - غير موجود"),
     *     @OA\Response(response=500, description="Server error - خطأ في الخادم")
     * )
     */
    public function update(int $id, UpdateTransferRequest $request)
    {
        try {
            $user = Auth::user();
            Log::info('TransferController::update - Updating transfer', ['transfer_id' => $id, 'user_id' => $user->user_id]);
            $dto = UpdateTransferDTO::fromRequest($request->validated());
            $transfer = $this->transferService->updateTransfer($id, $dto, $user);
            return response()->json([
                'success' => true,
                'message' => 'تم تحديث طلب النقل بنجاح',
                'data' => new TransferResource($transfer),
            ]);
        } catch (\Exception $e) {
            Log::error('TransferController::update - Error', ['transfer_id' => $id, 'error' => $e->getMessage()]);
            $statusCode = str_contains($e->getMessage(), 'غير موجود') ? 404 : (str_contains($e->getMessage(), 'صلاحية') ? 403 : (str_contains($e->getMessage(), 'معالجته') ? 400 : 500));
            return response()->json(['success' => false, 'message' => $e->getMessage()], $statusCode);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/transfers/{id}",
     *     summary="Delete a transfer request",
     *     description="حذف/إلغاء طلب نقل - يمكن فقط حذف الطلبات المعلقة",
     *     tags={"Transfer Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Transfer ID - معرف طلب النقل",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Transfer deleted successfully - تم حذف طلب النقل بنجاح",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم حذف طلب النقل بنجاح")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Cannot delete processed transfer - لا يمكن حذف طلب تمت معالجته"),
     *     @OA\Response(response=401, description="Unauthenticated - غير مصرح"),
     *     @OA\Response(response=403, description="Forbidden - ليس لديك صلاحية"),
     *     @OA\Response(response=404, description="Not found - غير موجود"),
     *     @OA\Response(response=500, description="Server error - خطأ في الخادم")
     * )
     */
    public function destroy(int $id)
    {
        try {
            $user = Auth::user();
            Log::info('TransferController::destroy - Deleting transfer', ['transfer_id' => $id, 'user_id' => $user->user_id]);
            $this->transferService->deleteTransfer($id, $user);
            return response()->json(['success' => true, 'message' => 'تم حذف طلب النقل بنجاح']);
        } catch (\Exception $e) {
            Log::error('TransferController::destroy - Error', ['transfer_id' => $id, 'error' => $e->getMessage()]);
            $statusCode = str_contains($e->getMessage(), 'غير موجود') ? 404 : (str_contains($e->getMessage(), 'صلاحية') ? 403 : (str_contains($e->getMessage(), 'معالجته') ? 400 : 500));
            return response()->json(['success' => false, 'message' => $e->getMessage()], $statusCode);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/transfers/{id}/approve-or-reject",
     *     summary="Approve or reject a transfer request",
     *     description="الموافقة على أو رفض طلب نقل - للمديرين فقط. عند الموافقة على نقل بين الشركات، يتطلب موافقة من كلتا الشركتين",
     *     tags={"Transfer Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Transfer ID - معرف طلب النقل",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"action"},
     *             @OA\Property(property="action", type="string", enum={"approve", "reject"}, example="approve", description="الإجراء: approve للموافقة أو reject للرفض"),
     *             @OA\Property(property="remarks", type="string", example="موافق على النقل لتحسين أداء الفريق", description="ملاحظات (اختياري)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Transfer processed successfully - تم معالجة طلب النقل بنجاح",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تمت الموافقة على طلب النقل"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Transfer already processed - تم معالجة الطلب مسبقاً",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="تم معالجة هذا الطلب مسبقاً")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated - غير مصرح"),
     *     @OA\Response(response=403, description="Forbidden - ليس لديك صلاحية"),
     *     @OA\Response(response=404, description="Not found - غير موجود"),
     *     @OA\Response(response=500, description="Server error - خطأ في الخادم")
     * )
     */
    public function approveOrReject(int $id, ApproveRejectTransferRequest $request)
    {
        try {
            $user = Auth::user();
            Log::info('TransferController::approveOrReject - Processing transfer', [
                'transfer_id' => $id,
                'user_id' => $user->user_id,
                'action' => $request->action,
            ]);
            $dto = ApproveRejectTransferDTO::fromRequest($request->validated(), $user->user_id);
            $transfer = $this->transferService->approveOrRejectTransfer($id, $dto);
            $actionMessage = $dto->action === 'approve' ? 'تمت الموافقة على طلب النقل' : 'تم رفض طلب النقل';
            return response()->json([
                'success' => true,
                'message' => $actionMessage,
                'data' => new TransferResource($transfer),
            ]);
        } catch (\Exception $e) {
            Log::error('TransferController::approveOrReject - Error', ['transfer_id' => $id, 'error' => $e->getMessage()]);
            $statusCode = str_contains($e->getMessage(), 'غير موجود') ? 404 : (str_contains($e->getMessage(), 'صلاحية') ? 403 : (str_contains($e->getMessage(), 'مسبقاً') ? 400 : 500));
            return response()->json(['success' => false, 'message' => $e->getMessage()], $statusCode);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/transfers/statuses",
     *     summary="Get transfer statuses",
     *     description="الحصول على قائمة حالات النقل المتاحة",
     *     tags={"Transfer Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Statuses retrieved successfully - تم جلب الحالات بنجاح",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم جلب الحالات بنجاح"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="value", type="string", example="pending"),
     *                     @OA\Property(property="label", type="string", example="قيد المراجعة")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated - غير مصرح"),
     *     @OA\Response(response=500, description="Server error - خطأ في الخادم")
     * )
     */
    public function getStatuses()
    {
        try {
            $statuses = $this->transferService->getTransferStatuses();
            return response()->json(['success' => true, 'message' => 'تم جلب الحالات بنجاح', 'data' => $statuses]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'حدث خطأ أثناء جلب الحالات'], 500);
        }
    }
}
