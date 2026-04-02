# Arabic Frontend Implementation Example

## 🎯 Goal
Show Arabic labels in dropdown, but send English enum names to API.

---

## 📋 Complete HTML + JavaScript Example

```html
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>طلب عمل إضافي</title>
    <style>
        body { font-family: 'Arial', sans-serif; padding: 20px; direction: rtl; }
        label { display: block; margin-top: 15px; font-weight: bold; }
        select, input { width: 100%; padding: 10px; margin-top: 5px; font-size: 14px; }
        button { margin-top: 20px; padding: 12px 30px; background: #4CAF50; color: white; border: none; cursor: pointer; }
        .response { margin-top: 20px; padding: 15px; background: #f0f0f0; direction: ltr; }
    </style>
</head>
<body>
    <h1>نموذج طلب عمل إضافي</h1>
    
    <form id="overtimeForm">
        <label for="overtime_reason">سبب العمل الإضافي *</label>
        <select id="overtime_reason" name="overtime_reason" required>
            <option value="">اختر السبب...</option>
        </select>

        <label for="compensation_type">طريقة الدفع *</label>
        <select id="compensation_type" name="compensation_type" required>
            <option value="">اختر طريقة الدفع...</option>
        </select>

        <label for="request_date">تاريخ الطلب *</label>
        <input type="date" id="request_date" name="request_date" required>

        <label for="clock_in">وقت البداية *</label>
        <input type="time" id="clock_in" name="clock_in" required>

        <label for="clock_out">وقت النهاية *</label>
        <input type="time" id="clock_out" name="clock_out" required>

        <label for="request_reason">سبب الطلب</label>
        <input type="text" id="request_reason" name="request_reason" placeholder="اختياري">

        <button type="submit">إرسال الطلب</button>
    </form>

    <div id="response" class="response" style="display: none;"></div>

    <script>
        const API_BASE = 'http://localhost:8000/api';
        const TOKEN = 'YOUR_AUTH_TOKEN_HERE'; // Replace with actual token

        // 1️⃣ Load Enums on Page Load
        async function loadEnums() {
            try {
                // Fetch overtime reasons
                const reasonsRes = await fetch(`${API_BASE}/enums/overtime-reasons`, {
                    headers: { 'Authorization': `Bearer ${TOKEN}` }
                });
                const reasonsData = await reasonsRes.json();

                // Fetch compensation types
                const compensationRes = await fetch(`${API_BASE}/enums/compensation-types`, {
                    headers: { 'Authorization': `Bearer ${TOKEN}` }
                });
                const compensationData = await compensationRes.json();

                // Populate overtime reasons dropdown
                const reasonSelect = document.getElementById('overtime_reason');
                reasonsData.data.forEach(reason => {
                    const option = document.createElement('option');
                    option.value = reason.name;          // English (for API)
                    option.textContent = reason.label_ar; // Arabic (for display)
                    reasonSelect.appendChild(option);
                });

                // Populate compensation types dropdown
                const compensationSelect = document.getElementById('compensation_type');
                compensationData.data.forEach(type => {
                    const option = document.createElement('option');
                    option.value = type.name;          // English (for API)
                    option.textContent = type.label_ar; // Arabic (for display)
                    compensationSelect.appendChild(option);
                });

                console.log('✅ Enums loaded successfully');
            } catch (error) {
                console.error('❌ Error loading enums:', error);
                alert('فشل في تحميل البيانات');
            }
        }

        // 2️⃣ Convert time to 12-hour format (API expects "2:30 PM")
        function convertTo12Hour(time24) {
            const [hours, minutes] = time24.split(':');
            const hour = parseInt(hours);
            const ampm = hour >= 12 ? 'PM' : 'AM';
            const hour12 = hour % 12 || 12;
            return `${hour12}:${minutes} ${ampm}`;
        }

        // 3️⃣ Handle Form Submit
        document.getElementById('overtimeForm').addEventListener('submit', async (e) => {
            e.preventDefault();

            const formData = new FormData(e.target);
            
            // Build request body
            const requestBody = {
                overtime_reason: formData.get('overtime_reason'),      // English name
                compensation_type: formData.get('compensation_type'),  // English name
                request_date: formData.get('request_date'),
                clock_in: convertTo12Hour(formData.get('clock_in')),
                clock_out: convertTo12Hour(formData.get('clock_out')),
                request_reason: formData.get('request_reason') || null
            };

            console.log('📤 Sending request:', requestBody);

            try {
                const response = await fetch(`${API_BASE}/overtime/requests`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${TOKEN}`
                    },
                    body: JSON.stringify(requestBody)
                });

                const result = await response.json();

                // Display response
                const responseDiv = document.getElementById('response');
                responseDiv.style.display = 'block';
                
                if (result.success) {
                    responseDiv.innerHTML = `
                        <h3 style="color: green;">✅ ${result.message}</h3>
                        <pre>${JSON.stringify(result.data, null, 2)}</pre>
                    `;
                    
                    // Show Arabic labels
                    console.log('📥 Response in Arabic:');
                    console.log('سبب العمل:', result.data.overtime_reason_label_ar);
                    console.log('طريقة الدفع:', result.data.compensation_type_label_ar);
                } else {
                    responseDiv.innerHTML = `
                        <h3 style="color: red;">❌ خطأ</h3>
                        <pre>${JSON.stringify(result, null, 2)}</pre>
                    `;
                }
            } catch (error) {
                console.error('❌ Error:', error);
                alert('فشل في إرسال الطلب');
            }
        });

        // Load enums when page loads
        loadEnums();
    </script>
</body>
</html>
```

---

## 🧪 What Happens Step by Step

### Step 1: Page Loads
```
GET /api/enums/overtime-reasons
GET /api/enums/compensation-types
```

Dropdown is populated with **Arabic labels**:
```
بدل عمل اضافي (مبلغ)
العمل وقت الاستراحة
تعيين مهمة عمل خارج المدينة
براتب إضافي
ساعات عمل إضافية
```

### Step 2: User Selects (Sees Arabic)
User clicks: **"بدل عمل اضافي (مبلغ)"**

### Step 3: Form Submits (Sends English)
```json
POST /api/overtime/requests
{
  "overtime_reason": "STANDBY_PAY",
  "compensation_type": "BANKED",
  "request_date": "2025-12-03",
  "clock_in": "9:00 AM",
  "clock_out": "5:00 PM"
}
```

### Step 4: API Responds (Both Languages)
```json
{
  "success": true,
  "data": {
    "overtime_reason": "STANDBY_PAY",
    "overtime_reason_label": "Standby Pay",
    "overtime_reason_label_ar": "بدل عمل اضافي (مبلغ)",
    "compensation_type": "BANKED",
    "compensation_type_label": "Banked",
    "compensation_type_label_ar": "بنكي"
  }
}
```

### Step 5: Display to User (In Arabic)
```javascript
// Show Arabic to user
document.getElementById('result').textContent = 
    result.data.overtime_reason_label_ar;  // "بدل عمل اضافي (مبلغ)"
```

---

## 🎯 Summary

| What | Language | Purpose |
|------|----------|---------|
| **Dropdown Display** | Arabic | User sees Arabic |
| **Option Value** | English | Sent to API |
| **API Request** | English | Stable identifier |
| **API Response** | Both | Flexibility |
| **Final Display** | Arabic | Use `label_ar` |

---

## ✅ Benefits

1. **User sees Arabic everywhere** - Natural UX
2. **API uses English internally** - Stable, debuggable
3. **No encoding issues** - English in database/logs
4. **Easy to add more languages** - Just add `label_fr`, `label_es`
5. **Industry standard** - Same pattern as Google, Facebook, Twitter

---

## 🚀 Ready to Test!

1. Replace `YOUR_AUTH_TOKEN_HERE` with a real token
2. Open the HTML file in a browser
3. See Arabic dropdown
4. Submit form
5. Check Network tab - you'll see English names sent to API
6. API responds with both English and Arabic labels


