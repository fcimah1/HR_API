<!DOCTYPE html>
<html dir="rtl">

<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            text-align: center;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: #F44336;
            color: white;
            padding: 15px;
        }

        .content {
            padding: 20px;
            background: #f9f9f9;
            text-align: center;
        }

        p {
            text-align: center;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h2 style="text-align: center;">تم رفض طلب السلفة</h2>
        </div>
        <div class="content">
            <p><strong>الموظف:</strong> {{ $employeeName }}</p>
            <p><strong>التاريخ:</strong> {{ $requestDate }}</p>
            <p><strong>المبلغ:</strong> {{ $amount }}</p>
            <p><strong>سبب الرفض:</strong> {{ $reason }}</p>
            <p>نأسف لإبلاغك بأنه تم رفض طلبك.</p>
        </div>
    </div>
</body>

</html>