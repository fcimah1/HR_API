@echo off
echo Testing CANCEL pending leave application (should succeed)
curl -X DELETE "http://127.0.0.1:8000/api/leaves/applications/104/cancel" ^
-H "Accept: application/json" ^
-H "Authorization: Bearer 21|XHRMWoJw1xKvsFOkrCG4frB9oqPmRyI07TEJeCem066da532"
