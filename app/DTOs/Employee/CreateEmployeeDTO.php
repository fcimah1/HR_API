<?php

namespace App\DTOs\Employee;

class CreateEmployeeDTO extends BaseEmployeeDTO
{
    public function __construct(
        public readonly array $userData,
        public readonly array $detailsData
    ) {}

    public static function fromRequest(array $data): self
    {
        $instance = new static([], []);
        
        $userData = $instance->getBasicUserData($data);
        $userData['password'] = isset($data['password']) ? bcrypt($data['password']) : null;
        $userData['company_id'] = (int) $data['company_id'];
        $userData['company_name'] = $data['company_name'];
        $userData['is_active'] = 1;
        $userData['is_logged_in'] = 0;
        $userData['created_at'] = date('Y-m-d H:i:s');
        
        $detailsData = $instance->getEmployeeDetailsData($data);
        $detailsData['company_id'] = (int) $data['company_id'];
        $detailsData['leave_categories'] = $detailsData['leave_categories'] ?? 'all';
        $detailsData['is_work_from_home'] = isset($detailsData['is_work_from_home']) ? ($detailsData['is_work_from_home'] ? 1 : 0) : 0;
        $detailsData['is_eqama'] = isset($detailsData['is_eqama']) ? ($detailsData['is_eqama'] ? 1 : 0) : 1;
        $detailsData['created_at'] = date('Y-m-d H:i:s');

        return new self(
            userData: $instance->filterNullValues($userData),
            detailsData: $instance->filterNullValues($detailsData)
        );
    }

    public function getUserData(): array
    {
        return $this->userData;
    }

    public function getUserDetailsData(int $userId): array
    {
        return array_merge($this->detailsData, ['user_id' => $userId]);
    }
}
