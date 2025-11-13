@echo off
curl -X POST "http://127.0.0.1:8000/api/leaves/applications" ^
-H "Content-Type: application/json" ^
-H "Accept: application/json" ^
-H "Authorization: Bearer 21|XHRMWoJw1xKvsFOkrCG4frB9oqPmRyI07TEJeCem066da532" ^
-d "{\"leave_type_id\":311,\"from_date\":\"2025-11-15\",\"to_date\":\"2025-11-17\",\"reason\":\"إجازة سنوية للراحة والاستجمام\",\"duty_employee_id\":37,\"is_half_day\":false,\"leave_hours\":\"8\",\"remarks\":\"ملاحظات إضافية\"}"
