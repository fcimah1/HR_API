<!DOCTYPE html>
<html dir="rtl" lang="ar">

<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'تقرير')</title>
    <style>
        * {
            font-family: 'dejavusans', sans-serif;
            direction: rtl;
            text-align: right;
        }

        body {
            margin: 0;
            padding: 0;
            font-size: 10px;
            line-height: 1.5;
        }

        .container {
            width: 100%;
            padding: 10px;
        }

        .header {
            text-align: center;
            padding-bottom: 10px;
            border-bottom: 2px solid #333;
            margin-bottom: 15px;
        }

        .company-logo {
            max-height: 50px;
            margin-bottom: 5px;
        }

        .company-name {
            font-size: 16px;
            font-weight: bold;
            margin: 5px 0;
        }

        .report-title {
            font-size: 14px;
            font-weight: bold;
            margin: 10px 0;
        }

        .print-date {
            font-size: 9px;
            color: #666;
        }

        .content {
            width: 100%;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        th {
            background-color: #4a5568;
            color: #ffffff;
            padding: 8px 5px;
            text-align: center;
            font-weight: bold;
            font-size: 9px;
        }

        td {
            padding: 6px 5px;
            text-align: center;
            border: 1px solid #e2e8f0;
            font-size: 9px;
        }

        tr:nth-child(even) {
            background-color: #f7fafc;
        }

        tr:nth-child(odd) {
            background-color: #ffffff;
        }

        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 8px;
            color: #666;
            padding: 5px;
            border-top: 1px solid #e2e8f0;
        }

        .page-number {
            font-size: 8px;
        }

        .summary-box {
            background-color: #f0f4f8;
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
        }

        .summary-item {
            display: inline-block;
            margin: 0 15px;
        }

        .label {
            font-weight: bold;
            color: #4a5568;
        }

        .value {
            font-weight: bold;
            color: #2b6cb0;
        }

        .status-present {
            color: #38a169;
            font-weight: bold;
        }

        .status-absent {
            color: #e53e3e;
            font-weight: bold;
        }

        .status-late {
            color: #dd6b20;
            font-weight: bold;
        }

        .status-pending {
            color: #d69e2e;
            font-weight: bold;
        }

        .status-approved {
            color: #38a169;
            font-weight: bold;
        }

        .status-rejected {
            color: #e53e3e;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <div class="container">
        @include('reports.partials.header')

        <div class="content">
            @yield('content')
        </div>

        @include('reports.partials.footer')
    </div>
</body>

</html>