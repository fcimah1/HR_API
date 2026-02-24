<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Document\CreateSystemDocumentRequest;
use App\Http\Requests\Document\UpdateSystemDocumentRequest;
use App\Http\Requests\Document\GetSystemDocumentRequest;
use App\DTOs\Document\CreateSystemDocumentDTO;
use App\DTOs\Document\UpdateSystemDocumentDTO;
use App\DTOs\Document\SystemDocumentFilterDTO;
use App\Http\Resources\SystemDocumentResource;
use App\Services\SystemDocumentService;
use App\Services\SimplePermissionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="General Documents",
 *     description="إدارة المستندات العامة والملفات"
 * )
 */
class SystemDocumentController extends Controller
{
    public function __construct(
        private readonly SystemDocumentService $documentService,
        private readonly SimplePermissionService $permissionService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/system-documents",
     *     tags={"General Documents"},
     *     summary="عرض قائمة المستندات العامة",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="department_id", in="query", description="فلترة حسب القسم", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="search", in="query", description="بحث في اسم أو نوع المستند", @OA\Schema(type="string")),
     *     @OA\Parameter(name="per_page", in="query", description="عدد السجلات في الصفحة", @OA\Schema(type="integer", default=15)),
     *     @OA\Parameter(name="page", in="query", description="رقم الصفحة", @OA\Schema(type="integer", default=1)),
     *     @OA\Response(
     *         response=200,
     *         description="تم جلب المستندات بنجاح",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/SystemDocumentResource")),
     *             @OA\Property(property="pagination", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description=" غير مصرح - يجب تسجيل الدخول "),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function index(GetSystemDocumentRequest $request)
    {
        try {
            $user = Auth::user();
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);

            $filters = SystemDocumentFilterDTO::fromRequest($request->validated(), $effectiveCompanyId, $user);
            $result = $this->documentService->getPaginatedDocuments($filters);

            return SystemDocumentResource::collection($result['data'])->additional([
                'success' => true,
                'message' => 'تم جلب المستندات بنجاح',
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
            Log::error('SystemDocumentController::index failed', [
                'user_id' => $user->id,
                'company_id' => $effectiveCompanyId,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب المستندات'
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/system-documents",
     *     tags={"General Documents"},
     *     summary="إضافة مستند جديد",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"department_id", "document_name", "document_type", "document_file"},
     *                 @OA\Property(property="department_id", example="1", type="integer"),
     *                 @OA\Property(property="document_name", example="اسم المستند", type="string"),
     *                 @OA\Property(property="document_type", example="نوع المستند", type="string"),
     *                 @OA\Property(property="document_file", type="string", format="binary")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="تم إضافة المستند بنجاح",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم إضافة المستند بنجاح"),
     *             @OA\Property(property="data", ref="#/components/schemas/SystemDocumentResource")
     *         )
     *     ),
     *     @OA\Response(response=422, description=" فشل التحقق من البيانات "),
     *     @OA\Response(response=401, description=" غير مصرح - يجب تسجيل الدخول "),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function store(CreateSystemDocumentRequest $request)
    {
        try {
            $user = Auth::user();
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);

            $dto = CreateSystemDocumentDTO::fromRequest($request->validated(), $effectiveCompanyId);
            $document = $this->documentService->createDocument($dto);


            Log::info('SystemDocumentController::store success', [
                'user_id' => $user->id,
                'company_id' => $effectiveCompanyId,
                'data' => $document
            ]);
            return response()->json([
                'success' => true,
                'message' => 'تم إضافة المستند بنجاح',
                'data' => new SystemDocumentResource($document)
            ], 201);
        } catch (\Exception $e) {
            Log::error('SystemDocumentController::store failed', [
                'user_id' => $user->id,
                'company_id' => $effectiveCompanyId,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'فشل في إضافة المستند: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/system-documents/{id}",
     *     tags={"General Documents"},
     *     summary="عرض تفاصيل مستند",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="تم جلب تفاصيل المستند بنجاح",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/SystemDocumentResource")
     *         )
     *     ),
     *     @OA\Response(response=404, description="المستند غير موجود"),
     *     @OA\Response(response=401, description=" غير مصرح - يجب تسجيل الدخول "),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function show(int $id)
    {
        try {
            $user = Auth::user();
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);

            $document = $this->documentService->getDocumentById($id, $effectiveCompanyId, $user);

            Log::info('SystemDocumentController::show success', [
                'user_id' => $user->id,
                'company_id' => $effectiveCompanyId,
                'data' => $document
            ]);
            return response()->json([
                'success' => true,
                'data' => new SystemDocumentResource($document)
            ]);
        } catch (\Exception $e) {
            $statusCode = in_array((int)$e->getCode(), [403, 404]) ? (int)$e->getCode() : 500;

            Log::error('SystemDocumentController::show failed', [
                'id' => $id,
                'user_id' => $user->id,
                'company_id' => $effectiveCompanyId,
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $statusCode);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/system-documents/{id}",
     *     tags={"General Documents"},
     *     summary="تحديث بيانات مستند",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"document_name", "document_type"},
     *             @OA\Property(property="document_name", example="اسم المستند", type="string"),
     *             @OA\Property(property="document_type", example="نوع المستند", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="تم تحديث المستند بنجاح",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم تحديث المستند بنجاح"),
     *             @OA\Property(property="data", ref="#/components/schemas/SystemDocumentResource")
     *         )
     *     ),
     *     @OA\Response(response=422, description=" فشل التحقق من البيانات "),
     *     @OA\Response(response=404, description="المستند غير موجود"),
     *     @OA\Response(response=401, description=" غير مصرح - يجب تسجيل الدخول "),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function update(int $id, UpdateSystemDocumentRequest $request)
    {
        try {
            $user = Auth::user();
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);

            $dto = UpdateSystemDocumentDTO::fromRequest($request->validated());
            $document = $this->documentService->updateDocument($id, $dto, $effectiveCompanyId, $user);

            Log::info('SystemDocumentController::update success', [
                'user_id' => $user->id,
                'company_id' => $effectiveCompanyId,
                'data' => $document
            ]);
            return response()->json([
                'success' => true,
                'message' => 'تم تحديث المستند بنجاح',
                'data' => new SystemDocumentResource($document)
            ]);
        } catch (\Exception $e) {
            $statusCode = in_array((int)$e->getCode(), [403, 404]) ? (int)$e->getCode() : 500;

            Log::error('SystemDocumentController::update failed', [
                'id' => $id,
                'user_id' => $user->id,
                'company_id' => $effectiveCompanyId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $statusCode);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/system-documents/{id}",
     *     tags={"General Documents"},
     *     summary="حذف مستند",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="تم حذف المستند بنجاح",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم حذف المستند بنجاح")
     *         )
     *     ),
     *     @OA\Response(response=404, description="المستند غير موجود"),
     *     @OA\Response(response=401, description=" غير مصرح - يجب تسجيل الدخول "),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function destroy(int $id)
    {
        $user = Auth::user();
        $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
        try {

            $this->documentService->deleteDocument($id, $effectiveCompanyId, $user);

            Log::info('SystemDocumentController::destroy success', [
                'user_id' => $user->id,
                'company_id' => $effectiveCompanyId,
            ]);
            return response()->json([
                'success' => true,
                'message' => 'تم حذف المستند بنجاح'
            ]);
        } catch (\Exception $e) {
            $statusCode = in_array((int)$e->getCode(), [403, 404]) ? (int)$e->getCode() : 500;

            Log::error('SystemDocumentController::destroy failed', [
                'id' => $id,
                'user_id' => $user->id,
                'company_id' => $effectiveCompanyId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $statusCode);
        }
    }
}
