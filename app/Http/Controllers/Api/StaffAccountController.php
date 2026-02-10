<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\DTOs\Finance\CreateAccountDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreAccountRequest;
use App\Http\Requests\Finance\StoreEmployeeAccountRequest;
use App\Http\Requests\Finance\UpdateAccountRequest;
use App\Services\FinanceService;
use App\Services\SimplePermissionService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(name="Finance Accounts", description="إدارة الحسابات المالية للشركة وحسابات الموظفين")
 */
class StaffAccountController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly FinanceService $financeService,
        private readonly SimplePermissionService $permissionService
    ) {}

    // =============================================
    // =========== COMPANY ACCOUNTS ===============
    // =============================================

    /**
     * @OA\Get(
     *     path="/api/finance/accounts",
     *     operationId="getFinanceAccounts",
     *     summary="عرض جميع الحسابات المالية للشركة",
     *     tags={"Finance Accounts"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=15), description="عدد السجلات في الصفحة"),
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer", default=1), description="رقم الصفحة"),
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string"), description="بحث في اسم الحساب أو الرقم"),
     *     @OA\Response(response=200, description="تم جلب الحسابات بنجاح"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $companyId = $this->permissionService->getEffectiveCompanyId($request->user());
            $perPage = (int) $request->query('per_page', 15);
            $search = $request->query('search');
            $page = (int) $request->query('page', 1);
            $accounts = $this->financeService->getAllAccounts($companyId, $perPage, $search, $page);

            Log::info("Finance Accounts Fetched", [
                'company_id' => $companyId,
                'user_id' => $request->user()->user_id,
                'message' => 'تم جلب الحسابات بنجاح'
            ]);
            return $this->paginatedResponse($accounts, 'تم جلب الحسابات بنجاح');
        } catch (\Exception $e) {
            Log::error("Finance Accounts Fetch Failed", [
                'company_id' => $companyId,
                'user_id' => $request->user()->user_id,
                'error' => $e->getMessage(),
                'message' => 'فشل جلب الحسابات'
            ]);
            return $this->handleException($e, 'StaffAccountController@index');
        }
    }

    /**
     * @OA\Get(
     *     path="/api/finance/accounts/{id}",
     *     operationId="getFinanceAccount",
     *     summary="عرض حساب مالي محدد",
     *     tags={"Finance Accounts"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="تم جلب الحساب بنجاح"),
     *     @OA\Response(response=404, description="الحساب غير موجود"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول")
     * )
     */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $companyId = $this->permissionService->getEffectiveCompanyId($request->user());
            $account = $this->financeService->getAccountById($id, $companyId);

            if (!$account) {
                Log::warning("Finance Account Not Found", [
                    'account_id' => $id,
                    'company_id' => $companyId,
                    'user_id' => $request->user()->user_id,
                    'message' => 'الحساب غير موجود'
                ]);
                return $this->notFoundResponse('الحساب غير موجود');
            }

            Log::info("Finance Account Fetched", [
                'account_id' => $id,
                'company_id' => $companyId,
                'user_id' => $request->user()->user_id,
                'message' => 'تم جلب الحساب بنجاح'
            ]);
            return $this->successResponse($account, 'تم جلب الحساب بنجاح');
        } catch (\Exception $e) {
            Log::error("Finance Account Fetch Failed", [
                'account_id' => $id,
                'company_id' => $companyId,
                'user_id' => $request->user()->user_id,
                'error' => $e->getMessage(),
                'message' => 'فشل جلب الحساب'
            ]);
            return $this->handleException($e, 'StaffAccountController@show');
        }
    }

    /**
     * @OA\Post(
     *     path="/api/finance/accounts",
     *     operationId="storeFinanceAccount",
     *     summary="إنشاء حساب مالي جديد",
     *     tags={"Finance Accounts"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/StoreAccountRequest")),
     *     @OA\Response(response=201, description="تم إنشاء الحساب بنجاح"),
     *     @OA\Response(response=422, description="فشل التحقق من البيانات"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول")
     * )
     */
    public function store(StoreAccountRequest $request): JsonResponse
    {
        try {
            $companyId = $this->permissionService->getEffectiveCompanyId($request->user());
            $dto = CreateAccountDTO::fromRequest($request, $companyId);
            $account = $this->financeService->createAccount($dto);

            Log::info("Finance Account Created", [
                'account_id' => $account->account_id,
                'account_name' => $account->account_name,
                'created_by' => $request->user()->user_id,
                'company_id' => $companyId,
                'user_id' => $request->user()->user_id,
                'message' => 'تم إنشاء الحساب بنجاح'
            ]);

            return $this->successResponse($account, 'تم إنشاء الحساب بنجاح', 201);
        } catch (\Exception $e) {
            Log::error("Finance Account Creation Failed", [
                'company_id' => $companyId,
                'user_id' => $request->user()->user_id,
                'error' => $e->getMessage(),
                'message' => 'فشل إنشاء الحساب'
            ]);
            return $this->handleException($e, 'StaffAccountController@store');
        }
    }

    /**
     * @OA\Put(
     *     path="/api/finance/accounts/{id}",
     *     operationId="updateFinanceAccount",
     *     summary="تحديث حساب مالي",
     *     tags={"Finance Accounts"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/UpdateAccountRequest")),
     *     @OA\Response(response=200, description="تم تحديث الحساب بنجاح"),
     *     @OA\Response(response=404, description="الحساب غير موجود"),
     *     @OA\Response(response=422, description="فشل التحقق من البيانات"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول")
     * )
     */
    public function update(UpdateAccountRequest $request, int $id): JsonResponse
    {
        try {
            $companyId = $this->permissionService->getEffectiveCompanyId($request->user());
            $account = $this->financeService->getAccountById($id, $companyId);

            if (!$account) {
                Log::warning("Finance Account Not Found", [
                    'account_id' => $id,
                    'company_id' => $companyId,
                    'user_id' => $request->user()->user_id,
                    'message' => 'الحساب غير موجود'
                ]);
                return $this->notFoundResponse('الحساب غير موجود');
            }

            $updated = $this->financeService->updateAccount($account, $request->validated());

            Log::info("Finance Account Updated", [
                'account_id' => $id,
                'company_id' => $companyId,
                'user_id' => $request->user()->user_id,
                'message' => 'تم تحديث الحساب بنجاح'
            ]);
            return $this->successResponse($updated, 'تم تحديث الحساب بنجاح');
        } catch (\Exception $e) {
            Log::error("Finance Account Update Failed", [
                'account_id' => $id,
                'company_id' => $companyId,
                'user_id' => $request->user()->user_id,
                'error' => $e->getMessage(),
                'message' => 'فشل تحديث الحساب'
            ]);
            return $this->handleException($e, 'StaffAccountController@update');
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/finance/accounts/{id}",
     *     operationId="deleteFinanceAccount",
     *     summary="حذف حساب مالي",
     *     tags={"Finance Accounts"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="تم حذف الحساب بنجاح"),
     *     @OA\Response(response=404, description="الحساب غير موجود"),
     *     @OA\Response(response=422, description="لا يمكن حذف حساب يحتوي على معاملات"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول")
     * )
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $companyId = $this->permissionService->getEffectiveCompanyId($request->user());
            $account = $this->financeService->getAccountById($id, $companyId);

            if (!$account) {
                Log::warning("Finance Account Not Found", [
                    'account_id' => $id,
                    'company_id' => $companyId,
                    'user_id' => $request->user()->user_id,
                    'message' => 'الحساب غير موجود'
                ]);
                return $this->notFoundResponse('الحساب غير موجود');
            }

            $this->financeService->deleteAccount($account);

            Log::info("Finance Account Deleted", [
                'account_id' => $id,
                'deleted_by' => $request->user()->user_id,
                'company_id' => $companyId,
                'user_id' => $request->user()->user_id,
                'message' => 'تم حذف الحساب بنجاح'
            ]);

            return $this->successResponse(null, 'تم حذف الحساب بنجاح');
        } catch (\Exception $e) {
            Log::error("Finance Account Deletion Failed", [
                'account_id' => $id,
                'company_id' => $companyId,
                'user_id' => $request->user()->user_id,
                'error' => $e->getMessage(),
                'message' => 'فشل حذف الحساب'
            ]);
            return $this->handleException($e, 'StaffAccountController@destroy');
        }
    }

    // /**
    //  * @OA\Get(
    //  *     path="/api/finance/accounts/{id}/statement",
    //  *     operationId="getFinanceAccountStatement",
    //  *     summary="كشف حساب مالي",
    //  *     tags={"Finance Accounts"},
    //  *     security={{"bearerAuth":{}}},
    //  *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
    //  *     @OA\Parameter(name="from_date", in="query", @OA\Schema(type="string", format="date"), description="من تاريخ"),
    //  *     @OA\Parameter(name="to_date", in="query", @OA\Schema(type="string", format="date"), description="إلى تاريخ"),
    //  *     @OA\Response(response=200, description="تم جلب كشف الحساب بنجاح"),
    //  *     @OA\Response(response=404, description="الحساب غير موجود"),
    //  *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول")
    //  * )
    //  */
    public function statement(Request $request, int $id): JsonResponse
    {
        try {
            $companyId = $this->permissionService->getEffectiveCompanyId($request->user());
            $statement = $this->financeService->getAccountStatement(
                $id,
                $companyId,
                $request->input('from_date'),
                $request->input('to_date')
            );

            Log::info("Finance Account Statement Fetched", [
                'account_id' => $id,
                'company_id' => $companyId,
                'user_id' => $request->user()->user_id,
                'message' => 'تم جلب كشف الحساب بنجاح'
            ]);
            return $this->successResponse($statement, 'تم جلب كشف الحساب بنجاح');
        } catch (\Exception $e) {
            Log::error("Finance Account Statement Fetch Failed", [
                'account_id' => $id,
                'company_id' => $companyId,
                'user_id' => $request->user()->user_id,
                'error' => $e->getMessage(),
                'message' => 'فشل جلب كشف الحساب'
            ]);
            return $this->handleException($e, 'StaffAccountController@statement');
        }
    }

    // =============================================
    // =========== EMPLOYEE ACCOUNTS ==============
    // =============================================

    /**
     * @OA\Get(
     *     path="/api/finance/employee-accounts",
     *     operationId="getEmployeeAccounts",
     *     summary="عرض بنوك الموظفين",
     *     tags={"Finance Accounts"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=15), description="عدد السجلات في الصفحة"),
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer", default=1), description="رقم الصفحة"),
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string"), description="بحث في اسم الحساب"),
     *     @OA\Response(response=200, description="تم جلب بنوك الموظفين بنجاح"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول")
     * )
     */
    public function bankIndex(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $companyId = $this->permissionService->getEffectiveCompanyId($user);
            $perPage = (int) $request->query('per_page', 15);
            $search = $request->query('search');
            $page = (int) $request->query('page', 1);
            $accounts = $this->financeService->getAllEmployeeAccounts($companyId, $perPage, $search, $page);

            Log::info("Employee Accounts Fetched", [
                'company_id' => $companyId,
                'user_id' => $request->user()->user_id,
                'message' => 'تم جلب بنوك الموظفين بنجاح'
            ]);
            return $this->paginatedResponse($accounts, 'تم جلب بنوك الموظفين بنجاح');
        } catch (\Exception $e) {
            Log::error("Employee Accounts Fetch Failed", [
                'company_id' => $companyId,
                'user_id' => $request->user()->user_id,
                'error' => $e->getMessage(),
                'message' => 'فشل جلب بنوك الموظفين'
            ]);
            return $this->handleException($e, 'StaffAccountController@bankIndex');
        }
    }

    /**
     * @OA\Post(
     *     path="/api/finance/employee-accounts",
     *     operationId="storeEmployeeAccount",
     *     summary="إضافة بنك جديد",
     *     tags={"Finance Accounts"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/StoreEmployeeAccountRequest")),
     *     @OA\Response(response=201, description="تم إضافة بنك جديد بنجاح"),
     *     @OA\Response(response=422, description="فشل إضافة بنك جديد"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول")
     * )
     */
    public function bankStore(StoreEmployeeAccountRequest $request): JsonResponse
    {
        try {
            $companyId = $this->permissionService->getEffectiveCompanyId($request->user());

            $account = $this->financeService->createEmployeeAccount([
                'company_id' => $companyId,
                'account_name' => $request->validated('account_name'),
                'created_at' => now()->toDateTimeString(),
            ]);

            Log::info("Employee Account Created", [
                'account_id' => $account->account_id,
                'account_name' => $account->account_name,
                'created_by' => $request->user()->user_id,
                'company_id' => $companyId,
                'user_id' => $request->user()->user_id,
                'message' => 'تم إضافة بنك جديد بنجاح'
            ]);

            return $this->successResponse($account, 'تم إضافة بنك جديد بنجاح', 201);
        } catch (\Exception $e) {
            Log::error("Employee Account Creation Failed", [
                'company_id' => $companyId,
                'user_id' => $request->user()->user_id,
                'error' => $e->getMessage(),
                'message' => 'فشل إضافة بنك جديد'
            ]);
            return $this->handleException($e, 'StaffAccountController@bankStore');
        }
    }

    /**
     * @OA\Get(
     *     path="/api/finance/employee-accounts/{id}",
     *     operationId="getEmployeeBank",
     *     summary="عرض تفاصيل بنك ",
     *     tags={"Finance Accounts"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="تم جلب تفاصيل البنك بنجاح"),
     *     @OA\Response(response=404, description="البنك غير موجود"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول")
     * )
     */
    public function bankShow(Request $request, int $id): JsonResponse
    {
        try {
            $companyId = $this->permissionService->getEffectiveCompanyId($request->user());
            $account = $this->financeService->getEmployeeAccountById($id, $companyId);

            if (!$account) {
                Log::warning("Employee Bank Not Found", [
                    'account_id' => $id,
                    'company_id' => $companyId,
                    'user_id' => $request->user()->user_id,
                    'message' => 'البنك غير موجود'
                ]);
                return $this->notFoundResponse('البنك غير موجود');
            }

            Log::info("Employee Bank Fetched", [
                'account_id' => $id,
                'company_id' => $companyId,
                'user_id' => $request->user()->user_id,
                'message' => 'تم جلب تفاصيل البنك بنجاح'
            ]);
            return $this->successResponse($account, 'تم جلب تفاصيل البنك بنجاح');
        } catch (\Exception $e) {
            Log::error("Employee Bank Fetch Failed", [
                'account_id' => $id,
                'company_id' => $companyId,
                'user_id' => $request->user()->user_id,
                'error' => $e->getMessage(),
                'message' => 'فشل جلب تفاصيل البنك'
            ]);
            return $this->handleException($e, 'StaffAccountController@bankShow');
        }
    }


    /**
     * @OA\Put(
     *     path="/api/finance/employee-accounts/{id}",
     *     operationId="updateEmployeeBank",
     *     summary="تحديث بنك",
     *     tags={"Finance Accounts"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/StoreEmployeeAccountRequest")),
     *     @OA\Response(response=200, description="تم تحديث البنك بنجاح"),
     *     @OA\Response(response=404, description="البنك غير موجود"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول")
     * )
     */
    public function bankUpdate(StoreEmployeeAccountRequest $request, int $id): JsonResponse
    {
        try {
            $companyId = $this->permissionService->getEffectiveCompanyId($request->user());
            $account = $this->financeService->getEmployeeAccountById($id, $companyId);

            if (!$account) {
                Log::warning("Employee Bank Not Found", [
                    'account_id' => $id,
                    'company_id' => $companyId,
                    'user_id' => $request->user()->user_id,
                    'message' => 'البنك غير موجود'
                ]);
                return $this->notFoundResponse('البنك غير موجود');
            }

            $updated = $this->financeService->updateEmployeeAccount($account, $request->validated());

            Log::info("Employee Bank Updated", [
                'account_id' => $id,
                'company_id' => $companyId,
                'user_id' => $request->user()->user_id,
                'message' => 'تم تحديث البنك بنجاح'
            ]);
            return $this->successResponse($updated, 'تم تحديث البنك بنجاح');
        } catch (\Exception $e) {
            Log::error("Employee Bank Update Failed", [
                'account_id' => $id,
                'company_id' => $companyId,
                'user_id' => $request->user()->user_id,
                'error' => $e->getMessage(),
                'message' => 'فشل تحديث البنك'
            ]);
            return $this->handleException($e, 'StaffAccountController@bankUpdate');
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/finance/employee-accounts/{id}",
     *     operationId="deleteEmployeeBank",
     *     summary="حذف بنك",
     *     tags={"Finance Accounts"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="تم حذف البنك بنجاح"),
     *     @OA\Response(response=404, description="البنك غير موجود"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول")
     * )
     */
    public function bankDelete(Request $request, int $id): JsonResponse
    {
        try {
            $companyId = $this->permissionService->getEffectiveCompanyId($request->user());
            $account = $this->financeService->getEmployeeAccountById($id, $companyId);

            if (!$account) {
                Log::warning("Employee Bank Not Found", [
                    'account_id' => $id,
                    'company_id' => $companyId,
                    'user_id' => $request->user()->user_id,
                    'message' => 'البنك غير موجود'
                ]);
                return $this->notFoundResponse('البنك غير موجود');
            }

            $this->financeService->deleteEmployeeAccount($account);

            Log::info("Employee Bank Deleted", [
                'account_id' => $id,
                'company_id' => $companyId,
                'user_id' => $request->user()->user_id,
                'message' => 'تم حذف البنك بنجاح'
            ]);
            return $this->successResponse(null, 'تم حذف البنك بنجاح');
        } catch (\Exception $e) {
            Log::error("Employee Bank Delete Failed", [
                'account_id' => $id,
                'company_id' => $companyId,
                'user_id' => $request->user()->user_id,
                'error' => $e->getMessage(),
                'message' => 'فشل حذف البنك'
            ]);
            return $this->handleException($e, 'StaffAccountController@bankDelete');
        }
    }
}
