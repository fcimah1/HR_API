<?php

namespace App\Http\Controllers\Api;

use App\DTOs\Complaint\CreateComplaintDTO;
use App\DTOs\Complaint\ComplaintFilterDTO;
use App\DTOs\Complaint\ResolveComplaintDTO;
use App\DTOs\Complaint\UpdateComplaintDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Complaint\CreateComplaintRequest;
use App\Http\Requests\Complaint\DeleteComplaintRequest;
use App\Http\Requests\Complaint\GetComplaintRequest;
use App\Http\Requests\Complaint\ResolveComplaintRequest;
use App\Http\Requests\Complaint\UpdateComplaintRequest;
use App\Http\Resources\ComplaintResource;
use App\Services\ComplaintService;
use App\Services\SimplePermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="Complaint Management",
 *     description="إدارة الشكاوى - Complaints management endpoints"
 * )
 */
class ComplaintController extends Controller
{
    public function __construct(
        private ComplaintService $complaintService,
        private SimplePermissionService $permissionService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/complaints",
     *     summary="Get complaints list",
     *     description="الحصول على قائمة الشكاوى مع إمكانية الفلترة والبحث",
     *     tags={"Complaint Management"},
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
     *         description="Filter by status - فلترة حسب الحالة (pending/resolved/rejected)",
     *         @OA\Schema(type="string", enum={"pending", "resolved", "rejected"})
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
     *         description="Search in title and description - البحث في العنوان والوصف",
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
     *         description="Complaints retrieved successfully - تم جلب الشكاوى بنجاح",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم جلب الشكاوى بنجاح"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="complaint_id", type="integer", example=1),
     *                     @OA\Property(property="employee_id", type="integer", example=37),
     *                     @OA\Property(property="title", type="string", example="شكوى بخصوص بيئة العمل"),
     *                     @OA\Property(property="description", type="string", example="تفاصيل الشكوى"),
     *                     @OA\Property(property="complaint_against", type="integer", nullable=true, example=45),
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
    public function index(GetComplaintRequest $request)
    {
        try {
            $user = Auth::user();
            Log::info('ComplaintController::index - Request received', [
                'user_id' => $user->user_id,
                'user_type' => $user->user_type,
            ]);
            $filters = ComplaintFilterDTO::fromRequest($request->validated());
            $result = $this->complaintService->getPaginatedComplaints($filters, $user);
            return response()->json([
                'success' => true,
                'message' => 'تم جلب الشكاوى بنجاح',
                'data' => ComplaintResource::collection($result['data']),
                'pagination' => $result['pagination'],
            ]);
        } catch (\Exception $e) {
            Log::error('ComplaintController::index - Error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب الشكاوى',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/complaints/{id}",
     *     summary="Get a specific complaint",
     *     description="عرض تفاصيل شكوى محددة",
     *     tags={"Complaint Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Complaint ID - معرف الشكوى",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Complaint retrieved successfully - تم جلب الشكوى بنجاح",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم جلب الشكوى بنجاح"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="complaint_id", type="integer", example=1),
     *                 @OA\Property(property="employee_id", type="integer", example=37),
     *                 @OA\Property(property="title", type="string", example="شكوى بخصوص بيئة العمل"),
     *                 @OA\Property(property="description", type="string", example="تفاصيل الشكوى"),
     *                 @OA\Property(property="complaint_against", type="integer", nullable=true),
     *                 @OA\Property(property="status", type="string", example="pending"),
     *                 @OA\Property(property="status_text", type="string", example="قيد المراجعة"),
     *                 @OA\Property(property="resolution", type="string", nullable=true),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
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
     *         description="Complaint not found - الشكوى غير موجودة",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="الشكوى غير موجودة أو ليس لديك صلاحية للوصول إليها")
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
            $complaint = $this->complaintService->getComplaintById($id, $effectiveCompanyId, null, $user);
            if (!$complaint) {
                return response()->json([
                    'success' => false,
                    'message' => 'الشكوى غير موجودة أو ليس لديك صلاحية للوصول إليها',
                ], 404);
            }
            return response()->json([
                'success' => true,
                'message' => 'تم جلب الشكوى بنجاح',
                'data' => new ComplaintResource($complaint),
            ]);
        } catch (\Exception $e) {
            Log::error('ComplaintController::show - Error', ['complaint_id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب الشكوى',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/complaints",
     *     summary="Create a new complaint",
     *     description="إنشاء شكوى جديدة - يمكن تقديم شكوى ضد موظف معين أو شكوى عامة",
     *     tags={"Complaint Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"title", "description","complaint_against","complaint_date"},
     *             @OA\Property(property="title", type="string", example="شكوى بخصوص بيئة العمل", description="عنوان الشكوى - مطلوب"),
     *             @OA\Property(property="description", type="string", example="التكييف لا يعمل بشكل صحيح في المكتب مما يؤثر على الإنتاجية", description="وصف تفصيلي للشكوى - مطلوب"),
     *             @OA\Property(property="complaint_against", type="array", @OA\Items(type="integer"), example={45,703}, description="معرفات الموظفين المشتكى ضدهم (اختياري)"),
     *             @OA\Property(property="complaint_date", type="string", format="date", example="2023-01-01", description="تاريخ الشكوى - مطلوب")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Complaint created successfully - تم إنشاء الشكوى بنجاح",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم إنشاء الشكوى بنجاح"),
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
    public function store(CreateComplaintRequest $request)
    {
        try {
            $user = Auth::user();
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
            Log::info('ComplaintController::store - Creating complaint', ['user_id' => $user->user_id]);
            $dto = CreateComplaintDTO::fromRequest(
                $request->validated(),
                $effectiveCompanyId,
                $user->user_id
            );
            $complaint = $this->complaintService->createComplaint($dto);
            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء الشكوى بنجاح',
                'data' => new ComplaintResource($complaint),
            ], 201);
        } catch (\Exception $e) {
            Log::error('ComplaintController::store - Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'message' => 'فشل في إنشاء الشكوى',
            ]);
            return response()->json([
                'success' => false,
                'message' => 'فشل في إنشاء الشكوى',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/complaints/{id}",
     *     summary="Update a complaint",
     *     description="تحديث شكوى - يمكن فقط للمالك تعديل شكواه قبل المعالجة",
     *     tags={"Complaint Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Complaint ID - معرف الشكوى",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="title", type="string", example="عنوان معدل للشكوى", description="العنوان الجديد"),
     *             @OA\Property(property="complaint_date", type="string", format="date", example="2023-01-01", description="تاريخ الشكوى - مطلوب"),
     *             @OA\Property(property="description", type="string", example="وصف معدل للشكوى", description="الوصف الجديد")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Complaint updated successfully - تم تحديث الشكوى بنجاح",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم تحديث الشكوى بنجاح"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Cannot update processed complaint - لا يمكن تعديل شكوى تمت معالجتها"),
     *     @OA\Response(response=401, description="Unauthenticated - غير مصرح"),
     *     @OA\Response(response=403, description="Forbidden - ليس لديك صلاحية"),
     *     @OA\Response(response=404, description="Not found - غير موجودة"),
     *     @OA\Response(response=500, description="Server error - خطأ في الخادم")
     * )
     */
    public function update(int $id, UpdateComplaintRequest $request)
    {
        try {
            $user = Auth::user();
            Log::info('ComplaintController::update - Updating complaint', ['complaint_id' => $id, 'user_id' => $user->user_id]);
            $dto = UpdateComplaintDTO::fromRequest($request->validated());
            $complaint = $this->complaintService->updateComplaint($id, $dto, $user);
            return response()->json([
                'success' => true,
                'message' => 'تم تحديث الشكوى بنجاح',
                'data' => new ComplaintResource($complaint),
            ]);
        } catch (\Exception $e) {
            Log::error('ComplaintController::update - Error', ['complaint_id' => $id, 'error' => $e->getMessage()]);
            $statusCode = str_contains($e->getMessage(), 'غير موجود') ? 404 : (str_contains($e->getMessage(), 'صلاحية') ? 403 : 500);
            return response()->json(['success' => false, 'message' => $e->getMessage()], $statusCode);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/complaints/{id}",
     *     summary="Delete a complaint",
     *     description="حذف شكوى - يمكن فقط للمالك حذف شكواه قبل المعالجة",
     *     tags={"Complaint Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Complaint ID - معرف الشكوى",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Complaint deleted successfully - تم حذف الشكوى بنجاح",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم حذف الشكوى بنجاح")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Cannot delete processed complaint - لا يمكن حذف شكوى تمت معالجتها"),
     *     @OA\Response(response=401, description="Unauthenticated - غير مصرح"),
     *     @OA\Response(response=403, description="Forbidden - ليس لديك صلاحية"),
     *     @OA\Response(response=404, description="Not found - غير موجودة"),
     *     @OA\Response(response=500, description="Server error - خطأ في الخادم")
     * )
     */
    public function destroy(int $id, DeleteComplaintRequest $request)
    {
        try {
            $user = Auth::user();
            Log::info('ComplaintController::destroy - Deleting complaint', ['complaint_id' => $id, 'user_id' => $user->user_id]);
            $this->complaintService->deleteComplaint($id, $user, $user->user_id, $request->validated()['description'] ?? null);
            return response()->json(['success' => true, 'message' => 'تم حذف الشكوى بنجاح']);
        } catch (\Exception $e) {
            Log::error('ComplaintController::destroy - Error', [
                'complaint_id' => $id,
                'error' => $e->getMessage(),
            ]);
            $statusCode = str_contains($e->getMessage(), 'غير موجود') ? 404 : (str_contains($e->getMessage(), 'صلاحية') ? 403 : 500);
            return response()->json(['success' => false, 'message' => $e->getMessage()], $statusCode);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/complaints/{id}/resolve",
     *     summary="Resolve or reject a complaint",
     *     description="حل أو رفض شكوى - للمديرين والموارد البشرية فقط",
     *     tags={"Complaint Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Complaint ID - معرف الشكوى",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"action"},
     *             @OA\Property(property="action", type="string", enum={"resolve", "reject"}, example="resolve", description="الإجراء: resolve للحل أو reject للرفض"),
     *             @OA\Property(property="description", type="string", example="تم إصلاح التكييف", description="تفاصيل الحل (مطلوب عند الحل)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Complaint processed successfully - تم معالجة الشكوى بنجاح",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم حل الشكوى بنجاح"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Complaint already processed - تم معالجة الشكوى مسبقاً",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="تم معالجة هذه الشكوى مسبقاً")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated - غير مصرح"),
     *     @OA\Response(response=403, description="Forbidden - ليس لديك صلاحية"),
     *     @OA\Response(response=404, description="Not found - غير موجودة"),
     *     @OA\Response(response=500, description="Server error - خطأ في الخادم")
     * )
     */
    public function resolve(int $id, ResolveComplaintRequest $request)
    {
        try {
            $user = Auth::user();
            Log::info('ComplaintController::resolve - Processing complaint', [
                'complaint_id' => $id,
                'user_id' => $user->user_id,
                'action' => $request->action,
            ]);
            $dto = ResolveComplaintDTO::fromRequest($request->validated(), $user->user_id);
            $complaint = $this->complaintService->resolveOrRejectComplaint($id, $dto);
            $actionMessage = $dto->action === 'resolve' ? 'تم حل الشكوى بنجاح' : 'تم رفض الشكوى';
            return response()->json([
                'success' => true,
                'message' => $actionMessage,
                'data' => new ComplaintResource($complaint),
            ]);
        } catch (\Exception $e) {
            Log::error('ComplaintController::resolve - Error', ['complaint_id' => $id, 'error' => $e->getMessage()]);
            $statusCode = str_contains($e->getMessage(), 'غير موجود') ? 404 : (str_contains($e->getMessage(), 'صلاحية') ? 403 : 500);
            return response()->json(['success' => false, 'message' => $e->getMessage()], $statusCode);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/complaints/statuses",
     *     summary="Get complaint statuses",
     *     description="الحصول على قائمة حالات الشكاوى المتاحة",
     *     tags={"Complaint Management"},
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
            $statuses = $this->complaintService->getComplaintStatuses();
            return response()->json(['success' => true, 'message' => 'تم جلب الحالات بنجاح', 'data' => $statuses]);
        } catch (\Exception $e) {
            Log::error('ComplaintController::getStatuses - Error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'حدث خطأ أثناء جلب الحالات'], 500);
        }
    }
}
