<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FingerprintSyncLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'id_mesin',
        'mesin_tipe',
        'action',
        'since',
        'after_finger_id',
        'batch_limit',
        'templates_count',
        'finger_ids',
        'sync_token',
        'success',
        'message',
        'ip_address',
        'requested_at',
    ];

    protected function casts(): array
    {
        return [
            'after_finger_id' => 'integer',
            'batch_limit' => 'integer',
            'templates_count' => 'integer',
            'finger_ids' => 'array',
            'success' => 'boolean',
            'requested_at' => 'datetime',
        ];
    }

    public function actionLabel(): string
    {
        return match ($this->action) {
            'sync_pull' => 'Pull template (sync)',
            'sync_status' => 'Cek status sync',
            'enroll_upload' => 'Upload enroll (absensi)',
            default => $this->action,
        };
    }
}
