<?php

namespace App\Http\Controllers\Api;

use App\DTOs\Transfer\CreateTransferDTO;
use App\DTOs\Transfer\TransferFilterDTO;
use App\DTOs\Transfer\ApproveRejectTransferDTO;
use App\DTOs\Transfer\UpdateTransferDTO;
use App\DTOs\Transfer\CompanyApprovalDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Transfer\CreateInternalTransferRequest;
use App\Http\Requests\Transfer\CreateBranchTransferRequest;
use App\Http\Requests\Transfer\CreateIntercompanyTransferRequest;
use App\Http\Requests\Transfer\GetTransferRequest;
use App\Http\Requests\Transfer\ApproveRejectTransferRequest;
use App\Http\Requests\Transfer\UpdateInternalTransferRequest;
use App\Http\Requests\Transfer\UpdateBranchTransferRequest;
use App\Http\Requests\Transfer\UpdateIntercompanyTransferRequest;
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
     *                     ),
     *                     @OA\Property(
     *                         property="approvals",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="status", type="integer", example=1),
     *                             @OA\Property(property="approval_level", type="integer", example=1),
     *                             @OA\Property(property="updated_at", type="string", format="date-time", example="2025-01-16 10:00:00"),
     *                             @OA\Property(
     *                                 property="staff",
     *                                 type="object",
     *                                 @OA\Property(property="user_id", type="integer", example=55),
     *                                 @OA\Property(property="full_name", type="string", example="مدير القسم"),
     *                                 @OA\Property(property="department", type="string", example="Department Name"),
     *                                 @OA\Property(property="position", type="string", example="Manager")
     *                             )
     *                         )
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
     *                     @OA\Property(property="user_id", type="integer"),
     *                     @OA\Property(property="full_name", type="string")
     *                 ),
     *                 @OA\Property(
     *                     property="approvals",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="status", type="integer", example=1),
     *                         @OA\Property(property="approval_level", type="integer", example=1),
     *                         @OA\Property(property="updated_at", type="string", format="date-time"),
     *                         @OA\Property(
     *                             property="staff",
     *                             type="object",
     *                             @OA\Property(property="user_id", type="integer"),
     *                             @OA\Property(property="full_name", type="string"),
     *                             @OA\Property(property="department", type="string"),
     *                             @OA\Property(property="position", type="string")
     *                         )
     *                     )
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
     *     path="/api/transfers/internal",
     *     summary="Create a new internal transfer request",
     *     tags={"Transfer Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"employee_id", "transfer_date", "reason", "transfer_department", "transfer_designation"},
     *             @OA\Property(property="employee_id", type="integer", example=1, description="Employee ID"),
     *             @OA\Property(property="transfer_date", type="string", format="date", example="2024-01-01", description="Transfer Date"),
     *             @OA\Property(property="reason", type="string", example="Promotion", description="Reason for transfer"),
     *             @OA\Property(property="transfer_department", type="integer", example=10, description="New Department ID"),
     *             @OA\Property(property="transfer_designation", type="integer", example=5, description="New Designation ID"),
     *             @OA\Property(property="notify_send_to", type="array", @OA\Items(type="integer"), example={55,703}, description="User ID to notify (optional)")
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
    public function storeInternal(CreateInternalTransferRequest $request)
    {
        return $this->processTransferRequest($request);
    }

    /**
     * @OA\Post(
     *     path="/api/transfers/branch",
     *     summary="Create a new branch transfer request",
     *     tags={"Transfer Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"employee_id", "transfer_date", "reason", "new_branch_id"},
     *             @OA\Property(property="employee_id", type="integer", example=1, description="Employee ID"),
     *             @OA\Property(property="transfer_date", type="string", format="date", example="2024-01-01", description="Transfer Date"),
     *             @OA\Property(property="reason", type="string", example="Relocation", description="Reason for transfer"),
     *             @OA\Property(property="new_branch_id", type="integer", example=3, description="New Branch ID"),
     *             @OA\Property(property="notify_send_to", type="array", @OA\Items(type="integer"), example={55,703}, description="User ID to notify (optional)")
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
    public function storeBranch(CreateBranchTransferRequest $request)
    {
        return $this->processTransferRequest($request);
    }

    /**
     * @OA\Post(
     *     path="/api/transfers/intercompany",
     *     summary="Create a new intercompany transfer request",
     *     tags={"Transfer Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"employee_id", "transfer_date", "reason", "new_company_id"},
     *             @OA\Property(property="employee_id", type="integer", example=1, description="Employee ID"),
     *             @OA\Property(property="transfer_date", type="string", format="date", example="2024-01-01", description="Transfer Date"),
     *             @OA\Property(property="reason", type="string", example="New Opportunity", description="Reason for transfer"),
     *             @OA\Property(property="new_company_id", type="integer", example=2, description="New Company ID"),
     *             @OA\Property(property="notify_send_to", type="array", @OA\Items(type="integer"), example={55,703}, description="User ID to notify (optional)")
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
    public function storeIntercompany(CreateIntercompanyTransferRequest $request)
    {
        return $this->processTransferRequest($request);
    }

    /**
     * Helper method to process transfer requests
     */
    private function processTransferRequest($request)
    {
        try {
            $user = Auth::user();
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);

            $data = $request->validated();
            $data['transfer_type'] = $request->getTransferType();

            // إضافة notify_send_to إلى البيانات
            if ($request->has('notify_send_to')) {
                $data['notify_send_to'] = $request->input('notify_send_to');
            }

            $employeeId = $data['employee_id'];
            $employee = User::find($employeeId);
            if (!$employee) {
                return response()->json([
                    'success' => false,
                    'message' => 'الموظف غير موجود',
                ], 404);
            }

            Log::info('TransferController::processTransferRequest - Creating transfer', [
                'user_id' => $user->user_id,
                'employee_id' => $employeeId,
                'type' => $data['transfer_type']
            ]);

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
            Log::error('TransferController::processTransferRequest - Error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'فشل في إنشاء طلب النقل',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/transfers/internal/{id}",
     *     summary="Update an internal transfer request",
     *     tags={"Transfer Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="Transfer ID", @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="employee_id", type="integer", example=1, description="Employee ID"),
     *             @OA\Property(property="transfer_date", type="string", format="date", example="2024-01-01", description="Transfer Date"),
     *             @OA\Property(property="reason", type="string", example="Reason", description="Reason"),
     *             @OA\Property(property="transfer_department", type="integer", example=10, description="New Department ID"),
     *             @OA\Property(property="transfer_designation", type="integer", example=5, description="New Designation ID"),
     *             @OA\Property(property="notify_send_to", type="array", @OA\Items(type="integer"), example={55,703}, description="User IDs to notify")
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
    public function updateInternal(int $id, UpdateInternalTransferRequest $request)
    {
        return $this->processUpdateTransferRequest($id, $request);
    }

    /**
     * @OA\Put(
     *     path="/api/transfers/branch/{id}",
     *     summary="Update a branch transfer request",
     *     tags={"Transfer Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="Transfer ID", @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="employee_id", type="integer", example=1, description="Employee ID"),
     *             @OA\Property(property="transfer_date", type="string", format="date", example="2024-01-01", description="Transfer Date"),
     *             @OA\Property(property="reason", type="string", example="Reason", description="Reason"),
     *             @OA\Property(property="new_branch_id", type="integer", example=3, description="New Branch ID"),
     *             @OA\Property(property="notify_send_to", type="array", @OA\Items(type="integer"), example={55,703}, description="User IDs to notify")
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
    public function updateBranch(int $id, UpdateBranchTransferRequest $request)
    {
        return $this->processUpdateTransferRequest($id, $request);
    }

    /**
     * @OA\Put(
     *     path="/api/transfers/intercompany/{id}",
     *     summary="Update an intercompany transfer request",
     *     tags={"Transfer Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="Transfer ID", @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="employee_id", type="integer", example=1, description="Employee ID"),
     *             @OA\Property(property="transfer_date", type="string", format="date", example="2024-01-01", description="Transfer Date"),
     *             @OA\Property(property="reason", type="string", example="Reason", description="Reason"),
     *             @OA\Property(property="new_company_id", type="integer", example=2, description="New Company ID"),
     *             @OA\Property(property="notify_send_to", type="array", @OA\Items(type="integer"), example={55,703}, description="User IDs to notify")
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
    public function updateIntercompany(int $id, UpdateIntercompanyTransferRequest $request)
    {
        return $this->processUpdateTransferRequest($id, $request);
    }

    /**
     * Helper method to process update transfer requests
     */
    private function processUpdateTransferRequest(int $id, $request)
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
            return response()->json(['success' => true, 'message' => 'تم إلغاء طلب النقل بنجاح']);
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
     * @OA\Post(
     *     path="/api/transfers/{id}/approve-current-company",
     *     summary="Approve or reject transfer by current company",
     *     description="موافقة أو رفض النقل من قبل الشركة الحالية (للنقل بين الشركات فقط)",
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
     *             @OA\Property(property="action", type="string", enum={"approve", "reject"}, example="approve"),
     *             @OA\Property(property="remarks", type="string", example="موافق على النقل")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Transfer processed successfully - تم معالجة طلب النقل بنجاح",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تمت الموافقة من قبل الشركة الحالية"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Already processed - تمت المعالجة مسبقاً"),
     *     @OA\Response(response=403, description="Forbidden - ليس لديك صلاحية"),
     *     @OA\Response(response=404, description="Not found - غير موجود")
     * )
     */
    public function approveByCurrentCompany(int $id, ApproveRejectTransferRequest $request)
    {
        try {
            $user = Auth::user();
            $dto = CompanyApprovalDTO::fromRequest([
                'action' => $request->input('action'),
                'approval_type' => 'current_company',
                'approved_by' => $user->user_id,
                'remarks' => $request->input('remarks'),
            ]);
            $transfer = $this->transferService->approveByCurrentCompany($id, $dto);
            $actionMessage = $dto->isApprove()
                ? 'تمت الموافقة من قبل الشركة الحالية'
                : 'تم رفض النقل من قبل الشركة الحالية';

            return response()->json([
                'success' => true,
                'message' => $actionMessage,
                'data' => new TransferResource($transfer),
            ]);
        } catch (\Exception $e) {
            Log::error('TransferController::approveByCurrentCompany - Error', [
                'transfer_id' => $id,
                'error' => $e->getMessage()
            ]);
            $statusCode = str_contains($e->getMessage(), 'غير موجود') ? 404
                : (str_contains($e->getMessage(), 'صلاحية') ? 403
                    : (str_contains($e->getMessage(), 'مسبقاً') ? 400 : 500));

            return response()->json(['success' => false, 'message' => $e->getMessage()], $statusCode);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/transfers/{id}/approve-new-company",
     *     summary="Approve or reject transfer by new company",
     *     description="موافقة أو رفض النقل من قبل الشركة الجديدة (للنقل بين الشركات فقط) - يتم التحقق من المتطلبات تلقائياً",
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
     *             @OA\Property(property="action", type="string", enum={"approve", "reject"}, example="approve"),
     *             @OA\Property(property="remarks", type="string", example="موافق على استقبال الموظف")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Transfer processed successfully - تم معالجة طلب النقل بنجاح",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تمت الموافقة النهائية وتم تنفيذ النقل"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation failed - متطلبات غير مستوفاة",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="لا يمكن الموافقة - الموظف لديه متطلبات غير مستوفاة"),
     *             @OA\Property(
     *                 property="blockers",
     *                 type="object",
     *                 description="تفاصيل المتطلبات غير المستوفاة",
     *                 @OA\Property(
     *                     property="active_leaves",
     *                     type="object",
     *                     @OA\Property(property="message", type="string", example="لديه إجازات نشطة"),
     *                     @OA\Property(property="count", type="integer", example=1)
     *                 ),
     *                 @OA\Property(
     *                     property="active_advances",
     *                     type="object",
     *                     @OA\Property(property="message", type="string", example="لديه سلف غير مسددة"),
     *                     @OA\Property(property="count", type="integer", example=2)
     *                 ),
     *                 @OA\Property(
     *                     property="unreturned_custody",
     *                     type="object",
     *                     @OA\Property(property="message", type="string", example="لديه عهد غير مرتجعة"),
     *                     @OA\Property(property="count", type="integer", example=3)
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="validations",
     *                 type="object",
     *                 description="تفاصيل الفحوصات مع العناصر",
     *                 @OA\Property(
     *                     property="active_leaves",
     *                     type="object",
     *                     @OA\Property(property="passed", type="boolean", example=false),
     *                     @OA\Property(property="count", type="integer", example=1),
     *                     @OA\Property(property="items", type="array", @OA\Items(type="object"))
     *                 ),
     *                 @OA\Property(
     *                     property="active_advances",
     *                     type="object",
     *                     @OA\Property(property="passed", type="boolean", example=true),
     *                     @OA\Property(property="count", type="integer", example=0),
     *                     @OA\Property(property="items", type="array", @OA\Items(type="object"))
     *                 ),
     *                 @OA\Property(
     *                     property="unreturned_custody",
     *                     type="object",
     *                     @OA\Property(property="passed", type="boolean", example=true),
     *                     @OA\Property(property="count", type="integer", example=0),
     *                     @OA\Property(property="items", type="array", @OA\Items(type="object"))
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=403, description="Forbidden - ليس لديك صلاحية"),
     *     @OA\Response(response=404, description="Not found - غير موجود")
     * )
     */
    public function approveByNewCompany(int $id, ApproveRejectTransferRequest $request)
    {
        try {
            $user = Auth::user();
            $dto = CompanyApprovalDTO::fromRequest([
                'action' => $request->input('action'),
                'approval_type' => 'new_company',
                'approved_by' => $user->user_id,
                'remarks' => $request->input('remarks'),
            ]);
            $transfer = $this->transferService->approveByNewCompany($id, $dto);
            $actionMessage = $dto->isApprove()
                ? 'تمت الموافقة النهائية وتم تنفيذ النقل'
                : 'تم رفض النقل من قبل الشركة الجديدة';

            return response()->json([
                'success' => true,
                'message' => $actionMessage,
                'data' => new TransferResource($transfer),
            ]);
        } catch (\Exception $e) {
            Log::error('TransferController::approveByNewCompany - Error', [
                'transfer_id' => $id,
                'error' => $e->getMessage()
            ]);

            // تحقق إذا كانت الرسالة تحتوي على تفاصيل validation
            $errorData = json_decode($e->getMessage(), true);
            if (json_last_error() === JSON_ERROR_NONE && isset($errorData['blockers'])) {
                return response()->json([
                    'success' => false,
                    'message' => $errorData['message'],
                    'blockers' => $errorData['blockers'],
                    'validations' => $errorData['validations'],
                ], 400);
            }

            $statusCode = str_contains($e->getMessage(), 'غير موجود') ? 404
                : (str_contains($e->getMessage(), 'صلاحية') ? 403
                    : (str_contains($e->getMessage(), 'مسبقاً') || str_contains($e->getMessage(), 'متطلبات') ? 400 : 500));

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

    /**
     * @OA\Get(
     *     path="/api/transfers/available-companies",
     *     summary="Get available companies for transfer",
     *     description="الحصول على قائمة الشركات المتاحة للنقل إليها",
     *     tags={"Transfer Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Companies retrieved successfully - تم جلب الشركات بنجاح",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم جلب الشركات المتاحة بنجاح"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="company_id", type="integer", example=1),
     *                     @OA\Property(property="company_name", type="string", example="شركة الأولى"),
     *                     @OA\Property(property="branches", type="array", @OA\Items(type="object", @OA\Property(property="branch_id", type="integer", example=1), @OA\Property(property="branch_name", type="string", example="فرع الأولى")))
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="unauthenticated - غير مصرح"),
     *     @OA\Response(response=500, description="internal server error - خطأ في الخادم")
     * )
     */

    public function getCompaniesWithBranches()
    {
        try {
            // استخدام الـ Repository بدلاً من الاستعلام المباشر
            $companies = $this->transferService->getCompaniesWithBranches();

            return response()->json([
                'success' => true,
                'data' => $companies
            ]);
        } catch (\Exception $e) {
            Log::error('TransferController::getCompaniesWithBranches - Error', [
                'error' => $e->getMessage(),
                'message' => 'حدث خطأ أثناء جلب الشركات مع الفروع'
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/transfers/branches",
     *     summary="Get branches for a specific company",
     *     description="الحصول على فروع شركة محددة لاستخدامها في نموذج النقل",
     *     tags={"Transfer Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="company_id",
     *         in="query",
     *         required=true,
     *         description="Company ID - معرف الشركة",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Branches retrieved successfully - تم جلب الفروع بنجاح",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="branch_id", type="integer", example=1),
     *                     @OA\Property(property="branch_name", type="string", example="الفرع الرئيسي")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated - غير مصرح"),
     *     @OA\Response(response=422, description="Validation error - خطأ في التحقق"),
     *     @OA\Response(response=500, description="Server error - خطأ في الخادم")
     * )
     */
    public function getBranches(Request $request)
    {
        try {
            $request->validate([
                'company_id' => 'required|integer|exists:ci_erp_users,user_id',
            ]);

            $companyId = $request->query('company_id');
            $branches = \App\Models\Branch::forCompany($companyId)->get(['branch_id', 'branch_name']);

            return response()->json([
                'success' => true,
                'data' => $branches
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل التحقق من البيانات',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('TransferController::getBranches - Error', [
                'error' => $e->getMessage(),
                'company_id' => $request->query('company_id')
            ]);
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب الفروع: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/transfers/{id}/pre-transfer-validation",
     *     summary="Validate employee eligibility for transfer",
     *     description="التحقق من أهلية الموظف للنقل - يتحقق من الإجازات النشطة، السلف، والعهد غير المرجعة",
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
     *         description="Validation completed - تم التحقق بنجاح",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم التحقق من المتطلبات بنجاح"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="can_transfer", type="boolean", example=false),
     *                 @OA\Property(
     *                     property="validations",
     *                     type="object",
     *                     @OA\Property(
     *                         property="active_leaves",
     *                         type="object",
     *                         @OA\Property(property="passed", type="boolean", example=false),
     *                         @OA\Property(property="count", type="integer", example=2),
     *                         @OA\Property(property="items", type="array", @OA\Items(type="object"))
     *                     ),
     *                     @OA\Property(
     *                         property="active_advances",
     *                         type="object",
     *                         @OA\Property(property="passed", type="boolean", example=false),
     *                         @OA\Property(property="count", type="integer", example=1),
     *                         @OA\Property(property="items", type="array", @OA\Items(type="object"))
     *                     ),
     *                     @OA\Property(
     *                         property="unreturned_custody",
     *                         type="object",
     *                         @OA\Property(property="passed", type="boolean", example=true),
     *                         @OA\Property(property="count", type="integer", example=0),
     *                         @OA\Property(property="items", type="array", @OA\Items(type="object"))
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated - غير مصرح"),
     *     @OA\Response(response=404, description="Transfer not found - طلب النقل غير موجود"),
     *     @OA\Response(response=500, description="Server error - خطأ في الخادم")
     * )
     */
    public function getPreTransferValidation(int $id)
    {
        try {
            $user = Auth::user();
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
            $transfer = $this->transferService->getTransferById($id, $effectiveCompanyId, null, $user);

            if (!$transfer) {
                Log::error('TransferController::getPreTransferValidation - Transfer not found', [
                    'transfer_id' => $id,
                    'message' => 'طلب النقل غير موجود',
                    'user_id' => $user->user_id,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'طلب النقل غير موجود',
                ], 404);
            }

            $validation = $this->transferService->validatePreTransferRequirements($transfer->employee_id);

            return response()->json([
                'success' => true,
                'message' => 'تم التحقق من المتطلبات بنجاح',
                'data' => $validation,
            ]);
        } catch (\Exception $e) {
            Log::error('TransferController::getPreTransferValidation - Error', [
                'transfer_id' => $id,
                'error' => $e->getMessage(),
                'message' => 'حدث خطأ أثناء التحقق من المتطلبات',
                'user_id' => $user->user_id,
            ]);
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء التحقق من المتطلبات',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
