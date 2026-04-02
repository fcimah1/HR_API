<?php

declare(strict_types=1);

namespace App\Repository\Interface;

use App\DTOs\InternalHelpdesk\CreateInternalReplyDTO;
use App\DTOs\InternalHelpdesk\CreateInternalTicketDTO;
use App\DTOs\InternalHelpdesk\InternalTicketFilterDTO;
use App\DTOs\InternalHelpdesk\UpdateInternalTicketDTO;
use App\Models\InternalSupportTicket;
use App\Models\InternalTicketReply;
use Illuminate\Support\Collection;

interface InternalHelpdeskRepositoryInterface
{
    /**
     * الحصول على قائمة التذاكر مع التصفية والترقيم
     */
    public function getPaginatedTickets(InternalTicketFilterDTO $filters): array;

    /**
     * الحصول على تذكرة بواسطة المعرف
     */
    public function findTicketById(int $id, int $companyId): ?InternalSupportTicket;

    /**
     * الحصول على تذكرة للمستخدم (المنشئ)
     */
    public function findTicketForUser(int $id, int $userId, int $companyId): ?InternalSupportTicket;

    /**
     * إنشاء تذكرة جديدة
     */
    public function createTicket(CreateInternalTicketDTO $dto): InternalSupportTicket;

    /**
     * تحديث تذكرة
     */
    public function updateTicket(InternalSupportTicket $ticket, UpdateInternalTicketDTO $dto): InternalSupportTicket;

    /**
     * حذف تذكرة
     */
    public function deleteTicket(InternalSupportTicket $ticket): bool;

    /**
     * إغلاق تذكرة
     */
    public function closeTicket(InternalSupportTicket $ticket, ?string $remarks = null): InternalSupportTicket;

    /**
     * إعادة فتح تذكرة
     */
    public function reopenTicket(InternalSupportTicket $ticket): InternalSupportTicket;

    /**
     * إضافة رد على تذكرة
     */
    public function addReply(CreateInternalReplyDTO $dto): InternalTicketReply;

    /**
     * الحصول على ردود تذكرة
     */
    public function getTicketReplies(int $ticketId): Collection;

    /**
     * الحصول على الأقسام في الشركة
     */
    public function getDepartments(int $companyId): Collection;

    /**
     * الحصول على موظفي قسم
     */
    public function getEmployeesByDepartment(int $companyId, int $departmentId): Collection;

    /**
     * الحصول على أقسام بمعرفاتها
     */
    public function getDepartmentsByIds(int $companyId, array $departmentIds): Collection;

    /**
     * الحصول على موظفي قسم - مفلترين حسب المستوى الوظيفي
     */
    public function getEmployeesByDepartmentFiltered(int $companyId, int $departmentId, array $allowedUserIds): Collection;
}
