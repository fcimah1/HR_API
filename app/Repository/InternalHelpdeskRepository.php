<?php

declare(strict_types=1);

namespace App\Repository;

use App\DTOs\InternalHelpdesk\CreateInternalReplyDTO;
use App\DTOs\InternalHelpdesk\CreateInternalTicketDTO;
use App\DTOs\InternalHelpdesk\InternalTicketFilterDTO;
use App\DTOs\InternalHelpdesk\UpdateInternalTicketDTO;
use App\Models\Department;
use App\Models\InternalSupportTicket;
use App\Models\InternalTicketReply;
use App\Models\User;
use App\Repository\Interface\InternalHelpdeskRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class InternalHelpdeskRepository implements InternalHelpdeskRepositoryInterface
{
    /**
     * الحصول على قائمة التذاكر مع التصفية والترقيم
     * يدعم المستويات الوظيفية: الموظف يرى تذاكره + تذاكر من بمستوى أدنى
     */
    public function getPaginatedTickets(InternalTicketFilterDTO $filters): array
    {
        $query = InternalSupportTicket::with(['creator', 'assignedEmployee', 'department'])
            ->where('company_id', $filters->companyId);

        // تصفية حسب المستوى الوظيفي
        // الموظف يرى: تذاكره + تذاكر موظفيه التابعين (سواء أنشأوها أو معينة لهم)
        if (!$filters->isCompanyOwner && $filters->allowedUserIds !== null) {
            $query->where(function ($q) use ($filters) {
                // التذاكر التي أنشأها الموظفون المسموح رؤيتهم
                $q->whereIn('created_by', $filters->allowedUserIds)
                    // أو التذاكر المعينة للموظفين التابعين
                    ->orWhereIn('employee_id', $filters->allowedUserIds);
            });
        }

        // فلتر الحالة
        if ($filters->status !== null) {
            $query->where('ticket_status', $filters->status);
        }

        // فلتر الأولوية
        if ($filters->priority !== null) {
            $query->where('ticket_priority', $filters->priority);
        }

        // فلتر القسم
        if ($filters->departmentId !== null) {
            $query->where('department_id', $filters->departmentId);
        }

        // فلتر الموظف المعين
        if ($filters->employeeId !== null) {
            $query->where('employee_id', $filters->employeeId);
        }

        // فلتر البحث
        if ($filters->search !== null) {
            $query->where(function ($q) use ($filters) {
                $q->where('subject', 'like', "%{$filters->search}%")
                    ->orWhere('description', 'like', "%{$filters->search}%")
                    ->orWhere('ticket_code', 'like', "%{$filters->search}%");
            });
        }

        // فلتر التاريخ
        if ($filters->fromDate !== null) {
            $query->whereDate('created_at', '>=', $filters->fromDate);
        }
        if ($filters->toDate !== null) {
            $query->whereDate('created_at', '<=', $filters->toDate);
        }

        $total = $query->count();
        $tickets = $query->orderBy('created_at', 'desc')
            ->skip(($filters->page - 1) * $filters->perPage)
            ->take($filters->perPage)
            ->get();

        return [
            'data' => $tickets,
            'pagination' => [
                'total' => $total,
                'page' => $filters->page,
                'per_page' => $filters->perPage,
                'total_pages' => (int)ceil($total / $filters->perPage),
            ],
        ];
    }

    /**
     * الحصول على تذكرة بواسطة المعرف
     */
    public function findTicketById(int $id, int $companyId): ?InternalSupportTicket
    {
        return InternalSupportTicket::with(['creator', 'assignedEmployee', 'department', 'replies'])
            ->where('ticket_id', $id)
            ->where('company_id', $companyId)
            ->first();
    }

    /**
     * الحصول على تذكرة للمستخدم (المنشئ)
     */
    public function findTicketForUser(int $id, int $userId, int $companyId): ?InternalSupportTicket
    {
        return InternalSupportTicket::with(['creator', 'assignedEmployee', 'department', 'replies'])
            ->where('ticket_id', $id)
            ->where('company_id', $companyId)
            ->where('created_by', $userId)
            ->first();
    }

    /**
     * إنشاء تذكرة جديدة
     */
    public function createTicket(CreateInternalTicketDTO $dto): InternalSupportTicket
    {
        $ticket = InternalSupportTicket::create($dto->toArray());

        Log::info('Internal ticket created', [
            'ticket_id' => $ticket->ticket_id,
            'ticket_code' => $ticket->ticket_code,
            'company_id' => $ticket->company_id,
            'created_by' => $ticket->created_by,
        ]);

        return $ticket->load(['creator', 'assignedEmployee', 'department']);
    }

    /**
     * تحديث تذكرة
     */
    public function updateTicket(InternalSupportTicket $ticket, UpdateInternalTicketDTO $dto): InternalSupportTicket
    {
        $ticket->update($dto->toArray());

        Log::info('Internal ticket updated', [
            'ticket_id' => $ticket->ticket_id,
        ]);

        return $ticket->fresh(['creator', 'assignedEmployee', 'department']);
    }

    /**
     * حذف تذكرة
     */
    public function deleteTicket(InternalSupportTicket $ticket): bool
    {
        // حذف الردود أولاً
        InternalTicketReply::where('ticket_id', $ticket->ticket_id)->delete();

        // حذف التذكرة
        $deleted = $ticket->delete();

        Log::info('Internal ticket deleted', [
            'ticket_id' => $ticket->ticket_id,
            'ticket_code' => $ticket->ticket_code,
        ]);

        return $deleted;
    }

    /**
     * إغلاق تذكرة
     */
    public function closeTicket(InternalSupportTicket $ticket, ?string $remarks = null): InternalSupportTicket
    {
        $updateData = ['ticket_status' => InternalSupportTicket::STATUS_CLOSED];

        if ($remarks !== null) {
            $updateData['ticket_remarks'] = $remarks;
        }

        $ticket->update($updateData);

        Log::info('Internal ticket closed', [
            'ticket_id' => $ticket->ticket_id,
        ]);

        return $ticket->fresh(['creator', 'assignedEmployee', 'department']);
    }

    /**
     * إعادة فتح تذكرة
     */
    public function reopenTicket(InternalSupportTicket $ticket): InternalSupportTicket
    {
        $ticket->update(['ticket_status' => InternalSupportTicket::STATUS_OPEN]);

        Log::info('Internal ticket reopened', [
            'ticket_id' => $ticket->ticket_id,
        ]);

        return $ticket->fresh(['creator', 'assignedEmployee', 'department']);
    }

    /**
     * إضافة رد على تذكرة
     */
    public function addReply(CreateInternalReplyDTO $dto): InternalTicketReply
    {
        $reply = InternalTicketReply::create($dto->toArray());

        Log::info('Internal ticket reply added', [
            'reply_id' => $reply->ticket_reply_id,
            'ticket_id' => $reply->ticket_id,
            'sent_by' => $reply->sent_by,
        ]);

        return $reply->load(['sender', 'assignee']);
    }

    /**
     * الحصول على ردود تذكرة
     */
    public function getTicketReplies(int $ticketId): Collection
    {
        return InternalTicketReply::with(['sender', 'assignee'])
            ->where('ticket_id', $ticketId)
            ->orderBy('created_at', 'asc')
            ->get();
    }

    /**
     * الحصول على الأقسام في الشركة
     */
    public function getDepartments(int $companyId): Collection
    {
        return Department::where('company_id', $companyId)
            ->orderBy('department_name', 'asc')
            ->get(['department_id', 'department_name']);
    }

    /**
     * الحصول على موظفي قسم
     */
    public function getEmployeesByDepartment(int $companyId, int $departmentId): Collection
    {
        return User::join('ci_erp_users_details', 'ci_erp_users.user_id', '=', 'ci_erp_users_details.user_id')
            ->where('ci_erp_users.company_id', $companyId)
            ->where('ci_erp_users_details.department_id', $departmentId)
            ->where('ci_erp_users.is_active', 1)
            ->select([
                'ci_erp_users.user_id',
                'ci_erp_users.first_name',
                'ci_erp_users.last_name',
            ])
            ->orderBy('ci_erp_users.first_name', 'asc')
            ->get();
    }

    /**
     * الحصول على أقسام بمعرفاتها
     */
    public function getDepartmentsByIds(int $companyId, array $departmentIds): Collection
    {
        if (empty($departmentIds)) {
            return collect([]);
        }

        return Department::where('company_id', $companyId)
            ->whereIn('department_id', $departmentIds)
            ->orderBy('department_name', 'asc')
            ->get(['department_id', 'department_name']);
    }

    /**
     * الحصول على موظفي قسم - مفلترين حسب المستوى الوظيفي
     */
    public function getEmployeesByDepartmentFiltered(int $companyId, int $departmentId, array $allowedUserIds): Collection
    {
        if (empty($allowedUserIds)) {
            return collect([]);
        }

        return User::join('ci_erp_users_details', 'ci_erp_users.user_id', '=', 'ci_erp_users_details.user_id')
            ->where('ci_erp_users.company_id', $companyId)
            ->where('ci_erp_users_details.department_id', $departmentId)
            ->whereIn('ci_erp_users.user_id', $allowedUserIds)
            ->where('ci_erp_users.is_active', 1)
            ->select([
                'ci_erp_users.user_id',
                'ci_erp_users.first_name',
                'ci_erp_users.last_name',
            ])
            ->orderBy('ci_erp_users.first_name', 'asc')
            ->get();
    }
}
