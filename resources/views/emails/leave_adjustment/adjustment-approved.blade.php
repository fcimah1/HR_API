<!DOCTYPE html>
<html dir="rtl" lang="ar">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تمت الموافقة على تسوية الإجازة</title>
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
            background-color: #4CAF50;
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

        .success-message {
            background-color: #e8f5e9;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            border-right: 4px solid #4CAF50;
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
            <h1>✓ تمت الموافقة على تسوية الإجازة</h1>
        </div>
        <div class="content">
            <p>عزيزي/عزيزتي <strong>{{ $employeeName }}</strong>,</p>

            <div class="success-message">
                <strong>مبروك!</strong> تمت الموافقة على طلب تسوية إجازتك.
            </div>

            <p>تفاصيل التسوية:</p>

            <div class="info-row">
                <div class="label">نوع الإجازة:</div>
                <div class="value">{{ $leaveType }}</div>
            </div>

            <div class="info-row">
                <div class="label">عدد الساعات:</div>
                <div class="value">{{ $adjustHours > 0 ? '+' . $adjustHours : $adjustHours }} ساعة</div>
            </div>

            @if($remarks)
            <div class="info-row">
                <div class="label">ملاحظات:</div>
                <div class="value">{{ $remarks }}</div>
            </div>
            @endif

            <p style="margin-top: 20px; color: #666;">
                تم تحديث رصيد إجازاتك وفقاً لذلك.
            </p>
        </div>
        <div class="footer">
            <p>هذه رسالة تلقائية، الرجاء عدم الرد عليها.</p>
            <p>&copy; {{ date('Y') }} نظام إدارة الموارد البشرية</p>
        </div>
    </div>
</body>

</html>