<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\DTOs\SupportTicket\CloseTicketDTO;
use App\DTOs\SupportTicket\CreateReplyDTO;
use App\DTOs\SupportTicket\CreateTicketDTO;
use App\DTOs\SupportTicket\TicketFilterDTO;
use App\DTOs\SupportTicket\UpdateTicketDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\SupportTicket\CloseTicketRequest;
use App\Http\Requests\SupportTicket\CreateReplyRequest;
use App\Http\Requests\SupportTicket\CreateTicketRequest;
use App\Http\Requests\SupportTicket\GetTicketsRequest;
use App\Http\Requests\SupportTicket\UpdateTicketRequest;
use App\Http\Resources\SupportTicketResource;
use App\Http\Resources\TicketReplyResource;
use App\Services\SupportTicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="Support Tickets",
 *     description="تذاكر الدعم الفني - Support Tickets management endpoints"
 * )
 */
class SupportTicketController extends Controller
{
    public function __construct(
        private SupportTicketService $ticketService,
    ) {}

    /**
     * @OA\Get(
     *     path="/api/support-tickets",
     *     summary="عرض قائمة التذاكر",
     *     description="Super User يرى كل التذاكر - المستخدم العادي يرى تذاكره فقط",
     *     operationId="getTickets",
     *     tags={"Support Tickets"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="page", in="query", description="رقم الصفحة", @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="per_page", in="query", description="عدد العناصر في الصفحة", @OA\Schema(type="integer", default=15, maximum=100)),
     *     @OA\Parameter(name="status", in="query", description="الحالة: open, closed", @OA\Schema(type="string")),
     *     @OA\Parameter(name="category", in="query", description="النوع: general, technical, billing, subscription, other", @OA\Schema(type="string")),
     *     @OA\Parameter(name="priority", in="query", description="الأولوية: urgent, high, medium, low", @OA\Schema(type="string")),
     *     @OA\Parameter(name="search", in="query", description="نص البحث", @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="تم جلب التذاكر بنجاح",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/SupportTicketResource")),
     *             @OA\Property(property="pagination", type="object")
     *         )
     *     )
     * )
     */
    public function index(GetTicketsRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $isSuperUser = $this->ticketService->isSuperUser($user);

            $filters = TicketFilterDTO::fromRequest(
                $request->validated(),
                $isSuperUser ? null : $user->company_id,
                $isSuperUser ? null : $user->user_id,
                $isSuperUser
            );

            $result = $this->ticketService->getPaginatedTickets($filters, $user);

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Error in SupportTicketController@index', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب التذاكر',
                'message_en' => 'Error fetching tickets',
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/support-tickets/{id}",
     *     summary="عرض تفاصيل تذكرة",
     *     operationId="getTicket",
     *     tags={"Support Tickets"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="تم جلب التذكرة بنجاح",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/SupportTicketResource")
     *         )
     *     ),
     *     @OA\Response(response=404, description="التذكرة غير موجودة")
     * )
     */
    public function show(int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $result = $this->ticketService->getTicketById($id, $user);

            if (!$result['success']) {
                return response()->json($result, 404);
            }

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Error in SupportTicketController@show', [
                'error' => $e->getMessage(),
                'ticket_id' => $id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب التذكرة',
                'message_en' => 'Error fetching ticket',
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/support-tickets",
     *     summary="إنشاء تذكرة جديدة",
     *     operationId="createTicket",
     *     tags={"Support Tickets"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"subject", "category", "priority", "description"},
     *             @OA\Property(property="subject", type="string", example="مشكلة في تسجيل الدخول"),
     *             @OA\Property(property="category", type="string", example="technical", description="يقبل: general, technical, billing, subscription, other"),
     *             @OA\Property(property="priority", type="string", example="high", description="يقبل: urgent, high, medium, low"),
     *             @OA\Property(property="description", type="string", example="لا أستطيع تسجيل الدخول")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="تم إنشاء التذكرة بنجاح",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم إنشاء التذكرة بنجاح"),
     *             @OA\Property(property="data", ref="#/components/schemas/SupportTicketResource")
     *         )
     *     ),
     *     @OA\Response(response=422, description="خطأ في البيانات المدخلة")
     * )
     */
    public function store(CreateTicketRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();

            // حساب company_id الفعلي: إذا كان صاحب شركة، يكون user_id، وإلا company_id
            $effectiveCompanyId = $user->user_type === 'company'
                ? $user->user_id
                : $user->company_id;

            $dto = CreateTicketDTO::fromRequest(
                $request->validated(),
                $effectiveCompanyId,
                $user->user_id
            );

            $result = $this->ticketService->createTicket($dto);

            return response()->json($result, 201);
        } catch (\Exception $e) {
            Log::error('Error in SupportTicketController@store', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء إنشاء التذكرة',
                'message_en' => 'Error creating ticket',
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/support-tickets/{id}",
     *     summary="تحديث تذكرة",
     *     operationId="updateTicket",
     *     tags={"Support Tickets"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         @OA\JsonContent(ref="#/components/schemas/UpdateTicketRequest")
     *     ),
     *     @OA\Response(response=200, description="تم تحديث التذكرة بنجاح"),
     *     @OA\Response(response=403, description="لا تملك صلاحية تعديل هذه التذكرة")
     * )
     */
    public function update(int $id, UpdateTicketRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();

            $dto = UpdateTicketDTO::fromRequest($request->validated());
            $result = $this->ticketService->updateTicket($id, $dto, $user);

            if (!$result['success']) {
                return response()->json($result, 403);
            }

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Error in SupportTicketController@update', [
                'error' => $e->getMessage(),
                'ticket_id' => $id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء تحديث التذكرة',
                'message_en' => 'Error updating ticket',
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/support-tickets/{id}/close",
     *     summary="إغلاق تذكرة",
     *     operationId="closeTicket",
     *     tags={"Support Tickets"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="ticket_remarks", type="string", example="تم حل المشكلة")
     *         )
     *     ),
     *     @OA\Response(response=200, description="تم إغلاق التذكرة بنجاح"),
     *     @OA\Response(response=403, description="لا تملك صلاحية إغلاق هذه التذكرة")
     * )
     */
    public function close(int $id, CloseTicketRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();

            $dto = CloseTicketDTO::fromRequest(
                $request->validated(),
                $user->user_id
            );

            $result = $this->ticketService->closeTicket($id, $dto, $user);

            if (!$result['success']) {
                return response()->json($result, 403);
            }

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Error in SupportTicketController@close', [
                'error' => $e->getMessage(),
                'ticket_id' => $id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء إغلاق التذكرة',
                'message_en' => 'Error closing ticket',
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/support-tickets/{id}/reopen",
     *     summary="إعادة فتح تذكرة",
     *     operationId="reopenTicket",
     *     tags={"Support Tickets"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="تم إعادة فتح التذكرة بنجاح"),
     *     @OA\Response(response=403, description="لا تملك صلاحية إعادة فتح هذه التذكرة")
     * )
     */
    public function reopen(int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $result = $this->ticketService->reopenTicket($id, $user);

            if (!$result['success']) {
                return response()->json($result, 403);
            }

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Error in SupportTicketController@reopen', [
                'error' => $e->getMessage(),
                'ticket_id' => $id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء إعادة فتح التذكرة',
                'message_en' => 'Error reopening ticket',
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/support-tickets/{id}/replies",
     *     summary="إضافة رد على تذكرة",
     *     operationId="addReply",
     *     tags={"Support Tickets"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"reply_text"},
     *             @OA\Property(property="reply_text", type="string", example="شكراً لتواصلكم، سنقوم بمراجعة المشكلة")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="تم إضافة الرد بنجاح",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/TicketReplyResource")
     *         )
     *     ),
     *     @OA\Response(response=403, description="التذكرة مغلقة أو لا تملك صلاحية الرد")
     * )
     */
    public function addReply(int $id, CreateReplyRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();

            // تحديد المستلم (assign_to)
            $isSuperUser = $this->ticketService->isSuperUser($user);

            // الحصول على التذكرة للحصول على created_by
            $ticketResult = $this->ticketService->getTicketById($id, $user);
            if (!$ticketResult['success']) {
                return response()->json($ticketResult, 404);
            }

            $assignTo = $isSuperUser
                ? $ticketResult['data']['created_by']
                : $user->user_id;

            // حساب company_id الفعلي: إذا كان صاحب شركة، يكون user_id، وإلا company_id
            $effectiveCompanyId = $user->user_type === 'company'
                ? $user->user_id
                : $user->company_id;

            $dto = CreateReplyDTO::fromRequest(
                $request->validated(),
                $id,
                $effectiveCompanyId,
                $user->user_id,
                $assignTo
            );

            $result = $this->ticketService->addReply($id, $dto, $user);

            if (!$result['success']) {
                return response()->json($result, 403);
            }

            return response()->json($result, 201);
        } catch (\Exception $e) {
            Log::error('Error in SupportTicketController@addReply', [
                'error' => $e->getMessage(),
                'ticket_id' => $id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء إضافة الرد',
                'message_en' => 'Error adding reply',
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/support-tickets/{id}/replies",
     *     summary="عرض ردود تذكرة",
     *     operationId="getTicketReplies",
     *     tags={"Support Tickets"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="تم جلب الردود بنجاح",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/TicketReplyResource")),
     *             @OA\Property(property="can_reply", type="boolean", example=true)
     *         )
     *     )
     * )
     */
    public function getReplies(int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $result = $this->ticketService->getTicketReplies($id, $user);

            if (!$result['success']) {
                return response()->json($result, 404);
            }

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Error in SupportTicketController@getReplies', [
                'error' => $e->getMessage(),
                'ticket_id' => $id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب الردود',
                'message_en' => 'Error fetching replies',
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/support-tickets/enums",
     *     summary="الحصول على الأنواع والحالات والأولويات",
     *     operationId="getTicketEnums",
     *     tags={"Support Tickets"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="تم جلب البيانات بنجاح",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="categories", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="statuses", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="priorities", type="array", @OA\Items(type="object"))
     *             )
     *         )
     *     )
     * )
     */
    public function getEnums(): JsonResponse
    {
        try {
            $enums = $this->ticketService->getEnums();

            return response()->json([
                'success' => true,
                'data' => $enums,
            ]);
        } catch (\Exception $e) {
            Log::error('Error in SupportTicketController@getEnums', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب البيانات',
                'message_en' => 'Error fetching enums',
            ], 500);
        }
    }

    // /**
    //  * @OA\Delete(
    //  *     path="/api/support-tickets/{id}",
    //  *     summary="حذف تذكرة",
    //  *     description="super_user يمكنه حذف أي تذكرة - المستخدم العادي يحذف تذاكره فقط",
    //  *     operationId="deleteTicket",
    //  *     tags={"Support Tickets"},
    //  *     security={{"bearerAuth":{}}},
    //  *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
    //  *     @OA\Response(
    //  *         response=200,
    //  *         description="تم حذف التذكرة بنجاح",
    //  *         @OA\JsonContent(
    //  *             @OA\Property(property="success", type="boolean", example=true),
    //  *             @OA\Property(property="message", type="string", example="تم حذف التذكرة بنجاح"),
    //  *             @OA\Property(property="data", type="object",
    //  *                 @OA\Property(property="ticket_id", type="integer"),
    //  *                 @OA\Property(property="ticket_code", type="string")
    //  *             )
    //  *         )
    //  *     ),
    //  *     @OA\Response(response=403, description="لا تملك صلاحية حذف هذه التذكرة"),
    //  *     @OA\Response(response=404, description="التذكرة غير موجودة")
    //  * )
    //  */
    // public function destroy(int $id): JsonResponse
    // {
    //     try {
    //         $user = Auth::user();
    //         $result = $this->ticketService->deleteTicket($id, $user);

    //         if (!$result['success']) {
    //             return response()->json($result, 403);
    //         }

    //         return response()->json($result);
    //     } catch (\Exception $e) {
    //         Log::error('Error in SupportTicketController@destroy', [
    //             'error' => $e->getMessage(),
    //             'ticket_id' => $id,
    //         ]);

    //         return response()->json([
    //             'success' => false,
    //             'message' => 'حدث خطأ أثناء حذف التذكرة',
    //             'message_en' => 'Error deleting ticket',
    //         ], 500);
    //     }
    // }
}
