# Training Management System - نظام إدارة التدريب

## Overview - نظرة عامة

نظام شامل لإدارة التدريب يتضمن:
- **Training Sessions (التدريبات):** إنشاء وإدارة جلسات التدريب
- **Trainers (المدربين):** إدارة بيانات المدربين
- **Training Skills (مهارات التدريب):** أنواع ومهارات التدريب

---

## Database Tables - جداول قاعدة البيانات

| Table                  | Description         | الوصف              |
|------------------------|---------------------|--------------------|
| `ci_erp_training`      | Training sessions   | جلسات التدريب      |
| `ci_erp_training_note` | Training notes      | ملاحظات التدريب     |
| `ci_erp_trainer`       | Trainers            | المدربين           |
| `ci_erp_constants`     | Training types      | أنواع التدريب      |

---

## Enums - التعدادات

### Training Status (TrainingStatusEnum)
| Value | Name       | Arabic       |
|-------|------------|--------------|
| 0     | PENDING    | قيد الانتظار |
| 1     | STARTED    | بدأ          |
| 2     | COMPLETED  | اكتمل       |
| 3     | REJECTED   | مرفوض       |

### Training Performance (TrainingPerformanceEnum)
| Value | Name         | Arabic       |
|-------|--------------|--------------|
| 0     | NOT_FINISHED | غير منتهى   |
| 1     | SATISFACTORY | مرضٍ          |
| 2     | AVERAGE      | متوسط        |
| 3     | POOR         | ضعيف         |
| 4     | EXCELLENT    | ممتاز        |

---

## Architecture - البنية المعمارية

### File Structure
```
app/
├── Models/
│   ├── Training.php
│   ├── TrainingNote.php
│   └── Trainer.php
├── Enums/
│   ├── TrainingStatusEnum.php
│   └── TrainingPerformanceEnum.php
├── DTOs/
│   ├── Training/
│   │   ├── CreateTrainingDTO.php
│   │   ├── UpdateTrainingDTO.php
│   │   └── TrainingFilterDTO.php
│   ├── Trainer/
│   │   ├── CreateTrainerDTO.php
│   │   └── TrainerFilterDTO.php
│   └── TrainingSkill/
│       ├── CreateTrainingSkillDTO.php
│       └── UpdateTrainingSkillDTO.php
├── Http/
│   ├── Controllers/Api/
│   │   ├── TrainingController.php
│   │   ├── TrainerController.php
│   │   └── TrainingSkillController.php
│   ├── Requests/
│   │   ├── Training/
│   │   │   ├── CreateTrainingRequest.php
│   │   │   ├── UpdateTrainingRequest.php
│   │   │   ├── UpdateTrainingStatusRequest.php
│   │   │   └── AddTrainingNoteRequest.php
│   │   ├── Trainer/
│   │   │   ├── CreateTrainerRequest.php
│   │   │   └── UpdateTrainerRequest.php
│   │   └── TrainingSkill/
│   │       ├── CreateTrainingSkillRequest.php
│   │       └── UpdateTrainingSkillRequest.php
│   └── Resources/
│       ├── TrainingResource.php
│       ├── TrainingNoteResource.php
│       └── TrainerResource.php
├── Repository/
│   ├── Interface/
│   │   ├── TrainingRepositoryInterface.php
│   │   ├── TrainerRepositoryInterface.php
│   │   └── TrainingSkillRepositoryInterface.php
│   ├── TrainingRepository.php
│   ├── TrainerRepository.php
│   └── TrainingSkillRepository.php
└── Services/
    ├── TrainingService.php
    ├── TrainerService.php
    └── TrainingSkillService.php
```

---

## API Endpoints - نقاط الوصول

### Training Sessions (التدريبات)

| Method | Endpoint                     | Description                    | Permission       |
|--------|------------------------------|--------------------------------|------------------|
| GET    | `/api/trainings`             | List all trainings             | training1        |
| POST   | `/api/trainings`             | Create new training            | training2        |
| GET    | `/api/trainings/{id}`        | Get training details           | training1        |
| PUT    | `/api/trainings/{id}`        | Update training                | training3        |
| DELETE | `/api/trainings/{id}`        | Delete training                | training4        |
| PATCH  | `/api/trainings/{id}/status` | Update status & performance    | training3        |
| GET    | `/api/trainings/{id}/notes`  | Get training notes             | training1        |
| POST   | `/api/trainings/{id}/notes`  | Add training note              | training2        |
| GET    | `/api/trainings/enums`       | Get status & performance enums | training1        |
| GET    | `/api/trainings/statistics`  | Get training statistics        | training1        |

### Trainers (المدربين)

| Method | Endpoint                     | Description               | Permission       |
|--------|------------------------------|---------------------------|------------------|
| GET    | `/api/trainers`              | List all trainers         | trainer1         |
| POST   | `/api/trainers`              | Create new trainer        | trainer2         |
| GET    | `/api/trainers/{id}`         | Get trainer details       | trainer1         |
| PUT    | `/api/trainers/{id}`         | Update trainer            | trainer3         |
| DELETE | `/api/trainers/{id}`         | Delete trainer            | trainer4         |
| GET    | `/api/trainers/dropdown`     | Get trainers for dropdown | training1        |

### Training Skills (مهارات التدريب)

| Method | Endpoint                     | Description               | Permission       |
|--------|------------------------------|---------------------------|------------------|
| GET    | `/api/training-skills`       | List all training skills  | training_skill1  |
| POST   | `/api/training-skills`       | Create new skill          | training_skill2  |
| PUT    | `/api/training-skills/{id}`  | Update skill              | training_skill3  |
| DELETE | `/api/training-skills/{id}`  | Delete skill              | training_skill4  |

---

## Query Parameters - معاملات الاستعلام

### Trainings List

```
?status=0                    # Filter by status (0=pending,1=started,2=completed,3=rejected)
?trainer_id=1                # Filter by trainer
?training_type_id=1          # Filter by training type
?department_id=1             # Filter by department
?employee_id=37              # Filter by employee
?from_date=2026-01-01        # Filter from date
?to_date=2026-12-31          # Filter to date
?search=PHP                  # Search term
?page=1                      # Page number
?per_page=15                 # Items per page
?sort_by=created_at          # Sort field
?sort_direction=desc         # Sort direction
```

---

## Usage Examples - أمثلة الاستخدام

### Create Training - إنشاء تدريب

```json
POST /api/trainings
{
    "department_id": 1,
    "employee_id": [37, 38, 39],
    "training_type_id": 1,
    "trainer_id": 1,
    "start_date": "2026-01-15",
    "finish_date": "2026-01-20",
    "training_cost": 1500.00,
    "description": "دورة تدريبية في تطوير PHP",
    "associated_goals": "تطوير مهارات البرمجة"
}
```

### Update Training Status (with Performance) - تحديث الحالة والأداء

```json
PATCH /api/trainings/{id}/status
{
    "status": 2,
    "performance": 4,
    "remarks": "تم الانتهاء من التدريب بنجاح"
}
```

### Create Trainer - إنشاء مدرب

```json
POST /api/trainers
{
    "first_name": "محمد",
    "last_name": "أحمد",
    "email": "trainer@example.com",
    "contact_number": "01234567890",
    "expertise": "PHP, Laravel, JavaScript",
    "address": "القاهرة، مصر"
}
```

### Create Training Skill - إنشاء نوع تدريب

```json
POST /api/training-skills
{
    "name": "تطوير البرمجيات"
}
```

### Add Training Note - إضافة ملاحظة

```json
POST /api/trainings/{id}/notes
{
    "note": "المتدربون أظهروا تقدماً ملحوظاً"
}
```

---

## Permission & Access Control - الصلاحيات والتحكم

### Module Permissions

| Permission Key        | Description          |
|-----------------------|----------------------|
| `hr_training`         | Module access        |
| `training1`           | View trainings       |
| `training2`           | Create trainings     |
| `training3`           | Update trainings     |
| `training4`           | Delete trainings     |
| `trainer1`            | View trainers        |
| `trainer2`            | Create trainers      |
| `trainer3`            | Update trainers      |
| `trainer4`            | Delete trainers      |
| `training_skill1`     | View training skills |
| `training_skill2`     | Create training skills |
| `training_skill3`     | Update training skills |
| `training_skill4`     | Delete training skills |

### Operation Restrictions - قيود العمليات

The system enforces operation restrictions based on:

1. **Training Type Restrictions (`training_type_`):**
   - Users can be restricted from specific training types
   - Restricted types are filtered from view and blocked from update/delete

2. **Trainer Restrictions (`trainer_`):**
   - Users can be restricted from specific trainers
   - Restricted trainers are blocked from update/delete

3. **Company Owner Bypass:**
   - Company owners (`user_type = 'company'`) bypass all operation restrictions

---

## Response Examples - أمثلة الاستجابة

### Training Resource

```json
{
    "success": true,
    "data": {
        "training_id": 1,
        "company_id": 36,
        "department_id": 1,
        "department_name": "قسم تقنية المعلومات",
        "employee_ids": [37, 38, 39],
        "training_type_id": 1,
        "training_type_name": "تطوير البرمجيات",
        "trainer_id": 1,
        "trainer_name": "محمد أحمد",
        "start_date": "2026-01-15",
        "finish_date": "2026-01-20",
        "training_cost": 1500.00,
        "training_status": 0,
        "status_label": "قيد الانتظار",
        "description": "دورة تدريبية في تطوير PHP",
        "performance": 0,
        "performance_label": "غير منتهى",
        "associated_goals": "تطوير مهارات البرمجة",
        "remarks": null,
        "created_at": "2026-01-10T10:00:00Z"
    }
}
```

### Statistics Response

```json
{
    "success": true,
    "data": {
        "total": 50,
        "pending": 10,
        "started": 15,
        "completed": 20,
        "rejected": 5
    }
}
```

---

## Important Notes - ملاحظات هامة

1. **Multiple Employees:** Training can include multiple employees (comma-separated in database)

2. **Training Skills from Constants:** Training types/skills are stored in `ci_erp_constants` table with `type = 'training_type'`

3. **Global Skills:** Skills with `company_id = 0` are global and visible to all companies

4. **Status & Performance Update:** Use PATCH `/api/trainings/{id}/status` to update both status and performance in one request

5. **Swagger Documentation:** All endpoints are documented with OpenAPI/Swagger at `/api/documentation`

---

**Developed by:** FirstSoft Development Team
