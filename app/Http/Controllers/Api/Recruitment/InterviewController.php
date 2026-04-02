<?php

namespace App\Http\Controllers\Api\Recruitment;

use App\Http\Controllers\Controller;
use App\Enums\Recruitment\InterviewStatusEnum;
use App\Http\Requests\Recruitment\Interview\InterviewSearchRequest;
use App\Http\Requests\Recruitment\Interview\UpdateInterviewStatusRequest;
use App\Http\Resources\Recruitment\InterviewResource;
use App\Services\Recruitment\InterviewService;
use Illuminate\Http\JsonResponse;
use App\Traits\ApiResponseTrait;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="Recruitment - Interviews",
 *     description="إدارة المقابلات"
 * )
 */
class InterviewController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        protected InterviewService $interviewService,
        protected \App\Services\SimplePermissionService $permissionService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/recruitment/interviews",
     *     summary="قائمة المقابلات",
     *     tags={"Recruitment - Interviews"},
     *     security={{ "bearerAuth": {} }},
     *     @OA\Parameter(name="page", in="query", example="1", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="per_page", in="query", example="10", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="paginate", in="query", example="true", @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="status", in="query", description="حالة المقابلة (not_started, successful, rejected) أو (لم يبدا, مقابله ناجحه, مرفوض)", @OA\Schema(type="string", enum={"not_started", "successful", "rejected", "لم يبدا", "مقابله ناجحه", "مرفوض"})),
     *     @OA\Parameter(name="job_id", in="query",  @OA\Schema(type="integer")),
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="تم عرض قائمة المقابلات بنجاح")
     * )
     */
    public function index(InterviewSearchRequest $request): JsonResponse
    {
        try {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId(Auth::user());
            $filters = $request->validated();
            $filters['company_id'] = $effectiveCompanyId;

            $interviews = $this->interviewService->getInterviews($filters);
            Log::info('Interviews listed successfully', [
                'user_id' => Auth::id(),
                'filters' => $filters,
                'company_id' => $filters['company_id'],
            ]);
            return $this->collectionResponse($interviews, InterviewResource::class);
        } catch (\Exception $e) {
            Log::error('Error listing interviews: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'company_id' => $filters['company_id'],
                'trace' => $e->getTraceAsString()
            ]);
            return $this->errorResponse('حدث خطأ أثناء عرض المقابلات', 500);
        }
    }


    /**
     * @OA\Get(
     *     path="/api/recruitment/interviews/{id}",
     *     summary="تفاصيل المقابلة",
     *     tags={"Recruitment - Interviews"},
     *     security={{ "bearerAuth": {} }},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="تم عرض تفاصيل المقابلة بنجاح"),
     *     @OA\Response(response=404, description="المقابلة غير موجودة")
     * )
     */
    public function show(int $id): JsonResponse
    {
        try {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId(Auth::user());
            $interview = $this->interviewService->getInterview($id, $effectiveCompanyId);
            if (!$interview) {
                Log::error('Interview not found', [
                    'user_id' => Auth::id(),
                    'interview_id' => $id,
                    'company_id' => $effectiveCompanyId,
                ]);
                return $this->errorResponse('المقابلة غير موجودة', 404);
            }

            Log::info('Interview shown successfully', [
                'user_id' => Auth::id(),
                'interview_id' => $interview->job_interview_id,
                'company_id' => $effectiveCompanyId,
            ]);
            return $this->successResponse(new InterviewResource($interview));
        } catch (\Exception $e) {
            Log::error('Error showing interview: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'interview_id' => $id,
                'company_id' => $effectiveCompanyId,
                'error' => $e->getMessage(),
            ]);
            return $this->errorResponse('حدث خطأ أثناء عرض بيانات المقابلة', 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/recruitment/interviews/{id}/status",
     *     summary="تحديث حالة المقابلة",
     *     tags={"Recruitment - Interviews"},
     *     security={{ "bearerAuth": {} }},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"status", "interview_remarks"},
     *             @OA\Property(property="status", type="string", enum={"لم يبدأ", "مقابلة ناجحة", "مرفوض"}, example="لم يبدأ"),
     *             @OA\Property(property="interview_remarks", type="string", example="remarks content")
     *         )
     *     ),
     *     @OA\Response(response=200, description="تم تحديث الحالة بنجاح"),
     *     @OA\Response(response=404, description="المقابلة غير موجودة")
     * )
     */
    public function updateStatus(int $id, UpdateInterviewStatusRequest $request): JsonResponse
    {
        try {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId(Auth::user());
            $interview = $this->interviewService->updateInterviewStatus($id, $effectiveCompanyId, $request->validated());

            Log::info('Interview status updated: ' . ($interview->status?->label() ?? 'N/A'), [
                'user_id' => Auth::id(),
                'interview_id' => $id,
                'company_id' => $effectiveCompanyId,
                'status' => $interview->status?->value
            ]);

            return $this->successResponse(new InterviewResource($interview), 'تم تحديث حالة المقابلة بنجاح');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}
