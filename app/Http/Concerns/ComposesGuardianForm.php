<?php

namespace App\Http\Concerns;

use App\Domain\Booking\Contracts\AuthorizableReservation;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Identity\Exceptions\GuardianAuthorizationRefusedException;
use App\Domain\Identity\Models\Dependent;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\DependentRegistry;
use App\Domain\Identity\Services\GuardianPlaces;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * **LO QUE EL FORMULARIO DE LA FIRMA NECESITA**, para las dos pantallas que lo pintan: la autorización de un menor
 * invitado (`GuardianAuthorizationController::show`) y, desde F6a de `specs/fiesta-sistema-nuevo.md`, el RECIBO de la
 * invitación (`InvitationPageController::receipt`), donde el padre firma sin salir de su recibo. Una sola fuente para
 * las dos: el bloqueo, las relaciones, lo que llega desde la invitación, el prellenado y los menores a cargo con
 * sesión, la URL firmada del envío y lo que vuelve por la sesión (desenlace, errores y lo escrito).
 *
 * ⚠️ Solo CALCULA para pintar: la puerta que manda sigue en el dominio, bajo el lock (`GuardianAuthorizationSigner`).
 */
trait ComposesGuardianForm
{
    /**
     * @param  array{invitation_reply_id?: int, minor?: string, desde?: string}  $extras  lo que viaja DENTRO de la firma
     * @return array<string, mixed>
     */
    protected function guardianFormInputs(Request $request, OrderItem $reservation, AuthorizableReservation $context, array $extras): array
    {
        $user = $request->user();

        return [
            'blocked' => self::guardianBlockedReason($context, isset($extras['invitation_reply_id'])),
            'relationships' => Dependent::RELATIONSHIPS,
            'fromInvitation' => [
                'reply_id' => $extras['invitation_reply_id'] ?? null,
                'minor' => $extras['minor'] ?? '',
            ],
            // §4.6: con sesión, los datos del adulto vienen rellenos (no verifica ni enlaza la cuenta con la firma).
            'prefill' => [
                'guardian_name' => $user?->name,
                'guardian_email' => $user?->email,
                'guardian_phone' => $user?->phone,
            ],
            // §12.5: con sesión, el menor se ELIGE —se COPIA el dato, no se enlaza—, y solo los que hoy son menores.
            'dependents' => $user === null ? [] : self::guardianMinorsOf($user),
            // ❗❗ Los extras viajan DENTRO de la firma del POST (`#704`): la atadura a la respuesta y, desde F6a, a dónde
            // se vuelve (`desde=recibo`). Un número del cuerpo no puede decidir a qué recibo se vuelve.
            'formAction' => URL::temporarySignedRoute(
                'reservation.authorization.store',
                $context->linkExpiresAt,
                ['reservation' => $reservation] + $extras,
            ),
            'status' => $request->session()->get('guardian_status'),
            'minorName' => $request->session()->get('guardian_minor'),
            'signer' => $request->session()->get('guardian_signer'),
            'errors' => $request->session()->get('errors'),
            'old' => $request->old(),
        ];
    }

    /**
     * Los menores a cargo del titular con sesión que HOY son menores (un mayor de edad firma por sí mismo), con lo que el
     * formulario copia al elegirlos.
     *
     * @return list<array{id: int, name: string, surname: string, born_on: ?string, relationship: string, label: string}>
     */
    protected static function guardianMinorsOf(User $user): array
    {
        $out = [];
        foreach (app(DependentRegistry::class)->activeFor($user) as $d) {
            if (! $d->isMinor()) {
                continue;
            }
            $nacido = $d->getAttribute('born_on');
            $out[] = [
                'id' => (int) $d->getKey(),
                'name' => (string) $d->name,
                'surname' => (string) $d->surname,
                'born_on' => $nacido instanceof DateTimeInterface ? $nacido->format('Y-m-d') : null,
                'relationship' => (string) $d->relationship,
                'label' => $d->fullName(),
            ];
        }

        return $out;
    }

    /**
     * Por qué NO se puede firmar, para PINTARLO (la puerta que manda vive en el dominio, bajo el lock).
     *
     * ❗❗ **Una firma ATADA a un «sí» de la invitación no gasta plaza** (`#576`): el firmador salta el tope cuando la
     * respuesta es un «sí» vivo de esta reserva, porque ese «sí» YA tiene la suya (`GuardianPlaces::takenIn` lo cuenta).
     * Hasta F6a la pantalla decía «no quedan plazas» a quien llegaba desde su recibo con la fiesta llena —el caso normal
     * de una fiesta completa— y el firmador le habría dejado firmar. Con la atadura, «llena» no se pinta: si la respuesta
     * no resultara un «sí» vivo, el dominio lo rechaza igual y lo dice.
     */
    protected static function guardianBlockedReason(AuthorizableReservation $context, bool $tied): ?string
    {
        if (! $context->isPaid) {
            return GuardianAuthorizationRefusedException::REASON_NOT_PAID;
        }
        if ($context->visitFinished) {
            return GuardianAuthorizationRefusedException::REASON_CLOSED;
        }
        // Las plazas LIBRES, no la cantidad: descuenta los menores a cargo asignados, los justificantes firmados y los
        // «sí» que aún no tienen el suyo (`GuardianPlaces`).
        if (! $tied && app(GuardianPlaces::class)->freeIn($context) < 1) {
            return GuardianAuthorizationRefusedException::REASON_FULL;
        }

        return null;
    }
}
