<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payslip\PayslipIndexRequest;
use App\Http\Requests\Payslip\PayslipApproveListRequest;
use App\Http\Requests\Payslip\PayslipDraftActionRequest;
use App\Http\Requests\Payslip\PayslipHistoryRequest;
use App\Http\Resources\PayslipResource;
use App\Http\Requests\Report\PayrollReportRequest;
use App\Repository\Interface\ReportRepositoryInterface;
use App\Services\PayslipService;
use App\Services\SimplePermissionService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="Payroll - Payslips",
 *     description="إدارة الرواتب (قسائم الرواتب)"
 * )
 */
class PayslipController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly PayslipService $payslipService,
        private readonly SimplePermissionService $permissionService,
        private readonly ReportRepositoryInterface $reportRepository,
    ) {}


    /**
     * @OA\Get(
     *     path="/api/payslips/payment-view",
     *     summary="عرض معلومات المرتبات (شاشة الدفع)",
     *     tags={"Payroll - Payslips"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="payment_date", in="query", required=true, @OA\Schema(type="string", example="2026-02")),
     *     @OA\Parameter(name="employee_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="branch_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="payment_method", in="query", required=false, @OA\Schema(type="string", example="cash")),
     *     @OA\Parameter(name="job_type", in="query", required=false, @OA\Schema(type="string", example="all")),
     *     @OA\Response(response=200, description="تم جلب البيانات بنجاح"),
     *     @OA\Response(response=401, description="غير مصرح"),
     *     @OA\Response(response=422, description="فشل التحقق من البيانات"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function paymentView(PayrollReportRequest $request): JsonResponse
    {
        $user = Auth::user();

        try {
            $companyId = $this->permissionService->getEffectiveCompanyId($user);
            $filters = $request->validated();

            if ($user->user_type !== 'company' && !$this->permissionService->isCompanyOwner($user)) {
                $rawEmployees = $this->permissionService->getEmployeesByHierarchy($user->user_id, $companyId, true);
                $allowedIds = collect($rawEmployees)->pluck('user_id')->toArray();

                if (!empty($filters['employee_id'])) {
                    if (!in_array((int) $filters['employee_id'], $allowedIds)) {
                        throw new \InvalidArgumentException('ليس لديك صلاحية لعرض بيانات هذا الموظف');
                    }
                } else {
                    $filters['employee_ids'] = $allowedIds;
                }
            }

            $data = $this->reportRepository->getPayrollReport($companyId, $filters);

            $payload = $data->map(function ($row) {
                $employee = $row->employee ?? null;
                $details = $row->details ?? null;
                $branch = $details?->branch ?? null;
                $currency = $details?->currency ?? null;

                return [
                    'user_id' => (int) ($row->user_id ?? 0),
                    'employee_id' => $details?->employee_id,
                    'employee_name' => trim((string) (($employee?->first_name ?? '') . ' ' . ($employee?->last_name ?? ''))),
                    'branch_id' => $branch?->branch_id,
                    'branch_name' => $branch?->branch_name,
                    'job_type' => $details?->job_type,
                    'payment_method' => $row->payment_method,
                    'basic_salary' => (float) ($row->basic_salary ?? 0),
                    'allowances_total' => (float) ($row->allowances_total ?? 0),
                    'deductions_total' => (float) ($row->deductions_total ?? 0),
                    'loan_amount' => (float) ($row->loan_amount ?? 0),
                    'unpaid_leave_days' => (float) ($row->unpaid_leave_days ?? 0),
                    'unpaid_leave_deduction' => (float) ($row->unpaid_leave_deduction ?? 0),
                    'sick_leave_deduction' => (float) ($row->sick_leave_deduction ?? 0),
                    'maternity_leave_deduction' => (float) ($row->maternity_leave_deduction ?? 0),
                    'net_salary' => (float) ($row->net_salary ?? 0),
                    'status' => (int) ($row->status ?? 0),
                    'is_paid' => (bool) ($row->is_paid ?? false),
                    'currency' => [
                        'currency_id' => $currency?->currency_id,
                        'currency_code' => $currency?->currency_code,
                    ],
                    'allowances' => collect($row->allowances ?? [])->map(function ($a) {
                        return [
                            'contract_option_id' => $a->contract_option_id ?? null,
                            'pay_title' => $a->pay_title ?? null,
                            'pay_amount' => (float) ($a->pay_amount ?? 0),
                            'is_fixed' => (int) ($a->is_fixed ?? 0),
                            'is_taxable' => (int) ($a->is_taxable ?? 0),
                        ];
                    })->values()->all(),
                    'deductions' => collect($row->deductions ?? [])->map(function ($d) {
                        return [
                            'contract_option_id' => $d->contract_option_id ?? null,
                            'pay_title' => $d->pay_title ?? null,
                            'pay_amount' => (float) ($d->pay_amount ?? 0),
                            'is_fixed' => (int) ($d->is_fixed ?? 0),
                        ];
                    })->values()->all(),
                ];
            })->values()->all();

            $payload = $this->sanitizeForJson($payload);
            return $this->successResponse($payload, 'تم جلب البيانات بنجاح');
        } catch (\Exception $e) {
            Log::error('PayslipController::paymentView failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->user_id,
            ]);

            return $this->handleException($e, 'PayslipController::paymentView');
        }
    }

    private function sanitizeForJson(mixed $data): mixed
    {
        if ($data instanceof Collection) {
            $data = $data->toArray();
        }

        if (is_array($data)) {
            $clean = [];
            foreach ($data as $k => $v) {
                $clean[$k] = $this->sanitizeForJson($v);
            }
            return $clean;
        }

        if (is_object($data)) {
            return $this->sanitizeForJson(get_object_vars($data));
        }

        if (is_string($data)) {
            if (!mb_check_encoding($data, 'UTF-8')) {
                return mb_convert_encoding($data, 'UTF-8');
            }
            return $data;
        }

        return $data;
    }

   /**
     * @OA\Post(
     *     path="/api/payslips/draft",
     *     summary="إنشاء مسودة الرواتب للشهر المحدد",
     *     tags={"Payroll - Payslips"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"salary_month"},
     *             @OA\Property(property="salary_month", type="string", example="2026-02")
     *         )
     *     ),
     *     @OA\Response(response=200, description="تم إنشاء المسودة بنجاح"),
     *     @OA\Response(response=401, description="غير مصرح"),
     *     @OA\Response(response=422, description="فشل التحقق من البيانات"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function createDraft(PayslipDraftActionRequest $request): JsonResponse
    {
        $user = Auth::user();

        try {
            $companyId = $this->permissionService->getEffectiveCompanyId($user);
            $count = $this->payslipService->createDraftPayslips($companyId, $user, $request->validated());

            Log::info('PayslipController::createDraft success', [
                'user_id' => $user->user_id,
                'company_id' => $companyId,
                'created' => $count,
            ]);

            return $this->successResponse(['created' => $count], 'تم إنشاء المسودة بنجاح');
        } catch (\Exception $e) {
            Log::error('PayslipController::createDraft failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->user_id,
            ]);
            return $this->handleException($e, 'PayslipController::createDraft');
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/payslips/draft",
     *     summary="إلغاء مسودة الرواتب (حذف الصفوف المنشأة)",
     *     tags={"Payroll - Payslips"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"salary_month"},
     *             @OA\Property(property="salary_month", type="string", example="2026-02")
     *         )
     *     ),
     *     @OA\Response(response=200, description="تم إلغاء المسودة بنجاح"),
     *     @OA\Response(response=401, description="غير مصرح"),
     *     @OA\Response(response=422, description="فشل التحقق من البيانات"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function cancelDraft(PayslipDraftActionRequest $request): JsonResponse
    {
        $user = Auth::user();

        try {
            $companyId = $this->permissionService->getEffectiveCompanyId($user);
            $count = $this->payslipService->cancelDraftPayslips($companyId, $user, $request->validated());

            Log::info('PayslipController::cancelDraft success', [
                'user_id' => $user->user_id,
                'company_id' => $companyId,
                'deleted' => $count,
            ]);

            return $this->successResponse(['deleted' => $count], 'تم إلغاء المسودة بنجاح');
        } catch (\Exception $e) {
            Log::error('PayslipController::cancelDraft failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->user_id,
            ]);
            return $this->handleException($e, 'PayslipController::cancelDraft');
        }
    }

    /**
     * @OA\Get(
     *     path="/api/payslips/approve-list",
     *     summary="قائمة قسائم الرواتب بانتظار الموافقة",
     *     tags={"Payroll - Payslips"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="salary_month", in="query", required=false, @OA\Schema(type="string", example="2026-02")),
     *     @OA\Parameter(name="staff_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="salary_payment_method", in="query", required=false, @OA\Schema(type="string", example="CASH")),
     *     @OA\Parameter(name="branch_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="job_type", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", example=10)),
     *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", example=1)),
     *     @OA\Response(response=200, description="تم جلب البيانات بنجاح"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=422, description="فشل التحقق من البيانات"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function approveList(PayslipApproveListRequest $request): JsonResponse
    {
        $user = Auth::user();

        try {
            $companyId = $this->permissionService->getEffectiveCompanyId($user);
            $result = $this->payslipService->getPayslipApproveList($companyId, $user, $request->validated());

            return $this->paginatedResponse($result, 'تم جلب البيانات بنجاح', PayslipResource::class);
        } catch (\Exception $e) {
            Log::error('PayslipController::approveList failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->user_id,
            ]);
            return $this->handleException($e, 'PayslipController::approveList');
        }
    }

    /**
     * @OA\Post(
     *     path="/api/payslips/approve",
     *     summary="اعتماد الرواتب (تحويل الحالة إلى 1)",
     *     tags={"Payroll - Payslips"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"salary_month"},
     *             @OA\Property(property="salary_month", type="string", example="2026-02")
     *         )
     *     ),
     *     @OA\Response(response=200, description="تم اعتماد الرواتب بنجاح"),
     *     @OA\Response(response=401, description="غير مصرح"),
     *     @OA\Response(response=422, description="فشل التحقق من البيانات"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function approve(PayslipDraftActionRequest $request): JsonResponse
    {
        $user = Auth::user();

        try {
            $companyId = $this->permissionService->getEffectiveCompanyId($user);
            $count = $this->payslipService->approveDraftPayslips($companyId, $user, $request->validated());

            Log::info('PayslipController::approve success', [
                'user_id' => $user->user_id,
                'company_id' => $companyId,
                'updated' => $count,
            ]);

            return $this->successResponse(['updated' => $count], 'تم اعتماد الرواتب بنجاح');
        } catch (\Exception $e) {
            Log::error('PayslipController::approve failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->user_id,
            ]);
            return $this->handleException($e, 'PayslipController::approve');
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/payslips/approve",
     *     summary="إلغاء اعتماد الرواتب (إرجاع الحالة إلى معلق)",
     *     tags={"Payroll - Payslips"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"salary_month"},
     *             @OA\Property(property="salary_month", type="string", example="2026-02")
     *         )
     *     ),
     *     @OA\Response(response=200, description="تم إلغاء الاعتماد بنجاح"),
     *     @OA\Response(response=401, description="غير مصرح"),
     *     @OA\Response(response=422, description="فشل التحقق من البيانات"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function cancelApprove(PayslipDraftActionRequest $request): JsonResponse
    {
        $user = Auth::user();

        try {
            $companyId = $this->permissionService->getEffectiveCompanyId($user);
            $count = $this->payslipService->cancelApprovePayslips($companyId, $user, $request->validated());

            Log::info('PayslipController::cancelApprove success', [
                'user_id' => $user->user_id,
                'company_id' => $companyId,
                'updated' => $count,
            ]);

            return $this->successResponse(['updated' => $count], 'تم إلغاء الاعتماد بنجاح');
        } catch (\Exception $e) {
            Log::error('PayslipController::cancelApprove failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->user_id,
            ]);
            return $this->handleException($e, 'PayslipController::cancelApprove');
        }
    }


    /**
     * @OA\Get(
     *     path="/api/payslips/{id}",
     *     summary="عرض قسيمة راتب",
     *     tags={"Payroll - Payslips"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="تم جلب البيانات بنجاح"),
     *     @OA\Response(response=404, description="غير موجود"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function show(int $id): JsonResponse
    {
        $user = Auth::user();

        try {
            $companyId = $this->permissionService->getEffectiveCompanyId($user);
            $payslip = $this->payslipService->getPayslipByIdForCompany($companyId, $user, $id);

            if (!$payslip) {
                return $this->notFoundResponse('قسيمة الراتب غير موجودة');
            }

            return $this->successResponse(new PayslipResource($payslip), 'تم جلب البيانات بنجاح');
        } catch (\Exception $e) {
            Log::error('PayslipController::show failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->user_id,
                'payslip_id' => $id,
            ]);
            return $this->handleException($e, 'PayslipController::show');
        }
    }

}
