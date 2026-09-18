<?php

namespace App\Domain\Identity\Services;

use App\Domain\Booking\Contracts\AuthorizableReservations;
use App\Domain\Booking\Contracts\PartyGuests;
use App\Domain\Identity\Exceptions\GuardianAuthorizationExistsException;
use App\Domain\Identity\Exceptions\GuardianAuthorizationRefusedException;
use App\Domain\Identity\Models\GuardianAuthorization;
use App\Domain\Identity\Models\LegalDocumentVersion;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\WaiverSignature;
use Illuminate\Support\Facades\DB;

/**
 * Fase 6 · el JUSTIFICANTE de un menor invitado — la puerta ÚNICA por la que nace
 * (`docs/specs/waiver-por-reserva.md` §4.9).
 *
 * Un adulto sin cuenta rellena el formulario de un enlace y esto escribe, **en una sola transacción y
 * bajo el lock de la fila del RESPONSABLE**:
 *
 *  1. la autorización (la persona + su ancla a la RESERVA), si no existía ya;
 *  2. la firma probatoria, delegando en {@see WaiverSigner} — que es el único escritor de
 *     `waiver_signatures` y sigue siéndolo.
 *
 * ❗ **Las dos cosas van juntas por dos razones, y ninguna es estética:**
 *
 *  - **No hay carrera contra el `UNIQUE`.** El lock del responsable es el punto de serialización de
 *    todas sus cadenas, así que dos envíos simultáneos del mismo menor se ordenan: el segundo
 *    ENCUENTRA la fila en vez de estrellarse contra la clave única con un 500.
 *  - **No hay autorización huérfana.** Si la firma no llega a escribirse —texto superado, versión
 *    equivocada, lo que sea—, la autorización tampoco. PII de un menor **sin prueba detrás** es
 *    exactamente lo que la regla de `dependents` («sin firma, se borra») existe para evitar.
 *
 * ⚠️ El lock se toma aquí y `WaiverSigner` lo vuelve a tomar dentro: sobre la misma fila y la misma
 * transacción es un no-op, y así el firmador sigue siendo correcto llamándolo por su cuenta.
 *
 * ⚠️ **«Un niño, un papel»** (`[DECIDIDO owner]` §7·9): si ese menor ya tiene justificante en esta
 * RESERVA y lo firmó **otro adulto**, esto **no escribe nada** y lanza — el segundo progenitor ve que
 * ya está firmado. Si es el MISMO adulto reenviando, se comporta como la idempotencia de siempre:
 * misma versión → la firma que hay; versión nueva → una firma más, encadenada.
 *
 * ⚠️⚠️ **«Un papel» es por VISITA desde `#401`, no por pedido**, y el cambio da MÁS de lo que quita: el
 * mismo niño que va a dos días distintos del mismo pedido necesita **dos** autorizaciones, y con la
 * clave por pedido la segunda se rechazaba diciendo que ya estaba firmada.
 */
final class GuardianAuthorizationSigner
{
    public function __construct(
        private readonly WaiverSigner $signer,
        private readonly AuthorizableReservations $reservations,
        private readonly GuardianPlaces $places,
        private readonly PartyGuests $guests,
    ) {}

    /**
     * @param  User  $responsible  el titular del pedido — el RESPONSABLE, no quien firma
     * @param  int  $reservationId  la LÍNEA a la que va el menor (`order_items.id`), no el pedido:
     *                              un pedido puede tener dos visitas en días distintos y el padre
     *                              autoriza una (§13)
     * @param  array{minor_name:string, minor_surname:string, minor_born_on:string, guardian_name:string, guardian_surname:string, guardian_relationship:string, guardian_email:?string, guardian_phone:?string}  $data
     * @param  ?int  $invitationReplyId  la respuesta de la invitación digital desde la que se llega
     *                                   («lo dejo y me voy», §4.5·7). ⚠️ **No se cree**: se comprueba
     *                                   contra el contrato de Booking, y si no es un «sí» vivo de ESTA
     *                                   reserva se ignora en silencio — un enlace de otra fiesta no
     *                                   puede servir para saltarse el tope de ésta
     * @return array{authorization: GuardianAuthorization, signature: WaiverSignature, created: bool}
     */
    public function sign(
        User $responsible,
        int $reservationId,
        LegalDocumentVersion $version,
        array $data,
        WaiverSignatureRequest $request,
        ?int $invitationReplyId = null,
    ): array {
        $key = GuardianAuthorization::keyFor($data['minor_name'], $data['minor_surname']);

        return DB::transaction(function () use ($responsible, $reservationId, $version, $data, $request, $key, $invitationReplyId): array {
            // El MISMO punto de serialización que usa el firmador (§4.4). Va primero, antes de leer
            // nada: si se buscara la autorización fuera del lock, dos envíos simultáneos del mismo
            // menor podrían decidir los dos que no existe.
            User::query()->whereKey($responsible->getKey())->lockForUpdate()->firstOrFail();

            // ⚠️⚠️ **Las tres puertas se comprueban AQUÍ, bajo el lock, y no al pintar el formulario**
            // (`SEC-04` aplicado al tiempo): entre que el padre abre el enlace y lo envía puede pasar
            // la visita, cancelarse el pedido o llenarse el cupo. Y el cupo, en concreto, **solo es
            // correcto dentro del lock**: dos envíos simultáneos con una plaza libre lo leerían los
            // dos como disponible.
            $reservation = $this->reservations->find($reservationId);
            if ($reservation === null || ! $reservation->isPaid) {
                throw GuardianAuthorizationRefusedException::notPaid($reservationId);
            }
            if ($reservation->visitFinished) {
                throw GuardianAuthorizationRefusedException::closed($reservationId);
            }

            $existing = GuardianAuthorization::query()
                ->where('order_item_id', $reservationId)
                ->where('minor_key', $key)
                ->first();

            if ($existing !== null) {
                // «Un niño, un papel». Reenviar el MISMO adulto es idempotencia; que aparezca OTRO es
                // el caso de los dos progenitores, y ahí se para: dos justificantes del mismo menor
                // harían que la puerta y la hoja de sala lo enseñaran dos veces.
                if (! $this->sameGuardian($existing, $data)) {
                    throw GuardianAuthorizationExistsException::for($existing);
                }

                $signature = $this->signer->sign(
                    $responsible,
                    $version,
                    $request->forGuestMinor((int) $existing->getKey()),
                );

                return ['authorization' => $existing, 'signature' => $signature, 'created' => false];
            }

            // El TOPE, y desde `#401` es el de ESTA reserva y descuenta lo que ya tiene dueño
            // (`GuardianPlaces`): la cantidad de la línea menos los menores a cargo ya asignados
            // menos los justificantes ya firmados. Antes sumaba las líneas del pedido entero, así que
            // una entrada suelta comprada junto a una excursión de 80 ofrecía 81 plazas.
            //
            // ⚠️ Se mira DESPUÉS de la idempotencia a propósito: un padre que reenvía su propio
            // formulario no consume plaza, así que una reserva llena sigue admitiendo su reenvío.
            // ⚠️⚠️ **La EXCEPCIÓN de la invitación digital** (§4.5·7, `#576`): una firma que llega atada a
            // un «sí» **no descuenta plaza, porque esa plaza ya tiene dueño** — `GuardianPlaces` la
            // cuenta desde que el padre contestó. Sin esta excepción, el padre que dijo «sí» con la
            // lista completa **no podría firmar**: el propio «sí» que le reservó el sitio le cerraría
            // la puerta, que es la peor forma de fallar que tiene esta feature.
            //
            // ⚠️ Se pregunta al CONTRATO, no al parámetro: un id inventado, de otra fiesta o de una
            // respuesta ya descartada no ata nada y el tope se aplica como siempre.
            $tied = $invitationReplyId !== null
                && $this->guests->isCommittedReply($invitationReplyId, $reservationId);

            if (! $tied && $this->places->freeIn($reservation) < 1) {
                throw GuardianAuthorizationRefusedException::full($reservationId, $reservation->quantity);
            }

            $authorization = GuardianAuthorization::create([
                'order_item_id' => $reservationId,
                // ⚠️ Solo si el contrato lo confirmó. Es lo que permite a `GuardianPlaces` dejar de
                // contar ese «sí» aparte: a partir de aquí, la plaza la cuenta el justificante.
                'invitation_reply_id' => $tied ? $invitationReplyId : null,
                'minor_name' => mb_substr(trim($data['minor_name']), 0, GuardianAuthorization::NAME_MAX),
                'minor_surname' => mb_substr(trim($data['minor_surname']), 0, GuardianAuthorization::SURNAME_MAX),
                'minor_key' => $key,
                'minor_born_on' => $data['minor_born_on'],
                'guardian_name' => mb_substr(trim($data['guardian_name']), 0, GuardianAuthorization::NAME_MAX),
                'guardian_surname' => mb_substr(trim($data['guardian_surname']), 0, GuardianAuthorization::SURNAME_MAX),
                'guardian_relationship' => $data['guardian_relationship'],
                'guardian_email' => $this->trimmedOrNull($data['guardian_email'] ?? null, GuardianAuthorization::EMAIL_MAX),
                'guardian_phone' => $this->trimmedOrNull($data['guardian_phone'] ?? null, GuardianAuthorization::PHONE_MAX),
            ]);

            $signature = $this->signer->sign(
                $responsible,
                $version,
                $request->forGuestMinor((int) $authorization->getKey()),
            );

            return ['authorization' => $authorization, 'signature' => $signature, 'created' => true];
        });
    }

    /**
     * ¿Es el mismo adulto el que vuelve? Se compara por la MISMA normalización que identifica al
     * menor: un reenvío desde el móvil puede traer «MARÍA LÓPEZ» donde antes puso «maría lopez», y
     * eso no es otro progenitor.
     *
     * @param  array{guardian_name:string, guardian_surname:string}  $data
     */
    private function sameGuardian(GuardianAuthorization $existing, array $data): bool
    {
        return GuardianAuthorization::keyFor($existing->guardian_name, $existing->guardian_surname)
            === GuardianAuthorization::keyFor($data['guardian_name'], $data['guardian_surname']);
    }

    private function trimmedOrNull(?string $value, int $max): ?string
    {
        $clean = trim((string) $value);

        return $clean === '' ? null : mb_substr($clean, 0, $max);
    }
}
