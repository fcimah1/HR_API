# Implementation Plan - Company-Based Leave Policy

## Goal

Refactor the Leave Policy system to apply rules based on the **Company's Country** (not the employee's). Provide a centralized configuration section for policies and a consolidated SQL script for database updates.

## Proposed Changes

### 1. Database

- **Consolidated SQL Script**: Create `leave_policy_migration.sql` containing:
  - Table creation (`ci_leave_policy_countries`, `ci_employee_leave_balances`, `ci_leave_policy_mapping`).
  - Column additions (`tier_order` in `ci_leave_policy_countries`, `is_disability` in `ci_erp_users_details`).
  - Seed data for SA, EG, KW, QA policies.

### 2. Backend Logic (`App\Libraries\LeavePolicy`)

- **Country Determination**:
  - Remove dependency on Employee `country_code`.
  - Fetch **Company User** (using `company_id` from employee).
  - Look up `country` ID in `ci_countries` to get the ISO code (e.g., 'SA', 'EG').
- **Entitlement Calculation**:
  - Use the Company's country code to query `ci_leave_policy_countries`.

### 3. Configuration Section

- **New Controller**: `App\Controllers\Erp\LeavePolicyConfig.php`
  - `index()`: Display list of policies applicable to the current company's country.
  - `mapping()`: Interface to map System Leave Types (Annual, Sick) to Company Leave Types.
- **New View**: `app/Views/erp/leave/policy_config.php`
- **Routes**: Add routes in `app/Config/Routes.php` under `// Leave Policy Configuration`.

### 4. Employees Module

- **Disability Field**: Keep `is_disability` for Egypt policy support, but ensure it's hidden/shown or just available. (User said "policy applied according to company account", but disability is an _employee_ attribute for the Egypt policy exception. I will keep it.)
- **Country Field**: Remove "Country Code" from Employee Edit if it's strictly Company-based (to avoid confusion), or leave it as purely informational. I will hide/remove the manual override to strictly follow "not employees".

## Verification Plan

### Automated/Manual Verification

1.  **Database**: Run the SQL script on a fresh (or existing) DB and verify tables/seeds exist.
2.  **Configuration**:
    - Go to `erp/leave-policy-config` (new route).
    - Verify it shows the policy for the Company's country (e.g., if Company is SA, show SA rules).
3.  **Leave Application**:
    - Apply for leave as an employee.
    - Verify entitlement is calculated based on Company's country.
    - Test "Disability" exception for Egypt (if Company is EG).
