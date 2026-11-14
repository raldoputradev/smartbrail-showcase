<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menambah nilai `account_verify` (dipakai AccountEmailVerification) ke enum
 * email_verification_tokens.type. Nilai lama tidak diubah dan tidak ada token yang dihapus.
 */
return new class extends Migration
{
    private const OLD = ['registration', 'password_reset'];

    private const NEW = ['registration', 'password_reset', 'account_verify'];

    public function up(): void
    {
        if (!Schema::hasTable('email_verification_tokens')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            if ($this->mysqlEnumHas('account_verify')) {
                return;
            }
            DB::statement($this->mysqlModify(self::NEW));

            return;
        }

        Schema::table('email_verification_tokens', function (Blueprint $table) {
            $table->enum('type', self::NEW)->default('registration')->change();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('email_verification_tokens')) {
            return;
        }

        if (DB::table('email_verification_tokens')->where('type', 'account_verify')->exists()) {
            throw new RuntimeException(
                'Rollback dibatalkan: masih ada token account_verify. '
                .'Biarkan token kedaluwarsa atau putuskan penanganannya secara manual.'
            );
        }

        if (DB::getDriverName() === 'mysql') {
            if (!$this->mysqlEnumHas('account_verify')) {
                return;
            }
            DB::statement($this->mysqlModify(self::OLD));

            return;
        }

        Schema::table('email_verification_tokens', function (Blueprint $table) {
            $table->enum('type', self::OLD)->default('registration')->change();
        });
    }

    private function mysqlEnumHas(string $value): bool
    {
        $column = DB::selectOne("SHOW COLUMNS FROM `email_verification_tokens` LIKE 'type'");

        return $column !== null && str_contains((string) ($column->Type ?? $column->type ?? ''), "'{$value}'");
    }

    /**
     * @param  list<string>  $values
     */
    private function mysqlModify(array $values): string
    {
        $list = implode(',', array_map(static fn (string $v) => "'{$v}'", $values));

        return "ALTER TABLE `email_verification_tokens` MODIFY `type` ENUM({$list}) NOT NULL DEFAULT 'registration'";
    }
};
