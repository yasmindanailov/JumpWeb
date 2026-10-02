<?php

namespace Tests\Unit;

use App\Domain\Content\Services\ReviewKeywords;
use PHPUnit\Framework\TestCase;

/**
 * **Las palabras de la reseña del día de la Puerta, y si un texto dice alguna** (`ReviewKeywords`, la P3 de
 * `docs/specs/puerta-nueva.md` §4.4: D17 y D18).
 *
 * Valor PURO —sin base ni fachadas fuera de `fromSettings()`—, así que va en `tests/Unit` (`CONVENCIONES §3.ter`). Cada
 * guarda tiene su mutante en `scripts/mutar-puerta-p1.sh` («reseña · …»).
 */
class ReviewKeywordsTest extends TestCase
{
    public function test_capitals_and_accents_do_not_matter_on_either_side(): void
    {
        $palabras = ReviewKeywords::from("Monitor\nPERSONAL");

        $this->assertTrue($palabras->matches('Los MONITORES, un diez'));
        $this->assertTrue($palabras->matches('la monitorá estuvo atenta'));
        $this->assertTrue($palabras->matches('El personal, encantador'));
        // La tilde DESCOMPUESTA (la «e» y su tilde, dos caracteres) pliega igual que la compuesta.
        $this->assertTrue(ReviewKeywords::from('equipo')->matches("Un e\u{0301}quipo de diez"));
        $this->assertTrue(ReviewKeywords::from('Ñandú')->matches('vimos el nandu'));
    }

    public function test_a_word_matches_at_its_start_and_may_go_on_but_never_inside_another(): void
    {
        $palabras = ReviewKeywords::from('monitor');

        $this->assertTrue($palabras->matches('monitora'));
        $this->assertTrue($palabras->matches('un co-monitor muy majo'));
        $this->assertFalse($palabras->matches('lo desmonitorizaron'));
        $this->assertFalse($palabras->matches('elmonitor'));
        $this->assertFalse($palabras->matches('2monitores'));
        $this->assertFalse($palabras->matches('Todo perfecto, repetiremos.'));
    }

    public function test_several_words_match_only_together_with_any_space_between(): void
    {
        $palabras = ReviewKeywords::from('muy atentos');

        $this->assertTrue($palabras->matches("Fueron muy\n  atentos con los peques"));
        // La última palabra puede seguir, pero no cambiar: «atentas» no empieza por «atentos».
        $this->assertFalse($palabras->matches('Fueron muy atentas'));
        $this->assertFalse($palabras->matches('muy amables y atentos'));
        $this->assertFalse($palabras->matches('muy, atentos'));
        $this->assertFalse($palabras->matches('atentos, muy'));
    }

    /** En una escritura sin espacios entre palabras no hay principio de palabra: la palabra casa dentro del texto. */
    public function test_a_script_without_spaces_matches_inside_the_text(): void
    {
        $this->assertTrue(ReviewKeywords::from('伊琳娜')->matches('我们的伊琳娜很好'));
        $this->assertTrue(ReviewKeywords::from('ทีมงาน')->matches('ประทับใจทีมงานมาก'));
        // Y una escritura CON espacios que `Str::ascii` no sabe escribir sigue pidiendo el principio de palabra.
        $this->assertTrue(ReviewKeywords::from('שלום')->matches('אמרו שלום לכולם'));
        $this->assertFalse(ReviewKeywords::from('שלום')->matches('אמרוxשלום'));
    }

    /** Lo que `Str::ascii` vacía se queda TAL CUAL, sin quitarle marcas: quitárselas le quitaba las vocales al tailandés. */
    public function test_folding_keeps_what_ascii_cannot_write(): void
    {
        $this->assertSame('伊琳娜很好', ReviewKeywords::fold('伊琳娜很好'));
        $this->assertSame('ทีมงาน', ReviewKeywords::fold('ทีมงาน'));
        $this->assertSame('irina y nandu', ReviewKeywords::fold('Ирина y Ñandú'));
    }

    public function test_without_words_nothing_matches(): void
    {
        $this->assertTrue(ReviewKeywords::from('')->isEmpty());
        $this->assertTrue(ReviewKeywords::from("  \n\n \t ")->isEmpty());
        $this->assertFalse(ReviewKeywords::from('')->matches('Los monitores, un diez'));
    }

    /** D17: limpias, sin vacías ni repetidas por su forma PLEGADA; se queda la primera tal como se escribió. */
    public function test_the_saved_list_is_clean_and_keeps_the_first_spelling(): void
    {
        $this->assertSame("Monitor\nmuy atentos\nIrene", ReviewKeywords::clean("Monitor\n  muy   atentos \n\nIrene\nMONITOR\nmonitór"));
        $this->assertSame(['Monitor', 'muy atentos', 'Irene'], ReviewKeywords::entries("Monitor\r\nmuy atentos\rIrene\n"));
    }

    /** La Puerta lee como mucho {@see ReviewKeywords::MAX_ENTRIES}: un ajuste escrito saltándose el formulario no la frena. */
    public function test_the_gate_reads_at_most_the_maximum_of_entries(): void
    {
        // «clave4z» no es el comienzo de «clave41z»: con «palabra4» y «palabra41», la primera casaría la segunda por su principio.
        $muchas = implode("\n", array_map(static fn (int $i): string => 'clave'.$i.'z', range(1, ReviewKeywords::MAX_ENTRIES + 5)));

        $this->assertCount(ReviewKeywords::MAX_ENTRIES, ReviewKeywords::entries($muchas));
        $this->assertTrue(ReviewKeywords::from($muchas)->matches('la clave'.ReviewKeywords::MAX_ENTRIES.'z sale'));
        $this->assertFalse(ReviewKeywords::from($muchas)->matches('la clave'.(ReviewKeywords::MAX_ENTRIES + 1).'z no'));
    }

    public function test_the_form_stops_too_many_entries_and_a_too_long_one(): void
    {
        $cuarenta = implode("\n", array_map(static fn (int $i): string => 'palabra'.$i, range(1, ReviewKeywords::MAX_ENTRIES)));
        $this->assertFalse(ReviewKeywords::tooMany($cuarenta."\n\n  \n"));
        $this->assertTrue(ReviewKeywords::tooMany($cuarenta."\notra"));

        $justa = str_repeat('a', ReviewKeywords::MAX_LENGTH);
        $this->assertNull(ReviewKeywords::tooLong("monitor\n".$justa));
        $this->assertSame($justa.'b', ReviewKeywords::tooLong("monitor\n  ".$justa.'b  '));
    }

    /** Un byte roto no tumba la Puerta: `mb_strtolower` lo cambia por «?» y el resto se lee (medido el 02-10). */
    public function test_a_broken_byte_does_not_break_the_match(): void
    {
        $this->assertTrue(ReviewKeywords::from('monitor')->matches("Los monitores\xC3\x28 un diez"));
        $this->assertFalse(ReviewKeywords::from('irene')->matches("Los monitores\xC3\x28 un diez"));
    }
}
