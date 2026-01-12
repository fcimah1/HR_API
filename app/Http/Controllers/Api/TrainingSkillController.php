<?php

// declare(strict_types=1);

// namespace App\Http\Controllers\Api;

// use App\Http\Controllers\Controller;
// use App\Services\SimplePermissionService;
// use App\Services\TrainingSkillService;
// use App\Http\Requests\TrainingSkill\CreateTrainingSkillRequest;
// use App\Http\Requests\TrainingSkill\UpdateTrainingSkillRequest;
// use App\DTOs\TrainingSkill\CreateTrainingSkillDTO;
// use App\DTOs\TrainingSkill\UpdateTrainingSkillDTO;
// use Illuminate\Http\JsonResponse;
// use Illuminate\Support\Facades\Auth;
// use Illuminate\Support\Facades\Log;

// /**
//  * @OA\Tag(
//  *     name="Training Skills",
//  *     description="Training skills/types management - إدارة مهارات/أنواع التدريب"
//  * )
//  * 
//  * @OA\Schema(
//  *     schema="TrainingSkillResource",
//  *     type="object",
//  *     title="Training Skill Resource",
//  *     description="بيانات نوع/مهارة التدريب",
//  *     @OA\Property(property="id", type="integer", example=1),
//  *     @OA\Property(property="name", type="string", example="تطوير البرمجيات"),
//  *     @OA\Property(property="company_id", type="integer", example=36),
//  *     @OA\Property(property="is_global", type="boolean", example=false)
//  * )
//  */
// class TrainingSkillController extends Controller
// {
//     public function __construct(
//         private readonly TrainingSkillService $trainingSkillService,
//         private readonly SimplePermissionService $permissionService,
//     ) {}

//     /**
//      * @OA\Get(
//      *     path="/api/training-skills",
//      *     summary="Get training skills list - قائمة مهارات التدريب",
//      *     tags={"Training Skills"},
//      *     security={{"bearerAuth":{}}},
//      *     @OA\Response(
//      *         response=200,
//      *         description="Training skills retrieved successfully",
//      *         @OA\JsonContent(
//      *             type="object",
//      *             @OA\Property(property="success", type="boolean", example=true),
//      *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/TrainingSkillResource"))
//      *         )
//      *     ),
//      *     @OA\Response(response=500, description="خطأ في الخادم"),
//      *     @OA\Response(response=401, description="غير مصرح يجب تسجيل الدخول"),
//      *     @OA\Response(response=403, description="ليس لديك الصلاحية للوصول إلى هذه البيانات")
//      * )
//      */
//     public function index(): JsonResponse
//     {
//         $user = Auth::user();

//         try {
//             $companyId = $this->permissionService->getEffectiveCompanyId($user);
//             $skills = $this->trainingSkillService->getAllForCompany($companyId);

//             // Apply operation restrictions (filter out restricted types)
//             if (!$this->permissionService->isCompanyOwner($user)) {
//                 $restrictedTypes = $this->permissionService->getRestrictedValues(
//                     $user->user_id,
//                     $companyId,
//                     'training_type_'
//                 );

//                 if (!empty($restrictedTypes)) {
//                     $skills = $skills->filter(fn($skill) => !in_array($skill->id, $restrictedTypes))->values();
//                 }
//             }

//             return response()->json([
//                 'success' => true,
//                 'data' => $skills
//             ]);
//         } catch (\Exception $e) {
//             Log::error('TrainingSkillController::index failed', [
//                 'error' => $e->getMessage(),
//                 'user_id' => $user->user_id
//             ]);

//             return response()->json([
//                 'success' => false,
//                 'message' => $e->getMessage()
//             ], 500);
//         }
//     }

//     /**
//      * @OA\Post(
//      *     path="/api/training-skills",
//      *     summary="Create new training skill - إنشاء مهارة تدريب جديدة",
//      *     tags={"Training Skills"},
//      *     security={{"bearerAuth":{}}},
//      *     @OA\RequestBody(
//      *         required=true,
//      *         @OA\JsonContent(ref="#/components/schemas/CreateTrainingSkillRequest")
//      *     ),
//      *     @OA\Response(
//      *         response=201,
//      *         description="Training skill created successfully",
//      *         @OA\JsonContent(
//      *             type="object",
//      *             @OA\Property(property="success", type="boolean", example=true),
//      *             @OA\Property(property="message", type="string", example="تم إنشاء مهارة التدريب بنجاح"),
//      *             @OA\Property(property="data", ref="#/components/schemas/TrainingSkillResource")
//      *         )
//      *     ),
//      *     @OA\Response(response=500, description="خطأ في الخادم"),
//      *     @OA\Response(response=401, description="غير مصرح يجب تسجيل الدخول"),
//      *     @OA\Response(response=403, description="ليس لديك الصلاحية للوصول إلى هذه البيانات"),
//      *     @OA\Response(response=422, description="خطأ في البيانات المدخلة")
//      * )
//      */
//     public function store(CreateTrainingSkillRequest $request): JsonResponse
//     {
//         $user = Auth::user();

//         try {
//             $validated = $request->validated();
//             $companyId = $this->permissionService->getEffectiveCompanyId($user);

//             $dto = CreateTrainingSkillDTO::fromRequest($validated, $companyId);
//             $skill = $this->trainingSkillService->createTrainingSkill($dto);

//             Log::info('TrainingSkillController::store success', [
//                 'skill_id' => $skill['id'],
//                 'user_id' => $user->user_id
//             ]);

//             return response()->json([
//                 'success' => true,
//                 'message' => 'تم إنشاء مهارة التدريب بنجاح',
//                 'data' => $skill
//             ], 201);
//         } catch (\Exception $e) {
//             Log::error('TrainingSkillController::store failed', [
//                 'error' => $e->getMessage(),
//                 'user_id' => $user->user_id
//             ]);

//             return response()->json([
//                 'success' => false,
//                 'message' => $e->getMessage()
//             ], 422);
//         }
//     }

//     /**
//      * @OA\Put(
//      *     path="/api/training-skills/{id}",
//      *     summary="Update training skill - تعديل مهارة تدريب",
//      *     tags={"Training Skills"},
//      *     security={{"bearerAuth":{}}},
//      *     @OA\Parameter(name="id", in="path", required=true, description="Skill ID", @OA\Schema(type="integer")),
//      *     @OA\RequestBody(
//      *         required=true,
//      *         @OA\JsonContent(ref="#/components/schemas/UpdateTrainingSkillRequest")
//      *     ),
//      *     @OA\Response(
//      *         response=200,
//      *         description="Training skill updated successfully",
//      *         @OA\JsonContent(
//      *             type="object",
//      *             @OA\Property(property="success", type="boolean", example=true),
//      *             @OA\Property(property="message", type="string", example="تم تحديث مهارة التدريب بنجاح"),
//      *             @OA\Property(property="data", ref="#/components/schemas/TrainingSkillResource")
//      *         )
//      *     ),
//      *     @OA\Response(response=500, description="خطأ في الخادم"),
//      *     @OA\Response(response=401, description="غير مصرح يجب تسجيل الدخول"),
//      *     @OA\Response(response=403, description="ليس لديك الصلاحية للوصول إلى هذه البيانات"),
//      *     @OA\Response(response=404, description=" المهارة غير موجودة"),
//      *     @OA\Response(response=422, description="خطأ في البيانات المدخلة")
//      * )
//      */
//     public function update(UpdateTrainingSkillRequest $request, int $id): JsonResponse
//     {
//         $user = Auth::user();

//         try {
//             $validated = $request->validated();
//             $companyId = $this->permissionService->getEffectiveCompanyId($user);

//             // Check operation restrictions
//             if (!$this->permissionService->isCompanyOwner($user)) {
//                 $restrictedTypes = $this->permissionService->getRestrictedValues(
//                     $user->user_id,
//                     $companyId,
//                     'training_type_'
//                 );

//                 if (in_array($id, $restrictedTypes)) {
//                     return response()->json([
//                         'success' => false,
//                         'message' => 'غير مصرح لك بتعديل نوع التدريب هذا (قيود العمليات)'
//                     ], 403);
//                 }
//             }

//             $dto = UpdateTrainingSkillDTO::fromRequest($validated);
//             $skill = $this->trainingSkillService->updateTrainingSkill($id, $dto, $companyId);

//             if (!$skill) {
//                 return response()->json([
//                     'success' => false,
//                     'message' => 'مهارة التدريب غير موجودة أو لا يمكن تعديلها'
//                 ], 404);
//             }

//             return response()->json([
//                 'success' => true,
//                 'message' => 'تم تحديث مهارة التدريب بنجاح',
//                 'data' => $skill
//             ]);
//         } catch (\Exception $e) {
//             Log::error('TrainingSkillController::update failed', [
//                 'error' => $e->getMessage(),
//                 'skill_id' => $id,
//                 'user_id' => $user->user_id
//             ]);

//             return response()->json([
//                 'success' => false,
//                 'message' => $e->getMessage()
//             ], 422);
//         }
//     }

//     /**
//      * @OA\Delete(
//      *     path="/api/training-skills/{id}",
//      *     summary="Delete training skill - حذف مهارة تدريب",
//      *     tags={"Training Skills"},
//      *     security={{"bearerAuth":{}}},
//      *     @OA\Parameter(name="id", in="path", required=true, description="Skill ID", @OA\Schema(type="integer")),
//      *     @OA\Response(
//      *         response=200,
//      *         description="Training skill deleted successfully",
//      *         @OA\JsonContent(
//      *             type="object",
//      *             @OA\Property(property="success", type="boolean", example=true),
//      *             @OA\Property(property="message", type="string", example="تم حذف مهارة التدريب بنجاح")
//      *         )
//      *     ),
//      *     @OA\Response(response=500, description="خطأ في الخادم"),
//      *     @OA\Response(response=401, description="غير مصرح يجب تسجيل الدخول"),
//      *     @OA\Response(response=403, description="ليس لديك الصلاحية للوصول إلى هذه البيانات"),
//      *     @OA\Response(response=404, description=" المهارة غير موجودة"),
//      *     @OA\Response(response=422, description="لا يمكن حذف المهارة لأنها مستخدمة")
//      * )
//      */
//     public function destroy(int $id): JsonResponse
//     {
//         $user = Auth::user();

//         try {
//             $companyId = $this->permissionService->getEffectiveCompanyId($user);

//             // Check operation restrictions
//             if (!$this->permissionService->isCompanyOwner($user)) {
//                 $restrictedTypes = $this->permissionService->getRestrictedValues(
//                     $user->user_id,
//                     $companyId,
//                     'training_type_'
//                 );

//                 if (in_array($id, $restrictedTypes)) {
//                     return response()->json([
//                         'success' => false,
//                         'message' => 'غير مصرح لك بحذف نوع التدريب هذا (قيود العمليات)'
//                     ], 403);
//                 }
//             }

//             $result = $this->trainingSkillService->deleteTrainingSkill($id, $companyId);

//             if ($result === false) {
//                 return response()->json([
//                     'success' => false,
//                     'message' => 'مهارة التدريب غير موجودة أو لا يمكن حذفها'
//                 ], 404);
//             }

//             if (is_string($result)) {
//                 return response()->json([
//                     'success' => false,
//                     'message' => $result
//                 ], 422);
//             }

//             Log::info('TrainingSkillController::destroy success', [
//                 'skill_id' => $id,
//                 'user_id' => $user->user_id
//             ]);

//             return response()->json([
//                 'success' => true,
//                 'message' => 'تم حذف مهارة التدريب بنجاح'
//             ]);
//         } catch (\Exception $e) {
//             Log::error('TrainingSkillController::destroy failed', [
//                 'error' => $e->getMessage(),
//                 'skill_id' => $id,
//                 'user_id' => $user->user_id
//             ]);

//             return response()->json([
//                 'success' => false,
//                 'message' => $e->getMessage()
//             ], 422);
//         }
//     }
// }
