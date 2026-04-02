<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Document\CreateOfficialDocumentRequest;
use App\Http\Requests\Document\UpdateOfficialDocumentRequest;
use App\Http\Requests\Document\GetOfficialDocumentRequest;
use App\DTOs\Document\CreateOfficialDocumentDTO;
use App\DTOs\Document\UpdateOfficialDocumentDTO;
use App\DTOs\Document\OfficialDocumentFilterDTO;
use App\Http\Resources\OfficialDocumentResource;
use App\Services\OfficialDocumentService;
use App\Services\SimplePermissionService;
use App\Traits\ApiResponseTrait;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="Official Documents",
 *     description="إدارة المستندات الرسمية والتراخيص"
 * )
 */
class OfficialDocumentController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly OfficialDocumentService $documentService,
        private readonly SimplePermissionService $permissionService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/official-documents",
     *     tags={"Official Documents"},
     *     summary="عرض مصفوفة المستندات الرسمية",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="search", in="query", description="بحث في اسم الموظف، نوع المستند أو رقم المستند", @OA\Schema(type="string")),
     *     @OA\Parameter(name="per_page", in="query", description="عدد السجلات في الصفحة", @OA\Schema(type="integer", default=15)),
     *     @OA\Parameter(name="page", in="query", description="رقم الصفحة", @OA\Schema(type="integer", default=1)),
     *     @OA\Response(
     *         response=200,
     *         description="تم جلب المستندات بنجاح",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/OfficialDocumentResource")),
     *             @OA\Property(property="pagination", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description=" غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function index(GetOfficialDocumentRequest $request)
    {
        try {
            $user = Auth::user();
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);

            $filters = OfficialDocumentFilterDTO::fromRequest($request->validated(), $effectiveCompanyId);
            $result = $this->documentService->getPaginatedDocuments($filters);

            Log::info('OfficialDocumentController::index success', [
                'user_id' => $user->id,
                'company_id' => $effectiveCompanyId
            ]);

            return OfficialDocumentResource::collection($result['data'])->additional([
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
            Log::error('OfficialDocumentController::index failed', [
                'user_id' => $user->id,
                'company_id' => $effectiveCompanyId,
                'error' => $e->getMessage()
            ]);
            return $this->errorResponse('حدث خطأ أثناء جلب المستندات');
        }
    }

    /**
     * @OA\Post(
     *     path="/api/official-documents",
     *     tags={"Official Documents"},
     *     summary="إضافة مستند رسمي جديد",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"license_name", "document_type", "license_no", "expiry_date", "document_file"},
     *                 @OA\Property(property="license_name", example="علي", type="string", description="اسم الموظف"),
     *                 @OA\Property(property="document_type", example="قسيمة", type="string", description="نوع المستند"),
     *                 @OA\Property(property="license_no", example="1", type="string", description="رقم المستند"),
     *                 @OA\Property(property="expiry_date", example="2026-02-28", type="string", description="تاريخ انتهاء الصلاحية"),
     *                 @OA\Property(property="document_file", type="string", format="binary", description="ملف المستند")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="تم إضافة المستند بنجاح",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم إضافة المستند بنجاح"),
     *             @OA\Property(property="data", ref="#/components/schemas/OfficialDocumentResource")
     *         )
     *     ),
     *     @OA\Response(response=422, description=" فشل التحقق من البيانات "),
     *     @OA\Response(response=401, description=" غير مصرح - يجب تسجيل الدخول "),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function store(CreateOfficialDocumentRequest $request)
    {
        try {
            $user = Auth::user();
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);

            $dto = CreateOfficialDocumentDTO::fromRequest($request->validated(), $effectiveCompanyId);
            $document = $this->documentService->createDocument($dto);

            Log::info('OfficialDocumentController::store success', [
                'user_id' => $user->id,
                'company_id' => $effectiveCompanyId,
                'document_id' => $document->document_id
            ]);

            return $this->successResponse(
                new OfficialDocumentResource($document),
                'تم إضافة المستند بنجاح',
                201
            );
        } catch (\Exception $e) {
            Log::error('OfficialDocumentController::store failed', [
                'user_id' => $user->id,
                'company_id' => $effectiveCompanyId,
                'error' => $e->getMessage()
            ]);
            return $this->errorResponse('فشل في إضافة المستند: ' . $e->getMessage());
        }
    }

    /**
     * @OA\Get(
     *     path="/api/official-documents/{id}",
     *     tags={"Official Documents"},
     *     summary="عرض تفاصيل مستند رسمي",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="تم جلب تفاصيل المستند بنجاح",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/OfficialDocumentResource")
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
                Log::warning('OfficialDocumentController::show not found', [
                    'user_id' => $user->id,
                    'company_id' => $effectiveCompanyId,
                    'document_id' => $id
                ]);
                return $this->errorResponse('المستند غير موجود', 404);
            }
            Log::info('OfficialDocumentController::show success', [
                'user_id' => $user->id,
                'company_id' => $effectiveCompanyId,
                'document' => $document
            ]);
            return $this->successResponse(new OfficialDocumentResource($document));
        } catch (\Exception $e) {
            Log::error('OfficialDocumentController::show failed', [
                'user_id' => $user->id,
                'company_id' => $effectiveCompanyId,
                'error' => $e->getMessage()
            ]);
            return $this->errorResponse('حدث خطأ أثناء جلب تفاصيل المستند');
        }
    }

    /**
     * @OA\Post(
     *     path="/api/official-documents/{id}",
     *     tags={"Official Documents"},
     *     summary="تحديث بيانات مستند رسمي (استخدم _method=PUT)",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={ "_method", "license_name", "document_type", "license_no", "expiry_date", "document_file" },
     *                 @OA\Property(property="_method", type="string", example="PUT", description="Laravel method spoofing"),
     *                 @OA\Property(property="license_name", example="علي", type="string", description="اسم الموظف"),
     *                 @OA\Property(property="document_type", example="قسيمة", type="string", description="نوع المستند"),
     *                 @OA\Property(property="license_no", example="1", type="string", description="رقم المستند"),
     *                 @OA\Property(property="expiry_date", example="2026-02-28", type="string", description="تاريخ انتهاء الصلاحية"),
     *                 @OA\Property(property="document_file", type="string", format="binary", description="ملف المستند الجديد (اختياري)")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="تم تحديث المستند بنجاح",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم تحديث المستند بنجاح"),
     *             @OA\Property(property="data", ref="#/components/schemas/OfficialDocumentResource")
     *         )
     *     ),
     *     @OA\Response(response=422, description=" فشل التحقق من البيانات "),
     *     @OA\Response(response=404, description="المستند غير موجود"),
     *     @OA\Response(response=401, description=" غير مصرح - يجب تسجيل الدخول "),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function update(int $id, UpdateOfficialDocumentRequest $request)
    {
        try {
            $user = Auth::user();
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);

            $dto = UpdateOfficialDocumentDTO::fromRequest($request->validated());
            $document = $this->documentService->updateDocument($id, $dto, $effectiveCompanyId);

            if (!$document) {
                Log::warning('OfficialDocumentController::update not found', [
                    'user_id' => $user->id,
                    'company_id' => $effectiveCompanyId,
                    'document_id' => $id
                ]);
                return $this->errorResponse('المستند غير موجود', 404);
            }

            Log::info('OfficialDocumentController::update success', [
                'user_id' => $user->id,
                'company_id' => $effectiveCompanyId,
                'document_id' => $id
            ]);

            return $this->successResponse(
                new OfficialDocumentResource($document),
                'تم تحديث المستند بنجاح'
            );
        } catch (\Exception $e) {
            Log::error('OfficialDocumentController::update failed', [
                'user_id' => $user->id,
                'company_id' => $effectiveCompanyId,
                'error' => $e->getMessage()
            ]);
            return $this->errorResponse('فشل في تحديث المستند');
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/official-documents/{id}",
     *     tags={"Official Documents"},
     *     summary="حذف مستند رسمي",
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
        try {
            $user = Auth::user();
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);

            $deleted = $this->documentService->deleteDocument($id, $effectiveCompanyId);

            if (!$deleted) {
                Log::warning('OfficialDocumentController::destroy not found', [
                    'user_id' => $user->id,
                    'company_id' => $effectiveCompanyId,
                    'document_id' => $id
                ]);
                return $this->errorResponse('المستند غير موجود أو لا تملك صلاحية حذفه', 404);
            }
            Log::info('OfficialDocumentController::destroy success', [
                'user_id' => $user->id,
                'company_id' => $effectiveCompanyId,
                'document_id' => $id
            ]);
            return $this->successResponse(null, 'تم حذف المستند بنجاح');
        } catch (\Exception $e) {
            Log::error('OfficialDocumentController::destroy failed', [
                'user_id' => $user->id,
                'company_id' => $effectiveCompanyId,
                'error' => $e->getMessage()
            ]);
            return $this->errorResponse('حدث خطأ أثناء حذف المستند');
        }
    }
}
