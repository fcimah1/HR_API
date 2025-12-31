<!DOCTYPE html>
<html dir="rtl" lang="ar">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تمت الموافقة على طلب السفر</title>
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
            <h1>✓ تمت الموافقة على طلب السفر</h1>
        </div>
        <div class="content">
            <p>عزيزي/عزيزتي <strong>{{ $employeeName }}</strong>,</p>

            <div class="success-message">
                <strong>مبروك!</strong> تمت الموافقة على طلب سفرك.
            </div>

            <p>تفاصيل السفر:</p>

            <div class="info-row">
                <div class="label">الوجهة:</div>
                <div class="value">{{ $destination }}</div>
            </div>

            <div class="info-row">
                <div class="label">تاريخ البدء:</div>
                <div class="value">{{ $startDate }}</div>
            </div>

            <div class="info-row">
                <div class="label">تاريخ الانتهاء:</div>
                <div class="value">{{ $endDate }}</div>
            </div>

            @if(isset($allowanceAmount) && $allowanceAmount)
            <div class="info-row">
                <div class="label">إجمالي بدل السفر:</div>
                <div class="value">{{ number_format($allowanceAmount, 2) }} {{ $currency ?? '' }}</div>
            </div>
            @else
            <div class="info-row">
                <div class="label">إجمالي بدل السفر:</div>
                <div class="value">غير محدد</div>
            </div>
            @endif

            @if($remarks)
            <div class="info-row">
                <div class="label">ملاحظات:</div>
                <div class="value">{{ $remarks }}</div>
            </div>
            @endif

            <p style="margin-top: 20px; color: #666;">
                نتمنى لك رحلة آمنة وموفقة.
            </p>
        </div>
        <div class="footer">
            <p>هذه رسالة تلقائية، الرجاء عدم الرد عليها.</p>
            <p>&copy; {{ date('Y') }} نظام إدارة الموارد البشرية</p>
        </div>
    </div>
</body>

</html>