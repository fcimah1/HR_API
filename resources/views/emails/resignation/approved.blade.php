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
            background: #4CAF50;
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
            <h2 style="text-align: center;">تمت الموافقة على طلب الاستقالة</h2>
        </div>
        <div class="content">
            <p><strong>الموظف:</strong> {{ $employeeName }}</p>
            <p><strong>تاريخ تقديم الاستقالة:</strong> {{ $resignationDate }}</p>
            <p><strong>آخر يوم عمل:</strong> {{ $lastWorkingDay }}</p>
            @if($remarks)
            <p><strong>ملاحظات:</strong> {{ $remarks }}</p>
            @endif
            <p>تمت الموافقة على طلب الاستقالة الخاص بك.</p>
        </div>
    </div>
</body>

</html>