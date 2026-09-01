<?php

namespace App\Domain\Identity\Services;

use App\Domain\Booking\Contracts\AuthorizableReservations;
use App\Domain\Identity\Models\Dependent;
use App\Domain\Identity\Models\LegalDocumentVersion;
use App\Domain\Identity\Models\WaiverSignature;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\DisplayTime;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Fase 6 · waiver — presentador del REGISTRO probatorio de una firma para su PDF
 * (`docs/specs/waiver-probatorio.md` §4.5): el texto firmado íntegro desde el SNAPSHOT (nunca desde
 * el CMS), la identidad del firmante tal y como estaba al firmar, fecha/hora con zona, ip,
 * user-agent, canal, versión, hashes y —si la declaró un operador— quién.
 *
 * Como `ReservationSlip`: testeable sin renderizar PDF y con la vista delgada (dompdf). El
 * controlador decide permiso, idioma y auditoría; aquí solo hay datos.
 *
 * ⚠️ Determinista a propósito: el mismo registro produce el mismo documento dos veces (§6). Por eso
 * no lleva «generado el»: la fecha que prueba es la de la aceptación, no la de la impresión.
 */
final class WaiverProof
{
    private function __construct(
        public readonly WaiverSignature $signature,
        public readonly LegalDocumentVersion $version,
    ) {}

    public static function make(WaiverSignature $signature): self
    {
        $signature->loadMissing(['version', 'user', 'declaredBy', 'dependent', 'authorization']);

        return new self($signature, $signature->version);
    }

    // ─── El texto firmado (snapshot) ─────────────────────────────────────────

    public function locale(): string
    {
        return (string) $this->version->locale;
    }

    public function title(): string
    {
        return (string) $this->version->title;
    }

    /**
     * @return list<array{h:string,p:string}>
     */
    public function sections(): array
    {
        return $this->version->sections();
    }

    public function versionLabel(): string
    {
        return $this->version->label();
    }

    public function publishedAtLabel(): string
    {
        return $this->inZone($this->version->published_at)->format('d/m/Y H:i');
    }

    // ─── El firmante ─────────────────────────────────────────────────────────

    public function holderName(): string
    {
        return $this->signature->holderName() ?? '—';
    }

    public function holderEmail(): string
    {
        return $this->signature->holderEmail() ?? '—';
    }

    /** ¿La cuenta está hoy anonimizada? La prueba sigue identificando por su copia (v2). */
    public function holderIsAnonymised(): bool
    {
        return (bool) $this->signature->user?->isAnonymized();
    }

    public function isForHolder(): bool
    {
        return $this->signature->isForHolder();
    }

    public function subjectId(): ?int
    {
        return $this->signature->subject_id;
    }

    /**
     * El menor en cuyo nombre se firmó, TAL Y COMO ESTABA al firmar (`menores-a-cargo.md` §4.2: la
     * copia va en la fila, esquema v3). Datos declarados por el titular, no verificados — el PDF lo dice.
     */
    public function subjectName(): ?string
    {
        return $this->signature->subjectName();
    }

    public function subjectBornOnLabel(): ?string
    {
        return $this->signature->subject_born_on?->format('d/m/Y');
    }

    /**
     * ¿Es el justificante de un menor INVITADO a una reserva
     * (`specs/waiver-por-reserva.md` §4.13)? Entonces **el titular de la cuenta NO es quien firma**:
     * es el RESPONSABLE de la reserva, y quien acepta es un adulto sin cuenta cuyos datos viven en
     * `signer_*`.
     */
    public function isForGuestMinor(): bool
    {
        return $this->signature->isForGuestMinor();
    }

    public function signerName(): ?string
    {
        return $this->signature->signer_name;
    }

    public function signerRelationship(): ?string
    {
        return $this->signature->signer_relationship;
    }

    public function signerEmail(): ?string
    {
        return $this->signature->signer_email;
    }

    public function signerPhone(): ?string
    {
        return $this->signature->signer_phone;
    }

    /**
     * La referencia del pedido al que va atado el justificante, si es de un menor invitado.
     *
     * ⚠️ Sale de `Booking\Contracts\AuthorizableReservations`, **no de un `DB::table('orders')`**. Un
     * `DB::table()` habría funcionado y `ModuleBoundariesTest` **no lo habría visto** —escanea
     * referencias a CLASES con el tokenizador, no cadenas SQL—, que es exactamente la razón por la
     * que la frontera se respeta a mano cuando el guardián no llega.
     */
    public function orderCode(): ?string
    {
        $reservationId = $this->signature->authorization?->order_item_id;
        if ($reservationId === null) {
            return null;
        }

        return $this->orderCode ??= app(AuthorizableReservations::class)->find((int) $reservationId)?->orderCode;
    }

    /**
     * A QUÉ visita autoriza esta prueba (`#343`): el producto y el día.
     *
     * ⚠️ **Faltaba, y en un documento probatorio importa**: el PDF decía la referencia del pedido y
     * un pedido puede tener dos visitas. Quien lea esta prueba dentro de dos años tiene que poder
     * decir a cuál de las dos autorizó el padre.
     */
    public function reservationLabel(): ?string
    {
        $reservationId = $this->signature->authorization?->order_item_id;
        if ($reservationId === null) {
            return null;
        }

        $reservation = app(AuthorizableReservations::class)->find((int) $reservationId);
        if ($reservation === null) {
            return null;
        }

        return $reservation->date === null
            ? $reservation->productName
            : $reservation->productName.' · '.DisplayTime::format(
                Carbon::parse($reservation->date), 'd/m/Y',
            );
    }

    private ?string $orderCode = null;

    // ─── La aceptación ───────────────────────────────────────────────────────

    public function acceptedTz(): string
    {
        return (string) $this->signature->accepted_tz;
    }

    public function acceptedAtLabel(): string
    {
        return $this->inZone($this->signature->accepted_at)->format('d/m/Y H:i:s');
    }

    public function acceptedAtUtc(): string
    {
        return Carbon::parse($this->signature->accepted_at)->utc()->format('Y-m-d\TH:i:s\Z');
    }

    public function ip(): string
    {
        return (string) ($this->signature->ip ?? '—');
    }

    public function userAgent(): string
    {
        return (string) ($this->signature->user_agent ?? '—');
    }

    public function channel(): string
    {
        return (string) $this->signature->channel;
    }

    public function isDeclared(): bool
    {
        return $this->signature->isDeclaredByOperator();
    }

    public function declaredByName(): ?string
    {
        return $this->signature->declaredBy?->name;
    }

    // ─── Integridad ──────────────────────────────────────────────────────────

    public function documentHash(): string
    {
        return (string) $this->signature->document_hash;
    }

    public function signatureHash(): string
    {
        return (string) $this->signature->hash;
    }

    public function prevHash(): ?string
    {
        return $this->signature->prev_hash;
    }

    public function canonicalVersion(): int
    {
        return (int) ($this->signature->canonical_version ?? 1);
    }

    /**
     * La fila da su hash, la versión da el suyo, y la firma apunta al texto que dice apuntar. Es lo
     * que un auditor pregunta primero, así que el documento lo DICE — con la fecha de la
     * comprobación fuera (el resultado es una propiedad del registro, no del momento).
     */
    public function integrityOk(): bool
    {
        return $this->signature->verifyHash()
            && $this->version->verifyHash()
            && hash_equals((string) $this->version->body_hash, (string) $this->signature->document_hash);
    }

    // ─── Conservación (§4.6) ─────────────────────────────────────────────────

    public function retainUntilLabel(): ?string
    {
        if ($this->isForHolder()) {
            $months = WaiverSettings::retentionMonths();

            return $months === null ? null : $this->inZone($this->signature->accepted_at)->addMonths($months)->format('d/m/Y');
        }

        // La firma de un MENOR se conserva N meses después de su 18.º cumpleaños (`DECISIONES #197`).
        $months = WaiverSettings::dependentRetentionMonths();
        $born = $this->signature->subject_born_on;
        if ($months === null || $born === null) {
            return null;
        }

        return $born->addYears(Dependent::ADULT_AGE)->addMonths($months)->format('d/m/Y');
    }

    public function businessName(): string
    {
        return (string) (Setting::value('business.name') ?: config('app.name'));
    }

    private function inZone(mixed $value): Carbon
    {
        return Carbon::parse($value)->setTimezone($this->acceptedTz());
    }
}
