<div class="header">
    @if(isset($company) && $company->profile_photo)
    <img src="{{ public_path($company->profile_photo) }}" alt="Company Logo" class="company-logo">
    @endif

    <div class="company-name">
        {{ $company->company_name ?? 'HR System' }}
    </div>

    @if(isset($reportTitle))
    <div class="report-title">{{ $reportTitle }}</div>
    @endif

    <div class="print-date">
        تاريخ الطباعة: {{ date('Y-m-d H:i') }}
    </div>
</div>