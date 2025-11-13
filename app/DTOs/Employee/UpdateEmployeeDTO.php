<?php

namespace App\DTOs\Employee;

class UpdateEmployeeDTO extends BaseEmployeeDTO
{
    public function __construct(
        public readonly int $userId,
        public readonly array $userData,
        public readonly array $detailsData
    ) {}

    public static function fromRequest(int $userId, array $data): self
    {
        $instance = new static($userId, [], []);
        
        $userData = $instance->getBasicUserData($data);
        if (isset($data['password'])) {
            $userData['password'] = bcrypt($data['password']);
        }
        if (isset($data['is_active'])) {
            $userData['is_active'] = (bool) $data['is_active'] ? 1 : 0;
        }
        
        $detailsData = $instance->getEmployeeDetailsData($data);
        if (isset($detailsData['is_work_from_home'])) {
            $detailsData['is_work_from_home'] = $detailsData['is_work_from_home'] ? 1 : 0;
        }
        if (isset($detailsData['is_eqama'])) {
            $detailsData['is_eqama'] = $detailsData['is_eqama'] ? 1 : 0;
        }

        return new self(
            userId: $userId,
            userData: $instance->filterNullValues($userData),
            detailsData: $instance->filterNullValues($detailsData)
        );
    }

    public function getUserData(): array
    {
        return $this->userData;
    }

    public function getUserDetailsData(): array
    {
        return $this->detailsData;
    }

    public function hasUserUpdates(): bool
    {
        return !empty($this->userData);
    }

    public function hasDetailsUpdates(): bool
    {
        return !empty($this->detailsData);
    }
}
