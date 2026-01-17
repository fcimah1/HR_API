@extends('reports.layouts.pdf-layout')

@section('title', 'تقرير الاستقالات')

@section('content')
<h2 style="text-align: center; margin-bottom: 15px;">تقرير الاستقالات</h2>

@if(isset($summary))
<div class="summary-box">
    <span class="summary-item"><span class="label">إجمالي الاستقالات:</span> <span class="value">{{ $summary['total'] ?? 0 }}</span></span>
    <span class="summary-item"><span class="label">قيد الانتظار:</span> <span class="value status-pending">{{ $summary['pending'] ?? 0 }}</span></span>
    <span class="summary-item"><span class="label">موافق عليها:</span> <span class="value status-approved">{{ $summary['approved'] ?? 0 }}</span></span>
</div>
@endif

<table>
    <thead>
        <tr>
            <th>#</th>
            <th>الموظف</th>
            <th>تاريخ الاستقالة</th>
            <th>آخر يوم عمل</th>
            <th>السبب</th>
            <th>الحالة</th>
        </tr>
    </thead>
    <tbody>
        @forelse($records as $index => $record)
        <tr>
            <td>{{ $index + 1 }}</td>
            <td>{{ $record->employee->full_name ?? '-' }}</td>
            <td>{{ $record->resignation_date ?? '-' }}</td>
            <td>{{ $record->last_working_day ?? '-' }}</td>
            <td>{{ Str::limit($record->reason ?? '-', 40) }}</td>
            <td class="{{ $record->status === 'approved' ? 'status-approved' : ($record->status === 'rejected' ? 'status-rejected' : 'status-pending') }}">
                @switch($record->status)
                @case('pending') قيد الانتظار @break
                @case('approved') موافق عليه @break
                @case('rejected') مرفوض @break
                @default {{ $record->status ?? '-' }}
                @endswitch
            </td>
        </tr>
        @empty
        <tr>
            <td colspan="6" style="text-align: center; padding: 20px;">لا توجد سجلات</td>
        </tr>
        @endforelse
    </tbody>
</table>
@endsection