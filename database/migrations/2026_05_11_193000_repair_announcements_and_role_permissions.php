<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ---------------------------------------------------------------
        // Pastikan tabel announcements LENGKAP (self-healing).
        // ---------------------------------------------------------------
        if (!Schema::hasTable('announcements')) {
            Schema::create('announcements', function (Blueprint $table) {
                $table->id();
                $table->string('judul', 200);
                $table->text('isi');
                $table->enum('target', ['semua', 'dosen', 'mahasiswa'])->default('semua')->index();
                $table->enum('prioritas', ['normal', 'penting', 'urgent'])->default('normal');
                $table->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('expires_at')->nullable()->index();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        } else {
            Schema::table('announcements', function (Blueprint $table) {
                if (!Schema::hasColumn('announcements', 'judul')) {
                    $table->string('judul', 200)->after('id');
                }
                if (!Schema::hasColumn('announcements', 'isi')) {
                    $table->text('isi');
                }
                if (!Schema::hasColumn('announcements', 'target')) {
                    $table->enum('target', ['semua', 'dosen', 'mahasiswa'])->default('semua')->index();
                }
                if (!Schema::hasColumn('announcements', 'prioritas')) {
                    $table->enum('prioritas', ['normal', 'penting', 'urgent'])->default('normal');
                }
                if (!Schema::hasColumn('announcements', 'dibuat_oleh')) {
                    $table->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
                }
                if (!Schema::hasColumn('announcements', 'expires_at')) {
                    $table->timestamp('expires_at')->nullable()->index();
                }
                if (!Schema::hasColumn('announcements', 'is_active')) {
                    $table->boolean('is_active')->default(true);
                }
                if (!Schema::hasColumn('announcements', 'created_at')) {
                    $table->timestamps();
                }
            });
        }

        // ---------------------------------------------------------------
        // Pastikan tabel role_permissions LENGKAP (self-healing).
        // ---------------------------------------------------------------
        if (!Schema::hasTable('role_permissions')) {
            Schema::create('role_permissions', function (Blueprint $table) {
                $table->id();
                $table->enum('role', ['laboran', 'dosen', 'mahasiswa']);
                $table->string('menu_key', 100);
                $table->boolean('can_view')->default(true);
                $table->boolean('can_create')->default(false);
                $table->boolean('can_update')->default(false);
                $table->boolean('can_delete')->default(false);
                $table->timestamps();
                $table->unique(['role', 'menu_key']);
            });
        } else {
            Schema::table('role_permissions', function (Blueprint $table) {
                if (!Schema::hasColumn('role_permissions', 'role')) {
                    $table->enum('role', ['laboran', 'dosen', 'mahasiswa']);
                }
                if (!Schema::hasColumn('role_permissions', 'menu_key')) {
                    $table->string('menu_key', 100);
                }
                if (!Schema::hasColumn('role_permissions', 'can_view')) {
                    $table->boolean('can_view')->default(true);
                }
                if (!Schema::hasColumn('role_permissions', 'can_create')) {
                    $table->boolean('can_create')->default(false);
                }
                if (!Schema::hasColumn('role_permissions', 'can_update')) {
                    $table->boolean('can_update')->default(false);
                }
                if (!Schema::hasColumn('role_permissions', 'can_delete')) {
                    $table->boolean('can_delete')->default(false);
                }
                if (!Schema::hasColumn('role_permissions', 'created_at')) {
                    $table->timestamps();
                }
            });
        }

        // ---------------------------------------------------------------
        // Auto-seed default permissions kalau tabel masih kosong.
        // ---------------------------------------------------------------
        $count = DB::table('role_permissions')->count();
        if ($count === 0 && class_exists(\App\Support\PermissionRegistry::class)) {
            \App\Support\PermissionRegistry::seedDefaults();
        }
    }

    public function down(): void
    {
        // Sengaja tidak menghapus apapun: ini migration self-heal.
    }
};
