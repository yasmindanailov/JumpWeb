<?php

namespace App\Domain\Platform\Services;

use DateTimeInterface;
use DateTimeZone;
use Illuminate\Support\Carbon;

/**
 * **Un evento de calendario descargable** (`.ics`, RFC 5545) — T5·4 de
 * `docs/specs/celebracion-e-invitacion.md` §4.6, `DECISIONES #705`.
 *
 * Nace para «Añadir al calendario» de la invitación digital, pero **no sabe nada de invitaciones**: le
 * das un evento y te devuelve el texto del fichero. Vive en Platform porque cualquier superficie del
 * producto puede querer ofrecerlo —una reserva, una excursión— y ninguna debería reinventar el formato.
 *
 * ## ⚠️⚠️ La hora es de PARED, y por eso lleva `TZID` con su `VTIMEZONE`
 *
 * Las franjas de este producto son hora de pared del parque: «17:00» significa las cinco **allí**
 * (`§7.2·R13`, la trampa de `#426`). Escribir `20261004T170000Z` —la cifra de pared con una `Z` pegada—
 * **adelanta la fiesta una o dos horas** en el móvil del padre. Y escribir el instante en UTC, aunque
 * sea correcto hoy, ata el evento a las reglas horarias de HOY: si el país cambia sus cambios de hora
 * antes de la fiesta, el evento se mueve solo. Un evento local se declara **local**.
 *
 * ⚠️ **`TZID` a pelo no basta**: la norma pide que el fichero traiga la definición de esa zona, y hay
 * lectores estrictos que sin ella descartan el evento. Se emite un `VTIMEZONE` con las dos últimas
 * observancias reales anteriores al evento —las que salen de las transiciones de PHP, no de una tabla
 * escrita a mano—, que es lo que un lector necesita para colocarlo. Una zona sin cambios de hora emite
 * una sola observancia.
 *
 * ⚠️ **El `UID` es estable**: descargarlo dos veces ACTUALIZA el evento en vez de duplicarlo, que es lo
 * que hace un padre que vuelve a abrir la invitación.
 *
 * ⚠️ Es un value object PURO —sin BD ni facades—, así que su prueba vive en `tests/Unit`
 * (`CONVENCIONES §3.ter`).
 */
final class CalendarFile
{
    /** Lo que se responde en `Content-Type`. */
    public const MIME = 'text/calendar; charset=utf-8';

    /** RFC 5545 §3.1: las líneas se pliegan a 75 octetos, y el corte no parte un carácter UTF-8. */
    private const LINE_OCTETS = 75;

    /**
     * El texto del `.ics` de UN evento.
     *
     * @param  string  $uid  identificador ESTABLE del evento (ver el docblock de la clase)
     * @param  DateTimeInterface  $startsAt  el INSTANTE de inicio; se escribe como hora de pared de `$timezone`
     * @param  DateTimeInterface  $endsAt  el instante de fin
     * @param  string  $timezone  la zona del parque (`DisplayTime::timezone()`)
     */
    public static function event(
        string $uid,
        string $summary,
        DateTimeInterface $startsAt,
        DateTimeInterface $endsAt,
        string $timezone,
        ?string $location = null,
        ?string $description = null,
    ): string {
        $zone = new DateTimeZone($timezone);
        $start = Carbon::instance(Carbon::parse($startsAt))->setTimezone($zone);
        $end = Carbon::instance(Carbon::parse($endsAt))->setTimezone($zone);

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//JumpWeb//Calendario//ES',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            ...self::timezoneComponent($zone, $start),
            'BEGIN:VEVENT',
            'UID:'.self::text($uid),
            'DTSTAMP:'.Carbon::now('UTC')->format('Ymd\THis\Z'),
            'DTSTART;TZID='.$timezone.':'.$start->format('Ymd\THis'),
            'DTEND;TZID='.$timezone.':'.$end->format('Ymd\THis'),
            'SUMMARY:'.self::text($summary),
        ];

        if (($location = trim((string) $location)) !== '') {
            $lines[] = 'LOCATION:'.self::text($location);
        }
        if (($description = trim((string) $description)) !== '') {
            $lines[] = 'DESCRIPTION:'.self::text($description);
        }

        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        // CRLF, que es lo que pide la norma: hay lectores que con `\n` a secas se plantan.
        return implode("\r\n", array_map(self::fold(...), $lines))."\r\n";
    }

    /**
     * El `VTIMEZONE` de la zona, con las observancias que hacen falta para colocar un evento en
     * `$moment`: las **dos últimas transiciones reales** anteriores a ese momento.
     *
     * ⚠️ Se leen de PHP (`DateTimeZone::getTransitions()`), nunca de una tabla escrita a mano: las
     * reglas de cambio de hora cambian por ley y una copia nuestra envejecería en silencio.
     *
     * @return list<string>
     */
    private static function timezoneComponent(DateTimeZone $zone, Carbon $moment): array
    {
        $ventana = (clone $moment)->subYears(2);
        $transiciones = $zone->getTransitions($ventana->getTimestamp(), $moment->getTimestamp()) ?: [];

        $lines = ['BEGIN:VTIMEZONE', 'TZID:'.$zone->getName()];

        // La primera entrada es el ESTADO al abrir la ventana, no una transición: las transiciones son
        // las siguientes. Sin ninguna, la zona no cambia de hora en dos años y basta una observancia
        // fija (un `VTIMEZONE` sin ninguna no valida).
        //
        // ⚠️⚠️ **Se trabaja con los ÍNDICES del array original, no con una copia recortada.** Recortarlo
        // reindexa, y entonces «la transición anterior» deja de ser la anterior: el `TZOFFSETFROM` salía
        // igual que el `TZOFFSETTO` —una observancia que no cambia nada— y el `DTSTART` de cada una,
        // una hora movido. Los casos unitarios no lo vieron porque miraban el `TO`; lo cazó el fichero
        // servido de verdad.
        $total = count($transiciones);
        $indices = array_values(array_filter([$total - 2, $total - 1], static fn (int $i): bool => $i >= 1));

        if ($indices === []) {
            $offset = (int) ($transiciones[0]['offset'] ?? $moment->utcOffset() * 60);
            $lines = [...$lines, ...self::observance(
                isDst: false,
                start: (clone $moment)->subYears(2),
                offsetFrom: $offset,
                offsetTo: $offset,
                abbr: (string) ($transiciones[0]['abbr'] ?? $zone->getName()),
            )];

            return [...$lines, 'END:VTIMEZONE'];
        }

        foreach ($indices as $i) {
            $t = $transiciones[$i];
            $offsetFrom = (int) $transiciones[$i - 1]['offset'];
            // El `DTSTART` de una observancia se escribe en hora local **del offset que se deja**
            // (RFC 5545 §3.6.5), no en la del que entra.
            $inicio = Carbon::createFromTimestampUTC((int) $t['ts'])->addSeconds($offsetFrom);

            $lines = [...$lines, ...self::observance(
                isDst: (bool) $t['isdst'],
                start: $inicio,
                offsetFrom: $offsetFrom,
                offsetTo: (int) $t['offset'],
                abbr: (string) $t['abbr'],
            )];
        }

        return [...$lines, 'END:VTIMEZONE'];
    }

    /** @return list<string> */
    private static function observance(bool $isDst, Carbon $start, int $offsetFrom, int $offsetTo, string $abbr): array
    {
        $tag = $isDst ? 'DAYLIGHT' : 'STANDARD';

        return [
            'BEGIN:'.$tag,
            'DTSTART:'.$start->format('Ymd\THis'),
            'TZOFFSETFROM:'.self::offset($offsetFrom),
            'TZOFFSETTO:'.self::offset($offsetTo),
            'TZNAME:'.self::text($abbr),
            'END:'.$tag,
        ];
    }

    /** Segundos → `+0200`. */
    private static function offset(int $seconds): string
    {
        $signo = $seconds < 0 ? '-' : '+';
        $seconds = abs($seconds);

        return $signo.sprintf('%02d%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60));
    }

    /** Escapado de un valor TEXT (RFC 5545 §3.3.11). El orden importa: la barra, primero. */
    private static function text(string $value): string
    {
        return str_replace(
            ['\\', ';', ',', "\r\n", "\n", "\r"],
            ['\\\\', '\\;', '\\,', '\\n', '\\n', '\\n'],
            $value,
        );
    }

    /** Plegado a 75 octetos con una continuación que empieza por espacio, sin partir un carácter. */
    private static function fold(string $line): string
    {
        if (strlen($line) <= self::LINE_OCTETS) {
            return $line;
        }

        $out = '';
        $actual = '';
        $primera = true;
        foreach (mb_str_split($line) as $char) {
            // La continuación gasta un octeto en su espacio inicial, así que cabe uno menos.
            $tope = $primera ? self::LINE_OCTETS : self::LINE_OCTETS - 1;
            if (strlen($actual) + strlen($char) > $tope) {
                $out .= ($primera ? '' : "\r\n ").$actual;
                $actual = '';
                $primera = false;
            }
            $actual .= $char;
        }

        return $out.($primera ? '' : "\r\n ").$actual;
    }
}
