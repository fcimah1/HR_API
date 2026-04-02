<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\Branch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Enums\NumericalStatusEnum;
use App\Traits\ReportHelperTrait;

/**
 * خدمة تصدير التقارير (PDF/Excel)
 * Report Export Service
 */
class ReportExportService
{
    use ReportHelperTrait;

    public function __construct(
        protected PdfGeneratorService $pdfGenerator
    ) {}

    
    // ==========================================
    // PDF Generation Methods
    // ==========================================

    /**
     * توليد PDF للحضور
     */
    // Completed
    public function generateAttendancePdf(Collection $data, string $title, int $companyId, string $type): void
    {

        $headers = ['الاسم', 'يوم', 'التاريخ', 'اسم الوردية', 'الحاله', 'وقت الدخول', 'وقت الخروج', 'التأخير', 'إجمالي العمل', 'موقع الدخول', 'موقع الخروج'];
        $rows = [];

        foreach ($data as $record) {
            // Safely check properties. clock_in/out might be null. first/last might be undefined.
            $firstIn = $record->clock_in ?? ($record->first_clock_in ?? null);
            $lastOut = $record->clock_out ?? ($record->last_clock_out ?? null);
            $totalWork = $record->total_work ?? '-';
            $delay = '-';

            // Employee Name & Shift
            $employeeName = '-';
            $emp = $record->employee ?? null;

            if ($emp) {
                if (is_array($emp)) {
                    $employeeName = ($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? '');
                } elseif (is_object($emp)) {
                    if (isset($emp->full_name)) {
                        $employeeName = $emp->full_name;
                    } else {
                        $employeeName = ($emp->first_name ?? '') . ' ' . ($emp->last_name ?? '');
                    }
                }
            }

            // JOINED Shift Name (from Repo) OR Fallback
            $shiftName = $record->shift_name_joined ?? $record->default_shift_name ?? 'غير محدد';

            $date = $record->attendance_date;
            $time = strtotime($date);
            $dayName = $date ? date('l', $time) : '-';
            $dayOfWeekLower = strtolower($dayName); // e.g., 'monday'

            // Status Logic
            $dayOfWeek = date('w', $time);
            $status = '';
            if (!empty($firstIn) || !empty($lastOut)) {
                $status = 'حاضر';
            } else {
                if (!empty($record->attendance_status)) {
                    $translated = $this->translateAttendanceStatus($record->attendance_status);
                    if ($translated) {
                        $status = $translated;
                    }
                }
                if (($status === 'غائب' || empty($status)) && $dayOfWeek == 5) {
                    $status = 'يوم الاجازة';
                } elseif (empty($status) && $dayOfWeek != 5) {
                    $status = 'غائب';
                }
            }

            // Format Time (Time Only)
            $fmtIn = '-';
            $clockInTimestamp = null;
            if (!empty($firstIn)) {
                $clockInTimestamp = strtotime($firstIn);
                $fmtIn = date('h:i A', $clockInTimestamp);
            }
            // Strict Check for LastOut (ignore 00:00:00)
            $fmtOut = '-';
            $hasCheckout = false;
            if (!empty($lastOut) && $lastOut !== '00:00:00') {
                $lastOutTs = strtotime($lastOut);
                // Check if it really parsed (not false and not epoch if undesired)
                if ($lastOutTs && date('H:i', $lastOutTs) !== '00:00') {
                    $fmtOut = date('h:i A', $lastOutTs);
                    $hasCheckout = true;
                }
            }

            // Map Lateness (Calculated: ClockIn - ShiftStart)
            $delay = '-';
            // Only calculate if Present and ClockIn exists
            if ($clockInTimestamp) {
                // Get Shift Start for this day from record (joined or fallback)
                // Column format: "monday_in_time"
                $dayInField = $dayOfWeekLower . '_in_time';
                $defaultInField = 'default_' . $dayOfWeekLower . '_in';

                $shiftStartTimeStr = $record->$dayInField ?? $record->$defaultInField ?? null;

                if (!empty($shiftStartTimeStr)) {
                    // Combine Date + Shift Time
                    $shiftStartTimestamp = strtotime($date . ' ' . $shiftStartTimeStr);

                    if ($shiftStartTimestamp && $clockInTimestamp > $shiftStartTimestamp) {
                        $diffSeconds = $clockInTimestamp - $shiftStartTimestamp;
                        if ($diffSeconds > 120) { // Tolerance > 2 mins
                            $delay = gmdate('H:i', $diffSeconds);
                        }
                    }
                } else {
                    // Fallback to legacy time_late
                    $delayRaw = $record->time_late ?? null;
                    if (!empty($delayRaw) && $delayRaw != '00:00:00') {
                        $dTime = strtotime($delayRaw);
                        if ($dTime !== false) {
                            $delay = date('H:i', $dTime);
                        } else {
                            $delay = substr($delayRaw, 0, 5);
                        }
                    }
                }
            }

            // Map Total Work
            // User requested: "Total work should not be mentioned unless there is a check-out time"
            if (!$hasCheckout) {
                $totalWork = '-';
            }

            // Map Location - Inside/Outside Branch
            // We need branch coordinates from record (joined in repo)
            $branchCoords = $record->branch_coordinates ?? null;
            $branchLat = 0;
            $branchLong = 0;
            if ($branchCoords) {
                $parts = explode(',', $branchCoords);
                if (count($parts) == 2) {
                    $branchLat = (float)$parts[0];
                    $branchLong = (float)$parts[1];
                }
            }

            // Helper for location text
            $getLocationText = function ($lat, $long) use ($branchLat, $branchLong) {
                if (empty($lat) || empty($long)) return '';
                if (empty($branchLat) || empty($branchLong)) return 'خارج الفرع'; // Default if no branch coords

                // Calculate distance (threshold 200m)
                $dist = $this->calculateDistance((float)$lat, (float)$long, $branchLat, $branchLong);
                return ($dist <= 200) ? 'داخل الفرع' : 'خارج الفرع';
            };

            $inLat = $record->clock_in_latitude ?? null;
            $inLong = $record->clock_in_longitude ?? null;
            $locIn = $getLocationText($inLat, $inLong);

            // Fallback to IP if no lat/long? NO, user wants "Inside/Outside" text.
            // If we have IP but no Lat/Long, we can't determine "Inside". default to empty or generic?
            // Let's stick to Lat/Long check. If missing, empty.

            $outLat = $record->clock_out_latitude ?? null;
            $outLong = $record->clock_out_longitude ?? null;

            // Fix: If no check-out time, force location empty
            if (!$hasCheckout) {
                $locOut = '';
            } else {
                $locOut = $getLocationText($outLat, $outLong);
            }

            $rows[] = [
                $employeeName,
                $this->translateDayName($dayName),
                $date,
                $shiftName,
                $status,
                $fmtIn,
                $fmtOut,
                $delay,
                $totalWork,
                $locIn,
                $locOut,
            ];
        }

        // Style Options: Light Grey Header, Black Text, Black Border, Compact Padding
        // Column Order: الاسم | يوم | التاريخ | اسم الوردية | الحاله | وقت الدخول | وقت الخروج | التأخير | إجمالي العمل | موقع الدخول | موقع الخروج
        $styleOptions = [
            'headerBg' => '#f0f0f0',
            'headerColor' => '#000000',
            'borderColor' => '#000000',
            'fontSize' => '8px',
            'cellPadding' => '2',
            'columnWidths' => ['9%', '6%', '9%', '18%', '7%', '8%', '8%', '6%', '8%', '10%', '11%'], // Wider Shift column (18%)
        ];

        // Separation: Extract Date Range if present in title for display above table
        // Format passed was "Title (From: X To: Y)"
        $displayTitle = $title;
        $dateRangeText = '';
        if (preg_match('/^(.*)\s\((.*)\)$/', $title, $matches)) {
            $displayTitle = trim($matches[1]);
            $dateRangeText = trim($matches[2]);
        }

        $tableHtml = '';
        if ($dateRangeText) {
            $tableHtml .= '<div style="text-align: center; font-family: dejavusans; font-size: 10px; margin-bottom: 5px;">' . $dateRangeText . '</div>';
        }
        $tableHtml .= $this->pdfGenerator->createTable($headers, $rows, $styleOptions);

        $this->pdfGenerator
            ->initialize($companyId, $displayTitle, 'L')
            ->addPage($displayTitle) // Use clean title for Header
            ->writeHtml($tableHtml)
            ->download('attendance_report_' . date('Y-m-d') . '.pdf');
    }

    /**
     * توليد PDF لأول وآخر حضور
     */
    // Completed
    public function generateFirstLastPdf(Collection $data, string $title, int $companyId, ?string $dateRange = null): void
    {
        // Headers (without Shift Name and Status)
        // الاسم | يوم | التاريخ | وقت الدخول | وقت الخروج | إجمالي العمل | موقع الدخول | موقع الخروج
        $headers = ['الاسم', 'يوم', 'التاريخ', 'وقت الدخول', 'وقت الخروج', 'إجمالي العمل', 'موقع الدخول', 'موقع الخروج'];
        $rows = [];

        foreach ($data as $record) {
            $firstIn = $record->first_clock_in ?? null;
            $lastOut = $record->last_clock_out ?? null;
            $totalWork = '-';
            $clockInTimestamp = null;

            if (!empty($firstIn)) {
                $clockInTimestamp = strtotime($firstIn);
            }

            // Calculate Total Work
            if ($firstIn && $lastOut && $lastOut !== '00:00:00') {
                try {
                    $start = \Carbon\Carbon::parse($firstIn);
                    $end = \Carbon\Carbon::parse($lastOut);
                    $totalWork = $end->diff($start)->format('%H:%I');
                } catch (\Exception $e) {
                    // Fallback
                }
            }

            $date = $record->attendance_date;
            $time = strtotime($date);
            $dayName = $date ? date('l', $time) : '-';

            // Employee Name
            $employeeName = '-';
            $emp = $record->employee ?? null;
            if ($emp) {
                if (is_array($emp)) {
                    $employeeName = ($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? '');
                } elseif (is_object($emp)) {
                    if (isset($emp->full_name)) {
                        $employeeName = $emp->full_name;
                    } else {
                        $employeeName = ($emp->first_name ?? '') . ' ' . ($emp->last_name ?? '');
                    }
                }
            }

            // Format Times
            $fmtIn = '-';
            if (!empty($firstIn)) {
                $fmtIn = date('h:i A', $clockInTimestamp);
            }
            $fmtOut = '-';
            $hasCheckout = false;
            if (!empty($lastOut) && $lastOut !== '00:00:00') {
                $lastOutTs = strtotime($lastOut);
                if ($lastOutTs && date('H:i', $lastOutTs) !== '00:00') {
                    $fmtOut = date('h:i A', $lastOutTs);
                    $hasCheckout = true;
                }
            }

            // Total Work (only if checkout)
            if (!$hasCheckout) {
                $totalWork = '-';
            }

            // Location Logic
            $branchCoords = $record->branch_coordinates ?? null;
            $branchLat = 0;
            $branchLong = 0;
            if ($branchCoords) {
                $parts = explode(',', $branchCoords);
                if (count($parts) == 2) {
                    $branchLat = (float)$parts[0];
                    $branchLong = (float)$parts[1];
                }
            }

            $getLocationText = function ($lat, $long) use ($branchLat, $branchLong) {
                if (empty($lat) || empty($long)) return '';
                if (empty($branchLat) || empty($branchLong)) return 'خارج الفرع';
                $dist = $this->calculateDistance((float)$lat, (float)$long, $branchLat, $branchLong);
                return ($dist <= 200) ? 'داخل الفرع' : 'خارج الفرع';
            };

            $inLat = $record->clock_in_latitude ?? null;
            $inLong = $record->clock_in_longitude ?? null;
            $locIn = $getLocationText($inLat, $inLong);

            $outLat = $record->clock_out_latitude ?? null;
            $outLong = $record->clock_out_longitude ?? null;
            $locOut = !$hasCheckout ? '' : $getLocationText($outLat, $outLong);

            $rows[] = [
                $employeeName,
                $dayName,
                $date,
                $fmtIn,
                $fmtOut,
                $totalWork,
                $locIn,
                $locOut,
            ];
        }

        // Style Options matching Monthly Report
        $options = [
            'headerBg' => '#f0f0f0',
            'headerColor' => '#000000',
            'borderColor' => '#000000',
            'fontSize' => 9,
            'cellPadding' => 2,
            // Adjusted column widths for 8 columns instead of 11
            'columnWidths' => ['14%', '8%', '10%', '12%', '12%', '10%', '17%', '17%'],
        ];
        $tableHtml = $this->pdfGenerator->createTable($headers, $rows, $options);

        $this->pdfGenerator
            ->initialize($companyId, $title, 'L')
            ->addPage($title);

        if ($dateRange) {
            $this->pdfGenerator->writeHtml('<div style="text-align: center; font-family: dejavusans; font-size: 10px; margin-bottom: 5px;">' . $dateRange . '</div>');
        }

        $this->pdfGenerator
            ->writeHtml($tableHtml)
            ->download('attendance_first_last_' . date('Y-m-d') . '.pdf');
    }

    /**
     * توليد PDF لسجلات الوقت
     */
    // Completed
    public function generateTimeRecordsPdf(Collection $data, string $title, int $companyId, ?User $employee = null, string $dateRange = ''): void
    {
        // Headers: يوم | التاريخ | الوقت | عدد السجلات
        $headers = ['يوم', 'التاريخ', 'الوقت', 'عدد السجلات'];

        // Group records by date
        $groupedByDate = $data->groupBy('attendance_date');

        $rows = [];
        foreach ($groupedByDate as $date => $records) {
            $dayName = date('l', strtotime($date));

            // Concatenate all clock times for this date
            $times = [];
            foreach ($records as $record) {
                if (!empty($record->clock_in)) {
                    $times[] = date('h:i A', strtotime($record->clock_in));
                }
                if (!empty($record->clock_out) && $record->clock_out !== '00:00:00') {
                    $times[] = date('h:i A', strtotime($record->clock_out));
                }
            }
            $timeStr = implode(', ', $times);

            // Skip if no records
            if (empty($times)) {
                continue;
            }

            $rows[] = [
                $dayName,
                $date,
                $timeStr,
                count($times),
            ];
        }

        // Sort by date ascending
        usort($rows, function ($a, $b) {
            return strtotime($a[1]) - strtotime($b[1]);
        });

        // Add row numbers
        $numberedRows = [];
        foreach ($rows as $index => $row) {
            array_unshift($row, $index + 1);
            $numberedRows[] = $row;
        }
        array_unshift($headers, 'عدد السجلات'); // Actually should be index
        $headers[0] = '#'; // Overwrite first header

        // Style Options
        $options = [
            'headerBg' => '#f0f0f0',
            'headerColor' => '#000000',
            'borderColor' => '#000000',
            'fontSize' => 9,
            'cellPadding' => 2,
            'columnWidths' => ['5%', '12%', '12%', '56%', '15%'],
        ];
        $tableHtml = $this->pdfGenerator->createTable($headers, $numberedRows, $options);

        // Employee Info Header
        $employeeInfoHtml = '';
        if ($employee) {
            $employeeName = ($employee->first_name ?? '') . ' ' . ($employee->last_name ?? '');
            $designation = $employee->details?->designation?->designation_name ?? '';
            $employeeInfoHtml = '<div style="text-align: right; font-family: dejavusans; font-size: 11px; margin-bottom: 10px;">';
            $employeeInfoHtml .= 'الموظف: <strong>' . $employeeName . '</strong>';
            if ($designation) {
                $employeeInfoHtml .= ' | ' . $designation;
            }
            $employeeInfoHtml .= '</div>';
        }

        $this->pdfGenerator
            ->initialize($companyId, $title, 'L')
            ->addPage($title);

        // Add Employee Info
        if ($employeeInfoHtml) {
            $this->pdfGenerator->writeHtml($employeeInfoHtml);
        }

        $this->pdfGenerator
            ->writeHtml($tableHtml)
            ->download('time_records_' . date('Y-m-d') . '.pdf');
    }

    /**
     * توليد PDF للحضور بنطاق زمني
     */
    // Completed
    public function generateDateRangePdf(Collection $data, string $title, int $companyId, ?User $employee = null, string $dateRange = ''): void
    {
        // Headers: التاريخ | وقت الدخول | وقت الخروج | إجمالي العمل | العمل من المنزل | موقع الدخول | موقع الخروج
        $headers = [
            'التاريخ',
            'وقت الدخول',
            'وقت الخروج',
            'إجمالي العمل',
            'العمل من المنزل',
            'موقع الدخول',
            'موقع الخروج',
        ];

        $rows = [];
        foreach ($data as $record) {
            $clockInTimestamp = strtotime($record->clock_in);
            $clockOutTimestamp = strtotime($record->clock_out);

            // Format Times
            $fmtIn = !empty($record->clock_in) ? date('h:i A', $clockInTimestamp) : '-';

            $fmtOut = '-';
            if (!empty($record->clock_out) && $record->clock_out !== '00:00:00') {
                $fmtOut = date('h:i A', $clockOutTimestamp);
            }

            // Location
            $locIn = !empty($record->clock_in) ? $this->getLocationText(
                $record->clock_in_latitude,
                $record->clock_in_longitude,
                $record->branch_coordinates
            ) : '-';

            $locOut = (!empty($record->clock_out) && $record->clock_out !== '00:00:00') ? $this->getLocationText(
                $record->clock_out_latitude,
                $record->clock_out_longitude,
                $record->branch_coordinates
            ) : '-';

            // WFH
            $wfh = $record->work_from_home ? 'نعم' : 'لا';

            $rows[] = [
                $record->attendance_date,
                $fmtIn,
                $fmtOut,
                $record->total_work ?? '00:00',
                $wfh,
                $locIn,
                $locOut,
            ];
        }

        // Add Employee/Date Info Header
        $employeeInfoHtml = '<div style="text-align: right; font-family: dejavusans; font-size: 11px; margin-bottom: 10px;">';
        if ($employee) {
            $employeeName = ($employee->first_name ?? '') . ' ' . ($employee->last_name ?? '');
            $designation = $employee->details?->designation?->designation_name ?? '';
            $employeeInfoHtml .= 'الموظف: <strong>' . $employeeName . '</strong>';
            if ($designation) {
                $employeeInfoHtml .= ' | ' . $designation;
            }
            $employeeInfoHtml .= '<br>';
        }
        if ($dateRange) {
            $employeeInfoHtml .= '<span dir="rtl">' . $dateRange . '</span>';
        }
        $employeeInfoHtml .= '</div>';

        // Style Options
        $options = [
            'headerBg' => '#f0f0f0',
            'headerColor' => '#000000',
            'borderColor' => '#000000',
            'fontSize' => 9,
            'cellPadding' => 2,
            'columnWidths' => ['18%', '18%', '10%', '10%', '12%', '12%', '20%'],
        ];

        $tableHtml = $this->pdfGenerator->createTable($headers, $rows, $options);

        $this->pdfGenerator
            ->initialize($companyId, $title, 'L')
            ->addPage($title);

        if ($employeeInfoHtml) {
            $this->pdfGenerator->writeHtml($employeeInfoHtml);
        }

        $this->pdfGenerator
            ->writeHtml($tableHtml)
            ->download('attendance_range_' . date('Y-m-d') . '.pdf');
    }


    /**
     * توليد PDF لإنهاء الخدمة
     */
    // Completed
    public function generateTerminationsPdf(Collection $data, string $title, int $companyId, string $dateRange = '', string $statusText = ''): void
    {
        $headers = [
            'الموظف',
            'السبب',
            'تاريخ الإشعار',
            'تاريخ إنهاء الخدمة',
            'الحالة',
        ];

        $rows = [];

        foreach ($data as $record) {
            $statusEnum = NumericalStatusEnum::tryFrom((int)$record->status);
            $recordStatusText = $statusEnum?->labelAr() ?? '-';

            $rows[] = [
                $record->employee?->full_name ?? '-',
                mb_substr($record->reason ?? '-', 0, 50),
                $record->notice_date ?? '-',
                $record->termination_date ?? '-',
                $recordStatusText,
            ];
        }

        // Initialize PDF
        $this->pdfGenerator->initialize($companyId, $title, 'L');

        $infoHtml = '<div style="text-align: right; font-weight: semi-bold; font-family: dejavusans; font-size: 10px; margin-bottom: 5px;">';
        if ($dateRange) {
            $infoHtml .= '<span>' . $dateRange . '</span>';
            if ($statusText) {
                $infoHtml .= ' | ';
            }
        }
        if ($statusText) {
            $infoHtml .= '<span>' . $statusText . '</span>';
        }
        $infoHtml .= '</div>';

        // Custom Table Construction to match styling
        $tableHtml = '<table border="1" cellpadding="3" cellspacing="0" style="width: 100%; font-size: 8px; font-family: dejavusans; direction: rtl; border-collapse: collapse; text-align: center;">';

        // Headers
        $tableHtml .= '<thead><tr style="background-color: #f0f0f0;">';
        foreach ($headers as $header) {
            $tableHtml .= '<td style="border: 1px solid #000; padding: 5px;">' . $header . '</td>';
        }
        $tableHtml .= '</tr></thead><tbody>';

        // Data
        foreach ($rows as $row) {
            $tableHtml .= '<tr>';
            foreach ($row as $cell) {
                $tableHtml .= '<td style="border: 1px solid #000; padding: 5px;">' . $cell . '</td>';
            }
            $tableHtml .= '</tr>';
        }

        $tableHtml .= '</tbody></table>';

        $this->pdfGenerator
            ->addPage($title)
            ->writeHtml($infoHtml)
            ->writeHtml($tableHtml)
            ->download('termination_report_' . date('Y-m-d') . '.pdf');
    }



    /**
     * توليد PDF لسجل الدوام
     */
    // Completed
    public function generateTimesheetPdf(Collection $data, string $title, int $companyId, string $dateRange = ''): void
    {
        // Fetch Leave Types
        $leaveTypes = DB::table('ci_erp_constants')
            ->where('company_id', $companyId)
            ->where('type', 'leave_type')
            ->orderBy('constants_id', 'ASC')
            ->get();

        // Headers (RTL: Index 0 is Rightmost)
        // 1. Employee, 2. Regular Hours, 3. Straight, 4. Time a half, 5. Double
        // 6..N Leave Types
        // N+1 Holidays, N+2 Standby, N+3 Work Lunch, N+4 Out of Town, N+5 Salaried, N+6 Unpaid
        // N+7 Total Hours (Leftmost)

        $headers = [
            'الموظف',
            'ساعات العمل',
            'العمل الاضافي الدائم', // Straight
            'وقت العمل الاضافي', // Time a half
            'المضاعفه', // Double
        ];

        foreach ($leaveTypes as $type) {
            $headers[] = $type->category_name;
        }

        $headers = array_merge($headers, [
            'Holidays',
            'بدل عمل اضافي (ساعات)', // Standby
            'العمل وقت الاستراحة', // Work Lunch
            'مهمة عمل خارج المدينه', // Out of Town
            'براتب إضافي', // Salaried
            'إجازة غير مدفوعة الأجر', // Unpaid
            'إجمالي الساعات', // Total Hours
        ]);

        // Group by Employee (Removed - Now Iterating Users directly)
        //$groupedByEmployee = $data->groupBy('employee_id');
        $rows = [];

        foreach ($data as $employee) {
            $records = $employee->attendances ?? collect([]);
            // $firstRecord = $records->first(); // No longer needed for employee info
            // $employee = $firstRecord->employee; // Already have employee object

            // Initialize Sums
            $totalWorkSeconds = 0;
            $straightSum = 0;
            $timeAHalfSum = 0;
            $doubleSum = 0;
            $holidaysSum = 0;
            $standbySum = 0;
            $workLunchSum = 0;
            $outOfTownSum = 0;
            $salariedSum = 0;
            $unpaidSum = 0;

            $leaveTypeSums = [];
            foreach ($leaveTypes as $type) {
                $leaveTypeSums[$type->constants_id] = 0;
            }

            foreach ($records as $record) {
                // Sum Total Work
                $totalWorkSeconds += $this->parseDurationToSeconds($record->total_work);

                // Sum Fixed Columns (assuming H:i format in DB like total_work)
                $straightSum += $this->parseDurationToSeconds($record->getAttribute('straight'));
                $timeAHalfSum += $this->parseDurationToSeconds($record->getAttribute('time_a_half'));
                $doubleSum += $this->parseDurationToSeconds($record->getAttribute('double_overtime'));
                $holidaysSum += $this->parseDurationToSeconds($record->getAttribute('holidays'));
                $standbySum += $this->parseDurationToSeconds($record->getAttribute('standby_hrs'));
                $workLunchSum += $this->parseDurationToSeconds($record->getAttribute('work_lunch'));
                $outOfTownSum += $this->parseDurationToSeconds($record->getAttribute('out_of_town'));
                $salariedSum += $this->parseDurationToSeconds($record->getAttribute('salaried_emp'));
                $unpaidSum += $this->parseDurationToSeconds($record->getAttribute('unpaid_leave'));

                // Sum Dynamic Leave Types
                foreach ($leaveTypes as $type) {
                    // Check if column exists or attribute available
                    // Legacy used: $timesheet[$constants_id]
                    $val = $record->getAttribute((string)$type->constants_id);
                    if ($val) {
                        $leaveTypeSums[$type->constants_id] += $this->parseDurationToSeconds($val);
                    }
                }
            }

            // Format Seconds to H:i
            $row = [
                $employee?->full_name ?? '-',
                $this->formatSecondsToTime($totalWorkSeconds), // Regular Hours
                $this->formatSecondsToTime($straightSum),
                $this->formatSecondsToTime($timeAHalfSum),
                $this->formatSecondsToTime($doubleSum),
            ];

            foreach ($leaveTypes as $type) {
                $row[] = $this->formatSecondsToTime($leaveTypeSums[$type->constants_id]);
            }

            $row = array_merge($row, [
                $this->formatSecondsToTime($holidaysSum),
                $this->formatSecondsToTime($standbySum),
                $this->formatSecondsToTime($workLunchSum),
                $this->formatSecondsToTime($outOfTownSum),
                $this->formatSecondsToTime($salariedSum),
                $this->formatSecondsToTime($unpaidSum),
                $this->formatSecondsToTime($totalWorkSeconds), // Total Hours
            ]);

            $rows[] = $row;
        }

        // Date Range Info Header
        $infoHtml = '';
        if ($dateRange) {
            $infoHtml = '<div style="text-align: right; font-family: dejavusans; font-size: 11px; margin-bottom: 10px;">';
            $infoHtml .= '<span dir="rtl">' . $title . '</span>';
            $infoHtml .= '<br>';
            $infoHtml .= '<span dir="rtl">' . $dateRange . '</span>';
            $infoHtml .= '</div>';
        }

        // Style Options
        $options = [
            'headerBg' => '#f0f0f0',
            'headerColor' => '#000000',
            'borderColor' => '#000000',
            'fontSize' => 8, // Smaller font for many columns
            'cellPadding' => 2,
        ];

        $tableHtml = $this->pdfGenerator->createTable($headers, $rows, $options);

        $this->pdfGenerator
            ->initialize($companyId, $title, 'L')
            ->addPage($title);

        if ($infoHtml) {
            $this->pdfGenerator->writeHtml($infoHtml);
        }

        $this->pdfGenerator
            ->writeHtml($tableHtml)
            ->download('timesheet_' . date('Y-m-d') . '.pdf');
    }



    /**
     * توليد PDF للسلف
     */
    // Completed
    public function generateLoanPdf(Collection $data, string $title, int $companyId, array $filters = []): void
    {
        $headers = [
            'الموظف',
            'الشهر والسنه',
            'خصم لمره واحده',
            'المبلغ',
            'القسط الشهرى',
            'المدفوع',
            'الرصيد',
            'تاريخ الاضافه'
        ];

        $rows = [];
        foreach ($data as $record) {
            $employeeName = ($record->employee->first_name ?? '') . ' ' . ($record->employee->last_name ?? '');
            $employeeId = $record->employee->employee_id ?? $record->employee->user_details->employee_id ?? '-'; // Try relationships

            // Balance = Advance - Paid
            $balance = ($record->advance_amount ?? 0) - ($record->total_paid ?? 0);

            $oneTime = ($record->one_time_deduct == 1 || $record->one_time_deduct === '1') ? 'نعم' : 'لا'; // Legacy uses 'y' in screenshot sometimes? Screenshot showed 'y' and 'نعم'. Code uses lang('Main.xin_yes').

            $rows[] = [
                $employeeName . "\n" . $employeeId,
                $record->month_year ?? '-',
                $oneTime,
                number_format((float) ($record->advance_amount ?? 0), 2),
                number_format((float) ($record->monthly_installment ?? 0), 2),
                number_format((float) ($record->total_paid ?? 0), 2),
                number_format((float) $balance, 2),
                $record->created_at ? date('Y-m-d', strtotime($record->created_at)) : '-',
            ];
        }

        // Additional Info (Date Range)
        $dateInfo = '';
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $dateInfo = '<div style="text-align: right; font-family: dejavusans; font-size: 11px; margin-bottom: 5px;">';
            $dateInfo .= '<span dir="rtl">من: ' . $filters['start_date'] . ' إلى: ' . $filters['end_date'] . '</span>';
            $dateInfo .= '</div>';
        }

        // Style Options
        $options = [
            'headerBg' => '#f0f0f0',
            'headerColor' => '#000000',
            'borderColor' => '#000000',
            'fontSize' => 8, // Smaller font for many columns
            'cellPadding' => 2,
        ];
        $tableHtml = $this->pdfGenerator->createTable($headers, $rows, $options);

        $this->pdfGenerator
            ->initialize($companyId, $title, 'L')
            ->addPage($title, $dateInfo)
            ->writeHtml($tableHtml)
            ->download('loan_report_' . date('Y-m') . '.pdf');
    }


    /**
     * توليد PDF للإجازات
     */
    // Completed
    public function generateLeavePdf(Collection $data, int $companyId, array $filters = []): void
    {
        $title = 'تقرير مخلص الاجازات';
        $this->pdfGenerator->initialize($companyId, $title, 'L');
        $this->pdfGenerator->addPage($title);

        $groupedData = $data->groupBy('leave_type');

        if ($groupedData->isEmpty()) {
            $this->pdfGenerator->writeHtml('<h3 style="text-align: center;">لا توجد بيانات للعرض</h3>');
        } else {
            $headers = [
                'الاسم',
                'نوع مدة الاجازة',
                'مرحل',
                'رصيد الاجازة الحالي', // Quota/Assigned
                'مستحق', // Total Entitled
                'المستهلكه',
                'حالة الاعتماد', // Pending
                'تسوية الاجازة',
                'متبقي', // Balance
                'أيام الإجازه',
                'العام'
            ];

            $html = '<table border="1" cellpadding="4" cellspacing="0" style="width: 100%; font-size: 8px; font-family: dejavusans; direction: rtl; border-collapse: collapse;">';

            // 1. Column Headers (<thead> ensures repetition on new pages)
            $html .= '<thead>';
            $html .= '<tr style="background-color: #f0f0f0; color: #000000;">';
            foreach ($headers as $header) {
                $html .= '<th style="text-align: center; font-weight: bold; border: 1px solid #000000; padding: 3px;">' . $header . '</th>';
            }
            $html .= '</tr>';
            $html .= '</thead><tbody>';

            foreach ($groupedData as $leaveType => $records) {
                // 2. Group Separator Row (Light blue like legacy)
                $html .= '<tr style="background-color: #e3f2fd; color: #000000;">';
                $html .= '<td colspan="11" style="font-weight: bold; font-family: dejavusans; font-size: 10pt; text-align: right; background-color: #e3f2fd; border: 1px solid #000000; padding: 4px;">' . $leaveType . '</td>';
                $html .= '</tr>';

                // 3. Data Rows
                foreach ($records as $record) {
                    // NOTE: Values are already converted by ReportRepository when durationType is 'daily'
                    // No need to divide again here

                    $rowData = [
                        $record['employee_name'],
                        $record['duration_type'] ?? 'بالساعه',
                        number_format((float)($record['carry_limit'] ?? 0), 2),
                        number_format((float)$record['entitled'], 2),
                        number_format((float)($record['total_entitled'] ?? $record['entitled']), 2),
                        number_format((float)$record['used'], 2),
                        number_format((float)$record['pending'], 2),
                        number_format((float)$record['adjustments'], 2),
                        number_format((float)$record['balance'], 2),
                        $record['leave_dates'] ?? '',
                        $record['year']
                    ];

                    $html .= '<tr nobr="true" style="background-color: #ffffff;">';
                    foreach ($rowData as $cell) {
                        $html .= '<td style="text-align: center; border: 1px solid #000000; padding: 2px;">' . ($cell ?? '-') . '</td>';
                    }
                    $html .= '</tr>';
                }
            }

            $html .= '</tbody></table>';
            $this->pdfGenerator->writeHtml($html);
        }

        $this->pdfGenerator->download('leaves_summary_report_' . date('Y-m-d') . '.pdf');
    }


    /**
     * توليد PDF للاستقالات
     */
    // Completed
    public function generateResignationsPdf(Collection $data, string $title, int $companyId, string $dateRange = '', string $statusText = ''): void
    {
        $headers = [
            'الموظف',
            'السبب',
            'تاريخ الإشعار',
            'تاريخ الاستقالة',
            'الحالة',
        ];

        $rows = [];

        foreach ($data as $record) {
            $statusEnum = NumericalStatusEnum::tryFrom((int)$record->status);
            $recordStatusText = $statusEnum?->labelAr() ?? '-';

            $rows[] = [
                $record->employee?->full_name ?? '-',
                mb_substr($record->reason ?? '-', 0, 50),
                $record->notice_date ?? '-',
                $record->resignation_date ?? '-',
                $recordStatusText,
            ];
        }

        // Initialize PDF
        $this->pdfGenerator->initialize($companyId, $title, 'L');

        $infoHtml = '<div style="text-align: right; font-weight: semi-bold; font-family: dejavusans; font-size: 10px; margin-bottom: 5px;">';
        if ($dateRange) {
            $infoHtml .= '<span>' . $dateRange . '</span>';
            if ($statusText) {
                $infoHtml .= ' | ';
            }
        }
        if ($statusText) {
            $infoHtml .= '<span>' . $statusText . '</span>';
        }
        $infoHtml .= '</div>';

        // Custom Table Construction to match styling
        $tableHtml = '<table border="1" cellpadding="3" cellspacing="0" style="width: 100%; font-size: 8px; font-family: dejavusans; direction: rtl; border-collapse: collapse; text-align: center;">';

        // Headers
        $tableHtml .= '<thead><tr style="background-color: #f0f0f0;">';
        foreach ($headers as $header) {
            $tableHtml .= '<td style="border: 1px solid #000; padding: 5px;">' . $header . '</td>';
        }
        $tableHtml .= '</tr></thead><tbody>';

        // Data
        foreach ($rows as $row) {
            $tableHtml .= '<tr>';
            foreach ($row as $cell) {
                $tableHtml .= '<td style="border: 1px solid #000; padding: 5px;">' . $cell . '</td>';
            }
            $tableHtml .= '</tr>';
        }

        $tableHtml .= '</tbody></table>';

        $this->pdfGenerator
            ->addPage($title)
            ->writeHtml($infoHtml)
            ->writeHtml($tableHtml)
            ->download('resignations_report_' . date('Y-m-d') . '.pdf');
    }

    /**
     * توليد PDF للتحويلات
     */
    // Completed
    public function generateTransfersPdf(Collection $data, string $title, int $companyId, string $dateRange = '', string $statusText = '', string $transferTypeText = '', string $transferType = 'all'): void
    {
        // Dynamic headers based on transfer type
        if ($transferType === 'branch') {
            $headers = [
                'الموظف',
                'نوع التحويل',
                'تاريخ التحويل',
                'الفرع القديم',
                'الفرع الجديد',
                'الحالة',
            ];
        } elseif ($transferType === 'intercompany') {
            $headers = [
                'الموظف',
                'الشركة القديمة',
                'الشركة الجديدة',
                'تاريخ التحويل',
                'نوع التحويل',
                'الحالة',
            ];
        } else {
            // Internal or All - show departments and designations
            $headers = [
                'الموظف',
                'القسم الجديد',
                'القسم القديم',
                'المسمى الوظيفي القديم',
                'المسمى الوظيفي الجديد',
                'تاريخ التحويل',
                'نوع التحويل',
                'الحالة',
            ];
        }

        $rows = [];
        foreach ($data as $record) {
            $statusEnum = NumericalStatusEnum::tryFrom((int)$record->status);
            $recordStatusText = $statusEnum?->labelAr() ?? '-';

            $transferTypeLabel = match ($record->transfer_type) {
                'internal' => 'داخلي',
                'branch' => 'بين الفروع',
                'intercompany' => 'بين الشركات',
                default => '-',
            };

            if ($transferType === 'branch') {
                $rows[] = [
                    $record->employee?->full_name ?? '-',
                    $record->oldBranch?->branch_name ?? '-',
                    $record->newBranch?->branch_name ?? '-',
                    $record->transfer_date ?? '-',
                    $transferTypeLabel,
                    $recordStatusText,
                ];
            } elseif ($transferType === 'intercompany') {
                $rows[] = [
                    $record->employee?->full_name ?? '-',
                    $record->oldCompany?->company_name ?? '-',
                    $record->newCompany?->company_name ?? '-',
                    $record->transfer_date ?? '-',
                    $transferTypeLabel,
                    $recordStatusText,
                ];
            } else {
                // Internal or All
                $rows[] = [
                    $record->employee?->full_name ?? '-',
                    $record->oldDepartment?->department_name ?? '-',
                    $record->newDepartment?->department_name ?? '-',
                    $record->oldDesignation?->designation_name ?? '-',
                    $record->newDesignation?->designation_name ?? '-',
                    $record->transfer_date ?? '-',
                    $transferTypeLabel,
                    $recordStatusText,
                ];
            }
        }

        // Initialize PDF
        $this->pdfGenerator->initialize($companyId, $title, 'L');

        // Build info HTML
        $infoHtml = '<div style="text-align: right; font-family: dejavusans; font-size: 10px; margin-bottom: 5px;">';
        $infoParts = [];
        if ($dateRange) $infoParts[] = $dateRange;
        if ($statusText) $infoParts[] = $statusText;
        $infoHtml .= '<span>' . implode(' | ', $infoParts) . '</span>';
        $infoHtml .= '</div>';

        // Build table HTML
        $tableHtml = '<table border="1" cellpadding="3" cellspacing="0" style="width: 100%; font-size: 8px; font-family: dejavusans; direction: rtl; border-collapse: collapse; text-align: center;">';
        $tableHtml .= '<thead><tr style="background-color: #f0f0f0;">';
        foreach ($headers as $header) {
            $tableHtml .= '<td style="border: 1px solid #000; padding: 5px;">' . $header . '</td>';
        }
        $tableHtml .= '</tr></thead><tbody>';

        foreach ($rows as $row) {
            $tableHtml .= '<tr>';
            foreach ($row as $cell) {
                $tableHtml .= '<td style="border: 1px solid #000; padding: 5px;">' . $cell . '</td>';
            }
            $tableHtml .= '</tr>';
        }
        $tableHtml .= '</tbody></table>';

        $this->pdfGenerator
            ->addPage($title)
            ->writeHtml($infoHtml)
            ->writeHtml($tableHtml)
            ->download('transfers_report_' . date('Y-m-d') . '.pdf');
    }

    /**
     * Generate Residence Renewal PDF
     */
    // Completed
    public function generateResidenceRenewalPdf(Collection $data, string $title, int $companyId, array $filters = []): void
    {
        $headers = [
            'الموظف',
            'المهنة',
            'تاريخ مباشرة العمل',
            'تاريخ انتهاء الإقامة الحالى',
            'قيمة رخصة العمل',
            'رسوم تجديد جوازات الإقامة',
            'قيمة المخالفه',
            'اجمالى المبلغ',
            'حصة الموظف',
            'حصة الشركة',
            'الإجمالي العام',
            'وقت انشاء الطلب',
        ];

        // Calculate totals
        $totalWorkPermitFee = 0;
        $totalResidenceRenewalFees = 0;
        $totalPenaltyAmount = 0;
        $totalAmount = 0;
        $totalEmployeeShare = 0;
        $totalCompanyShare = 0;
        $totalGrandTotal = 0;

        $rows = [];
        foreach ($data as $record) {
            $totalWorkPermitFee += (float)$record->work_permit_fee;
            $totalResidenceRenewalFees += (float)$record->residence_renewal_fees;
            $totalPenaltyAmount += (float)$record->penalty_amount;
            $totalAmount += (float)$record->total_amount;
            $totalEmployeeShare += (float)$record->employee_share;
            $totalCompanyShare += (float)$record->company_share;
            $totalGrandTotal += (float)$record->grand_total;

            $rows[] = [
                $record->employee?->full_name ?? '-',
                $record->profession ?? '-',
                $record->work_start_date?->format('Y-m-d') ?? '-',
                $record->current_residence_expiry_date?->format('Y-m-d') ?? '-',
                number_format((float)$record->work_permit_fee, 2),
                number_format((float)$record->residence_renewal_fees, 2),
                number_format((float)$record->penalty_amount, 2),
                number_format((float)$record->total_amount, 2),
                number_format((float)$record->employee_share, 2),
                number_format((float)$record->company_share, 2),
                number_format((float)$record->grand_total, 2),
                $record->created_at?->format('Y-m-d') ?? '-',
            ];
        }

        // Initialize PDF
        $this->pdfGenerator->initialize($companyId, $title, 'L');

        // Build info HTML
        $infoHtml = '<div style="text-align: right; font-family: dejavusans; font-size: 10px; margin-bottom: 5px;">';
        $infoHtml .= '<span>تاريخ التقرير: ' . date('Y-m-d H:i:s') . '</span>';
        if (!empty($filters['employee_id'])) {
            $employeeName = $data->first()?->employee?->full_name ?? '-';
            $infoHtml .= ' | <span>الموظف: ' . $employeeName . '</span>';
        } else {
            $infoHtml .= ' | <span>الموظف: جميع الموظفين</span>';
        }
        $infoHtml .= '</div>';

        // Build table HTML
        $tableHtml = '<table border="1" cellpadding="3" cellspacing="0" style="width: 100%; font-size: 7px; font-family: dejavusans; direction: rtl; border-collapse: collapse; text-align: center;">';
        $tableHtml .= '<thead><tr style="background-color: #f0f0f0;">';
        foreach ($headers as $header) {
            $tableHtml .= '<th style="border: 1px solid #000; padding: 5px;">' . $header . '</th>';
        }
        $tableHtml .= '</tr></thead><tbody>';

        foreach ($rows as $row) {
            $tableHtml .= '<tr>';
            foreach ($row as $cell) {
                $tableHtml .= '<td style="border: 1px solid #000; padding: 4px;">' . $cell . '</td>';
            }
            $tableHtml .= '</tr>';
        }

        // Add totals row
        $recordCount = count($data);
        $tableHtml .= '<tr style="background-color: #e3f2fd; font-weight: bold;">';
        $tableHtml .= '<td colspan="4" style="border: 1px solid #000; padding: 5px; text-align: right;">الإجمالي : ' . $recordCount . ' سجلات</td>';
        $tableHtml .= '<td style="border: 1px solid #000; padding: 5px;">' . number_format($totalWorkPermitFee, 2) . '</td>';
        $tableHtml .= '<td style="border: 1px solid #000; padding: 5px;">' . number_format($totalResidenceRenewalFees, 2) . '</td>';
        $tableHtml .= '<td style="border: 1px solid #000; padding: 5px;">' . number_format($totalPenaltyAmount, 2) . '</td>';
        $tableHtml .= '<td style="border: 1px solid #000; padding: 5px;">' . number_format($totalAmount, 2) . '</td>';
        $tableHtml .= '<td style="border: 1px solid #000; padding: 5px;">' . number_format($totalEmployeeShare, 2) . '</td>';
        $tableHtml .= '<td style="border: 1px solid #000; padding: 5px;">' . number_format($totalCompanyShare, 2) . '</td>';
        $tableHtml .= '<td  style="border: 1px solid #000; padding: 5px;">' . number_format($totalGrandTotal, 2) . '</td>';
        $tableHtml .= '<td style="border: 1px solid #000; padding: 5px;"></td>';
        $tableHtml .= '</tr>';

        $tableHtml .= '</tbody></table>';

        $this->pdfGenerator
            ->addPage($title)
            ->writeHtml($infoHtml)
            ->writeHtml($tableHtml)
            ->download('residence_renewal_report_' . date('Y-m-d') . '.pdf');
    }



    /**
     * توليد PDF للموظفين حسب الفرع
     */
    public function generateEmployeesByBranchPdf(Collection $data, string $title, int $companyId, array $filters = []): void
    {
        // 1. Initialize PDF
        $this->pdfGenerator->initialize($companyId, $title, 'L');

        // 2. Info Header
        // Display Filters: Branch, Status, Total Count
        $branchId = $filters['branch_id'] ?? 'all';
        $status = $filters['status'] ?? 'all';

        $branchName = 'كل الفروع';
        if ($branchId !== 'all' && $data->isNotEmpty()) {
            $branchName = $data->first()->branch_name ?? 'غير محدد';
        }

        $this->pdfGenerator->addPage($title);

        $statusTextMap = [
            'active' => 'نشط',
            'inactive' => 'غير نشط',
            'left' => 'متوقف / مغادر',
            'all' => 'الكل'
        ];
        $statusText = $statusTextMap[$status] ?? $status;

        $infoHtml = '
        <table border="0" cellpadding="4" cellspacing="0" style="width: 100%; margin-bottom: 10px; font-family: dejavusans; font-size: 10px; direction: rtl;">
            <tr>
                <td style="width: 15%;"><strong>الفرع:</strong></td>
                <td style="width: 35%;">' . $branchName . '</td>
                <td style="width: 15%;"><strong>الحالة:</strong></td>
                <td style="width: 35%;">' . $statusText . '</td>
            </tr>
            <tr>
                <td><strong>تاريخ التقرير:</strong></td>
                <td>' . date('Y-m-d') . '</td>
                <td><strong>إجمالي الموظفين:</strong></td>
                <td>' . $data->count() . '</td>
            </tr>
        </table>';

        $this->pdfGenerator->writeHtml($infoHtml);

        // 3. Group by Branch
        $grouped = $data->groupBy('branch_name');

        // 4. Generate Tables per Branch
        foreach ($grouped as $branchName => $employees) {
            $groupTitle = ($branchName ?: 'غير محدد') . ' (' . $employees->count() . ' موظف)';

            $sectionHeader = '<h3 style="font-family: dejavusans; margin-top: 10px;">' . $groupTitle . '</h3>';
            $this->pdfGenerator->writeHtml($sectionHeader);

            $headers = [
                'الرقم الوظيفي',
                'الرقم الهوية',
                'الموظف',
                'البريد الإلكتروني',
                'نوع الوظيفه',
                'رقم الاتصال',
                'اسم الفرع',
                'الدوله',
            ];

            $tableHtml = '<table border="1" cellpadding="3" cellspacing="0" style="width: 100%; font-size: 8px; font-family: dejavusans; direction: rtl; border-collapse: collapse; text-align: center;">';

            // Header
            $tableHtml .= '<thead><tr style="background-color: #f0f0f0;">';
            foreach ($headers as $header) {
                $tableHtml .= '<th style="border: 1px solid #000; padding: 5px;">' . $header . '</th>';
            }
            $tableHtml .= '</tr></thead><tbody>';

            $i = 1;
            foreach ($employees as $emp) {
                // Status Logic
                $empStatus = ($emp->is_active == 1) ? 'نشط' : 'غير نشط';
                if (!empty($emp->date_of_leaving)) {
                    $empStatus = 'مغادر في ' . $emp->date_of_leaving;
                }

                $row = [
                    $emp->employee_id,
                    $emp->employee_idnum,
                    ($emp->first_name ?? '') . ' ' . ($emp->last_name ?? ''),
                    $emp->email,
                    $this->translateJobType($emp->job_type ?? ''),
                    $emp->contact_number ?? '--', // Changed from contact_phone_no to contact_number (User table)
                    $emp->branch_name,
                    $emp->country_name,
                ];

                $tableHtml .= '<tr>';
                foreach ($row as $cell) {
                    $tableHtml .= '<td style="border: 1px solid #000; padding: 5px;">' . $cell . '</td>';
                }
                $tableHtml .= '</tr>';
            }
            $tableHtml .= '</tbody></table>';

            $this->pdfGenerator->writeHtml($tableHtml);
        }

        // 5. Download
        $this->pdfGenerator->download('employees_by_branch_' . date('Y-m-d') . '.pdf');
    }

    /**
     * توليد PDF للموظفين حسب الدولة
     */
    public function generateEmployeesByCountryPdf(Collection $data, string $title, int $companyId, array $filters = []): void
    {
        // 1. Initialize PDF
        $this->pdfGenerator->initialize($companyId, $title, 'L');

        // Add Page explicitly to match fix in branch report
        $this->pdfGenerator->addPage($title);

        // 2. Info Header
        $countryId = $filters['country_id'] ?? 'all';
        $status = $filters['status'] ?? 'all';

        $countryName = 'كل الدول';
        if ($countryId !== 'all' && $data->isNotEmpty()) {
            // Try to get country name from first record, fallback to raw country column if name is null
            $record = $data->first();
            $countryName = $record->country_name ?? $record->country ?? 'غير محدد';
        }

        $statusTextMap = [
            'active' => 'نشط',
            'inactive' => 'غير نشط',
            'left' => 'متوقف / مغادر',
            'all' => 'الكل'
        ];
        $statusText = $statusTextMap[$status] ?? $status;

        $infoHtml = '
        <table border="0" cellpadding="4" cellspacing="0" style="width: 100%; margin-bottom: 10px; font-family: dejavusans; font-size: 10px; direction: rtl;">
            <tr>
                <td style="width: 15%;"><strong>الدولة:</strong></td>
                <td style="width: 35%;">' . $countryName . '</td>
                <td style="width: 15%;"><strong>الحالة:</strong></td>
                <td style="width: 35%;">' . $statusText . '</td>
            </tr>
            <tr>
                <td><strong>تاريخ التقرير:</strong></td>
                <td>' . date('Y-m-d') . '</td>
                <td><strong>إجمالي الموظفين:</strong></td>
                <td>' . $data->count() . '</td>
            </tr>
        </table>';

        $this->pdfGenerator->writeHtml($infoHtml);

        $headers = [
            'الرقم الوظيفي',
            'الموظف',
            'البريد الإلكتروني',
            'رقم الاتصال',
            'اسم الفرع',
            'الدولة',
        ];

        $tableHtml = '<table border="1" cellpadding="3" cellspacing="0" style="width: 100%; font-size: 8px; font-family: dejavusans; direction: rtl; border-collapse: collapse; text-align: center;">';

        // Header
        $tableHtml .= '<thead><tr style="background-color: #f0f0f0;">';
        foreach ($headers as $header) {
            $tableHtml .= '<th style="border: 1px solid #000; padding: 5px;">' . $header . '</th>';
        }
        $tableHtml .= '</tr></thead><tbody>';

        foreach ($data as $emp) {
            $row = [
                $emp->employee_id,
                ($emp->first_name ?? '') . ' ' . ($emp->last_name ?? ''),
                $emp->email,
                $emp->contact_number ?? '--',
                $emp->branch_name ?? '--',
                $emp->country_name ?? $emp->country ?? '--', // Fallback to raw country column
            ];

            $tableHtml .= '<tr>';
            foreach ($row as $cell) {
                $tableHtml .= '<td style="border: 1px solid #000; padding: 5px;">' . $cell . '</td>';
            }
            $tableHtml .= '</tr>';
        }
        $tableHtml .= '</tbody></table>';

        $this->pdfGenerator->writeHtml($tableHtml);

        // 3. Download
        $this->pdfGenerator->download('employees_by_country_' . date('Y-m-d') . '.pdf');
    }


    // ==========================================
    // تقرير الرواتب الشهري PDF
    // ==========================================

    /**
     * توليد PDF للرواتب الشهرية
     */
    // Completed
    public function generatePayrollPdf(Collection $payrollData, string $title, int $companyId, array $filters = []): void
    {
        $paymentDate = $filters['payment_date'] ?? date('Y-m');
        $branchId = $filters['branch_id'] ?? null;

        // Get branch name for display
        $branchName = 'كل الفروع';
        if ($branchId) {
            $branch = Branch::find($branchId);
            $branchName = $branch?->branch_name ?? 'كل الفروع';
        }

        // Grouped Header Columns (matching web design)
        // معلومات الموظف (9 columns): #, الرقم الوظيفي, رقم الهوية, الموظف, الفرع, نوع الراتب, نوع الوظيفة, العملة, الراتب الأساسي
        // البدلات (1 column): إجمالي البدلات
        // الخصومات (2 columns): قسط السلفة, إجمالي الخصومات
        // صافي الراتب (3 columns): صافي الراتب, الحالة, طريقة الدفع

        $employeeColCount = 9;
        $allowanceColCount = 1;
        $deductionColCount = 2;
        $netColCount = 3;

        // Build Grouped Header Row (matching Leave Report colors)
        // Explicit widths added to ensure alignment with column headers below (Sum of children widths)
        $groupedHeaderHtml = '<tr style="background-color: #f0f0f0; color: #000000; font-weight: bold; text-align: center;">';
        $groupedHeaderHtml .= '<td colspan="' . $employeeColCount . '" style="border: 1px solid #000000; padding: 4px; width: 61%;">معلومات الموظف</td>';
        $groupedHeaderHtml .= '<td colspan="' . $allowanceColCount . '" style="border: 1px solid #000000; padding: 4px; width: 7%;">البدلات</td>';
        $groupedHeaderHtml .= '<td colspan="' . $deductionColCount . '" style="border: 1px solid #000000; padding: 4px; width: 14%;">الخصومات</td>';
        $groupedHeaderHtml .= '<td colspan="' . $netColCount . '" style="border: 1px solid #000000; padding: 4px; width: 18%;">صافي الراتب</td>';
        $groupedHeaderHtml .= '</tr>';

        // Build Column Headers Row
        $columnHeaders = [
            '#',
            'الرقم الوظيفي',
            'الموظف',
            'الفرع',
            'رقم الهوية',
            'نوع الراتب',
            'نوع الوظيفة',
            'العملة',
            'الراتب الأساسي',
            'إجمالي البدلات',
            'قسط السلفة',
            'إجمالي الخصومات',
            'صافي الراتب',
            'الحالة',
            'طريقة الدفع'
        ];

        // Column Widths (Percentages derived from Legacy mm values)
        // Total columns: 15
        $widths = [
            '3%',  // # (Seq)
            '7%',  // Job ID
            '12%', // Name
            '7%',  // Branch
            '9%',  // ID Num
            '5%',  // Salary Type
            '5%',  // Job Type
            '6%',  // Currency
            '7%',  // Basic
            '7%',  // Allowances
            '7%',  // Loan
            '7%',  // Deductions
            '8%',  // Net
            '5%',  // Status
            '5%'   // Payment Method
        ];

        // Build Data Rows and calculate totals
        $rows = [];
        $seq = 1;
        $totalBasicSalary = 0;
        $totalAllowances = 0;
        $totalLoan = 0;
        $totalDeductions = 0;
        $totalNetSalary = 0;

        foreach ($payrollData as $record) {
            $employee = $record->employee;
            $details = $record->details;

            // Get currency code with SAR symbol handling
            $currencyCode = $details?->currency?->currency_code ?? '-';
            if (strtoupper($currencyCode) === 'SAR') {
                $logoPath = public_path('uploads/currency_logos/sar_thumb.png');
                // Fix path slashes for TCPDF on Windows
                $logoPath = str_replace('\\', '/', $logoPath);

                if (file_exists($logoPath)) {
                    // Adjust width/height as needed (legacy used 4mm ~ 11px)
                    $currencyCode = '<img src="' . $logoPath . '" width="12" height="12" />';
                } else {
                    $currencyCode = 'ر.س';
                }
            }

            $row = [
                $seq++,
                $details?->employee_id ?? '-',
                $employee?->full_name ?? '-',
                $details?->branch?->branch_name ?? '-',
                $details?->employee_idnum ?? '-',
                $this->getWagesTypeText((int)($details?->salay_type ?? 1)),
                $this->translateJobType($details?->job_type ?? ''),
                $currencyCode,
                number_format((float)$record->basic_salary, 2),
                number_format((float)$record->allowances_total, 2),
                number_format((float)$record->loan_amount, 2),
                number_format((float)$record->deductions_total, 2),
                number_format((float)$record->net_salary, 2),
                $record->is_paid ? 'مدفوع' : 'غير مدفوع',
                $this->translatePaymentMethod($record->payment_method ?? ''),
            ];

            $totalBasicSalary += (float)$record->basic_salary;
            $totalAllowances += (float)$record->allowances_total;
            $totalLoan += (float)$record->loan_amount;
            $totalDeductions += (float)$record->deductions_total;
            $totalNetSalary += (float)$record->net_salary;

            $rows[] = $row;
        }

        // Build HTML Table
        $tableHtml = '<table border="1" cellpadding="2" cellspacing="0" style="font-family: dejavusans; font-size: 7px; direction: rtl; text-align: center; width: 100%; border-collapse: collapse;">';
        $tableHtml .= $groupedHeaderHtml;

        // Column headers
        $tableHtml .= '<tr style="background-color: #f0f0f0; color: #000000; font-weight: bold;">';
        foreach ($columnHeaders as $index => $header) {
            $width = $widths[$index] ?? 'auto';
            $tableHtml .= '<td style="border: 1px solid #000000; padding: 3px; width: ' . $width . ';">' . $header . '</td>';
        }
        $tableHtml .= '</tr>';

        // Data rows
        foreach ($rows as $row) {
            $tableHtml .= '<tr>';
            foreach ($row as $cell) {
                // Apply width to data cells too for consistency
                $tableHtml .= '<td style="border: 1px solid #000; padding: 2px;">' . $cell . '</td>';
            }
            $tableHtml .= '</tr>';
        }

        // Totals row (Color: #dce6f5 to match legacy RGB(220, 230, 245))
        // Explicitly define colspan for the first cell to span all info columns (8 columns)
        $tableHtml .= '<tr style="background-color: #dce6f5; font-weight: bold;">';
        $tableHtml .= '<td colspan="8" style="border: 1px solid #000; padding: 3px; text-align: center;">الإجمالي</td>';
        $tableHtml .= '<td style="border: 1px solid #000; padding: 3px;">' . ($totalBasicSalary != 0 ? number_format($totalBasicSalary, 2) : '') . '</td>';
        $tableHtml .= '<td style="border: 1px solid #000; padding: 3px;">' . ($totalAllowances != 0 ? number_format($totalAllowances, 2) : '') . '</td>';
        $tableHtml .= '<td style="border: 1px solid #000; padding: 3px;">' . ($totalLoan != 0 ? number_format($totalLoan, 2) : '') . '</td>';
        $tableHtml .= '<td style="border: 1px solid #000; padding: 3px;">' . ($totalDeductions != 0 ? number_format($totalDeductions, 2) : '') . '</td>';
        $tableHtml .= '<td style="border: 1px solid #000; padding: 3px;">' . ($totalNetSalary != 0 ? number_format($totalNetSalary, 2) : '') . '</td>';
        $tableHtml .= '<td style="border: 1px solid #000; padding: 3px;"></td>'; // Status
        $tableHtml .= '<td style="border: 1px solid #000; padding: 3px;"></td>'; // Payment Method
        $tableHtml .= '</tr>';

        $tableHtml .= '</table>';

        // Info section above table (Updated styles to match legacy feel)
        $printedBy = $user?->full_name ?? 'النظام';
        $printDateTime = date('d-m-Y H:i');

        // Legacy Layout (based on user snippet):
        // 1. Branch Centered
        // 2. Printed By & Date (Stacked, Right Aligned)

        $infoHtml = '<div style="text-align: center; font-family: dejavusans; font-size: 11px; font-weight: bold; margin-bottom: 5px;">الفرع: ' . $branchName . '</div>';

        $infoHtml .= '<div style="text-align: right; font-family: dejavusans; font-size: 11px; margin-bottom: 5px;">';
        $infoHtml .= '<strong>طبعت بواسطة:</strong> ' . $printedBy . '<br/>';
        $infoHtml .= '<strong>تاريخ الطباعة:</strong> ' . $printDateTime;
        $infoHtml .= '</div>';

        // Footer with signatures
        $footerHtml = '<br><br>';
        $footerHtml .= '<table style="width: 100%; font-family: dejavusans; font-size: 10px; text-align: center; direction: rtl;">';
        $footerHtml .= '<tr>';
        $footerHtml .= '<td style="width: 25%; border-top: 1px solid #000; padding-top: 10px;">الإدارة العليا</td>';
        $footerHtml .= '<td style="width: 25%; border-top: 1px solid #000; padding-top: 10px;">مدير الشركة</td>';
        $footerHtml .= '<td style="width: 25%; border-top: 1px solid #000; padding-top: 10px;">الحسابات</td>';
        $footerHtml .= '<td style="width: 25%; border-top: 1px solid #000; padding-top: 10px;">الموارد البشرية</td>';
        $footerHtml .= '</tr>';
        $footerHtml .= '</table>';

        $this->pdfGenerator
            ->initialize($companyId, $title, 'L') // Landscape for wide table
            ->addPage($title)
            ->writeHtml($infoHtml)
            ->writeHtml($tableHtml)
            ->writeHtml($footerHtml)
            ->download('payroll_report_' . $paymentDate . '.pdf');
    }

    /**
     * توليد PDF للمكافآت (Awards)
     */
    // Completed
    public function generateAwardsPdf(Collection $data, string $title, int $companyId, string $dateRange, User $user): void
    {
        $headers = [
            'نوع المكافأة',
            'الموظف',
            'هدية',
            'نقد',
            'الشهر والسنة',
        ];

        $rows = [];
        $totalCash = 0;

        foreach ($data as $record) {
            $rows[] = [
                $record->awardType?->category_name ?? '-',
                $record->employee?->full_name ?? '-',
                $record->gift_item ?? '-',
                number_format((float)$record->cash_price, 2),
                $record->award_month_year,
            ];
            $totalCash += (float)$record->cash_price;
        }

        // Initialize PDF
        $this->pdfGenerator->initialize($companyId, $title, 'L');


        $infoHtml = '<div style="text-align: right; font-family: dejavusans; font-size: 10px; margin-bottom: 5px;">';
        $infoHtml .= '<strong>' . $dateRange . '</strong><br/>';
        $infoHtml .= '</div>';

        $this->pdfGenerator->addPage($title);
        $this->pdfGenerator->writeHtml($infoHtml);

        $tableHtml = '<table border="1" cellpadding="3" cellspacing="0" style="width: 100%; font-size: 9px; font-family: dejavusans; direction: rtl; border-collapse: collapse; text-align: center;">';

        // Headers
        $tableHtml .= '<thead><tr style="background-color: #f0f0f0; font-weight: bold;">';
        foreach ($headers as $header) {
            $tableHtml .= '<td style="border: 1px solid #000; padding: 5px;">' . $header . '</td>';
        }
        $tableHtml .= '</tr></thead><tbody>';

        // Data
        foreach ($rows as $row) {
            $tableHtml .= '<tr>';
            foreach ($row as $cell) {
                $tableHtml .= '<td style="border: 1px solid #000; padding: 5px;">' . $cell . '</td>';
            }
            $tableHtml .= '</tr>';
        }

        // Totals Row
        $tableHtml .= '<tr style="background-color: #dce6f5; font-weight: bold;">';
        $tableHtml .= '<td colspan="3" style="border: 1px solid #000; padding: 5px; text-align: right;">المكافآت: ' . count($data) . '</td>';
        $tableHtml .= '<td colspan="2" style="border: 1px solid #000; padding: 5px;">الإجمالي: ' . number_format($totalCash, 2) . '</td>';
        $tableHtml .= '</tr>';

        $tableHtml .= '</tbody></table>';

        $this->pdfGenerator->writeHtml($tableHtml);
        $this->pdfGenerator->download('awards_report_' . date('Y-m-d') . '.pdf');
    }

    // Completed
    public function generatePromotionsPdf(Collection $data, string $title, int $companyId, string $dateRange, User $user): void
    {
        // Headers (Right to Left visual order for RTL table)
        $headers = [
            'الموظف',
            'عنوان الترقية',
            'القسم القديم',
            'القسم الجديد',
            'المسمى الوظيفي القديم',
            'المسمى الوظيفي الجديد',
            'الراتب القديم',
            'الراتب الجديد',
            'تاريخ الترقية',
            'الحالة'
        ];

        $rows = [];
        $approvedCount = 0;
        $pendingCount = 0;
        $rejectedCount = 0;

        foreach ($data as $record) {
            $statusEnum = NumericalStatusEnum::tryFrom((int)$record->status);
            $statusText = $statusEnum?->labelAr() ?? '-';

            // Count by status
            match ($statusEnum) {
                NumericalStatusEnum::PENDING => $pendingCount++,
                NumericalStatusEnum::APPROVED => $approvedCount++,
                NumericalStatusEnum::REJECTED => $rejectedCount++,
                default => null,
            };

            $rows[] = [
                $record->employee?->full_name ?? '-',
                $record->promotion_title,
                $record->oldDepartment?->department_name ?? '-',
                $record->newDepartment?->department_name ?? '-',
                $record->oldDesignation?->designation_name ?? '-',
                $record->newDesignation?->designation_name ?? '-',
                number_format((float)$record->old_salary, 2),
                number_format((float)$record->new_salary, 2),
                $record->promotion_date ? $record->promotion_date->format('Y-m-d') : '-',
                $statusText
            ];
        }

        // Initialize PDF
        $this->pdfGenerator->initialize($companyId, $title, 'L');

        $infoHtml = '<div style="text-align: right; font-weight: semi-bold; font-family: dejavusans; font-size: 10px; margin-bottom: 5px;">';
        $infoHtml .= '<strong>' . $dateRange . '</strong><br/>';
        $infoHtml .= '</div>';

        $this->pdfGenerator->addPage($title);
        $this->pdfGenerator->writeHtml($infoHtml);

        $tableHtml = '<table border="1" cellpadding="3" cellspacing="0" style="width: 100%; font-size: 8px; font-family: dejavusans; direction: rtl; border-collapse: collapse; text-align: center;">';

        // Headers
        $tableHtml .= '<thead><tr style="background-color: #f0f0f0;">';
        foreach ($headers as $header) {
            $tableHtml .= '<td style="border: 1px solid #000; padding: 5px;">' . $header . '</td>';
        }
        $tableHtml .= '</tr></thead><tbody>';

        // Data
        foreach ($rows as $row) {
            $tableHtml .= '<tr>';
            foreach ($row as $cell) {
                $tableHtml .= '<td style="border: 1px solid #000; padding: 5px;">' . $cell . '</td>';
            }
            $tableHtml .= '</tr>';
        }

        // Totals Row
        $tableHtml .= '<tr style="text-align: right; background-color: #dce6f5; font-weight: bold;">';
        $tableHtml .= '<td colspan="4" style="border: 1px solid #000; padding: 5px;">الإجمالي: ' . count($data) . '</td>';
        $tableHtml .= '<td colspan="2" style="border: 1px solid #000; padding: 5px;">قيد الانتظار: ' . $pendingCount . '</td>';
        $tableHtml .= '<td colspan="2" style="border: 1px solid #000; padding: 5px;">تم الموافقة: ' . $approvedCount . '</td>';
        $tableHtml .= '<td colspan="2" style="border: 1px solid #000; padding: 5px;">مرفوض: ' . $rejectedCount . '</td>';
        $tableHtml .= '</tr>';

        $tableHtml .= '</tbody></table>';

        $this->pdfGenerator->writeHtml($tableHtml);
        $this->pdfGenerator->download('promotions_report_' . date('Y-m-d') . '.pdf');
    }

    // Completed
    public function generateExpiringContractsPdf(Collection $data, string $title, int $companyId, array $filters = []): void
    {
        $headers = [
            'الرقم الوظيفي',
            'الاسم',
            'تاريخ انتهاء العقد',
            'الأيام المتبقية',
            'الحالة',
        ];

        $rows = [];
        $currentDate = new \DateTime();

        foreach ($data as $record) {
            $dateOfLeaving = $record->user_details?->date_of_leaving;
            $remainingDays = 0;
            $status = 'غير معروف';
            $formattedDate = '--';

            if ($dateOfLeaving) {
                $endDate = new \DateTime($dateOfLeaving);
                $formattedDate = $endDate->format('Y-m-d');

                // Calculate difference in days
                $diff = $currentDate->diff($endDate);
                $remainingDays = (int)$diff->format('%r%a'); // %r gives sign (+/-)

                if ($remainingDays > 0) {
                    $status = 'قارية على الانتهاء';
                } else {
                    $status = 'منتهي';
                }
            }

            $rows[] = [
                $record->user_details?->employee_id ?? '--',
                $record->full_name,
                $formattedDate,
                $dateOfLeaving ? $remainingDays : '--',
                $status,
            ];
        }

        // Initialize PDF
        $this->pdfGenerator->initialize($companyId, $title, 'L');

        // Build info HTML
        $endDateFilter = $filters['end_date'] ?? '';
        $infoHtml = '<div style="text-align: right; font-family: dejavusans; font-size: 10px; margin-bottom: 5px;">';
        $infoHtml .= '<span>' . $title . ' - ينتهي قبل ' . $endDateFilter . '</span>';
        $infoHtml .= '</div>';

        // Build table HTML
        $tableHtml = '<table border="1" cellpadding="3" cellspacing="0" style="width: 100%; font-size: 8px; font-family: dejavusans; direction: rtl; border-collapse: collapse; text-align: center;">';
        $tableHtml .= '<thead><tr style="background-color: #f0f0f0;">';
        foreach ($headers as $header) {
            $tableHtml .= '<th style="border: 1px solid #000; padding: 5px;">' . $header . '</th>';
        }
        $tableHtml .= '</tr></thead><tbody>';

        foreach ($rows as $row) {
            $tableHtml .= '<tr>';
            foreach ($row as $cell) {
                $tableHtml .= '<td style="border: 1px solid #000; padding: 5px;">' . $cell . '</td>';
            }
            $tableHtml .= '</tr>';
        }
        $tableHtml .= '</tbody></table>';

        $this->pdfGenerator
            ->addPage($title)
            ->writeHtml($infoHtml)
            ->writeHtml($tableHtml)
            ->download('ended_contracts_report_' . date('Y-m-d') . '.pdf');
    }

    // Completed
    public function generateExpiringDocumentsPdf(Collection $data, string $title, int $companyId, array $filters = []): void
    {
        $headers = [
            'الرقم الوظيفي',
            'الاسم',
            'تاريخ انتهاء الهوية/الإقامة',
            'عدد الأيام المتبقية',
            'الحالة',
        ];

        $currentDate = new \DateTime();
        $currentDate->setTime(0, 0, 0);

        foreach ($data as $record) {
            $expiryDate = $record->user_details?->contract_date_eqama;
            $remainingDays = 0;
            $status = 'غير معروف';
            $formattedDate = '--';

            if ($expiryDate) {
                $endDate = new \DateTime($expiryDate);
                $formattedDate = $endDate->format('Y-m-d');

                $diff = $currentDate->diff($endDate);
                $remainingDays = (int)$diff->format('%r%a');

                if ($remainingDays > 0) {
                    $status = 'ستنتهي قريبا';
                } else {
                    $status = 'منتهية';
                }
            }

            $rows[] = [
                $record->user_details?->employee_id ?? '--',
                $record->full_name,
                $formattedDate,
                $expiryDate ? $remainingDays : '--',
                $status,
            ];
        }

        // Initialize PDF
        $this->pdfGenerator->initialize($companyId, $title, 'L');

        // Build info HTML
        $endDateFilter = $filters['end_date'] ?? '';
        $infoHtml = '<div style="text-align: right; font-family: dejavusans; font-size: 10px; margin-bottom: 5px;">';
        $infoHtml .= '<span>' . $title . ' - تنتهي قبل ' . $endDateFilter . '</span>';
        $infoHtml .= '</div>';

        // Build table HTML
        $tableHtml = '<table border="1" cellpadding="3" cellspacing="0" style="width: 100%; font-size: 8px; font-family: dejavusans; direction: rtl; border-collapse: collapse; text-align: center;">';
        $tableHtml .= '<thead><tr style="background-color: #f0f0f0;">';
        foreach ($headers as $header) {
            $tableHtml .= '<th style="border: 1px solid #000; padding: 5px;">' . $header . '</th>';
        }
        $tableHtml .= '</tr></thead><tbody>';

        foreach ($rows as $row) {
            $tableHtml .= '<tr>';
            foreach ($row as $cell) {
                $tableHtml .= '<td style="border: 1px solid #000; padding: 5px;">' . $cell . '</td>';
            }
            $tableHtml .= '</tr>';
        }
        $tableHtml .= '</tbody></table>';

        $this->pdfGenerator
            ->addPage($title)
            ->writeHtml($infoHtml)
            ->writeHtml($tableHtml)
            ->download('ended_ids_report_' . date('Y-m-d') . '.pdf');
    }

    /**
     * Generate End of Service PDF
     */
    public function generateEndOfServicePdf(Collection $data, string $title, int $companyId, array $filters): void
    {
        $headers = [
            'اسم الموظف',
            'الرقم الوظيفي',
            'الحالة',
            'تاريخ حساب المكافاه',
            'تاريخ التعيين',
            'تاريخ انتهاء الخدمة',
            'نوع الانتهاء',
            'فترة الخدمة',
            'الراتب الأساسي',
            ' البدلات',
            'إجمالي الراتب',
            'مبلغ المكافأة',
            'أيام الإجازة غير المستخدمة',
            'حساب الإجازات',
            'حساب الإشعار',
            'إجمالي الحساب',
        ];

        $total = 0;

        foreach ($data as $item) {
            $total += (float)$item->total_compensation;
            $type = $item->termination_type == 'resignation' ? 'استقالة' : 'إنهاء خدمة';

            // Build service period string
            $servicePeriod = '';
            if (!empty($item->service_years)) {
                $servicePeriod .= $item->service_years . ' سنة';
            }
            if (!empty($item->service_months)) {
                $servicePeriod .= ($servicePeriod ? ' و ' : '') . $item->service_months . ' شهر';
            }
            if (!empty($item->service_days)) {
                $servicePeriod .= ($servicePeriod ? ' و ' : '') . $item->service_days . ' يوم';
            }
            if (empty($servicePeriod)) {
                $servicePeriod = '-';
            }

            // Status text
            $statusText = $item->is_approved ? 'معتمد' : 'قيد الانتظار';

            $rows[] = [
                ($item->employee->first_name ?? '') . ' ' . ($item->employee->last_name ?? ''),
                $item->employee->user_details->employee_id ?? '-',
                $statusText,
                $item->calculated_at?->format('Y-m-d - h:i:s A') ?? '-',
                $item->hire_date?->format('Y-m-d') ?? '-',
                $item->termination_date?->format('Y-m-d') ?? '-',
                $type,
                $servicePeriod,
                number_format((float)$item->basic_salary, 2),
                number_format((float)$item->allowances, 2),
                number_format((float)$item->total_salary, 2),
                number_format((float)$item->gratuity_amount, 2),
                $item->unused_leave_days ?? 0,
                number_format((float)$item->leave_compensation, 2),
                number_format((float)$item->notice_compensation, 2),
                number_format((float)$item->total_compensation, 2),
            ];
        }

        // Initialize PDF
        $this->pdfGenerator->initialize($companyId, $title, 'L');

        // Custom Table Construction to match styling
        $tableHtml = '<table border="1" cellpadding="3" cellspacing="0" style="width: 100%; font-size: 8px; font-family: dejavusans; direction: rtl; border-collapse: collapse; text-align: center;">';

        // Headers
        $tableHtml .= '<thead><tr style="background-color: #f0f0f0;">';
        foreach ($headers as $header) {
            $tableHtml .= '<td style="border: 1px solid #000; padding: 5px;">' . $header . '</td>';
        }
        $tableHtml .= '</tr></thead><tbody>';

        // Data
        $rowIndex = 0;
        $totalRows = count($rows);
        foreach ($rows as $row) {
            $rowIndex++;
            $isLastRow = ($rowIndex === $totalRows);
            $rowStyle = $isLastRow ? 'background-color: #f0f0f0; font-weight: bold;' : '';

            $tableHtml .= '<tr style="' . $rowStyle . '">';
            foreach ($row as $cell) {
                $tableHtml .= '<td style="border: 1px solid #000; padding: 5px;">' . $cell . '</td>';
            }
            $tableHtml .= '</tr>';
        }

        $tableHtml .= '</tbody></table>';

        $this->pdfGenerator
            ->addPage($title)
            ->writeHtml($tableHtml)
            ->download('end_of_service_report_' . date('Y-m-d') . '.pdf');
    }

}
