# Internal Helpdesk System - Implementation Plan (Updated v3)

## Overview

Internal support ticket system for each company, using **existing tables** (`ci_support_tickets`, `ci_support_ticket_reply`).

---

## Hierarchy-Based Access Control

### Hierarchy Levels:
- **Level 0** = Company/Super Admin → Full access
- **Level 1** = Highest rank (Manager)
- **Level 5** = Lowest rank (Employee)

### Authority Rule:
- Employee with Level **X** can view/manage:
  - Own tickets
  - Tickets of employees with Level **> X** (lower rank)

---

## Visibility Rules

| User Type | Can View Tickets                                          |
|-----------|-----------------------------------------------------------|
| `company` | All tickets in company                                    |
| `staff`   | Own tickets + Tickets created by/assigned to subordinates |

### Access Check Logic:
Employee can access ticket if:
1. **Created the ticket** (`created_by = user_id`)
2. **Ticket assigned to them** (`employee_id = user_id`)
3. **Creator is subordinate** (hierarchy level > user's level)
4. **Assigned employee is subordinate** (hierarchy level > user's level)

---

## Permission Matrix

| Operation             | company                           | staff                               |
|-----------------------|-----------------------------------|-------------------------------------|
| Create ticket         | ✅ (to any dept/employee)         | ✅ (self or subordinates)          |
| View tickets          | ✅ All                            | ✅ Own + subordinates              |
| View single ticket    | ✅ Any                            | ✅ Own + subordinates              |
| Update ticket         | ✅ Any (open only)                | ✅ Own + subordinates (open only) |
| Delete ticket         | ❌ **Disabled**                   | ❌ **Disabled**                    |
| Add reply             | ✅ Any (open only)                | ✅ Own + subordinates (open only)  |
| Close ticket          | ✅ Any                            | ✅ subordinates only (not own)     |
| Reopen ticket         | ✅ Any                            | ✅ subordinates only (not own)     |

> **Note:** Closed tickets (status=2) cannot receive updates or replies. Must reopen first.

---

## Creation Logic

### If user_type = `company`:
```
- Select department_id (required)
- Select employee_id from that department (required)
- Ticket is assigned to specific employee
```

### If user_type = `staff`:
```
- If no employee_id specified or employee_id = self:
  - employee_id = current user
  - department_id = user's own department
- If employee_id specified (subordinate):
  - Validate employee is in subordinates list
  - Use subordinate's department_id
```

---

## Departments & Employees Filtering

### GET /departments:
| User Type | Returns                                         |
|-----------|------------------------------------------------ |
| `company` | All departments                                 |
| `staff`   | Departments with subordinates + own department  |

### GET /employees/{dept}:
| User Type | Returns                                         |
|-----------|------------------------------------------------ |
| `company` | All employees in department                     |
| `staff`   | Subordinates in department + self (if same dept)|

---

## Update Ticket

- **Allowed fields:** `subject`, `priority` only
- **Priority:** Accepts name (`low`, `medium`, `high`, `urgent`, `critical`)
- **Converts:** Name to value in database

---

## API Endpoints

```
GET    /api/internal-helpdesk/enums
GET    /api/internal-helpdesk/departments      # Filtered by hierarchy
GET    /api/internal-helpdesk/employees/{dept} # Filtered by hierarchy

GET    /api/internal-helpdesk
POST   /api/internal-helpdesk
GET    /api/internal-helpdesk/{id}
PUT    /api/internal-helpdesk/{id}             # subject + priority only
DELETE /api/internal-helpdesk/{id}             # ❌ Disabled

POST   /api/internal-helpdesk/{id}/close
POST   /api/internal-helpdesk/{id}/reopen

GET    /api/internal-helpdesk/{id}/replies
POST   /api/internal-helpdesk/{id}/replies
```

---

## Key Features

- ✅ Use existing tables
- ✅ Hierarchy-based visibility
- ✅ Departments/Employees filtered by subordinates
- ✅ Company assigns to department + employee
- ✅ Staff creates for self or subordinates
- ✅ Update = subject + priority only
- ✅ Close/Reopen = Company any, Staff subordinates only
- ✅ Delete = Disabled
- ❌ No notifications
- ❌ No SLA/deadlines

