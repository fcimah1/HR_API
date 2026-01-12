<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    protected $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    /**
     * @OA\Get(
     *     path="/api/dashboard/stats",
     *     summary="Get service consumption statistics",
     *     description="Retrieve summary of leaves, overtime, travel, and loans for the authenticated user",
     *     tags={"Dashboard"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Dashboard stats retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="leaves",
     *                     type="object",
     *                     @OA\Property(property="granted", type="number", example=30),
     *                     @OA\Property(property="used", type="number", example=5),
     *                     @OA\Property(property="balance", type="number", example=25),
     *                     @OA\Property(property="pending", type="number", example=0)
     *                 ),
     *                 @OA\Property(
     *                     property="overtime",
     *                     type="object",
     *                     @OA\Property(property="approved_requests", type="integer", example=3)
     *                 ),
     *                 @OA\Property(
     *                     property="travel",
     *                     type="object",
     *                     @OA\Property(property="total_trips", type="integer", example=1)
     *                 ),
     *                 @OA\Property(
     *                     property="loans",
     *                     type="object",
     *                     @OA\Property(property="total_requests", type="integer", example=0)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=500, description="خطأ في الخادم"),
     *     @OA\Response(response=401, description="غير مصرح يجب تسجيل الدخول"),
     *     @OA\Response(response=403, description="ليس لديك الصلاحية للوصول إلى هذه البيانات"),
     *     @OA\Response(response=422, description="خطأ في البيانات المدخلة")
     * )
     */
    public function getStats(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $stats = $this->dashboardService->getConsumptionStats($user);

        return response()->json([
            'status' => true,
            'message' => 'Dashboard stats retrieved successfully',
            'data' => $stats
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/dashboard/activity",
     *     summary="Get recent activity",
     *     description="Retrieve recent activity (leaves, overtime, travel, loans) for the authenticated user",
     *     tags={"Dashboard"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Recent activity retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="type", type="string", example="leave"),
     *                     @OA\Property(property="title", type="string", example="Leave Request"),
     *                     @OA\Property(property="status", type="string", example="pending"),
     *                     @OA\Property(property="date", type="string", format="date-time", example="2024-05-20T10:00:00.000000Z"),
     *                     @OA\Property(property="formatted_date", type="string", example="2 hours ago"),
     *                     @OA\Property(property="details", type="string", example="Annual Leave"),
     *                     @OA\Property(property="id", type="integer", example=15)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=500, description="خطأ في الخادم"),
     *     @OA\Response(response=401, description="غير مصرح يجب تسجيل الدخول"),
     *     @OA\Response(response=403, description="ليس لديك الصلاحية للوصول إلى هذه البيانات"),
     *     @OA\Response(response=422, description="خطأ في البيانات المدخلة")
     * )
     */
    public function getActivity(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $activity = $this->dashboardService->getRecentActivity($user);

        return response()->json([
            'status' => true,
            'message' => 'Recent activity retrieved successfully',
            'data' => $activity
        ]);
    }
}
