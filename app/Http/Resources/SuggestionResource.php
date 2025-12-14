<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SuggestionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'suggestion_id' => $this->suggestion_id,
            'company_id' => $this->company_id,
            'title' => $this->title,
            'description' => $this->description,
            'attachment' => $this->attachment,
            'added_by' => $this->added_by,
            'created_at' => $this->created_at,

            // معلومات الموظف المرسل
            'employee_name' => $this->when(
                $this->relationLoaded('employee'),
                fn() => $this->employee ? ($this->employee->first_name . ' ' . $this->employee->last_name) : 'غير محدد'
            ),

            // معلومات الموظف إذا كانت محملة
            'employee' => $this->when($this->relationLoaded('employee'), function () {
                return $this->employee ? [
                    'user_id' => $this->employee->user_id,
                    'first_name' => $this->employee->first_name,
                    'last_name' => $this->employee->last_name,
                    'email' => $this->employee->email,
                    'full_name' => $this->employee->full_name,
                ] : null;
            }),

            // عدد التعليقات
            'comments_count' => $this->when(
                $this->relationLoaded('comments'),
                fn() => $this->comments->count()
            ),

            // التعليقات إذا كانت محملة
            'comments' => $this->when($this->relationLoaded('comments'), function () {
                return $this->comments->map(function ($comment) {
                    return [
                        'comment_id' => $comment->comment_id,
                        'employee_id' => $comment->employee_id,
                        'employee_name' => $comment->employee ? ($comment->employee->first_name . ' ' . $comment->employee->last_name) : 'غير محدد',
                        'comment' => $comment->suggestion_comment,
                        'created_at' => $comment->created_at,
                    ];
                });
            }),
        ];
    }
}
