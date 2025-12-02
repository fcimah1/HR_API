<!DOCTYPE html>
<html dir="rtl" lang="ar">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تم رفض تسوية الإجازة</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 20px;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .header {
            background-color: #f44336;
            color: white;
            padding: 20px;
            text-align: center;
        }

        .content {
            padding: 30px;
            direction: rtl;
            text-align: right;
        }

        .info-row {
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
            direction: rtl;
            text-align: right;
        }

        .label {
            font-weight: bold;
            color: #555;
        }

        .value {
            color: #333;
            margin-top: 5px;
        }

        .error-message {
            background-color: #ffebee;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            border-right: 4px solid #f44336;
        }

        .footer {
            background-color: #f9f9f9;
            padding: 20px;
            text-align: center;
            color: #777;
            font-size: 12px;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>تم رفض تسوية الإجازة</h1>
        </div>
        <div class="content">
            <p>عزيزي/عزيزتي <strong>{{ $employeeName }}</strong>,</p>

            <div class="error-message">
                للأسف، تم رفض طلب تسوية إجازتك.
            </div>

            <p>تفاصيل الطلب:</p>

            <div class="info-row">
                <div class="label">نوع الإجازة:</div>
                <div class="value">{{ $leaveType }}</div>
            </div>

            <div class="info-row">
                <div class="label">عدد الساعات:</div>
                <div class="value">{{ $adjustHours > 0 ? '+' . $adjustHours : $adjustHours }} ساعة</div>
            </div>

            <div class="info-row">
                <div class="label">سبب الرفض:</div>
                <div class="value">{{ $reason }}</div>
            </div>

            <p style="margin-top: 20px; color: #666;">
                للاستفسار، يرجى التواصل مع قسم الموارد البشرية.
            </p>
        </div>
        <div class="footer">
            <p>هذه رسالة تلقائية، الرجاء عدم الرد عليها.</p>
            <p>&copy; {{ date('Y') }} نظام إدارة الموارد البشرية</p>
        </div>
    </div>
</body>

</html>