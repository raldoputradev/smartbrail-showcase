<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pbl_team_members')) {
            return;
        }

        Schema::create('pbl_team_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pbl_project_id')->constrained('pbl_projects')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 16);
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['pbl_project_id', 'user_id']);
            $table->index(['pbl_project_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pbl_team_members');
    }
};
