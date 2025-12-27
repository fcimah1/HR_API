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
            <h2 style="text-align: center;">تم حل الشكوى</h2>
        </div>
        <div class="content">
            <p><strong>الموظف:</strong> {{ $employeeName }}</p>
            <p><strong>نوع الشكوى:</strong> {{ $complaintType }}</p>
            <p><strong>الموضوع:</strong> {{ $complaintSubject }}</p>
            <p><strong>الحل:</strong> {{ $resolution }}</p>
            @if($remarks)
            <p><strong>ملاحظات:</strong> {{ $remarks }}</p>
            @endif
            <p>تم حل شكواك بنجاح.</p>
        </div>
    </div>
</body>

</html>