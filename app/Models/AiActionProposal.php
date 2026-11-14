<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiActionProposal extends Model
{
    protected $fillable = [
        'requested_by',
        'tool',
        'title',
        'summary',
        'payload',
        'perm_menu',
        'perm_action',
        'status',
        'dangerous',
        'expires_at',
        'executed_at',
        'result',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'result' => 'array',
            'dangerous' => 'boolean',
            'expires_at' => 'datetime',
            'executed_at' => 'datetime',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending' && $this->expires_at->isFuture();
    }

    public function markExpiredIfNeeded(): bool
    {
        if ($this->status === 'pending' && $this->expires_at->isPast()) {
            $this->update(['status' => 'expired']);

            return true;
        }

        return false;
    }
}
