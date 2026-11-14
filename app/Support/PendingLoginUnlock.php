<?php



namespace App\Support;



use App\Models\User;

use Illuminate\Database\Eloquent\Builder;

use Illuminate\Support\Collection;



class PendingLoginUnlock

{

    public static function query(): Builder

    {

        return User::query()->where(function (Builder $q) {

            $q->where('login_requires_admin_unlock', true)

                ->orWhere(function (Builder $q2) {

                    $q2->whereNotNull('login_locked_until')

                        ->where('login_locked_until', '>', now());

                });

        });

    }



    public static function count(): int

    {

        return self::query()->count();

    }



    /** @return Collection<int, User> */

    public static function recent(int $limit = 6): Collection

    {

        return self::query()

            ->orderByDesc('login_locked_until')

            ->orderByDesc('updated_at')

            ->limit($limit)

            ->get(['id', 'nama_lengkap', 'email', 'role', 'nitk_nidn', 'login_locked_until', 'login_requires_admin_unlock']);

    }



    public static function notificationTitle(): string

    {

        $n = self::count();



        return "{$n} akun terkunci login";

    }



    public static function notificationBody(): string

    {

        return 'Pengguna gagal login berulang atau membutuhkan buka kunci admin — cek di Manajemen User.';

    }

}

