<?php

namespace App\Http\Controllers\Api;

use App\DTOs\Resignation\CreateResignationDTO;
use App\DTOs\Resignation\ResignationFilterDTO;
use App\DTOs\Resignation\ApproveRejectResignationDTO;
use App\DTOs\Resignation\UpdateResignationDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Resignation\CreateResignationRequest;
use App\Http\Requests\Resignation\GetResignationRequest;
use App\Http\Requests\Resignation\ApproveRejectResignationRequest;
use App\Http\Requests\Resignation\UpdateResignationRequest;
use App\Http\Resources\ResignationResource;
use App\Services\ResignationService;
use App\Services\SimplePermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="Resignation Management",
 *     description="إدارة الاستقالات - Resignations management endpoints"
 * )
 */
class ResignationController extends Controller
{
    public function __construct(
        private ResignationService $resignationService,
        private SimplePermissionService $permissionService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/resignations",
     *     summary="Get resignations list",
     *     description="الحصول على قائمة طلبات الاستقالة مع إمكانية الفلترة والبحث",
     *     tags={"Resignation Management"},
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
     *         description="Filter by status - فلترة حسب الحالة (pending/approved/rejected)",
     *         @OA\Schema(type="string", enum={"pending", "approved", "rejected"})
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
     *         description="Resignations retrieved successfully - تم جلب الاستقالات بنجاح",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم جلب طلبات الاستقالة بنجاح"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="resignation_id", type="integer", example=1),
     *                     @OA\Property(property="employee_id", type="integer", example=37),
     *                     @OA\Property(property="resignation_date", type="string", format="date", example="2025-02-01"),
     *                     @OA\Property(property="notice_date", type="string", format="date", example="2025-01-15"),
     *                     @OA\Property(property="reason", type="string", example="فرصة عمل أفضل"),
     *                     @OA\Property(property="status", type="string", example="pending"),
     *                     @OA\Property(property="status_text", type="string", example="قيد المراجعة"),
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
    public function index(GetResignationRequest $request)
    {
        try {
            $user = Auth::user();
            Log::info('ResignationController::index - Request received', [
                'user_id' => $user->user_id,
                'user_type' => $user->user_type,
            ]);
            $filters = ResignationFilterDTO::fromRequest($request->validated());
            $result = $this->resignationService->getPaginatedResignations($filters, $user);
            return response()->json([
                'success' => true,
                'message' => 'تم جلب طلبات الاستقالة بنجاح',
                'data' => ResignationResource::collection($result['data']),
                'pagination' => $result['pagination'],
            ]);
        } catch (\Exception $e) {
            Log::error('ResignationController::index - Error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب طلبات الاستقالة',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/resignations/{id}",
     *     summary="Get a specific resignation",
     *     description="عرض تفاصيل طلب استقالة محدد",
     *     tags={"Resignation Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Resignation ID - معرف طلب الاستقالة",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Resignation retrieved successfully - تم جلب طلب الاستقالة بنجاح",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم جلب طلب الاستقالة بنجاح"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="resignation_id", type="integer", example=1),
     *                 @OA\Property(property="employee_id", type="integer", example=37),
     *                 @OA\Property(property="resignation_date", type="string", format="date"),
     *                 @OA\Property(property="notice_date", type="string", format="date"),
     *                 @OA\Property(property="reason", type="string"),
     *                 @OA\Property(property="status", type="string"),
     *                 @OA\Property(property="status_text", type="string"),
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
     *         description="Resignation not found - طلب الاستقالة غير موجود",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="طلب الاستقالة غير موجود أو ليس لديك صلاحية للوصول إليه")
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
            $resignation = $this->resignationService->getResignationById($id, $effectiveCompanyId, null, $user);
            if (!$resignation) {
                return response()->json([
                    'success' => false,
                    'message' => 'طلب الاستقالة غير موجود أو ليس لديك صلاحية للوصول إليه',
                ], 404);
            }
            return response()->json([
                'success' => true,
                'message' => 'تم جلب طلب الاستقالة بنجاح',
                'data' => new ResignationResource($resignation),
            ]);
        } catch (\Exception $e) {
            Log::error('ResignationController::show - Error', ['resignation_id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب طلب الاستقالة',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/resignations",
     *     summary="Create a new resignation request",
     *     description="إنشاء طلب استقالة جديد - يمكن للموظف تقديم استقالته أو للمدير تقديمها نيابة عن موظف. يدعم رفع ملفات المستندات",
     *     tags={"Resignation Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"notice_date", "resignation_date", "reason", "document_file", "reason"},
     *                 @OA\Property(property="employee_id", type="integer", example=37, description="معرف الموظف (اختياري - الافتراضي المستخدم الحالي)"),
     *                 @OA\Property(property="notice_date", type="string", format="date", example="2025-01-15", description="تاريخ تقديم الإشعار - مطلوب"),
     *                 @OA\Property(property="resignation_date", type="string", format="date", example="2025-02-01", description="تاريخ الاستقالة المطلوب - مطلوب"),
     *                 @OA\Property(property="reason", type="string", example="فرصة عمل أفضل في شركة أخرى", description="سبب الاستقالة - مطلوب"),
     *                 @OA\Property(property="document_file", type="string", format="binary", description="ملف المستند (pdf, doc, docx, jpg, jpeg, png) - حد أقصى 5MB"),
     *                 @OA\Property(property="notify_send_to", type="string", example="employee_id", description="معرف الموظف المستلم للإشعار")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Resignation created successfully - تم إنشاء طلب الاستقالة بنجاح",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم إنشاء طلب الاستقالة بنجاح"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated - غير مصرح"),
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
    public function store(CreateResignationRequest $request)
    {
        try {
            $user = Auth::user();
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
            $employeeId = $request->validated()['employee_id'] ?? $user->user_id;

            Log::info('ResignationController::store - Creating resignation', [
                'user_id' => $user->user_id,
                'employee_id' => $employeeId,
            ]);

            $data = $request->validated();

            // حفظ الملف إذا تم رفعه - رفع إلى المسار المشترك
            if ($request->hasFile('document_file')) {

                $file = $request->file('document_file');
                $newName = $file->hashName(); // اسم عشوائي فريد

                // المسار المشترك من .env (يعمل على local و production)
                $sharedUploadsPath = env('SHARED_UPLOADS_PATH', public_path('uploads'));
                $resignationPath = $sharedUploadsPath . '/pdf_files/resignation';

                // التأكد من وجود المجلد
                if (!is_dir($resignationPath)) {
                    mkdir($resignationPath, 0755, true);
                }

                $file->move($resignationPath, $newName);
                $data['document_file'] = $newName;
            }

            $dto = CreateResignationDTO::fromRequest(
                $data,
                $effectiveCompanyId,
                $employeeId,
                $user->user_id
            );
            $resignation = $this->resignationService->createResignation($dto);
            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء طلب الاستقالة بنجاح',
                'data' => new ResignationResource($resignation),
            ], 201);
        } catch (\Exception $e) {
            Log::error('ResignationController::store - Error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'فشل في إنشاء طلب الاستقالة',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/resignations/{id}",
     *     summary="Update a resignation request",
     *     description="تحديث طلب استقالة - يمكن فقط تعديل الطلبات المعلقة",
     *     tags={"Resignation Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Resignation ID - معرف طلب الاستقالة",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="resignation_date", type="string", format="date", example="2025-02-15", description="تاريخ الاستقالة الجديد"),
     *             @OA\Property(property="reason", type="string", example="سبب معدل للاستقالة", description="السبب الجديد")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Resignation updated successfully - تم تحديث طلب الاستقالة بنجاح",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم تحديث طلب الاستقالة بنجاح"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Cannot update processed resignation - لا يمكن تعديل طلب تمت معالجته"),
     *     @OA\Response(response=401, description="Unauthenticated - غير مصرح"),
     *     @OA\Response(response=403, description="Forbidden - ليس لديك صلاحية"),
     *     @OA\Response(response=404, description="Not found - غير موجود"),
     *     @OA\Response(response=500, description="Server error - خطأ في الخادم")
     * )
     */
    public function update(int $id, UpdateResignationRequest $request)
    {
        try {
            $user = Auth::user();
            Log::info('ResignationController::update - Updating resignation', [
                'resignation_id' => $id,
                'user_id' => $user->user_id,
            ]);
            $dto = UpdateResignationDTO::fromRequest($request->validated());
            $resignation = $this->resignationService->updateResignation($id, $dto, $user);
            return response()->json([
                'success' => true,
                'message' => 'تم تحديث طلب الاستقالة بنجاح',
                'data' => new ResignationResource($resignation),
            ]);
        } catch (\Exception $e) {
            Log::error('ResignationController::update - Error', ['resignation_id' => $id, 'error' => $e->getMessage()]);
            $statusCode = str_contains($e->getMessage(), 'غير موجود') ? 404 : (str_contains($e->getMessage(), 'صلاحية') ? 403 : 500);
            return response()->json(['success' => false, 'message' => $e->getMessage()], $statusCode);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/resignations/{id}",
     *     summary="Delete a resignation request",
     *     description="حذف/إلغاء طلب استقالة - يمكن فقط حذف الطلبات المعلقة",
     *     tags={"Resignation Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Resignation ID - معرف طلب الاستقالة",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Resignation deleted successfully - تم حذف طلب الاستقالة بنجاح",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم حذف طلب الاستقالة بنجاح")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Cannot delete processed resignation - لا يمكن حذف طلب تمت معالجته"),
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
            Log::info('ResignationController::destroy - Deleting resignation', ['resignation_id' => $id, 'user_id' => $user->user_id]);
            $this->resignationService->deleteResignation($id, $user);
            return response()->json(['success' => true, 'message' => 'تم حذف طلب الاستقالة بنجاح']);
        } catch (\Exception $e) {
            Log::error('ResignationController::destroy - Error', ['resignation_id' => $id, 'error' => $e->getMessage()]);
            $statusCode = str_contains($e->getMessage(), 'غير موجود') ? 404 : (str_contains($e->getMessage(), 'صلاحية') ? 403 : 500);
            return response()->json(['success' => false, 'message' => $e->getMessage()], $statusCode);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/resignations/{id}/approve-or-reject",
     *     summary="Approve or reject a resignation request",
     *     description="الموافقة على أو رفض طلب استقالة - للمديرين فقط",
     *     tags={"Resignation Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Resignation ID - معرف طلب الاستقالة",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"action"},
     *             @OA\Property(property="action", type="string", enum={"approve", "reject"}, example="approve", description="الإجراء: approve للموافقة أو reject للرفض"),
     *             @OA\Property(property="remarks", type="string", example="موافق على الاستقالة مع الشكر على خدماتك", description="ملاحظات (اختياري)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Resignation processed successfully - تم معالجة طلب الاستقالة بنجاح",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تمت الموافقة على طلب الاستقالة"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Resignation already processed - تم معالجة الطلب مسبقاً",
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
    public function approveOrReject(int $id, ApproveRejectResignationRequest $request)
    {
        try {
            $user = Auth::user();
            Log::info('ResignationController::approveOrReject - Processing resignation', [
                'resignation_id' => $id,
                'user_id' => $user->user_id,
                'action' => $request->action,
            ]);
            $dto = ApproveRejectResignationDTO::fromRequest($request->validated(), $user->user_id);
            $resignation = $this->resignationService->approveOrRejectResignation($id, $dto);
            $actionMessage = $dto->action === 'approve' ? 'تمت الموافقة على طلب الاستقالة' : 'تم رفض طلب الاستقالة';
            return response()->json([
                'success' => true,
                'message' => $actionMessage,
                'data' => new ResignationResource($resignation),
            ]);
        } catch (\Exception $e) {
            Log::error('ResignationController::approveOrReject - Error', ['resignation_id' => $id, 'error' => $e->getMessage()]);
            $statusCode = str_contains($e->getMessage(), 'غير موجود') ? 404 : (str_contains($e->getMessage(), 'صلاحية') ? 403 : 500);
            return response()->json(['success' => false, 'message' => $e->getMessage()], $statusCode);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/resignations/statuses",
     *     summary="Get resignation statuses",
     *     description="الحصول على قائمة حالات الاستقالات المتاحة",
     *     tags={"Resignation Management"},
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
            $statuses = $this->resignationService->getResignationStatuses();
            return response()->json(['success' => true, 'message' => 'تم جلب الحالات بنجاح', 'data' => $statuses]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'حدث خطأ أثناء جلب الحالات'], 500);
        }
    }
}
