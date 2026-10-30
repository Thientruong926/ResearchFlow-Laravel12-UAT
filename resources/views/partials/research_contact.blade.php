<div class="small muted" style="overflow-wrap:anywhere">
    <p>Liên hệ: {{ $research['contact_name'] }} · {{ $research['contact_email'] }}</p>
    @if(!empty($research['contact_phone']))<p>Điện thoại: {{ $research['contact_phone'] }}</p>@endif
    @if(!empty($research['contact_address']))<p style="white-space:pre-wrap">Địa chỉ: {{ $research['contact_address'] }}</p>@endif
</div>
