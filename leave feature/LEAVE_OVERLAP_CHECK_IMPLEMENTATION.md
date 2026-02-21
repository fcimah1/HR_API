# Leave Overlap Check Implementation

## Overview

This document describes the implementation of a comprehensive leave overlap checker that prevents employees from submitting overlapping leave requests regardless of leave type.

## Problem Statement

Previously, the system only checked for overlapping leaves of the **same leave type**. This meant an employee could have:
- Annual leave from March 10-15
- Sick leave from March 12-18 (overlapping)

This created scheduling conflicts and data integrity issues.

## Solution

Implemented a comprehensive overlap check that prevents **any** overlapping leave requests, regardless of leave type.

## Changes Made

### 1. Language Files

Added bilingual error messages for better user experience:

**English** (`app/Language/en/Main.php`):
```php
'xin_overlapping_leave_request' => 'You already have a leave request during this period. Please check your existing leave applications.',
```

**Arabic** (`app/Language/ar/Main.php`):
```php
'xin_overlapping_leave_request' => 'لديك بالفعل طلب إجازة خلال هذه الفترة. يرجى التحقق من طلبات الإجازة الحالية الخاصة بك.',
```

### 2. Leave Controller

Modified `app/Controllers/Erp/Leave.php` in the `add_leave()` method (around line 1077):

**Before:**
```php
foreach ($form_dates as  $fdate) {
    $builder = $db->table('ci_leave_applications');
    $builder->where('"' . $fdate . '" BETWEEN from_date AND to_date');
    $builder->where('employee_id', $luser_id);
    $builder->where('leave_type_id', $leave_type); // Only checked same type
    $builder->where('status!=', 3);
    // ...
}
```

**After:**
```php
// Check for overlapping leave requests of ANY type
$builder = $db->table('ci_leave_applications');
$builder->where('employee_id', $luser_id);
$builder->where('status!=', 3); // Exclude rejected leaves
$builder->groupStart()
    ->where("from_date BETWEEN '{$start_date}' AND '{$end_date}'")
    ->orWhere("to_date BETWEEN '{$start_date}' AND '{$end_date}'")
    ->orWhere("(from_date <= '{$start_date}' AND to_date >= '{$end_date}')")
->groupEnd();
$query = $builder->get();
$overlappingLeaves = $query->getResultArray();

if (count($overlappingLeaves) > 0) {
    $existingLeave = $overlappingLeaves[0];
    $existingLeaveType = $ConstantsModel->where('constants_id', $existingLeave['leave_type_id'])->first();
    $leaveTypeName = $existingLeaveType ? $existingLeaveType['category_name'] : 'Unknown';
    
    $Return['error'] = lang('Main.xin_overlapping_leave_request') . 
                       ' (' . $leaveTypeName . ': ' . 
                       $existingLeave['from_date'] . ' - ' . 
                       $existingLeave['to_date'] . ')';
    $Return['csrf_hash'] = csrf_hash();
    $this->output($Return);
}
```

## Overlap Detection Logic

The new check detects overlaps using three conditions:

1. **New leave starts within existing leave:**
   ```sql
   from_date BETWEEN existing_from AND existing_to
   ```

2. **New leave ends within existing leave:**
   ```sql
   to_date BETWEEN existing_from AND existing_to
   ```

3. **New leave encompasses existing leave:**
   ```sql
   from_date <= existing_from AND to_date >= existing_to
   ```

## Test Cases

All test cases pass successfully:

### Test Case 1: Overlapping Leave (Different Type)
- **Existing:** Annual Leave (2026-03-10 to 2026-03-15)
- **New:** Sick Leave (2026-03-12 to 2026-03-18)
- **Result:** ✓ BLOCKED (overlap detected)

### Test Case 2: Non-Overlapping Leave
- **Existing:** Annual Leave (2026-03-10 to 2026-03-15)
- **New:** Sick Leave (2026-03-20 to 2026-03-25)
- **Result:** ✓ ALLOWED (no overlap)

### Test Case 3: Exact Same Dates
- **Existing:** Annual Leave (2026-03-10 to 2026-03-15)
- **New:** Sick Leave (2026-03-10 to 2026-03-15)
- **Result:** ✓ BLOCKED (exact overlap)

### Test Case 4: Partial Overlap (Start Date)
- **Existing:** Annual Leave (2026-03-10 to 2026-03-15)
- **New:** Leave (2026-03-14 to 2026-03-20)
- **Result:** ✓ BLOCKED (partial overlap)

### Test Case 5: Encompassing Leave
- **Existing:** Annual Leave (2026-03-10 to 2026-03-15)
- **New:** Leave (2026-03-05 to 2026-03-20)
- **Result:** ✓ BLOCKED (encompasses existing)

## Error Message Format

When an overlap is detected, the user sees:

**English:**
```
You already have a leave request during this period. Please check your existing leave applications. 
(Annual Leave: 2026-03-10 - 2026-03-15)
```

**Arabic:**
```
لديك بالفعل طلب إجازة خلال هذه الفترة. يرجى التحقق من طلبات الإجازة الحالية الخاصة بك.
(إجازة سنوية: 2026-03-10 - 2026-03-15)
```

## Benefits

1. **Prevents scheduling conflicts:** No more double-booking of employees
2. **Data integrity:** Ensures accurate leave tracking
3. **Better user experience:** Clear error messages in both English and Arabic
4. **Comprehensive coverage:** Works for all leave types (annual, sick, maternity, etc.)
5. **Includes leave details:** Shows which existing leave is causing the conflict

## Backward Compatibility

- Existing leave records are not affected
- Only applies to new leave requests
- Rejected leaves (status = 3) are excluded from overlap checks
- Permission requests (hourly leaves) continue to use the existing same-day check

## Files Modified

1. `app/Language/en/Main.php` - Added English error message
2. `app/Language/ar/Main.php` - Added Arabic error message
3. `app/Controllers/Erp/Leave.php` - Modified overlap check logic in `add_leave()` method

## Testing

Run the manual test to verify:
```bash
php tests/manual_test_leave_overlap_check.php
```

All 5 test cases should pass.
