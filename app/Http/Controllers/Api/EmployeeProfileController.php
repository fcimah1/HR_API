<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\ChangePasswordRequest;
use App\Http\Requests\Employee\UploadProfileImageRequest;
use App\Http\Requests\Employee\UploadDocumentRequest;
use App\Http\Requests\Employee\UpdateProfileInfoRequest;
use App\Http\Requests\Employee\UpdateCVRequest;
use App\Http\Requests\Employee\UpdateSocialLinksRequest;
use App\Http\Requests\Employee\UpdateBankInfoRequest;
use App\Http\Requests\Employee\AddFamilyDataRequest;
use App\Http\Requests\Employee\GetDocumentRequest;
use App\Http\Requests\Employee\UpdateBasicInfoRequest;
use App\Services\EmployeeManagementService;
use App\Services\SimplePermissionService;
use App\Services\FileUploadService;
use App\Services\EmployeeService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;


class EmployeeProfileController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly EmployeeManagementService $employeeService,
        private readonly SimplePermissionService $permissionService,
        private readonly EmployeeService $employeeManagementService,
        private readonly FileUploadService $fileUploadService,

    ) {}


    /**
     * @OA\Put(
     *     path="/api/my-profile/change-password",
     *     summary="Change employee password",
     *     description="Update employee password",
     *     tags={"Employee Profile"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"password", "confirm_password"},
     *             @OA\Property(property="password", type="string", format="password", example="newpassword123"),
     *             @OA\Property(property="confirm_password", type="string", format="password", example="newpassword123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Password changed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم تغيير كلمة المرور بنجاح")
     *         )
     *     ),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=404, description="الموظف أو البيانات غير موجودة أو ليس لديك صلاحية لتغيير كلمة المرور"),
     *     @OA\Response(response=422, description="بيانات غير صحيحة"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $targetEmployeeId = $user->user_id;
            $success = $this->employeeService->changeEmployeePassword($user, $targetEmployeeId, $request->password);

            if (!$success) {
                Log::error('EmployeeProfileController::changePassword failed', [
                    'message' => 'الموظف غير موجود أو ليس لديك صلاحية لتعديل كلمة المرور',
                    'user_id' => $user->user_id,
                    'employee_id' => $targetEmployeeId,
                ]);
                return $this->notFoundResponse('الموظف غير موجود أو ليس لديك صلاحية لتعديل كلمة المرور');
            }

            Log::info('EmployeeProfileController::changePassword success', [
                'user_id' => $user->user_id,
                'employee_id' => $targetEmployeeId,
            ]);
            return $this->successResponse(null, 'تم تغيير كلمة المرور بنجاح');
        } catch (\Exception $e) {
            Log::error('EmployeeProfileController::changePassword failed', [
                'message' => $e->getMessage(),
                'user_id' => $user->user_id,
                'employee_id' => $targetEmployeeId,
            ]);
            return $this->handleException($e, 'EmployeeController::changePassword');
        }
    }

    /**
     * @OA\Post(
     *     path="/api/my-profile/upload-profile-image",
     *     summary="Upload employee profile image",
     *     description="Upload or update employee profile image",
     *     tags={"Employee Profile"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"profile_image"},
     *                 @OA\Property(property="profile_image", type="string", format="binary", description="Profile image file")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Profile image uploaded successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم رفع صورة الملف الشخصي بنجاح"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="profile_image_url", type="string", example="/storage/profiles/employee_123.jpg")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=404, description="الموظف أو البيانات غير موجودة أو ليس لديك صلاحية لرفع الصورة الشخصية"),
     *     @OA\Response(response=422, description="بيانات غير صحيحة"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function uploadProfileImage(UploadProfileImageRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $targetEmployeeId = $user->user_id;

            $result = $this->employeeService->uploadEmployeeProfileImage($user, $targetEmployeeId, $request->file('profile_image'));

            if (!$result) {
                Log::error('EmployeeProfileController::uploadProfileImage failed', [
                    'message' => 'الموظف غير موجود أو ليس لديك صلاحية لرفع الصورة الشخصية',
                    'user_id' => $user->user_id,
                    'employee_id' => $targetEmployeeId,
                ]);
                return $this->notFoundResponse('الموظف غير موجود أو ليس لديك صلاحية لرفع الصورة الشخصية');
            }

            Log::info('EmployeeProfileController::uploadProfileImage success', [
                'user_id' => $user->user_id,
                'employee_id' => $targetEmployeeId,
            ]);
            return $this->successResponse($result, 'تم رفع صورة الملف الشخصي بنجاح');
        } catch (\Exception $e) {
            Log::error('EmployeeProfileController::uploadProfileImage failed', [
                'message' => $e->getMessage(),
                'user_id' => $user->user_id,
                'employee_id' => $targetEmployeeId,
            ]);
            return $this->handleException($e, 'EmployeeController::uploadProfileImage');
        }
    }

    /**
     * @OA\Put(
     *     path="/api/my-profile/update-profile-info",
     *     summary="Update employee profile info",
     *     description="Update employee username and email",
     *     tags={"Employee Profile"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="username", type="string", example="new.username"),
     *             @OA\Property(property="email", type="string", format="email", example="newemail@company.com")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Profile info updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم تحديث معلومات الملف الشخصي بنجاح")
     *         )
     *     ),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=404, description="الموظف أو البيانات غير موجودة أو ليس لديك صلاحية لتعديل معلومات الملف الشخصي"),
     *     @OA\Response(response=422, description="بيانات غير صحيحة"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function updateProfileInfo(UpdateProfileInfoRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $id = $user->user_id;

            $success = $this->employeeService->updateEmployeeProfileInfo($user, $id, $request->only(['username', 'email']));

            if (!$success) {
                Log::error('EmployeeProfileController::updateProfileInfo failed', [
                    'message' => 'الموظف غير موجود أو ليس لديك صلاحية لتعديل معلومات الملف الشخصي',
                    'user_id' => $user->user_id,
                    'employee_id' => $id,
                ]);
                return $this->notFoundResponse('الموظف غير موجود أو ليس لديك صلاحية لتعديل معلومات الملف الشخصي');
            }

            Log::info('EmployeeProfileController::updateProfileInfo success', [
                'user_id' => $user->user_id,
                'employee_id' => $id,
            ]);
            return $this->successResponse(null, 'تم تحديث معلومات الملف الشخصي بنجاح');
        } catch (\Exception $e) {
            Log::error('EmployeeProfileController::updateProfileInfo failed', [
                'message' => $e->getMessage(),
                'user_id' => $user->user_id,
                'employee_id' => $id,
            ]);
            return $this->handleException($e, 'EmployeeController::updateProfileInfo');
        }
    }

    /**
     * @OA\Put(
     *     path="/api/my-profile/cv",
     *     summary="Update employee CV",
     *     description="Update employee bio and experience",
     *     tags={"Employee Profile"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="bio", type="string", example="مطور برمجيات خبرة 5 سنوات"),
     *             @OA\Property(property="experience", type="string", enum={"بدون","سنة","سنتان","سنوات 3","سنوات 4","سنوات 5","سنوات 6","سنوات 7","سنوات 8","سنوات 9","سنوات 10","أكثر من 10+"}, example="سنوات 5")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="CV updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم تحديث السيرة الذاتية بنجاح")
     *         )
     *     ),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=404, description="الموظف أو البيانات غير موجودة أو ليس لديك صلاحية لتعديل السيرة الذاتية"),
     *     @OA\Response(response=422, description="بيانات غير صحيحة"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function updateCV(UpdateCVRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $id = $user->user_id;

            $success = $this->employeeService->updateEmployeeCV($user, $id, $request->only(['bio', 'experience']));

            if (!$success) {
                Log::error('EmployeeProfileController::updateCV failed', [
                    'message' => 'الموظف غير موجود أو ليس لديك صلاحية لتعديل السيرة الذاتية',
                    'user_id' => $user->user_id,
                    'employee_id' => $id,
                ]);
                return $this->notFoundResponse('الموظف غير موجود أو ليس لديك صلاحية لتعديل السيرة الذاتية');
            }

            Log::info('EmployeeProfileController::updateCV success', [
                'user_id' => $user->user_id,
                'employee_id' => $id,
            ]);
            return $this->successResponse(null, 'تم تحديث السيرة الذاتية بنجاح');
        } catch (\Exception $e) {
            Log::error('EmployeeProfileController::updateCV failed', [
                'message' => $e->getMessage(),
                'user_id' => $user->user_id,
                'employee_id' => $id,
            ]);
            return $this->handleException($e, 'EmployeeController::updateCV');
        }
    }

    /**
     * @OA\Put(
     *     path="/api/my-profile/social-links",
     *     summary="Update employee social links",
     *     description="Update employee social media profiles",
     *     tags={"Employee Profile"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="fb_profile", type="string", example="https://facebook.com/username"),
     *             @OA\Property(property="twitter_profile", type="string", example="https://twitter.com/username"),
     *             @OA\Property(property="gplus_profile", type="string", example="https://plus.google.com/username"),
     *             @OA\Property(property="linkedin_profile", type="string", example="https://linkedin.com/in/username")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Social links updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم تحديث الروابط الاجتماعية بنجاح")
     *         )
     *     ),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=404, description="الموظف أو البيانات غير موجودة أو ليس لديك صلاحية لتعديل الروابط الاجتماعية"),
     *     @OA\Response(response=422, description="بيانات غير صحيحة"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function updateSocialLinks(UpdateSocialLinksRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $id = $user->user_id;

            $success = $this->employeeService->updateEmployeeSocialLinks($user, $id, $request->only([
                'fb_profile',
                'twitter_profile',
                'gplus_profile',
                'linkedin_profile'
            ]));

            if (!$success) {
                Log::error('EmployeeProfileController::updateSocialLinks failed', [
                    'message' => 'الموظف غير موجود أو ليس لديك صلاحية لتعديل الروابط الاجتماعية',
                    'user_id' => $user->user_id,
                    'employee_id' => $id,
                ]);
                return $this->notFoundResponse('الموظف غير موجود أو ليس لديك صلاحية لتعديل الروابط الاجتماعية');
            }

            Log::info('EmployeeProfileController::updateSocialLinks success', [
                'user_id' => $user->user_id,
                'employee_id' => $id,
            ]);
            return $this->successResponse(null, 'تم تحديث الروابط الاجتماعية بنجاح');
        } catch (\Exception $e) {
            Log::error('EmployeeProfileController::updateSocialLinks failed', [
                'message' => $e->getMessage(),
                'user_id' => $user->user_id,
                'employee_id' => $id,
            ]);
            return $this->handleException($e, 'EmployeeController::updateSocialLinks');
        }
    }

    /**
     * @OA\Put(
     *     path="/api/my-profile/bank-info",
     *     summary="Update employee bank information",
     *     description="Update employee bank account details",
     *     tags={"Employee Profile"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="account_number", type="string", example="1234567890"),
     *             @OA\Property(property="bank_name", type="int", example="11"),
     *             @OA\Property(property="iban", type="string", example="SA1234567890123456789012"),
     *             @OA\Property(property="bank_branch", type="string", example="فرع الرياض الرئيسي")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Bank information updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم تحديث المعلومات البنكية بنجاح")
     *         )
     *     ),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=404, description="الموظف أو البيانات غير موجودة أو ليس لديك صلاحية لتعديل البيانات البنكيه"),
     *     @OA\Response(response=422, description="بيانات غير صحيحة"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function updateBankInfo(UpdateBankInfoRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $id = $user->user_id;

            $success = $this->employeeService->updateEmployeeBankInfo($user, $id, $request->only([
                'account_number',
                'bank_name',
                'iban',
                'bank_branch'
            ]));

            if (!$success) {
                Log::error('EmployeeController::updateBankInfo failed', [
                    'user_id' => $user->user_id,
                    'employee_id' => $id,
                ]);
                return $this->notFoundResponse('الموظف غير موجود أو ليس لديك صلاحية لتعديل المعلومات البنكية');
            }

            Log::info('EmployeeController::updateBankInfo success', [
                'user_id' => $user->user_id,
                'employee_id' => $id,
            ]);
            return $this->successResponse(null, 'تم تحديث المعلومات البنكية بنجاح');
        } catch (\Exception $e) {
            Log::error('EmployeeController::updateBankInfo failed', [
                'message' => $e->getMessage(),
                'user_id' => $user?->user_id,
                'employee_id' => $id,
            ]);
            return $this->handleException($e, 'EmployeeController::updateBankInfo');
        }
    }

    /**
     * @OA\Put(
     *     path="/api/my-profile/family-data",
     *     summary="Add employee family data",
     *     description="Add employee family/emergency contact information",
     *     tags={"Employee Profile"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="relative_full_name", type="string", example="أحمد محمد العلي"),
     *             @OA\Property(property="relative_email", type="string", format="email", example="relative@email.com"),
     *             @OA\Property(property="relative_phone", type="string", example="0501234567"),
     *             @OA\Property(property="relative_place", type="integer", example="1"),
     *             @OA\Property(property="relative_address", type="string", example="حي النخيل، شارع الملك فهد"),
     *             @OA\Property(property="relative_relation", type="integer", example="1")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Family data added successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم إضافة بيانات العائلة بنجاح")
     *         )
     *     ),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=404, description="الموظف أو البيانات غير موجودة أو ليس لديك صلاحية لإضافة بيانات العائلة"),
     *     @OA\Response(response=422, description="بيانات غير صحيحة"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function addFamilyData(AddFamilyDataRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $id = $user->user_id;

            $success = $this->employeeService->addEmployeeFamilyData($user, $id, $request->only([
                'relative_full_name',
                'relative_email',
                'relative_phone',
                'relative_place',
                'relative_address',
                'relative_relation'
            ]));

            if (!$success) {
                Log::error('EmployeeProfileController::addFamilyData failed', [
                    'message' => 'الموظف غير موجود أو ليس لديك صلاحية لإضافة بيانات العائلة',
                    'user_id' => $user->user_id,
                    'employee_id' => $id,
                ]);
                return $this->notFoundResponse('الموظف غير موجود أو ليس لديك صلاحية لإضافة بيانات العائلة');
            }

            Log::info('EmployeeProfileController::addFamilyData success', [
                'user_id' => $user->user_id,
                'employee_id' => $id,
            ]);
            return $this->successResponse(null, 'تم إضافة بيانات العائلة بنجاح');
        } catch (\Exception $e) {
            Log::error('EmployeeProfileController::addFamilyData failed', [
                'message' => $e->getMessage(),
                'user_id' => $user->user_id,
                'employee_id' => $id,
            ]);
            return $this->handleException($e, 'EmployeeController::addFamilyData');
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/my-profile/family-data/{contactId}",
     *     summary="Delete employee family data",
     *     description="Delete a specific family/emergency contact record",
     *     tags={"Employee Profile"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="contactId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Family data deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم حذف بيانات العائلة بنجاح")
     *         )
     *     ),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=404, description="الموظف أو البيانات غير موجودة أو ليس لديك صلاحية للحذف"),
     *     @OA\Response(response=422, description="بيانات غير صحيحة"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function deleteFamilyData(int $contactId): JsonResponse
    {
        try {
            $user = Auth::user();
            $id = $user->user_id;

            $success = $this->employeeService->deleteEmployeeFamilyData($user, $id, $contactId);

            if (!$success) {
                Log::error('EmployeeProfileController::deleteFamilyData failed', [
                    'message' => 'الموظف أو البيانات غير موجودة أو ليس لديك صلاحية للحذف',
                    'user_id' => $user->user_id,
                    'employee_id' => $id,
                ]);
                return $this->notFoundResponse('الموظف أو البيانات غير موجودة أو ليس لديك صلاحية للحذف');
            }

            Log::info('EmployeeProfileController::deleteFamilyData success', [
                'user_id' => $user->user_id,
                'employee_id' => $id,
            ]);
            return $this->successResponse(null, 'تم حذف بيانات العائلة بنجاح');
        } catch (\Exception $e) {
            Log::error('EmployeeProfileController::deleteFamilyData failed', [
                'message' => $e->getMessage(),
                'user_id' => $user->user_id,
                'employee_id' => $id,
            ]);
            return $this->handleException($e, 'EmployeeController::deleteFamilyData');
        }
    }

    /**
     * @OA\Get(
     *     path="/api/my-profile/documents",
     *     summary="Get employee documents",
     *     description="Retrieve and search through employee documents",
     *     tags={"Employee Profile"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search term for document name or type",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Documents retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم جلب المستندات بنجاح"),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="document_id", type="integer", example=1),
     *                     @OA\Property(property="document_name", type="string", example="CV"),
     *                     @OA\Property(property="document_type", type="string", example="resume"),
     *                     @OA\Property(property="document_file", type="string", example="/storage/documents/cv_123.pdf"),
     *                     @OA\Property(property="expiry_date", type="string", format="date", example="2025-12-31"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-01T12:00:00Z")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=404, description="الموظف غير موجود"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function getEmployeeDocuments(GetDocumentRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $id = $user->user_id;

            $documents = $this->employeeService->getEmployeeDocuments($user, $id, $request->search);
            if (!$documents) {
                Log::error('EmployeeProfileController::getEmployeeDocuments failed', [
                    'message' => 'الموظف أو البيانات غير موجودة أو ليس لديك صلاحية للحذف',
                    'user_id' => $user->user_id,
                    'employee_id' => $id,
                ]);
                return $this->notFoundResponse('الموظف أو البيانات غير موجودة أو ليس لديك صلاحية للحذف');
            }

            Log::info('EmployeeProfileController::getEmployeeDocuments success', [
                'user_id' => $user->user_id,
                'employee_id' => $id,
            ]);
            return $this->successResponse($documents, 'تم جلب المستندات بنجاح');
        } catch (\Exception $e) {
            Log::error('EmployeeProfileController::getEmployeeDocuments failed', [
                'message' => $e->getMessage(),
                'user_id' => $user->user_id,
                'employee_id' => $id,
            ]);
            return $this->handleException($e, 'EmployeeProfileController::getEmployeeDocuments');
        }
    }

    /**
     * Get all profile related enums and types
     * 
     * @OA\Get(
     *     path="/api/my-profile/enums",
     *     tags={"Employee Profile"},
     *     security={{"bearerAuth":{}}},
     *     summary="جلب الثوابت الخاصة بملف الموظف",
     *     description="يجلب فصائل الدم، الحالة الاجتماعية، والجنس",
     *     @OA\Response(
     *         response=200,
     *         description="تم جلب البيانات بنجاح",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="blood_groups", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="marital_statuses", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="genders", type="array", @OA\Items(type="object"))
     *             )
     *         )
     *     )
     * )
     */
    public function getProfileEnums(): JsonResponse
    {
        return $this->successResponse($this->employeeService->getProfileEnums(), 'تم جلب البيانات بنجاح');
    }

    /**
     * Update employee basic information
     * 
     * @OA\Put(
     *     path="/api/my-profile/basic-info",
     *     tags={"Employee Profile"},
     *     security={{"bearerAuth":{}}},
     *     summary="تحديث المعلومات الأساسية للموظف",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/UpdateBasicInfoRequest")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="تم تحديث المعلومات بنجاح",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم تحديث المعلومات الأساسية بنجاح")
     *         )
     *     ),
     *     @OA\Response(response=422, description="بيانات غير صالحة"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=404, description="الموظف غير موجود"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function updateBasicInfo(UpdateBasicInfoRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $id = $user->user_id;

            $success = $this->employeeService->updateEmployeeBasicInfo($user, $id, $request->validated());

            if (!$success) {
                Log::error('EmployeeProfileController::updateBasicInfo failed', [
                    'message' => 'فشل تحديث المعلومات الأساسية',
                    'trace' => $success,
                    'user_id' => $user->user_id,
                    'employee_id' => $id,
                ]);
                return $this->errorResponse('فشل تحديث المعلومات الأساسية', 500);
            }

            Log::info('EmployeeProfileController::updateBasicInfo success', [
                'user_id' => $user->user_id,
                'employee_id' => $id,
            ]);
            return $this->successResponse(null, 'تم تحديث المعلومات الأساسية بنجاح');
        } catch (\Exception $e) {
            Log::error('EmployeeProfileController::updateBasicInfo failed', [
                'message' => $e->getMessage(),
                'user_id' => $user->user_id,
                'employee_id' => $id,
            ]);
            return $this->handleException($e, 'EmployeeProfileController::updateBasicInfo');
        }
    }

    /**
     * Get current employee contract data
     * 
     * @OA\Get(
     *     path="/api/my-profile/contract-data",
     *     tags={"Employee Profile"},
     *     security={{"bearerAuth":{}}},
     *     summary="جلب بيانات العقد للموظف الحالي",
     *     @OA\Response(
     *         response=200,
     *         description="تم جلب بيانات العقد بنجاح",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=500, description="خطأ في الخادم"),
     *     @OA\Response(response=404, description="الموظف غير موجود"),
     *     @OA\Response(response=422, description="بيانات غير صالحة"),
     *     @OA\Response(response=403, description="ليس لديك صلاحية"),
     * )
     */
    public function getContractData(): JsonResponse
    {
        try {
            $user = Auth::user();
            $id = $user->user_id;

            $data = $this->employeeService->getEmployeeContractData($user, $id);

            if(!$data){
                Log::error('EmployeeProfileController::getContractData failed', [
                    'message' => 'بيانات العقد غير موجودة',
                    'user_id' => $user->user_id,
                    'employee_id' => $id,
                ]);
                return $this->errorResponse('بيانات العقد غير موجودة', 404);
            }

            Log::info('EmployeeProfileController::getContractData success', [
                'user_id' => $user->user_id,
                'employee_id' => $id,
            ]);

            return $this->successResponse($data, 'تم جلب بيانات العقد بنجاح');
        } catch (\Exception $e) {
            Log::error('EmployeeProfileController::getContractData failed', [
                'message' => $e->getMessage(),
                'user_id' => $user->user_id,
                'employee_id' => $id,
            ]);
            return $this->handleException($e, 'EmployeeProfileController::getContractData');
        }
    }

}
