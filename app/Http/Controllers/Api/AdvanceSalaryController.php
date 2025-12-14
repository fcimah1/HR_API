<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AdvanceSalaryService;
use App\DTOs\AdvanceSalary\AdvanceSalaryFilterDTO;
use App\DTOs\AdvanceSalary\CreateAdvanceSalaryDTO;
use App\DTOs\AdvanceSalary\UpdateAdvanceSalaryDTO;
use App\Http\Requests\AdvanceSalary\CreateAdvanceSalaryRequest;
use App\Http\Requests\AdvanceSalary\UpdateAdvanceSalaryRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Tag(
 *     name="Advance Salary & Loan Management",
 *     description="Employee advance salary and loan requests management"
 * )
 */
class AdvanceSalaryController extends Controller
{
    public function __construct(
        private readonly AdvanceSalaryService $advanceSalaryService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/advances",
     *     summary="Get advance salary/loan requests",
     *     tags={"Advance Salary & Loan Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         description="Filter by type (loan or advance)",
     *         @OA\Schema(type="string", enum={"loan", "advance"})
     *     ),
     *     @OA\Parameter(
     *         name="employee_id",
     *         in="query",
     *         description="Filter by employee ID (managers/HR only)",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by status (0=pending, 1=approved, 2=rejected)",
     *         @OA\Schema(type="integer", enum={0, 1, 2})
     *     ),
     *     @OA\Parameter(
     *         name="month_year",
     *         in="query",
     *         description="Filter by month and year (format: YYYY-MM)",
     *         @OA\Schema(type="string", example="")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search by employee name or email",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Sort by column (created_at, amount, status)",
     *         @OA\Schema(type="string", enum={"created_at", "amount", "status"})
     *     ),
     *     @OA\Parameter(
     *         name="sort_direction",
     *         in="query",
     *         description="Sort direction (asc, desc)",
     *         @OA\Schema(type="string", enum={"asc", "desc"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Requests retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم جلب الطلبات بنجاح"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="pagination", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - No permission",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="غير مصرح لك بعرض الطلبات")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="حدث خطأ في الخادم")
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        try {
            $filters = AdvanceSalaryFilterDTO::fromRequest($request->all());
            $result = $this->advanceSalaryService->getPaginatedAdvances($filters, $user);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب الطلبات بنجاح',
                ...$result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 403);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/advances",
     *     summary="Create a new advance salary/loan request",
     *     tags={"Advance Salary & Loan Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"employee_id", "salary_type","month_year","advance_amount","one_time_deduct","monthly_installment","reason"},
     *             @OA\Property(property="employee_id", type="integer", example=755, description="معرف الموظف"),
     *             @OA\Property(property="salary_type", type="string", enum={"loan", "advance"}, example="loan", description="نوع الطلب: قرض أو سلفة"),
     *             @OA\Property(property="month_year", type="string", example="2025-12", description="الشهر والسنة (YYYY-MM)"),
     *             @OA\Property(property="advance_amount", type="number", example=5000.00, description="المبلغ الإجمالي"),
     *             @OA\Property(property="one_time_deduct", type="string", enum={"0", "1"}, example="0", description="خصم لمرة واحدة (0=لا، 1=نعم)"),
     *             @OA\Property(property="monthly_installment", type="number", example=500.00, description="القسط الشهري"),
     *             @OA\Property(property="reason", type="string", example="احتياج شخصي عاجل", description="سبب الطلب (10 أحرف على الأقل)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Request created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم إنشاء الطلب بنجاح"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - No permission",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="غير مصرح لك بإنشاء طلبات")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="فشل التحقق من البيانات"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="فشل في إنشاء الطلب")
     *         )
     *     )
     * )
     */
    public function store(CreateAdvanceSalaryRequest $request)
    {
        $user = Auth::user();

        try {
            // Get effective company ID from attributes
            $effectiveCompanyId = $request->attributes->get('effective_company_id');

            $validated = $request->validated();
            
            // Use employee_id from request if provided, otherwise use current user's ID
            $employeeId = $validated['employee_id'] ?? $user->user_id;

            $dto = CreateAdvanceSalaryDTO::fromRequest(
                $validated,
                $effectiveCompanyId,
                $employeeId
            );

            $advance = $this->advanceSalaryService->createAdvance($dto);

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء الطلب بنجاح',
                'data' => $advance->toArray()
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل في إنشاء الطلب',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/advances/{id}",
     *     summary="Get a specific advance salary/loan request",
     *     tags={"Advance Salary & Loan Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Request retrieved successfully"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Request not found"
     *     )
     * )
     */
    public function show(int $id, Request $request)
    {
        $user = Auth::user();

        try {
            $effectiveCompanyId = $request->attributes->get('effective_company_id');

            // Use updated getAdvanceById method with user object for proper hierarchy checks
            $result = $this->advanceSalaryService->getAdvanceById($id, $effectiveCompanyId, $user->user_id, $user);

            if (!$result['advance']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['reason'] ?? 'الطلب غير موجود'
                ], $result['reason'] === 'الطلب غير موجود' ? 404 : 403);
            }

            return response()->json([
                'success' => true,
                'data' => $result['advance']->toArray()
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/advances/{id}",
     *     summary="Update an advance salary/loan request (pending only)",
     *     tags={"Advance Salary & Loan Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="month_year", type="string", example="2025-12"),
     *             @OA\Property(property="advance_amount", type="number", example=6000.00),
     *             @OA\Property(property="one_time_deduct", type="string", enum={"0", "1"}, example="0"),
     *             @OA\Property(property="monthly_installment", type="number", example=600.00),
     *             @OA\Property(property="reason", type="string", example="تحديث السبب")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Request updated successfully"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Cannot update approved/rejected request"
     *     )
     * )
     */
    public function update(UpdateAdvanceSalaryRequest $request, int $id)
    {
        $user = Auth::user();

        try {
            $dto = UpdateAdvanceSalaryDTO::fromRequest($request->validated());
            $advance = $this->advanceSalaryService->updateAdvance($id, $dto, $user);

            if (!$advance) {
                return response()->json([
                    'success' => false,
                    'message' => 'الطلب غير موجود'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث الطلب بنجاح',
                'data' => $advance->toArray()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 403);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/advances/{id}/cancel",
     *     summary="Cancel an advance salary/loan request (marks as rejected, keeps record)",
     *     tags={"Advance Salary & Loan Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="ID of the advance/loan request"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Request cancelled successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم إلغاء الطلب بنجاح")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Permission denied or cannot cancel approved request"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Request not found"
     *     )
     * )
     */
    public function cancel(int $id)
    {
        $user = Auth::user();

        try {
            $cancelled = $this->advanceSalaryService->cancelAdvance($id, $user);

            if (!$cancelled) {
                return response()->json([
                    'success' => false,
                    'message' => 'الطلب غير موجود'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'تم إلغاء الطلب بنجاح'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 403);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/advances/{id}/approve",
     *     summary="Approve an advance salary/loan request (HR/Manager only)",
     *     tags={"Advance Salary & Loan Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="action",
     *         in="query",
     *         required=true,
     *         @OA\Schema(type="string", enum={"approve", "reject"}),
     *         description="الإجراء: approve للموافقة أو reject للرفض"
     *     ),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="remarks", type="string", example="موافق على الطلب")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Request approved successfully"
     *     )
     * )
     */
    public function approve(Request $request, int $id)
    {
        $user = Auth::user();

        // We remove the strict user_type check here because an 'employee' might be a manager via hierarchy.
        // The service layer will handle the specific hierarchy validation.
        // if (!in_array($user->user_type, ['company', 'admin', 'hr', 'manager'])) {
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'ليس لديك صلاحية للموافقة على الطلبات'
        //     ], 403);
        // }

        try {
            $effectiveCompanyId = $request->attributes->get('effective_company_id');
            $remarks = $request->input('remarks');

            $action = $request->input('action');

            if ($action === 'approve') {
                $advance = $this->advanceSalaryService->approveAdvance($id, $effectiveCompanyId, $user->user_id, $remarks);
            } elseif ($action === 'reject') {
                $advance = $this->advanceSalaryService->rejectAdvance($id, $effectiveCompanyId, $user->user_id, $remarks);
            }

            if (!$advance) {
                return response()->json([
                    'success' => false,
                    'message' => 'الطلب غير موجود'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => $action === 'approve' ? 'تمت الموافقة على الطلب ' : 'تم رفض الطلب ',
                'data' => $advance->toArray()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/advances/stats",
     *     summary="Get advance salary/loan statistics (HR/Manager only)",
     *     tags={"Advance Salary & Loan Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Statistics retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="total_requests", type="integer"),
     *                 @OA\Property(property="total_loans", type="integer"),
     *                 @OA\Property(property="total_advances", type="integer"),
     *                 @OA\Property(property="pending_count", type="integer"),
     *                 @OA\Property(property="approved_count", type="integer"),
     *                 @OA\Property(property="rejected_count", type="integer"),
     *                 @OA\Property(property="total_amount", type="number"),
     *                 @OA\Property(property="total_paid", type="number"),
     *                 @OA\Property(property="total_remaining", type="number")
     *             )
     *         )
     *     )
     * )
     */
    public function stats(Request $request)
    {
        $user = Auth::user();

        if (!in_array($user->user_type, ['company', 'admin', 'hr', 'manager'])) {
            return response()->json([
                'success' => false,
                'message' => 'ليس لديك صلاحية لعرض الإحصائيات'
            ], 403);
        }

        try {
            $effectiveCompanyId = $request->attributes->get('effective_company_id');
            $stats = $this->advanceSalaryService->getAdvanceStatistics($effectiveCompanyId);

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
