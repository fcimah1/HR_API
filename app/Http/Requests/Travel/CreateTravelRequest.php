<?php

namespace App\Http\Requests\Travel;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Schema(
 *     schema="CreateTravelRequest",
 *     type="object",
 *     title="Create Travel Request",
 *     required={"start_date", "end_date", "visit_purpose", "visit_place", "travel_mode", "arrangement_type", "expected_budget", "actual_budget"},
 *     @OA\Property(property="employee_id", type="integer", description="Employee ID (optional, for admin/company)"),
 *     @OA\Property(property="start_date", type="string", format="date", description="Start date of travel"),
 *     @OA\Property(property="end_date", type="string", format="date", description="End date of travel"),
 *     @OA\Property(property="visit_purpose", type="string", description="Purpose of the visit"),
 *     @OA\Property(property="visit_place", type="string", description="Place of visit"),
 *     @OA\Property(property="travel_mode", type="integer", description="Mode of travel (1-5)"),
 *     @OA\Property(property="arrangement_type", type="integer", description="Type of arrangement"),
 *     @OA\Property(property="expected_budget", type="number", format="float", description="Expected budget"),
 *     @OA\Property(property="actual_budget", type="number", format="float", description="Actual budget"),
 *     @OA\Property(property="description", type="string", description="Description"),
 *     @OA\Property(property="associated_goals", type="array", @OA\Items(type="integer"), description="List of associated goal IDs")
 * )
 */
class CreateTravelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check(); // Authorization is handled in Controller/Service
    }

    public function rules(): array
    {
        return [
            'employee_id' => 'nullable|exists:ci_erp_users,user_id', // Optional if creating for self
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'visit_purpose' => 'required|string|max:255',
            'visit_place' => 'required|string|max:255',
            'travel_mode' => 'required|integer|in:1,2,3,4,5',
            'arrangement_type' => 'required|integer', // Assuming validation against constants table is not strictly required here or handled elsewhere
            'expected_budget' => 'required|numeric|min:0',
            'actual_budget' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'associated_goals' => 'nullable|array',
            'associated_goals.*' => 'integer',
        ];
    }

    public function messages(): array
    {
        return [
            'start_date.date' => 'Start date must be a valid date.',
            'end_date.date' => 'End date must be a valid date.',
            'end_date.after_or_equal' => 'End date must be on or after start date.',
            'visit_purpose.string' => 'Visit purpose must be a string.',
            'visit_purpose.max' => 'Visit purpose must be less than or equal to 255 characters.',
            'visit_place.string' => 'Visit place must be a string.',
            'visit_place.max' => 'Visit place must be less than or equal to 255 characters.',
            'travel_mode.integer' => 'Travel mode must be an integer.',
            'travel_mode.in' => 'Travel mode must be between 1 and 5.',
            'arrangement_type.integer' => 'Arrangement type must be an integer.',
            'expected_budget.numeric' => 'Expected budget must be a number.',
            'expected_budget.min' => 'Expected budget must be greater than or equal to 0.',
            'actual_budget.numeric' => 'Actual budget must be a number.',
            'actual_budget.min' => 'Actual budget must be greater than or equal to 0.',
            'description.string' => 'Description must be a string.',
            'associated_goals.array' => 'Associated goals must be an array.',
            'associated_goals.*.integer' => 'Associated goals must be an array of integers.',
        ];
    }

    public function attributes(): array
    {
        return [
            'start_date' => 'Start Date',
            'end_date' => 'End Date',
            'visit_purpose' => 'Visit Purpose',
            'visit_place' => 'Visit Place',
            'travel_mode' => 'Travel Mode',
            'arrangement_type' => 'Arrangement Type',
            'expected_budget' => 'Expected Budget',
            'actual_budget' => 'Actual Budget',
            'description' => 'Description',
            'associated_goals' => 'Associated Goals',
        ];
    }

    public function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        Log::warning('فشل إنشاء طلب رحلة', [
            'errors' => $validator->errors()->toArray(),
            'input' => $this->all()
        ]);

        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'فشل إنشاء طلب رحلة',
            'errors' => $validator->errors(),
        ], 422));
    }
}
