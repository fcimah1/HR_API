# Country-Based Leave Policy - Testing Manual

## Prerequisites

### 1. Run SQL Migration

```sql
-- Run in phpMyAdmin or MySQL CLI
SOURCE c:/wamp64/www/HR/leave_policy_migration.sql;
```

### 2. Verify Tables Created

```sql
SELECT COUNT(*) FROM ci_leave_policy_countries;  -- Should be ~30+ records
SELECT COUNT(*) FROM ci_employee_onetime_leaves; -- Should be 0 initially
DESCRIBE ci_leave_applications; -- Should have country_code, policy_id columns
```

---

## Test Suite 1: Policy Configuration

### TC1.1 - Select Country and Save

| Step | Action                                               | Expected Result                    |
| ---- | ---------------------------------------------------- | ---------------------------------- |
| 1    | Login as Company Admin                               | Dashboard loads                    |
| 2    | Navigate to `erp/leave-policy-config`                | Policy configuration page displays |
| 3    | Select "المملكة العربية السعودية (SA)" from dropdown | Country selected                   |
| 4    | Click Save                                           | Success message appears            |
| 5    | Check `ci_erp_company_settings` table                | `leave_policy_country = 'SA'`      |

### TC1.2 - Auto-Create Leave Types

| Step | Action                         | Expected Result                      |
| ---- | ------------------------------ | ------------------------------------ |
| 1    | After saving SA policy         | Leave types auto-created             |
| 2    | Check `ci_erp_constants` table | 5 new records with type='leave_type' |
| 3    | Navigate to `erp/leave-type`   | New leave types visible              |

**Verify these leave types exist:**

- الإجازة السنوية (Annual)
- الإجازة المرضية (Sick)
- إجازة الأمومة (Maternity)
- إجازة الحج (Hajj)
- إجازة الوفاة والطوارئ (Emergency)

### TC1.3 - Verify Policy Mappings

```sql
SELECT * FROM ci_leave_policy_mapping WHERE company_id = {your_company_id};
```

**Expected:** 5 mappings linking `leave_type_id` to `system_leave_type`

### TC1.4 - Switch Country

| Step | Action                        | Expected Result      |
| ---- | ----------------------------- | -------------------- |
| 1    | Change country to Egypt (EG)  | Country changed      |
| 2    | Click Save                    | Success message      |
| 3    | Verify policies display       | Egypt policies shown |
| 4    | Existing leave types retained | No duplicate types   |

---

## Test Suite 2: Saudi Arabia (SA) - Annual Leave

### TC2.1 - Employee < 1 Year Service

| Prerequisite               | Value        |
| -------------------------- | ------------ |
| Employee `date_of_joining` | 6 months ago |

| Step | Action                         | Expected Result                              |
| ---- | ------------------------------ | -------------------------------------------- |
| 1    | Employee requests annual leave | Error: "Not entitled - insufficient service" |
| 2    | Check entitlement calculation  | Returns 0 days                               |

### TC2.2 - Employee 1-5 Years Service

| Prerequisite               | Value       |
| -------------------------- | ----------- |
| Employee `date_of_joining` | 3 years ago |

| Step | Action                                 | Expected Result            |
| ---- | -------------------------------------- | -------------------------- |
| 1    | Employee requests 21 days annual leave | Request accepted           |
| 2    | Employee requests 22 days              | Error: Exceeds entitlement |

### TC2.3 - Employee 5+ Years Service

| Prerequisite               | Value       |
| -------------------------- | ----------- |
| Employee `date_of_joining` | 7 years ago |

| Step | Action                                          | Expected Result  |
| ---- | ----------------------------------------------- | ---------------- |
| 1    | Employee requests 30 days annual leave          | Request accepted |
| 2    | Check `payment_percentage` in leave application | 100              |

---

## Test Suite 3: Egypt (EG) - Annual Leave Edge Cases

### TC3.1 - Employee < 6 Months

| Prerequisite               | Value        |
| -------------------------- | ------------ |
| Employee `date_of_joining` | 3 months ago |
| Country                    | EG           |

| Step | Action               | Expected Result     |
| ---- | -------------------- | ------------------- |
| 1    | Request annual leave | Error: Not entitled |

### TC3.2 - Employee 6 Months to 1 Year

| Prerequisite               | Value        |
| -------------------------- | ------------ |
| Employee `date_of_joining` | 8 months ago |

| Step | Action            | Expected Result |
| ---- | ----------------- | --------------- |
| 1    | Check entitlement | 15 days         |

### TC3.3 - Employee 10+ Years with Disability

> [!IMPORTANT]
> This tests the Egypt disability rule (45 days instead of 30)

| Prerequisite                         | Value        |
| ------------------------------------ | ------------ |
| Employee `date_of_joining`           | 12 years ago |
| `ci_erp_users_details.is_disability` | 1            |

| Step | Action                         | Expected Result      |
| ---- | ------------------------------ | -------------------- |
| 1    | Check annual leave entitlement | **45 days** (not 30) |
| 2    | Request 45 days                | Request accepted     |

### TC3.4 - Employee 10+ Years without Disability

| Prerequisite    | Value |
| --------------- | ----- |
| `is_disability` | 0     |

| Step | Action            | Expected Result |
| ---- | ----------------- | --------------- |
| 1    | Check entitlement | 30 days         |

---

## Test Suite 4: One-Time Leave (Hajj)

### TC4.1 - Hajj First Request (SA)

| Prerequisite     | Value   |
| ---------------- | ------- |
| Employee service | 3 years |
| Country          | SA      |

| Step | Action                       | Expected Result                             |
| ---- | ---------------------------- | ------------------------------------------- |
| 1    | Request Hajj leave (15 days) | Request accepted                            |
| 2    | Approve the request          | `ci_employee_onetime_leaves` record created |
| 3    | Check `taken_date`           | Today's date                                |

### TC4.2 - Hajj Second Request (Should Fail)

| Prerequisite           | Value |
| ---------------------- | ----- |
| Same employee as TC4.1 | -     |
| First Hajj approved    | Yes   |

| Step | Action                   | Expected Result                                     |
| ---- | ------------------------ | --------------------------------------------------- |
| 1    | Request Hajj leave again | **Error: "This leave type can only be taken once"** |

### TC4.3 - Hajj Before Service Requirement

| Prerequisite     | Value                |
| ---------------- | -------------------- |
| Employee service | 1 year (less than 2) |
| Country          | SA                   |

| Step | Action             | Expected Result                             |
| ---- | ------------------ | ------------------------------------------- |
| 1    | Request Hajj leave | **Error: Minimum 2 years service required** |

### TC4.4 - Egypt Hajj (Requires 5 Years)

| Prerequisite     | Value   |
| ---------------- | ------- |
| Employee service | 4 years |
| Country          | EG      |

| Step | Action             | Expected Result        |
| ---- | ------------------ | ---------------------- |
| 1    | Request Hajj leave | Error: Minimum 5 years |

| Prerequisite     | Value   |
| ---------------- | ------- |
| Employee service | 6 years |

| Step | Action             | Expected Result            |
| ---- | ------------------ | -------------------------- |
| 1    | Request Hajj leave | Request accepted (30 days) |

---

## Test Suite 5: Tiered Sick Leave

### TC5.1 - Saudi Arabia Tiered Sick Leave

| Prerequisite | Value |
| ------------ | ----- |
| Country      | SA    |
| Employee     | Any   |

**Test Tier 1 (Days 1-30, 100%):**
| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Request 20 days sick leave | `payment_percentage = 100` |
| 2 | Request additional 10 days | `payment_percentage = 100` |

**Test Tier 2 (Days 31-90, 75%):**
| Step | Action | Expected Result |
|------|--------|-----------------|
| 3 | Already used 30 days | - |
| 4 | Request 15 more days | `payment_percentage = 75` |

**Test Tier 3 (Days 91-120, 0%):**
| Step | Action | Expected Result |
|------|--------|-----------------|
| 5 | Already used 90 days | - |
| 6 | Request 10 more days | `payment_percentage = 0` |

### TC5.2 - Qatar Tiered Sick Leave

| Tier | Days  | Payment % |
| ---- | ----- | --------- |
| 1    | 1-14  | 100%      |
| 2    | 15-42 | 50%       |
| 3    | 43-84 | 0%        |

| Step | Action                     | Expected Result    |
| ---- | -------------------------- | ------------------ |
| 1    | Request 14 days            | 100% payment       |
| 2    | Request 28 more (total 42) | 50% for these days |
| 3    | Request 42 more (total 84) | 0% for these days  |

---

## Test Suite 6: Kuwait Special Cases

### TC6.1 - Annual Leave Before 9 Months

| Prerequisite     | Value    |
| ---------------- | -------- |
| Employee service | 7 months |
| Country          | KW       |

| Step | Action               | Expected Result     |
| ---- | -------------------- | ------------------- |
| 1    | Request annual leave | Error: Not entitled |

### TC6.2 - Female Employee Husband Death

| Prerequisite    | Value                     |
| --------------- | ------------------------- |
| Employee gender | Female                    |
| Leave type      | Emergency (husband death) |

| Step | Action                           | Expected Result             |
| ---- | -------------------------------- | --------------------------- |
| 1    | Request 130 days emergency leave | **Accepted** (Iddah period) |
| 2    | Check `payment_percentage`       | 100%                        |

---

## Test Suite 7: Qatar - No Hajj/Emergency

### TC7.1 - Verify No Hajj Policy

| Prerequisite | Value |
| ------------ | ----- |
| Country      | QA    |

| Step | Action                          | Expected Result            |
| ---- | ------------------------------- | -------------------------- |
| 1    | Check if Hajj leave type exists | Should NOT exist for Qatar |
| 2    | Try to request Hajj             | Leave type not available   |

---

## Test Suite 8: Policy Tracking in Leave Applications

### TC8.1 - Verify Policy Data Stored

| Step | Action                              | Expected Result          |
| ---- | ----------------------------------- | ------------------------ |
| 1    | Create any leave request            | Request saved            |
| 2    | Check `ci_leave_applications` table | Verify columns populated |

```sql
SELECT
    leave_id,
    country_code,
    service_years,
    policy_id,
    payment_percentage
FROM ci_leave_applications
WHERE leave_id = {new_leave_id};
```

**Expected values:**

- `country_code`: 'SA', 'EG', 'KW', or 'QA'
- `service_years`: Decimal (e.g., 3.5)
- `policy_id`: Integer referencing `ci_leave_policy_countries`
- `payment_percentage`: 0, 50, 75, or 100

---

## Test Suite 9: Language Display

### TC9.1 - Arabic Language

| Step | Action                                | Expected Result        |
| ---- | ------------------------------------- | ---------------------- |
| 1    | Set system language to Arabic         | -                      |
| 2    | Navigate to `erp/leave-policy-config` | -                      |
| 3    | Verify country names                  | Arabic names displayed |
| 4    | Verify policy descriptions            | Arabic descriptions    |

### TC9.2 - English Language

| Step | Action                         | Expected Result |
| ---- | ------------------------------ | --------------- |
| 1    | Set system language to English | -               |
| 2    | Navigate to policy config      | English content |

---

## Test Suite 10: Edge Cases

### TC10.1 - Company Without Policy

| Prerequisite           | Value |
| ---------------------- | ----- |
| `leave_policy_country` | NULL  |

| Step | Action                    | Expected Result               |
| ---- | ------------------------- | ----------------------------- |
| 1    | Employee requests leave   | Uses traditional quota system |
| 2    | Policy validation skipped | No policy error               |

### TC10.2 - Unmapped Leave Type

| Prerequisite      | Value          |
| ----------------- | -------------- |
| Custom leave type | Not in mapping |

| Step | Action                    | Expected Result           |
| ---- | ------------------------- | ------------------------- |
| 1    | Request custom leave      | Policy validation skipped |
| 2    | Traditional balance check | Works normally            |

### TC10.3 - Mid-Year Policy Change

| Step | Action                                 | Expected Result             |
| ---- | -------------------------------------- | --------------------------- |
| 1    | Employee has 10 days used (old policy) | -                           |
| 2    | Admin changes country                  | -                           |
| 3    | Employee requests new leave            | Uses new policy entitlement |
| 4    | Existing balances retained             | No double counting          |

### TC10.4 - Leap Year for Service Calculation

| Prerequisite      | Value                    |
| ----------------- | ------------------------ |
| `date_of_joining` | Feb 29, 2024 (leap year) |

| Step | Action                   | Expected Result |
| ---- | ------------------------ | --------------- |
| 1    | Calculate service years  | No PHP error    |
| 2    | Correct years calculated | Decimal value   |

---

## Database Verification Queries

### Check Policy Assignment

```sql
SELECT
    cs.company_id,
    cs.leave_policy_country,
    COUNT(lpm.mapping_id) as mappings
FROM ci_erp_company_settings cs
LEFT JOIN ci_leave_policy_mapping lpm ON cs.company_id = lpm.company_id
GROUP BY cs.company_id;
```

### Check One-Time Leave Usage

```sql
SELECT
    e.employee_id,
    u.first_name,
    u.last_name,
    e.leave_type,
    e.taken_date,
    la.leave_id
FROM ci_employee_onetime_leaves e
JOIN ci_erp_users u ON e.employee_id = u.user_id
LEFT JOIN ci_leave_applications la ON e.leave_application_id = la.leave_id;
```

### Check Employee Balances

```sql
SELECT
    b.*,
    u.first_name,
    u.last_name
FROM ci_employee_leave_balances b
JOIN ci_erp_users u ON b.employee_id = u.user_id
WHERE b.year = 2026;
```

---

## Troubleshooting

| Issue                   | Check                                            |
| ----------------------- | ------------------------------------------------ |
| Leave types not created | Verify `ci_leave_policy_mapping` table           |
| Policy not validating   | Check `leave_policy_country` in company settings |
| Entitlement wrong       | Verify `date_of_joining` format                  |
| Hajj allowed twice      | Check `ci_employee_onetime_leaves` table         |
| Payment % always 100    | Check tiered policy `tier_order`                 |
