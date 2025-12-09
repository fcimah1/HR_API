<?php

namespace App\Enums;

/**
 * Hierarchy Level Enum
 * 
 * Defines the 5 hierarchical levels for employees.
 * Level 1 is the highest (can request for all lower levels)
 * Level 5 is the lowest (cannot request for anyone)
 */
enum HierarchyLevel: int
{
    case LEVEL_1 = 1; // Highest - Can request for levels 2,3,4,5
    case LEVEL_2 = 2; // Can request for levels 3,4,5
    case LEVEL_3 = 3; // Can request for levels 4,5
    case LEVEL_4 = 4; // Can request for level 5 only
    case LEVEL_5 = 5; // Lowest - Cannot request for anyone

    /**
     * Get label for the hierarchy level
     */
    public function label(): string
    {
        return match ($this) {
            self::LEVEL_1 => 'المستوى الأول - الأعلى',
            self::LEVEL_2 => 'المستوى الثاني',
            self::LEVEL_3 => 'المستوى الثالث',
            self::LEVEL_4 => 'المستوى الرابع',
            self::LEVEL_5 => 'المستوى الخامس - الأدنى',
        };
    }

    /**
     * Get description for the hierarchy level
     */
    public function description(): string
    {
        return match ($this) {
            self::LEVEL_1 => 'يمكنه تقديم طلبات للمستويات 2، 3، 4، 5',
            self::LEVEL_2 => 'يمكنه تقديم طلبات للمستويات 3، 4، 5',
            self::LEVEL_3 => 'يمكنه تقديم طلبات للمستويات 4، 5',
            self::LEVEL_4 => 'يمكنه تقديم طلبات للمستوى 5 فقط',
            self::LEVEL_5 => ' لا يمكنه تقديم طلبات لأي موظف غيره',
        };
    }

    /**
     * Check if this level can make requests for another level
     */
    public function canMakeRequestFor(HierarchyLevel $otherLevel): bool
    {
        return $this->value < $otherLevel->value;
    }

    /**
     * Check if this level can make requests for a given level number
     */
    public function canMakeRequestForLevel(int $levelNumber): bool
    {
        return $this->value < $levelNumber;
    }

    /**
     * Get all levels that this level can make requests for
     * 
     * @return array<int>
     */
    public function getAllowedSubordinateLevels(): array
    {
        $allowed = [];
        for ($i = $this->value + 1; $i <= 5; $i++) {
            $allowed[] = $i;
        }
        return $allowed;
    }

    /**
     * Get all available hierarchy levels
     * 
     * @return array<HierarchyLevel>
     */
    public static function all(): array
    {
        return [
            self::LEVEL_1,
            self::LEVEL_2,
            self::LEVEL_3,
            self::LEVEL_4,
            self::LEVEL_5,
        ];
    }

    /**
     * Get all hierarchy levels as array with value and label
     * 
     * @return array<array{value: int, label: string, description: string}>
     */
    public static function toArray(): array
    {
        return array_map(
            fn(HierarchyLevel $level) => [
                'value' => $level->value,
                'case_name' => $level->name,
                'case_name_ar' => $level->label(),
                'description' => $level->description(),
            ],
            self::all()
        );
    }
}
