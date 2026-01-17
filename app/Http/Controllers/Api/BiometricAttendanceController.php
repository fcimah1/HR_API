<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\BiometricPunchRequest;
use App\Http\Requests\Attendance\BiometricBulkLogsRequest;
use App\Models\BiometricLog;
use App\Models\User;
use App\Models\UserDetails;
use App\Repository\Interface\UserRepositoryInterface;
use App\Services\AttendanceService;
use Illuminate\Support\Facades\DB;
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
        private AttendanceService $attendanceService,
        private UserRepositoryInterface $userRepository
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
                employeeId: $request->employee_id,
                punchTime: $request->punch_time,
                verifyMode: $request->verify_mode,
                punchType: $request->punch_type,
                workCode: $request->work_code
            );

            Log::info('Biometric punch processed', [
                'company_id' => $request->company_id,
                'kiosk_code' => $request->employee_id,
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

            // تحديد رسالة الخطأ بناءً على نوع الخطأ
            $errorMessage = $e->getMessage();

            if (str_contains($errorMessage, 'Column not found')) {
                $userMessage = 'حدث خطأ في قاعدة البيانات. يرجى التواصل مع الدعم الفني.';
            } elseif (str_contains($errorMessage, 'Duplicate entry')) {
                $userMessage = 'هذه البصمة مسجلة مسبقاً.';
            } elseif (str_contains($errorMessage, 'employee_id') || str_contains($errorMessage, 'company_id')) {
                $userMessage = 'بيانات الموظف أو الشركة غير صحيحة.';
            } else {
                $userMessage = 'فشل تسجيل البصمة. يرجى المحاولة مرة أخرى.';
            }

            return response()->json([
                'success' => false,
                'message' => $userMessage,
                'error_code' => 'BIOMETRIC_PUNCH_FAILED',
                'details' => app()->environment('local') ? $errorMessage : null
            ], 400);
        }
    }

    /**
     * استقبال مجموعة من سجلات البصمة دفعة واحدة
     * Receive bulk biometric logs from device
     * 
     * @OA\Post(
     *     path="/api/biometric/logs",
     *     operationId="storeBulkBiometricLogs",
     *     summary="تخزين مجموعة من سجلات البصمة",
     *     description="استقبال وتخزين مجموعة من سجلات البصمة من الجهاز دفعة واحدة. يتم تخزين السجلات في جدول منفصل ليتم معالجتها لاحقاً.",
     *     tags={"Biometric Attendance"},
     *     @OA\RequestBody(
     *         required=true,
     *         description="بيانات سجلات البصمة من الجهاز",
     *         @OA\JsonContent(ref="#/components/schemas/BiometricBulkLogsRequest")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="تم تخزين السجلات بنجاح",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم تخزين 10 سجل بنجاح"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="total_received", type="integer", example=10, description="عدد السجلات المستلمة"),
     *                 @OA\Property(property="total_stored", type="integer", example=10, description="عدد السجلات المخزنة"),
     *                 @OA\Property(property="total_matched", type="integer", example=8, description="عدد السجلات المطابقة لموظفين في النظام"),
     *                 @OA\Property(property="total_unmatched", type="integer", example=2, description="عدد السجلات غير المطابقة")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="خطأ في البيانات المدخلة",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="خطأ في البيانات المدخلة"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="خطأ في الخادم",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="حدث خطأ أثناء تخزين السجلات")
     *         )
     *     )
     * )
     */
    public function storeBulkLogs(BiometricBulkLogsRequest $request)
    {
        try {
            $logs = $request->getLogs();

            $totalReceived = count($logs);
            $totalStored = 0;
            $totalMatched = 0;
            $totalUnmatched = 0;
            $totalProcessed = 0;
            $processingErrors = [];
            $unmatchedEmployees = []; // قائمة الموظفين غير الموجودين

            // تجميع الـ logs حسب company_id و branch_id للبحث الفعال
            $groupedLogs = collect($logs)->groupBy(function ($log) {
                return $log['company_id'] . '_' . $log['branch_id'];
            });

            // DEBUG: عرض البيانات المستلمة
            Log::info('Biometric bulk logs - Received data', [
                'total_logs' => count($logs),
                'first_log_sample' => $logs[0] ?? null,
                'grouped_keys' => $groupedLogs->keys()->toArray(),
            ]);

            // إنشاء cache للموظفين لتجنب queries متكررة
            $employeeCache = [];

            foreach ($groupedLogs as $key => $logsGroup) {
                [$companyId, $branchId] = explode('_', $key);
                $companyId = (int) $companyId;
                $branchId = (int) $branchId;

                $kioskCodes = $logsGroup->pluck('employee_id')->unique()->toArray();

                // DEBUG: عرض الموظفين المطلوب البحث عنهم
                Log::info('Biometric bulk logs - Searching employees', [
                    'company_id' => $companyId,
                    'branch_id' => $branchId,
                    'kiosk_codes_from_device' => $kioskCodes,
                ]);

                // البحث عن الموظفين باستخدام Repository
                $matchedEmployees = $this->userRepository->getUsersByKioskCodes($companyId, $branchId, $kioskCodes);

                // DEBUG: عرض نتائج البحث
                Log::info('Biometric bulk logs - Database match results', [
                    'company_id' => $companyId,
                    'branch_id' => $branchId,
                    'matched_count' => count($matchedEmployees),
                ]);

                // تخزين في الـ cache
                foreach ($matchedEmployees as $kioskCode => $userId) {
                    $cacheKey = "{$companyId}_{$branchId}_{$kioskCode}";
                    $employeeCache[$cacheKey] = $userId;
                }

                // DEBUG: عرض الـ cache المبني
                Log::info('Biometric - Cache built', [
                    'cache_keys' => array_keys($employeeCache),
                    'cache_details' => array_map(function ($key, $userId) {
                        return "{$key} => {$userId}";
                    }, array_keys($employeeCache), $employeeCache),
                ]);
            }

            DB::beginTransaction();

            $totalDuplicated = 0;

            foreach ($logs as $log) {
                $companyId = $log['company_id'];
                $branchId = $log['branch_id'];
                $kioskCode = $log['employee_id']; // kiosk_code من جهاز البصمة
                $punchTime = $log['punch_time'];

                // التحقق من وجود سجل مكرر
                $existingLog = BiometricLog::where('company_id', $companyId)
                    ->where('branch_id', $branchId)
                    ->where('kiosk_code', $kioskCode)
                    ->where('punch_time', $punchTime)
                    ->first();

                if ($existingLog) {
                    $totalDuplicated++;
                    continue; // تخطي السجل المكرر
                }

                // البحث في الـ cache
                // تحويل kiosk_code للتطابق مع الـ cache keys
                $kioskCodeNormalized = is_numeric($kioskCode) ? (int)$kioskCode : $kioskCode;
                $cacheKey = "{$companyId}_{$branchId}_{$kioskCodeNormalized}";
                $userId = $employeeCache[$cacheKey] ?? null;

                if ($userId) {
                    $totalMatched++;
                } else {
                    $totalUnmatched++;
                    // إضافة الموظف غير الموجود للقائمة (بدون تكرار)
                    $unmatchedKey = "{$companyId}_{$branchId}_{$kioskCode}";
                    if (!isset($unmatchedEmployees[$unmatchedKey])) {
                        $unmatchedEmployees[$unmatchedKey] = [
                            'company_id' => $companyId,
                            'branch_id' => $branchId,
                            'kiosk_code' => $kioskCode,
                        ];
                    }
                    continue; // تخطي السجل إذا لم يتم العثور على الموظف
                }

                // 1. تخزين في ci_biometric_logs (فقط للموظفين الموجودين)
                $biometricLog = BiometricLog::create([
                    'company_id' => $companyId,
                    'branch_id' => $branchId,
                    'kiosk_code' => $kioskCode,
                    'user_id' => $userId,
                    'punch_time' => $punchTime,
                    'punch_type' => $log['punch_type'],
                    'verify_mode' => $log['verify_mode'],
                    'raw_data' => $log,
                    'is_processed' => false,
                ]);

                $totalStored++;

                // 2. التسجيل المباشر في ci_timesheet (إذا كان الموظف موجود)
                if ($userId) {
                    try {
                        $result = $this->attendanceService->biometricPunch(
                            companyId: $companyId,
                            branchId: $branchId,
                            employeeId: $kioskCode, // kiosk_code يُمرر كـ employeeId للـ service
                            punchTime: $log['punch_time'],
                            verifyMode: $log['verify_mode'],
                            punchType: $log['punch_type'],
                            workCode: $log['work_code'] ?? null
                        );

                        // تحديث السجل كمعالج
                        $biometricLog->markAsProcessed(
                            attendanceId: $result['data']['attendance_id'] ?? null,
                            notes: $result['message'] ?? 'تمت المعالجة بنجاح'
                        );

                        $totalProcessed++;
                    } catch (\Exception $e) {
                        // تسجيل الخطأ وإكمال المعالجة
                        $biometricLog->update([
                            'processing_notes' => 'فشل: ' . $e->getMessage(),
                        ]);

                        $processingErrors[] = [
                            'kiosk_code' => $kioskCode,
                            'error' => $e->getMessage(),
                        ];

                        Log::warning('Direct biometric processing failed', [
                            'kiosk_code' => $kioskCode,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            DB::commit();

            Log::info('Bulk biometric logs stored and processed', [
                'total_received' => $totalReceived,
                'total_stored' => $totalStored,
                'total_duplicated' => $totalDuplicated,
                'total_matched' => $totalMatched,
                'total_unmatched' => $totalUnmatched,
                'total_processed' => $totalProcessed,
                'errors_count' => count($processingErrors),
            ]);

            // بناء رسالة واضحة ومفصلة
            $successMessage = "تم استلام {$totalReceived} سجل";
            $detailedMessages = [];

            if ($totalStored > 0) {
                $detailedMessages[] = "✓ تم تخزين {$totalStored} سجل بنجاح";
            }

            if ($totalProcessed > 0) {
                $detailedMessages[] = "✓ تمت معالجة {$totalProcessed} سجل في جدول الحضور";
            }

            if ($totalDuplicated > 0) {
                $detailedMessages[] = "⚠ تم تخطي {$totalDuplicated} سجل مكرر";
            }

            if ($totalUnmatched > 0) {
                $detailedMessages[] = "✗ فشل {$totalUnmatched} سجل (موظف غير موجود في النظام)";
            }

            if (count($processingErrors) > 0) {
                $detailedMessages[] = "✗ فشلت معالجة " . count($processingErrors) . " سجل";
            }

            $fullMessage = $successMessage . "\n" . implode("\n", $detailedMessages);

            // تحديد حالة النجاح الكلية
            $isFullSuccess = ($totalUnmatched === 0 && count($processingErrors) === 0);
            $isPartialSuccess = ($totalProcessed > 0 && ($totalUnmatched > 0 || count($processingErrors) > 0));


            // بناء قائمة مفصلة بالموظفين غير الموجودين
            $unmatchedDetails = [];
            foreach ($unmatchedEmployees as $emp) {
                $unmatchedDetails[] = [
                    'company_id' => $emp['company_id'],
                    'branch_id' => $emp['branch_id'],
                    'kiosk_code' => $emp['kiosk_code'],
                    'error' => "الموظف بكود الكشك {$emp['kiosk_code']} غير موجود في الشركة {$emp['company_id']} - الفرع {$emp['branch_id']}",
                ];
            }

            // بناء قائمة مفصلة بأخطاء المعالجة
            $processingErrorsDetails = [];
            foreach ($processingErrors as $error) {
                $processingErrorsDetails[] = [
                    'kiosk_code' => $error['kiosk_code'],
                    'error' => $error['error'],
                    'error_type' => 'processing_failed',
                ];
            }

            // تحديد HTTP Status Code بناءً على النتيجة
            $httpStatusCode = 200;
            if ($totalUnmatched === $totalReceived) {
                // جميع السجلات فشلت
                $httpStatusCode = 422; // Unprocessable Entity
            } elseif ($totalUnmatched > 0 || count($processingErrors) > 0) {
                // نجاح جزئي
                $httpStatusCode = 207; // Multi-Status
            }

            return response()->json([
                'success' => $isFullSuccess || $isPartialSuccess,
                'status' => $isFullSuccess ? 'success' : ($isPartialSuccess ? 'partial_success' : 'failed'),
                'message' => $fullMessage,
                'summary' => [
                    'total_received' => $totalReceived,
                    'total_stored' => $totalStored,
                    'total_processed' => $totalProcessed,
                    'total_duplicated' => $totalDuplicated,
                    'total_matched' => $totalMatched,
                    'total_unmatched' => $totalUnmatched,
                    'total_failed' => count($processingErrors),
                ],
                'errors' => [
                    'has_errors' => ($totalUnmatched > 0 || count($processingErrors) > 0),
                    'unmatched_employees' => count($unmatchedEmployees) > 0 ? [
                        'count' => count($unmatchedEmployees),
                        'message' => 'تحذير: الموظفين التاليين غير موجودين في النظام ولم يتم تسجيل حضورهم',
                        'action_required' => 'يرجى التأكد من إضافة هؤلاء الموظفين في النظام أو تصحيح kiosk_code الخاص بهم',
                        'details' => $unmatchedDetails,
                    ] : null,
                    'processing_errors' => count($processingErrors) > 0 ? [
                        'count' => count($processingErrors),
                        'message' => 'فشلت معالجة السجلات التالية',
                        'action_required' => 'يرجى مراجعة الأخطاء التالية وإعادة المحاولة',
                        'details' => $processingErrorsDetails,
                    ] : null,
                ],
            ], $httpStatusCode);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Bulk biometric logs storage failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => 'حدث خطأ أثناء تخزين السجلات',
                'error' => [
                    'type' => 'server_error',
                    'message' => $e->getMessage(),
                    'details' => config('app.debug') ? [
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'trace' => explode("\n", $e->getTraceAsString()),
                    ] : null,
                ],
                'help' => 'يرجى التحقق من صحة البيانات المرسلة أو التواصل مع الدعم الفني',
            ], 500);
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
