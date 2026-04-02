<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\InternalHelpdesk\CloseInternalTicketDTO;
use App\DTOs\InternalHelpdesk\CreateInternalReplyDTO;
use App\DTOs\InternalHelpdesk\CreateInternalTicketDTO;
use App\DTOs\InternalHelpdesk\InternalTicketFilterDTO;
use App\DTOs\InternalHelpdesk\UpdateInternalTicketDTO;
use App\Enums\TicketPriorityEnum;
use App\Enums\TicketStatusEnum;
use App\Http\Resources\InternalTicketReplyResource;
use App\Http\Resources\InternalTicketResource;
use App\Models\InternalSupportTicket;
use App\Models\User;
use App\Repository\Interface\InternalHelpdeskRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InternalHelpdeskService
{
    public function __construct(
        protected InternalHelpdeskRepositoryInterface $ticketRepository,
        protected SimplePermissionService $permissionService,
    ) {}

    /**
     * التحقق إذا كان المستخدم صاحب الشركة
     */
    public function isCompanyOwner(User $user): bool
    {
        return $this->permissionService->isCompanyOwner($user);
    }

    /**
     * التحقق من صلاحية الوصول للتذكرة بناءً على المستوى الوظيفي
     * - صاحب الشركة يرى الكل
     * - الموظف يرى: تذاكره + تذاكر موظفيه التابعين (سواء أنشأوها أو معينة لهم)
     */
    public function canAccessTicket(InternalSupportTicket $ticket, User $user): bool
    {
        $companyId = $this->permissionService->getEffectiveCompanyId($user);

        // التحقق من نفس الشركة
        if ($ticket->company_id !== $companyId) {
            return false;
        }

        // صاحب الشركة يرى الكل
        if ($this->isCompanyOwner($user)) {
            return true;
        }

        // التذكرة الشخصية (أنشأها أو معينة له)
        if ($ticket->created_by === $user->user_id || $ticket->employee_id === $user->user_id) {
            return true;
        }

        // فحص المستوى الوظيفي لمنشئ التذكرة
        $ticketCreator = User::find($ticket->created_by);
        if ($ticketCreator && $this->permissionService->canViewEmployeeRequests($user, $ticketCreator)) {
            return true;
        }

        // فحص المستوى الوظيفي للموظف المعين
        if ($ticket->employee_id) {
            $assignedEmployee = User::find($ticket->employee_id);
            if ($assignedEmployee && $this->permissionService->canViewEmployeeRequests($user, $assignedEmployee)) {
                return true;
            }
        }

        return false;
    }

    /**
     * الحصول على قائمة التذاكر
     */
    public function getPaginatedTickets(InternalTicketFilterDTO $filters): array
    {
        $result = $this->ticketRepository->getPaginatedTickets($filters);

        return [
            'success' => true,
            'data' => InternalTicketResource::collection($result['data']),
            'pagination' => $result['pagination'],
        ];
    }

    /**
     * الحصول على تذكرة بالمعرف مع فحص المستوى الوظيفي
     */
    public function getTicketById(int $id, User $user): array
    {
        $companyId = $this->permissionService->getEffectiveCompanyId($user);

        // جلب التذكرة أولاً
        $ticket = $this->ticketRepository->findTicketById($id, $companyId);

        if (!$ticket) {
            return [
                'success' => false,
                'message' => 'التذكرة غير موجودة',
                'message_en' => 'Ticket not found',
            ];
        }

        // فحص الصلاحية باستخدام المستوى الوظيفي
        if (!$this->canAccessTicket($ticket, $user)) {
            return [
                'success' => false,
                'message' => 'لا تملك صلاحية عرض هذه التذكرة',
                'message_en' => 'Access denied',
            ];
        }

        return [
            'success' => true,
            'data' => new InternalTicketResource($ticket),
        ];
    }

    /**
     * إنشاء تذكرة جديدة
     */
    public function createTicket(CreateInternalTicketDTO $dto): array
    {
        try {
            return DB::transaction(function () use ($dto) {
                $ticket = $this->ticketRepository->createTicket($dto);

                return [
                    'success' => true,
                    'message' => 'تم إنشاء التذكرة بنجاح',
                    'message_en' => 'Ticket created successfully',
                    'data' => new InternalTicketResource($ticket),
                ];
            });
        } catch (\Exception $e) {
            Log::error('Error creating internal ticket', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * تحديث تذكرة مع فحص المستوى الوظيفي
     */
    public function updateTicket(int $id, UpdateInternalTicketDTO $dto, User $user): array
    {
        $companyId = $this->permissionService->getEffectiveCompanyId($user);

        $ticket = $this->ticketRepository->findTicketById($id, $companyId);

        if (!$ticket) {
            return [
                'success' => false,
                'message' => 'التذكرة غير موجودة',
                'message_en' => 'Ticket not found',
            ];
        }

        if (!$this->canAccessTicket($ticket, $user)) {
            return [
                'success' => false,
                'message' => 'لا تملك صلاحية تعديل هذه التذكرة',
                'message_en' => 'Access denied',
            ];
        }

        // التحقق أن التذكرة مفتوحة
        if (!$ticket->isOpen()) {
            return [
                'success' => false,
                'message' => 'لا يمكن تعديل تذكرة مغلقة',
                'message_en' => 'Cannot update a closed ticket',
            ];
        }

        try {
            return DB::transaction(function () use ($ticket, $dto, $id) {
                $updatedTicket = $this->ticketRepository->updateTicket($ticket, $dto);

                return [
                    'success' => true,
                    'message' => 'تم تحديث التذكرة بنجاح',
                    'message_en' => 'Ticket updated successfully',
                    'data' => new InternalTicketResource($updatedTicket),
                ];
            });
        } catch (\Exception $e) {
            Log::error('Error updating internal ticket', [
                'error' => $e->getMessage(),
                'ticket_id' => $id,
            ]);

            throw $e;
        }
    }

    /**
     * الغلق - معطل حالياً
     */
    public function deleteTicket(int $id, User $user): array
    {
        $companyId = $this->permissionService->getEffectiveCompanyId($user);
        $ticket = $this->ticketRepository->findTicketById($id, $companyId);

        if (!$ticket) {
            return [
                'success' => false,
                'message' => 'التذكرة غير موجودة',
                'message_en' => 'Ticket not found',
            ];
        }

        if (!$this->canAccessTicket($ticket, $user)) {
            return [
                'success' => false,
                'message' => 'لا تملك صلاحية غلق هذه التذكرة',
                'message_en' => 'You do not have permission to close this ticket',
            ];
        }

        if (!$ticket->isOpen()) {
            return [
                'success' => false,
                'message' => 'لا يمكن غلق تذكرة مغلقة',
                'message_en' => 'Cannot close a closed ticket',
            ];
        }

        try {
            return DB::transaction(function () use ($ticket, $id) {
                $updatedTicket = $this->ticketRepository->closeTicket($ticket);

                return [
                    'success' => true,
                    'message' => 'تم غلق التذكرة بنجاح',
                    'message_en' => 'Ticket closed successfully',
                    'data' => new InternalTicketResource($updatedTicket),
                ];
            });
        } catch (\Exception $e) {
            Log::error('Error closing internal ticket', [
                'error' => $e->getMessage(),
                'ticket_id' => $id,
            ]);

            throw $e;
        }
    }

    /**
     * إغلاق تذكرة - للموظفين التابعين فقط (ليس التذكرة الشخصية)
     */
    public function closeTicket(int $id, CloseInternalTicketDTO $dto, User $user): array
    {
        $companyId = $this->permissionService->getEffectiveCompanyId($user);
        $ticket = $this->ticketRepository->findTicketById($id, $companyId);

        if (!$ticket) {
            return [
                'success' => false,
                'message' => 'التذكرة غير موجودة',
                'message_en' => 'Ticket not found',
            ];
        }

        // صاحب الشركة يغلق أي تذكرة
        if (!$this->isCompanyOwner($user)) {
            // الموظف لا يمكنه إغلاق تذكرته الشخصية
            if ($ticket->created_by === $user->user_id || $ticket->employee_id === $user->user_id) {
                return [
                    'success' => false,
                    'message' => 'لا تملك صلاحية إغلاق هذه التذكرة',
                    'message_en' => 'You do not have the authority to close this ticket',
                ];
            }

            // يجب أن يكون له صلاحية الوصول (تذاكر subordinates)
            if (!$this->canAccessTicket($ticket, $user)) {
                return [
                    'success' => false,
                    'message' => 'لا تملك صلاحية إغلاق هذه التذكرة',
                    'message_en' => 'You do not have the authority to close this ticket',
                ];
            }
        }

        if (!$ticket->isOpen()) {
            return [
                'success' => false,
                'message' => 'التذكرة مغلقة بالفعل',
                'message_en' => 'Ticket is already closed',
            ];
        }

        try {
            return DB::transaction(function () use ($ticket, $dto, $id) {
                $closedTicket = $this->ticketRepository->closeTicket($ticket, $dto->ticketRemarks);

                return [
                    'success' => true,
                    'message' => 'تم إغلاق التذكرة بنجاح',
                    'message_en' => 'Ticket closed successfully',
                    'data' => new InternalTicketResource($closedTicket),
                ];
            });
        } catch (\Exception $e) {
            Log::error('Error closing internal ticket', [
                'error' => $e->getMessage(),
                'ticket_id' => $id,
            ]);

            throw $e;
        }
    }

    /**
     * إعادة فتح تذكرة - للموظفين التابعين فقط (ليس التذكرة الشخصية)
     */
    public function reopenTicket(int $id, User $user): array
    {
        $companyId = $this->permissionService->getEffectiveCompanyId($user);
        $ticket = $this->ticketRepository->findTicketById($id, $companyId);

        if (!$ticket) {
            return [
                'success' => false,
                'message' => 'التذكرة غير موجودة',
                'message_en' => 'Ticket not found',
            ];
        }

        // صاحب الشركة يفتح أي تذكرة
        if (!$this->isCompanyOwner($user)) {
            // الموظف لا يمكنه إعادة فتح تذكرته الشخصية
            if ($ticket->created_by === $user->user_id || $ticket->employee_id === $user->user_id) {
                return [
                    'success' => false,
                    'message' => 'لا تملك صلاحية إعادة فتح هذه التذكرة',
                    'message_en' => 'You do not have the authority to reopen this ticket',
                ];
            }

            // يجب أن يكون له صلاحية الوصول (تذاكر subordinates)
            if (!$this->canAccessTicket($ticket, $user)) {
                return [
                    'success' => false,
                    'message' => 'لا تملك صلاحية إعادة فتح هذه التذكرة',
                    'message_en' => 'Access denied',
                ];
            }
        }

        if ($ticket->isOpen()) {
            return [
                'success' => false,
                'message' => 'التذكرة مفتوحة بالفعل',
                'message_en' => 'Ticket is already open',
            ];
        }

        try {
            return DB::transaction(function () use ($ticket, $id) {
                $reopenedTicket = $this->ticketRepository->reopenTicket($ticket);

                return [
                    'success' => true,
                    'message' => 'تم إعادة فتح التذكرة بنجاح',
                    'message_en' => 'Ticket reopened successfully',
                    'data' => new InternalTicketResource($reopenedTicket),
                ];
            });
        } catch (\Exception $e) {
            Log::error('Error reopening internal ticket', [
                'error' => $e->getMessage(),
                'ticket_id' => $id,
            ]);

            throw $e;
        }
    }

    /**
     * إضافة رد على تذكرة مع فحص المستوى الوظيفي
     */
    public function addReply(int $ticketId, CreateInternalReplyDTO $dto, User $user): array
    {
        $companyId = $this->permissionService->getEffectiveCompanyId($user);

        $ticket = $this->ticketRepository->findTicketById($ticketId, $companyId);

        if (!$ticket) {
            return [
                'success' => false,
                'message' => 'التذكرة غير موجودة',
                'message_en' => 'Ticket not found',
            ];
        }

        if (!$this->canAccessTicket($ticket, $user)) {
            return [
                'success' => false,
                'message' => 'لا تملك صلاحية الرد على هذه التذكرة',
                'message_en' => 'Access denied',
            ];
        }

        // التحقق أن التذكرة مفتوحة
        if (!$ticket->isOpen()) {
            return [
                'success' => false,
                'message' => 'لا يمكن الرد على تذكرة مغلقة',
                'message_en' => 'Cannot reply to a closed ticket',
            ];
        }

        try {
            return DB::transaction(function () use ($dto, $ticketId) {
                $reply = $this->ticketRepository->addReply($dto);

                return [
                    'success' => true,
                    'message' => 'تم إضافة الرد بنجاح',
                    'message_en' => 'Reply added successfully',
                    'data' => new InternalTicketReplyResource($reply),
                ];
            });
        } catch (\Exception $e) {
            Log::error('Error adding internal ticket reply', [
                'error' => $e->getMessage(),
                'ticket_id' => $ticketId,
            ]);

            throw $e;
        }
    }

    /**
     * الحصول على ردود تذكرة مع فحص المستوى الوظيفي
     */
    public function getTicketReplies(int $ticketId, User $user): array
    {
        $companyId = $this->permissionService->getEffectiveCompanyId($user);

        $ticket = $this->ticketRepository->findTicketById($ticketId, $companyId);

        if (!$ticket) {
            return [
                'success' => false,
                'message' => 'التذكرة غير موجودة',
                'message_en' => 'Ticket not found',
            ];
        }

        if (!$this->canAccessTicket($ticket, $user)) {
            return [
                'success' => false,
                'message' => 'لا تملك صلاحية عرض ردود هذه التذكرة',
                'message_en' => 'Access denied',
            ];
        }

        $replies = $this->ticketRepository->getTicketReplies($ticketId);

        return [
            'success' => true,
            'data' => InternalTicketReplyResource::collection($replies),
            'ticket_status' => $ticket->ticket_status,
            'can_reply' => $ticket->isOpen(),
        ];
    }

    /**
     * الحصول على الأقسام - مفلترة حسب المستوى الوظيفي
     * Company: كل الأقسام
     * Staff: الأقسام التي بها subordinates فقط
     */
    public function getDepartments(User $user): array
    {
        $companyId = $this->permissionService->getEffectiveCompanyId($user);

        if ($this->isCompanyOwner($user)) {
            // Company يرى كل الأقسام
            $departments = $this->ticketRepository->getDepartments($companyId);
        } else {
            // Staff يرى الأقسام التي بها subordinates فقط
            $subordinates = $this->permissionService->getEmployeesByHierarchy($user->user_id, $companyId, true);
            $departmentIds = array_unique(array_column($subordinates, 'department_id'));

            // إضافة قسم المستخدم نفسه
            $userDeptId = $user->user_details?->department_id;
            if ($userDeptId && !in_array($userDeptId, $departmentIds)) {
                $departmentIds[] = $userDeptId;
            }

            $departments = $this->ticketRepository->getDepartmentsByIds($companyId, $departmentIds);
        }

        return [
            'success' => true,
            'data' => $departments,
        ];
    }

    /**
     * الحصول على موظفي قسم - مفلترة حسب المستوى الوظيفي
     * Company: كل الموظفين
     * Staff: subordinates فقط + نفسه
     */
    public function getEmployeesByDepartment(int $departmentId, User $user): array
    {
        $companyId = $this->permissionService->getEffectiveCompanyId($user);

        if ($this->isCompanyOwner($user)) {
            // Company يرى كل الموظفين
            $employees = $this->ticketRepository->getEmployeesByDepartment($companyId, $departmentId);
        } else {
            // Staff يرى subordinates فقط + نفسه
            $subordinates = $this->permissionService->getEmployeesByHierarchy($user->user_id, $companyId, true);
            $allowedIds = array_column($subordinates, 'user_id');

            // إضافة المستخدم نفسه
            if (!in_array($user->user_id, $allowedIds)) {
                $allowedIds[] = $user->user_id;
            }

            $employees = $this->ticketRepository->getEmployeesByDepartmentFiltered($companyId, $departmentId, $allowedIds);
        }

        return [
            'success' => true,
            'data' => $employees->map(function ($emp) {
                return [
                    'user_id' => $emp->user_id,
                    'name' => trim($emp->first_name . ' ' . $emp->last_name),
                ];
            }),
        ];
    }

    /**
     * الحصول على الـ Enums
     */
    public function getEnums(): array
    {
        return [
            'statuses' => TicketStatusEnum::toArray(),
            'priorities' => TicketPriorityEnum::toArray(),
        ];
    }
}
