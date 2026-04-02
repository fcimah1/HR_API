<?php

namespace App\Http\Controllers\Api\Recruitment;

use App\DTOs\Recruitment\Job\ApplyJobDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Recruitment\Job\CreateJobRequest;
use App\Http\Requests\Recruitment\Job\JobSearchRequest;
use App\Http\Requests\Recruitment\Job\UpdateJobRequest;
use App\Http\Resources\Recruitment\JobResource;
use App\Services\Recruitment\JobService;
use Illuminate\Http\JsonResponse;
use App\Traits\ApiResponseTrait;
use App\DTOs\Recruitment\Job\CreateJobDTO;
use App\DTOs\Recruitment\Job\UpdateJobDTO;
use App\Http\Requests\Recruitment\Job\ApplyJobRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="Recruitment - Jobs",
 *     description="وظائف التوظيف"
 * )
 */
class JobController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        protected JobService $jobService,
        protected \App\Services\SimplePermissionService $permissionService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/recruitment/jobs",
     *     summary="عرض قائمة الوظائف",
     *     tags={"Recruitment - Jobs"},
     *     security={{ "bearerAuth": {} }},
     *     @OA\Parameter(name="page", in="query", example="1", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="per_page", in="query", example="10", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="paginate", in="query", example="true", @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="company_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="status", in="query", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="تم عرض قائمة الوظائف بنجاح")
     * )
     */
    public function index(JobSearchRequest $request): JsonResponse
    {
        try {
            $filters = $request->validated();
            $filters['company_id'] = $this->permissionService->getEffectiveCompanyId(Auth::user());

            $jobs = $this->jobService->getJobs($filters);
            return $this->collectionResponse($jobs, JobResource::class);
        } catch (\Exception $e) {
            Log::error('Error listing jobs: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->errorResponse('حدث خطأ أثناء عرض الوظائف', 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/recruitment/jobs",
     *     summary="إضافة وظيفة",
     *     tags={"Recruitment - Jobs"},
     *     security={{ "bearerAuth": {} }},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 required={"job_title", "designation_id", "job_vacancy", "gender", "minimum_experience", "date_of_closing", "status", "company_id"},
     *                 @OA\Property(property="job_title", example="PHP developer", type="string"),
     *                 @OA\Property(property="designation_id", example="1", type="integer"),
     *                 @OA\Property(property="job_vacancy", example="1", type="integer"),
     *                 @OA\Property(property="gender", example="ذكر", type="string", enum={"ذكر", "انثى", "لا_يوجد"}),
     *                 @OA\Property(property="minimum_experience", example=1, type="integer", enum={"حديث التخرج","سنة","سنتان","3 سنوات","4 سنوات","5 سنوات","6 سنوات","7 سنوات","8 سنوات","9 سنوات","10 سنوات","10+ سنوات"}),
     *                 @OA\Property(property="date_of_closing", example="2026-02-14", type="string", format="date"),
     *                 @OA\Property(property="short_description", example="short_description", type="string"),
     *                 @OA\Property(property="long_description", example="long_description", type="string"),
     *                 @OA\Property(property="status", example="تم النشر", type="string", enum={"تم النشر", "غير منشور"}),
     *                 @OA\Property(property="job_type", example="دائم", type="string", enum={"دائم", "دوام جزئي", "عقد", "تجريبي"})
     *             )
     *         )
     *     ),
     *     @OA\Response(response=201, description="تم إضافة الوظيفة بنجاح"),
     *     @OA\Response(response=422, description="فشل فى التحقق من البيانات")
     * )
     */
    public function store(CreateJobRequest $request): JsonResponse
    {
        try {
            $dto = $request->validated();
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId(Auth::user());

            // Always assign company_id to ensure DTO has the key, even if 0
            $dto['company_id'] = $effectiveCompanyId;

            $job = $this->jobService->createJob(CreateJobDTO::from($dto));

            Log::info('Job created successfully', [
                'job_id' => $job->id,
                'user_id' => Auth::id(),
                'company_id' => $effectiveCompanyId,
            ]);

            return $this->successResponse(new JobResource($job), 'تم إضافة الوظيفة بنجاح', 201);
        } catch (\Exception $e) {
            Log::error('Error creating job: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'request' => $request->all(),
                'company_id' => $effectiveCompanyId,
                'error' => $e->getMessage(),
            ]);
            return $this->errorResponse('حدث خطأ أثناء إضافة الوظيفة', 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/recruitment/jobs/{id}",
     *     summary="عرض تفاصيل الوظيفة",
     *     tags={"Recruitment - Jobs"},
     *     security={{ "bearerAuth": {} }},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="تم عرض تفاصيل الوظيفة بنجاح"),
     *     @OA\Response(response=404, description="الوظيفة غير موجودة")
     * )
     */
    public function show(int $id): JsonResponse
    {
        try {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId(Auth::user());
            // Ideally we should also check if the user has access to this job's company
            $job = $this->jobService->getJob($id);
            if (!$job) {
                Log::error('Job not found', [
                    'job_id' => $id,
                    'user_id' => Auth::id(),
                    'company_id' => $effectiveCompanyId,
                ]);
                return $this->errorResponse('الوظيفة غير موجودة', 404);
            }

            Log::info('Job retrieved', [
                'job_id' => $job->id,
                'user_id' => Auth::id(),
                'company_id' => $effectiveCompanyId,
            ]);
            return $this->successResponse(new JobResource($job));
        } catch (\Exception $e) {
            Log::error('Error getting job: ' . $e->getMessage(), [
                'job_id' => $id,
                'user_id' => Auth::id(),
                'company_id' => $effectiveCompanyId,
                'trace' => $e->getTraceAsString()
            ]);
            return $this->errorResponse('حدث خطأ أثناء عرض الوظيفة', 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/recruitment/jobs/{id}",
     *     summary="تعديل الوظيفة",
     *     tags={"Recruitment - Jobs"},
     *     security={{ "bearerAuth": {} }},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 required={"job_title", "designation_id", "job_vacancy", "gender", "minimum_experience", "date_of_closing", "status", "company_id"},
     *                 @OA\Property(property="job_title", example="PHP developer", type="string"),
     *                 @OA\Property(property="designation_id", example="1", type="integer"),
     *                 @OA\Property(property="job_vacancy", example="1", type="integer"),
     *                 @OA\Property(property="gender", example="ذكر", type="string", enum={"ذكر", "أنثى", "غير محدد"}),
     *                 @OA\Property(property="minimum_experience", example="حديث التخرج", type="string", enum={"حديث التخرج","سنة","سنتان","3 سنوات","4 سنوات","5 سنوات","6 سنوات","7 سنوات","8 سنوات","9 سنوات","10 سنوات","10+ سنوات"}),
     *                 @OA\Property(property="date_of_closing", example="2026-02-14", type="string", format="date"),
     *                 @OA\Property(property="short_description", example="", type="string"),
     *                 @OA\Property(property="long_description", example="", type="string"),
     *                 @OA\Property(property="status", example="تم النشر", type="string", enum={"تم النشر", "غير منشور"}),
     *                 @OA\Property(property="job_type", example="دائم", type="string", enum={"دائم", "دوام جزئي", "عقد", "تجريبي"})
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="تم تعديل الوظيفة بنجاح"),
     *     @OA\Response(response=404, description="الوظيفة غير موجودة")
     * )
     */
    public function update(UpdateJobRequest $request, int $id): JsonResponse
    {
        try {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId(Auth::user());

            $job = $this->jobService->updateJob($id, UpdateJobDTO::from($request->validated()));

            Log::info('Job updated successfully', [
                'job_id' => $job->id,
                'user_id' => Auth::id(),
                'company_id' => $effectiveCompanyId,
            ]);
            return $this->successResponse(new JobResource($job), 'تم تعديل الوظيفة بنجاح');
        } catch (\Throwable $th) {
            Log::error('Job update failed', [
                'job_id' => $id,
                'user_id' => Auth::id(),
                'company_id' => $effectiveCompanyId,
                'error' => $th->getMessage(),
            ]);
            return $this->errorResponse('حدث خطأ أثناء تعديل الوظيفة', 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/recruitment/jobs/{id}",
     *     summary="غلق الوظيفة",
     *     tags={"Recruitment - Jobs"},
     *     security={{ "bearerAuth": {} }},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="تم غلق الوظيفة بنجاح"),
     *     @OA\Response(response=404, description="الوظيفة غير موجودة")
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId(Auth::user());

            $existingJob = $this->jobService->getJob($id);
            if (!$existingJob) {
                Log::error('Job not found', [
                    'job_id' => $id,
                    'user_id' => Auth::id(),
                    'company_id' => $effectiveCompanyId,
                ]);
                return $this->errorResponse('الوظيفة غير موجودة', 404);
            }

            $deleted = $this->jobService->deleteJob($id);
            if (!$deleted) {
                Log::error('Job not found', [
                    'job_id' => $id,
                    'user_id' => Auth::id(),
                    'company_id' => $effectiveCompanyId,
                ]);
                return $this->errorResponse('الوظيفة غير موجودة', 500);
            }
            Log::info('Job closed successfully', [
                'job_id' => $id,
                'user_id' => Auth::id(),
                'company_id' => $effectiveCompanyId,
            ]);
            return $this->successResponse(null, 'تم غلق الوظيفه');
        } catch (\Exception $e) {
            Log::error('Error closing job: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'job_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            return $this->errorResponse('حدث خطأ أثناء غلق الوظيفة', 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/recruitment/jobs/apply",
     *     summary="التقديم على وظيفة",
     *     tags={"Recruitment - Jobs"},
     *     security={{ "bearerAuth": {} }},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"job_id", "job_resume"},
     *                 @OA\Property(property="job_id", type="string", example="20"),
     *                 @OA\Property(property="message", type="string", example="انا مهتم بهذه الوظيفة"),
     *                 @OA\Property(property="job_resume", type="string", format="binary", description="ملف السيرة الذاتية (pdf, doc, jpeg, png)")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=201, description="تم إرسال طلب التقديم بنجاح"),
     *     @OA\Response(response=422, description="فشل فى التحقق من البيانات"),
     *     @OA\Response(response=400, description="خطأ في طلب التقديم")
     * )
     */
    public function apply(ApplyJobRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);

            // Get job to find designation
            $job = $this->jobService->getJob($request->job_id);
            if (!$job) {
                return $this->errorResponse('الوظيفة غير موجودة', 404);
            }

            $data = $request->validated();
            $data['company_id'] = $effectiveCompanyId;
            $data['designation_id'] = $job->designation_id;
            $data['staff_id'] = $user->user_id;
            $data['job_resume'] = $request->file('job_resume');

            $candidate = $this->jobService->applyToJob(ApplyJobDTO::from($data));

            return $this->successResponse($candidate, 'تم إرسال طلب التقديم بنجاح', 201);
        } catch (\Exception $e) {
            Log::error('Error applying for job: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'request' => $request->all(),
            ]);
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/recruitment/jobs/constants/enums",
     *     summary="استرجاع الثوابت (Enums)",
     *     tags={"Recruitment - Jobs"},
     *     security={{ "bearerAuth": {} }},
     *     @OA\Response(response=200, description="تم استرجاع الثوابت بنجاح")
     * )
     */
    public function getConstants(): JsonResponse
    {
        try {
            $enums = [
                'status' => \App\Enums\Recruitment\JobStatusEnum::cases(),
                'job_type' => \App\Enums\JobTypeEnum::toArray(),
                'gender' => \App\Enums\GenderEnum::toArray(),
                'experience_level' => \App\Enums\ExperienceLevel::toArray(),
                'candidate_status' => \App\Enums\Recruitment\CandidateStatusEnum::cases(),
            ];

            // Reformat native cases to match toArray structure if needed
            $enums['status'] = array_map(fn($case) => [
                'value' => $case->value,
                'label' => $case->label(),
            ], $enums['status']);

            $enums['candidate_status'] = array_map(fn($case) => [
                'value' => $case->value,
                'label' => $case->label(),
            ], $enums['candidate_status']);

            return $this->successResponse($enums);
        } catch (\Exception $e) {
            Log::error('Error getting recruitment constants: ' . $e->getMessage());
            return $this->errorResponse('حدث خطأ أثناء استرجاع الثوابت', 500);
        }
    }
}
