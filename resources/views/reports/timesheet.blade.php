@extends('reports.layouts.pdf-layout')

@section('title', 'سجل الدوام')

@section('content')
<h2 style="text-align: center; margin-bottom: 15px;">
    سجل الدوام (Timesheet)
    @if(isset($month))
    <br><small style="font-size: 11px;">{{ $month }}</small>
    @endif
</h2>

@if(isset($summary))
<div class="summary-box">
    <span class="summary-item"><span class="label">إجمالي الأيام:</span> <span class="value">{{ $summary['total_days'] ?? 0 }}</span></span>
    <span class="summary-item"><span class="label">إجمالي ساعات العمل:</span> <span class="value">{{ $summary['total_hours'] ?? '00:00' }}</span></span>
</div>
@endif

<table>
    <thead>
        <tr>
            <th>#</th>
            <th>الموظف</th>
            <th>التاريخ</th>
            <th>الحضور</th>
            <th>الانصراف</th>
            <th>إجمالي العمل</th>
            <th>الحالة</th>
            <th>من المنزل</th>
        </tr>
    </thead>
    <tbody>
        @forelse($records as $index => $record)
        <tr>
            <td>{{ $index + 1 }}</td>
            <td>{{ $record->employee->full_name ?? '-' }}</td>
            <td>{{ $record->attendance_date ?? '-' }}</td>
            <td>{{ $record->clock_in ?? '-' }}</td>
            <td>{{ $record->clock_out ?? '-' }}</td>
            <td>{{ $record->total_work ?? '-' }}</td>
            <td class="{{ $record->attendance_status === 'Present' ? 'status-present' : ($record->attendance_status === 'Absent' ? 'status-absent' : 'status-late') }}">
                @switch($record->attendance_status)
                @case('Present') حاضر @break
                @case('Absent') غائب @break
                @case('Late') متأخر @break
                @case('Half Day') نصف يوم @break
                @case('On Leave') إجازة @break
                @default {{ $record->attendance_status ?? '-' }}
                @endswitch
            </td>
            <td>{{ $record->work_from_home ? 'نعم' : 'لا' }}</td>
        </tr>
        @empty
        <tr>
            <td colspan="8" style="text-align: center; padding: 20px;">لا توجد سجلات</td>
        </tr>
        @endforelse
    </tbody>
</table>
@endsection