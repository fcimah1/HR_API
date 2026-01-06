# Support Tickets System

## Overview

A support ticket management system that allows users to create tickets and communicate with support staff.

---

## Database Tables

| Table                     | Description |
|---------------------------|-------------|
| `ci_company_tickets`      | Tickets     |
| `ci_company_tickets_reply`| Replies     |

---

## Enums

### Ticket Status (TicketStatusEnum)
| Value | Name  | Arabic   |
|-------|-------|----------|
| 1     | open  | مفتوحة  |
| 2     | closed| مغلقة   |

### Ticket Category (TicketCategoryEnum)
| Value | Name        | Arabic    |
|-------|-------------|-----------|
| 0     | general     | عام       |
| 1     | technical   | تقني      |
| 2     | billing     | فواتير    |
| 3     | subscription| اشتراك    |
| 4     | other       | أخرى      |

### Ticket Priority (TicketPriorityEnum)
| Value | Name    | Arabic | Color |
|-------|---------|--------|-------|
| 1     | urgent  | عاجل   | red   |
| 2     | high    | عالي   | orange|
| 3     | medium  | متوسط  | yellow|
| 4     | low     | قليل   | green |

---

## Permission System

### User Types
| Type          | company_id | Description                         |
|---------------|------------|-------------------------------------|
| `super_user`  | -          | Support staff (full access)         |
| `company`     | 0          | Company owner (company_id = user_id)|
| `staff`       | > 0        | Employee belonging to a company     |

### Permission Matrix

| Operation     | super_user                   | company/staff                |
|---------------|------------------------------|------------------------------|
| Create ticket | ✅                          | ✅                          |
| View tickets  | ✅ All tickets              | ✅ Own tickets only         |
| Update ticket | ✅ Open tickets only        | ✅ Own open tickets only    |
| Delete ticket | ✅ Any ticket               | ✅ Own tickets only         | <!-- ✅ disabled until choosing logic -->
| Add reply     | ✅ Open tickets only        | ✅ Own open tickets only    |
| Close ticket  | ✅                          | ❌                          |
| Reopen ticket | ✅                          | ❌                          |

---

## API Endpoints

### Endpoint List

```
GET    /api/support-tickets/enums        # Get categories, statuses, priorities
GET    /api/support-tickets              # List tickets
POST   /api/support-tickets              # Create ticket
GET    /api/support-tickets/{id}         # Get ticket details
PUT    /api/support-tickets/{id}         # Update ticket
DELETE /api/support-tickets/{id}         # Delete ticket                            # ✅ disabled until choosing logic
POST   /api/support-tickets/{id}/close   # Close ticket (super_user only)
POST   /api/support-tickets/{id}/reopen  # Reopen ticket (super_user only)
GET    /api/support-tickets/{id}/replies # Get ticket replies
POST   /api/support-tickets/{id}/replies # Add reply
```

### Query Parameters for Filtering

```
?page=1
?per_page=15
?status=open          # or closed, 1, 2
?category=technical   # or general, billing, subscription, other
?priority=high        # or urgent, medium, low
?search=search text
?from_date=2026-01-01
?to_date=2026-01-31
```

---

## Usage Examples

### Create Ticket

```json
POST /api/support-tickets
{
  "subject": "Login issue",
  "category": "technical",
  "priority": "high",
  "description": "Cannot login to the system"
}
```

### Add Reply

```json
POST /api/support-tickets/{id}/replies
{
  "reply_text": "Thank you for contacting us, we will review the issue"
}
```

### Close Ticket (super_user only)

```json
POST /api/support-tickets/{id}/close
{
  "ticket_remarks": "Issue resolved"
}
```

---

## File Structure

```
app/
├── Enums/
│   ├── TicketCategoryEnum.php
│   ├── TicketStatusEnum.php
│   └── TicketPriorityEnum.php
├── Models/
│   ├── SupportTicket.php
│   └── TicketReply.php
├── DTOs/SupportTicket/
│   ├── CreateTicketDTO.php
│   ├── UpdateTicketDTO.php
│   ├── TicketFilterDTO.php
│   ├── CreateReplyDTO.php
│   └── CloseTicketDTO.php
├── Repository/
│   ├── Interface/SupportTicketRepositoryInterface.php
│   └── SupportTicketRepository.php
├── Services/
│   └── SupportTicketService.php
├── Http/
│   ├── Controllers/Api/SupportTicketController.php
│   ├── Requests/SupportTicket/
│   │   ├── CreateTicketRequest.php
│   │   ├── UpdateTicketRequest.php
│   │   ├── GetTicketsRequest.php
│   │   ├── CreateReplyRequest.php
│   │   └── CloseTicketRequest.php
│   └── Resources/
│       ├── SupportTicketResource.php
│       └── TicketReplyResource.php
└── Providers/
    └── AppServiceProvider.php (updated)

routes/
└── api.php (updated)
```

---

## Important Notes

1. **company_id on Creation:**
   - If user `user_type = 'company'` → saves `company_id = user_id`
   - If user is `staff` → saves `company_id` from user table

2. **Closed Tickets (status = 2):**
   - ❌ **No one** can update a closed ticket (including super_user)
   - ❌ **No one** can add replies to a closed ticket (including super_user)
   - ✅ Ticket must be **reopened first** via `/api/support-tickets/{id}/reopen`
   - ✅ Only super_user can close/reopen tickets

3. **Swagger Documentation:**
   - All endpoints are documented with OpenAPI/Swagger
   - Requests accept names (technical, high) and convert them to numbers automatically

4. **Enum Methods:**
   - `fromName()`: Accepts English/Arabic names or numeric values
   - `getAcceptedNames()`: Returns all accepted input values
