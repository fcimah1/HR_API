<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BranchService;
use App\Http\Requests\Branch\BranchSearchRequest;
use App\DTOs\Branch\BranchFilterDTO;
use App\Http\Resources\BranchResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Tag(
 *     name="Branches",
 *     description="إدارة الفروع"
 * )
 */
class BranchController extends Controller
{
    protected $branchService;

    public function __construct(BranchService $branchService)
    {
        $this->branchService = $branchService;
    }

    /**
     * @OA\Get(
     *     path="/api/branches",
     *     summary="استرجاع فروع الشركة",
     *     description="قائمة بالفروع الخاصة بالشركة مع إمكانية البحث",
     *     tags={"Branches"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="البحث بالاسم",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="branch_id",
     *         in="query",
     *         description="البحث برقم الفرع",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="تم استرجاع الفروع بنجاح",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="branch_id", type="integer", example=1),
     *                     @OA\Property(property="branch_name", type="string", example="الفرع الرئيسي"),
     *                     @OA\Property(property="description", type="string", example="وصف الفرع")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=422, description="بيانات غير صحيحة")
     * )
     */
    public function index(BranchSearchRequest $request): JsonResponse
    {
        $user = Auth::user();
        $companyId = ($user->user_type === 'company' || $user->company_id === 0) ? $user->user_id : $user->company_id;

        $filters = BranchFilterDTO::fromRequest($request)->toArray();
        $branches = $this->branchService->getBranches($companyId, $filters);

        return response()->json([
            'success' => true,  
            'company_id' => $companyId,
            'company_name' => ($user->user_type === 'company' || $user->company_id === 0) ? $user->company_name : $user->company->company_name,
            'data' => BranchResource::collection($branches)
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/branches/{id}",
     *     summary="استرجاع تفاصيل الفرع",
     *     description="تفاصيل فرع محدد تابع للشركة",
     *     tags={"Branches"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="رقم الفرع",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="تم استرجاع تفاصيل الفرع بنجاح",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="branch_id", type="integer", example=1),
     *                 @OA\Property(property="branch_name", type="string", example="الفرع الرئيسي"),
     *                 @OA\Property(property="description", type="string", example="وصف الفرع")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="الفرع غير موجود"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول")
     * )
     */
    public function show(int $id): JsonResponse
    {
        $user = Auth::user();
        $companyId = ($user->user_type === 'company' || $user->company_id === 0) ? $user->user_id : $user->company_id;

        $branch = $this->branchService->getBranch($id, $companyId);

        if (!$branch) {
            return response()->json([
                'success' => false,
                'message' => 'الفرع غير موجود'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'company_id' => $branch->company_id,
            'company_name' => $branch->company ? $branch->company->company_name : null,
            'data' => new BranchResource($branch)
        ]);
    }
}
