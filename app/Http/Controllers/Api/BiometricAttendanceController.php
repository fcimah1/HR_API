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
     *             required={"company_id", "branch_id", "employee_id", "punch_time"},
     *             @OA\Property(property="company_id", type="integer", example=24, description="رقم الشركة"),
     *             @OA\Property(property="branch_id", type="integer", example=1, description="رقم الفرع (0 إذا لم يوجد)"),
     *             @OA\Property(property="employee_id", type="string", example="073323", description="رقم الموظف في جهاز البصمة"),
     *             @OA\Property(property="punch_time", type="string", format="datetime", example="2025-12-14 08:00:00", description="وقت البصمة")
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
     *                     property="punch_time",
     *                     type="array",
     *                     @OA\Items(type="string", example="صيغة وقت البصمة غير صحيحة")
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
                employeeId: $request->employee_id,
                punchTime: $request->punch_time
            );

            Log::info('Biometric punch processed', [
                'company_id' => $request->company_id,
                'employee_id' => $request->employee_id,
                'type' => $result['type'] ?? 'unknown',
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
}
