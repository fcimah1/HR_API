@echo off
echo Testing status=false (pending applications)
curl -X GET "http://127.0.0.1:8000/api/leaves/applications?status=false" ^
-H "Accept: application/json" ^
-H "Authorization: Bearer 21|XHRMWoJw1xKvsFOkrCG4frB9oqPmRyI07TEJeCem066da532"

echo.
echo.
echo ================================
echo Testing status=true (approved applications)
curl -X GET "http://127.0.0.1:8000/api/leaves/applications?status=true" ^
-H "Accept: application/json" ^
-H "Authorization: Bearer 21|XHRMWoJw1xKvsFOkrCG4frB9oqPmRyI07TEJeCem066da532"

echo.
echo.
echo ================================
echo Testing without status filter (all applications)
curl -X GET "http://127.0.0.1:8000/api/leaves/applications" ^
-H "Accept: application/json" ^
-H "Authorization: Bearer 21|XHRMWoJw1xKvsFOkrCG4frB9oqPmRyI07TEJeCem066da532"
