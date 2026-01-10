<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\DTOs\InternalHelpdesk\CloseInternalTicketDTO;
use App\DTOs\InternalHelpdesk\CreateInternalReplyDTO;
use App\DTOs\InternalHelpdesk\CreateInternalTicketDTO;
use App\DTOs\InternalHelpdesk\InternalTicketFilterDTO;
use App\DTOs\InternalHelpdesk\UpdateInternalTicketDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\InternalHelpdesk\CloseInternalTicketRequest;
use App\Http\Requests\InternalHelpdesk\CreateInternalReplyRequest;
use App\Http\Requests\InternalHelpdesk\CreateInternalTicketRequest;
use App\Http\Requests\InternalHelpdesk\GetInternalTicketsRequest;
use App\Http\Requests\InternalHelpdesk\UpdateInternalTicketRequest;
use App\Http\Resources\InternalTicketResource;
use App\Models\InternalSupportTicket;
use App\Models\User;
use App\Services\InternalHelpdeskService;
use App\Services\SimplePermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="Internal Helpdesk",
 *     description="التذاكر الداخلية للدعم الفني"
 * )
 */
class InternalHelpdeskController extends Controller
{
    public function __construct(
        protected InternalHelpdeskService $ticketService,
        protected SimplePermissionService $permissionService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/internal-helpdesk/enums",
     *     summary="الحصول على الحالات والأولويات المتاحة",
     *     description="يُرجع قوائم الحالات (statuses) والأولويات (priorities) المتاحة للتذاكر",
     *     operationId="getInternalHelpdeskEnums",
     *     tags={"Internal Helpdesk"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="نجاح",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="statuses", type="array", @OA\Items(
     *                     @OA\Property(property="value", type="integer"),
     *                     @OA\Property(property="name", type="string"),
     *                     @OA\Property(property="label", type="string"),
     *                     @OA\Property(property="label_en", type="string")
     *                 )),
     *                 @OA\Property(property="priorities", type="array", @OA\Items(
     *                     @OA\Property(property="value", type="integer"),
     *                     @OA\Property(property="name", type="string"),
     *                     @OA\Property(property="label", type="string"),
     *                     @OA\Property(property="label_en", type="string")
     *                 ))
     *             )
     *         )
     *     ),
     *     @OA\Response(response=500, description="خطأ في الخادم"),
     *     @OA\Response(response=401, description="غير مصرح يجب تسجيل الدخول"),
     *     @OA\Response(response=403, description="ليس لديك الصلاحية للوصول إلى هذه البيانات"),
     *     @OA\Response(response=422, description="خطأ في البيانات المدخلة")
     * )
     */
    public function getEnums(): JsonResponse
    {
        try {
            $enums = $this->ticketService->getEnums();
            return response()->json([
                'success' => true,
                'data' => $enums,
            ]);
        } catch (\Exception $e) {
            Log::error('Error in InternalHelpdeskController@getEnums', [
                'success' => false,
                'error' => $e->getMessage(),
                'message_ar' => 'حدث خطأ أثناء جلب الأعداد',
                'message_en' => 'Error fetching enums',
            ]);

            return response()->json([
                'success' => false,
                'message_ar' => 'حدث خطأ',
                'message_en' => 'Error occurred',
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/internal-helpdesk/departments",
     *     summary="الحصول على الأقسام المتاحة",
     *     description="Company: كل الأقسام | Staff: الأقسام التي بها subordinates + قسمه الشخصي",
     *     operationId="getInternalHelpdeskDepartments",
     *     tags={"Internal Helpdesk"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="نجاح",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="department_id", type="integer", example=165),
     *                 @OA\Property(property="department_name", type="string", example="التطوير والبرمجة")
     *             ))
     *         )
     *     ),
     *     @OA\Response(response=500, description="خطأ في الخادم"),
     *     @OA\Response(response=401, description="غير مصرح يجب تسجيل الدخول"),
     *     @OA\Response(response=403, description="ليس لديك الصلاحية للوصول إلى هذه البيانات"),
     *     @OA\Response(response=422, description="خطأ في البيانات المدخلة")
     * )
     */
    public function getDepartments(): JsonResponse
    {
        try {
            $user = Auth::user();
            $result = $this->ticketService->getDepartments($user);
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Error in InternalHelpdeskController@getDepartments', [
                'success' => false,
                'error' => $e->getMessage(),
                'message_ar' => 'حدث خطأ أثناء جلب الأقسام',
                'message_en' => 'Error fetching departments',
            ]);

            return response()->json([
                'success' => false,
                'message_ar' => 'حدث خطأ',
                'message_en' => 'Error occurred',
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/internal-helpdesk/employees/{departmentId}",
     *     summary="الحصول على موظفي قسم",
     *     description="Company: كل موظفي القسم | Staff: subordinates فقط + نفسه داخل القسم",
     *     operationId="getInternalHelpdeskEmployees",
     *     tags={"Internal Helpdesk"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="departmentId",
     *         in="path",
     *         required=true,
     *         description="معرف القسم",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="نجاح",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="user_id", type="integer", example=768),
     *                 @OA\Property(property="name", type="string", example="محمد أحمد")
     *             ))
     *         )
     *     ),
     *     @OA\Response(response=500, description="خطأ في الخادم"),
     *     @OA\Response(response=401, description="غير مصرح يجب تسجيل الدخول"),
     *     @OA\Response(response=403, description="ليس لديك الصلاحية للوصول إلى هذه البيانات"),
     *     @OA\Response(response=422, description="خطأ في البيانات المدخلة")
     * )
     */
    public function getEmployees(int $departmentId): JsonResponse
    {
        try {
            $user = Auth::user();
            $result = $this->ticketService->getEmployeesByDepartment($departmentId, $user);
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Error in InternalHelpdeskController@getEmployees', [
                'success' => false,
                'error' => $e->getMessage(),
                'message_ar' => 'حدث خطأ أثناء جلب الموظفين',
                'message_en' => 'Error fetching employees',
            ]);

            return response()->json([
                'success' => false,
                'message_ar' => 'حدث خطأ',
                'message_en' => 'Error occurred',
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/internal-helpdesk",
     *     summary="قائمة التذاكر الداخلية",
     *     description="Company: كل التذاكر | Staff: التذاكر الشخصية + تذاكر subordinates. مع دعم الترقيم والتصفية",
     *     operationId="listInternalTickets",
     *     tags={"Internal Helpdesk"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="page", in="query", description="رقم الصفحة", @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="per_page", in="query", description="عدد العناصر في الصفحة", @OA\Schema(type="integer", default=15)),
     *     @OA\Parameter(name="status", in="query", description="تصفية بالحالة", @OA\Schema(type="string", enum={"open", "closed"})),
     *     @OA\Parameter(name="priority", in="query", description="تصفية بالأولوية", @OA\Schema(type="string", enum={"low", "medium", "high", "critical"})),
     *     @OA\Parameter(name="department", in="query", description="معرف القسم", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="search", in="query", description="بحث في العنوان والوصف", @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="تم جلب التذاكر بنجاح",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/InternalTicketResource")),
     *             @OA\Property(property="meta", type="object",
     *                 @OA\Property(property="current_page", type="integer"),
     *                 @OA\Property(property="last_page", type="integer"),
     *                 @OA\Property(property="per_page", type="integer"),
     *                 @OA\Property(property="total", type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=500, description="خطأ في الخادم"),
     *     @OA\Response(response=422, description="خطأ في البيانات المدخلة"),
     *     @OA\Response(response=401, description="غير مصرح يجب تسجيل الدخول"),
     *     @OA\Response(response=403, description="ليس لديك الصلاحية للوصول إلى هذه البيانات")
     * )
     */
    public function index(GetInternalTicketsRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $isCompanyOwner = $this->ticketService->isCompanyOwner($user);
            $companyId = $this->permissionService->getEffectiveCompanyId($user);

            // جلب قائمة المستخدمين المسموح رؤية تذاكرهم حسب المستوى الوظيفي
            $allowedUserIds = null;
            if (!$isCompanyOwner) {
                $subordinates = $this->permissionService->getEmployeesByHierarchy($user->user_id, $companyId, true);
                $allowedUserIds = array_column($subordinates, 'user_id');
                // إضافة المستخدم نفسه
                if (!in_array($user->user_id, $allowedUserIds)) {
                    $allowedUserIds[] = $user->user_id;
                }
            }

            $filters = InternalTicketFilterDTO::fromRequest(
                $request->validated(),
                $companyId,
                $isCompanyOwner,
                $user->user_id,
                $allowedUserIds
            );

            $result = $this->ticketService->getPaginatedTickets($filters);
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Error in InternalHelpdeskController@index', [
                'success' => false,
                'error' => $e->getMessage(),
                'message_ar' => 'حدث خطأ أثناء جلب التذاكر',
                'message_en' => 'Error fetching tickets',
            ]);

            return response()->json([
                'success' => false,
                'message_ar' => 'حدث خطأ أثناء جلب التذاكر',
                'message_en' => 'Error fetching tickets',
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/internal-helpdesk",
     *     summary="إنشاء تذكرة داخلية",
     *     description="Company: ينشئ لأي موظف | Staff: ينشئ لنفسه أو لـ subordinates",
     *     operationId="createInternalTicket",
     *     tags={"Internal Helpdesk"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/CreateInternalTicketRequest")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="تم الإنشاء بنجاح",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message_ar", type="string"),
     *             @OA\Property(property="message_en", type="string"),
     *             @OA\Property(property="data", ref="#/components/schemas/InternalTicketResource")
     *         )
     *     ),
     *     @OA\Response(response=401, description="غير مصرح يجب تسجيل الدخول"),
     *     @OA\Response(response=403, description="لا يمكنك إنشاء تذكرة لهذا الموظف"),
     *     @OA\Response(response=422, description="خطأ في البيانات المدخلة"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function store(CreateInternalTicketRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $isCompanyOwner = $this->ticketService->isCompanyOwner($user);
            $companyId = $this->permissionService->getEffectiveCompanyId($user);

            // تحديد employee_id و department_id
            if ($isCompanyOwner) {
                // Company يختار أي موظف
                $employeeId = $request->validated()['employee_id'];
                $departmentId = $request->validated()['department_id'];
            } else {
                // Staff - يمكنه فتح تذكرة لنفسه أو لـ subordinates
                $requestedEmployeeId = $request->validated()['employee_id'] ?? null;

                if ($requestedEmployeeId && (int)$requestedEmployeeId != $user->user_id) {
                    // تحقق أن الموظف المطلوب من subordinates
                    $subordinates = $this->permissionService->getEmployeesByHierarchy($user->user_id, $companyId, true);
                    $allowedIds = array_map('intval', array_column($subordinates, 'user_id'));


                    if (!in_array((int)$requestedEmployeeId, $allowedIds, true)) {
                        Log::error('Error in InternalHelpdeskController@store', [
                            'success' => false,
                            'user_id' => $user->user_id,
                            'requested_employee_id' => $requestedEmployeeId,
                            'allowed_ids' => $allowedIds,
                            'message_ar' => 'لا يمكنك إنشاء تذكرة لهذا الموظف',
                            'message_en' => 'You cannot create ticket for this employee',
                        ]);
                        return response()->json([
                            'success' => false,
                            'message_ar' => 'لا يمكنك إنشاء تذكرة لهذا الموظف',
                            'message_en' => 'You cannot create ticket for this employee',
                        ], 403);
                    }

                    // استخدم قسم الموظف المطلوب
                    $targetUser = User::find($requestedEmployeeId);
                    $employeeId = $requestedEmployeeId;
                    $departmentId = $targetUser?->user_details?->department_id ?? 0;
                } else {
                    // التذكرة لنفسه
                    $employeeId = $user->user_id;
                    $departmentId = $user->user_details?->department_id ?? 0;
                }
            }

            $dto = CreateInternalTicketDTO::fromRequest(
                $request->validated(),
                $companyId,
                $user->user_id,
                $employeeId,
                $departmentId,
                InternalSupportTicket::generateTicketCode()
            );

            $result = $this->ticketService->createTicket($dto);
            return response()->json($result, 201);
        } catch (\Exception $e) {
            Log::error('Error in InternalHelpdeskController@store', [
                'success' => false,
                'error' => $e->getMessage(),
                'message_ar' => 'حدث خطأ أثناء إنشاء التذكرة',
                'message_en' => 'Error creating ticket',
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء إنشاء التذكرة',
                'message_en' => 'Error creating ticket',
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/internal-helpdesk/{id}",
     *     summary="تفاصيل تذكرة",
     *     description="عرض تفاصيل تذكرة معينة. يجب أن يكون لديك صلاحية الوصول للتذكرة",
     *     operationId="showInternalTicket",
     *     tags={"Internal Helpdesk"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="معرف التذكرة",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="نجاح",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/InternalTicketResource")
     *         )
     *     ),
     *     @OA\Response(response=401, description="غير مصرح يجب تسجيل الدخول"),
     *     @OA\Response(response=403, description="لا تملك صلاحية عرض هذه التذكرة"),
     *     @OA\Response(response=404, description="التذكرة غير موجودة"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function show(int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $result = $this->ticketService->getTicketById($id, $user);

            if (!$result['success']) {
                Log::error('Error in InternalHelpdeskController@show', [
                    'error' => $result['message'],
                    'ticket_id' => $id,
                    'message_ar' => 'التذكرة غير موجودة',
                    'message_en' => 'Ticket not found',
                ]);
                return response()->json([
                    'success' => false,
                    'message_ar' => 'التذكرة غير موجودة',
                    'message_en' => 'Ticket not found',
                ], 404);
            }

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Error in InternalHelpdeskController@show', [
                'error' => $e->getMessage(),
                'ticket_id' => $id,
                'message_ar' => 'حدث خطأ',
                'message_en' => 'Error occurred',
            ]);

            return response()->json([
                'success' => false,
                'message_ar' => 'حدث خطأ',
                'message_en' => 'Error occurred',
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/internal-helpdesk/{id}",
     *     summary="تحديث تذكرة (العنوان والأولوية فقط)",
     *     description="يمكن تحديث العنوان والأولوية فقط للتذاكر المفتوحة التي لديك صلاحية الوصول إليها",
     *     operationId="updateInternalTicket",
     *     tags={"Internal Helpdesk"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="معرف التذكرة",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="subject",
     *                 type="string",
     *                 maxLength=255,
     *                 example="مشكلة في النظام",
     *                 description="عنوان التذكرة"
     *             ),
     *             @OA\Property(
     *                 property="priority",
     *                 type="string",
     *                 enum={"low", "medium", "high", "urgent", "critical"},
     *                 example="high",
     *                 description="أولوية التذكرة (اسم الأولوية)"
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="تم التحديث بنجاح"),
     *     @OA\Response(response=401, description="غير مصرح يجب تسجيل الدخول"),
     *     @OA\Response(response=403, description="لا تملك صلاحية الوصول للتذكرة"),
     *     @OA\Response(response=404, description="التذكرة غير موجودة"),
     *     @OA\Response(response=422, description="خطأ في البيانات المدخلة"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function update(int $id, UpdateInternalTicketRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $dto = UpdateInternalTicketDTO::fromRequest($request->validated());
            $result = $this->ticketService->updateTicket($id, $dto, $user);

            if (!$result['success']) {
                Log::error('Error in InternalHelpdeskController@update', [
                    'error' => $result['message'],
                    'ticket_id' => $id,
                    'message_ar' => 'لا تملك صلاحية الوصول للتذكرة',
                    'message_en' => 'You do not have permission to access this ticket',
                ]);
                return response()->json([
                    'success' => false,
                    'message_ar' => 'لا تملك صلاحية الوصول للتذكرة',
                    'message_en' => 'You do not have permission to access this ticket',
                ], 403);
            }

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Error in InternalHelpdeskController@update', [
                'error' => $e->getMessage(),
                'ticket_id' => $id,
                'message_ar' => 'حدث خطأ',
                'message_en' => 'Error occurred',
            ]);

            return response()->json([
                'success' => false,
                'message_ar' => 'حدث خطأ',
                'message_en' => 'Error occurred',
            ], 500);
        }
    }

    // /**
    //  * @OA\Delete(
    //  *     path="/api/internal-helpdesk/{id}",
    //  *     summary="حذف تذكرة",
    //  *     operationId="deleteInternalTicket",
    //  *     tags={"Internal Helpdesk"},
    //  *     security={{"bearerAuth":{}}},
    //  *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
    //  *     @OA\Response(response=200, description="نجاح"),
    //  *     @OA\Response(response=403, description="لا يملك صلاحية")
    //  * )
    //  */
    // public function destroy(int $id): JsonResponse
    // {
    //     try {
    //         $user = Auth::user();
    //         $result = $this->ticketService->deleteTicket($id, $user);

    //         if (!$result['success']) {
    //             Log::error('Error in InternalHelpdeskController@destroy', [
    //                 'error' => $result['message'],
    //                 'ticket_id' => $id,
    //                 'message_ar' => 'لا تملك صلاحية حذف هذه التذكرة',
    //                 'message_en' => 'You do not have permission to delete this ticket',
    //             ]);
    //             return response()->json([
    //                 'success' => false,
    //                 'message_ar' => 'لا تملك صلاحية حذف هذه التذكرة',
    //                 'message_en' => 'You do not have permission to delete this ticket',
    //             ], 403);
    //         }

    //         return response()->json($result);
    //     } catch (\Exception $e) {
    //         Log::error('Error in InternalHelpdeskController@destroy', [
    //             'error' => $e->getMessage(),
    //             'ticket_id' => $id,
    //             'message_ar' => 'حدث خطأ',
    //             'message_en' => 'Error occurred',
    //         ]);

    //         return response()->json([
    //             'success' => false,
    //             'message_ar' => 'حدث خطأ',
    //             'message_en' => 'Error occurred',
    //         ], 500);
    //     }
    // }





    /**
     * @OA\Post(
     *     path="/api/internal-helpdesk/{id}/close",
     *     summary="إغلاق تذكرة",
     *     description="Company: يغلق أي تذكرة | Staff: يغلق تذاكر subordinates فقط (ليس تذكرته الشخصية)",
     *     operationId="closeInternalTicket",
     *     tags={"Internal Helpdesk"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="معرف التذكرة",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="ticket_remarks", type="string", description="ملاحظات الإغلاق")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="تم الإغلاق بنجاح",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message_ar", type="string"),
     *             @OA\Property(property="message_en", type="string"),
     *             @OA\Property(property="data", ref="#/components/schemas/InternalTicketResource")
     *         )
     *     ),
     *     @OA\Response(response=403, description="لا تملك صلاحية إغلاق هذه التذكرة"),
     *     @OA\Response(response=404, description="التذكرة غير موجودة"),
     *     @OA\Response(response=422, description="خطأ في البيانات المدخلة"),
     *     @OA\Response(response=401, description="غير مصرح يجب تسجيل الدخول"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function close(int $id, CloseInternalTicketRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $dto = CloseInternalTicketDTO::fromRequest($request->validated(), $user->user_id);
            $result = $this->ticketService->closeTicket($id, $dto, $user);

            if (!$result['success']) {
                Log::error('Error in InternalHelpdeskController@close', [
                    'error' => $result['message'],
                    'ticket_id' => $id,
                    'message_ar' => 'لا تملك صلاحية إغلاق هذه التذكرة',
                    'message_en' => 'You do not have permission to close this ticket',
                ]);
                return response()->json([
                    'success' => false,
                    'message_ar' => 'لا تملك صلاحية إغلاق هذه التذكرة',
                    'message_en' => 'You do not have permission to close this ticket',
                ], 403);
            }

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Error in InternalHelpdeskController@close', [
                'error' => $e->getMessage(),
                'ticket_id' => $id,
                'message_ar' => 'حدث خطأ',
                'message_en' => 'Error occurred',
            ]);

            return response()->json([
                'success' => false,
                'message_ar' => 'حدث خطأ',
                'message_en' => 'Error occurred',
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/internal-helpdesk/{id}/reopen",
     *     summary="إعادة فتح تذكرة",
     *     description="Company: يفتح أي تذكرة | Staff: يفتح تذاكر subordinates فقط (ليس تذكرته الشخصية)",
     *     operationId="reopenInternalTicket",
     *     tags={"Internal Helpdesk"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="معرف التذكرة",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="تم إعادة الفتح بنجاح",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message_ar", type="string"),
     *             @OA\Property(property="message_en", type="string"),
     *             @OA\Property(property="data", ref="#/components/schemas/InternalTicketResource")
     *         )
     *     ),
     *     @OA\Response(response=403, description="لا تملك صلاحية إعادة فتح هذه التذكرة"),
     *     @OA\Response(response=404, description="التذكرة غير موجودة"),
     *     @OA\Response(response=422, description="خطأ في البيانات المدخلة"),
     *     @OA\Response(response=401, description="غير مصرح يجب تسجيل الدخول"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function reopen(int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $result = $this->ticketService->reopenTicket($id, $user);

            if (!$result['success']) {
                Log::error('Error in InternalHelpdeskController@reopen', [
                    'error' => $result['message'],
                    'ticket_id' => $id,
                    'message_ar' => 'لا تملك صلاحية إعادة فتح هذه التذكرة',
                    'message_en' => 'You do not have permission to reopen this ticket',
                ]);
                return response()->json([
                    'success' => false,
                    'message_ar' => 'لا تملك صلاحية إعادة فتح هذه التذكرة',
                    'message_en' => 'You do not have permission to reopen this ticket',
                ], 403);
            }

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Error in InternalHelpdeskController@reopen', [
                'error' => $e->getMessage(),
                'ticket_id' => $id,
                'message_ar' => 'حدث خطأ',
                'message_en' => 'Error occurred',
            ]);

            return response()->json([
                'success' => false,
                'message_ar' => 'حدث خطأ',
                'message_en' => 'Error occurred',
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/internal-helpdesk/{id}/replies",
     *     summary="ردود التذكرة",
     *     description="عرض جميع الردود على تذكرة معينة",
     *     operationId="getInternalTicketReplies",
     *     tags={"Internal Helpdesk"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="معرف التذكرة",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="نجاح",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="reply_id", type="integer"),
     *                 @OA\Property(property="reply_text", type="string"),
     *                 @OA\Property(property="sent_by", type="integer"),
     *                 @OA\Property(property="sent_by_name", type="string"),
     *                 @OA\Property(property="created_at", type="string")
     *             ))
     *         )
     *     ),
     *     @OA\Response(response=401, description="غير مصرح يجب تسجيل الدخول"),
     *     @OA\Response(response=403, description="لا تملك صلاحية عرض هذه التذكرة"),
     *     @OA\Response(response=404, description="التذكرة غير موجودة"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function getReplies(int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $result = $this->ticketService->getTicketReplies($id, $user);

            if (!$result['success']) {
                Log::error('Error in InternalHelpdeskController@getReplies', [
                    'error' => $result['message'],
                    'ticket_id' => $id,
                    'message_ar' => 'التذكرة غير موجودة',
                    'message_en' => 'Ticket not found',
                ]);
                return response()->json([
                    'success' => false,
                    'message_ar' => 'التذكرة غير موجودة',
                    'message_en' => 'Ticket not found',
                ], 404);
            }

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Error in InternalHelpdeskController@getReplies', [
                'error' => $e->getMessage(),
                'ticket_id' => $id,
                'message_ar' => 'حدث خطأ',
                'message_en' => 'Error occurred',
            ]);

            return response()->json([
                'success' => false,
                'message_ar' => 'حدث خطأ',
                'message_en' => 'Error occurred',
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/internal-helpdesk/{id}/replies",
     *     summary="إضافة رد على تذكرة",
     *     description="إضافة رد جديد على تذكرة مفتوحة. لا يمكن الرد على التذاكر المغلقة",
     *     operationId="addInternalTicketReply",
     *     tags={"Internal Helpdesk"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="معرف التذكرة",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"reply_text"},
     *             @OA\Property(property="reply_text", type="string", description="نص الرد")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="تم إضافة الرد بنجاح",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message_ar", type="string"),
     *             @OA\Property(property="message_en", type="string")
     *         )
     *     ),
     *     @OA\Response(response=401, description="غير مصرح يجب تسجيل الدخول"),
     *     @OA\Response(response=403, description="لا تملك صلاحية الوصول للتذكرة"),
     *     @OA\Response(response=404, description="التذكرة غير موجودة"),
     *     @OA\Response(response=422, description="خطأ في البيانات المدخلة"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function addReply(int $id, CreateInternalReplyRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $companyId = $this->permissionService->getEffectiveCompanyId($user);

            $dto = CreateInternalReplyDTO::fromRequest(
                $request->validated(),
                $id,
                $companyId,
                $user->user_id,
                $user->user_id // assign_to = sender for now
            );

            $result = $this->ticketService->addReply($id, $dto, $user);

            if (!$result['success']) {
                Log::error('Error in InternalHelpdeskController@addReply', [
                    'error' => $result['message'],
                    'ticket_id' => $id,
                    'message_ar' => 'ليس لديك صلاحية الوصول للتذكرة',
                    'message_en' => 'You do not have permission to access the ticket',
                ]);
                return response()->json($result, 403);
            }

            return response()->json($result, 201);
        } catch (\Exception $e) {
            Log::error('Error in InternalHelpdeskController@addReply', [
                'error' => $e->getMessage(),
                'ticket_id' => $id,
                'message_ar' => 'حدث خطأ',
                'message_en' => 'Error occurred',
            ]);

            return response()->json([
                'success' => false,
                'message_ar' => 'حدث خطأ',
                'message_en' => 'Error occurred',
            ], 500);
        }
    }
}
