# Role
Act as a Senior Laravel Developer building a consumer API for an external ERP database.

# Context
We have a mobile app for employees. We need a "Pre-Check" endpoint that tells the user *if* they can apply for a loan and *what* their limits are, based on the CodeIgniter ERP database.

# Objective
Create a read-only endpoint `GET /api/v1/loans/eligibility` that runs business logic against the ERP database.

# Database Connection
- Use a secondary database connection config (`config/database.php` -> `erp_db`) to connect to the CodeIgniter MySQL database.
- Do NOT create new migrations. We are reading existing tables: `ci_employees`, `ci_loan_requests`, and the new `ci_loan_policy_configs`.

# Logic Specifications
Implement a Service class `LoanEligibilityService` with this logic:

1.  **Fetch User Data:** Get the authenticated user's salary and ID.
2.  **Date Check:**
    - Is today between Day 7 and Day 21 of the month?
    - If NO: Return `eligible: false`, `message: "Requests only accepted between 2nd and 3rd week"`.
3.  **Active Loan Check:**
    - Query `ci_loan_requests` on `erp_db`.
    - If user has any loan where `status != 'paid'`, Return `eligible: false`.
4.  **Calculate Limits:**
    - Fetch all tiers from `ci_loan_policy_configs`.
    - Calculate `max_deduction = salary * 0.50`.
    - For each tier, calculate the max loan amount (`salary * multiplier`).
    - **Validation:** Check if `(max_loan_amount / max_months) <= max_deduction`. If it fits, add to `allowed_options`.

# Output JSON Structure
Return this exact format:
```json
{
    "status": true,
    "data": {
        "eligible": true,
        "salary_cap": 5000,
        "current_window_open": true,
        "available_tiers": [
            {
                "id": 2,
                "name": "1 Month Salary",
                "max_amount": 10000,
                "months": 4
            }
        ]
    }
}