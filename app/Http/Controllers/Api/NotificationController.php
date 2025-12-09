<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\NotificationService;
use App\Services\SimplePermissionService;
use App\DTOs\Notification\NotificationSettingDTO;
use App\Http\Requests\Notification\UpdateNotificationSettingsRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="Notifications",
 *     description="API for managing notifications and notification settings"
 * )
 */
class NotificationController extends Controller
{
    public function __construct(
        protected NotificationService $notificationService,
        protected SimplePermissionService $permissionService,
    ) {}

    /**
     * @OA\Get(
     *     path="/api/notifications",
     *     summary="Get user notifications",
     *     tags={"Notifications"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="module_option",
     *         in="query",
     *         description="Filter by module (optional)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         @OA\Schema(type="integer", default=20)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Notifications retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="notification_status_id", type="integer", example=1001),
     *                     @OA\Property(property="module_option", type="string", example="leave_settings"),
     *                     @OA\Property(property="module_status", type="integer", example=0, description="0=pending,1=approved,2=rejected"),
     *                     @OA\Property(property="module_key_id", type="string", example="325"),
     *                     @OA\Property(property="staff_id", type="integer", example=37),
     *                     @OA\Property(property="is_read", type="integer", example=0),
     *                     @OA\Property(
     *                         property="staff",
     *                         type="object",
     *                         nullable=true,
     *                         @OA\Property(property="user_id", type="integer", example=37),
     *                         @OA\Property(property="full_name", type="string", example="محمد أحمد"),
     *                         @OA\Property(property="email", type="string", example="m.ahmed@example.com")
     *                     )
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="pagination",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="last_page", type="integer", example=5),
     *                 @OA\Property(property="per_page", type="integer", example=20),
     *                 @OA\Property(property="total", type="integer", example=87),
     *                 @OA\Property(property="from", type="integer", example=1),
     *                 @OA\Property(property="to", type="integer", example=20),
     *                 @OA\Property(property="has_more_pages", type="boolean", example=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="حدث خطأ في الخادم")
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        try {
            $user = Auth::user();
            $moduleOption = $request->query('module_option');
            $perPage = (int) $request->query('per_page', 20);

            $notifications = $this->notificationService->getUserNotifications(
                $user->user_id,
                $moduleOption,
                $perPage
            );

            return response()->json([
                'success' => true,
                'data' => $notifications['data'],
                'pagination' => $notifications['pagination'],
            ]);
        } catch (\Exception $e) {
            Log::error('NotificationController::index failed', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/notifications/unread-count",
     *     summary="Get unread notifications count",
     *     tags={"Notifications"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="module_option",
     *         in="query",
     *         description="Filter by module (optional)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Unread count retrieved"
     *     )
     * )
     */
    public function unreadCount(Request $request)
    {
        try {
            $user = Auth::user();
            $moduleOption = $request->query('module_option');

            $count = $this->notificationService->getUnreadCount($user->user_id, $moduleOption);

            return response()->json([
                'success' => true,
                'unread_count' => $count,
            ]);
        } catch (\Exception $e) {
            Log::error('NotificationController::unreadCount failed', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/notifications/{id}/read",
     *     summary="Mark notification as read",
     *     tags={"Notifications"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Notification marked as read"
     *     )
     * )
     */
    public function markAsRead(int $id)
    {
        try {
            $user = Auth::user();
            $result = $this->notificationService->markNotificationAsRead($id, $user->user_id);

            if (!$result) {
                return response()->json([
                    'success' => false,
                    'message' => 'الإشعار غير موجود'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'تم تعليم الإشعار كمقروء'
            ]);
        } catch (\Exception $e) {
            Log::error('NotificationController::markAsRead failed', [
                'error' => $e->getMessage(),
                'notification_id' => $id,
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/notifications/mark-all-read",
     *     summary="Mark all notifications as read",
     *     tags={"Notifications"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="module_option",
     *         in="query",
     *         description="Mark all for specific module (optional)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="All notifications marked as read"
     *     )
     * )
     */
    public function markAllAsRead(Request $request)
    {
        try {
            $user = Auth::user();
            $moduleOption = $request->query('module_option');

            $count = $this->notificationService->markAllAsRead($user->user_id, $moduleOption);

            return response()->json([
                'success' => true,
                'message' => 'تم تعليم جميع الإشعارات كمقروءة',
                'count' => $count,
            ]);
        } catch (\Exception $e) {
            Log::error('NotificationController::markAllAsRead failed', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/notifications/settings/{module}",
     *     summary="Get notification settings for module (Admin only)",
     *     tags={"Notifications"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="module",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Settings retrieved"
     *     )
     * )
     */
    public function getSettings(string $module)
    {
        try {
            $user = Auth::user();
            $companyId = $this->permissionService->getEffectiveCompanyId($user);

            $settings = $this->notificationService->getNotificationSettings($companyId, $module);

            if (!$settings) {
                return response()->json([
                    'success' => true,
                    'data' => null,
                    'message' => 'لا توجد إعدادات مسجلة لهذه الوحدة'
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $settings
            ]);
        } catch (\Exception $e) {
            Log::error('NotificationController::getSettings failed', [
                'error' => $e->getMessage(),
                'module' => $module,
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/notifications/settings",
     *     summary="Update notification settings (Admin only)",
     *     tags={"Notifications"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"module_option"},
     *             @OA\Property(property="module_option", type="string"),
     *             @OA\Property(property="notify_upon_submission", type="array", @OA\Items(type="integer")),
     *             @OA\Property(property="notify_upon_approval", type="array", @OA\Items(type="integer")),
     *             @OA\Property(property="approval_level", type="integer")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Settings updated"
     *     )
     * )
     */
    public function updateSettings(UpdateNotificationSettingsRequest $request)
    {
        try {
            $user = Auth::user();
            $companyId = $this->permissionService->getEffectiveCompanyId($user);

            $dto = NotificationSettingDTO::fromRequest($request, $companyId);
            $result = $this->notificationService->updateNotificationSettings($dto);

            return response()->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('NotificationController::updateSettings failed', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
