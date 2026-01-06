<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\SupportTicket\CloseTicketDTO;
use App\DTOs\SupportTicket\CreateReplyDTO;
use App\DTOs\SupportTicket\CreateTicketDTO;
use App\DTOs\SupportTicket\TicketFilterDTO;
use App\DTOs\SupportTicket\UpdateTicketDTO;
use App\Enums\TicketCategoryEnum;
use App\Enums\TicketPriorityEnum;
use App\Enums\TicketStatusEnum;
use App\Models\SupportTicket;
use App\Models\TicketReply;
use App\Models\User;
use App\Repository\Interface\SupportTicketRepositoryInterface;
use App\Services\SimplePermissionService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SupportTicketService
{
    public function __construct(
        protected SupportTicketRepositoryInterface $ticketRepository,
        protected SimplePermissionService $permissionService,
    ) {}

    /**
     * التحقق إذا كان المستخدم Super User
     */
    public function isSuperUser(User $user): bool
    {
        return $this->permissionService->isSuperUser($user);
    }

    /**
     * التحقق من صلاحية الوصول للتذكرة
     */
    public function canAccessTicket(SupportTicket $ticket, User $user): bool
    {
        // Super User يمكنه الوصول لأي تذكرة
        if ($this->isSuperUser($user)) {
            return true;
        }

        // المستخدم العادي يمكنه الوصول لتذاكره فقط
        return $ticket->created_by === $user->user_id;
    }

    /**
     * الحصول على قائمة التذاكر مع التصفية
     */
    public function getPaginatedTickets(TicketFilterDTO $filters, User $user): array
    {
        try {
            // تعديل الفلاتر حسب نوع المستخدم
            $isSuperUser = $this->isSuperUser($user);

            // للمستخدم من نوع company، الـ user_id هو نفسه company_id
            $companyId = $user->company_id;
            if ($user->user_type === 'company') {
                $companyId = $user->user_id;
            }

            $adjustedFilters = new TicketFilterDTO(
                companyId: $isSuperUser ? $filters->companyId : $companyId,
                createdBy: $isSuperUser ? $filters->createdBy : $user->user_id,
                status: $filters->status,
                categoryId: $filters->categoryId,
                priority: $filters->priority,
                search: $filters->search,
                fromDate: $filters->fromDate,
                toDate: $filters->toDate,
                page: $filters->page,
                perPage: $filters->perPage,
                isSuperUser: $isSuperUser,
            );

            $result = $this->ticketRepository->getPaginatedTickets($adjustedFilters, $user);

            // تحويل البيانات للاستجابة
            $formattedData = array_map(function ($ticket) {
                return $this->formatTicketResponse($ticket);
            }, $result['data']);

            return [
                'success' => true,
                'data' => $formattedData,
                'pagination' => $result['pagination'],
            ];
        } catch (\Exception $e) {
            Log::error('Error getting tickets', [
                'error' => $e->getMessage(),
                'user_id' => $user->user_id,
            ]);

            throw $e;
        }
    }

    /**
     * الحصول على تذكرة بواسطة المعرف
     */
    public function getTicketById(int $id, User $user): array
    {
        $isSuperUser = $this->isSuperUser($user);

        // Super User يمكنه الوصول لأي تذكرة
        if ($isSuperUser) {
            $ticket = $this->ticketRepository->findTicketById($id);
        } else {
            // المستخدم العادي يحصل على تذكرته فقط
            $ticket = $this->ticketRepository->findTicketForUser($id, $user->user_id);
        }

        if (!$ticket) {
            return [
                'success' => false,
                'message' => 'التذكرة غير موجودة أو لا تملك صلاحية الوصول إليها',
                'message_en' => 'Ticket not found or access denied',
            ];
        }

        return [
            'success' => true,
            'data' => $this->formatTicketResponse($ticket, true),
        ];
    }

    /**
     * إنشاء تذكرة جديدة
     */
    public function createTicket(CreateTicketDTO $dto): array
    {
        try {
            return DB::transaction(function () use ($dto) {
                $ticket = $this->ticketRepository->createTicket($dto);

                Log::info('Ticket created successfully', [
                    'ticket_id' => $ticket->ticket_id,
                    'ticket_code' => $ticket->ticket_code,
                    'created_by' => $dto->createdBy,
                ]);

                return [
                    'success' => true,
                    'message' => 'تم إنشاء التذكرة بنجاح',
                    'message_en' => 'Ticket created successfully',
                    'data' => $this->formatTicketResponse($ticket),
                ];
            });
        } catch (\Exception $e) {
            Log::error('Error creating ticket', [
                'error' => $e->getMessage(),
                'dto' => $dto->toArray(),
            ]);

            throw $e;
        }
    }

    /**
     * تحديث تذكرة
     */
    public function updateTicket(int $id, UpdateTicketDTO $dto, User $user): array
    {
        $isSuperUser = $this->isSuperUser($user);

        // الحصول على التذكرة
        if ($isSuperUser) {
            $ticket = $this->ticketRepository->findTicketById($id);
        } else {
            $ticket = $this->ticketRepository->findTicketForUser($id, $user->user_id);
        }

        if (!$ticket) {
            return [
                'success' => false,
                'message' => 'التذكرة غير موجودة أو لا تملك صلاحية تعديلها',
                'message_en' => 'Ticket not found or access denied',
            ];
        }

        // التحقق أن التذكرة مفتوحة (status = 1) - لا يسمح بالتعديل إذا لم تكن مفتوحة صراحةً
        // لا يُسمح لأي مستخدم (بما فيهم super_user) بالتعديل على تذكرة مغلقة
        if (!$ticket->isOpen()) {
            return [
                'success' => false,
                'message' => 'لا يمكن تعديل تذكرة مغلقة',
                'message_en' => 'Cannot update a closed ticket',
            ];
        }

        try {
            return DB::transaction(function () use ($id, $ticket, $dto) {
                $updatedTicket = $this->ticketRepository->updateTicket($ticket, $dto);

                return [
                    'success' => true,
                    'message' => 'تم تحديث التذكرة بنجاح',
                    'message_en' => 'Ticket updated successfully',
                    'data' => $this->formatTicketResponse($updatedTicket),
                ];
            });
        } catch (\Exception $e) {
            Log::error('Error updating ticket', [
                'error' => $e->getMessage(),
                'ticket_id' => $id,
            ]);

            throw $e;
        }
    }

    /**
     * إغلاق تذكرة
     */
    public function closeTicket(int $id, CloseTicketDTO $dto, User $user): array
    {
        $isSuperUser = $this->isSuperUser($user);

        // الإغلاق مسموح فقط لـ super_user
        if (!$isSuperUser) {
            return [
                'success' => false,
                'message' => 'عملية الإغلاق مسموحة فقط للدعم الفني',
                'message_en' => 'Only support staff can close tickets',
            ];
        }

        // الحصول على التذكرة
        $ticket = $this->ticketRepository->findTicketById($id);

        if (!$ticket) {
            return [
                'success' => false,
                'message' => 'التذكرة غير موجودة',
                'message_en' => 'Ticket not found',
            ];
        }

        // التحقق أن التذكرة مفتوحة
        if (!$ticket->isOpen()) {
            return [
                'success' => false,
                'message' => 'التذكرة ليست مفتوحة',
                'message_en' => 'Ticket is not open',
            ];
        }

        try {
            return DB::transaction(function () use ($id, $ticket, $dto) {
                $closedTicket = $this->ticketRepository->closeTicket(
                    $ticket,
                    $dto->closedBy,
                    $dto->ticketRemarks
                );

                return [
                    'success' => true,
                    'message' => 'تم إغلاق التذكرة بنجاح',
                    'message_en' => 'Ticket closed successfully',
                    'data' => $this->formatTicketResponse($closedTicket),
                ];
            });
        } catch (\Exception $e) {
            Log::error('Error closing ticket', [
                'error' => $e->getMessage(),
                'ticket_id' => $id,
            ]);

            throw $e;
        }
    }

    /**
     * إعادة فتح تذكرة
     */
    public function reopenTicket(int $id, User $user): array
    {
        $isSuperUser = $this->isSuperUser($user);

        // إعادة الفتح مسموحة فقط لـ super_user
        if (!$isSuperUser) {
            return [
                'success' => false,
                'message' => 'عملية إعادة الفتح مسموحة فقط للدعم الفني',
                'message_en' => 'Only support staff can reopen tickets',
            ];
        }

        // الحصول على التذكرة
        $ticket = $this->ticketRepository->findTicketById($id);

        if (!$ticket) {
            return [
                'success' => false,
                'message' => 'التذكرة غير موجودة',
                'message_en' => 'Ticket not found',
            ];
        }

        // التحقق أن التذكرة مغلقة
        if ($ticket->isOpen()) {
            return [
                'success' => false,
                'message' => 'التذكرة مفتوحة بالفعل',
                'message_en' => 'Ticket is already open',
            ];
        }

        try {
            return DB::transaction(function () use ($id, $ticket, $user) {
                $reopenedTicket = $this->ticketRepository->reopenTicket($ticket, $user->user_id);

                return [
                    'success' => true,
                    'message' => 'تم إعادة فتح التذكرة بنجاح',
                    'message_en' => 'Ticket reopened successfully',
                    'data' => $this->formatTicketResponse($reopenedTicket),
                ];
            });
        } catch (\Exception $e) {
            Log::error('Error reopening ticket', [
                'error' => $e->getMessage(),
                'ticket_id' => $id,
            ]);

            throw $e;
        }
    }

    /**
     * إضافة رد على تذكرة
     */
    public function addReply(int $ticketId, CreateReplyDTO $dto, User $user): array
    {
        $isSuperUser = $this->isSuperUser($user);

        // الحصول على التذكرة
        if ($isSuperUser) {
            $ticket = $this->ticketRepository->findTicketById($ticketId);
        } else {
            $ticket = $this->ticketRepository->findTicketForUser($ticketId, $user->user_id);
        }

        if (!$ticket) {
            return [
                'success' => false,
                'message' => 'التذكرة غير موجودة أو لا تملك صلاحية الرد عليها',
                'message_en' => 'Ticket not found or access denied',
            ];
        }

        // التحقق أن التذكرة مفتوحة (status = 1) - لا يسمح بالرد إلا على تذكرة مفتوحة صراحةً
        if (!$ticket->isOpen()) {
            return [
                'success' => false,
                'message' => 'لا يمكن الرد على تذكرة مغلقة',
                'message_en' => 'Cannot reply to a closed ticket',
            ];
        }

        try {
            return DB::transaction(function () use ($ticketId, $dto, $ticket) {
                $reply = $this->ticketRepository->addReply($dto);

                return [
                    'success' => true,
                    'message' => 'تم إضافة الرد بنجاح',
                    'message_en' => 'Reply added successfully',
                    'data' => $this->formatReplyResponse($reply),
                ];
            });
        } catch (\Exception $e) {
            Log::error('Error adding reply', [
                'error' => $e->getMessage(),
                'ticket_id' => $ticketId,
            ]);

            throw $e;
        }
    }

    /**
     * الحصول على ردود تذكرة
     */
    public function getTicketReplies(int $ticketId, User $user): array
    {
        $isSuperUser = $this->isSuperUser($user);

        // الحصول على التذكرة للتحقق من الصلاحية
        if ($isSuperUser) {
            $ticket = $this->ticketRepository->findTicketById($ticketId);
        } else {
            $ticket = $this->ticketRepository->findTicketForUser($ticketId, $user->user_id);
        }

        if (!$ticket) {
            return [
                'success' => false,
                'message' => 'التذكرة غير موجودة أو لا تملك صلاحية الوصول إليها',
                'message_en' => 'Ticket not found or access denied',
            ];
        }

        $replies = $this->ticketRepository->getTicketReplies($ticketId);

        $formattedReplies = $replies->map(function ($reply) {
            return $this->formatReplyResponse($reply);
        })->toArray();

        return [
            'success' => true,
            'data' => $formattedReplies,
            'ticket_status' => $ticket->ticket_status,
            'ticket_status_text' => $ticket->status_text,
            'can_reply' => $ticket->isOpen(),
        ];
    }

    /**
     * حذف تذكرة
     * super_user: يمكنه حذف أي تذكرة
     * company/staff: يمكنهم حذف تذاكرهم فقط
     */
    public function deleteTicket(int $id, User $user): array
    {
        $isSuperUser = $this->isSuperUser($user);

        // الحصول على التذكرة
        if ($isSuperUser) {
            $ticket = $this->ticketRepository->findTicketById($id);
        } else {
            $ticket = $this->ticketRepository->findTicketForUser($id, $user->user_id);
        }

        if (!$ticket) {
            return [
                'success' => false,
                'message' => 'التذكرة غير موجودة أو لا تملك صلاحية حذفها',
                'message_en' => 'Ticket not found or access denied',
            ];
        }

        try {
            return DB::transaction(function () use ($id, $ticket) {
                $ticketCode = $ticket->ticket_code;
                $ticketId = $ticket->ticket_id;

                $this->ticketRepository->deleteTicket($ticket);

                return [
                    'success' => true,
                    'message' => 'تم حذف التذكرة بنجاح',
                    'message_en' => 'Ticket deleted successfully',
                    'data' => [
                        'ticket_id' => $ticketId,
                        'ticket_code' => $ticketCode,
                    ],
                ];
            });
        } catch (\Exception $e) {
            Log::error('Error deleting ticket', [
                'error' => $e->getMessage(),
                'ticket_id' => $id,
            ]);

            throw $e;
        }
    }

    /**
     * الحصول على الـ Enums
     */
    public function getEnums(): array
    {
        return [
            'categories' => TicketCategoryEnum::toArray(),
            'statuses' => TicketStatusEnum::toArray(),
            'priorities' => TicketPriorityEnum::toArray(),
        ];
    }

    /**
     * تنسيق استجابة التذكرة
     */
    protected function formatTicketResponse(SupportTicket $ticket, bool $includeReplies = false): array
    {
        $response = [
            'ticket_id' => $ticket->ticket_id,
            'ticket_code' => $ticket->ticket_code,
            'company_id' => $ticket->company_id,
            'subject' => $ticket->subject,
            'description' => $ticket->description,
            'category_id' => $ticket->category_id,
            'category_text' => $ticket->category_text,
            'category_text_en' => $ticket->category_text_en,
            'ticket_priority' => $ticket->ticket_priority,
            'priority_text' => $ticket->priority_text,
            'priority_text_en' => $ticket->priority_text_en,
            'priority_color' => $ticket->priority_color,
            'ticket_status' => $ticket->ticket_status,
            'status_text' => $ticket->status_text,
            'status_text_en' => $ticket->status_text_en,
            'is_open' => $ticket->isOpen(),
            'ticket_remarks' => $ticket->ticket_remarks,
            'created_by' => $ticket->created_by,
            'created_by_name' => $ticket->createdBy?->full_name ?? 'غير معروف',
            'created_by_type' => $ticket->createdBy?->user_type ?? null,
            'created_at' => $ticket->created_at,
            'replies_count' => $ticket->replies_count,
        ];

        if ($includeReplies) {
            $response['replies'] = $ticket->replies->map(function ($reply) {
                return $this->formatReplyResponse($reply);
            })->toArray();
        }

        return $response;
    }

    /**
     * تنسيق استجابة الرد
     */
    protected function formatReplyResponse(TicketReply $reply): array
    {
        return [
            'ticket_reply_id' => $reply->ticket_reply_id,
            'ticket_id' => $reply->ticket_id,
            'sent_by' => $reply->sent_by,
            'sender_name' => $reply->sender_name,
            'sender_type' => $reply->sender?->user_type ?? null,
            'is_super_user' => $reply->isSenderSuperUser(),
            'assign_to' => $reply->assign_to,
            'assignee_name' => $reply->assignee_name,
            'reply_text' => $reply->reply_text,
            'created_at' => $reply->created_at,
            'formatted_created_at' => $reply->formatted_created_at,
            'time_ago' => $reply->time_ago,
        ];
    }
}
