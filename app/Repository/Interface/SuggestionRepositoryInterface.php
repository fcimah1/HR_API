<?php

namespace App\Repository\Interface;

use App\DTOs\Suggestion\CreateSuggestionDTO;
use App\DTOs\Suggestion\CreateSuggestionCommentDTO;
use App\DTOs\Suggestion\SuggestionFilterDTO;
use App\DTOs\Suggestion\UpdateSuggestionDTO;
use App\Models\Suggestion;
use App\Models\SuggestionComment;
use App\Models\User;

interface SuggestionRepositoryInterface
{
    /**
     * الحصول على قائمة الاقتراحات مع التصفية والترقيم الصفحي
     */
    public function getPaginatedSuggestions(SuggestionFilterDTO $filters, User $user): array;

    /**
     * الحصول على اقتراح بواسطة المعرف
     */
    public function findSuggestionById(int $id, int $companyId): ?Suggestion;

    /**
     * الحصول على اقتراح للموظف
     */
    public function findSuggestionForEmployee(int $id, int $employeeId): ?Suggestion;

    /**
     * إنشاء اقتراح جديد
     */
    public function createSuggestion(CreateSuggestionDTO $dto): Suggestion;

    /**
     * تحديث اقتراح
     */
    public function updateSuggestion(Suggestion $suggestion, UpdateSuggestionDTO $dto): Suggestion;

    /**
     * حذف اقتراح
     */
    public function deleteSuggestion(Suggestion $suggestion): bool;

    /**
     * إضافة تعليق على اقتراح
     */
    public function addComment(CreateSuggestionCommentDTO $dto): SuggestionComment;

    /**
     * الحصول على تعليقات اقتراح
     */
    public function getComments(int $suggestionId): array;

    /**
     * البحث عن تعليق بواسطة المعرف
     */
    public function findCommentById(int $commentId, int $suggestionId, int $companyId): ?SuggestionComment;

    /**
     * حذف تعليق
     */
    public function deleteComment(SuggestionComment $comment): bool;
}
