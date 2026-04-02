# Push Notifications System - نظام الإشعارات الفورية

## Overview - نظرة عامة

نظام إشعارات فورية يستخدم **Firebase Cloud Messaging (FCM V1 API)** لإرسال إشعارات Push للتطبيقات المحمولة.

---

## Complete Flow - التسلسل الكامل من فتح التطبيق

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                    Complete Push Notification Flow                          │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  ┌──────────────── PHASE 1: App Launch & Token Registration ──────────────┐│
│  │                                                                         ││
│  │  1. User opens Mobile App                                               ││
│  │              │                                                          ││
│  │              ▼                                                          ││
│  │  2. Firebase SDK generates FCM Device Token                             ││
│  │              │                                                          ││
│  │              ▼                                                          ││
│  │  3. User logs in → POST /api/login                                      ││
│  │              │                                                          ││
│  │              ▼                                                          ││
│  │  4. App saves FCM Token → POST /api/user/device-token                   ││
│  │              │              { "token": "fcm_token_here..." }            ││
│  │              ▼                                                          ││
│  │  5. Backend saves token in ci_erp_users.device_token                    ││
│  │                                                                         ││
│  └─────────────────────────────────────────────────────────────────────────┘│
│                                                                             │
│  ┌──────────────── PHASE 2: Request Submission ───────────────────────────┐│
│  │                                                                         ││
│  │  6. Employee submits request (Leave, Overtime, etc.)                    ││
│  │              │                                                          ││
│  │              ▼                                                          ││
│  │  7. Service Layer (LeaveService, etc.) processes request                ││
│  │              │                                                          ││
│  │              ▼                                                          ││
│  │  8. Dispatches SendNotificationJob to Queue                             ││
│  │              │                                                          ││
│  │              ├──→ Creates record in ci_erp_notifications                ││
│  │              │                                                          ││
│  │              └──→ PushNotificationService.sendSubmissionPush()          ││
│  │                         │                                               ││
│  │                         ▼                                               ││
│  │  9. Sends to FCM V1 API → Firebase servers                              ││
│  │              │                                                          ││
│  │              ▼                                                          ││
│  │  10. Manager receives Push: "طلب جديد - إجازة"                          ││
│  │                                                                         ││
│  └─────────────────────────────────────────────────────────────────────────┘│
│                                                                             │
│  ┌──────────────── PHASE 3: Approval/Rejection ───────────────────────────┐│
│  │                                                                         ││
│  │  11. Manager approves/rejects request                                   ││
│  │              │                                                          ││
│  │              ▼                                                          ││
│  │  12. Service Layer processes approval                                   ││
│  │              │                                                          ││
│  │              ▼                                                          ││
│  │  13. Dispatches SendApprovalNotificationJob                             ││
│  │              │                                                          ││
│  │              ├──→ Records approval in ci_erp_notifications_approval     ││
│  │              │                                                          ││
│  │              └──→ PushNotificationService.sendApprovalPush()            ││
│  │                         │                                               ││
│  │                         ▼                                               ││
│  │  14. Sends to FCM V1 API → Firebase servers                             ││
│  │              │                                                          ││
│  │              ▼                                                          ││
│  │  15. Employee receives Push: "تمت الموافقة - إجازة"                     ││
│  │                                                                         ││
│  └─────────────────────────────────────────────────────────────────────────┘│
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

### Sequence Summary:

| Step  | Action                  | Endpoint/Component                  |
|-------|-------------------------|-------------------------------------|
| 1     | فتح التطبيق            | Mobile App                          |
| 2     | Firebase يولد Token     | Firebase SDK                        |
| 3     | تسجيل الدخول           | `POST /api/login`                   |
| 4     | حفظ Token              | `POST /api/user/device-token`       |
| 5     | تخزين في DB            | `ci_erp_users.device_token`         |
| 6     | تقديم طلب              | `POST /api/leaves` (مثال)           |
| 7-9   | إرسال Push للمدير      | `SendNotificationJob` → FCM         |
| 10    | المدير يستلم الإشعار    | Mobile Push                         |
| 11    | الموافقة/الرفض         | `PATCH /api/leaves/{id}/approve`    |
| 12-14 | إرسال Push للموظف      | `SendApprovalNotificationJob` → FCM |
| 15    | الموظف يستلم النتيجة   | Mobile Push                         |  

---

## Architecture - البنية المعمارية

## Integration Status - حالة التكامل

### ✅ مُكتمل (Completed)

| Component                     | Status | Description                                  |
|-------------------------------|--------|----------------------------------------------|
| `PushNotificationService`     | ✅     | Service للإرسال عبر FCM V1                   |
| `SendNotificationJob`         | ✅     | Job لإرسال إشعارات الطلبات الجديدة          |
| `SendApprovalNotificationJob` | ✅     | Job لإرسال إشعارات الموافقة/الرفض           |
| Device Token Endpoint         | ✅     | `/api/user/device-token` لحفظ token الجهاز   |
| Database Column               | ✅     | `ci_erp_users.device_token`                   |

### 📋 Services المُتكاملة

Push Notifications مُفعّلة تلقائياً عند:
- إنشاء طلب جديد (via `SendNotificationJob`)
- الموافقة/الرفض (via `SendApprovalNotificationJob`)

---

## API Endpoints - نقاط الوصول

### Save Device Token - حفظ Token الجهاز

```http
POST /api/user/device-token
Authorization: Bearer {access_token}
Content-Type: application/json

{
    "token": "fMC_device_token_from_mobile_app..."
}
```

**Response:**
```json
{
    "success": true,
    "message": "Device token updated",
    "device_token": "fMC..."
}
```

---

## Push Notification Types - أنواع الإشعارات

### 1. Submission (طلب جديد)

```json
{
    "notification": {
        "title": "طلب جديد - إجازة",
        "body": "قام أحمد محمد بإرسال طلب جديد"
    },
    "data": {
        "type": "submission",
        "module": "إجازة",
        "request_id": "123"
    }
}
```

### 2. Approval Result (نتيجة الموافقة)

```json
{
    "notification": {
        "title": "تمت الموافقة - إجازة",
        "body": "تم تمت الموافقة على طلبك"
    },
    "data": {
        "type": "approval_result",
        "module": "إجازة",
        "status": "approved",
        "request_id": "123"
    }
}
```

---

## File Structure - هيكل الملفات

```
app/
├── Services/
│   └── PushNotificationService.php    # FCM V1 API Service
├── Jobs/
│   ├── SendNotificationJob.php         # New request notifications
│   └── SendApprovalNotificationJob.php # Approval/Rejection notifications
├── Models/
│   └── User.php                         # device_token field
└── Http/Controllers/Api/
    └── AuthController.php               # updateDeviceToken() method

storage/app/
└── firebase_credentials.json            # Firebase Service Account

database/migrations/
└── 2026_01_04_092906_add_device_token_to_users_table.php
```

---

## Configuration - التكوين

### 1. Firebase Credentials

ضع ملف `firebase_credentials.json` في:
```
storage/app/firebase_credentials.json
```

يمكن الحصول عليه من:
1. Firebase Console → Project Settings → Service Accounts
2. Generate New Private Key

### 2. Composer Package

```bash
composer require google/auth
```

### 3. Database

الـ migration موجود بالفعل:
```php
// 2026_01_04_092906_add_device_token_to_users_table.php
Schema::table('ci_erp_users', function (Blueprint $table) {
    $table->string('device_token', 500)->nullable();
});
```

---

## PushNotificationService Methods

| Method                                                                        | Description            |
|-------------------------------------------------------------------------------|------------------------|
| `sendToUser($userId, $title, $body, $data)`                                   | إرسال لمستخدم واحد    |
| `sendToUsers($userIds, $title, $body, $data)`                                 | إرسال لعدة مستخدمين   |
| `sendSubmissionPush($recipientIds, $moduleTitle, $submitterName, $requestId)` | إشعار طلب جديد       |
| `sendApprovalPush($recipientIds, $moduleTitle, $status, $requestId)`          | إشعار موافقة/رفض     |

---

## Mobile App Integration - تكامل التطبيق

### 1. Save Device Token

عند تسجيل الدخول أو تحديث الـ FCM token:

```dart
// Flutter Example
Future<void> saveDeviceToken() async {
  final fcmToken = await FirebaseMessaging.instance.getToken();
  
  await http.post(
    Uri.parse('$baseUrl/api/user/device-token'),
    headers: {'Authorization': 'Bearer $accessToken'},
    body: {'token': fcmToken},
  );
}
```

### 2. Handle Push Notification

```dart
// Flutter Example
FirebaseMessaging.onMessage.listen((RemoteMessage message) {
  final data = message.data;
  
  if (data['type'] == 'submission') {
    // Navigate to requests list
  } else if (data['type'] == 'approval_result') {
    // Show result and refresh
  }
});
```

---

## Logging - التسجيل

All push notification activities are logged:

```php
// Success
Log::info('Push notification sent successfully (V1)', ['user_token' => '...']);

// Failure
Log::error('FCM V1 Send Error', ['http_code' => 401, 'response' => '...']);
Log::warning('No device token for user', ['user_id' => 123]);
```

---

## Troubleshooting - استكشاف الأخطاء

| Issue                                 | Solution                                          |
|---------------------------------------|---------------------------------------------------|
| `Firebase credentials file not found` | ضع `firebase_credentials.json` في `storage/app/` |
| `No device token for user`            | المستخدم لم يسجل الـ token بعد                  |
| `HTTP 401`                            | الـ credentials غير صالحة                        |
| `HTTP 404`                            | الـ device token غير صالح (unregistered)         |

---

**Developed by:** FirstSoft Development Team
