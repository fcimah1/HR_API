<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TrainerService;
use App\Services\SimplePermissionService;
use App\DTOs\Trainer\TrainerFilterDTO;
use App\DTOs\Trainer\CreateTrainerDTO;
use App\Http\Requests\Trainer\CreateTrainerRequest;
use App\Http\Requests\Trainer\UpdateTrainerRequest;
use App\Http\Resources\TrainerResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="Trainer Management",
 *     description="Trainers management - إدارة المدربين"
 * )
 * 
 * @OA\Schema(
 *     schema="TrainerResource",
 *     type="object",
 *     title="Trainer Resource",
 *     description="بيانات المدرب",
 *     @OA\Property(property="trainer_id", type="integer", example=1),
 *     @OA\Property(property="company_id", type="integer", example=36),
 *     @OA\Property(property="first_name", type="string", example="محمد"),
 *     @OA\Property(property="last_name", type="string", example="أحمد"),
 *     @OA\Property(property="full_name", type="string", example="محمد أحمد"),
 *     @OA\Property(property="contact_number", type="string", example="01234567890"),
 *     @OA\Property(property="email", type="string", format="email", example="trainer@example.com"),
 *     @OA\Property(property="expertise", type="string", example="PHP, Laravel, JavaScript"),
 *     @OA\Property(property="address", type="string", example="القاهرة، مصر"),
 *     @OA\Property(property="trainings_count", type="integer", example=5),
 *     @OA\Property(property="created_at", type="string", format="date-time")
 * )
 * 
 * @OA\Schema(
 *     schema="TrainingResource",
 *     type="object",
 *     title="Training Resource",
 *     description="بيانات التدريب",
 *     @OA\Property(property="training_id", type="integer", example=1),
 *     @OA\Property(property="company_id", type="integer", example=36),
 *     @OA\Property(property="department_id", type="integer", example=1),
 *     @OA\Property(property="department_name", type="string", example="قسم تقنية المعلومات"),
 *     @OA\Property(property="employee_ids", type="array", @OA\Items(type="integer"), example={1, 2, 3}),
 *     @OA\Property(property="training_type_id", type="integer", example=1),
 *     @OA\Property(property="training_type_name", type="string", example="تطوير البرمجيات"),
 *     @OA\Property(property="trainer_id", type="integer", example=1),
 *     @OA\Property(property="trainer_name", type="string", example="محمد أحمد"),
 *     @OA\Property(property="start_date", type="string", format="date", example="2026-01-15"),
 *     @OA\Property(property="finish_date", type="string", format="date", example="2026-01-20"),
 *     @OA\Property(property="training_cost", type="number", format="float", example=1500.00),
 *     @OA\Property(property="training_status", type="integer", example=0),
 *     @OA\Property(property="status_label", type="string", example="قيد الانتظار"),
 *     @OA\Property(property="description", type="string", example="دورة تدريبية في تطوير PHP"),
 *     @OA\Property(property="performance", type="integer", example=0),
 *     @OA\Property(property="performance_label", type="string", example="غير منتهى"),
 *     @OA\Property(property="associated_goals", type="string", example="تطوير مهارات البرمجة"),
 *     @OA\Property(property="remarks", type="string", example="ملاحظات إضافية"),
 *     @OA\Property(property="created_at", type="string", format="date-time")
 * )
 * 
 * @OA\Schema(
 *     schema="TrainingNoteResource",
 *     type="object",
 *     title="Training Note Resource",
 *     description="بيانات ملاحظة التدريب",
 *     @OA\Property(property="training_note_id", type="integer", example=1),
 *     @OA\Property(property="training_id", type="integer", example=1),
 *     @OA\Property(property="company_id", type="integer", example=36),
 *     @OA\Property(property="employee_id", type="integer", example=37),
 *     @OA\Property(property="employee_name", type="string", example="أحمد محمود"),
 *     @OA\Property(property="training_note", type="string", example="ملاحظة حول أداء المتدربين"),
 *     @OA\Property(property="created_at", type="string", format="date-time")
 * )
 */
class TrainerController extends Controller
{
    public function __construct(
        private readonly TrainerService $trainerService,
        private readonly SimplePermissionService $permissionService,
    ) {}

    /**
     * @OA\Get(
     *     path="/api/trainers",
     *     summary="Get trainers list - قائمة المدربين",
     *     tags={"Trainer Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="search", in="query", description="Search by name", @OA\Schema(type="string")),
     *     @OA\Parameter(name="email", in="query", description="Filter by email", @OA\Schema(type="string")),
     *     @OA\Parameter(name="per_page", in="query", description="Items per page", @OA\Schema(type="integer", default=15)),
     *     @OA\Parameter(name="page", in="query", description="Page number", @OA\Schema(type="integer", default=1)),
     *     @OA\Response(
     *         response=200,
     *         description="Trainers retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/TrainerResource")),
     *             @OA\Property(
     *                 property="pagination",
     *                 type="object",
     *                 @OA\Property(property="total", type="integer"),
     *                 @OA\Property(property="per_page", type="integer"),
     *                 @OA\Property(property="current_page", type="integer"),
     *                 @OA\Property(property="last_page", type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=500, description="خطأ في الخادم"),
     *     @OA\Response(response=401, description="غير مصرح يجب تسجيل الدخول"),
     *     @OA\Response(response=403, description="ليس لديك الصلاحية للوصول إلى هذه البيانات"),
     *     @OA\Response(response=422, description="خطأ في البيانات المدخلة")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();

        try {
            $companyId = $this->permissionService->getEffectiveCompanyId($user);
            $filters = TrainerFilterDTO::fromRequest(array_merge($request->all(), [
                'company_id' => $companyId
            ]));

            $result = $this->trainerService->getPaginatedTrainers($filters);

            Log::info('TrainerController::index - Trainers retrieved successfully', [
                'user_id' => Auth::user()->user_id,
                'trainers' => $result['data'],
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
            Log::error('TrainerController::index failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->user_id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'فشل في جلب المدربين',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/trainers/dropdown",
     *     summary="Get trainers for dropdown - قائمة المدربين للاختيار",
     *     tags={"Trainer Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Trainers list retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="trainer_id", type="integer", example=1),
     *                     @OA\Property(property="full_name", type="string", example="محمد أحمد"),
     *                     @OA\Property(property="email", type="string", example="trainer@example.com")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=500, description="خطأ في الخادم"),
     *     @OA\Response(response=401, description="غير مصرح يجب تسجيل الدخول"),
     *     @OA\Response(response=403, description="ليس لديك الصلاحية للوصول إلى هذه البيانات"),
     *     @OA\Response(response=422, description="خطأ في البيانات المدخلة")
     * )
     */
    public function dropdown(): JsonResponse
    {
        $user = Auth::user();

        try {
            $companyId = $this->permissionService->getEffectiveCompanyId($user);
            $trainers = $this->trainerService->getAllForCompany($companyId, $user);

            Log::info('TrainerController::dropdown - Trainers retrieved successfully', [
                'user_id' => Auth::user()->user_id,
                'trainers' => $trainers,
            ]);
            return response()->json([
                'success' => true,
                'data' => $trainers
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting trainers dropdown: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::user()->user_id,
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/trainers/{id}",
     *     summary="Show trainer details - تفاصيل المدرب",
     *     tags={"Trainer Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="Trainer ID", @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Trainer details retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/TrainerResource")
     *         )
     *     ),
     *     @OA\Response(response=500, description="خطأ في الخادم"),
     *     @OA\Response(response=401, description="غير مصرح يجب تسجيل الدخول"),
     *     @OA\Response(response=403, description="ليس لديك الصلاحية للوصول إلى هذه البيانات"),
     *     @OA\Response(response=422, description="خطأ في البيانات المدخلة")
     * )
     */
    public function show(int $id): JsonResponse
    {
        $user = Auth::user();

        try {
            $companyId = $this->permissionService->getEffectiveCompanyId($user);
            $trainer = $this->trainerService->getTrainerById($id, $companyId);

            if (!$trainer) {
                Log::info('TrainerController::show - No trainer found', [
                    'user_id' => Auth::user()->user_id,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'المدرب غير موجود'
                ], 404);
            }

            Log::info('TrainerController::show - Trainer retrieved successfully', [
                'user_id' => Auth::user()->user_id,
                'trainer' => $trainer,
            ]);
            return response()->json([
                'success' => true,
                'data' => $trainer
            ]);
        } catch (\Exception $e) {
            Log::error('TrainerController::show failed', [
                'error' => $e->getMessage(),
                'trainer_id' => $id,
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
     *     path="/api/trainers",
     *     summary="Create new trainer - إنشاء مدرب جديد",
     *     tags={"Trainer Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/CreateTrainerRequest")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Trainer created successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم إنشاء المدرب بنجاح"),
     *             @OA\Property(property="data", ref="#/components/schemas/TrainerResource")
     *         )
     *     ),
     *     @OA\Response(response=500, description="خطأ في الخادم"),
     *     @OA\Response(response=401, description="غير مصرح يجب تسجيل الدخول"),
     *     @OA\Response(response=403, description="ليس لديك الصلاحية للوصول إلى هذه البيانات"),
     *     @OA\Response(response=422, description="خطأ في البيانات المدخلة")
     * )
     */
    public function store(CreateTrainerRequest $request): JsonResponse
    {
        $user = Auth::user();

        try {
            $validated = $request->validated();

            $companyId = $this->permissionService->getEffectiveCompanyId($user);
            $dto = CreateTrainerDTO::fromRequest($validated, $companyId);
            $trainer = $this->trainerService->createTrainer($dto, $user);

            Log::info('TrainerController::store success', [
                'trainer_id' => $trainer['trainer_id'],
                'user_id' => $user->user_id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء المدرب بنجاح',
                'data' => $trainer
            ], 201);
        } catch (\Exception $e) {
            Log::error('TrainerController::store failed', [
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
     *     path="/api/trainers/{id}",
     *     summary="Update trainer - تعديل مدرب",
     *     tags={"Trainer Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="Trainer ID", @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/UpdateTrainerRequest")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Trainer updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم تحديث المدرب بنجاح"),
     *             @OA\Property(property="data", ref="#/components/schemas/TrainerResource")
     *         )
     *     ),
     *     @OA\Response(response=500, description="خطأ في الخادم"),
     *     @OA\Response(response=401, description="غير مصرح يجب تسجيل الدخول"),
     *     @OA\Response(response=403, description="ليس لديك الصلاحية للوصول إلى هذه البيانات"),
     *     @OA\Response(response=404, description="المدرب غير موجود"),
     *     @OA\Response(response=422, description="خطأ في البيانات المدخلة")
     * )
     */
    public function update(UpdateTrainerRequest $request, int $id): JsonResponse
    {
        $user = Auth::user();

        try {
            $validated = $request->validated();
            $companyId = $this->permissionService->getEffectiveCompanyId($user);

            $trainer = $this->trainerService->updateTrainer($id, $validated, $companyId, $user);

            if (!$trainer) {
                Log::info('TrainerController::update - No trainer found', [
                    'user_id' => Auth::user()->user_id,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'المدرب غير موجود'
                ], 404);
            }

            Log::info('TrainerController::update - Trainer updated successfully', [
                'user_id' => Auth::user()->user_id,
                'trainer' => $trainer,
            ]);
            return response()->json([
                'success' => true,
                'message' => 'تم تحديث المدرب بنجاح',
                'data' => $trainer
            ]);
        } catch (\Exception $e) {
            Log::error('TrainerController::update failed', [
                'error' => $e->getMessage(),
                'trainer_id' => $id,
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
     *     path="/api/trainers/{id}",
     *     summary="Delete trainer - حذف مدرب",
     *     tags={"Trainer Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="Trainer ID", @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Trainer deleted successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم حذف المدرب بنجاح")
     *         )
     *     ),
     *     @OA\Response(response=500, description="خطأ في الخادم"),
     *     @OA\Response(response=401, description="غير مصرح يجب تسجيل الدخول"),
     *     @OA\Response(response=403, description="ليس لديك الصلاحية للوصول إلى هذه البيانات"),
     *     @OA\Response(response=404, description="المدرب غير موجود"),
     *     @OA\Response(response=422, description="خطأ في البيانات المدخلة")
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        $user = Auth::user();

        try {
            $companyId = $this->permissionService->getEffectiveCompanyId($user);

            $deleted = $this->trainerService->deleteTrainer($id, $companyId, $user);

            if (!$deleted) {
                Log::info('TrainerController::destroy - No trainer found', [
                    'user_id' => Auth::user()->user_id,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'المدرب غير موجود'
                ], 404);
            }

            Log::info('TrainerController::destroy - Trainer deleted successfully', [
                'user_id' => Auth::user()->user_id,
                'trainer_id' => $id,
            ]);
            return response()->json([
                'success' => true,
                'message' => 'تم حذف المدرب بنجاح'
            ]);
        } catch (\Exception $e) {
            Log::error('TrainerController::destroy failed', [
                'error' => $e->getMessage(),
                'trainer_id' => $id,
                'user_id' => $user->user_id
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }
}
