<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\User;

class DebugDb extends Command
{
    protected $signature = 'debug:db';
    protected $description = 'Debug database state';

    public function handle()
    {
        $this->info('=== USERS ===');
        $users = User::all();
        if ($users->isEmpty()) {
            $this->warn('No users found');
        } else {
            foreach ($users as $u) {
                $this->line("ID={$u->id} | {$u->nama_lengkap} | {$u->email} | role={$u->role} | active=" . ($u->is_active ? 'YES' : 'NO'));
            }
        }

        $this->info("\n=== EMAIL VERIFICATION TOKENS ===");
        $tokens = DB::table('email_verification_tokens')->get();
        if ($tokens->isEmpty()) {
            $this->warn('No tokens found');
        } else {
            foreach ($tokens as $t) {
                $this->line("ID={$t->id} | {$t->email} | OTP={$t->otp_code} | type={$t->type} | expires={$t->expires_at}");
            }
        }
    }
}
