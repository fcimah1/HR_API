<?php

declare(strict_types=1);

namespace App\Repository\Interface;

use App\DTOs\SupportTicket\CreateReplyDTO;
use App\DTOs\SupportTicket\CreateTicketDTO;
use App\DTOs\SupportTicket\TicketFilterDTO;
use App\DTOs\SupportTicket\UpdateTicketDTO;
use App\Models\SupportTicket;
use App\Models\TicketReply;
use App\Models\User;
use Illuminate\Support\Collection;

interface SupportTicketRepositoryInterface
{
    /**
     * الحصول على قائمة التذاكر مع التصفية والترقيم الصفحي
     */
    public function getPaginatedTickets(TicketFilterDTO $filters, User $user): array;

    /**
     * الحصول على تذكرة بواسطة المعرف
     */
    public function findTicketById(int $id, ?int $companyId = null): ?SupportTicket;

    /**
     * الحصول على تذكرة للمستخدم (المنشئ)
     */
    public function findTicketForUser(int $id, int $userId): ?SupportTicket;

    /**
     * إنشاء تذكرة جديدة
     */
    public function createTicket(CreateTicketDTO $dto): SupportTicket;

    /**
     * تحديث تذكرة
     */
    public function updateTicket(SupportTicket $ticket, UpdateTicketDTO $dto): SupportTicket;

    /**
     * إغلاق تذكرة
     */
    public function closeTicket(SupportTicket $ticket, int $closedBy, ?string $remarks = null): SupportTicket;

    /**
     * إعادة فتح تذكرة
     */
    public function reopenTicket(SupportTicket $ticket, int $reopenedBy): SupportTicket;

    /**
     * إضافة رد على تذكرة
     */
    public function addReply(CreateReplyDTO $dto): TicketReply;

    /**
     * الحصول على ردود تذكرة
     */
    public function getTicketReplies(int $ticketId): Collection;

    /**
     * حذف تذكرة
     */
    public function deleteTicket(SupportTicket $ticket): bool;
}
