<?php

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Models\CustomerVisit;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Services\Analytics\Recorder;
use App\Domain\Platform\Services\AuditLogger;
use Carbon\CarbonInterface;

/**
 * Fase 6 · subsistema A — ACREDITAR la visita de un cliente en la puerta (`docs/specs/identidad-qr-puerta.md`
 * §8.3, §9.2 A·4).
 *
 * Es un acto EXPLÍCITO e IDEMPOTENTE por (cliente, día): la ficha se abre varias veces por cliente
 * —comprobar el waiver, mirar un vale, teclear un correo— y si el punto cayera al abrirla el saldo
 * dependería de cuántas veces mira el empleado. El único `(user_id, visited_on)` hace la idempotencia
 * por construcción; se audita SOLO cuando se escribe (`puerta.visit_registered`, target el cliente,
 * `by` el operador de la petición).
 */
final class GateVisits
{
    /** `true` si la visita se ha registrado AHORA; `false` si ya estaba registrada ese día. */
    public function register(User $customer, ?User $by, CarbonInterface $day): bool
    {
        $written = CustomerVisit::query()->insertOrIgnore([
            'user_id' => $customer->getKey(),
            'visited_on' => $day->toDateString(),
            'registered_by' => $by?->getKey(),
            'created_at' => now(),
        ]);

        if ($written > 0) {
            AuditLogger::log('puerta.visit_registered', $customer, ['visited_on' => $day->toDateString()]);

            // El libro de eventos (`specs/analitica.md` §4.2, T2b): la visita acreditada es un hecho del
            // servidor, y se anota UNA vez por (cliente, día), igual que la fila: la idempotencia de arriba
            // es la suya. El recorder nunca lanza (`PAY-21`), así que la visita no depende de él.
            app(Recorder::class)->fact('visit_checked_in', [], ['user_id' => (int) $customer->getKey()]);
        }

        return $written > 0;
    }

    public function registeredOn(User $customer, CarbonInterface $day): bool
    {
        return CustomerVisit::query()
            ->where('user_id', $customer->getKey())
            ->where('visited_on', $day->toDateString())
            ->exists();
    }
}
