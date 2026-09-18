<?php

namespace Tests\Unit;

use App\Domain\Platform\Services\CalendarFile;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

/**
 * **El `.ics` de «Añadir al calendario»** (T5·4 de `docs/specs/celebracion-e-invitacion.md` §4.6;
 * `DECISIONES #705`).
 *
 * Value object PURO —sin BD ni facades—, así que va en `tests/Unit` con `PHPUnit\Framework\TestCase`
 * (`CONVENCIONES §3.ter`).
 *
 * ⚠️⚠️ **El caso que sostiene el diseño no aserta una cadena: RECONSTRUYE el instante.** Un test que
 * comprobara «pone 170000» daría verde con `20261004T170000Z`, que es exactamente el defecto de
 * `§7.2·R13` —la cifra de pared con una `Z` pegada— y le movería la fiesta al padre una o dos horas.
 * Lo que se mide es lo que hace un lector: leer `TZID` + la hora local y calcular el momento.
 */
class CalendarFileTest extends TestCase
{
    private const MADRID = 'Europe/Madrid';

    /**
     * ❗❗ Hora de PARED: leído como lo lee un calendario, el evento cae en el instante correcto — y la
     * cifra que se escribe es la del reloj del parque, no la de UTC.
     *
     * Se prueba en VERANO y en INVIERNO a propósito: con un solo caso, una implementación que clavara
     * `+01:00` pasaría media parte del año.
     */
    public function test_the_event_lands_on_the_right_instant_in_summer_and_in_winter(): void
    {
        foreach ([
            ['2026-07-04 17:00:00', '+02:00'],   // verano: CEST
            ['2026-12-05 17:00:00', '+01:00'],   // invierno: CET
        ] as [$pared, $offset]) {
            $inicio = new DateTimeImmutable($pared, new DateTimeZone(self::MADRID));
            $fin = $inicio->modify('+120 minutes');

            $ics = CalendarFile::event('uid-1@jumpweb', 'Cumple de Lucía', $inicio, $fin, self::MADRID);

            // La cifra es la del reloj del parque, sin `Z`.
            $this->assertStringContainsString(
                'DTSTART;TZID=Europe/Madrid:'.$inicio->format('Ymd\THis'),
                $ics,
                "la hora de pared de {$pared} no se escribió tal cual"
            );
            $this->assertStringNotContainsString('DTSTART:'.$inicio->format('Ymd\THis').'Z', $ics);

            // Y leído como lo lee un calendario, es el instante correcto.
            [$leido, $leidoFin] = $this->readEvent($ics);
            $this->assertSame($inicio->getTimestamp(), $leido->getTimestamp(), "el instante de inicio se movió en {$pared}");
            $this->assertSame($fin->getTimestamp(), $leidoFin->getTimestamp(), "el instante de fin se movió en {$pared}");
            $this->assertSame($offset, $leido->format('P'), 'el desfase horario no es el de esa época del año');
        }
    }

    /**
     * ⚠️ `TZID` a pelo no basta: la norma pide la definición de la zona dentro del fichero, y hay
     * lectores estrictos que sin ella descartan el evento.
     */
    public function test_the_file_carries_the_definition_of_its_timezone(): void
    {
        $inicio = new DateTimeImmutable('2026-10-04 17:00:00', new DateTimeZone(self::MADRID));
        $ics = CalendarFile::event('uid-2@jumpweb', 'Cumple', $inicio, $inicio->modify('+2 hours'), self::MADRID);

        $this->assertStringContainsString("BEGIN:VTIMEZONE\r\nTZID:Europe/Madrid", $ics);
        $this->assertStringContainsString('END:VTIMEZONE', $ics);
        // Las dos caras del cambio de hora, con sus desfases: es lo que el lector necesita para colocar
        // un evento de octubre (todavía en CEST) y otro de noviembre (ya en CET).
        $this->assertStringContainsString('TZOFFSETTO:+0200', $ics);
        $this->assertStringContainsString('TZOFFSETTO:+0100', $ics);

        // ❗❗ **Una observancia que ENTRA en el mismo desfase del que viene no cambia nada**, y es
        // exactamente lo que salía cuando la lista de transiciones se recortaba antes de leer «la
        // anterior». El caso de arriba no lo veía porque solo miraba el `TO`; lo cazó el fichero
        // servido por HTTP. Aquí se comprueba el PAR, que es lo que un lector usa.
        $this->assertTrue(
            (bool) preg_match_all('/TZOFFSETFROM:(\S+)\r\nTZOFFSETTO:(\S+)/', $ics, $pares, PREG_SET_ORDER),
            'el fichero no declara los desfases de sus observancias'
        );
        foreach ($pares as $par) {
            $this->assertNotSame(
                $par[1],
                $par[2],
                'una observancia declara que entra en el mismo desfase del que viene: no cambia nada'
            );
        }

        // Y el salto a horario de verano de 2026 en Madrid es a las 02:00 CET → **03:00 escrito en la
        // hora del desfase que se DEJA** (RFC 5545 §3.6.5). Un `DTSTART` en la hora del que entra
        // coloca el cambio una hora tarde.
        $this->assertStringContainsString("BEGIN:DAYLIGHT\r\nDTSTART:20260329T020000\r\nTZOFFSETFROM:+0100", $ics);
    }

    /** Una zona SIN cambios de hora también tiene que salir válida: un `VTIMEZONE` vacío no lo es. */
    public function test_a_zone_without_daylight_saving_still_declares_one_observance(): void
    {
        $inicio = new DateTimeImmutable('2026-10-04 17:00:00', new DateTimeZone('Atlantic/Reykjavik'));
        $ics = CalendarFile::event('uid-3@jumpweb', 'Cumple', $inicio, $inicio->modify('+2 hours'), 'Atlantic/Reykjavik');

        $this->assertSame(1, substr_count($ics, 'BEGIN:STANDARD'), 'una zona sin cambios de hora debe traer UNA observancia');
        $this->assertStringNotContainsString('BEGIN:DAYLIGHT', $ics);
        $this->assertStringContainsString('TZOFFSETTO:+0000', $ics);

        [$leido] = $this->readEvent($ics, 'Atlantic/Reykjavik');
        $this->assertSame($inicio->getTimestamp(), $leido->getTimestamp());
    }

    /**
     * ⚠️ Lo que escribe un anfitrión entra en el fichero: una coma sin escapar **parte el valor en dos**
     * para el lector, y un salto de línea lo rompe entero.
     */
    public function test_what_a_person_wrote_cannot_break_the_file(): void
    {
        $inicio = new DateTimeImmutable('2026-10-04 17:00:00', new DateTimeZone(self::MADRID));

        $ics = CalendarFile::event(
            'uid-4@jumpweb',
            'Cumple de Lucía, Martina; y toda la clase\\',
            $inicio,
            $inicio->modify('+2 hours'),
            self::MADRID,
            "Calle Mayor, 3\nNave 2",
        );

        $this->assertStringContainsString('SUMMARY:Cumple de Lucía\\, Martina\\; y toda la clase\\\\', $ics);
        $this->assertStringContainsString('LOCATION:Calle Mayor\\, 3\\nNave 2', $ics);
        // Y no queda ningún salto crudo dentro de un valor: todas las líneas son cabecera o continuación.
        foreach (explode("\r\n", trim($ics)) as $linea) {
            $this->assertTrue(
                $linea === '' || str_starts_with($linea, ' ') || (bool) preg_match('/^[A-Z-]+[;:]/', $linea),
                "una línea del fichero no es ni cabecera ni continuación: «{$linea}»"
            );
        }
    }

    /** Las líneas se pliegan a 75 octetos **sin partir un carácter**: un acento roto es basura ilegible. */
    public function test_long_lines_fold_without_breaking_a_character(): void
    {
        $inicio = new DateTimeImmutable('2026-10-04 17:00:00', new DateTimeZone(self::MADRID));
        $largo = str_repeat('áé', 80);

        $ics = CalendarFile::event('uid-5@jumpweb', $largo, $inicio, $inicio->modify('+2 hours'), self::MADRID);

        foreach (explode("\r\n", trim($ics)) as $linea) {
            $this->assertLessThanOrEqual(75, strlen($linea), 'una línea pasa de 75 octetos');
        }
        // Desplegado (se quita el CRLF + espacio), el texto vuelve ENTERO y legible.
        $this->assertStringContainsString('SUMMARY:'.$largo, str_replace("\r\n ", '', $ics));
        $this->assertSame($largo, mb_convert_encoding($largo, 'UTF-8', 'UTF-8'), 'el propio fixture no es UTF-8 válido');
    }

    /** El `UID` es ESTABLE: volver a descargarlo actualiza el evento, no lo duplica. */
    public function test_the_same_event_keeps_the_same_uid(): void
    {
        $inicio = new DateTimeImmutable('2026-10-04 17:00:00', new DateTimeZone(self::MADRID));
        $uno = CalendarFile::event('fiesta-42@jumpweb', 'Cumple', $inicio, $inicio->modify('+2 hours'), self::MADRID);
        $dos = CalendarFile::event('fiesta-42@jumpweb', 'Cumple', $inicio, $inicio->modify('+2 hours'), self::MADRID);

        $this->assertStringContainsString('UID:fiesta-42@jumpweb', $uno);
        $this->assertSame(
            preg_replace('/^DTSTAMP:.*$/m', '', $uno),
            preg_replace('/^DTSTAMP:.*$/m', '', $dos),
            'el mismo evento produjo dos ficheros distintos'
        );
    }

    // ── El lector: lo que hace un calendario al abrir el fichero ──────────────

    /**
     * Lee `DTSTART`/`DTEND` **como un calendario**: la hora local escrita, interpretada en la zona que
     * declara `TZID`. Es el instrumento que distingue «pone la cifra bien» de «cae en el momento bien».
     *
     * @return array{0: DateTimeImmutable, 1: DateTimeImmutable}
     */
    private function readEvent(string $ics, string $expectedZone = self::MADRID): array
    {
        $leer = function (string $campo) use ($ics, $expectedZone): DateTimeImmutable {
            // ⚠️ El `\r?` no es decorativo: las líneas acaban en CRLF (lo pide la norma) y sin él la
            // expresión no casa nunca — el primer «defecto» que midió este fichero era suyo.
            $this->assertTrue(
                (bool) preg_match('/^'.$campo.';TZID=([^:\r\n]+):(\d{8}T\d{6})\r?$/m', $ics, $m),
                "el fichero no trae un {$campo} con zona"
            );
            $this->assertSame($expectedZone, $m[1], 'la zona declarada no es la del parque');

            return new DateTimeImmutable($m[2], new DateTimeZone($m[1]));
        };

        return [$leer('DTSTART'), $leer('DTEND')];
    }
}
