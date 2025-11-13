@echo off
echo Testing CANCEL approved leave application (should fail)
echo Trying to cancel leave ID 79 which is already approved
curl -X DELETE "http://127.0.0.1:8000/api/leaves/applications/79/cancel" ^
-H "Accept: application/json" ^
-H "Authorization: Bearer 21|XHRMWoJw1xKvsFOkrCG4frB9oqPmRyI07TEJeCem066da532"
