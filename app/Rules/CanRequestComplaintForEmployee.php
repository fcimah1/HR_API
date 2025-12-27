<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Auth;

class CanRequestComplaintForEmployee implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (empty($value)) {
            return;
        }

        // If validation is applied to the array itself, $value is an array.
        // If applied to '.*', $value is a single ID.
        // We handle both just in case, though the request seems to apply it to the array column.
        $ids = is_array($value) ? $value : [$value];

        $user = Auth::user();
        $permissionService = app(\App\Services\SimplePermissionService::class);
        $effectiveCompanyId = $permissionService->getEffectiveCompanyId($user);

        foreach ($ids as $targetId) {
            $targetId = (int) $targetId; // ensure int

            // Check if user exists and is in the same company
            $exists = User::where('user_id', $targetId)
                ->where('company_id', $effectiveCompanyId)
                ->where('is_active', 1)
                ->exists();

            if (!$exists) {
                $fail("الموظف المحدد (ID: {$targetId}) غير موجود أو لا ينتمي لنفس الشركة.");
                return;
            }
        }
    }
}
