# خطة تنفيذ نظام تذاكر الدعم الفني - Support Tickets Module

## نظرة عامة

تطوير موديول كامل لإدارة تذاكر الدعم الفني يتضمن:
- إنشاء تذاكر جديدة مع أنواع مختلفة (عام، تقني، فواتير، اشتراك، أخرى)
- نظام رسائل/ردود بين الدعم الفني والموظف/الشركة
- حالتين للتذكرة: **مفتوحة** (تسمح بتبادل الرسائل) و **مغلقة** (تمنع CHATTING)

> **ملاحظة مهمة:**
> هذا الموديول يتبع نفس النمط المستخدم في موديول `Complaint` وباقي الموديولات الموجودة في المشروع.

---

## نظام الصلاحيات والرؤية

> **تحذير:**
> هذا القسم مهم جداً لفهم منطق الوصول للتذاكر

### 1. مستخدم Super User (`user_type = 'super_user'`)

| الصلاحية | الوصف |
|----------|-------|
| رؤية جميع التذاكر | يرى كل التذاكر لجميع الشركات في النظام |
| الرد على أي تذكرة | يمكنه التفاعل بالشات مع أي تذكرة مفتوحة |
| إغلاق أي تذكرة | يمكنه إغلاق أي تذكرة عند الانتهاء من حلها |
| إعادة فتح أي تذكرة | يمكنه إعادة فتح أي تذكرة مغلقة |

### 2. المستخدمين العاديين (Company Users / Employees)

| الصلاحية | الوصف |
|----------|-------|
| رؤية تذاكرهم فقط | يرى فقط التذاكر التي أنشأها هو |
| فتح تذكرة جديدة | يمكنه إنشاء تذكرة دعم جديدة |
| فتح تذكرة موجودة | يمكنه عرض تفاصيل تذكرته والردود |
| الشات مع الدعم | يمكنه إضافة ردود على تذكرته المفتوحة |
| إغلاق تذكرته | يمكنه إغلاق تذكرته الخاصة عند انتهاء الحاجة |

### منطق التحقق في الـ Service

```php
// في SupportTicketService
public function getPaginatedTickets(TicketFilterDTO $filters, User $user): array
{
    // Super User يرى كل التذاكر
    if ($user->user_type === 'super_user') {
        // لا يتم تطبيق أي فلتر على الشركة أو المنشئ
        return $this->repository->getAllTickets($filters);
    }
    
    // المستخدم العادي يرى تذاكره فقط
    $filters->createdBy = $user->user_id;
    return $this->repository->getTicketsByCreator($filters);
}

public function canAccessTicket(SupportTicket $ticket, User $user): bool
{
    // Super User يمكنه الوصول لأي تذكرة
    if ($user->user_type === 'super_user') {
        return true;
    }
    
    // المستخدم العادي يمكنه الوصول لتذاكره فقط
    return $ticket->created_by === $user->user_id;
}
```

---

## الجداول الموجودة

### جدول `ci_company_tickets`

| العمود | النوع | الوصف |
|--------|------|-------|
| `ticket_id` | INT (PK) | معرف التذكرة |
| `company_id` | INT | معرف الشركة |
| `ticket_code` | VARCHAR | كود التذكرة الفريد |
| `subject` | VARCHAR | عنوان التذكرة |
| `ticket_priority` | TINYINT | أولوية التذكرة |
| `category_id` | TINYINT | نوع/فئة التذكرة |
| `description` | TEXT | وصف المشكلة |
| `ticket_remarks` | TEXT | ملاحظات |
| `ticket_status` | TINYINT | حالة التذكرة (1=مفتوحة، 0=مغلقة) |
| `created_by` | INT | معرف منشئ التذكرة |
| `created_at` | DATETIME | تاريخ الإنشاء |

### جدول `ci_company_tickets_reply`

| العمود | النوع | الوصف |
|--------|------|-------|
| `ticket_reply_id` | INT (PK) | معرف الرد |
| `company_id` | INT | معرف الشركة |
| `ticket_id` | INT | معرف التذكرة (FK) |
| `sent_by` | INT | معرف المرسل |
| `assign_to` | INT | معرف المستلم |
| `reply_text` | TEXT | نص الرد/الرسالة |
| `created_at` | DATETIME | تاريخ الإرسال |

---

## التغييرات المقترحة

### Enums (ملفات التعداد)

#### [NEW] TicketCategoryEnum.php
المسار: `app/Enums/TicketCategoryEnum.php`

```php
enum TicketCategoryEnum: int
{
    case GENERAL = 0;      // عام
    case TECHNICAL = 1;    // تقني
    case BILLING = 2;      // فواتير
    case SUBSCRIPTION = 3; // اشتراك
    case OTHER = 4;        // أخرى
}
```

#### [NEW] TicketStatusEnum.php
المسار: `app/Enums/TicketStatusEnum.php`

```php
enum TicketStatusEnum: int
{
    case CLOSED = 0;  // مغلقة - لا يمكن إضافة ردود
    case OPEN = 1;    // مفتوحة - يمكن تبادل الرسائل
}
```

#### [NEW] TicketPriorityEnum.php
المسار: `app/Enums/TicketPriorityEnum.php`

```php
enum TicketPriorityEnum: int
{
    case URGENT = 1;  // عاجل
    case HIGH = 2;    // مرتفع
    case MEDIUM = 3;  // متوسط
    case LOW = 4;     // منخفض
}
```

---

### Models

#### [NEW] SupportTicket.php
المسار: `app/Models/SupportTicket.php`

موديل التذاكر مع:
- العلاقات: `company()`, `createdBy()`, `replies()`
- Accessors: `getStatusTextAttribute()`, `getCategoryTextAttribute()`, `getPriorityTextAttribute()`
- ثوابت الحالات

#### [NEW] TicketReply.php
المسار: `app/Models/TicketReply.php`

موديل الردود مع:
- العلاقات: `ticket()`, `sender()`, `assignee()`

---

### DTOs

| الملف | المسار |
|-------|--------|
| [NEW] CreateTicketDTO.php | `app/DTOs/SupportTicket/CreateTicketDTO.php` |
| [NEW] UpdateTicketDTO.php | `app/DTOs/SupportTicket/UpdateTicketDTO.php` |
| [NEW] TicketFilterDTO.php | `app/DTOs/SupportTicket/TicketFilterDTO.php` |
| [NEW] CreateReplyDTO.php | `app/DTOs/SupportTicket/CreateReplyDTO.php` |
| [NEW] CloseTicketDTO.php | `app/DTOs/SupportTicket/CloseTicketDTO.php` |

---

### Repository

#### [NEW] SupportTicketRepositoryInterface.php
المسار: `app/Repository/Interface/SupportTicketRepositoryInterface.php`

```php
interface SupportTicketRepositoryInterface
{
    public function getPaginatedTickets(TicketFilterDTO $filters, User $user): array;
    public function findTicketById(int $id, int $companyId): ?SupportTicket;
    public function createTicket(CreateTicketDTO $dto): SupportTicket;
    public function updateTicket(SupportTicket $ticket, UpdateTicketDTO $dto): SupportTicket;
    public function closeTicket(SupportTicket $ticket, int $closedBy, ?string $remarks = null): SupportTicket;
    public function addReply(CreateReplyDTO $dto): TicketReply;
    public function getTicketReplies(int $ticketId): Collection;
}
```

#### [NEW] SupportTicketRepository.php
المسار: `app/Repository/SupportTicketRepository.php`

---

### Service

#### [NEW] SupportTicketService.php
المسار: `app/Services/SupportTicketService.php`

الـ Service سيحتوي على:
- `getPaginatedTickets()` - عرض التذاكر مع التصفية **حسب نوع المستخدم**
- `getTicketById()` - عرض تفاصيل تذكرة **مع التحقق من الصلاحية**
- `createTicket()` - إنشاء تذكرة جديدة مع كود فريد
- `updateTicket()` - تحديث تذكرة **للمالك أو Super User**
- `closeTicket()` - إغلاق تذكرة (منع الردود) **للمالك أو Super User**
- `reopenTicket()` - إعادة فتح تذكرة **للمالك أو Super User**
- `addReply()` - إضافة رد/رسالة (فقط إذا كانت التذكرة مفتوحة) **مع التحقق من الصلاحية**
- `getTicketReplies()` - عرض ردود تذكرة **مع التحقق من الصلاحية**
- `getEnums()` - عرض الأنواع والأولويات والحالات
- `canAccessTicket()` - **التحقق من صلاحية الوصول للتذكرة**

---

### Form Requests

| الملف | المسار |
|-------|--------|
| [NEW] CreateTicketRequest.php | `app/Http/Requests/SupportTicket/CreateTicketRequest.php` |
| [NEW] UpdateTicketRequest.php | `app/Http/Requests/SupportTicket/UpdateTicketRequest.php` |
| [NEW] GetTicketsRequest.php | `app/Http/Requests/SupportTicket/GetTicketsRequest.php` |
| [NEW] CreateReplyRequest.php | `app/Http/Requests/SupportTicket/CreateReplyRequest.php` |
| [NEW] CloseTicketRequest.php | `app/Http/Requests/SupportTicket/CloseTicketRequest.php` |

---

### Controller

#### [NEW] SupportTicketController.php
المسار: `app/Http/Controllers/Api/SupportTicketController.php`

| Method | Route | الوصف | الصلاحية |
|--------|-------|-------|----------|
| `index()` | GET `/support-tickets` | عرض قائمة التذاكر | Super User: الكل، عادي: تذاكره فقط |
| `show()` | GET `/support-tickets/{id}` | عرض تفاصيل تذكرة | Super User: أي تذكرة، عادي: تذكرته |
| `store()` | POST `/support-tickets` | إنشاء تذكرة جديدة | الكل |
| `update()` | PUT `/support-tickets/{id}` | تحديث تذكرة | Super User: أي تذكرة، عادي: تذكرته |
| `close()` | POST `/support-tickets/{id}/close` | إغلاق تذكرة | Super User: أي تذكرة، عادي: تذكرته |
| `reopen()` | POST `/support-tickets/{id}/reopen` | إعادة فتح تذكرة | Super User: أي تذكرة، عادي: تذكرته |
| `addReply()` | POST `/support-tickets/{id}/replies` | إضافة رد | Super User: أي تذكرة، عادي: تذكرته |
| `getReplies()` | GET `/support-tickets/{id}/replies` | عرض الردود | Super User: أي تذكرة، عادي: تذكرته |
| `getEnums()` | GET `/support-tickets/enums` | عرض الأنواع والحالات | الكل |

---

### Routes

#### [MODIFY] api.php
المسار: `routes/api.php`

```php
// Support Tickets Management - تذاكر الدعم الفني
Route::prefix('support-tickets')->group(function () {
    Route::get('/enums', [SupportTicketController::class, 'getEnums']);
    Route::get('/', [SupportTicketController::class, 'index']);
    Route::post('/', [SupportTicketController::class, 'store']);
    Route::get('/{id}', [SupportTicketController::class, 'show']);
    Route::put('/{id}', [SupportTicketController::class, 'update']);
    Route::post('/{id}/close', [SupportTicketController::class, 'close']);
    Route::post('/{id}/reopen', [SupportTicketController::class, 'reopen']);
    Route::get('/{id}/replies', [SupportTicketController::class, 'getReplies']);
    Route::post('/{id}/replies', [SupportTicketController::class, 'addReply']);
});
```

---

### Service Provider

#### [MODIFY] AppServiceProvider.php
المسار: `app/Providers/AppServiceProvider.php`

إضافة ربط الـ Interface:

```php
$this->app->bind(
    SupportTicketRepositoryInterface::class,
    SupportTicketRepository::class
);
```

---

## خطة التحقق (Verification Plan)

### اختبار يدوي باستخدام Postman/Insomnia

**اختبار Super User:**
1. تسجيل دخول كـ `super_user`
2. عرض كل التذاكر (من جميع الشركات)
3. الرد على أي تذكرة
4. إغلاق أي تذكرة

**اختبار مستخدم عادي:**
1. تسجيل دخول كمستخدم عادي
2. إنشاء تذكرة جديدة
3. عرض التذاكر (يجب أن يرى تذاكره فقط)
4. محاولة الوصول لتذكرة شخص آخر (يجب أن يفشل - 403)
5. الرد على تذكرته الخاصة
6. إغلاق تذكرته

---

## ملخص الملفات الجديدة

| النوع | الملف |
|------|-------|
| Enum | `app/Enums/TicketCategoryEnum.php` |
| Enum | `app/Enums/TicketStatusEnum.php` |
| Enum | `app/Enums/TicketPriorityEnum.php` |
| Model | `app/Models/SupportTicket.php` |
| Model | `app/Models/TicketReply.php` |
| DTO | `app/DTOs/SupportTicket/CreateTicketDTO.php` |
| DTO | `app/DTOs/SupportTicket/UpdateTicketDTO.php` |
| DTO | `app/DTOs/SupportTicket/TicketFilterDTO.php` |
| DTO | `app/DTOs/SupportTicket/CreateReplyDTO.php` |
| DTO | `app/DTOs/SupportTicket/CloseTicketDTO.php` |
| Interface | `app/Repository/Interface/SupportTicketRepositoryInterface.php` |
| Repository | `app/Repository/SupportTicketRepository.php` |
| Service | `app/Services/SupportTicketService.php` |
| Request | `app/Http/Requests/SupportTicket/CreateTicketRequest.php` |
| Request | `app/Http/Requests/SupportTicket/UpdateTicketRequest.php` |
| Request | `app/Http/Requests/SupportTicket/GetTicketsRequest.php` |
| Request | `app/Http/Requests/SupportTicket/CreateReplyRequest.php` |
| Request | `app/Http/Requests/SupportTicket/CloseTicketRequest.php` |
| Controller | `app/Http/Controllers/Api/SupportTicketController.php` |

**إجمالي الملفات الجديدة:** 19 ملف
