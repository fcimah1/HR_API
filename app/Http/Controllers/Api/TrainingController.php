<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TrainingService;
use App\Services\SimplePermissionService;
use App\DTOs\Training\TrainingFilterDTO;
use App\DTOs\Training\CreateTrainingDTO;
use App\DTOs\Training\UpdateTrainingDTO;
use App\Http\Requests\Training\CreateTrainingRequest;
use App\Http\Requests\Training\UpdateTrainingRequest;
use App\Http\Requests\Training\UpdateTrainingStatusRequest;
use App\Http\Requests\Training\AddTrainingNoteRequest;
use App\Http\Resources\TrainingResource;
use App\Http\Resources\TrainingNoteResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="Training Management",
 *     description="Training sessions management - إدارة التدريب"
 * )
 */
class TrainingController extends Controller
{
    public function __construct(
        private readonly TrainingService $trainingService,
        private readonly SimplePermissionService $permissionService,
    ) {}

    /**
     * @OA\Get(
     *     path="/api/trainings",
     *     summary="Get trainings list - قائمة التدريبات",
     *     tags={"Training Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="status", in="query", description="Filter by status (0=pending,1=started,2=completed,3=rejected)", @OA\Schema(type="integer", enum={0,1,2,3})),
     *     @OA\Parameter(name="trainer_id", in="query", description="Filter by trainer", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="training_type_id", in="query", description="Filter by training type", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="department_id", in="query", description="Filter by department", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="from_date", in="query", description="Filter from date (Y-m-d)", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="to_date", in="query", description="Filter to date (Y-m-d)", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="search", in="query", description="Search term", @OA\Schema(type="string")),
     *     @OA\Parameter(name="per_page", in="query", description="Items per page", @OA\Schema(type="integer", default=15)),
     *     @OA\Parameter(name="page", in="query", description="Page number", @OA\Schema(type="integer", default=1)),
     *     @OA\Response(
     *         response=200,
     *         description="Trainings retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/TrainingResource")),
     *             @OA\Property(
     *                 property="pagination",
     *                 type="object",
     *                 @OA\Property(property="total", type="integer", example=50),
     *                 @OA\Property(property="per_page", type="integer", example=15),
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="last_page", type="integer", example=4),
     *                 @OA\Property(property="from", type="integer", example=1),
     *                 @OA\Property(property="to", type="integer", example=15)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="غير مصرح يجب تسجيل الدخول"),
     *     @OA\Response(response=403, description="ليس لديك الصلاحية للوصول إلى هذه البيانات"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();

        try {
            $companyId = $this->permissionService->getEffectiveCompanyId($user);
            $filters = TrainingFilterDTO::fromRequest(array_merge($request->all(), [
                'company_id' => $companyId
            ]));

            $result = $this->trainingService->getPaginatedTrainings($filters, $user);

            Log::info('TrainingController::index - Trainings retrieved successfully', [
                'user_id' => Auth::user()->user_id,
                'trainings' => $result['data'],
            ]);
            return response()->json([
                'success' => true,
                'data' => $result['data'],
                'pagination' => [
                    'total' => $result['total'],
                    'per_page' => $result['per_page'],
                    'current_page' => $result['current_page'],
                    'last_page' => $result['last_page'],
                    'from' => $result['from'],
                    'to' => $result['to'],
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('TrainingController::index failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->user_id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'فشل في جلب التدريبات',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/trainings/{id}",
     *     summary="Show training details - تفاصيل التدريب",
     *     tags={"Training Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="Training ID", @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Training details retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/TrainingResource")
     *         )
     *     ),
     *     @OA\Response(response=500, description="خطأ في الخادم"),
     *     @OA\Response(response=401, description="غير مصرح يجب تسجيل الدخول"),
     *     @OA\Response(response=403, description="ليس لديك الصلاحية للوصول إلى هذه البيانات"),
     *     @OA\Response(response=404, description="التدريب غير موجود")
     * )
     */
    public function show(int $id): JsonResponse
    {
        $user = Auth::user();

        try {
            $companyId = $this->permissionService->getEffectiveCompanyId($user);
            $training = $this->trainingService->getTrainingById($id, $companyId);

            if (!$training) {
                return response()->json([
                    'success' => false,
                    'message' => 'التدريب غير موجود'
                ], 404);
            }

            Log::info('TrainingController::show - Training retrieved successfully', [
                'user_id' => Auth::user()->user_id,
                'training' => $training,
            ]);
            return response()->json([
                'success' => true,
                'data' => $training
            ]);
        } catch (\Exception $e) {
            Log::error('TrainingController::show failed', [
                'error' => $e->getMessage(),
                'training_id' => $id,
                'user_id' => $user->user_id
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/trainings",
     *     summary="Create new training - إنشاء تدريب جديد",
     *     tags={"Training Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/CreateTrainingRequest")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Training created successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم إنشاء التدريب بنجاح"),
     *             @OA\Property(property="data", ref="#/components/schemas/TrainingResource")
     *         )
     *     ),
     *     @OA\Response(response=401, description="غير مصرح يجب تسجيل الدخول"),
     *     @OA\Response(response=403, description="ليس لديك الصلاحية للوصول إلى هذه البيانات"),
     *     @OA\Response(
     *         response=422,
     *         description="خطأ في البيانات المدخلة",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="فشل التحقق من البيانات"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function store(CreateTrainingRequest $request): JsonResponse
    {
        $user = Auth::user();

        try {
            $validated = $request->validated();

            $companyId = $this->permissionService->getEffectiveCompanyId($user);
            $dto = CreateTrainingDTO::fromRequest($validated, $companyId);
            $training = $this->trainingService->createTraining($dto, $user);

            Log::info('TrainingController::store success', [
                'training_id' => $training->training_id,
                'user_id' => $user->user_id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء التدريب بنجاح',
                'data' => $training
            ], 201);
        } catch (\Exception $e) {
            Log::error('TrainingController::store failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->user_id
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/trainings/{id}",
     *     summary="Update training - تعديل تدريب",
     *     tags={"Training Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="Training ID", @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/UpdateTrainingRequest")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Training updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم تحديث التدريب بنجاح"),
     *             @OA\Property(property="data", ref="#/components/schemas/TrainingResource")
     *         )
     *     ),
     *     @OA\Response(response=500, description="خطأ في الخادم"),
     *     @OA\Response(response=401, description="غير مصرح يجب تسجيل الدخول"),
     *     @OA\Response(response=403, description="ليس لديك الصلاحية للوصول إلى هذه البيانات"),
     *     @OA\Response(response=404, description="التدريب غير موجود"),
     *     @OA\Response(response=422, description="خطأ في البيانات المدخلة")
     * )
     */
    public function update(UpdateTrainingRequest $request, int $id): JsonResponse
    {
        $user = Auth::user();

        try {
            $validated = $request->validated();
            $companyId = $this->permissionService->getEffectiveCompanyId($user);

            // Get training to check its type for restrictions
            $existingTraining = $this->trainingService->getTrainingById($id, $companyId);
            if (!$existingTraining) {
                return response()->json([
                    'success' => false,
                    'message' => 'التدريب غير موجود'
                ], 404);
            }

            // Check operation restrictions on training type
            if (!$this->permissionService->isCompanyOwner($user)) {
                $restrictedTypes = $this->permissionService->getRestrictedValues(
                    $user->user_id,
                    $companyId,
                    'training_type_'
                );

                if (in_array($existingTraining->training_type_id, $restrictedTypes)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'غير مصرح لك بتعديل هذا التدريب (قيود العمليات)'
                    ], 403);
                }
            }

            $dto = UpdateTrainingDTO::fromRequest($validated);
            $training = $this->trainingService->updateTraining($id, $dto, $companyId);

            if (!$training) {
                Log::info('TrainingController::update - No training found', [
                    'user_id' => Auth::user()->user_id,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'التدريب غير موجود'
                ], 404);
            }

            Log::info('TrainingController::update success', [
                'user_id' => Auth::user()->user_id,
                'training' => $training,
            ]);
            return response()->json([
                'success' => true,
                'message' => 'تم تحديث التدريب بنجاح',
                'data' => $training
            ]);
        } catch (\Exception $e) {
            Log::error('TrainingController::update failed', [
                'error' => $e->getMessage(),
                'training_id' => $id,
                'user_id' => $user->user_id
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/trainings/{id}",
     *     summary="Delete training - حذف تدريب",
     *     tags={"Training Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="Training ID", @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Training deleted successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم حذف التدريب بنجاح")
     *         )
     *     ),
     *     @OA\Response(response=500, description="خطأ في الخادم"),
     *     @OA\Response(response=401, description="غير مصرح يجب تسجيل الدخول"),
     *     @OA\Response(response=403, description="ليس لديك الصلاحية للوصول إلى هذه البيانات"),
     *     @OA\Response(response=404, description="التدريب غير موجود")
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        $user = Auth::user();

        try {
            $companyId = $this->permissionService->getEffectiveCompanyId($user);

            // Get training to check its type for restrictions
            $existingTraining = $this->trainingService->getTrainingById($id, $companyId);
            if (!$existingTraining) {
                Log::info('TrainingController::destroy - No training found', [
                    'user_id' => Auth::user()->user_id,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'التدريب غير موجود'
                ], 404);
            }

            // Check operation restrictions on training type
            if (!$this->permissionService->isCompanyOwner($user)) {
                $restrictedTypes = $this->permissionService->getRestrictedValues(
                    $user->user_id,
                    $companyId,
                    'training_type_'
                );

                if (in_array($existingTraining->training_type_id, $restrictedTypes)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'غير مصرح لك بحذف هذا التدريب (قيود العمليات)'
                    ], 403);
                }
            }

            $deleted = $this->trainingService->deleteTraining($id, $companyId);

            if (!$deleted) {
                Log::info('TrainingController::destroy - No training found', [
                    'user_id' => Auth::user()->user_id,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'التدريب غير موجود'
                ], 404);
            }

            Log::info('TrainingController::destroy success', [  
                'user_id' => Auth::user()->user_id,
                'training_id' => $id,
            ]);
            return response()->json([
                'success' => true,
                'message' => 'تم حذف التدريب بنجاح'
            ]);
        } catch (\Exception $e) {
            Log::error('TrainingController::destroy failed', [
                'error' => $e->getMessage(),
                'training_id' => $id,
                'user_id' => $user->user_id
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * @OA\Patch(
     *     path="/api/trainings/{id}/status",
     *     summary="Update training status - تحديث حالة التدريب",
     *     tags={"Training Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="Training ID", @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/UpdateTrainingStatusRequest")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Status updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم تحديث حالة التدريب بنجاح"),
     *             @OA\Property(property="data", ref="#/components/schemas/TrainingResource")
     *         )
     *     ),
     *     @OA\Response(response=500, description="خطأ في الخادم"),
     *     @OA\Response(response=401, description="غير مصرح يجب تسجيل الدخول"),
     *     @OA\Response(response=403, description="ليس لديك الصلاحية للوصول إلى هذه البيانات"),
     *     @OA\Response(response=404, description="التدريب غير موجود"),
     *     @OA\Response(response=422, description="خطأ في البيانات المدخلة")
     * )
     */
    public function updateStatus(UpdateTrainingStatusRequest $request, int $id): JsonResponse
    {
        $user = Auth::user();

        try {
            $validated = $request->validated();

            $companyId = $this->permissionService->getEffectiveCompanyId($user);
            $training = $this->trainingService->updateTrainingStatus(
                $id,
                $validated['status'],
                $companyId,
                $validated['performance'] ?? null,
                $validated['remarks']
            );

            if (!$training) {
                Log::info('TrainingController::updateStatus - No training found', [
                    'user_id' => Auth::user()->user_id,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'التدريب غير موجود'
                ], 404);
            }

            Log::info('TrainingController::updateStatus success', [
                'user_id' => Auth::user()->user_id,
                'training' => $training,
            ]);
            return response()->json([
                'success' => true,
                'message' => 'تم تحديث حالة التدريب بنجاح',
                'data' => $training
            ]);
        } catch (\Exception $e) {
            Log::error('TrainingController::updateStatus failed', [
                'error' => $e->getMessage(),
                'training_id' => $id,
                'user_id' => $user->user_id
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/trainings/{id}/notes",
     *     summary="Add note to training - إضافة ملاحظة للتدريب",
     *     tags={"Training Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="Training ID", @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/AddTrainingNoteRequest")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Note added successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم إضافة الملاحظة بنجاح"),
     *             @OA\Property(property="data", ref="#/components/schemas/TrainingNoteResource")
     *         )
     *     ),
     *     @OA\Response(response=500, description="خطأ في الخادم"),
     *     @OA\Response(response=401, description="غير مصرح يجب تسجيل الدخول"),
     *     @OA\Response(response=403, description="ليس لديك الصلاحية للوصول إلى هذه البيانات"),
     *     @OA\Response(response=404, description="التدريب غير موجود"),
     *     @OA\Response(response=422, description="خطأ في البيانات المدخلة")
     * )
     */
    public function addNote(AddTrainingNoteRequest $request, int $id): JsonResponse
    {
        $user = Auth::user();

        try {
            $validated = $request->validated();

            $companyId = $this->permissionService->getEffectiveCompanyId($user);
            $note = $this->trainingService->addNote($id, $companyId, $user->user_id, $validated['note']);

            Log::info('TrainingController::addNote success', [
                'user_id' => Auth::user()->user_id,
                'training_id' => $id,
                'note' => $note
            ]);
            return response()->json([
                'success' => true,
                'message' => 'تم إضافة الملاحظة بنجاح',
                'data' => $note
            ], 201);
        } catch (\Exception $e) {
            Log::error('TrainingController::addNote failed', [
                'error' => $e->getMessage(),
                'training_id' => $id,
                'user_id' => $user->user_id
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $e->getMessage() === 'Training not found' ? 404 : 422);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/trainings/{id}/notes",
     *     summary="Get training notes - ملاحظات التدريب",
     *     tags={"Training Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="Training ID", @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Notes retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/TrainingNoteResource"))
     *         )
     *     ),
     *     @OA\Response(response=500, description="خطأ في الخادم"),
     *     @OA\Response(response=401, description="غير مصرح يجب تسجيل الدخول"),
     *     @OA\Response(response=403, description="ليس لديك الصلاحية للوصول إلى هذه البيانات"),
     *     @OA\Response(response=404, description="التدريب غير موجود")
     * )
     */
    public function getNotes(int $id): JsonResponse
    {
        $user = Auth::user();

        try {
            $companyId = $this->permissionService->getEffectiveCompanyId($user);
            $notes = $this->trainingService->getNotes($id, $companyId);

            Log::info('TrainingController::getNotes success', [
                'user_id' => Auth::user()->user_id,
                'training_id' => $id,
                'notes' => $notes
            ]);
            return response()->json([
                'success' => true,
                'data' => $notes
            ]);
        } catch (\Exception $e) {
            Log::error('TrainingController::getNotes failed', [
                'error' => $e->getMessage(),
                'training_id' => $id,
                'user_id' => $user->user_id
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $e->getMessage() === 'Training not found' ? 404 : 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/trainings/enums",
     *     summary="Get training enums - قوائم التدريب",
     *     tags={"Training Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Enums retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="statuses",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="value", type="integer", example=0),
     *                         @OA\Property(property="name", type="string", example="PENDING"),
     *                         @OA\Property(property="label", type="string", example="قيد الانتظار")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="performance_levels",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="value", type="integer", example=4),
     *                         @OA\Property(property="name", type="string", example="EXCELLENT"),
     *                         @OA\Property(property="label", type="string", example="ممتاز")
     *                     )
     *                 )
     *             )
     *         ),
     *     ),
     *     @OA\Response(response=500, description="خطأ في الخادم"),
     *     @OA\Response(response=401, description="غير مصرح يجب تسجيل الدخول"),
     *     @OA\Response(response=403, description="ليس لديك الصلاحية للوصول إلى هذه البيانات")
     * )
     */
    public function enums(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->trainingService->getTrainingEnums()
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/trainings/statistics",
     *     summary="Get training statistics - إحصائيات التدريب",
     *     tags={"Training Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Statistics retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="total", type="integer", example=50),
     *                 @OA\Property(property="pending", type="integer", example=10),
     *                 @OA\Property(property="started", type="integer", example=15),
     *                 @OA\Property(property="completed", type="integer", example=20),
     *                 @OA\Property(property="rejected", type="integer", example=5),
     *                 @OA\Property(property="total_cost", type="number", format="float", example=75000.00)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=500, description="خطأ في الخادم"),
     *     @OA\Response(response=401, description="غير مصرح يجب تسجيل الدخول"),
     *     @OA\Response(response=403, description="ليس لديك الصلاحية للوصول إلى هذه البيانات")
     * )
     */
    public function statistics(): JsonResponse
    {
        $user = Auth::user();

        try {
            $companyId = $this->permissionService->getEffectiveCompanyId($user);
            $stats = $this->trainingService->getStatistics($companyId);

            Log::info('TrainingController::statistics success', [
                'user_id' => Auth::user()->user_id,
                'stats' => $stats
            ]);
            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            Log::error('TrainingController::statistics failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->user_id
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
