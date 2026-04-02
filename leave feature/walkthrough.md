# Company-Based Leave Policy Walkthrough

## Overview

This system enforces leave policies (Entitlement, Sick Leave Tiers, etc.) based on the **Company's Country Configuration**. This ensures that all employees under a company account follow the labor laws of that specific jurisdiction (e.g., Saudi Arabia, Egypt, Kuwait, Qatar).

## Changes Implemented

### 1. Database Updates

- **New Tables**:
  - `ci_leave_policy_countries`: Stores the rules (seeded for SA, EG, KW, QA).
  - `ci_employee_leave_balances`: For future balance tracking.
  - `ci_leave_policy_mapping`: Maps system types to user leave types.
- **New Settings Field**:
  - `ci_erp_company_settings` -> `leave_policy_country`: Stores the explicit country choice for the company.
- **Migration Script**: `leave_policy_migration.sql` in the root directory contains all schema changes and seed data.

### 2. Configuration Section

- **New Route**: `erp/leave-policy-config/`
- **Controller**: `LeavePolicyConfig`
- **Feature**: Allows Company Admins to select their "Policy Country" (e.g., switch from SA to EG). This setting overrides the company profile's default country if set.
- **Visibility**: Displays the active policy rules (Entitlement days, Service Years) for the selected country.

### 3. Logic Layer (`LeavePolicy` Library)

- **Policy Resolution Priority**:
  1. Check `leave_policy_country` in Company Settings.
  2. Fallback to Company Account's Profile Country.
  3. Default to 'SA' (Saudi Arabia) if neither is found.
- **Employee Edit**: Removed "Country Code" selector to avoid confusion. Policy depends strictly on the Company. Kept `is_disability` for Egypt policy support.

## How to Test

### 1. Database Setup

1. Run the provided SQL script: `source c:/wamp64/www/HR/leave_policy_migration.sql` (or import via PHPMyAdmin).

### 2. Configure Policy

1. Log in as a **Company Admin**.
2. Navigate to **Leave Policy Configuration** (URL: `.../erp/leave-policy-config/`).
3. Select a Country (e.g., **Egypt**) and Save.
4. Verify the table below updates to show Egypt's rules (e.g., "15 days for >0.5 years", "Disability 45 days").

### 3. Verify Entitlement (e.g. Egypt Annual Leave)

1. Go to **Employees**. Edit an employee -> ensure `Join Date` makes them eligible (e.g., 1 year service).
2. Go to **Leave > Apply Leave**.
3. Select "Annual Leave" (ensure your leave type constant is named "Annual...").
4. Try to apply for **22 days**.
   - _Expected_: Error "Cannot apply more than 21 days" (Standard EG policy for <10 years).
5. Edit Employee -> Set **Disability** to "Yes".
6. Retry applying for **40 days**.
   - _Expected_: Allowed (EG Disability Entitlement is 45 days).

### 4. Switch Country (e.g. Saudi Arabia)

1. Go back to Configuration -> Switch to **Saudi Arabia**.
2. Apply for Leave again.
   - _Expected_: Entitlement changes to 21 days (for <5 years service).

## Files Created/Modified

- `leave_policy_migration.sql`: Consolidated Database Script.
- `app/Libraries/LeavePolicy.php`: Core Logic.
- `app/Controllers/Erp/LeavePolicyConfig.php`: Settings Controller.
- `app/Views/erp/leave/policy_config.php`: Settings View.
- `app/Controllers/Erp/Leave.php`: Integration point.
- `app/Views/erp/employees/staff_details.php`: UI updates.
