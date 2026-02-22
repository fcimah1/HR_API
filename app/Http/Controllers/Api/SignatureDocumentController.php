<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Document\CreateSignatureDocumentRequest;
use App\Http\Requests\Document\UpdateSignatureDocumentRequest;
use App\Http\Requests\Document\GetSignatureDocumentRequest;
use App\DTOs\Document\CreateSignatureDocumentDTO;
use App\DTOs\Document\UpdateSignatureDocumentDTO;
use App\DTOs\Document\SignatureDocumentFilterDTO;
use App\Http\Resources\SignatureDocumentResource;
use App\Services\SignatureDocumentService;
use App\Services\SimplePermissionService;
use App\Traits\ApiResponseTrait;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="Signature Documents",
 *     description="إدارة ملفات التوقيع الإلكتروني"
 * )
 */
class SignatureDocumentController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly SignatureDocumentService $documentService,
        private readonly SimplePermissionService $permissionService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/signature-documents",
     *     tags={"Signature Documents"},
     *     summary="عرض مصفوفة ملفات التوقيع الإلكتروني",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="search", in="query", description="بحث في اسم المستند", @OA\Schema(type="string")),
     *     @OA\Parameter(name="per_page", in="query", description="عدد السجلات في الصفحة", @OA\Schema(type="integer", default=15)),
     *     @OA\Parameter(name="page", in="query", description="رقم الصفحة", @OA\Schema(type="integer", default=1)),
     *     @OA\Response(
     *         response=200,
     *         description="تم جلب ملفات التوقيع بنجاح",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/SignatureDocumentResource")),
     *             @OA\Property(property="pagination", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description=" غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function index(GetSignatureDocumentRequest $request)
    {
        try {
            $user = Auth::user();
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);

            $filters = SignatureDocumentFilterDTO::fromRequest($request->validated(), $effectiveCompanyId);
            $result = $this->documentService->getPaginatedDocuments($filters);

            Log::info('SignatureDocumentController::index success', [
                'user_id' => $user->id,
                'company_id' => $effectiveCompanyId
            ]);

            return SignatureDocumentResource::collection($result['data'])->additional([
                'success' => true,
                'message' => 'تم جلب ملفات التوقيع بنجاح',
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
            Log::error('SignatureDocumentController::index failed', [
                'user_id' => $user->id,
                'company_id' => $effectiveCompanyId,
                'error' => $e->getMessage()
            ]);
            return $this->errorResponse('حدث خطأ أثناء جلب ملفات التوقيع');
        }
    }

    /**
     * @OA\Post(
     *     path="/api/signature-documents",
     *     tags={"Signature Documents"},
     *     summary="إضافة ملف توقيع إلكتروني جديد",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"document_name", "share_with_employees", "signature_task", "document_file"},
     *                 @OA\Property(property="document_name", example="عقد عمل", type="string", description="اسم المستند"),
     *                 @OA\Property(property="share_with_employees", example="0", type="integer", description="مشاركة مع الموظفين (استخدم '0' للكل أو أرسل المصفوفة staff_ids)"),
     *                 @OA\Property(property="staff_ids[]", type="array", @OA\Items(type="integer"), description="مصفوفة أرقام الموظفين المختارة"),
     *                 @OA\Property(property="signature_task", example="ملف onboarding", type="string", enum={"ملف onboarding", "ملف offboarding"}, description="مهمة توقيع (ملف onboarding، ملف offboarding)"),
     *                 @OA\Property(property="document_file", type="string", format="binary", description="ملف المستند")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="تم إضافة ملف التوقيع بنجاح",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم إضافة ملف التوقيع بنجاح"),
     *             @OA\Property(property="data", ref="#/components/schemas/SignatureDocumentResource")
     *         )
     *     ),
     *     @OA\Response(response=422, description=" فشل التحقق من البيانات "),
     *     @OA\Response(response=401, description=" غير مصرح - يجب تسجيل الدخول "),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function store(CreateSignatureDocumentRequest $request)
    {
        try {
            $user = Auth::user();
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);

            $dto = CreateSignatureDocumentDTO::fromRequest($request->validated(), $effectiveCompanyId);
            $document = $this->documentService->createDocument($dto);

            Log::info('SignatureDocumentController::store success', [
                'user_id' => $user->id,
                'company_id' => $effectiveCompanyId,
                'document_id' => $document->document_id
            ]);

            return $this->successResponse(
                new SignatureDocumentResource($document),
                'تم إضافة ملف التوقيع بنجاح',
                201
            );
        } catch (\Exception $e) {
            Log::error('SignatureDocumentController::store failed', [
                'user_id' => $user->id,
                'company_id' => $effectiveCompanyId,
                'error' => $e->getMessage()
            ]);
            return $this->errorResponse('فشل في إضافة ملف التوقيع: ' . $e->getMessage());
        }
    }

    /**
     * @OA\Get(
     *     path="/api/signature-documents/{id}",
     *     tags={"Signature Documents"},
     *     summary="عرض تفاصيل ملف توقيع",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="تم جلب التفاصيل بنجاح",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/SignatureDocumentResource")
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

            $document = $this->documentService->getDocumentById($id, $effectiveCompanyId);

            if (!$document) {
                Log::info('SignatureDocumentController::show not found', [
                    'user_id' => $user->id,
                    'company_id' => $effectiveCompanyId,
                    'document_id' => $id
                ]);
                return $this->errorResponse('المستند غير موجود', 404);
            }

            Log::info('SignatureDocumentController::show success', [
                'user_id' => $user->id,
                'company_id' => $effectiveCompanyId,
                'document_id' => $id
            ]);
            return $this->successResponse(new SignatureDocumentResource($document));
        } catch (\Exception $e) {
            Log::error('SignatureDocumentController::show failed', [
                'user_id' => $user->id,
                'company_id' => $effectiveCompanyId,
                'document_id' => $id,
                'error' => $e->getMessage()
            ]);
            return $this->errorResponse('حدث خطأ أثناء جلب تفاصيل ملف التوقيع');
        }
    }

    /**
     * @OA\Post(
     *     path="/api/signature-documents/{id}",
     *     tags={"Signature Documents"},
     *     summary="تحديث بيانات ملف توقيع (استخدم _method=PUT)",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="_method", type="string", example="PUT", description="Laravel method spoofing"),
     *                 @OA\Property(property="document_name", example="عقد عمل جديد", type="string", description="اسم المستند"),
     *                 @OA\Property(property="share_with_employees", example=1, type="integer", description="مشاركة مع الموظفين (0=لا، 1=نعم)"),
     *                 @OA\Property(property="signature_task", example=0, type="integer", description="مهمة توقيع (0=لا، 1=نعم)"),
     *                 @OA\Property(property="document_file", type="string", format="binary", description="ملف المستند الجديد (اختياري)")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="تم تحديث ملف التوقيع بنجاح",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم تحديث ملف التوقيع بنجاح"),
     *             @OA\Property(property="data", ref="#/components/schemas/SignatureDocumentResource")
     *         )
     *     ),
     *     @OA\Response(response=422, description=" فشل التحقق من البيانات "),
     *     @OA\Response(response=404, description="المستند غير موجود"),
     *     @OA\Response(response=401, description=" غير مصرح - يجب تسجيل الدخول "),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function update(int $id, UpdateSignatureDocumentRequest $request)
    {
        try {
            $user = Auth::user();
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);

            $dto = UpdateSignatureDocumentDTO::fromRequest($request->validated());
            $document = $this->documentService->updateDocument($id, $dto, $effectiveCompanyId);

            if (!$document) {
                Log::info('SignatureDocumentController::update not found', [
                    'user_id' => $user->id,
                    'company_id' => $effectiveCompanyId,
                    'document_id' => $id
                ]);
                return $this->errorResponse('المستند غير موجود', 404);
            }

            Log::info('SignatureDocumentController::update success', [
                'user_id' => $user->id,
                'company_id' => $effectiveCompanyId,
                'document_id' => $id
            ]);

            return $this->successResponse(
                new SignatureDocumentResource($document),
                'تم تحديث ملف التوقيع بنجاح'
            );
        } catch (\Exception $e) {
            Log::error('SignatureDocumentController::update failed', [
                'user_id' => $user->id,
                'company_id' => $effectiveCompanyId,
                'document_id' => $id,
                'error' => $e->getMessage()
            ]);
            return $this->errorResponse('فشل في تحديث ملف التوقيع: ' . $e->getMessage());
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/signature-documents/{id}",
     *     tags={"Signature Documents"},
     *     summary="حذف ملف توقيع",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="تم حذف ملف التوقيع بنجاح",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم حذف ملف التوقيع بنجاح")
     *         )
     *     ),
     *     @OA\Response(response=404, description="المستند غير موجود"),
     *     @OA\Response(response=401, description=" غير مصرح - يجب تسجيل الدخول "),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function destroy(int $id)
    {
        try {
            $user = Auth::user();
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);

            $deleted = $this->documentService->deleteDocument($id, $effectiveCompanyId);

            if (!$deleted) {
                Log::info('SignatureDocumentController::destroy not found', [
                    'user_id' => $user->id,
                    'company_id' => $effectiveCompanyId,
                    'document_id' => $id
                ]);
                return $this->errorResponse('المستند غير موجود أو لا تملك صلاحية حذفه', 404);
            }

            Log::info('SignatureDocumentController::destroy success', [
                'user_id' => $user->id,
                'company_id' => $effectiveCompanyId,
                'document_id' => $id
            ]);
            return $this->successResponse(null, 'تم حذف ملف التوقيع بنجاح');
        } catch (\Exception $e) {
            Log::error('SignatureDocumentController::destroy failed', [
                'user_id' => $user->id,
                'company_id' => $effectiveCompanyId,
                'document_id' => $id,
                'error' => $e->getMessage()
            ]);
            return $this->errorResponse('حدث خطأ أثناء حذف ملف التوقيع');
        }
    }
}
