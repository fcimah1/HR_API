@extends('reports.layouts.pdf-layout')

@section('title', 'تقرير الإجازات')

@section('content')
<h2 style="text-align: center; margin-bottom: 15px;">تقرير الإجازات</h2>

@if(isset($summary))
<div class="summary-box">
    <span class="summary-item"><span class="label">إجمالي الطلبات:</span> <span class="value">{{ $summary['total'] ?? 0 }}</span></span>
    <span class="summary-item"><span class="label">قيد الانتظار:</span> <span class="value status-pending">{{ $summary['pending'] ?? 0 }}</span></span>
    <span class="summary-item"><span class="label">موافق عليها:</span> <span class="value status-approved">{{ $summary['approved'] ?? 0 }}</span></span>
    <span class="summary-item"><span class="label">مرفوضة:</span> <span class="value status-rejected">{{ $summary['rejected'] ?? 0 }}</span></span>
</div>
@endif

<table>
    <thead>
        <tr>
            <th>#</th>
            <th>الموظف</th>
            <th>نوع الإجازة</th>
            <th>من</th>
            <th>إلى</th>
            <th>عدد الأيام</th>
            <th>الحالة</th>
        </tr>
    </thead>
    <tbody>
        @forelse($records as $index => $record)
        <tr>
            <td>{{ $index + 1 }}</td>
            <td>{{ $record->employee->full_name ?? '-' }}</td>
            <td>{{ $record->leaveType->type_name ?? '-' }}</td>
            <td>{{ $record->from_date ?? '-' }}</td>
            <td>{{ $record->to_date ?? '-' }}</td>
            <td>{{ $record->number_of_days ?? '-' }}</td>
            <td class="{{ $record->status === 'approved' ? 'status-approved' : ($record->status === 'rejected' ? 'status-rejected' : 'status-pending') }}">
                @switch($record->status)
                @case('pending') قيد الانتظار @break
                @case('approved') موافق عليه @break
                @case('rejected') مرفوض @break
                @case('cancelled') ملغي @break
                @default {{ $record->status ?? '-' }}
                @endswitch
            </td>
        </tr>
        @empty
        <tr>
            <td colspan="7" style="text-align: center; padding: 20px;">لا توجد سجلات</td>
        </tr>
        @endforelse
    </tbody>
</table>
@endsection