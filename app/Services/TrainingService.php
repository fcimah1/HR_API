<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Training\CreateTrainingDTO;
use App\DTOs\Training\TrainingFilterDTO;
use App\DTOs\Training\UpdateTrainingDTO;
use App\Enums\TrainingPerformanceEnum;
use App\Enums\TrainingStatusEnum;
use App\Http\Resources\TrainingResource;
use App\Http\Resources\TrainingNoteResource;
use App\Models\Training;
use App\Models\User;
use App\Repository\Interface\TrainingRepositoryInterface;
use Illuminate\Support\Facades\Log;

class TrainingService
{
    public function __construct(
        protected TrainingRepositoryInterface $trainingRepository,
        protected SimplePermissionService $permissionService,
    ) {}

    /**
     * Get paginated trainings with filters and permission check
     */
    public function getPaginatedTrainings(TrainingFilterDTO $filters, User $user): array
    {
        // إنشاء filters جديد بناءً على صلاحيات المستخدم
        $filterData = $filters->toArray();

        // التحقق من نوع المستخدم (company أو staff فقط)
        if ($user->user_type == 'company') {
            // مدير الشركة: يرى جميع التدريبات في الشركة
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
            $filterData['company_id'] = $effectiveCompanyId;
        } else {
            // موظف (staff): يرى التدريبات الخاصة به + تدريبات الموظفين التابعين له
            $subordinateIds = $this->getSubordinateEmployeeIds($user);

            if (!empty($subordinateIds)) {
                // لديه موظفين تابعين: تدريباته + تدريبات التابعين

                // Filter subordinates based on restrictions (Department/Branch restrictions from OperationRestriction)
                $subordinateIds = array_filter($subordinateIds, function ($empId) use ($user) {
                    $emp = User::findOrFail($empId);
                    if (!$emp) return false;
                    return $this->permissionService->canViewEmployeeRequests($user, $emp);
                });

                $subordinateIds[] = $user->user_id; // إضافة نفسه
                $filterData['employee_ids'] = $subordinateIds;
                $filterData['company_id'] = $user->company_id;

                // إضافة فلترة المستويات الهرمية للموظفين التابعين
                $hierarchyLevels = $this->permissionService->getUserHierarchyLevel($user);
                if ($hierarchyLevels !== null) {
                    // جلب المستويات الأعلى من مستوى المدير
                    $filterData['hierarchy_levels'] = range($hierarchyLevels + 1, 5);
                }
            } else {
                // ليس لديه موظفين تابعين: تدريباته فقط
                $filterData['employee_id'] = $user->user_id;
                $filterData['company_id'] = $user->company_id;
            }
        }

        // إنشاء DTO جديد مع البيانات المحدثة
        $updatedFilters = TrainingFilterDTO::fromRequest($filterData);

        $result = $this->trainingRepository->getPaginatedTrainings($updatedFilters, $user);

        // Convert data items to Resources
        $result['data'] = TrainingResource::collection(
            collect($result['data'])->map(
                fn($item) => is_array($item)
                    ? Training::find($item['training_id'])
                    : $item
            )->filter()
        )->resolve();

        return $result;
    }

    /**
     * Get training enums for dropdowns
     */
    public function getTrainingEnums(): array
    {
        return [
            'statuses' => TrainingStatusEnum::toArray(),
            'performance_levels' => TrainingPerformanceEnum::toArray(),
        ];
    }

    /**
     * Create a new training
     */
    public function createTraining(CreateTrainingDTO $dto, User $user): TrainingResource
    {
        // Check hierarchy permissions for staff users creating for other employees
        if ($user->user_type !== 'company' && !empty($dto->employeeIds)) {
            // Validate that the user has permission to create training for each employee
            foreach ($dto->employeeIds as $employeeId) {
                if ((int) $employeeId !== $user->user_id) {
                    $employee = User::query()->where('user_id', '=', (int) $employeeId)->first();
                    if (!$employee || !$this->permissionService->canViewEmployeeRequests($user, $employee)) {
                        Log::warning('TrainingService::createTraining - Hierarchy permission denied', [
                            'requester_id' => $user->user_id,
                            'requester_type' => $user->user_type,
                            'target_employee_id' => $employeeId,
                            'requester_level' => $this->permissionService->getUserHierarchyLevel($user),
                            'target_level' => $this->permissionService->getUserHierarchyLevel($employee),
                            'message' => 'ليس لديك صلاحية لإنشاء تدريب لهذا الموظف',
                        ]);
                        throw new \Exception('ليس لديك صلاحية لإنشاء تدريب لهذا الموظف');
                    }
                }
            }
        }

        $training = $this->trainingRepository->create($dto);

        Log::info('Training created', [
            'training_id' => $training->training_id,
            'company_id' => $dto->companyId,
        ]);

        return new TrainingResource($training);
    }

    /**
     * Get training by ID with permission check
     */
    public function getTrainingById(int $id, int $companyId): ?TrainingResource
    {
        $training = $this->trainingRepository->findByIdInCompany($id, $companyId);

        if (!$training) {
            return null;
        }

        return new TrainingResource($training);
    }

    /**
     * Update training
     */
    public function updateTraining(int $id, UpdateTrainingDTO $dto, int $companyId): ?TrainingResource
    {
        $training = $this->trainingRepository->findByIdInCompany($id, $companyId);

        if (!$training) {
            return null;
        }

        $training = $this->trainingRepository->update($training, $dto);

        Log::info('Training updated', [
            'training_id' => $training->training_id,
            'company_id' => $companyId,
        ]);

        return new TrainingResource($training);
    }

    /**
     * Delete training
     */
    public function deleteTraining(int $id, int $companyId): bool
    {
        $training = $this->trainingRepository->findByIdInCompany($id, $companyId);

        if (!$training) {
            return false;
        }

        $result = $this->trainingRepository->delete($training);

        if ($result) {
            Log::info('Training deleted', [
                'training_id' => $id,
                'company_id' => $companyId,
            ]);
        }

        return $result;
    }

    /**
     * Update training status and optionally performance
     */
    public function updateTrainingStatus(int $id, int $status, int $companyId, ?int $performance = null, ?string $remarks = null): ?TrainingResource
    {
        $training = $this->trainingRepository->findByIdInCompany($id, $companyId);

        if (!$training) {
            return null;
        }

        // Validate status
        if (TrainingStatusEnum::tryFrom($status) === null) {
            throw new \InvalidArgumentException('Invalid training status');
        }

        // Validate performance if provided
        if ($performance !== null && TrainingPerformanceEnum::tryFrom($performance) === null) {
            throw new \InvalidArgumentException('Invalid performance level');
        }

        $training = $this->trainingRepository->updateStatus($training, $status, $performance, $remarks);

        Log::info('Training status updated', [
            'training_id' => $training->training_id,
            'new_status' => $status,
            'performance' => $performance,
        ]);

        return new TrainingResource($training);
    }

    /**
     * Add note to training
     */
    public function addNote(int $trainingId, int $companyId, int $employeeId, string $note): TrainingNoteResource
    {
        // Verify training exists in company
        $training = $this->trainingRepository->findByIdInCompany($trainingId, $companyId);

        if (!$training) {
            throw new \Exception('Training not found');
        }

        $noteRecord = $this->trainingRepository->addNote($trainingId, $companyId, $employeeId, $note);

        Log::info('Training note added', [
            'training_id' => $trainingId,
            'employee_id' => $employeeId,
        ]);

        return new TrainingNoteResource($noteRecord);
    }

    /**
     * Get training notes
     */
    public function getNotes(int $trainingId, int $companyId): array
    {
        // Verify training exists in company
        $training = $this->trainingRepository->findByIdInCompany($trainingId, $companyId);

        if (!$training) {
            throw new \Exception('Training not found');
        }

        return $this->trainingRepository->getNotes($trainingId);
    }

    /**
     * Get training statistics
     */
    public function getStatistics(int $companyId): array
    {
        return $this->trainingRepository->getStatistics($companyId);
    }

    /**
     * الحصول على جميع معرفات الموظفين التابعين
     */
    private function getSubordinateEmployeeIds(User $manager): array
    {
        $allEmployees = User::query()
            ->where('company_id', '=', $manager->company_id)
            ->where('user_type', '=', 'staff')
            ->with('user_details.designation')
            ->get();

        if ($allEmployees->isEmpty()) {
            return [];
        }

        $managerLevel = $this->permissionService->getUserHierarchyLevel($manager);
        if ($managerLevel === null) {
            return [];
        }

        $subordinates = [];
        foreach ($allEmployees as $emp) {
            $empLevel = $emp->getHierarchyLevel();
            // المدير يرى الموظفين في مستويات أعلى (أرقام أكبر)
            if ($empLevel !== null && $empLevel > $managerLevel) {
                $subordinates[] = $emp->user_id;
            }
        }

        return $subordinates;
    }
}
