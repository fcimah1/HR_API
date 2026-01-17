<div class="footer">
    <div class="page-number">
        صفحة {PAGE_NUM} من {PAGE_COUNT}
    </div>
    @if(isset($footerText))
    <div>{{ $footerText }}</div>
    @endif
</div>