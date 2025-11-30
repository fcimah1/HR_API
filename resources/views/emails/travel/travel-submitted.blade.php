<!DOCTYPE html>
<html dir="rtl" lang="ar">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>طلب سفر جديد</title>
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
        }

        .info-row {
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }

        .label {
            font-weight: bold;
            color: #555;
        }

        .value {
            color: #333;
            margin-top: 5px;
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
            <h1>طلب سفر جديد</h1>
        </div>
        <div class="content">
            <p>عزيزي/عزيزتي <strong>{{ $employeeName }}</strong>,</p>
            <p>تم تقديم طلب سفرك بنجاح. فيما يلي تفاصيل الطلب:</p>

            <div class="info-row">
                <div class="label">الوجهة:</div>
                <div class="value">{{ $destination }}</div>
            </div>

            <div class="info-row">
                <div class="label">الغرض من السفر:</div>
                <div class="value">{{ $purpose }}</div>
            </div>

            <div class="info-row">
                <div class="label">تاريخ البدء:</div>
                <div class="value">{{ $startDate }}</div>
            </div>

            <div class="info-row">
                <div class="label">تاريخ الانتهاء:</div>
                <div class="value">{{ $endDate }}</div>
            </div>

            <p style="margin-top: 20px; color: #666;">
                سيتم مراجعة طلبك وإعلامك بالقرار في أقرب وقت ممكن.
            </p>
        </div>
        <div class="footer">
            <p>هذه رسالة تلقائية، الرجاء عدم الرد عليها.</p>
            <p>&copy; {{ date('Y') }} نظام إدارة الموارد البشرية</p>
        </div>
    </div>
</body>

</html>