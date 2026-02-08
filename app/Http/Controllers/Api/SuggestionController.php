<?php

namespace App\Http\Controllers\Api;

use App\DTOs\Suggestion\CreateSuggestionDTO;
use App\DTOs\Suggestion\CreateSuggestionCommentDTO;
use App\DTOs\Suggestion\SuggestionFilterDTO;
use App\DTOs\Suggestion\UpdateSuggestionDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Suggestion\AddCommentRequest;
use App\Http\Requests\Suggestion\CreateSuggestionRequest;
use App\Http\Requests\Suggestion\GetSuggestionRequest;
use App\Http\Requests\Suggestion\UpdateSuggestionRequest;
use App\Http\Resources\SuggestionResource;
use App\Services\SuggestionService;
use App\Services\SimplePermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="Suggestion Management",
 *     description="إدارة الاقتراحات - Suggestions management endpoints"
 * )
 */
class SuggestionController extends Controller
{
    public function __construct(
        private SuggestionService $suggestionService,
        private SimplePermissionService $permissionService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/suggestions",
     *     summary="Get suggestions list",
     *     description="الحصول على قائمة الاقتراحات مع إمكانية الفلترة والبحث",
     *     tags={"Suggestion Management"},
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
     *         description="Suggestions retrieved successfully - تم جلب الاقتراحات بنجاح",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم جلب الاقتراحات بنجاح"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="suggestion_id", type="integer", example=1),
     *                     @OA\Property(property="employee_id", type="integer", example=37),
     *                     @OA\Property(property="title", type="string", example="اقتراح لتحسين بيئة العمل"),
     *                     @OA\Property(property="description", type="string", example="تفاصيل الاقتراح"),
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
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated - غير مصرح",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error - خطأ في الخادم",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="حدث خطأ أثناء جلب الاقتراحات")
     *         )
     *     )
     * )
     */
    public function index(GetSuggestionRequest $request)
    {
        try {
            $user = Auth::user();
            Log::info('SuggestionController::index - Request received', [
                'user_id' => $user->user_id,
                'user_type' => $user->user_type,
            ]);
            $filters = SuggestionFilterDTO::fromRequest($request->validated());
            $result = $this->suggestionService->getPaginatedSuggestions($filters, $user);
            return response()->json([
                'success' => true,
                'message' => 'تم جلب الاقتراحات بنجاح',
                'data' => SuggestionResource::collection($result['data']),
                'pagination' => $result['pagination'],
                'user_id' => $user->user_id,
            ]);
        } catch (\Exception $e) {
            Log::error('SuggestionController::index - Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $user->user_id,
            ]);
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب الاقتراحات',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/suggestions/{id}",
     *     summary="Get a specific suggestion",
     *     description="عرض تفاصيل اقتراح محدد",
     *     tags={"Suggestion Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Suggestion ID - معرف الاقتراح",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Suggestion retrieved successfully - تم جلب الاقتراح بنجاح",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم جلب الاقتراح بنجاح"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="suggestion_id", type="integer", example=1),
     *                 @OA\Property(property="employee_id", type="integer", example=37),
     *                 @OA\Property(property="title", type="string", example="اقتراح لتحسين بيئة العمل"),
     *                 @OA\Property(property="description", type="string", example="تفاصيل الاقتراح"),
     *                 @OA\Property(property="status", type="string", example="pending"),
     *                 @OA\Property(property="status_text", type="string", example="قيد المراجعة"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(
     *                     property="employee",
     *                     type="object",
     *                     @OA\Property(property="user_id", type="integer", example=37),
     *                     @OA\Property(property="full_name", type="string", example="محمد أحمد")
     *                 ),
     *                 @OA\Property(
     *                     property="comments",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="comment_id", type="integer"),
     *                         @OA\Property(property="comment", type="string"),
     *                         @OA\Property(property="created_at", type="string", format="date-time")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated - غير مصرح",
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Unauthenticated"))
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Suggestion not found - الاقتراح غير موجود",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="الاقتراح غير موجود أو ليس لديك صلاحية للوصول إليه")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error - خطأ في الخادم",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="حدث خطأ أثناء جلب الاقتراح")
     *         )
     *     )
     * )
     */
    public function show(int $id, Request $request)
    {
        try {
            $user = Auth::user();
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
            $suggestion = $this->suggestionService->getSuggestionById($id, $effectiveCompanyId, null, $user);
            if (!$suggestion) {
                return response()->json([
                    'success' => false,
                    'message' => 'الاقتراح غير موجود أو ليس لديك صلاحية للوصول إليه',
                ], 404);
            }
            return response()->json([
                'success' => true,
                'message' => 'تم جلب الاقتراح بنجاح',
                'data' => new SuggestionResource($suggestion),
                'user_id' => $user->user_id,
            ]);
        } catch (\Exception $e) {
            Log::error('SuggestionController::show - Error', [
                'suggestion_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $user->user_id,
            ]);
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب الاقتراح',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/suggestions",
     *     summary="Create a new suggestion",
     *     description="إنشاء اقتراح جديد",
     *     tags={"Suggestion Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"title", "description"},
     *                 @OA\Property(property="title", type="string", example="اقتراح لتحسين بيئة العمل", description="عنوان الاقتراح - مطلوب"),
     *                 @OA\Property(property="description", type="string", example="أقترح إضافة نباتات خضراء في المكتب لتحسين جودة الهواء والراحة النفسية", description="وصف تفصيلي للاقتراح - مطلوب"),
     *                 @OA\Property(property="attachment", type="string", format="binary", description="ملف مرفق (jpeg, jpg, png, pdf) - الحد الأقصى 5MB")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Suggestion created successfully - تم إنشاء الاقتراح بنجاح",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم إنشاء الاقتراح بنجاح"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="suggestion_id", type="integer", example=1),
     *                 @OA\Property(property="employee_id", type="integer", example=37),
     *                 @OA\Property(property="title", type="string", example="اقتراح لتحسين بيئة العمل"),
     *                 @OA\Property(property="description", type="string", example="تفاصيل الاقتراح"),
     *                 @OA\Property(property="status", type="string", example="pending"),
     *                 @OA\Property(property="created_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated - غير مصرح",
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Unauthenticated"))
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error - خطأ في التحقق",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="فشل التحقق من البيانات"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(property="title", type="array", @OA\Items(type="string", example="عنوان الاقتراح مطلوب")),
     *                 @OA\Property(property="description", type="array", @OA\Items(type="string", example="وصف الاقتراح مطلوب"))
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error - خطأ في الخادم",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="فشل في إنشاء الاقتراح")
     *         )
     *     )
     * )
     */
    public function store(CreateSuggestionRequest $request)
    {
        try {
            $user = Auth::user();
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
            Log::info('SuggestionController::store - Creating suggestion', [
                'user_id' => $user->user_id,
                'request' => $request->all(),
            ]);

            // Handle file upload
            $attachmentName = null;
            if ($request->hasFile('attachment')) {
                $file = $request->file('attachment');
                $attachmentName = $file->hashName();

                // Upload to shared path (CodeIgniter public folder)
                $uploadPath = env('SHARED_UPLOADS_PATH', public_path('uploads')) . '/suggestion_attachments';

                // Create directory if not exists
                if (!file_exists($uploadPath)) {
                    mkdir($uploadPath, 0755, true);
                }

                $file->move($uploadPath, $attachmentName);
                Log::info('SuggestionController::store - File uploaded', ['file' => $attachmentName]);
            }

            $dto = CreateSuggestionDTO::fromRequest(
                array_merge($request->validated(), ['attachment' => $attachmentName]),
                $effectiveCompanyId,
                $user->user_id
            );
            $suggestion = $this->suggestionService->createSuggestion($dto);
            Log::info('SuggestionController::store - Suggestion created successfully', [
                'suggestion_id' => $suggestion->suggestion_id,
                'user_id' => $user->user_id,
                'suggestion' => $suggestion,
            ]);
            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء الاقتراح بنجاح',
                'data' => new SuggestionResource($suggestion),
            ], 201);
        } catch (\Exception $e) {
            Log::error('SuggestionController::store - Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $user->user_id,
            ]);
            return response()->json([
                'success' => false,
                'message' => 'فشل في إنشاء الاقتراح',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/suggestions/{id}",
     *     summary="Update a suggestion",
     *     description="تحديث اقتراح - يمكن فقط للمالك تحديث اقتراحه قبل المعالجة",
     *     tags={"Suggestion Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Suggestion ID - معرف الاقتراح",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="title", type="string", example="عنوان معدل للاقتراح", description="العنوان الجديد"),
     *             @OA\Property(property="description", type="string", example="وصف معدل للاقتراح", description="الوصف الجديد")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Suggestion updated successfully - تم تحديث الاقتراح بنجاح",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم تحديث الاقتراح بنجاح"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated - غير مصرح"),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - ليس لديك صلاحية",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="ليس لديك صلاحية لتعديل هذا الاقتراح")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Not found - غير موجود",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="الاقتراح غير موجود")
     *         )
     *     ),
     *     @OA\Response(response=500, description="Server error - خطأ في الخادم")
     * )
     */
    public function update(int $id, UpdateSuggestionRequest $request)
    {
        try {
            $user = Auth::user();
            Log::info('SuggestionController::update - Updating suggestion', [
                'suggestion_id' => $id,
                'user_id' => $user->user_id,
                'request' => $request->all(),
            ]);
            $dto = UpdateSuggestionDTO::fromRequest($request->validated());
            $suggestion = $this->suggestionService->updateSuggestion($id, $dto, $user);
            Log::info('SuggestionController::update - Suggestion updated successfully', [
                'suggestion_id' => $suggestion->suggestion_id,
                'user_id' => $user->user_id,
                'suggestion' => $suggestion,
            ]);
            return response()->json([
                'success' => true,
                'message' => 'تم تحديث الاقتراح بنجاح',
                'data' => new SuggestionResource($suggestion),
            ]);
        } catch (\Exception $e) {
            Log::error('SuggestionController::update - Error', [
                'suggestion_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $user->user_id,
            ]);
            $statusCode = str_contains($e->getMessage(), 'غير موجود') ? 404 : (str_contains($e->getMessage(), 'صلاحية') ? 403 : 500);
            return response()->json(['success' => false, 'message' => $e->getMessage()], $statusCode);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/suggestions/{id}",
     *     summary="Delete a suggestion",
     *     description="حذف اقتراح - يمكن فقط للمالك حذف اقتراحه قبل المعالجة",
     *     tags={"Suggestion Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Suggestion ID - معرف الاقتراح",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Suggestion deleted successfully - تم حذف الاقتراح بنجاح",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم حذف الاقتراح بنجاح")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated - غير مصرح"),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - ليس لديك صلاحية",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="ليس لديك صلاحية لحذف هذا الاقتراح")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Not found - غير موجود",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="الاقتراح غير موجود")
     *         )
     *     ),
     *     @OA\Response(response=500, description="Server error - خطأ في الخادم")
     * )
     */
    public function destroy(int $id)
    {
        try {
            $user = Auth::user();
            Log::info('SuggestionController::destroy - Deleting suggestion', [
                'suggestion_id' => $id,
                'user_id' => $user->user_id,
            ]);
            $this->suggestionService->deleteSuggestion($id, $user);
            Log::info('SuggestionController::destroy - Suggestion deleted successfully', [
                'suggestion_id' => $id,
                'user_id' => $user->user_id,
            ]);
            return response()->json(['success' => true, 'message' => 'تم حذف الاقتراح بنجاح']);
        } catch (\Exception $e) {
            Log::error('SuggestionController::destroy - Error', [
                'suggestion_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::user()->user_id,
            ]);
            $statusCode = str_contains($e->getMessage(), 'غير موجود') ? 404 : (str_contains($e->getMessage(), 'صلاحية') ? 403 : 500);
            return response()->json(['success' => false, 'message' => $e->getMessage()], $statusCode);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/suggestions/{id}/comments",
     *     summary="Add a comment to suggestion",
     *     description="إضافة تعليق على اقتراح",
     *     tags={"Suggestion Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Suggestion ID - معرف الاقتراح",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"comment"},
     *             @OA\Property(property="comment", type="string", example="تعليق ممتاز على الاقتراح", description="نص التعليق - مطلوب")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Comment added successfully - تم إضافة التعليق بنجاح",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم إضافة التعليق بنجاح"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="comment_id", type="integer", example=1),
     *                 @OA\Property(property="suggestion_id", type="integer", example=5),
     *                 @OA\Property(property="employee_id", type="integer", example=37),
     *                 @OA\Property(property="comment", type="string", example="تعليق ممتاز"),
     *                 @OA\Property(property="created_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated - غير مصرح"),
     *     @OA\Response(response=404, description="Suggestion not found - الاقتراح غير موجود"),
     *     @OA\Response(response=422, description="Validation error - خطأ في التحقق"),
     *     @OA\Response(response=500, description="Server error - خطأ في الخادم")
     * )
     */
    public function addComment(int $id, AddCommentRequest $request)
    {
        try {
            $user = Auth::user();
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
            Log::info('SuggestionController::addComment - Adding comment', [
                'suggestion_id' => $id,
                'user_id' => $user->user_id,
                'request' => $request->all(),
            ]);
            $dto = CreateSuggestionCommentDTO::fromRequest(
                $request->validated(),
                $effectiveCompanyId,
                $id,
                $user->user_id
            );
            $comment = $this->suggestionService->addComment($id, $dto, $user);
            Log::info('SuggestionController::addComment - Comment added successfully', [
                'comment_id' => $comment->comment_id,
                'suggestion_id' => $comment->suggestion_id,
                'user_id' => $user->user_id,
                'comment' => $comment,
            ]);
            return response()->json([
                'success' => true,
                'message' => 'تم إضافة التعليق بنجاح',
                'data' => [
                    'comment_id' => $comment->comment_id,
                    'suggestion_id' => $comment->suggestion_id,
                    'employee_id' => $comment->employee_id,
                    'comment' => $comment->suggestion_comment,
                    'created_at' => $comment->created_at,
                ],
            ], 201);
        } catch (\Exception $e) {
            Log::error('SuggestionController::addComment - Error', [
                'suggestion_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::user()->user_id,
            ]);
            $statusCode = str_contains($e->getMessage(), 'غير موجود') ? 404 : 500;
            return response()->json(['success' => false, 'message' => $e->getMessage()], $statusCode);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/suggestions/{id}/comments",
     *     summary="Get suggestion comments",
     *     description="الحصول على تعليقات اقتراح محدد",
     *     tags={"Suggestion Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Suggestion ID - معرف الاقتراح",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Comments retrieved successfully - تم جلب التعليقات بنجاح",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم جلب التعليقات بنجاح"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="comment_id", type="integer", example=1),
     *                     @OA\Property(property="suggestion_id", type="integer", example=5),
     *                     @OA\Property(property="employee_id", type="integer", example=37),
     *                     @OA\Property(property="comment", type="string", example="تعليق ممتاز"),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(
     *                         property="employee",
     *                         type="object",
     *                         @OA\Property(property="user_id", type="integer", example=37),
     *                         @OA\Property(property="full_name", type="string", example="محمد أحمد")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated - غير مصرح"),
     *     @OA\Response(response=404, description="Suggestion not found - الاقتراح غير موجود"),
     *     @OA\Response(response=500, description="Server error - خطأ في الخادم")
     * )
     */
    public function getComments(int $id)
    {
        try {
            $user = Auth::user();
            Log::info('SuggestionController::getComments - Getting comments', [
                'suggestion_id' => $id,
                'user_id' => $user->user_id,
            ]);
            $comments = $this->suggestionService->getComments($id, $user);
            Log::info('SuggestionController::getComments - Comments fetched successfully', [
                'suggestion_id' => $id,
                'user_id' => $user->user_id,
            ]);
            return response()->json([
                'success' => true,
                'message' => 'تم جلب التعليقات بنجاح',
                'data' => $comments,
            ]);
        } catch (\Exception $e) {
            Log::error('SuggestionController::getComments - Error', [
                'suggestion_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::user()->user_id,
            ]);
            $statusCode = str_contains($e->getMessage(), 'غير موجود') ? 404 : 500;
            return response()->json(['success' => false, 'message' => $e->getMessage()], $statusCode);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/suggestions/{suggestionId}/comments/{commentId}",
     *     summary="Delete a comment from suggestion",
     *     description="حذف تعليق من اقتراح - يمكن للمالك فقط حذف تعليقه",
     *     tags={"Suggestion Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="suggestionId",
     *         in="path",
     *         required=true,
     *         description="Suggestion ID - معرف الاقتراح",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="commentId",
     *         in="path",
     *         required=true,
     *         description="Comment ID - معرف التعليق",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Comment deleted successfully - تم حذف التعليق بنجاح",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم حذف التعليق بنجاح")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated - غير مصرح"),
     *     @OA\Response(response=403, description="Forbidden - غير مسموح بحذف هذا التعليق"),
     *     @OA\Response(response=404, description="Comment not found - التعليق غير موجود"),
     *     @OA\Response(response=500, description="Server error - خطأ في الخادم")
     * )
     */
    public function deleteComment(int $suggestionId, int $commentId)
    {
        try {
            $user = Auth::user();

            $this->suggestionService->deleteComment($suggestionId, $commentId, $user);
            Log::info('SuggestionController::deleteComment - Comment deleted successfully', [
                'suggestion_id' => $suggestionId,
                'comment_id' => $commentId,
                'user_id' => $user->user_id,
            ]);
            return response()->json([
                'success' => true,
                'message' => 'تم حذف التعليق بنجاح',
            ]);
        } catch (\Exception $e) {
            Log::error('SuggestionController::deleteComment - Error', [
                'suggestion_id' => $suggestionId,
                'comment_id' => $commentId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::user()->user_id,
            ]);

            $statusCode = match (true) {
                str_contains($e->getMessage(), 'غير موجود') => 404,
                str_contains($e->getMessage(), 'غير مسموح') => 403,
                default => 500,
            };

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }
}
