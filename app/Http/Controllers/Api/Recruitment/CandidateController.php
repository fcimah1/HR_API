<?php

namespace App\Http\Controllers\Api\Recruitment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Recruitment\Candidate\CandidateSearchRequest;
use App\Http\Requests\Recruitment\Candidate\UpdateCandidateStatusRequest;
use App\Http\Resources\Recruitment\CandidateResource;
use App\Services\Recruitment\CandidateService;
use Illuminate\Http\JsonResponse;
use App\Traits\ApiResponseTrait;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="Recruitment - Candidates",
 *     description="أدارة المرشحين"
 * )
 */
class CandidateController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        protected CandidateService $candidateService,
        protected \App\Services\SimplePermissionService $permissionService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/recruitment/candidates",
     *     summary="قائمة المرشحين",
     *     tags={"Recruitment - Candidates"},
     *     security={{ "bearerAuth": {} }},
     *     @OA\Parameter(name="page", in="query", example="1", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="per_page", in="query", example="10", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="paginate", in="query", example="true", @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="job_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="status", in="query", description="حالة المرشح (pending, invited_to_interview, rejected) أو (قيد الانتظار, دعوه للمقابله, مرفوض)", @OA\Schema(type="string", enum={"pending", "invited_to_interview", "rejected", "قيد الانتظار", "دعوه للمقابله", "مرفوض"})),
     *     @OA\Response(response=200, description="تم بنجاح"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=500, description="حدث خطأ أثناء عرض قائمة المرشحين")
     * )
     */
    public function index(CandidateSearchRequest $request): JsonResponse
    {
        try {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId(Auth::user());
            $filters = $request->validated();
            $filters['company_id'] = $effectiveCompanyId;

            $candidates = $this->candidateService->getCandidates($filters);
            Log::info('Candidates listed successfully', [
                'user_id' => Auth::id(),
                'company_id' => $effectiveCompanyId,
                'filters' => $filters
            ]);
            return $this->collectionResponse($candidates, CandidateResource::class);
        } catch (\Exception $e) {
            Log::error('Error listing candidates: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'company_id' => $effectiveCompanyId,
                'trace' => $e->getTraceAsString()
            ]);
            return $this->errorResponse('حدث خطأ أثناء عرض المرشحين', 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/recruitment/candidates/{id}",
     *     summary="تفاصيل المرشح",
     *     tags={"Recruitment - Candidates"},
     *     security={{ "bearerAuth": {} }},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="تفاصيل المرشح"),
     *     @OA\Response(response=404, description="المرشح غير موجود"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=500, description="حدث خطأ أثناء عرض تفاصيل المرشح")
     * )
     */
    public function show(int $id): JsonResponse
    {
        try {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId(Auth::user());
            $candidate = $this->candidateService->getCandidate($id, $effectiveCompanyId);
            if (!$candidate) {
                Log::error('Candidate not found: ' . $id, [
                    'user_id' => Auth::id(),
                    'candidate_id' => $id,
                ]);
                return $this->errorResponse('المرشح غير موجود', 404);
            }

            Log::info('Candidate shown successfully: ' . $candidate->id, [
                'user_id' => Auth::id(),
                'company_id' => $effectiveCompanyId,
                'candidate_id' => $id
            ]);
            return $this->successResponse(new CandidateResource($candidate));
        } catch (\Exception $e) {
            Log::error('Error showing candidate: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'company_id' => $effectiveCompanyId,
                'candidate_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            return $this->errorResponse('حدث خطأ أثناء عرض بيانات المرشح', 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/recruitment/candidates/{id}",
     *     summary="حذف المرشح",
     *     tags={"Recruitment - Candidates"},
     *     security={{ "bearerAuth": {} }},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="تم حذف المرشح بنجاح"),
     *     @OA\Response(response=404, description="المرشح غير موجود"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=500, description="حدث خطأ أثناء حذف المرشح")
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId(Auth::user());

            $candidate = $this->candidateService->getCandidate($id, $effectiveCompanyId);
            if (!$candidate) {
                Log::error('Candidate not found: ' . $id, [
                    'user_id' => Auth::id(),
                    'candidate_id' => $id,
                ]);
                return $this->errorResponse('المرشح غير موجود', 404);
            }


            $deleted = $this->candidateService->deleteCandidate($id, $effectiveCompanyId);
            if (!$deleted) {
                Log::error('Candidate could not be deleted: ' . $id, [
                    'user_id' => Auth::id(),
                    'company_id' => $effectiveCompanyId,
                    'candidate_id' => $id
                ]);
                return $this->errorResponse('Candidate could not be deleted', 500);
            }
            Log::info('Candidate deleted successfully: ' . $id, [
                'user_id' => Auth::id(),
                'company_id' => $effectiveCompanyId,
                'candidate_id' => $id
            ]);
            return $this->successResponse(null, 'تم حذف المرشح بنجاح');
        } catch (\Exception $e) {
            Log::error('Error deleting candidate: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'company_id' => $effectiveCompanyId,
                'candidate_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            return $this->errorResponse('حدث خطأ أثناء حذف المرشح', 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/recruitment/candidates/{id}/status",
     *     summary="تحديث حالة المرشح وجدولة مقابلة",
     *     tags={"Recruitment - Candidates"},
     *     security={{ "bearerAuth": {} }},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"application_status"},
     *             @OA\Property(property="application_status", type="string", description="حالة الطلب (invited_to_interview, rejected) أو (دعوه للمقابله, مرفوض)", enum={"invited_to_interview", "rejected", "دعوه للمقابله", "مرفوض"}, example="دعوه للمقابله"),
     *             @OA\Property(property="interview_date", type="string", format="date", example="2026-03-01", description="مطلوب فقط في حالة دعوة للمقابلة"),
     *             @OA\Property(property="interview_time", type="string", example="10:00", description="مطلوب فقط في حالة دعوة للمقابلة"),
     *             @OA\Property(property="interview_place", type="string", example="Office", description="مطلوب فقط في حالة دعوة للمقابلة"),
     *             @OA\Property(property="interviewer_id", type="integer", example=1, description="مطلوب فقط في حالة دعوة للمقابلة"),
     *             @OA\Property(property="description", type="string", example="Interview description", description="مطلوب فقط في حالة دعوة للمقابلة")
     *         )
     *     ),
     *     @OA\Response(response=200, description="تم تحديث الحالة بنجاح"),
     *     @OA\Response(response=404, description="المرشح غير موجود"),
     *     @OA\Response(response=422, description="بيانات غير صحيحة"),
     *     @OA\Response(response=500, description="حدث خطأ أثناء تحديث الحالة"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول")
     * )
     */
    public function updateStatus(int $id, UpdateCandidateStatusRequest $request): JsonResponse
    {
        try {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId(Auth::user());
            $candidate = $this->candidateService->getCandidate($id, $effectiveCompanyId);

            if (!$candidate) {
                Log::error('Candidate not found: ' . $id, [
                    'user_id' => Auth::id(),
                    'candidate_id' => $id,
                ]);
                return $this->errorResponse('المرشح غير موجود', 404);
            }

            $candidate = $this->candidateService->updateCandidateStatus($id, $effectiveCompanyId, $request->validated());
            Log::info('Candidate status updated successfully: ' . ($candidate->application_status?->label() ?? 'N/A'), [
                'user_id' => Auth::id(),
                'candidate_id' => $id,
                'company_id' => $effectiveCompanyId,
                'application_status' => $candidate->application_status?->value
            ]);

            $message = $candidate->application_status === \App\Enums\Recruitment\CandidateStatusEnum::REJECTED
                ? 'تم تحديث الحالة بالرفض'
                : 'تم تحديث الحالة بالموافقة على المقابلة';

            return $this->successResponse(new CandidateResource($candidate), $message);
        } catch (\Exception $e) {
            Log::error('Error updating candidate status: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'candidate_id' => $id,
                'trace' => $e->getTraceAsString(),
                'company_id' => $effectiveCompanyId
            ]);
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/recruitment/candidates/{id}/message",
     *     summary="عرض رسالة التقديم للمرشح",
     *     tags={"Recruitment - Candidates"},
     *     security={{ "bearerAuth": {} }},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="رسالة التقديم"),
     *     @OA\Response(response=404, description="المرشح غير موجود"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول")
     * )
     */
    public function showMessage(int $id): JsonResponse
    {
        try {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId(Auth::user());
            $candidate = $this->candidateService->getCandidate($id, $effectiveCompanyId);
            if (!$candidate) {
                Log::error('Candidate not found: ' . $id, [
                    'user_id' => Auth::id(),
                    'candidate_id' => $id,
                ]);
                return $this->errorResponse('المرشح غير موجود', 404);
            }
            Log::info('Candidate message shown successfully: ' . $candidate->message, [
                'user_id' => Auth::id(),
                'candidate_id' => $id,
                'company_id' => $effectiveCompanyId,
                'message' => $candidate->message
            ]);
            return $this->successResponse(['message' => $candidate->message]);
        } catch (\Exception $e) {
            Log::error('Error showing candidate message: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'candidate_id' => $id,
                'trace' => $e->getTraceAsString(),
                'company_id' => $effectiveCompanyId
            ]);
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/recruitment/candidates/{id}/download",
     *     summary="تحميل السيرة الذاتية",
     *     tags={"Recruitment - Candidates"},
     *     security={{ "bearerAuth": {} }},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="ملف السيرة الذاتية"),
     *     @OA\Response(response=404, description="المرشح غير موجود"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول")
     * )
     */
    public function downloadResume(int $id)
    {
        try {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId(Auth::user());
            $candidate = $this->candidateService->getCandidate($id, $effectiveCompanyId);
            if (!$candidate) {
                Log::error('Candidate not found: ' . $id, [
                    'user_id' => Auth::id(),
                    'candidate_id' => $id,
                ]);
                return $this->errorResponse('المرشح غير موجود', 404);
            }

            $filePath = $this->candidateService->downloadCandidateResume($id, $effectiveCompanyId);
            Log::info('Candidate resume downloaded successfully: ' . $filePath, [
                'user_id' => Auth::id(),
                'candidate_id' => $id,
                'company_id' => $effectiveCompanyId,
                'file_path' => $filePath
            ]);
            return response()->download($filePath);
        } catch (\Exception $e) {
            Log::error('Error downloading candidate resume: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'candidate_id' => $id,
                'trace' => $e->getTraceAsString(),
                'company_id' => $effectiveCompanyId
            ]);
            return $this->errorResponse($e->getMessage(), 404);
        }
    }
}
