@echo off
echo Testing GET specific leave application (ID: 105)
curl -X GET "http://127.0.0.1:8000/api/leaves/applications/105" ^
-H "Accept: application/json" ^
-H "Authorization: Bearer 21|XHRMWoJw1xKvsFOkrCG4frB9oqPmRyI07TEJeCem066da532"
