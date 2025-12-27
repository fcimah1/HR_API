<?php

namespace App\Services;

use App\DTOs\Suggestion\CreateSuggestionDTO;
use App\DTOs\Suggestion\CreateSuggestionCommentDTO;
use App\DTOs\Suggestion\SuggestionFilterDTO;
use App\DTOs\Suggestion\UpdateSuggestionDTO;
use App\Models\Suggestion;
use App\Models\SuggestionComment;
use App\Models\User;
use App\Repository\Interface\SuggestionRepositoryInterface;
use App\Services\SimplePermissionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SuggestionService
{
    public function __construct(
        protected SuggestionRepositoryInterface $suggestionRepository,
        protected SimplePermissionService $permissionService,
    ) {}

    /**
     * الحصول على قائمة الاقتراحات مع التصفية
     */
    public function getPaginatedSuggestions(SuggestionFilterDTO $filters, User $user): array
    {
        // إنشاء filters جديد بناءً على صلاحيات المستخدم
        $filterData = $filters->toArray();

        // التحقق من نوع المستخدم (company أو staff فقط)
        if ($user->user_type == 'company') {
            // مدير الشركة: يرى جميع اقتراحات شركته
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
            $filterData['company_id'] = $effectiveCompanyId;
        } else {
            // موظف (staff): يرى اقتراحاته + اقتراحات الموظفين التابعين له
            $subordinateIds = $this->getSubordinateEmployeeIds($user);

            if (!empty($subordinateIds)) {
                // لديه موظفين تابعين: اقتراحاته + اقتراحات التابعين
                $subordinateIds[] = $user->user_id;
                $filterData['employee_ids'] = $subordinateIds;
                $filterData['company_id'] = $user->company_id;
            } else {
                // ليس لديه موظفين تابعين: اقتراحاته فقط
                $filterData['employee_id'] = $user->user_id;
                $filterData['company_id'] = $user->company_id;
            }
        }

        // إنشاء DTO جديد مع البيانات المحدثة
        $updatedFilters = SuggestionFilterDTO::fromRequest($filterData);

        return $this->suggestionRepository->getPaginatedSuggestions($updatedFilters, $user);
    }

    /**
     * الحصول على جميع معرفات الموظفين التابعين
     */
    private function getSubordinateEmployeeIds(User $manager): array
    {
        // الحصول على جميع الموظفين في نفس الشركة
        $allEmployees = User::where('company_id', $manager->company_id)
            ->where('user_type', 'staff')
            ->get();

        $subordinateIds = [];

        foreach ($allEmployees as $employee) {
            // التحقق إذا كان المدير يمكنه عرض طلبات هذا الموظف
            if ($this->permissionService->canViewEmployeeRequests($manager, $employee)) {
                $subordinateIds[] = $employee->user_id;
            }
        }

        return $subordinateIds;
    }

    /**
     * الحصول على اقتراح بواسطة المعرف
     */
    public function getSuggestionById(int $id, ?int $companyId = null, ?int $userId = null, ?User $user = null): ?Suggestion
    {
        $user = $user ?? Auth::user();

        if (is_null($companyId) && is_null($userId)) {
            Log::warning('SuggestionService::getSuggestionById - Invalid arguments', [
                'id'=> $id,
                'message' => 'Invalid arguments'
            ]);
            throw new \InvalidArgumentException('يجب توفير معرف الشركة أو معرف المستخدم');
        }

        // البحث عن الاقتراح بواسطة معرف الشركة (للمستخدمين من نوع company/admins)
        if ($companyId !== null) {
            $suggestion = $this->suggestionRepository->findSuggestionById($id, $companyId);

            // Check hierarchy permissions for staff users
            if ($user && $user->user_type !== 'company' && $suggestion) {
                // Allow users to view their own suggestions
                if ($suggestion->added_by === $user->user_id) {
                    return $suggestion;
                }

                $employee = User::find($suggestion->added_by);
                if (!$employee || !$this->permissionService->canViewEmployeeRequests($user, $employee)) {
                    Log::warning('SuggestionService::getSuggestionById - Hierarchy permission denied', [
                        'suggestion_id' => $id,
                        'requester_id' => $user->user_id,
                        'added_by' => $suggestion->added_by,
                        'message' => 'Hierarchy permission denied'
                    ]);
                    return null;
                }
            }
            return $suggestion;
        }

        // البحث عن الاقتراح بواسطة معرف المستخدم (للموظفين العاديين)
        if ($userId !== null) {
            return $this->suggestionRepository->findSuggestionForEmployee($id, $userId);
        }

        return null;
    }

    /**
     * إنشاء اقتراح جديد
     */
    public function createSuggestion(CreateSuggestionDTO $dto): Suggestion
    {
        return DB::transaction(function () use ($dto) {

            $suggestion = $this->suggestionRepository->createSuggestion($dto);

            if (!$suggestion) {
                Log::warning('SuggestionService::createSuggestion - Failed to create suggestion', [
                    'added_by' => $dto->addedBy,
                    'title' => $dto->title,
                    'message' => 'Failed to create suggestion'
                ]);
                throw new \Exception('فشل في إنشاء الاقتراح');
            }

            return $suggestion;
        });
    }

    /**
     * تحديث اقتراح
     */
    public function updateSuggestion(int $id, UpdateSuggestionDTO $dto, User $user): Suggestion
    {
        return DB::transaction(function () use ($id, $dto, $user) {
            // الحصول على معرف الشركة الفعلي
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);

            // البحث عن الاقتراح
            $suggestion = $this->suggestionRepository->findSuggestionById($id, $effectiveCompanyId);

            if (!$suggestion) {

                Log::warning('SuggestionService::updateSuggestion - Suggestion not found', [
                    'suggestion_id' => $id,
                    'message' => 'Suggestion not found'
                ]);
                throw new \Exception('الاقتراح غير موجود');
            }

            // التحقق من صلاحية التعديل (المالك فقط أو مدير الشركة)
            $isOwner = $suggestion->added_by === $user->user_id;
            $isCompanyAdmin = $user->user_type === 'company';

            if (!$isOwner && !$isCompanyAdmin) {

                Log::warning('SuggestionService::updateSuggestion - Permission denied', [
                    'suggestion_id' => $id,
                    'message' => 'Permission denied'
                ]);
                throw new \Exception('ليس لديك صلاحية لتعديل هذا الاقتراح');
            }

            // تحديث الاقتراح
            $updatedSuggestion = $this->suggestionRepository->updateSuggestion($suggestion, $dto);

            Log::info('SuggestionService::updateSuggestion', [
                'suggestion_id' => $updatedSuggestion->suggestion_id,
                'updated_by' => $user->user_id,
            ]);

            return $updatedSuggestion;
        });
    }

    /**
     * حذف اقتراح
     */
    public function deleteSuggestion(int $id, User $user): bool
    {
        return DB::transaction(function () use ($id, $user) {
            // Get effective company ID
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);

            // Find suggestion
            $suggestion = $this->suggestionRepository->findSuggestionById($id, $effectiveCompanyId);

            if (!$suggestion) {
                Log::warning('SuggestionService::deleteSuggestion - Suggestion not found', [
                    'suggestion_id' => $id,
                    'message' => 'الاقتراح غير موجود',
                    'deleted_by' => $user->user_id,
                ]);
                throw new \Exception('الاقتراح غير موجود');
            }

            // Permission Check (no status check as suggestions don't have status)
            $isOwner = $suggestion->added_by === $user->user_id;
            $isCompany = $user->user_type === 'company';

            // Check hierarchy permission (is a manager of the employee)
            $isHierarchyManager = false;
            if (!$isOwner && !$isCompany) {
                $employee = User::find($suggestion->added_by);
                if ($employee && $this->permissionService->canViewEmployeeRequests($user, $employee)) {
                    $isHierarchyManager = true;
                }
            }

            if (!$isOwner && !$isCompany && !$isHierarchyManager) {
                Log::warning('SuggestionService::deleteSuggestion - Permission denied', [
                    'suggestion_id' => $id,
                    'message' => 'ليس لديك صلاحية لحذف هذا الاقتراح',
                    'deleted_by' => $user->user_id,
                ]);
                throw new \Exception('ليس لديك صلاحية لحذف هذا الاقتراح');
            }

            return $this->suggestionRepository->deleteSuggestion($suggestion);
        });
    }

    /**
     * إضافة تعليق على اقتراح
     */
    public function addComment(int $suggestionId, CreateSuggestionCommentDTO $dto, User $user): SuggestionComment
    {
        return DB::transaction(function () use ($suggestionId, $dto, $user) {
            // الحصول على معرف الشركة الفعلي
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);

            // التحقق من وجود الاقتراح
            $suggestion = $this->suggestionRepository->findSuggestionById($suggestionId, $effectiveCompanyId);

            if (!$suggestion) {
                Log::warning('SuggestionService::addComment - Suggestion not found', [
                    'suggestion_id' => $suggestionId,
                    'message' => 'الاقتراح غير موجود',
                    'employee_id' => $dto->employeeId,
                ]);
                throw new \Exception('الاقتراح غير موجود');
            }

            $comment = $this->suggestionRepository->addComment($dto);

            return $comment;
        });
    }

    /**
     * الحصول على تعليقات اقتراح
     */
    public function getComments(int $suggestionId, User $user): array
    {
        // الحصول على معرف الشركة الفعلي
        $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);

        // التحقق من وجود الاقتراح
        $suggestion = $this->suggestionRepository->findSuggestionById($suggestionId, $effectiveCompanyId);

        if (!$suggestion) {
            Log::warning('SuggestionService::getComments - Suggestion not found', [
                'suggestion_id' => $suggestionId,
                'message' => 'الاقتراح غير موجود',
                'employee_id' => $user->user_id,
            ]);
            throw new \Exception('الاقتراح غير موجود');
        }

        return $this->suggestionRepository->getComments($suggestionId);
    }

    /**
     * حذف تعليق من اقتراح
     */
    public function deleteComment(int $suggestionId, int $commentId, User $user): bool
    {
        // الحصول على معرف الشركة الفعلي
        $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);

        // البحث عن التعليق
        $comment = $this->suggestionRepository->findCommentById($commentId, $suggestionId, $effectiveCompanyId);

        if (!$comment) {
            Log::warning('SuggestionService::deleteComment - Comment not found', [
                'comment_id' => $commentId,
                'message' => 'التعليق غير موجود',
                'employee_id' => $user->user_id,
            ]);
            throw new \Exception('التعليق غير موجود');
        }

        //  التحقق من أن المستخدم هو صاحب التعليق أو مدير الشركة او المستوى الاعلى منه hierercly_level
        if ($comment->employee_id !== $user->user_id && $user->user_type !== 'company' && $user->hierarchy_level < $comment->employee->hierarchy_level) {
            Log::warning('SuggestionService::deleteComment - Permission denied', [
                'comment_id' => $commentId,
                'message' => 'غير مسموح بحذف هذا التعليق',
                'employee_id' => $user->user_id,
            ]);
            throw new \Exception('غير مسموح بحذف هذا التعليق', 403);
        }

        return $this->suggestionRepository->deleteComment($comment);
    }
}
