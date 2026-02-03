<?php

namespace App\Http\Controllers\Api;

use App\DTOs\Award\AwardFilterDTO;
use App\DTOs\Award\CreateAwardDTO;
use App\DTOs\Award\UpdateAwardDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Award\StoreAwardRequest;
use App\Http\Requests\Award\UpdateAwardRequest;
use App\Services\AwardService;
use App\Services\SimplePermissionService;
use App\Traits\ApiResponseTrait;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Award Management",
 *     description="إدارة المكافآت"
 * )
 */
class AwardController extends Controller
{
    use ApiResponseTrait;

    protected $awardService;
    protected $permissionService;

    public function __construct(
        AwardService $awardService,
        SimplePermissionService $permissionService
    ) {
        $this->awardService = $awardService;
        $this->permissionService = $permissionService;
    }

    /**
     * @OA\Get(
     *     path="/api/awards",
     *     summary="عرض قائمة المكافئات",
     *     description="يعرض قائمة بجميع المكافئات مع إمكانية التصفية",
     *     tags={"Award Management"},
     *     security={{ "bearerAuth": {} }},
     *     @OA\Parameter(name="employee_id", in="query", @OA\Schema(type="integer"), description="تصفية حسب الموظف"),
     *     @OA\Parameter(name="award_type_id", in="query", @OA\Schema(type="integer"), description="تصفية حسب نوع المكافئة"),
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string"), description="بحث عن المكافئة"),
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer"), description="رقم الصفحة"),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer"), description="عدد العناصر في الصفحة"),
     *     @OA\Response(response=200, description="تم جلب البيانات بنجاح"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        try{
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId(Auth::user());

            $filters = $request->only(['employee_id', 'award_type_id','search']);
            $perPage = (int) $request->input('per_page', 15);

            $awards = $this->awardService->getAwards($effectiveCompanyId, $filters, $perPage);

            if(!$awards){
                Log::error('AwardController@index: No awards found', [
                    'message' => 'لا توجد بيانات',
                    'company_id' => $effectiveCompanyId,
                    'filters' => $filters,
                    'per_page' => $perPage,
                ]);
                return $this->errorResponse('لا توجد بيانات', 404);
            }
            Log::info('AwardController@index: Awards fetched successfully', [
                'message' => 'تم جلب البيانات بنجاح',
                'company_id' => $effectiveCompanyId,
                'filters' => $filters,
                'per_page' => $perPage,
                'awards_count' => $awards->count(),
            ]);
            return $this->successResponse($awards, 'تم جلب البيانات بنجاح');
        }catch(Exception $e){
            Log::error('AwardController@index: Error fetching awards', [
                'message' => $e->getMessage(),
                'company_id' => $effectiveCompanyId,
                'filters' => $filters,
                'per_page' => $perPage,
            ]);
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/awards",
     *     summary="إضافة مكافئة جديدة",
     *     description="يضيف مكافئة جديدة للموظف",
     *     tags={"Award Management"},
     *     security={{ "bearerAuth": {} }},
     *     @OA\RequestBody(
     *         required=true,
     *         content={
     *             @OA\MediaType(
     *                 mediaType="multipart/form-data",
     *                 @OA\Schema(
     *                     required={"employee_id", "award_type_id", "award_date"},
     *                     @OA\Property(property="employee_id", type="integer", example=101),
     *                     @OA\Property(property="award_type_id", type="integer", example=5),
     *                     @OA\Property(property="award_date", type="string", format="date", example="2024-01-01"),
     *                     @OA\Property(property="gift_item", type="string", example="الساعة الذكية"),
     *                     @OA\Property(property="cash_price", type="number", format="float", example=500.00),
     *                     @OA\Property(property="description", type="string", example="مكافئة للأداء المتميز"),
     *                     @OA\Property(property="award_information", type="string", example="تفاصيل إضافية"),
     *                     @OA\Property(property="award_file", type="string", format="binary")
     *                 )
     *             )
     *         }
     *     ),
     *     @OA\Response(response=201, description="تم إضافة المكافئة بنجاح"),
     *     @OA\Response(response=422, description="بيانات غير صالحة"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function store(StoreAwardRequest $request): JsonResponse
    {
        try{
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId(Auth::user());

            $dto = CreateAwardDTO::fromRequest($request->validated(), $effectiveCompanyId);
            $award = $this->awardService->createAward($dto);

            if(!$award){
                Log::error('AwardController@store: Error creating award', [
                    'message' => 'لا توجد بيانات',
                    'company_id' => $effectiveCompanyId,
                    'request' => $request->all(),
                ]);
                return $this->errorResponse('لا توجد بيانات', 404);
            }
            Log::info('AwardController@store: Award created successfully', [
                'message' => 'تم إضافة المكافئة بنجاح',
                'company_id' => $effectiveCompanyId,
                'award' => $award,
            ]);
            return $this->successResponse($award, 'تم إضافة المكافئة بنجاح', 201);
        }catch(Exception $e){
            Log::error('AwardController@store: Error creating award', [
                'message' => $e->getMessage(),
                'company_id' => $effectiveCompanyId,
                'request' => $request->all(),
            ]);
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/awards/{id}",
     *     summary="عرض تفاصيل المكافئة",
     *     description="يعرض تفاصيل مكافئة محددة",
     *     tags={"Award Management"},
     *     security={{ "bearerAuth": {} }},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="تم جلب التفاصيل بنجاح"),
     *     @OA\Response(response=404, description="المكافئة غير موجودة"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function show(int $id): JsonResponse
    {
        try{
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId(Auth::user());

            $award = $this->awardService->getAward($id, $effectiveCompanyId);

            if (!$award) {
                Log::error('AwardController@show: Error fetching award', [
                    'message' => 'المكافئة غير موجودة',
                    'company_id' => $effectiveCompanyId,
                    'id' => $id,
                ]);
                return $this->errorResponse('المكافئة غير موجودة', 404);
            }
            Log::info('AwardController@show: Award fetched successfully', [
                'message' => 'تم جلب التفاصيل بنجاح',
                'company_id' => $effectiveCompanyId,
                'award' => $award,
            ]);
            return $this->successResponse($award, 'تم جلب التفاصيل بنجاح');
        }catch(Exception $e){
            Log::error('AwardController@show: Error fetching award', [
                'message' => $e->getMessage(),
                'company_id' => $effectiveCompanyId,
                'id' => $id,
            ]);
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/awards/{id}",
     *     summary="تعديل مكافئة",
     *     description="يقوم بتعديل بيانات مكافئة موجودة (استخدم POST مع _method=PUT لدعم رفع الملفات)",
     *     tags={"Award Management"},
     *     security={{ "bearerAuth": {} }},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="_method", type="string", example="PUT"),
     *                 @OA\Property(property="employee_id", type="integer", example="1"),
     *                 @OA\Property(property="award_type_id", type="integer", example="1"),
     *                 @OA\Property(property="award_date", type="string", format="date", example="2026-02-02"),
     *                 @OA\Property(property="gift_item", type="string", example="Gift Item"),
     *                 @OA\Property(property="cash_price", type="number", format="float", example="100.00"),
     *                 @OA\Property(property="description", type="string", example="Description"),
     *                 @OA\Property(property="award_information", type="string", example="Award Information"),
     *                 @OA\Property(property="award_file", type="string", format="binary", example="Award File")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="تم تعديل المكافئة بنجاح"),
     *     @OA\Response(response=404, description="المكافئة غير موجودة"),
     *     @OA\Response(response=422, description="بيانات غير صالحة"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function update(UpdateAwardRequest $request, int $id): JsonResponse
    {
        try{
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId(Auth::user());

            $dto = UpdateAwardDTO::fromRequest($id, $request->validated());
            $award = $this->awardService->updateAward($dto, $effectiveCompanyId);

            if (!$award) {
                Log::error('AwardController@update: Error updating award', [
                    'message' => 'المكافئة غير موجودة أو لم يتم التعديل',
                    'company_id' => $effectiveCompanyId,
                    'id' => $id,
                ]);
                return $this->errorResponse('المكافئة غير موجودة أو لم يتم التعديل', 404);
            }
            Log::info('AwardController@update: Award updated successfully', [
                'message' => 'تم تعديل المكافئة بنجاح',
                'company_id' => $effectiveCompanyId,
                'award' => $award,
            ]);
            return $this->successResponse($award, 'تم تعديل المكافئة بنجاح');
        }catch(Exception $e){
            Log::error('AwardController@update: Error updating award', [
                'message' => $e->getMessage(),
                'company_id' => $effectiveCompanyId,
                'id' => $id,
            ]);
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/awards/{id}",
     *     summary="حذف مكافئة",
     *     description="يحذف مكافئة موجودة",
     *     tags={"Award Management"},
     *     security={{ "bearerAuth": {} }},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="تم الحذف بنجاح"),
     *     @OA\Response(response=404, description="المكافئة غير موجودة"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        try{
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId(Auth::user());

            $deleted = $this->awardService->deleteAward($id, $effectiveCompanyId);

            if (!$deleted) {
                Log::error('AwardController@destroy: Error deleting award', [
                    'message' => 'المكافئة غير موجودة',
                    'company_id' => $effectiveCompanyId,
                    'id' => $id,
                ]);
                return $this->errorResponse('المكافئة غير موجودة', 404);
            }
            Log::info('AwardController@destroy: Award deleted successfully', [
                'message' => 'تم حذف المكافئة بنجاح',
                'company_id' => $effectiveCompanyId,
                'id' => $id,
            ]);
            return $this->successResponse(null, 'تم الحذف بنجاح');
        }catch(Exception $e){
            Log::error('AwardController@destroy: Error deleting award', [
                'message' => $e->getMessage(),
                'company_id' => $effectiveCompanyId,
                'id' => $id,
            ]);
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}
