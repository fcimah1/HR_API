<?php

namespace App\Repository;

use App\DTOs\Suggestion\CreateSuggestionDTO;
use App\DTOs\Suggestion\CreateSuggestionCommentDTO;
use App\DTOs\Suggestion\SuggestionFilterDTO;
use App\DTOs\Suggestion\UpdateSuggestionDTO;
use App\Models\Suggestion;
use App\Models\SuggestionComment;
use App\Models\User;
use App\Repository\Interface\SuggestionRepositoryInterface;
use Illuminate\Support\Facades\Log;

class SuggestionRepository implements SuggestionRepositoryInterface
{
    /**
     * الحصول على قائمة الاقتراحات مع التصفية والترقيم الصفحي
     */
    public function getPaginatedSuggestions(SuggestionFilterDTO $filters, User $user): array
    {
        $query = Suggestion::with(['employee', 'comments']);

        // تطبيق فلتر الشركة
        if ($filters->companyId !== null) {
            $query->where('company_id', $filters->companyId);
        }

        // تطبيق فلتر الموظف المحدد
        if ($filters->employeeId !== null) {
            $query->where('added_by', $filters->employeeId);
        }

        // تطبيق فلتر قائمة الموظفين (للمديرين)
        if ($filters->employeeIds !== null && !empty($filters->employeeIds)) {
            $query->whereIn('added_by', $filters->employeeIds);
        }

        // تطبيق البحث
        if ($filters->search !== null && trim($filters->search) !== '') {
            $searchTerm = '%' . $filters->search . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('title', 'like', $searchTerm)
                    ->orWhere('description', 'like', $searchTerm)
                    ->orWhereHas('employee', function ($subQuery) use ($searchTerm) {
                        $subQuery->where('first_name', 'like', $searchTerm)
                            ->orWhere('last_name', 'like', $searchTerm);
                    });
            });
        }

        // تطبيق فلتر التاريخ
        if ($filters->fromDate !== null && $filters->toDate !== null) {
            $query->whereBetween('created_at', [$filters->fromDate, $filters->toDate . ' 23:59:59']);
        } elseif ($filters->fromDate !== null) {
            $query->where('created_at', '>=', $filters->fromDate);
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
     * الحصول على اقتراح بواسطة المعرف
     */
    public function findSuggestionById(int $id, int $companyId): ?Suggestion
    {
        return Suggestion::with(['employee', 'comments.employee'])
            ->where('suggestion_id', $id)
            ->where('company_id', $companyId)
            ->first();
    }

    /**
     * الحصول على اقتراح للموظف
     */
    public function findSuggestionForEmployee(int $id, int $employeeId): ?Suggestion
    {
        return Suggestion::with(['employee', 'comments.employee'])
            ->where('suggestion_id', $id)
            ->where('added_by', $employeeId)
            ->first();
    }

    /**
     * إنشاء اقتراح جديد
     */
    public function createSuggestion(CreateSuggestionDTO $dto): Suggestion
    {
        $suggestion = Suggestion::create($dto->toArray());
        $suggestion->load(['employee']);

        Log::info('Suggestion created', [
            'suggestion_id' => $suggestion->suggestion_id,
            'added_by' => $suggestion->added_by,
        ]);

        return $suggestion;
    }

    /**
     * تحديث اقتراح
     */
    public function updateSuggestion(Suggestion $suggestion, UpdateSuggestionDTO $dto): Suggestion
    {
        $updateData = $dto->toArray();

        if (!empty($updateData)) {
            $suggestion->update($updateData);
        }

        $suggestion->refresh();
        $suggestion->load(['employee', 'comments.employee']);

        Log::info('Suggestion updated', [
            'suggestion_id' => $suggestion->suggestion_id,
        ]);

        return $suggestion;
    }

    /**
     * حذف اقتراح
     */
    public function deleteSuggestion(Suggestion $suggestion): bool
    {
        Log::info('Suggestion deleted', [
            'suggestion_id' => $suggestion->suggestion_id,
            'added_by' => $suggestion->added_by,
        ]);

        // حذف التعليقات أولاً
        SuggestionComment::where('suggestion_id', $suggestion->suggestion_id)->delete();

        return $suggestion->delete();
    }

    /**
     * إضافة تعليق على اقتراح
     */
    public function addComment(CreateSuggestionCommentDTO $dto): SuggestionComment
    {
        $comment = SuggestionComment::create($dto->toArray());
        $comment->load(['employee', 'suggestion']);

        Log::info('Suggestion comment added', [
            'comment_id' => $comment->comment_id,
            'suggestion_id' => $dto->suggestionId,
            'employee_id' => $dto->employeeId,
        ]);

        return $comment;
    }

    /**
     * الحصول على تعليقات اقتراح
     */
    public function getComments(int $suggestionId): array
    {
        return SuggestionComment::with(['employee'])
            ->where('suggestion_id', $suggestionId)
            ->orderBy('created_at', 'asc')
            ->get()
            ->toArray();
    }
}
