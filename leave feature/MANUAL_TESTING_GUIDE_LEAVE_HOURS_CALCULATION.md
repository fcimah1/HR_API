# Manual Testing Guide: Fix Leave Hours Calculation

## Overview
This guide provides comprehensive manual testing procedures for the leave hours calculation fix. The feature ensures accurate calculation of leave hours based on employee shift configurations, working days, company holidays, and break times.

---

## Table of Contents
1. [Pre-Testing Setup](#pre-testing-setup)
2. [Test Scenarios](#test-scenarios)
3. [Edge Cases](#edge-cases)
4. [Validation Checklist](#validation-checklist)
5. [Troubleshooting](#troubleshooting)

---

## Pre-Testing Setup

### Required Test Data

#### 1. Create Test Shifts
You need at least 3 different shift configurations:

**Shift A: Standard 8-Hour Shift (Mon-Fri)**
- Hours per day: 8
- Monday-Friday: 08:00 - 17:00 (Lunch: 12:00-13:00)
- Saturday-Sunday: Empty (non-working days)

**Shift B: Extended 10-Hour Shift (Sun-Thu)**
- Hours per day: 10
- Sunday-Thursday: 07:00 - 18:00 (Lunch: 12:00-13:00)
- Friday-Saturday: Empty (non-working days)

**Shift C: 6-Day Work Week (Mon-Sat)**
- Hours per day: 8
- Monday-Saturday: 09:00 - 18:00 (Lunch: 13:00-14:00)
- Sunday: Empty (non-working day)

#### 2. Create Test Employees
Create at least 5 test employees:

**Employee 1: Standard Employee**
- Name: Test Employee 1
- Shift: Shift A (8 hours, Mon-Fri)
- Company: Company A

**Employee 2: Extended Hours Employee**
- Name: Test Employee 2
- Shift: Shift B (10 hours, Sun-Thu)
- Company: Company A

**Employee 3: Six-Day Employee**
- Name: Test Employee 3
- Shift: Shift C (8 hours, Mon-Sat)
- Company: Company A

**Employee 4: No Shift Employee**
- Name: Test Employee 4
- Shift: NONE (no shift assigned)
- Company: Company A

**Employee 5: Different Company Employee**
- Name: Test Employee 5
- Shift: Shift A (8 hours, Mon-Fri)
- Company: Company B

#### 3. Create Test Holidays
Add company holidays to test exclusion logic:

**Company A Holidays:**
- Holiday 1: Single day (e.g., 2024-03-15) - "National Day"
- Holiday 2: Multi-day (e.g., 2024-04-10 to 2024-04-12) - "Spring Break"
- Holiday 3: Falls on weekend (e.g., 2024-05-18 - Saturday) - "Weekend Holiday"

**Company B Holidays:**
- Holiday 4: Different date (e.g., 2024-03-20) - "Company B Holiday"

---

## Test Scenarios

### Scenario 1: Full Day Leave - Basic Calculation

**Objective:** Verify basic working days and hours calculation

**Test Steps:**
1. Login as Employee 1 (8-hour shift, Mon-Fri)
2. Navigate to Leave Request page
3. Select "Leave in Days" request type
4. Enter date range: Monday to Friday (5 working days, no holidays)
5. Select leave type (e.g., Annual Leave)
6. Submit request

**Expected Results:**
- ✅ Request accepted without errors
- ✅ Leave hours calculated: **40 hours** (5 days × 8 hours)
- ✅ Database record shows `leave_hours = 40.00`
- ✅ Leave balance deducted by 40 hours

**Verification Query:**
```sql
SELECT leave_id, employee_id, from_date, to_date, leave_hours, leave_month, leave_year
FROM ci_leave_applications
WHERE employee_id = [Employee1_ID]
ORDER BY leave_id DESC LIMIT 1;
```

---

### Scenario 2: Full Day Leave - Weekend Exclusion

**Objective:** Verify weekends are excluded from working days calculation

**Test Steps:**
1. Login as Employee 1 (8-hour shift, Mon-Fri, Sat-Sun off)
2. Request leave from Friday to Tuesday (spans weekend)
3. Date range: Fri, Sat, Sun, Mon, Tue = 5 calendar days

**Expected Results:**
- ✅ Working days: **4 days** (Fri, Mon, Tue, Wed - excludes Sat, Sun)
- ✅ Leave hours: **32 hours** (4 days × 8 hours)
- ✅ Saturday and Sunday NOT counted

**Manual Verification:**
- Check that Saturday and Sunday have empty `in_time` in shift configuration
- Verify only working days are counted

---

### Scenario 3: Full Day Leave - Holiday Exclusion

**Objective:** Verify company holidays are excluded from working days

**Test Steps:**
1. Login as Employee 1 (Company A)
2. Request leave that includes a company holiday
3. Date range: Monday to Friday (5 calendar days)
4. Ensure one day (e.g., Wednesday) is a Company A holiday

**Expected Results:**
- ✅ Working days: **4 days** (excludes holiday)
- ✅ Leave hours: **32 hours** (4 days × 8 hours)
- ✅ Holiday day NOT counted

**Verification:**
- Check `ci_holidays` table for Company A holidays
- Verify holiday falls within date range
- Confirm holiday is excluded from calculation

---

### Scenario 4: Full Day Leave - Multi-Day Holiday

**Objective:** Verify multi-day holidays are properly excluded

**Test Steps:**
1. Login as Employee 1
2. Request leave from Monday to Friday (5 calendar days)
3. Ensure a 3-day holiday (Wed-Thu-Fri) exists in the range

**Expected Results:**
- ✅ Working days: **2 days** (Mon, Tue only)
- ✅ Leave hours: **16 hours** (2 days × 8 hours)
- ✅ All 3 holiday days excluded

---

### Scenario 5: Full Day Leave - Holiday on Weekend

**Objective:** Verify no double-counting when holiday falls on weekend

**Test Steps:**
1. Login as Employee 1 (Mon-Fri shift)
2. Request leave from Monday to Sunday (7 calendar days)
3. Ensure a holiday exists on Saturday (already a non-working day)

**Expected Results:**
- ✅ Working days: **5 days** (Mon-Fri only)
- ✅ Leave hours: **40 hours** (5 days × 8 hours)
- ✅ Saturday counted only ONCE as excluded (not twice)
- ✅ Sunday also excluded (non-working day)

---

### Scenario 6: Full Day Leave - Different Shift Hours

**Objective:** Verify hours calculation varies by employee shift

**Test Steps:**
1. Login as Employee 2 (10-hour shift, Sun-Thu)
2. Request leave from Sunday to Thursday (5 working days)
3. Submit request

**Expected Results:**
- ✅ Working days: **5 days**
- ✅ Leave hours: **50 hours** (5 days × 10 hours)
- ✅ Different from 8-hour shift employee

**Comparison Test:**
- Employee 1 (8 hours): 5 days = 40 hours
- Employee 2 (10 hours): 5 days = 50 hours
- ✅ Same days, different hours based on shift

---

### Scenario 7: Full Day Leave - Six-Day Work Week

**Objective:** Verify calculation for employees with 6-day work weeks

**Test Steps:**
1. Login as Employee 3 (Mon-Sat shift, Sunday off)
2. Request leave from Monday to Sunday (7 calendar days)

**Expected Results:**
- ✅ Working days: **6 days** (Mon-Sat, excludes Sunday)
- ✅ Leave hours: **48 hours** (6 days × 8 hours)
- ✅ Sunday excluded (non-working day)

---

### Scenario 8: Full Day Leave - Company-Specific Holidays

**Objective:** Verify only employee's company holidays are excluded

**Test Steps:**
1. Login as Employee 1 (Company A)
2. Request leave from Monday to Friday
3. Ensure Company A has holiday on Wednesday
4. Ensure Company B has holiday on Thursday (should NOT affect Employee 1)

**Expected Results:**
- ✅ Working days: **4 days** (excludes only Company A holiday on Wed)
- ✅ Leave hours: **32 hours** (4 days × 8 hours)
- ✅ Company B holiday on Thursday IS counted (not excluded)

**Verification:**
- Check that Company B holiday does NOT affect Company A employee
- Verify company_id filtering in holiday query

---

### Scenario 9: Hourly Permission - Basic Calculation

**Objective:** Verify hourly permission calculation without break overlap

**Test Steps:**
1. Login as Employee 1 (Shift: 08:00-17:00, Lunch: 12:00-13:00)
2. Select "Leave in Hours" (Permission) request type
3. Select date: Monday (working day)
4. Enter time: 08:00 AM to 11:00 AM (3 hours, before lunch)
5. Submit request

**Expected Results:**
- ✅ Request accepted
- ✅ Leave hours: **3.00 hours** (11:00 - 08:00)
- ✅ No break time subtracted (permission ends before lunch)

---

### Scenario 10: Hourly Permission - Break Time Subtraction

**Objective:** Verify break time is subtracted when permission spans lunch

**Test Steps:**
1. Login as Employee 1 (Shift: 08:00-17:00, Lunch: 12:00-13:00)
2. Request permission from 08:00 AM to 02:00 PM (6 hours)
3. Permission spans lunch break (12:00-13:00)

**Expected Results:**
- ✅ Raw time difference: 6 hours (14:00 - 08:00)
- ✅ Break overlap: 1 hour (12:00-13:00)
- ✅ Leave hours: **5.00 hours** (6 - 1 = 5)
- ✅ Break time automatically subtracted

---

### Scenario 11: Hourly Permission - Partial Break Overlap

**Objective:** Verify partial break overlap is correctly calculated

**Test Steps:**
1. Login as Employee 1 (Lunch: 12:00-13:00)
2. Request permission from 11:00 AM to 12:30 PM

**Expected Results:**
- ✅ Raw time: 1.5 hours (12:30 - 11:00)
- ✅ Break overlap: 0.5 hours (12:00-12:30, partial overlap)
- ✅ Leave hours: **1.00 hour** (1.5 - 0.5 = 1.0)

---

### Scenario 12: Hourly Permission - After Break

**Objective:** Verify no break subtraction when permission is after lunch

**Test Steps:**
1. Login as Employee 1 (Lunch: 12:00-13:00)
2. Request permission from 01:00 PM to 04:00 PM (after lunch)

**Expected Results:**
- ✅ Leave hours: **3.00 hours** (16:00 - 13:00)
- ✅ No break time subtracted (permission starts after lunch)

---

### Scenario 13: Hourly Permission - Different Shift Break Times

**Objective:** Verify break times vary by shift configuration

**Test Steps:**
1. Login as Employee 3 (Shift C: Lunch 13:00-14:00)
2. Request permission from 12:00 PM to 03:00 PM

**Expected Results:**
- ✅ Raw time: 3 hours (15:00 - 12:00)
- ✅ Break overlap: 1 hour (13:00-14:00)
- ✅ Leave hours: **2.00 hours** (3 - 1 = 2)
- ✅ Different break time than Shift A

---

### Scenario 14: Hourly Permission - Time Validation (Before Shift)

**Objective:** Verify permission times outside shift hours are rejected

**Test Steps:**
1. Login as Employee 1 (Shift: 08:00-17:00)
2. Request permission from 06:00 AM to 10:00 AM (starts before shift)

**Expected Results:**
- ❌ Request REJECTED
- ✅ Error message: "Permission times must fall within your shift working hours"
- ✅ Error message in user's language (English or Arabic)
- ✅ No leave application created

---

### Scenario 15: Hourly Permission - Time Validation (After Shift)

**Objective:** Verify permission ending after shift hours is rejected

**Test Steps:**
1. Login as Employee 1 (Shift: 08:00-17:00)
2. Request permission from 03:00 PM to 06:00 PM (ends after shift)

**Expected Results:**
- ❌ Request REJECTED
- ✅ Error message displayed
- ✅ No leave application created

---

### Scenario 16: Hourly Permission - Non-Working Day

**Objective:** Verify permission on non-working day is rejected

**Test Steps:**
1. Login as Employee 1 (Mon-Fri shift, Sat-Sun off)
2. Request permission on Saturday
3. Enter time: 08:00 AM to 12:00 PM

**Expected Results:**
- ❌ Request REJECTED
- ✅ Error message: "Cannot request permission on a non-working day"
- ✅ No leave application created

---

### Scenario 17: No Shift Assigned - Validation

**Objective:** Verify employees without shifts cannot request leave

**Test Steps:**
1. Login as Employee 4 (NO SHIFT ASSIGNED)
2. Attempt to request any type of leave (full day or hourly)

**Expected Results:**
- ❌ Request REJECTED immediately
- ✅ Error message: "You must have an office shift assigned before requesting leave"
- ✅ Error message in user's language
- ✅ Validation occurs BEFORE any calculation
- ✅ No leave application created

**Verification:**
- Check that `office_shift_id` is NULL or 0 in `ci_erp_users_details`
- Verify error appears immediately on submission

---

### Scenario 18: Zero Working Days

**Objective:** Verify handling when leave request has no working days

**Test Steps:**
1. Login as Employee 1 (Mon-Fri shift)
2. Request leave for Saturday-Sunday only (2 non-working days)

**Expected Results:**
- ✅ Request accepted (no validation error)
- ✅ Working days: **0 days**
- ✅ Leave hours: **0.00 hours**
- ✅ No hours deducted from balance

---

### Scenario 19: Backward Compatibility - Existing Leave

**Objective:** Verify existing leave applications are NOT recalculated

**Test Steps:**
1. Identify an existing leave application created BEFORE this fix
2. Note its current `leave_hours` value (may be incorrect)
3. Deploy the new code
4. Query the same leave application

**Expected Results:**
- ✅ Existing `leave_hours` value UNCHANGED
- ✅ No automatic recalculation occurs
- ✅ Historical data integrity maintained
- ✅ Only NEW requests use new calculation logic

**Verification Query:**
```sql
-- Check old leave applications
SELECT leave_id, employee_id, from_date, to_date, leave_hours, created_at
FROM ci_leave_applications
WHERE created_at < '2024-01-28'  -- Before fix deployment
ORDER BY leave_id DESC LIMIT 10;
```

---

### Scenario 20: Localization - English Error Messages

**Objective:** Verify error messages display in English

**Test Steps:**
1. Set system language to English
2. Login as Employee 4 (no shift)
3. Attempt to request leave

**Expected Results:**
- ✅ Error message in English: "You must have an office shift assigned before requesting leave"
- ✅ Message retrieved from `app/Language/en/Main.php`

---

### Scenario 21: Localization - Arabic Error Messages

**Objective:** Verify error messages display in Arabic

**Test Steps:**
1. Set system language to Arabic
2. Login as Employee 4 (no shift)
3. Attempt to request leave

**Expected Results:**
- ✅ Error message in Arabic: "يجب أن يكون لديك وردية مكتبية معينة قبل طلب الإجازة"
- ✅ Message retrieved from `app/Language/ar/Main.php`
- ✅ Arabic text displays correctly (RTL)

---

### Scenario 22: Leave Balance Display

**Objective:** Verify leave balance shows both days and hours

**Test Steps:**
1. Login as Employee 1 (8-hour shift)
2. Check leave balance display
3. Note: Employee has 10 days annual leave remaining

**Expected Results:**
- ✅ Display format: "10 days (80 hours)"
- ✅ Hours calculated: 10 × 8 = 80 hours
- ✅ Display updates dynamically

**Comparison:**
- Employee 1 (8 hours): 10 days = "10 days (80 hours)"
- Employee 2 (10 hours): 10 days = "10 days (100 hours)"
- ✅ Same days, different hours based on shift

---

## Edge Cases

### Edge Case 1: Leap Year February 29

**Test Steps:**
1. Request leave that includes February 29 (leap year)
2. Verify date is handled correctly

**Expected Results:**
- ✅ February 29 counted if it's a working day
- ✅ No date calculation errors

---

### Edge Case 2: Year Boundary

**Test Steps:**
1. Request leave from December 30 to January 5 (crosses year boundary)

**Expected Results:**
- ✅ Working days calculated correctly across years
- ✅ Holidays from both years considered
- ✅ No date range errors

---

### Edge Case 3: Same Day Leave

**Test Steps:**
1. Request full day leave for a single day (from Monday to Monday)

**Expected Results:**
- ✅ Working days: **1 day**
- ✅ Leave hours: **8 hours** (or shift hours_per_day)

---

### Edge Case 4: Permission Exactly During Break

**Test Steps:**
1. Request permission from 12:00 PM to 01:00 PM (exactly lunch break)

**Expected Results:**
- ✅ Raw time: 1 hour
- ✅ Break overlap: 1 hour (entire permission is break)
- ✅ Leave hours: **0.00 hours**
- ✅ Request accepted but no hours deducted

---

### Edge Case 5: Permission with Minutes

**Test Steps:**
1. Request permission from 08:15 AM to 11:45 AM

**Expected Results:**
- ✅ Leave hours: **3.50 hours** (3 hours 30 minutes)
- ✅ Decimal precision maintained
- ✅ Minutes correctly converted to decimal hours

---

### Edge Case 6: Very Long Leave Request

**Test Steps:**
1. Request leave for 30 consecutive days

**Expected Results:**
- ✅ All working days calculated correctly
- ✅ All weekends excluded
- ✅ All holidays excluded
- ✅ Correct total hours calculated
- ✅ No performance issues

---

### Edge Case 7: Multiple Holidays in Range

**Test Steps:**
1. Request leave that includes 3 separate holidays

**Expected Results:**
- ✅ All 3 holidays excluded
- ✅ Working days count correct
- ✅ Hours calculation accurate

---

### Edge Case 8: Holiday Spanning Multiple Days

**Test Steps:**
1. Request leave that includes a 5-day holiday (e.g., Eid holiday)

**Expected Results:**
- ✅ All 5 holiday days excluded
- ✅ Multi-day holiday handled correctly
- ✅ Working days calculation accurate

---

### Edge Case 9: Shift with No Break Time

**Test Steps:**
1. Create shift with empty lunch break times
2. Request hourly permission spanning typical lunch hours

**Expected Results:**
- ✅ No break time subtracted (break is empty)
- ✅ Full time difference calculated
- ✅ No errors due to empty break times

---

### Edge Case 10: Different Day Break Times

**Test Steps:**
1. Create shift where Monday has different break time than Tuesday
2. Request permission on Monday spanning break
3. Request permission on Tuesday spanning break

**Expected Results:**
- ✅ Monday: Correct break time subtracted for Monday
- ✅ Tuesday: Correct break time subtracted for Tuesday
- ✅ Day-specific break times respected

---

## Validation Checklist

### Pre-Deployment Validation

- [ ] All automated tests passing (2,200+ assertions)
- [ ] No syntax errors in modified files
- [ ] Database schema supports `leave_hours` column (DECIMAL)
- [ ] Language files contain required error messages (English & Arabic)
- [ ] LeavePolicy library properly instantiated in controller

### Post-Deployment Validation

- [ ] Existing leave applications unchanged (backward compatibility)
- [ ] New leave requests use new calculation logic
- [ ] Error messages display in correct language
- [ ] Leave balance displays correctly
- [ ] No performance degradation

### Functional Validation

- [ ] Full day leave calculates working days correctly
- [ ] Weekends excluded based on shift configuration
- [ ] Company holidays excluded correctly
- [ ] Company-specific holiday filtering works
- [ ] No double-counting of overlapping exclusions
- [ ] Hours conversion uses correct hours_per_day
- [ ] Hourly permission calculates time difference correctly
- [ ] Break time subtracted when permission spans break
- [ ] Permission times validated against shift hours
- [ ] Employees without shifts cannot request leave

### Data Integrity Validation

- [ ] Leave hours stored as DECIMAL in database
- [ ] Values match calculated hours (round-trip test)
- [ ] No data loss or corruption
- [ ] Historical data unchanged

---

## Troubleshooting

### Issue: Error "You must have an office shift assigned"

**Cause:** Employee has no shift assigned in `ci_erp_users_details`

**Solution:**
1. Check `office_shift_id` in `ci_erp_users_details` for the employee
2. Assign a valid shift to the employee
3. Retry leave request

**Verification Query:**
```sql
SELECT user_id, office_shift_id
FROM ci_erp_users_details
WHERE user_id = [EMPLOYEE_ID];
```

---

### Issue: Incorrect working days calculation

**Cause:** Shift configuration has incorrect day settings

**Solution:**
1. Check shift configuration in `ci_office_shifts`
2. Verify `{day}_in_time` columns are correctly set
3. Empty `in_time` = non-working day
4. Non-empty `in_time` = working day

**Verification Query:**
```sql
SELECT office_shift_id, shift_name, 
       monday_in_time, tuesday_in_time, wednesday_in_time,
       thursday_in_time, friday_in_time, saturday_in_time, sunday_in_time
FROM ci_office_shifts
WHERE office_shift_id = [SHIFT_ID];
```

---

### Issue: Holidays not excluded

**Cause:** Holiday not in database or wrong company_id

**Solution:**
1. Check `ci_holidays` table for the date range
2. Verify `company_id` matches employee's company
3. Verify `start_date` and `end_date` cover the leave dates

**Verification Query:**
```sql
SELECT holiday_id, company_id, event_name, start_date, end_date
FROM ci_holidays
WHERE company_id = [COMPANY_ID]
  AND start_date <= '[LEAVE_END_DATE]'
  AND end_date >= '[LEAVE_START_DATE]';
```

---

### Issue: Break time not subtracted

**Cause:** Break time not configured in shift or permission doesn't overlap

**Solution:**
1. Check shift has `{day}_lunch_break` and `{day}_lunch_break_out` set
2. Verify permission time range actually overlaps with break time
3. Check day of week matches shift configuration

**Verification Query:**
```sql
SELECT office_shift_id, shift_name,
       monday_lunch_break, monday_lunch_break_out,
       tuesday_lunch_break, tuesday_lunch_break_out
FROM ci_office_shifts
WHERE office_shift_id = [SHIFT_ID];
```

---

### Issue: Permission rejected with "Invalid times"

**Cause:** Permission times fall outside shift working hours

**Solution:**
1. Check shift `{day}_in_time` and `{day}_out_time` for the day
2. Verify permission start_time >= shift in_time
3. Verify permission end_time <= shift out_time
4. Adjust permission times to fall within shift hours

---

### Issue: Different hours for same days

**Cause:** This is EXPECTED behavior - employees have different shifts

**Solution:**
- This is correct! Employees with different `hours_per_day` will have different leave hours for the same number of days
- Example: 5 days for 8-hour shift = 40 hours, but 5 days for 10-hour shift = 50 hours

---

### Issue: Existing leave applications changed

**Cause:** This should NOT happen - indicates a bug

**Solution:**
1. Check deployment process - new logic should only apply to NEW requests
2. Verify no data migration scripts were run
3. Restore from backup if historical data was modified
4. Report as critical bug

---

## Test Completion Sign-Off

### Tester Information
- **Tester Name:** ___________________________
- **Test Date:** ___________________________
- **Environment:** ☐ Development  ☐ Staging  ☐ Production

### Test Results Summary
- **Total Scenarios Tested:** _____ / 22
- **Passed:** _____
- **Failed:** _____
- **Blocked:** _____

### Critical Issues Found
1. _____________________________________________
2. _____________________________________________
3. _____________________________________________

### Sign-Off
- [ ] All critical scenarios passed
- [ ] All edge cases validated
- [ ] Backward compatibility confirmed
- [ ] Localization verified (English & Arabic)
- [ ] No data integrity issues found
- [ ] Feature ready for production deployment

**Tester Signature:** ___________________________

**Date:** ___________________________

---

## Additional Notes

### Performance Considerations
- Leave calculation should complete within 2 seconds for typical date ranges (up to 30 days)
- No noticeable delay when submitting leave requests
- Database queries optimized with proper indexing

### Security Considerations
- Employees can only request leave for themselves
- Shift validation prevents unauthorized leave requests
- All inputs sanitized and validated

### Accessibility Considerations
- Error messages clearly displayed
- Form validation provides helpful feedback
- Bilingual support for diverse user base

---

**Document Version:** 1.0  
**Last Updated:** January 28, 2024  
**Author:** Development Team  
**Status:** Ready for Testing
