<?php

namespace App\Http\Controllers\Api;

use App\DTOs\Polls\CreatePollDTO;
use App\DTOs\Polls\PollFilterDTO;
use App\DTOs\Polls\UpdatePollDTO;
use App\DTOs\Polls\VotePollDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Polls\CreatePollRequest;
use App\Http\Requests\Polls\UpdatePollRequest;
use App\Http\Requests\Polls\VotePollRequest;
use App\Http\Resources\PollResource;
use App\Services\PollService;
use App\Services\SimplePermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="Polls",
 *     description="ادارة الاستبيانات"
 * )
 */
class PollController extends Controller
{
    public function __construct(
        protected PollService $pollService,
        protected SimplePermissionService $permissionService,
    ) {}

    /**
     * @OA\Get(
     *     path="/api/polls",
     *     summary="عرض قائمة الاستبيانات",
     *     tags={"Polls"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="بحث عن الاستبيانات",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="تصفية حسب الحالة (نشط، انتهت، مستقبلية)",
     *         required=false,
     *         @OA\Schema(type="string", enum={"active", "expired", "upcoming"})
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="رقم الصفحة",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="عدد العناصر في الصفحة",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="تم جلب الاستبيانات بنجاح",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم جلب الاستبيانات بنجاح"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="pagination", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="خطأ في الخادم",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="خطأ في الخادم")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="غير مصرح - يجب تسجيل الدخول",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="غير مصرح - يجب تسجيل الدخول")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="غير مصرح - ليس لديك صلاحية",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="غير مصرح - ليس لديك صلاحية")
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $companyId = $this->permissionService->getEffectiveCompanyId($user);

            $filters = PollFilterDTO::fromRequest($request->all(), $companyId);
            $result = $this->pollService->getPaginatedPolls($filters);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب الاستبيانات بنجاح',
                'data' => PollResource::collection($result['data']),
                'pagination' => $result['pagination'],
                'user_id' => $user->id,
            ]);
        } catch (\Exception $e) {
            Log::error('PollController::index failed', [
                'error' => $e->getMessage(),
                'message' => 'حدث خطأ أثناء جلب الاستبيانات',
                'user_id' => $user->id,
            ]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/polls",
     *     summary="إنشاء استبيان جديد",
     *     tags={"Polls"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"poll_title", "poll_start_date", "poll_end_date", "questions"},
     *             @OA\Property(property="poll_title", type="string", example="Employee Satisfaction"),
     *             @OA\Property(property="poll_start_date", type="string", format="date", example="2024-01-01"),
     *             @OA\Property(property="poll_end_date", type="string", format="date", example="2024-01-31"),
     *             @OA\Property(property="is_active", type="boolean", example=true),
     *             @OA\Property(property="questions", type="array", @OA\Items(
     *                 @OA\Property(property="poll_question", type="string", example="How satisfied are you?"),
     *                 @OA\Property(property="poll_answer1", type="string", example="Very Satisfied"),
     *                 @OA\Property(property="poll_answer2", type="string", example="Satisfied"),
     *                 @OA\Property(property="poll_answer3", type="string", example="Neutral")
     *             ))
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="تم إنشاء الاستبيان بنجاح",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم إنشاء الاستبيان بنجاح"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="غير مصرح - يجب تسجيل الدخول",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="غير مصرح - يجب تسجيل الدخول")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="غير مصرح - ليس لديك صلاحية",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="غير مصرح - ليس لديك صلاحية")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="خطأ في الخادم",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="خطأ في الخادم")
     *         )
     *     )
     * )
     */
    public function store(CreatePollRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $companyId = $this->permissionService->getEffectiveCompanyId($user);

            $dto = CreatePollDTO::fromRequest($request->validated(), $companyId, $user->user_id);
            $poll = $this->pollService->createPoll($dto);

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء الاستبيان بنجاح',
                'data' => $poll,
                'user_id' => $user->id,
            ], 201);
        } catch (\Exception $e) {
            Log::error('PollController::store failed', [
                'error' => $e->getMessage(),
                'message' => 'حدث خطأ أثناء إنشاء الاستبيان',
                'user_id' => $user->id,
            ]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/polls/{id}",
     *     summary="عرض تفاصيل الاستبيان",
     *     tags={"Polls"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="تم جلب تفاصيل الاستبيان بنجاح",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم جلب تفاصيل الاستبيان بنجاح"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="غير مصرح - يجب تسجيل الدخول",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="غير مصرح - يجب تسجيل الدخول")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="غير مصرح - ليس لديك صلاحية",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="غير مصرح - ليس لديك صلاحية")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="خطأ في الخادم",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="خطأ في الخادم")
     *         )
     *     )
     * )
     */
    public function show(int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $companyId = $this->permissionService->getEffectiveCompanyId($user);

            $poll = $this->pollService->getPollById($id, $companyId, $user->user_id);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب تفاصيل الاستبيان بنجاح',
                'data' => new PollResource($poll),
                'user_id' => $user->id,
            ]);
        } catch (\Exception $e) {
            Log::error('PollController::show failed', [
                'id' => $id,
                'error' => $e->getMessage(),
                'message' => 'حدث خطأ أثناء جلب تفاصيل الاستبيان',
                'user_id' => $user->id,
            ]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/polls/{id}/vote",
     *     summary="تصويت على الاستبيان",
     *     tags={"Polls"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"votes"},
     *             @OA\Property(property="votes", type="array", @OA\Items(
     *                 @OA\Property(property="question_id", type="integer", example=1, description="ID of the question from ci_polls_questions"),
     *                 @OA\Property(property="answer", type="integer", example="1", description="Answer text OR answer index (1-5)")
     *             ))
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="تم التصويت بنجاح",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم التصويت بنجاح")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="غير مصرح - يجب تسجيل الدخول",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="غير مصرح - يجب تسجيل الدخول")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="غير مصرح - ليس لديك صلاحية",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="غير مصرح - ليس لديك صلاحية")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="خطأ في الخادم",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="خطأ في الخادم")
     *         )
     *     )
     * )
     */
    public function vote(int $id, VotePollRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $companyId = $this->permissionService->getEffectiveCompanyId($user);

            $dto = VotePollDTO::fromRequest($request->validated(), $user->user_id, $companyId, $id);
            $this->pollService->vote($dto);

            return response()->json([
                'success' => true,
                'message' => 'تم التصويت بنجاح',
                'user_id' => $user->id,
            ]);
        } catch (\Exception $e) {
            Log::error('PollController::vote failed', [
                'id' => $id,
                'error' => $e->getMessage(),
                'message' => 'حدث خطأ أثناء التصويت',
                'user_id' => $user->id,
            ]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/polls/{id}",
     *     summary="تعديل الاستبيان",
     *     tags={"Polls"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"poll_title", "poll_start_date", "poll_end_date", "questions"},
     *             @OA\Property(property="poll_title", type="string", example="Employee Satisfaction Updated"),
     *             @OA\Property(property="poll_start_date", type="string", format="date", example="2024-01-01"),
     *             @OA\Property(property="poll_end_date", type="string", format="date", example="2024-01-31"),
     *             @OA\Property(property="is_active", type="boolean", example=true),
     *             @OA\Property(property="questions", type="array", @OA\Items(
     *                 @OA\Property(property="id", type="integer", example=1, description="ID of existing question, null for new"),
     *                 @OA\Property(property="poll_question", type="string", example="How satisfied are you now?"),
     *                 @OA\Property(property="poll_answer1", type="string", example="Very Satisfied"),
     *                 @OA\Property(property="poll_answer2", type="string", example="Satisfied")
     *             ))
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="تم تحديث الاستبيان بنجاح",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم تحديث الاستبيان بنجاح"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=422, description="فشل التحقق"),
     *     @OA\Response(response=404, description="الاستبيان غير موجود"),
     *     @OA\Response(response=500, description="خطأ في الخادم"),
     *     @OA\Response(response=403, description="غير مصرح - ليس لديك صلاحية"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول")
     * )
     */
    public function update(int $id, UpdatePollRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $companyId = $this->permissionService->getEffectiveCompanyId($user);

            $dto = UpdatePollDTO::fromRequest($request->validated(), $id, $companyId, $user->user_id);
            $poll = $this->pollService->updatePoll($dto);

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث الاستبيان بنجاح',
                'data' => new PollResource($poll),
                'user_id' => $user->id,
            ]);
        } catch (\Exception $e) {
            Log::error('PollController::update failed', [
                'id' => $id,
                'error' => $e->getMessage(),
                'message' => 'حدث خطأ أثناء تحديث الاستبيان',
                'user_id' => $user->id,
            ]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/polls/{id}",
     *     summary="حذف الاستبيان",
     *     tags={"Polls"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="تم حذف الاستبيان بنجاح",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم حذف الاستبيان بنجاح")
     *         )
     *     ),
     * @OA\Response(
     *         response=404,
     *         description="الاستبيان غير موجود",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="الاستبيان غير موجود")
     *         )
     *     ),
     * @OA\Response(
     *         response=500,
     *         description="خطأ في الخادم",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="خطأ في الخادم")
     *         )
     *     ),
     * @OA\Response(
     *         response=401,
     *         description="غير مصرح - يجب تسجيل الدخول",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="غير مصرح - يجب تسجيل الدخول")
     *         )
     *     ),
     * @OA\Response(
     *         response=403,
     *         description="غير مصرح - ليس لديك صلاحية",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="غير مصرح - ليس لديك صلاحية")
     *         )
     *     )
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $companyId = $this->permissionService->getEffectiveCompanyId($user);

            $this->pollService->deletePoll($id, $companyId);

            return response()->json([
                'success' => true,
                'message' => 'تم حذف الاستبيان بنجاح',
                'user_id' => $user->id,
            ]);
        } catch (\Exception $e) {
            Log::error('PollController::destroy failed', [
                'id' => $id,
                'error' => $e->getMessage(),
                'message' => 'حدث خطأ أثناء حذف الاستبيان',
                'user_id' => $user->id,
            ]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
