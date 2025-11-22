<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AdvanceSalaryService;
use App\Services\SimplePermissionService;
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
        private readonly AdvanceSalaryService $advanceSalaryService,
        private readonly SimplePermissionService $permissionService
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
     *         @OA\Schema(type="string", example="2025-11")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Requests retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم جلب الطلبات بنجاح"),
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="advance_salary_id", type="integer"),
     *                 @OA\Property(property="employee_name", type="string"),
     *                 @OA\Property(property="salary_type", type="string"),
     *                 @OA\Property(property="salary_type_text", type="string"),
     *                 @OA\Property(property="month_year", type="string"),
     *                 @OA\Property(property="advance_amount", type="number"),
     *                 @OA\Property(property="monthly_installment", type="number"),
     *                 @OA\Property(property="remaining_amount", type="number"),
     *                 @OA\Property(property="status_text", type="string")
     *             )),
     *             @OA\Property(property="pagination", type="object")
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
     *             required={"salary_type","month_year","advance_amount","one_time_deduct","monthly_installment","reason"},
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
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function store(CreateAdvanceSalaryRequest $request)
    {
        $user = Auth::user();

        try {
            // Get effective company ID from attributes
            $effectiveCompanyId = $request->attributes->get('effective_company_id');
            
            $dto = CreateAdvanceSalaryDTO::fromRequest(
                $request->validated(),
                $effectiveCompanyId,
                $user->user_id
            );

            $advance = $this->advanceSalaryService->createAdvance($dto, $user);

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
            
            // Check if user can view all company requests or just their own
            // This determines query scope - permission check is done in service
            $canViewAll = $this->permissionService->isCompanyOwner($user) || 
                         $this->permissionService->checkPermissionWithFallback($user, 'hradvance_salary', 'advance_salary1') ||
                         $this->permissionService->checkPermissionWithFallback($user, 'hrloan', 'loan1');
            
            // Try to get the record - first by company (if user can view all), then by user_id
            // Service method now handles permission checks internally
            try {
                $advance = $canViewAll 
                    ? $this->advanceSalaryService->getAdvanceById($id, $user, $effectiveCompanyId, null)
                    : null;
                
                // If not found and user is employee, try to get their own request
                if (!$advance) {
                    $advance = $this->advanceSalaryService->getAdvanceById($id, $user, null, $user->user_id);
                }
            } catch (\Exception $e) {
                // Permission denied or not found
                if (str_contains($e->getMessage(), 'صلاحية')) {
                    return response()->json([
                        'success' => false,
                        'message' => $e->getMessage()
                    ], 403);
                }
                return response()->json([
                    'success' => false,
                    'message' => 'الطلب غير موجود'
                ], 404);
            }

            if (!$advance) {
                return response()->json([
                    'success' => false,
                    'message' => 'الطلب غير موجود'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $advance->toArray()
            ]);

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

        try {
            $effectiveCompanyId = $request->attributes->get('effective_company_id');
            $remarks = $request->input('remarks');

            $advance = $this->advanceSalaryService->approveAdvance($id, $effectiveCompanyId, $user, $remarks);

            if (!$advance) {
                return response()->json([
                    'success' => false,
                    'message' => 'الطلب غير موجود'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'تمت الموافقة على الطلب بنجاح',
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

        // Check if user has permission to view advance salary or loan stats
        $canViewAdvance = $this->permissionService->checkPermission($user, 'hradvance_salary');
        $canViewLoan = $this->permissionService->checkPermission($user, 'hrloan');
        
        if (!$canViewAdvance && !$canViewLoan && !$this->permissionService->isCompanyOwner($user)) {
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

