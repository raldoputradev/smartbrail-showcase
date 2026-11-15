<?php



namespace App\Models;



use Illuminate\Database\Eloquent\Factories\HasFactory;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;



class Suggestion extends Model

{

    use HasFactory;



    protected $fillable = [

        'user_id',

        'source',

        'nama',

        'email',

        'subjek',

        'pesan',

        'status',

        'admin_reply',

        'admin_replied_at',

        'admin_replied_by',

        'user_read_reply_at',

        'lampiran',

        'ip',

        'user_agent',

    ];



    protected function casts(): array

    {

        return [

            'admin_replied_at' => 'datetime',

            'user_read_reply_at' => 'datetime',

        ];

    }



    public function user(): BelongsTo

    {

        return $this->belongsTo(User::class);

    }



    public function repliedBy(): BelongsTo

    {

        return $this->belongsTo(User::class, 'admin_replied_by');

    }



    public function sourceLabel(): string

    {

        return match ($this->source) {

            'dosen' => 'Dosen',

            'mahasiswa' => 'Mahasiswa (akun)',

            'public' => 'Landing publik',

            default => ucfirst((string) $this->source),

        };

    }



    public function hasUnreadReplyForUser(): bool

    {

        return $this->admin_replied_at !== null && $this->user_read_reply_at === null;

    }

}

