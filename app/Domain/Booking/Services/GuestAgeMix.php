<?php

namespace App\Domain\Booking\Services;

/**
 * El VEREDICTO de mezcla de edades de UNA reserva (`docs/specs/cumple-mixto.md` §9·4).
 *
 * Responde a «¿esta fiesta es MIXTA, y cuánto costaría regularizarla?». Es un valor puro: no toca
 * BD, no escribe nada y **no se persiste NUNCA**.
 *
 * ▶ **Por qué derivado y no sellado.** El post-form es editable hasta el día del evento
 * (`sistemas/POSTFORM-INVITADOS.md` §6), así que el veredicto va y viene: se declara un niño de 8
 * (mixta), se corrige a 6 (ya no), se vuelve a subir. Un sello habría que deshacerlo, y si el cargo
 * ya salió, deshacerlo es un movimiento de dinero y no un `update` (spec §4). Derivándolo, la
 * corrección es gratis: la etiqueta desaparece sola.
 *
 * ⚠️ **La etiqueta describe un HECHO, no un cobro.** `mixed` puede ser `true` con
 * `surchargeCents === 0` —los dos productos cuestan lo mismo ese día, que es el caso de la
 * instalación de desarrollo (spec §8.8)— o con `surchargeCents === null` —falta el precio de algún
 * producto para ese día, o la reserva no tiene franja—. Ninguno de los dos es un error: son
 * respuestas, y quien las pinta tiene que distinguirlas de «no aplica».
 */
final class GuestAgeMix
{
    /**
     * @param  bool  $applies  el pack participa en una familia por edad Y su post-form pide la edad
     * @param  bool  $mixed  hay invitados que corresponden a un producto distinto del reservado
     * @param  int  $guests  fichas de invitado de la reserva (= su cantidad)
     * @param  int  $withoutAge  fichas sin edad declarada: el veredicto es PARCIAL mientras haya
     * @param  int  $outOfRange  edades que NINGÚN producto de la familia cubre (config incompleta)
     * @param  list<array{type_id:int, name:string, count:int, unit_cents:int|null}>  $upgrades  a qué
     *                                                                                           producto corresponde cada grupo de invitados y cuánto cuesta la diferencia POR CABEZA
     * @param  int|null  $surchargeCents  total a cobrar; `null` = no se pudo tarificar (§8.4)
     * @param  int|null  $savingsCents  lo que la fiesta costaría MENOS si cada invitado estuviera en
     *                                  su pack — **informativo, NO es dinero** (§14)
     * @param  int|null  $basePriceCents  lo que cuesta por invitado el pack reservado ese día
     */
    public function __construct(
        public bool $applies,
        public bool $mixed,
        public int $guests,
        public int $withoutAge,
        public int $outOfRange,
        public array $upgrades,
        public ?int $surchargeCents,
        public ?int $savingsCents = null,
        public ?int $basePriceCents = null,
    ) {}

    /** El veredicto de una reserva que no participa: ni etiqueta, ni propuesta, ni aviso. */
    public static function notApplicable(): self
    {
        return new self(false, false, 0, 0, 0, [], null);
    }

    /**
     * ¿Hay algo que decir de que la fiesta salga MÁS BARATA en el régimen que corresponde?
     *
     * ⚠️⚠️ **Esto NO es dinero y no puede entrar en el desglose como si lo fuera.**
     * `[owner, 2026-08-29]`: «no se devuelve dinero automáticamente, solo avisar al operador y al
     * cliente de que la reserva es X € más barata por ese cambio». El desglose ya tiene un canal
     * «Pendiente de devolución» y es **deuda real** del parque, con `PAY-16`/`PAY-17` cuadrando
     * sobre él: meter aquí una cifra informativa lo descuadraría y —peor— el cliente leería una
     * deuda que no existe. Va como AVISO, aparte, y no suma en ningún total.
     */
    public function hasSavings(): bool
    {
        return ($this->savingsCents ?? 0) > 0;
    }

    /** Nº de invitados que corresponden a otro producto (la cantidad del suplemento propuesto). */
    public function upgradedGuests(): int
    {
        return array_sum(array_column($this->upgrades, 'count'));
    }

    /**
     * ¿Hay una propuesta de cobro que enseñarle al operador? Exige las dos cosas: que la fiesta sea
     * mixta y que el importe se haya podido resolver. Un `> 0` no basta como condición de pintado
     * —un suplemento de 0,00 € es información útil («es mixta y no cuesta nada»)—, pero sí para
     * decidir si hay algo que cobrar.
     */
    public function isPriceable(): bool
    {
        return $this->mixed && $this->surchargeCents !== null;
    }

    /**
     * ¿El veredicto se ha calculado sobre datos COMPLETOS? Con fichas sin edad, «no es mixta» solo
     * significa «todavía no consta que lo sea». La diferencia importa: es lo que separa un aviso
     * de una afirmación, y quien pinta esto no puede fingir que no existe.
     */
    public function isComplete(): bool
    {
        return $this->withoutAge === 0 && $this->outOfRange === 0;
    }
}
