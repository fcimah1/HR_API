# Manual Testing Guide: Sick & Maternity Leave Salary Cap

This guide will help you verify that sick and maternity leave deductions are properly capped at monthly salary to prevent negative net salary.

---

## Prerequisites

1. Access to the HR system with admin/manager privileges
2. A test employee with:
   - Basic salary configured (e.g., 30,000 SAR)
   - Assigned to a company with country policy (Saudi Arabia or Qatar recommended)
   - Active employment status

---

## Test Scenario 1: Saudi Arabia Sick Leave (100% Deduction Tier)

### Objective
Verify that when an employee takes sick leave in the 100% deduction tier (tier 3) for 31 days, the deduction is capped at monthly salary.

### Setup

**Saudi Arabia Sick Leave Policy:**
- Tier 1: First 30 days @ 100% paid (0% deduction)
- Tier 2: Next 60 days @ 75% paid (25% deduction)
- Tier 3: Next 30 days @ 0% paid (100% deduction)

### Steps

#### Step 1: Prepare Test Employee
1. Go to **Employees** → Select a test employee
2. Note down:
   - Employee ID: `_______`
   - Basic Salary: `_______ SAR`
   - Country: Should be **Saudi Arabia (SA)**

#### Step 2: Create Previous Sick Leaves (to reach Tier 3)
To test the 100% deduction tier, the employee needs to have already used 90 days of sick leave in the current year.

1. Go to **Leave** → **Add Leave**
2. Create **First Previous Leave**:
   - Employee: [Your test employee]
   - Leave Type: **Sick Leave**
   - From Date: `2026-01-01`
   - To Date: `2026-01-30` (30 days)
   - Status: **Approved**
   - Save

3. Create **Second Previous Leave**:
   - Employee: [Same test employee]
   - Leave Type: **Sick Leave**
   - From Date: `2026-02-01`
   - To Date: `2026-03-31` (60 days)
   - Status: **Approved**
   - Save

**Note:** The employee has now used 90 days (30 + 60), exhausting Tier 1 and Tier 2.

#### Step 3: Create Test Leave (31 days in Tier 3)
1. Go to **Leave** → **Add Leave**
2. Create the test leave:
   - Employee: [Same test employee]
   - Leave Type: **Sick Leave**
   - From Date: `2026-07-01`
   - To Date: `2026-07-31` (31 days - a 31-day month)
   - Status: **Approved**
   - Save

#### Step 4: Verify Deduction Created
1. Go to **Database** → Open `ci_payslip_statutory_deductions` table
2. Filter by:
   - `staff_id` = [Your employee ID]
   - `salary_month` = `2026-07`
   - `pay_title` LIKE `%Sick%`

3. Check the deduction record:
   ```
   Expected Results:
   - pay_amount should be ≤ Basic Salary
   - For 30,000 SAR salary:
     * Without cap: 31 days × 1,000 = 31,000 SAR
     * With cap: Should be 30,000 SAR (capped)
   - Net Salary = Basic Salary - pay_amount should be ≥ 0
   ```

#### Step 5: Verify in Payroll
1. Go to **Payroll** → **Generate Payslip**
2. Select:
   - Employee: [Your test employee]
   - Month: July 2026
3. Generate payslip
4. Check:
   - **Deductions section** should show "Sick Leave Deduction"
   - **Net Salary** should NOT be negative
   - Net Salary should be ≥ 0 SAR

### Expected Results ✓

| Item | Expected Value |
|------|----------------|
| Calculated Deduction (31 days) | 31,000 SAR |
| Actual Deduction (capped) | 30,000 SAR |
| Basic Salary | 30,000 SAR |
| Net Salary | 0 SAR (not negative) |

---

## Test Scenario 2: Qatar Sick Leave (50% Deduction Tier)

### Objective
Verify salary cap works for partial deduction scenarios.

### Setup

**Qatar Sick Leave Policy:**
- Tier 1: First 14 days @ 100% paid (0% deduction)
- Tier 2: Next 28 days @ 50% paid (50% deduction)
- Tier 3: Next 42 days @ 0% paid (100% deduction)

### Steps

#### Step 1: Prepare Test Employee
1. Select a test employee with:
   - Country: **Qatar (QA)**
   - Basic Salary: `_______ SAR`

#### Step 2: Create Previous Leave (to reach Tier 2)
1. Go to **Leave** → **Add Leave**
2. Create previous leave:
   - Employee: [Your test employee]
   - Leave Type: **Sick Leave**
   - From Date: `2026-01-01`
   - To Date: `2026-01-14` (14 days - exhausts Tier 1)
   - Status: **Approved**
   - Save

#### Step 3: Create Test Leave (28 days in Tier 2)
1. Go to **Leave** → **Add Leave**
2. Create test leave:
   - Employee: [Same test employee]
   - Leave Type: **Sick Leave**
   - From Date: `2026-07-01`
   - To Date: `2026-07-28` (28 days)
   - Status: **Approved**
   - Save

#### Step 4: Verify Deduction
1. Check `ci_payslip_statutory_deductions` table:
   - `staff_id` = [Your employee ID]
   - `salary_month` = `2026-07`

2. Expected calculation:
   ```
   For 30,000 SAR salary:
   - Daily rate: 30,000 / 30 = 1,000 SAR
   - 28 days @ 50% deduction: 28 × 1,000 × 0.5 = 14,000 SAR
   - This is less than 30,000 SAR, so no cap needed
   - Net Salary: 30,000 - 14,000 = 16,000 SAR ✓
   ```

---

## Test Scenario 3: Maternity Leave (If Applicable)

### Objective
Verify salary cap works for maternity leave deductions (if your country policy has deduction tiers).

### Note
Most countries (SA, QA, KW, EG) have maternity leave at 100% paid with no deductions. This test only applies if you have custom maternity policies with deduction tiers.

### Steps

If you have a maternity policy with deductions:
1. Follow similar steps as Sick Leave tests
2. Create maternity leave for 31 days in a deduction tier
3. Verify deduction is capped at monthly salary

---

## Quick Verification Checklist

Use this checklist for each test:

- [ ] Employee has correct country policy assigned
- [ ] Basic salary is configured for the employee
- [ ] Previous leaves created to reach the deduction tier
- [ ] Test leave created for 31 days (or full month)
- [ ] Deduction record exists in `ci_payslip_statutory_deductions`
- [ ] Deduction amount ≤ Basic Salary
- [ ] Net Salary ≥ 0 (not negative)
- [ ] Payslip displays correctly

---

## Database Verification Queries

### Check Deduction Records
```sql
SELECT 
    staff_id,
    salary_month,
    pay_title,
    pay_amount,
    created_at
FROM ci_payslip_statutory_deductions
WHERE staff_id = [EMPLOYEE_ID]
  AND salary_month = '2026-07'
  AND payslip_id = 0
ORDER BY created_at DESC;
```

### Check Leave Applications
```sql
SELECT 
    leave_id,
    employee_id,
    leave_type_id,
    from_date,
    to_date,
    calculated_days,
    status,
    country_code
FROM ci_leave_applications
WHERE employee_id = [EMPLOYEE_ID]
  AND from_date >= '2026-01-01'
ORDER BY from_date;
```

### Calculate Expected Deduction
```sql
SELECT 
    ud.basic_salary,
    (ud.basic_salary / 30) AS daily_rate,
    la.calculated_days,
    (la.calculated_days * (ud.basic_salary / 30)) AS calculated_deduction,
    LEAST((la.calculated_days * (ud.basic_salary / 30)), ud.basic_salary) AS capped_deduction
FROM ci_leave_applications la
JOIN ci_erp_users_details ud ON la.employee_id = ud.user_id
WHERE la.leave_id = [LEAVE_ID];
```

---

## Troubleshooting

### Issue: No deduction record created
**Possible causes:**
- Leave status is not "Approved" (status = 1)
- Employee has no basic salary configured
- Leave type is not mapped to sick/maternity system type

**Solution:**
1. Check leave status in database
2. Verify employee has `basic_salary` in `ci_erp_users_details`
3. Check leave type mapping in `ci_leave_policy_mapping`

### Issue: Deduction amount is 0
**Possible causes:**
- Leave is in a 100% paid tier (no deduction)
- Employee hasn't reached deduction tiers yet

**Solution:**
- Create previous leaves to exhaust paid tiers
- Verify tier configuration in `ci_leave_policy_countries`

### Issue: Deduction exceeds salary
**Possible causes:**
- The fix was not applied correctly
- Old deduction records from before the fix

**Solution:**
1. Verify the code changes in `app/Libraries/LeavePolicy.php`:
   - Line ~1165: `$monthlyDeductionTotal = min($monthlyDeductionTotal, $basicSalary);`
   - Line ~1260: `$monthlyDeductionTotal = min($monthlyDeductionTotal, $basicSalary);`
2. Delete old deduction records and recreate leave

---

## Success Criteria

The fix is working correctly if:

✓ Deductions never exceed monthly salary  
✓ Net salary is never negative  
✓ 31-day months are handled correctly  
✓ All leave types (sick, maternity, unpaid) follow same pattern  
✓ Payslips display correct amounts  

---

## Test Results Template

Use this template to document your test results:

```
Test Date: __________
Tester: __________

Test Scenario 1: SA Sick Leave (31 days @ 100% deduction)
- Employee ID: __________
- Basic Salary: __________ SAR
- Calculated Deduction: __________ SAR
- Actual Deduction: __________ SAR
- Net Salary: __________ SAR
- Result: [ ] PASS  [ ] FAIL
- Notes: _________________________________

Test Scenario 2: QA Sick Leave (28 days @ 50% deduction)
- Employee ID: __________
- Basic Salary: __________ SAR
- Calculated Deduction: __________ SAR
- Actual Deduction: __________ SAR
- Net Salary: __________ SAR
- Result: [ ] PASS  [ ] FAIL
- Notes: _________________________________

Overall Result: [ ] ALL TESTS PASSED  [ ] SOME TESTS FAILED
```

---

## Need Help?

If you encounter issues:
1. Check the log file: `writable/logs/log-[DATE].log`
2. Look for entries containing "Creating sick leave deductions" or "Creating maternity leave deductions"
3. Verify the deduction amount in the log matches the database
4. Contact the development team with:
   - Employee ID
   - Leave ID
   - Expected vs Actual deduction amounts
   - Screenshots of the issue
