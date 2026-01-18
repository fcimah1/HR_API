<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CountryService;
use App\Http\Requests\Country\CountrySearchRequest;
use App\DTOs\Country\CountryFilterDTO;
use App\Http\Resources\CountryResource;
use Illuminate\Http\JsonResponse;

class CountryController extends Controller
{
    protected $countryService;

    public function __construct(CountryService $countryService)
    {
        $this->countryService = $countryService;
    }

    /**
     * @OA\Get(
     *     path="/api/countries",
     *     summary="Get all countries",
     *     description="استرجاع قائمة بجميع الدول مع البحث الاختياري",
     *     tags={"Countries"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="البحث بالاسم أو الرمز",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="country_id",
     *         in="query",
     *         description="البحث برقم الدولة",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="country_name",
     *         in="query",
     *         description="البحث باسم الدولة",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="country_code",
     *         in="query",
     *         description="البحث برمز الدولة",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="تم استرجاع الدول بنجاح",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="country_id", type="integer", example=1),
     *                     @OA\Property(property="country_name", type="string", example="أفغانستان"),
     *                     @OA\Property(property="country_code", type="string", example="93"),
     *                     @OA\Property(property="iso_code", type="string", example="AF"),
     *                     @OA\Property(property="phone_code", type="string", example="93")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=404, description="الدولة غير موجودة"),
     *     @OA\Response(response=422, description="بيانات غير صحيحة"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function index(CountrySearchRequest $request): JsonResponse
    {
        $filters = CountryFilterDTO::fromRequest($request)->toArray();
        $countries = $this->countryService->getCountries($filters);

        return response()->json([
            'success' => true,
            'data' => CountryResource::collection($countries)
        ]);
    }
    /**
     * @OA\Get(
     *     path="/api/countries/{id}",
     *     summary="استرجاع تفاصيل الدولة",
     *     description="استرجاع تفاصيل الدولة",
     *     tags={"Countries"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="رقم الدولة",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="تم استرجاع تفاصيل الدولة بنجاح",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="country_id", type="integer", example=1),
     *                 @OA\Property(property="country_name", type="string", example="أفغانستان"),
     *                 @OA\Property(property="country_code", type="string", example="93"),
     *                 @OA\Property(property="iso_code", type="string", example="AF"),
     *                 @OA\Property(property="phone_code", type="string", example="93")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="الدولة غير موجودة"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=422, description="بيانات غير صحيحة"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function show(int $id): JsonResponse
    {
        $country = $this->countryService->getCountry($id);

        if (!$country) {
            return response()->json([
                'success' => false,
                'message' => 'الدولة غير موجودة'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new CountryResource($country)
        ]);
    }
}
