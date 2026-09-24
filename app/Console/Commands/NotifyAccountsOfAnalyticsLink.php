<?php

namespace App\Console\Commands;

use App\Domain\Identity\Models\User;
use App\Notifications\AnalyticsLinkNotice;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * **EL AVISO A LAS CUENTAS EXISTENTES** (`docs/specs/analitica.md` §4.3, T3a·4): informa por correo, UNA vez,
 * a cada cuenta de cliente que ya existía de que, desde la v3 de la política de cookies, aceptar «análisis»
 * puede vincular su navegación a su cuenta — y de dónde oponerse. Se corre la noche del despliegue de la v3
 * (runbook de `docs/ENTORNOS.md`) y se puede repetir: es idempotente por `users.analytics_notified_at`.
 *
 * ## A quién NO
 *  · al equipo (`PANEL_ROLES`): su navegación nunca se ata (`RecordLoginFact`);
 *  · a quien ya se opuso (`analytics_opt_out`): ya sabe lo que hay, y el aviso sería ruido;
 *  · a una cuenta anonimizada (art. 17): no hay a quién escribir;
 *  · a quien ya lo recibió (`analytics_notified_at`).
 * Sí a quien tiene el correo SIN verificar: su cuenta existe, puede entrar y por tanto puede vincularse.
 *
 * ⚠️⚠️ **Se marca ANTES de encolar**, y con `toBase()`: si el envío revienta, la cuenta se queda sin correo pero
 * no con seis — un fallo de correo se ve en `failed_jobs`, y seis correos los ve el cliente. Es el molde de
 * `reservations:eve-notice`. Y por `chunkById`: marcar filas del lote en curso no desplaza los siguientes.
 *
 * ⚠️ Una cuenta creada DESPUÉS de esta pasada no recibe nada, y es correcto: se registró bajo la política nueva
 * y el banner le pregunta. El aviso del cajón sale de la misma marca (`CustomerAccountContext`), así que
 * tampoco lo ve.
 */
class NotifyAccountsOfAnalyticsLink extends Command
{
    /** Cuántas cuentas por lote: un `notify()` por fila, en cola. */
    private const CHUNK = 200;

    protected $signature = 'analytics:notify-accounts
        {--dry-run : Enseña a cuántas cuentas avisaría y no manda ni marca nada.}';

    protected $description = 'Avisa por correo, una vez, a las cuentas existentes de que su navegación puede vincularse a su cuenta (v3 de la política de cookies).';

    public function handle(): int
    {
        $sent = 0;
        $failed = 0;
        $dryRun = (bool) $this->option('dry-run');

        $this->pending()->chunkById(self::CHUNK, function (Collection $users) use (&$sent, &$failed, $dryRun): void {
            /** @var User $user */
            foreach ($users as $user) {
                if ($dryRun) {
                    $sent++;

                    continue;
                }

                User::query()->whereKey($user->getKey())->toBase()
                    ->update(['analytics_notified_at' => Carbon::now()]);

                try {
                    $user->notify(new AnalyticsLinkNotice);
                    $sent++;
                } catch (Throwable $e) {
                    // La marca se queda puesta a propósito: reintentar mandaría el correo dos veces si el
                    // fallo fue después de encolar. Queda el rastro para mirarlo.
                    $failed++;
                    Log::warning('analytics.notice_not_queued', [
                        'user_id' => $user->getKey(),
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });

        $this->info(sprintf('%s %d cuentas%s.',
            $dryRun ? 'Avisaría a' : 'Avisadas',
            $sent,
            $failed > 0 ? sprintf(' (%d sin encolar, ver el log)', $failed) : '',
        ));

        return self::SUCCESS;
    }

    /**
     * Las cuentas de cliente que todavía no han recibido el aviso.
     *
     * @return Builder<User>
     */
    private function pending(): Builder
    {
        return User::query()
            ->whereNull('analytics_notified_at')
            ->where('analytics_opt_out', false)
            ->where('email', 'not like', '%@'.User::ANONYMIZED_EMAIL_DOMAIN)
            ->whereDoesntHave('roles', fn (Builder $roles) => $roles->whereIn('name', User::PANEL_ROLES))
            ->orderBy('id');
    }
}
