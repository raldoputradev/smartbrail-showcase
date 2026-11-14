@if($user->login_requires_admin_unlock)
    <span class="status-badge status-badge--icon danger" style="margin-top:6px;display:inline-flex;">
        @include('components.icon', ['name' => 'shield-alert', 'class' => 'status-badge__icon'])
        Kunci login · perlu admin
    </span>
@elseif($user->isLoginTemporarilyLocked())
    <span class="status-badge status-badge--icon warning" style="margin-top:6px;display:inline-flex;">
        @include('components.icon', ['name' => 'clock', 'class' => 'status-badge__icon'])
        Kunci sementara
    </span>
@endif
