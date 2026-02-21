# Company-Based Leave Policy Manual

## Overview

The Company-Based Leave Policy system allows companies to enforce country-specific labor laws and leave entitlements for all their employees. Instead of configuring rules per employee, the system applies valid rules based on the **Company's Operating Country** (e.g., Saudi Arabia, Egypt, Kuwait, Qatar).

## Features

- **Centralized Configuration**: Set the country once for the entire company account.
- **Auto-Entitlement**: Automatically calculates Annual Leave days based on "Service Years".
- **Sick Leave Tiers**: Supports complex tiered sick leave (e.g., full pay for first 30 days, 75% for next 60).
- **Exceptions**: Supports country-specific exceptions, such as "Disability" entitlement in Egypt.

---

## Configuration Guide

### 1. Accessing the Configuration

1. Log in as a **Company Admin** or **Super User**.
2. Determine which country your company operates in (or which labor law you follow).
3. Navigate to **Employees > Leave Policy Configuration** in the side menu.

### 2. Setting the Policy Country

1. On the configuration page, you will see a dropdown labeled **Country**.
2. Select one of the supported countries:
   - **Saudi Arabia (SA)**
   - **Egypt (EG)**
   - **Kuwait (KW)**
   - **Qatar (QA)**
3. Click **Save**.
4. The table below the form will update to show the **Active Policy Rules** for that country. Review these rules to ensure they match your expectations.

---

## Employee Management

### 1. Employee Setup

- Go to **Employees > Staff Directory > Edit Employee**.
- Ensure the **Date of Joining** is correct. This is crucial as entitlement is calculated based on Service Years (Current Date - Join Date).
- **Disability (Egypt Only)**: If your company is configured for **Egypt**, you will see an option for **Disability** in the Basic Info tab. Set this to "Yes" if the employee qualifies for the increased 45-day entitlement.

### 2. Leave Application

- When an employee (or HR on their behalf) applies for leave:
  - The system checks the **Company Policy** settings.
  - It calculates the maximum allowed days for that specific Leave Type (Annual, Sick, etc.).
  - If the requested duration exceeds the entitlement (or balance), the application is blocked with an error message explaining the limit.

---

## Troubleshooting

### Q: I selected a country but the rules look wrong.

**A**: Ensure you clicked "Save" after selecting. If rules persist, contact support to check if the database seeds were updated correctly.

### Q: My employee is not getting the correct entitlement.

**A**: Check the **Date of Joining**. If an employee has only worked 6 months but the policy requires 1 year for full entitlement, they will get the lower tier (or pro-rated amount if applicable).

### Q: The "Disability" field is not showing.

**A**: This field is always visible in the Employee Edit form designated for policy exceptions, but it only affects calculations if the **Egypt** policy is active.

### Q: Can I set a different policy for one specific employee?

**A**: No. The system is designed to enforce a consistent Company-Wide Policy. Exceptions must be handled via manual adjustments or system overrides if built.
