<!DOCTYPE html>
<html dir="rtl">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; text-align: center; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px;  }
        .header { background: yellow; color: white; padding: 15px; }
        .content { padding: 20px; background: #f9f9f9; text-align: center; }
        p { text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2 style="text-align: center;">تم تحديث طلب الإجازة </h2>
        </div>
        <div class="content">
            <p><strong>الموظف:</strong> {{ $employeeName }}</p>
            <p><strong>نوع الإجازة:</strong> {{ $leaveType }}</p>
            <p><strong>من:</strong> {{ $startDate }}</p>
            <p><strong>إلى:</strong> {{ $endDate }}</p>
            <p>تم تحديث طلب الإجازة بنجاح.</p>
        </div>
    </div>
</body>
</html>