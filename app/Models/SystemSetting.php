<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    /** @var array<int|string, array<string, mixed>> memo per-request keyed oleh object request agar aman di test. */
    private static array $memo = [];

    private static function memoKey(): int|string
    {
        $req = app('request');

        return $req ? spl_object_id($req) : 'cli';
    }

    public static function flushValueMemo(): void
    {
        self::$memo = [];
    }

    protected static function booted(): void
    {
        static::saved(fn () => self::$memo = []);
        static::deleted(fn () => self::$memo = []);
    }

    public static function valueFor(string $key, mixed $default = null): mixed
    {
        $memoKey = self::memoKey();
        if (array_key_exists($key, self::$memo[$memoKey] ?? [])) {
            return self::$memo[$memoKey][$key] ?? $default;
        }

        $setting = self::query()->where('key', $key)->first();
        self::$memo[$memoKey][$key] = $setting?->value;

        return $setting?->value ?? $default;
    }

    public static function setValue(string $key, mixed $value): self
    {
        return self::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }
}
