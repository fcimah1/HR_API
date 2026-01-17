@extends('reports.layouts.pdf-layout')

@section('title', 'تقرير السلف والقروض')

@section('content')
<h2 style="text-align: center; margin-bottom: 15px;">تقرير السلف والقروض</h2>

@if(isset($summary))
<div class="summary-box">
    <span class="summary-item"><span class="label">إجمالي السلف:</span> <span class="value">{{ $summary['total_count'] ?? 0 }}</span></span>
    <span class="summary-item"><span class="label">إجمالي المبالغ:</span> <span class="value">{{ number_format($summary['total_amount'] ?? 0, 2) }}</span></span>
    <span class="summary-item"><span class="label">قيد الانتظار:</span> <span class="value status-pending">{{ $summary['pending'] ?? 0 }}</span></span>
    <span class="summary-item"><span class="label">موافق عليها:</span> <span class="value status-approved">{{ $summary['approved'] ?? 0 }}</span></span>
</div>
@endif

<table>
    <thead>
        <tr>
            <th>#</th>
            <th>الموظف</th>
            <th>المبلغ</th>
            <th>الحالة</th>
            <th>تاريخ الطلب</th>
            <th>سبب الطلب</th>
        </tr>
    </thead>
    <tbody>
        @forelse($records as $index => $record)
        <tr>
            <td>{{ $index + 1 }}</td>
            <td>{{ $record->employee->full_name ?? '-' }}</td>
            <td>{{ number_format($record->amount ?? 0, 2) }}</td>
            <td class="{{ $record->status === 'approved' ? 'status-approved' : ($record->status === 'rejected' ? 'status-rejected' : 'status-pending') }}">
                @switch($record->status)
                @case('pending') قيد الانتظار @break
                @case('approved') موافق عليه @break
                @case('rejected') مرفوض @break
                @case('completed') مكتمل @break
                @default {{ $record->status ?? '-' }}
                @endswitch
            </td>
            <td>{{ $record->created_at?->format('Y-m-d') ?? '-' }}</td>
            <td>{{ Str::limit($record->reason ?? '-', 40) }}</td>
        </tr>
        @empty
        <tr>
            <td colspan="6" style="text-align: center; padding: 20px;">لا توجد سجلات</td>
        </tr>
        @endforelse
    </tbody>
</table>
@endsection