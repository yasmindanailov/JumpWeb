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
 * ⚠️ Lo que SÍ está sellado son las CONDICIONES de las que deriva (spec §21, `DECISIONES #284` D1):
 * la familia, los tramos y los precios viven en `order_items.age_family_seal` desde que la reserva
 * nace, no en el catálogo vivo. El veredicto sigue siendo una lectura; lo que cambió es de qué.
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
     * @param  bool  $sealed  el veredicto sale del SELLO de la reserva (§21). Importa sobre todo
     *                        cuando `applies` es `false`: con sello, «no aplica» es una AFIRMACIÓN
     *                        («se vendió sin condiciones por edad») y puede retirar un suplemento;
     *                        sin sello es un SILENCIO y no puede retirar nada
     * @param  bool  $staleSeal  la reserva lleva un sello que NO corresponde a su pack o a su fecha:
     *                           alguien la movió sin re-sellarla. El veredicto calla y la ficha lo dice
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
        public bool $sealed = false,
        public bool $staleSeal = false,
    ) {}

    /**
     * El veredicto de una reserva que no participa: ni etiqueta, ni propuesta, ni aviso.
     *
     * Tres orígenes distintos, y **no significan lo mismo** (§21.5): sin sello (silencio), sello
     * caducado (silencio, y se enseña) y sello que dice «sin condiciones» (afirmación).
     */
    public static function notApplicable(bool $sealed = false, bool $staleSeal = false): self
    {
        return new self(false, false, 0, 0, 0, [], null, sealed: $sealed, staleSeal: $staleSeal);
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
     *
     * ⚠️ Es la pregunta de la PRESENTACIÓN («¿queda algo sin resolver?»), no la del dinero: para el
     * dinero vale {@see allAgesDeclared}. Una edad sin producto deja esto en `false` y aquello en
     * `true`, y las dos respuestas son correctas (spec §22.2).
     */
    public function isComplete(): bool
    {
        return $this->withoutAge === 0 && $this->outOfRange === 0;
    }

    /**
     * ¿Están DECLARADAS todas las edades? Es la pregunta que decide si el DINERO puede moverse
     * (`DECISIONES #284` D6 y `#285` §20.6, spec §22.2): **solo una edad que FALTA congela el
     * importe**. Una edad sin producto es un estado CONOCIDO —«no hay producto para ella»—, no una
     * incógnita: esa ficha no genera ninguna línea y las demás se tarifican con normalidad. Hasta el
     * 2026-08-31 contaba como incompleto y congelaba el dinero de toda la fiesta (medido: un bebé de
     * 0 años impedía que el cargo bajara aunque el cliente corrigiera las otras edades).
     */
    public function allAgesDeclared(): bool
    {
        return $this->withoutAge === 0;
    }
}
