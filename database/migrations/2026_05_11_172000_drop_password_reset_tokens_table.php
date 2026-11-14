<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('password_reset_tokens');
    }

    public function down(): void
    {
        // Tabel ini sengaja tidak dibuat ulang karena alur reset password
        // sudah dipindah ke email_verification_tokens (type=password_reset).
    }
};
