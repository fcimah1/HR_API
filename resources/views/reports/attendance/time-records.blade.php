@extends('reports.layouts.pdf-layout')

@section('title', 'تقرير سجلات الوقت')

@section('content')
<h2 style="text-align: center; margin-bottom: 15px;">تقرير سجلات الوقت</h2>

<table>
    <thead>
        <tr>
            <th>#</th>
            <th>الموظف</th>
            <th>التاريخ</th>
            <th>الحضور</th>
            <th>الانصراف</th>
            <th>بداية الاستراحة</th>
            <th>نهاية الاستراحة</th>
            <th>إجمالي العمل</th>
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
            <td>{{ $record->lunch_break_in ?? '-' }}</td>
            <td>{{ $record->lunch_break_out ?? '-' }}</td>
            <td>{{ $record->total_work ?? '-' }}</td>
        </tr>
        @empty
        <tr>
            <td colspan="8" style="text-align: center; padding: 20px;">لا توجد سجلات</td>
        </tr>
        @endforelse
    </tbody>
</table>
@endsection