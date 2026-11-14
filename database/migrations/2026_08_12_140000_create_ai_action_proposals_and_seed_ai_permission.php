<?php

use App\Models\RolePermission;
use App\Support\PermissionRegistry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_action_proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->string('tool', 80);
            $table->string('title', 200);
            $table->text('summary')->nullable();
            $table->json('payload');
            $table->string('perm_menu', 60);
            $table->string('perm_action', 20)->default('update');
            $table->string('status', 20)->default('pending'); // pending|confirmed|cancelled|expired|failed
            $table->boolean('dangerous')->default(false);
            $table->timestamp('expires_at');
            $table->timestamp('executed_at')->nullable();
            $table->json('result')->nullable();
            $table->timestamps();

            $table->index(['requested_by', 'status']);
            $table->index('expires_at');
        });

        RolePermission::query()->updateOrCreate(
            ['role' => 'laboran', 'menu_key' => 'ai_assistant'],
            [
                'can_view' => true,
                'can_create' => false,
                'can_update' => false,
                'can_delete' => false,
            ]
        );
        PermissionRegistry::clearCache();
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_action_proposals');
        RolePermission::query()
            ->where('role', 'laboran')
            ->where('menu_key', 'ai_assistant')
            ->delete();
        PermissionRegistry::clearCache();
    }
};
