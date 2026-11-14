<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('absensi.staff.laboran', function (User $user) {
    return $user->isLaboran()
        && (bool) $user->is_active
        && $user->approval_status === 'approved';
});

Broadcast::channel('absensi.staff.dosen', function (User $user) {
    return $user->isDosen()
        && (bool) $user->is_active
        && $user->approval_status === 'approved';
});
