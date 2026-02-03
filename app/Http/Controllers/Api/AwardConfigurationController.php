<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Award\StoreAwardConfigurationRequest;
use App\Services\SimplePermissionService;
use App\Traits\ApiResponseTrait;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use OpenApi\Annotations as OA;


/**
 * @OA\Tag(
 *     name="Award Configuration",
 *     description="إدارة إعدادات المكافآت"
 * )
 */
class AwardConfigurationController extends Controller
{
    use ApiResponseTrait;

    protected $awardConfigurationService;
    protected $permissionService;

    public function __construct(
        \App\Services\AwardConfigurationService $awardConfigurationService,
        SimplePermissionService $permissionService
    ) {
        $this->awardConfigurationService = $awardConfigurationService;
        $this->permissionService = $permissionService;
    }

    /**
     * @OA\Get(
     *     path="/api/awards/types",
     *     summary="عرض أنواع المكافئات",
     *     description="يعرض قائمة بأنواع المكافئات المتاحة للشركة",
     *     tags={"Award Configuration"},
     *     security={{ "bearerAuth": {} }},
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string"), description="بحث باسم النوع"),
     *     @OA\Response(
     *         response=200,
     *         description="تم جلب البيانات بنجاح",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم جلب الأنواع بنجاح"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="constants_id", type="integer"),
     *                     @OA\Property(property="category_name", type="string"),
     *                     @OA\Property(property="created_at", type="string", format="date-time")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function indexTypes(): JsonResponse
    {
        try {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId(Auth::user());

            $search = request('search');

            $types = $this->awardConfigurationService->getTypes($effectiveCompanyId, $search);

            if(!$types){
                Log::error('AwardConfigurationController@indexTypes: No award types found', [
                    'message' => 'لا يوجد أنواع',
                    'company_id' => $effectiveCompanyId ?? null,
                    'search' => $search ?? null,
                ]);
                return $this->errorResponse('لا يوجد أنواع', 404);
            }

            Log::info('AwardConfigurationController@indexTypes: Award types fetched successfully', [
                'message' => 'تم جلب الأنواع بنجاح',
                'company_id' => $effectiveCompanyId ?? null,
                'search' => $search ?? null,
            ]);

            return $this->successResponse($types, 'تم جلب الأنواع بنجاح');
        } catch (Exception $e) {
            Log::error('AwardConfigurationController@indexTypes: Error fetching award types', [
                'error' => $e->getMessage(),
                'message' => 'حدث خطأ أثناء جلب الأنواع',
                'company_id' => $effectiveCompanyId ?? null,
                'search' => $search ?? null,
            ]);
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/awards/types",
     *     summary="إضافة نوع مكافئة جديد",
     *     description="يضيف نوع مكافئة جديد للشركة",
     *     tags={"Award Configuration"},
     *     security={{ "bearerAuth": {} }},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", description="اسم النوع")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="تم إضافة النوع بنجاح",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم إضافة النوع بنجاح"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="constants_id", type="integer"),
     *                 @OA\Property(property="category_name", type="string"),
     *                 @OA\Property(property="type", type="string", example="award_type"),
     *                 @OA\Property(property="company_id", type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=400, description="خطأ في الطلب"),
     *     @OA\Response(response=422, description="بيانات غير صالحة"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function storeType(StoreAwardConfigurationRequest $request): JsonResponse
    {
        try {
            $companyId = $this->permissionService->getEffectiveCompanyId(Auth::user());

            $type = $this->awardConfigurationService->createType($companyId, $request->name);

            if(!$type){
                Log::error('AwardConfigurationController@storeType: Error creating award type', [
                    'message' => 'حدث خطأ أثناء إضافة النوع',
                    'company_id' => $companyId ?? null,
                    'name' => $request->name,
                ]);
                return $this->errorResponse('حدث خطأ أثناء إضافة النوع', 500);
            }
            
            Log::info('AwardConfigurationController@storeType: Award type created successfully', [
                'message' => 'تم إضافة النوع بنجاح',
                'company_id' => $companyId ?? null,
                'name' => $request->name,
            ]);
            return $this->successResponse($type, 'تم إضافة النوع بنجاح', 201);
        } catch (Exception $e) {
            Log::error('AwardConfigurationController@storeType: Error creating award type', [
                'error' => $e->getMessage(),
                'message' => 'حدث خطأ أثناء إضافة النوع',
                'company_id' => $companyId ?? null,
                'name' => $request->name,
            ]);
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/awards/types/{id}",
     *     summary="تحديث نوع مكافئة",
     *     description="يحدث نوع مكافئة موجود",
     *     tags={"Award Configuration"},
     *     security={{ "bearerAuth": {} }},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", description="اسم النوع")
     *         )
     *     ),
     *     @OA\Response(response=200, description="تم التحديث بنجاح"),
     *     @OA\Response(response=404, description="النوع غير موجود"),
     *     @OA\Response(response=422, description="بيانات غير صالحة"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function updateType(StoreAwardConfigurationRequest $request, $id): JsonResponse
    {
        try {
            $companyId = $this->permissionService->getEffectiveCompanyId(Auth::user());

            $type = $this->awardConfigurationService->updateType($companyId, $id, $request->name);

            if (!$type) {
                Log::error('AwardConfigurationController@updateType: Award type not found', [
                    'message' => 'النوع غير موجود',
                    'company_id' => $companyId ?? null,
                    'id' => $id,
                ]);
                return $this->errorResponse('النوع غير موجود', 404);
            }

            Log::info('AwardConfigurationController@updateType: Award type updated successfully', [
                'message' => 'تم تحديث النوع بنجاح',
                'company_id' => $companyId ?? null,
                'id' => $id,
            ]);
            return $this->successResponse($type, 'تم تحديث النوع بنجاح');
        } catch (Exception $e) {
            Log::error('AwardConfigurationController@updateType: Error updating award type', [
                'error' => $e->getMessage(),
                'message' => 'حدث خطأ أثناء تحديث النوع',
                'company_id' => $companyId ?? null,
                'id' => $id,
            ]);
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/awards/types/{id}",
     *     summary="حذف نوع مكافئة",
     *     description="يحذف نوع مكافئة موجود",
     *     tags={"Award Configuration"},
     *     security={{ "bearerAuth": {} }},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="تم الحذف بنجاح"),
     *     @OA\Response(response=404, description="النوع غير موجود"),
     *     @OA\Response(response=422, description="بيانات غير صالحة"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function destroyType(int $id): JsonResponse
    {
        try {
            $companyId = $this->permissionService->getEffectiveCompanyId(Auth::user());

            $deleted = $this->awardConfigurationService->deleteType($companyId, $id);

            if (!$deleted) {
                Log::error('AwardConfigurationController@destroyType: Award type not found', [
                    'message' => 'النوع غير موجود',
                    'company_id' => $companyId ?? null,
                    'id' => $id,
                ]);
                return $this->errorResponse('النوع غير موجود', 404);
            }

            Log::info('AwardConfigurationController@destroyType: Award type deleted successfully', [
                'message' => 'تم حذف النوع بنجاح',
                'company_id' => $companyId ?? null,
                'id' => $id,
            ]);
            return $this->successResponse(null, 'تم حذف النوع بنجاح');
        } catch (Exception $e) {
            Log::error('AwardConfigurationController@destroyType: Error deleting award type', [
                'error' => $e->getMessage(),
                'message' => 'حدث خطأ أثناء حذف النوع',
                'company_id' => $companyId ?? null,
                'id' => $id,
            ]);
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}
