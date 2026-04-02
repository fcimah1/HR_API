<?php

declare(strict_types=1);

namespace App\Repository;

use App\DTOs\SupportTicket\CreateReplyDTO;
use App\DTOs\SupportTicket\CreateTicketDTO;
use App\DTOs\SupportTicket\TicketFilterDTO;
use App\DTOs\SupportTicket\UpdateTicketDTO;
use App\Models\SupportTicket;
use App\Models\TicketReply;
use App\Models\User;
use App\Repository\Interface\SupportTicketRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class SupportTicketRepository implements SupportTicketRepositoryInterface
{
    /**
     * الحصول على قائمة التذاكر مع التصفية والترقيم الصفحي
     */
    public function getPaginatedTickets(TicketFilterDTO $filters, User $user): array
    {
        $query = SupportTicket::with(['createdBy', 'replies.sender']);

        // Super User يرى كل التذاكر
        if ($filters->isSuperUser) {
            // لا يتم تطبيق فلتر الشركة أو المنشئ
            Log::info('Super user accessing all tickets');
        } else {
            // المستخدم العادي يرى تذاكره فقط (بناءً على created_by)
            if ($filters->createdBy !== null) {
                $query->where('created_by', $filters->createdBy);
            }

            // فلتر الشركة للمستخدم العادي
            // if ($filters->companyId !== null) {
            //     $query->where('company_id', $filters->companyId);
            // }
        }

        // تطبيق فلتر الحالة
        if ($filters->status !== null) {
            $query->where('ticket_status', $filters->status);
        }

        // تطبيق فلتر الفئة
        if ($filters->categoryId !== null) {
            $query->where('category_id', $filters->categoryId);
        }

        // تطبيق فلتر الأولوية
        if ($filters->priority !== null) {
            $query->where('ticket_priority', $filters->priority);
        }

        // تطبيق البحث
        if ($filters->search !== null && trim($filters->search) !== '') {
            $searchTerm = '%' . $filters->search . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('subject', 'like', $searchTerm)
                    ->orWhere('description', 'like', $searchTerm)
                    ->orWhere('ticket_code', 'like', $searchTerm)
                    ->orWhereHas('createdBy', function ($subQuery) use ($searchTerm) {
                        $subQuery->where('first_name', 'like', $searchTerm)
                            ->orWhere('last_name', 'like', $searchTerm)
                            ->orWhere('username', 'like', $searchTerm);
                    });
            });
        }

        // تطبيق فلتر التاريخ
        if ($filters->fromDate !== null && $filters->toDate !== null) {
            $query->whereBetween('created_at', [$filters->fromDate . ' 00:00:00', $filters->toDate . ' 23:59:59']);
        } elseif ($filters->fromDate !== null) {
            $query->where('created_at', '>=', $filters->fromDate . ' 00:00:00');
        } elseif ($filters->toDate !== null) {
            $query->where('created_at', '<=', $filters->toDate . ' 23:59:59');
        }

        // ترتيب حسب التاريخ تنازليًا
        $query->orderBy('created_at', 'desc');

        // استخدام paginate
        $paginator = $query->paginate($filters->perPage, ['*'], 'page', $filters->page);

        return [
            'data' => $paginator->items(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    /**
     * الحصول على تذكرة بواسطة المعرف
     */
    public function findTicketById(int $id, ?int $companyId = null): ?SupportTicket
    {
        $query = SupportTicket::with(['createdBy', 'replies.sender'])
            ->where('ticket_id', $id);

        if ($companyId !== null) {
            $query->where('company_id', $companyId);
        }

        return $query->first();
    }

    /**
     * الحصول على تذكرة للمستخدم (المنشئ)
     */
    public function findTicketForUser(int $id, int $userId): ?SupportTicket
    {
        return SupportTicket::with(['createdBy', 'replies.sender'])
            ->where('ticket_id', $id)
            ->where('created_by', $userId)
            ->first();
    }

    /**
     * إنشاء تذكرة جديدة
     */
    public function createTicket(CreateTicketDTO $dto): SupportTicket
    {
        $ticket = SupportTicket::create($dto->toArray());
        $ticket->load(['createdBy', 'replies.sender']);

        Log::info('Ticket created', [
            'ticket_id' => $ticket->ticket_id,
            'ticket_code' => $ticket->ticket_code,
            'created_by' => $ticket->created_by,
        ]);

        return $ticket;
    }

    /**
     * تحديث تذكرة
     */
    public function updateTicket(SupportTicket $ticket, UpdateTicketDTO $dto): SupportTicket
    {
        $updateData = $dto->toArray();

        if (!empty($updateData)) {
            $ticket->update($updateData);
        }

        $ticket->refresh();
        $ticket->load(['createdBy', 'replies.sender']);

        Log::info('Ticket updated', [
            'ticket_id' => $ticket->ticket_id,
            'updated_fields' => array_keys($updateData),
        ]);

        return $ticket;
    }

    /**
     * إغلاق تذكرة
     */
    public function closeTicket(SupportTicket $ticket, int $closedBy, ?string $remarks = null): SupportTicket
    {
        $updateData = [
            'ticket_status' => SupportTicket::STATUS_CLOSED,
        ];

        if ($remarks !== null) {
            $existingRemarks = $ticket->ticket_remarks ?? '';
            $updateData['ticket_remarks'] = trim($existingRemarks . "\n" . "تم الإغلاق بواسطة " . User::getFullNameById($closedBy) . " بتاريخ " . now()->format('Y-m-d H:i:s') . ($remarks ? " | " . $remarks : ""));
        }

        $ticket->update($updateData);
        $ticket->refresh();
        $ticket->load(['createdBy', 'replies.sender']);

        Log::info('Ticket closed', [
            'ticket_id' => $ticket->ticket_id,
            'closed_by' => $closedBy,
        ]);

        return $ticket;
    }

    /**
     * إعادة فتح تذكرة
     */
    public function reopenTicket(SupportTicket $ticket, int $reopenedBy): SupportTicket
    {
        $existingRemarks = $ticket->ticket_remarks ?? '';
        $ticket->update([
            'ticket_status' => SupportTicket::STATUS_OPEN,
            'ticket_remarks' => trim($existingRemarks . "\n" . "تم إعادة الفتح بواسطة " . User::getFullNameById($reopenedBy) . " بتاريخ " . now()->format('Y-m-d H:i:s')),
        ]);

        $ticket->refresh();
        $ticket->load(['createdBy', 'replies.sender']);

        Log::info('Ticket reopened', [
            'ticket_id' => $ticket->ticket_id,
            'reopened_by' => $reopenedBy,
        ]);

        return $ticket;
    }

    /**
     * إضافة رد على تذكرة
     */
    public function addReply(CreateReplyDTO $dto): TicketReply
    {
        $reply = TicketReply::create($dto->toArray());
        $reply->load(['sender', 'assignee']);

        Log::info('Reply added to ticket', [
            'ticket_id' => $dto->ticketId,
            'reply_id' => $reply->ticket_reply_id,
            'sent_by' => $dto->sentBy,
        ]);

        return $reply;
    }

    /**
     * الحصول على ردود تذكرة
     */
    public function getTicketReplies(int $ticketId): Collection
    {
        return TicketReply::with(['sender', 'assignee'])
            ->where('ticket_id', $ticketId)
            ->orderBy('created_at', 'asc')
            ->get();
    }

    /**
     * حذف تذكرة (مع ردودها)
     */
    public function deleteTicket(SupportTicket $ticket): bool
    {
        // حذف الردود أولاً
        // TicketReply::where('ticket_id', $ticket->ticket_id)->delete();

        // غلق التذكرة
        $deleted = $ticket->update([
            'ticket_status' => SupportTicket::STATUS_CLOSED,
        ]);

        Log::info('Ticket closed', [
            'ticket_id' => $ticket->ticket_id,
            'ticket_code' => $ticket->ticket_code,
        ]);

        return $deleted;
    }
}
