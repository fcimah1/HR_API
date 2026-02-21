<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LeavePolicyService;
use App\Services\SimplePermissionService;
use App\Services\TieredLeaveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Leave Policy Controller
 * 
 * Handles API endpoints for country-based leave policies
 * 
 * @OA\Tag(
 *     name="Leave Policies",
 *     description="Advanced leave policies management - سياسات الإجازات المتقدمة"
 * )
 */
class LeavePolicyController extends Controller
{
    public function __construct(
        private readonly LeavePolicyService $leavePolicyService,
        private readonly TieredLeaveService $tieredLeaveService,
        private readonly SimplePermissionService $permissionService
    ) {}

    /**
     * Get leave policies for a specific country
     * 
     * @OA\Get(
     *     path="/api/leave-policies/country/{countryCode}",
     *     summary="Get leave policies by country - الحصول على سياسات دولة",
     *     tags={"Leave Policies"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="countryCode",
     *         in="path",
     *         required=true,
     *         description="Country code (SA, EG, KW, QA)",
     *         @OA\Schema(type="string", enum={"SA", "EG", "KW", "QA"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="country_code", type="string", example="SA"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="policy_id", type="integer", example=1),
     *                     @OA\Property(property="country_code", type="string", example="SA"),
     *                     @OA\Property(property="leave_type", type="string", example="sick"),
     *                     @OA\Property(property="days_per_year", type="integer", example=120),
     *                     @OA\Property(property="tier_1_days", type="integer", example=30),
     *                     @OA\Property(property="tier_1_payment", type="integer", example=100)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=400, description="Invalid country code"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=500, description="Server error")
     * )
     * 
     * @param string $countryCode Country code (SA, EG, KW, QA)
     * @param Request $request
     * @return JsonResponse
     */
    public function getPoliciesByCountry(string $countryCode, Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $companyId = $this->permissionService->getEffectiveCompanyId($user);
            // Validate country code
            $validCountries = ['SA', 'EG', 'KW', 'QA'];
            if (!in_array(strtoupper($countryCode), $validCountries)) {
                return response()->json([
                    'success' => false,
                    'message' => 'رمز الدولة غير صالح. القيم المسموحة: SA, EG, KW, QA',
                ], 400);
            }

            $policies = $this->leavePolicyService->getPoliciesForCountry(
                strtoupper($countryCode),
                $companyId
            );

            return response()->json([
                'success' => true,
                'data' => $policies,
                'country_code' => strtoupper($countryCode),
            ]);
        } catch (\Exception $e) {
            Log::error('LeavePolicyController::getPoliciesByCountry - Error', [
                'country_code' => $countryCode,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب السياسات',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check if employee has used a one-time leave
     * 
     * @OA\Get(
     *     path="/api/leave-policies/employee/{employeeId}/one-time-check/{leaveType}",
     *     summary="Check one-time leave usage - التحقق من استخدام إجازة لمرة واحدة",
     *     tags={"Leave Policies"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="employeeId",
     *         in="path",
     *         required=true,
     *         description="Employee ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="leaveType",
     *         in="path",
     *         required=true,
     *         description="Leave type (e.g., hajj, umrah)",
     *         @OA\Schema(type="string", example="hajj")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="employee_id", type="integer"),
     *                 @OA\Property(property="leave_type", type="string", example="hajj"),
     *                 @OA\Property(property="has_used", type="boolean", example=false),
     *                 @OA\Property(property="can_request", type="boolean", example=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=500, description="Server error")
     * )
     * 
     * @param int $employeeId
     * @param string $leaveType
     * @return JsonResponse
     */
    public function checkOneTimeLeaveUsage(int $employeeId, string $leaveType): JsonResponse
    {
        try {
            $eligibility = $this->leavePolicyService->validateOneTimeLeaveEligibility($employeeId, $leaveType);

            return response()->json([
                'success' => true,
                'data' => [
                    'employee_id' => $employeeId,
                    'leave_type' => $leaveType,
                    'has_used' => $eligibility['has_used'],
                    'can_request' => $eligibility['can_request'],
                    'errors' => $eligibility['errors'],
                    'service_years' => $eligibility['service_years'],
                    'policy' => $eligibility['policy']
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('LeavePolicyController::checkOneTimeLeaveUsage - Error', [
                'employee_id' => $employeeId,
                'leave_type' => $leaveType,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء التحقق من استخدام الإجازة',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get company's leave policies
     * 
     * @OA\Get(
     *     path="/api/leave-policies/company",
     *     summary="Get company policies - عرض سياسات الشركة",
     *     tags={"Leave Policies"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="country_code", type="string", example="SA"),
     *                 @OA\Property(property="is_custom", type="boolean", example=false),
     *                 @OA\Property(property="policies", type="array", @OA\Items(type="object"))
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=500, description="Server error")
     * )
     * 
     * @param int $companyId
     * @param Request $request
     * @return JsonResponse
     */
    public function getCompanyPolicies(Request $request): JsonResponse
    {
        try {
            $companyId = $this->permissionService->getEffectiveCompanyId(Auth::user());
            $data = $this->leavePolicyService->getCompanyPolicies($companyId);

            Log::info('LeavePolicyController::getCompanyPolicies - Success', [
                'company_id' => $companyId,
                'data' => $data,
            ]);
            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            Log::error('LeavePolicyController::getCompanyPolicies - Error', [
                'company_id' => $companyId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Save/Update company leave policies
     * 
     * @OA\Post(
     *     path="/api/leave-policies/company/{country_code}",
     *     summary="Save company policies - حفظ سياسات الشركة",
     *     tags={"Leave Policies"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="country_code",
     *         in="path",
     *         required=true,
     *         description="Country code (SA, EG, KW, QA)",
     *         @OA\Schema(type="string", enum={"SA", "EG", "KW", "QA"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم تهيئة إعدادات الإجازات بنجاح"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="mapping_count", type="integer", example=5),
     *                 @OA\Property(property="country_code", type="string", example="SA"),
     *                 @OA\Property(property="policies", type="array", @OA\Items(type="object"))
     *             )
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=500, description="Server error")
     * )
     * 
     * @param string $countryCode
     * @return JsonResponse
     */
    public function saveCompanyPolicies(string $countryCode): JsonResponse
    {
        try {
            $companyId = $this->permissionService->getEffectiveCompanyId(Auth::user());

            // Validate country code manually since it's from path
            if (!in_array(strtoupper($countryCode), ['SA', 'EG', 'KW', 'QA'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'كود الدولة غير مدعوم',
                ], 422);
            }

            $result = $this->leavePolicyService->saveCompanyPolicies(
                $companyId,
                strtoupper($countryCode)
            );

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => [
                    'mapping_count' => $result['mapping_count'],
                    'country_code' => $result['country_code'],
                    'policies' => $result['policies'],
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('LeavePolicyController::saveCompanyPolicies - Error', [
                'company_id' => $companyId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ: ' . $e->getMessage(),
            ], 500);
        }
    }
}
