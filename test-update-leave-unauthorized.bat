@echo off
echo Testing UPDATE leave application that belongs to another employee (should fail)
echo Trying to update leave ID 79 which belongs to employee 36, but we'll use same token
curl -X PUT "http://127.0.0.1:8000/api/leaves/applications/79" ^
-H "Content-Type: application/json" ^
-H "Accept: application/json" ^
-H "Authorization: Bearer 21|XHRMWoJw1xKvsFOkrCG4frB9oqPmRyI07TEJeCem066da532" ^
-d "{\"from_date\":\"2025-12-05\",\"to_date\":\"2025-12-10\",\"reason\":\"محاولة تعديل طلب موظف آخر\",\"remarks\":\"يجب أن تفشل\"}"
