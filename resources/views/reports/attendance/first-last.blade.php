@extends('reports.layouts.pdf-layout')

@section('title', 'تقرير أول وآخر حضور/انصراف')

@section('content')
<h2 style="text-align: center; margin-bottom: 15px;">تقرير أول وآخر حضور/انصراف</h2>

<table>
    <thead>
        <tr>
            <th>#</th>
            <th>الموظف</th>
            <th>أول تاريخ حضور</th>
            <th>آخر تاريخ حضور</th>
            <th>أول حضور</th>
            <th>آخر انصراف</th>
            <th>إجمالي الأيام</th>
        </tr>
    </thead>
    <tbody>
        @forelse($records as $index => $record)
        <tr>
            <td>{{ $index + 1 }}</td>
            <td>{{ $record->employee->full_name ?? '-' }}</td>
            <td>{{ $record->first_attendance_date ?? '-' }}</td>
            <td>{{ $record->last_attendance_date ?? '-' }}</td>
            <td>{{ $record->first_clock_in ?? '-' }}</td>
            <td>{{ $record->last_clock_out ?? '-' }}</td>
            <td>{{ $record->total_days ?? 0 }}</td>
        </tr>
        @empty
        <tr>
            <td colspan="7" style="text-align: center; padding: 20px;">لا توجد سجلات</td>
        </tr>
        @endforelse
    </tbody>
</table>
@endsection