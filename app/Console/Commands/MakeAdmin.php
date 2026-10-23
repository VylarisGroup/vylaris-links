<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class MakeAdmin extends Command
{
    protected $signature = 'links:make-admin {email : E-Mail-Adresse eines bestehenden Kontos} {--revoke : Admin-Rechte entziehen}';

    protected $description = 'Macht ein Konto zum Admin (Moderation) oder entzieht die Rechte.';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();
        if (! $user) {
            $this->error('Kein Konto mit dieser E-Mail gefunden.');

            return self::FAILURE;
        }

        $user->forceFill(['is_admin' => ! $this->option('revoke')])->save();
        $this->info($user->email.($user->is_admin ? ' ist jetzt Admin.' : ' ist kein Admin mehr.'));

        return self::SUCCESS;
    }
}
