# Hierarchical Access Control System

## Quick Reference

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                         PERMISSION MATRIX                                    │
├─────────────────┬────────┬─────────┬───────────┬─────────┬─────────────────┤
│ Action          │  Self  │ Company │ Same Lvl  │ Higher  │ Higher+Restrict │
├─────────────────┼────────┼─────────┼───────────┼─────────┼─────────────────┤
│ View List       │   ✓    │    ✓    │     ✓     │    ✓    │        ✗        │
│ View Details    │   ✓    │    ✓    │     ✓     │    ✓    │        ✗        │
│ Create for      │   ✓    │    ✓    │     ✓     │    ✓    │        ✗        │
│ Update Own      │   ✓    │    ✓    │     ✗     │    ✓    │        ✗        │
│ Update Other's  │   -    │    ✓    │     ✗     │    ✓    │        ✗        │
│ Approve         │   ✗    │    ✓    │     ✗     │    ✓    │        ✗        │
│ Reject          │   ✗    │    ✓    │     ✗     │    ✓    │        ✗        │
└─────────────────┴────────┴─────────┴───────────┴─────────┴─────────────────┘

Legend: ✓ = Allowed  |  ✗ = Blocked  |  - = N/A
```

---

## Hierarchy Levels

```
┌───────┬──────────────┬─────────────────────┐
│ Level │    Rank      │       Example       │
├───────┼──────────────┼─────────────────────┤
│   0   │ System Admin │ Company Account     │
│   1   │ Highest      │ CEO, Director       │
│   2   │ High         │ Department Manager  │
│   3   │ Mid          │ Supervisor          │
│   4   │ Low          │ Senior Staff        │
│   5   │ Lowest       │ Junior Staff        │
└───────┴──────────────┴─────────────────────┘

Rule: Lower number = Higher authority
```

---

## Core Functions

### `canViewEmployeeRequests(User $manager, User $employee): bool`

**Purpose:** Check if user can **VIEW** requests (lists, details)

**Allows:** Same level OR higher level

```
┌──────────────────────────────────────┬─────────┐
│ Condition                            │ Result  │
├──────────────────────────────────────┼─────────┤
│ Company owner                        │   ✓     │
│ Different company                    │   ✗     │
│ Higher level (lower number)          │   ✓     │
│ Same level                           │   ✓     │
│ Lower level (higher number)          │   ✗     │
│ Higher level + Dept/Branch restrict  │   ✗     │
└──────────────────────────────────────┴─────────┘
```

**Logic:**
```php
// Lower number = Higher rank
if ($managerLevel > $employeeLevel) {
    return false; // Manager has lower authority
}
// + Department/Branch restriction checks
```

---

### `canApproveEmployeeRequests(User $approver, User $employee): bool`

**Purpose:** Check if user can **APPROVE/REJECT/UPDATE** requests

**Requires:** STRICTLY higher level (same level NOT allowed)

```
┌──────────────────────────────────────┬─────────┐
│ Condition                            │ Result  │
├──────────────────────────────────────┼─────────┤
│ Self (own request)                   │   ✗     │
│ Company owner                        │   ✓     │
│ Different company                    │   ✗     │
│ Higher level (lower number)          │   ✓     │
│ Same level                           │   ✗     │  ← KEY DIFFERENCE
│ Lower level (higher number)          │   ✗     │
│ Higher level + Dept/Branch restrict  │   ✗     │
└──────────────────────────────────────┴─────────┘
```

**Logic:**
```php
// Cannot approve own request
if ($approver->user_id === $employee->user_id) {
    return false;
}
// Strict: must be HIGHER level
if ($approverLevel >= $employeeLevel) {
    return false; // Same or lower = blocked
}
// + Department/Branch restriction checks
```

---

## Method Usage

```
┌──────────────────────────────────────────────────────────────────────────────┐
│                    APPROVE/REJECT METHODS                                    │
│                   (use canApproveEmployeeRequests)                           │
├──────────────────────────┬───────────────────────────────────────────────────┤
│ LeaveService             │ approveApplication, rejectApplication             │
│ HourlyLeaveService       │ approveOrRejectHourlyLeave                        │
│ LeaveAdjustmentService   │ approveAdjustment, rejectAdjustment               │
│ TravelService            │ approveTravel, rejectTravel                       │
│ TransferService          │ approveOrRejectTransfer                           │
│ ResignationService       │ approveOrRejectResignation                        │
│ OvertimeService          │ approveRequest, rejectRequest                     │
│ ComplaintService         │ resolveOrRejectComplaint                          │
│ AdvanceSalaryService     │ approveAdvance, rejectAdvance                     │
└──────────────────────────┴───────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────────────────────┐
│                    UPDATE METHODS                                            │
│  (use canApproveEmployeeRequests - strict) + can update it's own request     │
├──────────────────────────┬───────────────────────────────────────────────────┤
│ LeaveService             │ update_Application                                │
│ HourlyLeaveService       │ updateHourlyLeave                                 │
│ LeaveAdjustmentService   │ updateAdjustment                                  │
│ TravelService            │ updateTravel                                      │
│ TransferService          │ updateTransfer                                    │
│ ResignationService       │ updateResignation                                 │
│ OvertimeService          │ updateRequest                                     │
│ ComplaintService         │ updateComplaint                                   │
│ AdvanceSalaryService     │ updateAdvance                                     │
└──────────────────────────┴───────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────────────────────┐
│                    VIEW/LIST METHODS                                         │
│                   (use canViewEmployeeRequests)                              │
├──────────────────────────┬───────────────────────────────────────────────────┤
│ All Services             │ getPaginated* methods + request details methods   │
└──────────────────────────┴───────────────────────────────────────────────────┘
```

---

## Examples

**Example 1: Same Level**
```
Ahmed (Level 4) tries to approve Mohamed's (Level 4) request
→ Level 4 >= Level 4 → BLOCKED ✗
```

**Example 2: Higher Level**
```
Khaled (Level 2) tries to approve Mohamed's (Level 4) request
→ Level 2 < Level 4 → ALLOWED ✓
```

**Example 3: Self Approval**
```
Mohamed (Level 4) tries to approve his own request
→ Same user ID → BLOCKED ✗
```

**Example 4: Higher Level + Restriction**
```
Khaled (Level 2) tries to approve Mohamed's (Level 4) request
→ Level 2 < Level 4 → ALLOWED ✓
→ Department Restriction → BLOCKED ✗
```

**Example 5: Lower Level**
```
Mohamed (Level 4) tries to approve Khaled's (Level 2) request
→ Level 4 > Level 2 → BLOCKED ✗
```

---

## Operation Restrictions (القيود)

Operation restrictions allow companies to limit what types of operations specific users can perform or access. These are stored in the `ci_operation_restriction` table.

### Restriction Types

```
┌─────────────────┬────────────────────────────────────────────────────────────┐
│ Prefix          │ Description                                                │
├─────────────────┼────────────────────────────────────────────────────────────┤
│ dept_X          │ Department restriction (X = department_id)                 │
│ branch_X        │ Branch restriction (X = branch_id)                         │
│ leave_type_X    │ Leave type restriction (X = leave_type_id)                 │
│ travel_type_X   │ Travel type restriction (X = arrangement_type)             │
└─────────────────┴────────────────────────────────────────────────────────────┘
```

### How Restrictions Work

**Department/Branch Restrictions** - Applied in `canViewEmployeeRequests` and `canApproveEmployeeRequests`:
- If user has `dept_5` restriction → Cannot view/approve requests from Department 5 employees
- If user has `branch_3` restriction → Cannot view/approve requests from Branch 3 employees

**Leave Type Restrictions** - Applied in Create/Update operations:
- If employee has `leave_type_199` restriction → Cannot request Leave Type 199
- Manager can override if they have higher level (via `canOverrideRestriction`)

**Travel Type Restrictions** - Applied in Create/Update operations:
- If employee has `travel_type_2` restriction → Cannot request Travel Type 2

### Database Structure

```sql
-- Table: ci_operation_restriction
┌──────────────────┬─────────────────────────────────────────────────┐
│ Column           │ Description                                     │
├──────────────────┼─────────────────────────────────────────────────┤
│ id               │ Primary key                                     │
│ company_id       │ Company ID                                      │
│ user_id          │ User ID (the restricted user)                   │
│ restricted_operations │ Comma-separated list: "dept_5,leave_type_199" │
└──────────────────┴─────────────────────────────────────────────────┘
```

### Restriction Check Flow

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│ 1. User tries to view/approve request                                           │
│                              ↓                                                  │
│ 2. Check hierarchy level (canViewEmployeeRequests / canApproveEmployeeRequests) │
│                              ↓                                                  │
│ 3. Fetch user's OperationRestriction from database                              │
│                              ↓                                                  │
│ 4. Parse restricted_operations (e.g., "dept_5,branch_3")                        │
│                              ↓                                                  │
│ 5. Check if target employee's dept/branch matches any restriction               │
│    - If dept_X matches employee's department → BLOCKED                          │
│    - If branch_X matches employee's branch → BLOCKED                            │
│                              ↓                                                  │
│ 6. If no restrictions match → ALLOWED                                           │
└─────────────────────────────────────────────────────────────────────────────────┘
```

### Examples

**Example 6: Department Restriction**
```
Khaled (Level 2) has restriction: "dept_5"
Mohamed (Level 4) belongs to Department 5

Khaled tries to approve Mohamed's request:
→ Level 2 < Level 4 → Hierarchy OK ✓
→ Mohamed's dept (5) matches restriction (dept_5) → BLOCKED ✗
```

**Example 7: Leave Type Restriction**
```
Mohamed has restriction: "leave_type_199"

Mohamed tries to create a Leave Type 199 request for himself:
→ Leave Type 199 is in his restricted list → BLOCKED ✗

Khaled (Level 2, no restrictions) creates Leave Type 199 for Mohamed:
→ Khaled can override (higher level + canOverrideRestriction) → ALLOWED ✓
```

**Example 8: Multiple Restrictions**
```
Khaled has restriction: "dept_5,branch_3,leave_type_199"

This means Khaled CANNOT:
- View/approve requests from Department 5 employees
- View/approve requests from Branch 3 employees
- Is NOT restricted on leave types (those apply to employee, not approver)
```

### Override Logic (`canOverrideRestriction`)

Higher-level managers can override type restrictions when creating requests for subordinates:

```php
// If requester != target employee AND requester has higher level
// → Requester can override target's leave_type/travel_type restrictions
if ($this->permissionService->canOverrideRestriction($requester, $targetEmployee, 'leave_type_', $leaveTypeId)) {
    // Allow even if target has this leave type restricted
}
```

---

## Travel Policy (سياسة السفر)

Travel policies define allowances based on employee hierarchy level. When a travel request is approved, the system calculates the allowance automatically.

### How Travel Policy Works

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│ 1. Travel request is APPROVED                                                   │
│                              ↓                                                  │
│ 2. Get employee's hierarchy_level from designation                              │
│                              ↓                                                  │
│ 3. Query PolicyResult table:                                                    │
│    - policy_id = 1 (Travel)                                                     │
│    - hierarchy_level = employee's level                                         │
│    - company_id = employee's company                                            │
│                              ↓                                                  │
│ 4. If PolicyResult found:                                                       │
│    - total_amount = Travel allowance amount                                     │
│    - currency_local = Currency (e.g., SAR)                                      │
│                              ↓                                                  │
│ 5. Include in approval notification/email                                       │
└─────────────────────────────────────────────────────────────────────────────────┘
```

### Database Structure

```sql
-- Table: ci_policy_result
┌──────────────────┬──────────────────────────────────────────────────┐
│ Column           │ Description                                      │
├──────────────────┼──────────────────────────────────────────────────┤
│ id               │ Primary key                                      │
│ policy_id        │ Policy type (1 = Travel)                         │
│ company_id       │ Company ID                                       │
│ hierarchy_level  │ Target hierarchy level (1-5)                     │
│ total_amount     │ Allowance amount                                 │
│ currency_local   │ Currency code (SAR, USD, etc.)                   │
└──────────────────┴──────────────────────────────────────────────────┘
```

### Policy by Hierarchy Level

```
┌───────┬──────────────────┬─────────────────┬─────────────────────────────┐
│ Level │ Position         │ Daily Allowance │ Notes                       │
├───────┼──────────────────┼─────────────────┼─────────────────────────────┤
│   1   │ CEO/Director     │ Higher rate     │ Business class, 5-star hotel│
│   2   │ Dept Manager     │ High rate       │ Business class allowed      │
│   3   │ Supervisor       │ Mid rate        │ Economy premium             │
│   4   │ Senior Staff     │ Standard rate   │ Economy class               │
│   5   │ Junior Staff     │ Basic rate      │ Economy class               │
└───────┴──────────────────┴─────────────────┴─────────────────────────────┘

Note: Actual amounts are configured per company in PolicyResult table
```

### Code Implementation

```php
// In TravelService::approveTravel()
$employee = User::find($travel->employee_id);
$employeeHierarchyLevel = $this->permissionService->getUserHierarchyLevel($employee);

// Get travel allowance based on hierarchy level
$policyResult = PolicyResult::where('policy_id', 1) // 1 = Travel
    ->where('hierarchy_level', $employeeHierarchyLevel)
    ->where('company_id', $effectiveCompanyId)
    ->first();

if ($policyResult) {
    $allowanceAmount = $policyResult->total_amount;
    $currency = $policyResult->currency_local;
}
```

### Example

```
Mohamed (Level 4) submits a travel request
→ Request approved by Khaled (Level 2)
→ System queries PolicyResult(policy_id=1, hierarchy_level=4, company_id=24)
→ Result: total_amount=500, currency_local=SAR
→ Approval notification includes: "Travel Allowance: 500 SAR"
→ Approval email includes: "Travel Allowance: 500 SAR"
```

---

## Multi-Level Approval System (نظام مراحل الاعتماد)

The system uses **TWO approval mechanisms** that work together. A user must pass BOTH to approve a request.

### Dual Approval Architecture

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                         APPROVAL FLOW                                           │
├─────────────────────────────────────────────────────────────────────────────────┤
│ 1. Hierarchy Check (canApproveEmployeeRequests)                                 │
│    - Is approver at a higher level than request owner?                          │
│    - No department/branch restrictions?                                         │
│    ↓ If PASSED                                                                  │
│ 2. Approval Levels Check (ApprovalService::canUserApprove)                      │
│    - Is approver assigned in employee's approval_level01/02/03?                 │
│    - Has the request reached approver's stage?                                  │
│    ↓ If PASSED                                                                  │
│ 3. Approval Granted ✅                                                          │
└─────────────────────────────────────────────────────────────────────────────────┘
```

### Comparison of Two Systems

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│ System 1: Hierarchy-Based (canApproveEmployeeRequests)                          │
├─────────────────────────────────────────────────────────────────────────────────┤
│ • Based on hierarchy_level in ci_designations                                   │
│ • Level 2 can approve Level 4 (lower number = higher authority)                 │
│ • General check for all employees in company                                    │
│ • Respects department/branch restrictions                                       │
└─────────────────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────────────┐
│ System 2: Staff-Specific Approval Levels (ci_staff_details)                     │
├─────────────────────────────────────────────────────────────────────────────────┤
│ • Based on approval_level01, approval_level02, approval_level03 columns         │
│ • Each employee has specific approvers assigned                                 │
│ • Multi-stage workflow: Stage 1 → Stage 2 → Stage 3                             │
│ • Example: Mohamed → Stage 1: Khaled → Stage 2: Ahmed → Final                   │
└─────────────────────────────────────────────────────────────────────────────────┘
```

### Database Structure (ci_staff_details)

```
┌───────────────────┬─────────────────────────────────────────────────────────────┐
│ Column            │ Description                                                 │
├───────────────────┼─────────────────────────────────────────────────────────────┤
│ user_id           │ Employee's user ID                                          │
│ approval_level01  │ Serialized array: First level approver(s)                   │
│ approval_level02  │ Serialized array: Second level approver(s)                  │
│ approval_level03  │ Serialized array: Third level approver(s)                   │
│ approval_levels   │ Serialized array: Module-specific approval settings         │
└───────────────────┴─────────────────────────────────────────────────────────────┘

Format: a:3:{s:6:"level1";s:1:"3";s:6:"level2";s:0:"";s:6:"level3";s:0:"";}
        (PHP serialized array)
```

### How ApprovalService Works

```php
// In OvertimeService::approveRequest() - after hierarchy check passes
$canApprove = $this->approvalService->canUserApprove(
    $approver->user_id,           // Who is trying to approve
    $request->time_request_id,    // Request ID
    $request->staff_id,           // Employee who made the request
    'overtime_request_settings'   // Module setting key
);

if (!$canApprove) {
    throw new \Exception('ليس لديك صلاحية للموافقة على هذا الطلب في المرحلة الحالية');
}
```

### Scenario Examples

**Scenario 1: Both Checks Pass ✅**
```
Khaled (Level 2) tries to approve Mohamed's (Level 4) overtime request
→ Hierarchy Check: Level 2 < Level 4 → PASSED ✅
→ Approval Levels: Khaled is in Mohamed's approval_level01 → PASSED ✅
→ Result: APPROVED ✅
```

**Scenario 2: Hierarchy Passes, Approval Levels Fail ❌**
```
Ahmed (Level 2) tries to approve Mohamed's (Level 4) overtime request
→ Hierarchy Check: Level 2 < Level 4 → PASSED ✅
→ Approval Levels: Ahmed is NOT in Mohamed's approval_level01/02/03 → FAILED ❌
→ Result: "ليس لديك صلاحية للموافقة على هذا الطلب في المرحلة الحالية"
```

**Scenario 3: Hierarchy Fails ❌**
```
Ali (Level 4) tries to approve Mohamed's (Level 4) overtime request
→ Hierarchy Check: Level 4 >= Level 4 → FAILED ❌
→ Result: "ليس لديك صلاحية للموافقة على طلب هذا الموظف"
```

### Modules Using Multi-Level Approval

```
┌─────────────────────────┬────────────────────────────────┐
│ Module                  │ Setting Key                    │
├─────────────────────────┼────────────────────────────────┤
│ Leave                   │ leave_application_settings     │
│ Hourly Leave            │ hourly_leave_settings          │
│ Leave Adjustment        │ leave_adjustment_settings      │
│ Travel                  │ travel_request_settings        │
│ Transfer                │ transfer_request_settings      │
│ Resignation             │ resignation_request_settings   │
│ Overtime                │ overtime_request_settings      │
│ Advance Salary          │ advance_salary_settings        │
└─────────────────────────┴────────────────────────────────┘
```

### Exception: Company Users

Company users (`user_type = 'company'`) bypass the Approval Levels check and can approve directly:

```php
if ($userType === 'company') {
    // Direct approval - no need to check approval_levels
    $approvedRequest = $this->repository->approveRequest($request, $approver->user_id);
}
```
