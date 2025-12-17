<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\BiometricPunchRequest;
use App\Services\AttendanceService;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="Biometric Attendance",
 *     description="تسجيل الحضور والانصراف من أجهزة البصمة"
 * )
 */
class BiometricAttendanceController extends Controller
{
    public function __construct(
        private AttendanceService $attendanceService
    ) {}

    /**
     * استقبال بيانات البصمة من الجهاز
     * Receive punch data from biometric device
     * 
     * @OA\Post(
     *     path="/api/biometric/punch",
     *     operationId="biometricPunch",
     *     summary="تسجيل الحضور/الانصراف من جهاز البصمة",
     *     description="استقبال بيانات البصمة من الجهاز وتسجيلها تلقائياً كحضور أو انصراف",
     *     tags={"Biometric Attendance"},
     *     @OA\RequestBody(
     *         required=true,
     *         description="بيانات البصمة من الجهاز",
     *         @OA\JsonContent(
     *             required={"company_id", "branch_id", "employee_id", "punch_time", "verify_mode", "punch_type"},
     *             @OA\Property(property="company_id", type="integer", example=24, description="رقم الشركة"),
     *             @OA\Property(property="branch_id", type="integer", example=1, description="رقم الفرع (0 إذا لم يوجد)"),
     *             @OA\Property(property="employee_id", type="string", example="073323", description="رقم الموظف في جهاز البصمة"),
     *             @OA\Property(property="punch_time", type="string", format="datetime", example="2025-12-14 08:00:00", description="وقت البصمة"),
     *             @OA\Property(property="verify_mode", type="integer", example=1, description="طريقة التحقق: 0=كلمة مرور, 1=بصمة, 2=بطاقة, 3=كلمة مرور+بصمة, 4=بطاقة+بصمة, 15=وجه"),
     *             @OA\Property(property="punch_type", type="integer", example=0, description="نوع البصمة: 0=حضور, 1=انصراف, 2=خروج استراحة, 3=عودة استراحة, 4=حضور عمل إضافي, 5=انصراف عمل إضافي, 255=غير محدد"),
     *             @OA\Property(property="work_code", type="integer", example=0, description="كود العمل/المشروع (اختياري)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="تم تسجيل البصمة بنجاح",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="type", type="string", enum={"clock_in", "clock_out"}, example="clock_in", description="نوع البصمة: حضور أو انصراف"),
     *             @OA\Property(property="message", type="string", example="تم تسجيل الحضور بنجاح"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="user_id", type="integer", example=755, description="رقم المستخدم في النظام"),
     *                 @OA\Property(property="employee_id", type="string", example="073323", description="رقم الموظف في جهاز البصمة"),
     *                 @OA\Property(property="punch_time", type="string", example="2025-12-14 08:00:00"),
     *                 @OA\Property(property="attendance_id", type="integer", example=1155, description="رقم سجل الحضور"),
     *                 @OA\Property(property="verify_mode", type="integer", example=1, description="طريقة التحقق"),
     *                 @OA\Property(property="verify_mode_text", type="string", example="بصمة", description="وصف طريقة التحقق"),
     *                 @OA\Property(property="punch_type", type="integer", example=0, description="نوع البصمة"),
     *                 @OA\Property(property="punch_type_text", type="string", example="حضور", description="وصف نوع البصمة"),
     *                 @OA\Property(property="work_code", type="integer", example=0, description="كود العمل"),
     *                 @OA\Property(property="total_work", type="string", example="09:00", nullable=true, description="إجمالي ساعات العمل (فقط عند الانصراف)")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="خطأ في العملية",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="الموظف غير موجود في النظام")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="خطأ في البيانات المدخلة",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="company_id",
     *                     type="array",
     *                     @OA\Items(type="string", example="حقل رقم الشركة مطلوب")
     *                 ),
     *                 @OA\Property(
     *                     property="employee_id",
     *                     type="array",
     *                     @OA\Items(type="string", example="حقل رقم الموظف مطلوب")
     *                 ),
     *                 @OA\Property(
     *                     property="branch_id",
     *                     type="array",
     *                     @OA\Items(type="string", example="حقل رقم الفرع مطلوب")
     *                 ),
     *                 @OA\Property(
     *                     property="punch_time",
     *                     type="array",
     *                     @OA\Items(type="string", example="صيغة وقت البصمة غير صحيحة")
     *                 ),
     *                 @OA\Property(
     *                     property="verify_mode",
     *                     type="array",
     *                     @OA\Items(type="string", example="نوع التحقق غير صالح")
     *                 ),
     *                 @OA\Property(
     *                     property="punch_type",
     *                     type="array",
     *                     @OA\Items(type="string", example="نوع البصمة غير صالح")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function punch(BiometricPunchRequest $request)
    {
        try {
            $result = $this->attendanceService->biometricPunch(
                companyId: $request->company_id,
                branchId: $request->branch_id,
                employeeIdnum: $request->employee_id,
                punchTime: $request->punch_time,
                verifyMode: $request->verify_mode,
                punchType: $request->punch_type,
                workCode: $request->work_code
            );

            Log::info('Biometric punch processed', [
                'company_id' => $request->company_id,
                'employee_idnum' => $request->employee_id,
                'type' => $result['type'] ?? 'unknown',
                'verify_mode' => $request->verify_mode,
                'punch_type' => $request->punch_type,
                'work_code' => $request->work_code,
            ]);

            return response()->json($result, 200);
        } catch (\Exception $e) {
            Log::error('Biometric punch failed', [
                'company_id' => $request->company_id,
                'employee_id' => $request->employee_id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * الحصول على الشركات مع الفروع
     * Get all companies with their branches
     * 
     * @OA\Get(
     *     path="/api/biometric/companies",
     *     operationId="getCompaniesWithBranches",
     *     summary="الحصول على الشركات والفروع",
     *     description="إرجاع قائمة الشركات مع الفروع التابعة لكل شركة",
     *     tags={"Biometric Attendance"},
     *     @OA\Response(
     *         response=200,
     *         description="قائمة الشركات والفروع",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="company_id", type="integer", example=24),
     *                     @OA\Property(property="company_name", type="string", example="شركة التقنية"),
     *                     @OA\Property(
     *                         property="branches",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="branch_id", type="integer", example=1),
     *                             @OA\Property(property="branch_name", type="string", example="الفرع الرئيسي"),
     *                             @OA\Property(property="coordinates", type="string", example="24.7136,46.6753")
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function getCompaniesWithBranches()
    {
        try {
            // جلب الشركات مع الفروع باستخدام Eager Loading
            $companies = \App\Models\User::where('user_type', 'company')
                ->where('is_active', 1)
                ->with(['branches:branch_id,company_id,branch_name,description'])
                ->select(['user_id', 'company_name', 'trading_name'])
                ->get()
                ->map(function ($company) {
                    return [
                        'company_id' => $company->user_id,
                        'company_name' => $company->company_name ?? $company->trading_name,
                        'branches' => $company->branches->map(function ($branch) {
                            return [
                                'branch_id' => $branch->branch_id,
                                'branch_name' => $branch->branch_name,
                            ];
                        })
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $companies
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
