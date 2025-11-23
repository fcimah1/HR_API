<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemLog;
use App\Services\SimplePermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SystemLogController extends Controller
{
    protected $permissionService;

    public function __construct(SimplePermissionService $permissionService)
    {
        $this->permissionService = $permissionService;
    }

    /**
     * @OA\Get(
     *     path="/api/system-logs",
     *     summary="Get system logs",
     *     description="Retrieve paginated system logs with filtering options",
     *     tags={"System Logs"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="level", in="query", description="Filter by log level (info, error, etc)", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="user_id", in="query", description="Filter by user ID", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="from_date", in="query", description="Filter from date (Y-m-d)", required=false, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="to_date", in="query", description="Filter to date (Y-m-d)", required=false, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="search", in="query", description="Search in message", required=false, @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="pagination", type="object")
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        // التحقق من الصلاحية (يمكنك تعديل اسم الصلاحية حسب نظامك)
        // if (!$this->permissionService->checkPermission(Auth::user(), 'view_logs')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        $query = SystemLog::with('user:user_id,first_name,last_name') // Assuming User model has first_name/last_name
            ->orderBy('created_at', 'desc');

        // Filters
        if ($request->has('level')) {
            $query->where('level', $request->level);
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        if ($request->has('search')) {
            $query->where('message', 'like', '%' . $request->search . '%');
        }

        $logs = $query->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $logs->items(),
            'pagination' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ]
        ]);
    }
}
