@extends('reports.layouts.pdf-layout')

@section('title', 'تقرير الموظفين حسب الدولة')

@section('content')
<h2 style="text-align: center; margin-bottom: 15px;">تقرير الموظفين حسب الدولة</h2>

@if(isset($summary))
<div class="summary-box">
    <span class="summary-item"><span class="label">إجمالي الموظفين:</span> <span class="value">{{ $summary['total'] ?? 0 }}</span></span>
    <span class="summary-item"><span class="label">نشط:</span> <span class="value status-approved">{{ $summary['active'] ?? 0 }}</span></span>
    <span class="summary-item"><span class="label">غير نشط:</span> <span class="value status-rejected">{{ $summary['inactive'] ?? 0 }}</span></span>
</div>
@endif

<table>
    <thead>
        <tr>
            <th>#</th>
            <th>الموظف</th>
            <th>الرقم الوظيفي</th>
            <th>الجنسية</th>
            <th>الفرع</th>
            <th>القسم</th>
            <th>الحالة</th>
        </tr>
    </thead>
    <tbody>
        @forelse($records as $index => $record)
        <tr>
            <td>{{ $index + 1 }}</td>
            <td>{{ $record->full_name ?? '-' }}</td>
            <td>{{ $record->employee_id ?? '-' }}</td>
            <td>{{ $record->nationality ?? '-' }}</td>
            <td>{{ $record->branch->office_name ?? '-' }}</td>
            <td>{{ $record->department->department_name ?? '-' }}</td>
            <td class="{{ $record->is_active ? 'status-approved' : 'status-rejected' }}">
                {{ $record->is_active ? 'نشط' : 'غير نشط' }}
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