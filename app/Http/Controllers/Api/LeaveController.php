<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LeaveService;
use App\Models\ErpConstant;
use App\DTOs\Leave\LeaveApplicationFilterDTO;
use App\DTOs\Leave\CreateLeaveApplicationDTO;
use App\DTOs\Leave\UpdateLeaveApplicationDTO;
use App\DTOs\Leave\LeaveAdjustmentFilterDTO;
use App\DTOs\Leave\CreateLeaveAdjustmentDTO;
use App\DTOs\Leave\UpdateLeaveAdjustmentDTO;
use App\Http\Requests\Leave\CreateLeaveApplicationRequest;
use App\Http\Requests\Leave\UpdateLeaveApplicationRequest;
use App\Http\Requests\Leave\CreateLeaveAdjustmentRequest;
use App\Http\Requests\Leave\UpdateLeaveAdjustmentRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Tag(
 *     name="Leave Management",
 *     description="Leave applications and adjustments management"
 * )
 */
class LeaveController extends Controller
{
    public function __construct(
        private readonly LeaveService $leaveService
    ) {}
    /**
     * @OA\Get(
     *     path="/api/leaves/applications",
     *     summary="Get leave applications",
     *     tags={"Leave Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="employee_id",
     *         in="query",
     *         description="Filter by employee ID (managers/HR only)",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by status (true=approved, false=pending)",
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Parameter(
     *         name="leave_type_id",
     *         in="query",
     *         description="Filter by leave type ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Leave applications retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="leave_id", type="integer"),
     *                 @OA\Property(property="employee_name", type="string"),
     *                 @OA\Property(property="leave_type_name", type="string"),
     *                 @OA\Property(property="from_date", type="string", format="date"),
     *                 @OA\Property(property="to_date", type="string", format="date"),
     *                 @OA\Property(property="duration_days", type="integer"),
     *                 @OA\Property(property="reason", type="string"),
     *                 @OA\Property(property="status_text", type="string")
     *             )),
     *             @OA\Property(property="pagination", type="object")
     *         )
     *     )
     * )
     */
    public function getApplications(Request $request)
    {
        $user = Auth::user();
        
        try {
            $filters = LeaveApplicationFilterDTO::fromRequest($request->all());
            $result = $this->leaveService->getPaginatedApplications($filters, $user);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب طلبات الإجازات بنجاح',
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
     *     path="/api/leaves/applications",
     *     summary="Create a new leave application",
     *     tags={"Leave Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"leave_type_id","from_date","to_date","reason"},
     *             @OA\Property(property="leave_type_id", type="integer", example=323, description="معرف نوع الإجازة - استخدم 311-316 أو 194,195,199,320-323"),
     *             @OA\Property(property="from_date", type="string", format="date", example="2025-12-01", description="تاريخ بداية الإجازة"),
     *             @OA\Property(property="to_date", type="string", format="date", example="2025-12-07", description="تاريخ نهاية الإجازة"),
     *             @OA\Property(property="reason", type="string", example="إجازة سنوية للراحة والاستجمام", description="سبب الإجازة (10 أحرف على الأقل)"),
     *             @OA\Property(property="duty_employee_id", type="integer", example=37, description="معرف الموظف البديل (اختياري) - يجب أن يكون من نفس الشركة: 36,37,118,702,703,725,726,744"),
     *             @OA\Property(property="is_half_day", type="boolean", example=false, description="هل الإجازة نصف يوم؟"),
     *             @OA\Property(property="leave_hours", type="string", example="8", description="عدد ساعات الإجازة"),
     *             @OA\Property(property="remarks", type="string", example="ملاحظات إضافية", description="ملاحظات (اختياري)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Leave application created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم إنشاء طلب الإجازة بنجاح"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(property="errors", type="object",
     *                 @OA\Property(property="leave_type_id", type="array", @OA\Items(type="string", example="نوع الإجازة غير متاح لشركتك")),
     *                 @OA\Property(property="duty_employee_id", type="array", @OA\Items(type="string", example="الموظف البديل يجب أن يكون من نفس الشركة ونشط")),
     *                 @OA\Property(property="reason", type="array", @OA\Items(type="string", example="سبب الإجازة يجب أن يكون 10 أحرف على الأقل"))
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    public function createApplication(CreateLeaveApplicationRequest $request)
    {
        $user = Auth::user();

        try {
            // الحصول على معرف الشركة الفعلي من attributes
            $effectiveCompanyId = $request->attributes->get('effective_company_id');
            
            $dto = CreateLeaveApplicationDTO::fromRequest(
                $request->validated(),
                $effectiveCompanyId,
                $user->user_id
            );

            $application = $this->leaveService->createApplication($dto);

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء طلب الإجازة بنجاح',
                'data' => $application->toArray()
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل في إنشاء طلب الإجازة',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/leaves/applications/{id}",
     *     summary="Get a specific leave application",
     *     tags={"Leave Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Leave application retrieved successfully"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Leave application not found"
     *     )
     * )
     */
    public function showApplication(int $id, Request $request)
    {
        $user = Auth::user();

        try {
            // الحصول على معرف الشركة الفعلي من attributes
            $effectiveCompanyId = $request->attributes->get('effective_company_id');
            
            if (in_array($user->user_type, ['company', 'admin', 'hr', 'manager'])) {
                $application = $this->leaveService->getApplicationById($id, $effectiveCompanyId);
            } else {
                $application = $this->leaveService->getApplicationById($id, null, $user->user_id);
            }

            if (!$application) {
                return response()->json([
                    'success' => false,
                    'message' => 'طلب الإجازة غير موجود'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $application->toArray()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في جلب طلب الإجازة',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/leaves/applications/{id}",
     *     summary="Update a leave application (Employee owner only)",
     *     description="Updates a leave application. Only the employee who created the application can update it. Managers and company owners cannot update employee applications.",
     *     tags={"Leave Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="from_date", type="string", format="date"),
     *             @OA\Property(property="to_date", type="string", format="date"),
     *             @OA\Property(property="reason", type="string"),
     *             @OA\Property(property="remarks", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Leave application updated successfully"
     *     )
     * )
     */
    public function updateApplication(UpdateLeaveApplicationRequest $request, int $id)
    {
        $user = Auth::user();

        try {
            \Log::info('Update Application Request', [
                'user_id' => $user->user_id,
                'application_id' => $id,
                'request_data' => $request->validated()
            ]);
            
            $dto = UpdateLeaveApplicationDTO::fromRequest($request->validated());
            $application = $this->leaveService->updateApplication($id, $dto, $user);

            if (!$application) {
                return response()->json([
                    'success' => false,
                    'message' => 'طلب الإجازة غير موجود أو غير مصرح لك بتعديله'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث طلب الإجازة بنجاح',
                'data' => $application->toArray()
            ]);

        } catch (\Exception $e) {
            \Log::error('Update Application Error', [
                'user_id' => $user->user_id,
                'application_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/leaves/applications/{id}/cancel",
     *     summary="Cancel a leave application (mark as rejected)",
     *     description="Cancels a leave application by marking it as rejected. The application remains in the database for audit purposes with status 'rejected' and remarks indicating it was cancelled by the employee.",
     *     tags={"Leave Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Leave application ID to cancel",
     *         @OA\Schema(type="integer", example=123)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Leave application cancelled successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم إلغاء طلب الإجازة بنجاح")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Leave application not found or cannot be cancelled",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="طلب الإجازة غير موجود أو لا يمكن إلغاؤه")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Cannot cancel processed application",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="لا يمكن إلغاء طلب تم الموافقة عليه مسبقاً")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized - Invalid or missing token"
     *     )
     * )
     */
    public function cancelApplication(int $id)
    {
        $user = Auth::user();

        try {
            $success = $this->leaveService->cancelApplication($id, $user);

            if (!$success) {
                return response()->json([
                    'success' => false,
                    'message' => 'طلب الإجازة غير موجود أو لا يمكن إلغاؤه'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'تم إلغاء طلب الإجازة بنجاح'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/leaves/applications/{id}",
     *     summary="Delete a leave application permanently (Employee owner only)",
     *     description="Permanently deletes a leave application from the database. Only the employee who created the application can delete it. Managers and company owners cannot delete employee applications. Only pending applications can be deleted.",
     *     tags={"Leave Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Leave application ID to delete permanently",
     *         @OA\Schema(type="integer", example=123)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Leave application deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم حذف طلب الإجازة نهائياً")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Leave application not found or cannot be deleted",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="طلب الإجازة غير موجود أو لا يمكن حذفه")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Cannot delete processed application",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="لا يمكن حذف الطلب بعد الموافقة عليه")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized - Invalid or missing token"
     *     )
     * )
     */
    public function deleteApplication(int $id)
    {
        $user = Auth::user();

        try {
            $success = $this->leaveService->deleteApplication($id, $user);

            if (!$success) {
                return response()->json([
                    'success' => false,
                    'message' => 'طلب الإجازة غير موجود أو لا يمكن حذفه'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'تم حذف طلب الإجازة نهائياً'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/leaves/adjustments",
     *     summary="Get leave adjustments",
     *     tags={"Leave Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Leave adjustments retrieved successfully"
     *     )
     * )
     */
    public function getAdjustments(Request $request)
    {
        $user = Auth::user();
        
        // Build filter DTO
        $filterData = $request->all();
        
        // Role-based filtering
        if (in_array($user->user_type, ['company', 'admin', 'hr', 'manager'])) {
            $filterData['company_name'] = $user->company_name;
        } else {
            $filterData['employee_id'] = $user->user_id;
        }

        $filters = LeaveAdjustmentFilterDTO::fromRequest($filterData);
        $result = $this->leaveService->getPaginatedAdjustments($filters);

        return response()->json([
            'success' => true,
            'message' => 'تم جلب تسويات الإجازات بنجاح',
            ...$result
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/leaves/adjustments",
     *     summary="Create a new leave adjustment",
     *     tags={"Leave Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"leave_type_id","adjust_hours","reason_adjustment"},
     *             @OA\Property(property="leave_type_id", type="integer", example=1),
     *             @OA\Property(property="adjust_hours", type="string", example="8"),
     *             @OA\Property(property="reason_adjustment", type="string", example="تسوية إجازة متراكمة"),
     *             @OA\Property(property="adjustment_date", type="string", format="date", example="2025-11-15"),
     *             @OA\Property(property="duty_employee_id", type="integer", example=25)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Leave adjustment created successfully"
     *     )
     * )
     */
    public function createAdjustment(CreateLeaveAdjustmentRequest $request)
    {
        $user = Auth::user();

        try {
            // For company users, use 0 as company_id, for others use their actual company_id
            $companyId = $user->user_type === 'company' ? 0 : $user->company_id;
            
            $dto = CreateLeaveAdjustmentDTO::fromRequest(
                $request->validated(),
                $companyId,
                $user->user_id
            );

            $adjustment = $this->leaveService->createAdjustment($dto);

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء طلب التسوية بنجاح',
                'data' => $adjustment
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل في إنشاء طلب التسوية',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/leaves/types",
     *     summary="Get available leave types",
     *     tags={"Leave Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Leave types retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="leave_type_id", type="integer", example=1),
     *                 @OA\Property(property="leave_type_name", type="string", example="إجازة سنوية"),
     *                 @OA\Property(property="leave_type_short_name", type="string", example="سنوية"),
     *                 @OA\Property(property="leave_days", type="integer", example=30),
     *                 @OA\Property(property="leave_type_status", type="boolean", example=true)
     *             ))
     *         )
     *     )
     * )
     */
    public function getLeaveTypes()
    {
        $user = Auth::user();
        
        // Get leave types for the user's company and general types
        $leaveTypes = ErpConstant::getActiveLeaveTypesByCompanyName($user->company_name);
        
        // Transform data to match expected format
        $formattedTypes = $leaveTypes->map(function($constant) {
            return [
                'leave_type_id' => $constant->constants_id,
                'leave_type_name' => $constant->leave_type_name,
                'leave_type_short_name' => $constant->leave_type_short_name,
                'leave_days' => $constant->leave_days,
                'leave_type_status' => $constant->leave_type_status,
                'company_id' => $constant->company_id,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $formattedTypes,
            'message' => 'تم جلب أنواع الإجازات بنجاح'
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/leaves/types",
     *     summary="Create a new leave type (HR/Admin only)",
     *     tags={"Leave Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"leave_type_name","leave_days"},
     *             @OA\Property(property="leave_type_name", type="string", example="إجازة دراسية"),
     *             @OA\Property(property="leave_type_short_name", type="string", example="دراسية"),
     *             @OA\Property(property="leave_days", type="integer", example=10)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Leave type created successfully"
     *     )
     * )
     */
    public function createLeaveType(Request $request)
    {
        $user = Auth::user();

        // Only HR and Admin can create leave types
        if (!in_array($user->user_type, ['company', 'admin', 'hr'])) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح لك بإنشاء أنواع إجازات جديدة'
            ], 403);
        }

        $request->validate([
            'leave_type_name' => 'required|string|max:255',
            'leave_type_short_name' => 'nullable|string|max:100',
            'leave_days' => 'required|integer|min:0|max:365',
        ]);

        $leaveType = ErpConstant::createLeaveType(
            $user->company_id,
            $request->leave_type_name,
            $request->leave_type_short_name,
            $request->leave_days
        );

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء نوع الإجازة بنجاح',
            'data' => $leaveType
        ], 201);
    }

    /**
     * @OA\Post(
     *     path="/api/leaves/applications/{id}/approve",
     *     summary="Approve leave application (Managers/HR only)",
     *     tags={"Leave Management"},
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
     *         description="Leave application approved successfully"
     *     )
     * )
     */
    public function approveApplication(Request $request, int $id)
    {
        $user = Auth::user();

        if (!in_array($user->user_type, ['company', 'admin', 'hr', 'manager'])) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح لك بالموافقة على الطلبات'
            ], 403);
        }

        $application = LeaveApplication::forCompany($user->company_id)->findOrFail($id);

        if ($application->status) {
            return response()->json([
                'success' => false,
                'message' => 'تم الموافقة على هذا الطلب مسبقاً'
            ], 422);
        }

        $request->validate([
            'remarks' => 'nullable|string|max:1000'
        ]);

        $application->update([
            'status' => true,
            'remarks' => $request->remarks,
        ]);

        $application->load(['employee', 'dutyEmployee', 'leaveType']);

        return response()->json([
            'success' => true,
            'message' => 'تم الموافقة على طلب الإجازة بنجاح',
            'data' => $application
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/leaves/adjustments/{id}/approve",
     *     summary="Approve leave adjustment (Managers/HR only)",
     *     tags={"Leave Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Leave adjustment approved successfully"
     *     )
     * )
     */
    public function approveAdjustment(int $id)
    {
        $user = Auth::user();

        if (!in_array($user->user_type, ['company', 'admin', 'hr', 'manager'])) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح لك بالموافقة على الطلبات'
            ], 403);
        }

        $adjustment = LeaveAdjustment::forCompany($user->company_id)->findOrFail($id);

        if ($adjustment->status !== LeaveAdjustment::STATUS_PENDING) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن الموافقة على هذا الطلب'
            ], 422);
        }

        $adjustment->update([
            'status' => LeaveAdjustment::STATUS_APPROVED,
        ]);

        $adjustment->load(['employee', 'dutyEmployee', 'leaveType']);

        return response()->json([
            'success' => true,
            'message' => 'تم الموافقة على طلب التسوية بنجاح',
            'data' => $adjustment
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/leaves/stats",
     *     summary="Get leave statistics (Managers/HR only)",
     *     tags={"Leave Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Leave statistics retrieved successfully"
     *     )
     * )
     */
    public function getStats()
    {
        $user = Auth::user();

        if (!in_array($user->user_type, ['company', 'admin', 'hr', 'manager'])) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح لك بعرض الإحصائيات'
            ], 403);
        }

        $applicationStats = [
            'total_applications' => LeaveApplication::forCompany($user->company_id)->count(),
            'pending_applications' => LeaveApplication::forCompany($user->company_id)->withStatus(false)->count(),
            'approved_applications' => LeaveApplication::forCompany($user->company_id)->withStatus(true)->count(),
        ];

        $adjustmentStats = [
            'total_adjustments' => LeaveAdjustment::forCompany($user->company_id)->count(),
            'pending_adjustments' => LeaveAdjustment::forCompany($user->company_id)->withStatus(LeaveAdjustment::STATUS_PENDING)->count(),
            'approved_adjustments' => LeaveAdjustment::forCompany($user->company_id)->withStatus(LeaveAdjustment::STATUS_APPROVED)->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'applications' => $applicationStats,
                'adjustments' => $adjustmentStats,
            ]
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/leaves/adjustments/{id}",
     *     summary="Update a leave adjustment",
     *     description="Updates a leave adjustment. Only pending adjustments can be updated.",
     *     tags={"Leave Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Leave adjustment ID to update",
     *         @OA\Schema(type="integer", example=123)
     *     ),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="leave_type_id", type="integer", example=323),
     *             @OA\Property(property="adjust_hours", type="string", example="8"),
     *             @OA\Property(property="reason_adjustment", type="string", example="تحديث سبب التسوية"),
     *             @OA\Property(property="adjustment_date", type="string", format="date", example="2025-12-01"),
     *             @OA\Property(property="duty_employee_id", type="integer", example=37)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Leave adjustment updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم تحديث تسوية الإجازة بنجاح")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Leave adjustment not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="تسوية الإجازة غير موجودة أو غير مصرح لك بتعديلها")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Cannot update processed adjustment",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="لا يمكن تعديل التسوية بعد المراجعة")
     *         )
     *     )
     * )
     */
    public function updateAdjustment(UpdateLeaveAdjustmentRequest $request, int $id)
    {
        $user = Auth::user();

        try {
            $dto = UpdateLeaveAdjustmentDTO::fromRequest($request->validated());
            $adjustment = $this->leaveService->updateAdjustment($id, $dto, $user->user_id);

            if (!$adjustment) {
                return response()->json([
                    'success' => false,
                    'message' => 'تسوية الإجازة غير موجودة أو غير مصرح لك بتعديلها'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث تسوية الإجازة بنجاح',
                'data' => $adjustment->toArray()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/leaves/adjustments/{id}/cancel",
     *     summary="Cancel a leave adjustment (mark as rejected)",
     *     description="Cancels a leave adjustment by marking it as rejected. The adjustment remains in the database for audit purposes.",
     *     tags={"Leave Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Leave adjustment ID to cancel",
     *         @OA\Schema(type="integer", example=123)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Leave adjustment cancelled successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم إلغاء تسوية الإجازة بنجاح")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Leave adjustment not found or cannot be cancelled",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="تسوية الإجازة غير موجودة أو لا يمكن إلغاؤها")
     *         )
     *     )
     * )
     */
    public function cancelAdjustment(int $id)
    {
        $user = Auth::user();

        try {
            $success = $this->leaveService->cancelAdjustment($id, $user->user_id);

            if (!$success) {
                return response()->json([
                    'success' => false,
                    'message' => 'تسوية الإجازة غير موجودة أو لا يمكن إلغاؤها'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'تم إلغاء تسوية الإجازة بنجاح'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/leaves/adjustments/{id}",
     *     summary="Delete a leave adjustment permanently",
     *     description="Permanently deletes a leave adjustment from the database. This action cannot be undone. Only pending adjustments can be deleted.",
     *     tags={"Leave Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Leave adjustment ID to delete permanently",
     *         @OA\Schema(type="integer", example=123)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Leave adjustment deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم حذف تسوية الإجازة نهائياً")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Leave adjustment not found or cannot be deleted",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="تسوية الإجازة غير موجودة أو لا يمكن حذفها")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Cannot delete processed adjustment",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="لا يمكن حذف التسوية بعد الموافقة عليها")
     *         )
     *     )
     * )
     */
    public function deleteAdjustment(int $id)
    {
        $user = Auth::user();

        try {
            $success = $this->leaveService->deleteAdjustment($id, $user->user_id);

            if (!$success) {
                return response()->json([
                    'success' => false,
                    'message' => 'تسوية الإجازة غير موجودة أو لا يمكن حذفها'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'تم حذف تسوية الإجازة نهائياً'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }


}
