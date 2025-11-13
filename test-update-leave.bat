@echo off
echo Testing UPDATE leave application (ID: 104)
curl -X PUT "http://127.0.0.1:8000/api/leaves/applications/104" ^
-H "Content-Type: application/json" ^
-H "Accept: application/json" ^
-H "Authorization: Bearer 21|XHRMWoJw1xKvsFOkrCG4frB9oqPmRyI07TEJeCem066da532" ^
-d "{\"from_date\":\"2025-12-05\",\"to_date\":\"2025-12-10\",\"reason\":\"تم تحديث سبب الإجازة - اختبار التعديل\",\"remarks\":\"ملاحظات محدثة\"}"
