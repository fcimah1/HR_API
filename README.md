# HR Management API System

## 📋 Overview

An enterprise-grade Human Resources Management API built with **Laravel**. This system provides a robust backend for managing the complete employee lifecycle, from recruitment to resignation/retirement. It features a sophisticated **hierarchical permission system**, multi-level approval workflows, and extensive module logical isolation (Departments/Branches).

## 🚀 Key Features

### 👤 Employee Management

- **Complete Profile Management:** Personal, official, and document details.
- **Hierarchical Visibility:** Access to employee data is strictly governed by `hierarchy_level` (Levels 1-5).
- **Subordinates Management:** Automatic subordinate detection based on hierarchy.
- **Duty/Backup Employees:** Logic for handling temporary assignments.

### 🕒 Attendance & Time Tracking

- **Biometric Integration:** Endpoints for syncing logs from biometric devices.
- **Manual Adjustments:** Support for manual clock-in/out with approval.
- **Reports:** Daily status and monthly detailed reports.

### 🏝️ Leave Management

- **Types:** Annual, Sick, Unpaid, etc.
- **Hourly Leaves:** Short duration leave request tracking.
- **Adjustments:** Automatic calculation adjustments and balance checks.
- **Leave Balance:** Real-time checking endpoint.

### 💸 Payroll & Financials

- **Advance Salary/Loans:** Request and approval cycle.
- **Overtime:** Request management with rate calculations.

### 🔄 Request & Workflow Modules

- **Office Shift Management:** Complete management of working hours, lunch breaks, and late allowances with full daily configuration.
- **Unified Employee Requests:** Centralized view for all types of requests (Leaves, Overtimes, Transfers, etc.) for a specific employee.
- **Custody Clearance (إخلاء طرف):** Asset tracking and return validation strictly tied to employee assignments.
- **Transfers:** Internal (Dept to Dept), Branch, and Inter-company transfers with multi-step approvals.
- **Promotions:** Employee career progression tracking with automatic record updates upon approval.
- **Resignations:** Formal resignation process with approval workflow.
- **Travels:** Business travel requests handling.
- **Complaints & Suggestions:** Feedback channels with privacy controls.

### 📦 Assets & Awards

- **Asset Management:** Cataloging company assets with category and brand configuration.
- **Award Management:** Recording employee recognitions, cash awards, and gift certificates with multi-level approval.

### 🏭 Inventory Management

- **Warehouses:** Complete warehouse management with hierarchical access control.
- **Suppliers:** Manage suppliers and vendors information with isolated company data.

### 💰 Finance Management

- **Accounts:** Manage company-staff accounts and employee bank accounts.
- **Transactions:** track deposits and expenses (Income/Expense) with categorized logging.
- **Categories:** Configurable income and expense categories.

### 🔐 Security & Access Control

- **OAuth2 Authentication:** Secure API access using Bearer tokens.
- **Simple Permission Service:** Custom service layer enforcing:
    - **Hierarchy Check:** Users can only view/act on subordinates.
    - **Operation Restrictions:** Department and Branch level isolation.
    - **Leaves Types Restrictions:** Leaves types restrictions.

- **Company Isolation:** Multi-tenant architecture support (Company vs. Staff users).

### ✅ Approval Workflow

- **Multi-Level Approvals:** Configurable approval chains (Level 1, 2, 3).
- **Fallback Logic:** Automatic fallback to direct manager if no approval chain is defined.
- **Notifications:** Integrated notification system for pending approvals.

## 🛠️ Technology Stack

- **Framework:** Laravel 11/12 (PHP > 8.2)
- **Database:** MySQL
- **Documentation:** Swagger/OpenAPI (`l5-swagger`)
- **PDF Generation:** mPDF / TCPDF
- **Validation:** Laravel Form Requests with custom rules
- **Push Notifications:** Laravel Notifications with firebase fcm
- **DTO:** Data Transfer Objects
- **SOLID Principles:** SOLID principles followed
- **Dependency Injection:** Dependency injection used
- **Design Patterns:** Design patterns used
- **Version Control:** Git
- **Repository pattern:** Repository pattern used

## ⚙️ Installation

1. **Clone the repository:**

    ```bash
    git clone <repository-url>
    cd HR_API
    ```

2. **Install Dependencies:**

    ```bash
    composer install
    npm install
    ```

3. **Environment Setup:**

    ```bash
    cp .env.example .env
    php artisan key:generate
    ```

    _Configure your database credentials in `.env`_

4. **Database Migration:**

    ```bash
    php artisan migrate
    ```

5. **API Documentation:**
   Generate Swagger documentation:

    ```bash
    php artisan l5-swagger:generate
    ```

6. **Serve:**
    ```bash
    php artisan serve
    ```

## 📚 API Structure

| Module                       | Base Path                              | Key Operations                                                                                              |
| ---------------------------- | -------------------------------------- | ----------------------------------------------------------------------------------------------------------- | --- |
| **Employees**                | `/api/employees`                       | List, View, Create, Update, Export, Stats by Country, Relatives/Family                                      |
| **Branches**                 | `/api/branches`                        | Manage company branches and locations                                                                       |
| **Departments**              | `/api/departments`                     | Manage organizational departments                                                                           |
| **Designations**             | `/api/designations`                    | Manage job titles and hierarchy levels                                                                      |
| **Meetings**                 | `/api/meetings`                        | Schedule and manage meetings                                                                                |
| **EndOfService**             | `/api/end-of-service`                  | Calculate, Save, List, Approve (See [docs/end_of_service_calculator.md](docs/end_of_service_calculator.md)) |
| **Office Shifts**            | `/api/office-shifts`                   | CRUD for office working schedules                                                                           |
| **Unified Requests**         | `/api/employees/{id}/requests/unified` | Combined view of all request types                                                                          |
| **Leaves**                   | `/api/leaves`                          | Apply, Approve/Reject, Balance Check                                                                        |
| **Hourly Leaves**            | `/api/hourly-leaves`                   | Apply, Approve/Reject, Balance Check                                                                        |
| **Leave Adjustments**        | `/api/leave-adjustments`               | Apply, Approve/Reject                                                                                       |
| **Leave Balance**            | `/api/leave-balance`                   | Check Balance                                                                                               |
| **Overtime**                 | `/api/overtimes`                       | Apply, Approve/Reject                                                                                       |
| **Advance Salary/Loans**     | `/api/advances`                        | Apply, Approve/Reject                                                                                       |
| **Attendance**               | `/api/attendances`                     | Clock In/Out, Monthly Report                                                                                |
| **Async Reports**            | `/api/async-reports`                   | Generate and track background report tasks                                                                  |
| **Visitors**                 | `/api/visitors`                        | Manage visitor logs and entries                                                                             |
| **Custody**                  | `/api/custody-clearances`              | Create Clearance, List Assets                                                                               |
| **Transfers**                | `/api/transfers`                       | Internal/Branch Transfer Requests                                                                           |
| **Resignations**             | `/api/resignations`                    | Apply, Approve/Reject                                                                                       |
| **Announcements**            | `/api/announcements`                   | Create and view company-wide announcements                                                                  |
| **Travels**                  | `/api/travels`                         | Apply, Approve/Reject                                                                                       |     |
| **Complaints & Suggestions** | `/api/complaints`                      | Apply, Approve/Reject                                                                                       |
| **Polls**                    | `/api/polls`                           | Create and vote on polls                                                                                    |
| **Terminations**             | `/api/terminations`                    | Manage employee terminations (involuntary)                                                                  |
| **Residence Renewals**       | `/api/residence-renewals`              | Track and renew employee residency documents                                                                |
| **Holidays**                 | `/api/holidays`                        | Manage public holidays and company off-days                                                                 |
| **System Logs**              | `/api/system-logs`                     | View system activity logs (Admin only)                                                                      |
| **Jobs Monitor**             | `/api/jobs`                            | Monitor background jobs and failed queues                                                                   |
| **Reports**                  | `/api/reports`                         | Comprehensive HR, Financial, and Attendance reports                                                         |
| **Support Tickets**          | `/api/support-tickets`                 | Create, Reply, Close, Reopen (See [docs/SUPPORT_TICKETS.md](docs/SUPPORT_TICKETS.md))                       |
| **Internal Helpdesk**        | `/api/internal-helpdesk`               | Internal IT/HR Support Tickets (See [docs/INTERNAL_HELPDESK_PLAN.md](docs/INTERNAL_HELPDESK_PLAN.md))       |
| **Assets**                   | `/api/assets`                          | CRUD for company assets and equipment                                                                       |
| **Awards**                   | `/api/awards`                          | Manage employee awards with approval cycle                                                                  |
| **Inventory (Warehouses)**   | `/api/inventory/warehouses`            | Manage company warehouses with hierarchical access                                                          |
| **Inventory (Suppliers)**    | `/api/inventory/suppliers`             | Manage suppliers and vendor information                                                                     |
| **Finance (Accounts)**       | `/api/finance/accounts`                | Manage staff financial accounts                                                                             |
| **Finance (Emp. Accounts)**  | `/api/finance/employee-accounts`       | Manage employee bank accounts                                                                               |
| **Finance (Deposits)**       | `/api/finance/deposits`                | Record and manage income transactions                                                                       |
| **Finance (Expenses)**       | `/api/finance/expenses`                | Record and manage expense transactions                                                                      |
| **Finance (Transactions)**   | `/api/finance/transactions`            | View all financial transactions                                                                             |
| **Finance (Categories)**     | `/api/finance/categories`              | Manage income/expense categories                                                                            |
| **Promotions**               | `/api/promotions`                      | Manage employee career movements and salary updates                                                         |
| **Training**                 | `/api/trainings`                       | Manage Training Sessions (See [docs/TRAINING.md](docs/TRAINING.md))                                         |
| **Trainers**                 | `/api/trainers`                        | Manage Trainers (See [docs/TRAINING.md](docs/TRAINING.md))                                                  |
| **Training Skills**          | `/api/training-skills`                 | Manage Training Types/Skills (See [docs/TRAINING.md](docs/TRAINING.md))                                     |
| **Biometric Attendance**     | `/api/biometric-logs`                  | Sync Logs                                                                                                   |
| **Manual Attendance**        | `/api/attendances`                     | Clock In/Out, Monthly Report                                                                                |
| **Notifications**            | `/api/notifications`                   | List, Mark as Read                                                                                          |
| **Approval**                 | `/api/approvals`                       | Pending List, History, Process                                                                              |

_For full endpoint details, please refer to the Swagger UI at `/api/documentation`_

## 🛡️ Architecture Highlights

### Standard Implementation Pattern

To ensure scalability and maintainability, the project uses a layered architecture:

- **DTOs (Data Transfer Objects):** For structured data passing between layers and request validation assistance.
- **Repository Pattern:** Decoupling database logic from business logic.
- **Service Layer:** Explicit business logic handling (e.g., `OfficeShiftService`).
- **Form Requests:** Specialized request classes for robust input validation.
- **Middleware:** Custom middleware for authentication and authorization.
- **Events:** Custom events for business logic handling.
- **Jobs:** Custom jobs for background processing.
- **Notifications:** Custom notifications for business logic handling.
- **Observers:** Custom observers for business logic handling.
- **Resources:** Custom resources for responses formatting.
- **Scopes:** Custom scopes for database queries.
- **Services:** Custom services for business logic handling.
- **Traits:** Custom traits for code reuse.
- **Validators:** Custom validators for input validation.

### The `SimplePermissionService`

The core of our access control. Unlike standard RBAC, this service evaluates **dynamic relationships**:

- Is User Has Permission To Do Something? (`permission`)
- Is User A numerically superior to User B? (`hierarchy_level`)
- Is User A restricted from User B's department? (`OperationRestriction`)
- Is User A restricted from User B's branch? (`OperationRestriction`)

### The `ApprovalService`

A unified service for all approval-based modules.

- **Checks:** `canUserApprove($userId, $requestId)`
- **Logic:**
    1. Checks defined approval chain in `ci_erp_users_details`.
    2. If empty, falls back to `SimplePermissionService` hierarchy check.
    3. Records approval steps in `ci_erp_notifications_approval`.

### Validation

Strict validation rules prevent logical errors, such as:

- Preventing creating requests for non-subordinates (403 Forbidden).
- Ensuring assets in Custody Clearance actually belong to the employee.

---

**Developed by:** FirstSoft Development Team
Eng: Mohamed Ahmed
Email: mohamed.firstsoft@gmail.com
Phone No : 01120882362

https://api.firstsoft.io

Rules for Uploading to GitHub:
1- git add .  
2- git commit -m "Your commit message"
3- git checkout -b <branch_name>
4- git push -u origin <branch_name>
5- git fetch origin  
6- git merge origin/main
7- solve conflicts if any
8- git add .
9- git commit -m "Your commit message"
10- git push
11- in github create pull request
12- git pull origin main

https://api.firstsoft.io
php artisan l5-swagger:generate
