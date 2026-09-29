<?php

namespace App\Console\Commands;

use App\Domain\Identity\Models\User;
use App\Domain\Platform\Services\AuditLogger;
use Illuminate\Console\Command;

/**
 * **Quitar el authenticator del panel a una cuenta** (P3 de `docs/specs/panel-a-salvo.md` §4.3, `DECISIONES #851`).
 *
 * El authenticator es obligatorio para los administradores y el panel no tiene página de perfil: quien pierde el móvil Y sus
 * ocho códigos de recuperación se queda fuera de su propio panel —y si es el único administrador, nadie puede ayudarle desde
 * dentro—. Por SSH, esto le quita el secreto y los códigos: en su siguiente inicio de sesión (con su contraseña) el panel le
 * pide configurarlo de nuevo. Se audita (`panel.app_authentication_removed`, sin PII).
 *
 *   php artisan panel:quitar-authenticator persona@dominio.tld
 */
class RemovePanelAuthenticator extends Command
{
    protected $signature = 'panel:quitar-authenticator {email : La cuenta del panel que ha perdido su authenticator.}';

    protected $description = 'Quita el authenticator del panel a una cuenta (móvil y códigos perdidos): su siguiente inicio de sesión le pide configurarlo.';

    public function handle(): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->error("No hay ninguna cuenta con el correo «{$email}».");

            return self::FAILURE;
        }

        if (blank($user->getAppAuthenticationSecret())) {
            $this->info('Esa cuenta no tiene authenticator: nada que quitar.');

            return self::SUCCESS;
        }

        $user->saveAppAuthenticationSecret(null);
        $user->saveAppAuthenticationRecoveryCodes(null);
        AuditLogger::logSystem('panel.app_authentication_removed', $user);

        $this->info('Authenticator quitado. En su siguiente inicio de sesión, el panel le pedirá configurarlo de nuevo.');

        return self::SUCCESS;
    }
}
