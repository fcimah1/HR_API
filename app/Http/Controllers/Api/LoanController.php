<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LoanEligibilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Tag(
 *     name="Loan Management",
 *     description="Loan form initialization, preview, and request management"
 * )
 */
class LoanController extends Controller
{
    public function __construct(
        private readonly LoanEligibilityService $loanEligibilityService
    ) {
    }

    /**
     * @OA\Get(
     *     path="/api/loans/form-init",
     *     summary="Initialize loan request form",
     *     description="Returns employee info auto-filled data + available tiers with pre-calculated amounts. Used when employee is selected from dropdown.",
     *     tags={"Loan Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="employee_id",
     *         in="query",
     *         description="Employee ID (defaults to authenticated user)",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Form data retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="eligible", type="boolean", example=true),
     *                 @OA\Property(property="employee", type="object",
     *                     @OA\Property(property="employee_id", type="integer", example=755),
     *                     @OA\Property(property="full_name", type="string", example="أحمد محمد"),
     *                     @OA\Property(property="position", type="string", example="مطور برمجيات"),
     *                     @OA\Property(property="company", type="string", example="شركة التقنية"),
     *                     @OA\Property(property="department", type="string", example="تقنية المعلومات"),
     *                     @OA\Property(property="division", type="string", example="التطوير"),
     *                     @OA\Property(property="monthly_salary", type="number", example=10000.00)
     *                 ),
     *                 @OA\Property(property="available_tiers", type="array",
     *                     @OA\Items(type="object",
     *                         @OA\Property(property="tier_id", type="integer", example=2),
     *                         @OA\Property(property="label", type="string", example="قرض راتب شهر / 4 شهور"),
     *                         @OA\Property(property="loan_amount", type="number", example=10000.00),
     *                         @OA\Property(property="max_months", type="integer", example=4),
     *                         @OA\Property(property="min_months", type="integer", example=2),
     *                         @OA\Property(property="default_installment", type="number", example=2500.00)
     *                     )
     *                 ),
     *                 @OA\Property(property="blocked_tiers", type="array",
     *                     @OA\Items(type="object",
     *                         @OA\Property(property="tier_id", type="integer"),
     *                         @OA\Property(property="label", type="string"),
     *                         @OA\Property(property="reason", type="string")
     *                     )
     *                 ),
     *                 @OA\Property(property="blocked_reasons", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Employee not found"
     *     )
     * )
     */
    public function formInit(Request $request)
    {
        $user = Auth::user();
        $employeeId = $request->input('employee_id', $user->user_id);

        $result = $this->loanEligibilityService->getFormInitData($user, $employeeId);

        if (isset($result['error'])) {
            return response()->json([
                'success' => false,
                'message' => $result['error'],
            ], $result['code'] ?? 400);
        }

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/loans/preview",
     *     summary="Preview/Calculate loan installment",
     *     description="Recalculates loan when user changes installment months. Use for 'Calculate' button.",
     *     tags={"Loan Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"employee_id", "tier_id", "requested_months"},
     *             @OA\Property(property="employee_id", type="integer", example=755, description="Employee ID"),
     *             @OA\Property(property="tier_id", type="integer", example=2, description="Selected tier ID"),
     *             @OA\Property(property="requested_months", type="integer", example=3, description="Number of installment months")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Calculation completed",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="tier", type="object",
     *                     @OA\Property(property="tier_id", type="integer", example=2),
     *                     @OA\Property(property="label", type="string", example="قرض راتب شهر / 4 شهور"),
     *                     @OA\Property(property="max_months", type="integer", example=4)
     *                 ),
     *                 @OA\Property(property="calculation", type="object",
     *                     @OA\Property(property="employee_salary", type="number", example=10000.00),
     *                     @OA\Property(property="loan_amount", type="number", example=10000.00),
     *                     @OA\Property(property="requested_months", type="integer", example=3),
     *                     @OA\Property(property="monthly_installment", type="number", example=3333.33),
     *                     @OA\Property(property="max_allowed_deduction", type="number", example=5000.00),
     *                     @OA\Property(property="min_months", type="integer", example=2),
     *                     @OA\Property(property="is_valid", type="boolean", example=true),
     *                     @OA\Property(property="validation_message", type="string", nullable=true)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation error"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function preview(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|integer',
            'tier_id' => 'required|integer|exists:ci_loan_policy_tiers,tier_id',
            'requested_months' => 'required|integer|min:1',
        ]);

        $employee = \App\Models\User::find($request->input('employee_id'));
        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'الموظف غير موجود',
            ], 404);
        }

        $salary = $this->loanEligibilityService->getEmployeeSalary($employee);

        if ($salary <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'لم يتم تحديد راتب الموظف في النظام',
            ], 400);
        }

        $preview = $this->loanEligibilityService->previewLoan(
            $request->input('tier_id'),
            $salary,
            $request->input('requested_months')
        );

        if (!$preview) {
            return response()->json([
                'success' => false,
                'message' => 'نوع القرض/السلفة غير صالح',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => $preview->toArray(),
        ]);
    }
}
