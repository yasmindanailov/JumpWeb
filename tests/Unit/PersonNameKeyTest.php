<?php

namespace Tests\Unit;

use App\Domain\Identity\Models\GuardianAuthorization;
use App\Domain\Platform\Services\PersonNameKey;
use PHPUnit\Framework\TestCase;

/**
 * **La clave con la que dos escrituras del mismo nombre se reconocen** (`PersonNameKey`, T4·1 de
 * `docs/specs/celebracion-e-invitacion.md`; `DECISIONES #573`).
 *
 * Value object PURO —sin BD ni facades—, así que va en `tests/Unit` con `PHPUnit\Framework\TestCase`
 * (`CONVENCIONES §3.ter`).
 *
 * ⚠️ El caso que de verdad sostiene el diseño es el de la COLACIÓN: si la normalización se hiciera en
 * la base de datos, `'Perez'` y `'Pérez'` serían el mismo en MySQL y distintos en SQLite —donde corre
 * la suite—, así que una guarda mediría una conducta en el test y la contraria en producción.
 */
class PersonNameKeyTest extends TestCase
{
    public function test_case_accents_and_spacing_collapse_into_the_same_key(): void
    {
        $expected = 'ana perez';

        $this->assertSame($expected, PersonNameKey::for('Ana Pérez'));
        $this->assertSame($expected, PersonNameKey::for('ana perez'));
        $this->assertSame($expected, PersonNameKey::for('  ANA   PÉREZ  '));
        $this->assertSame($expected, PersonNameKey::for("Ana\tPérez"));
    }

    public function test_two_different_children_never_share_a_key(): void
    {
        $this->assertNotSame(PersonNameKey::for('Hugo Ruiz'), PersonNameKey::for('Hugo Ruiz Gil'));
        $this->assertNotSame(PersonNameKey::for('Ana Pérez'), PersonNameKey::for('Ana Pereda'));
    }

    /**
     * Y el reverso, que es el CONTRATO y no una laxitud: **una tilde mal puesta no convierte a un niño
     * en otro**. `'Pérez'`, `'Peréz'` y `'Perez'` son la misma persona — que es exactamente lo que
     * `utf8mb4_unicode_ci` haría en MySQL y SQLite no, y por eso se normaliza en PHP.
     *
     * ⚠️ La primera versión de este fichero aseveraba lo contrario y salió ROJO: el instrumento decía
     * que el código estaba mal cuando lo que estaba mal era la expectativa.
     */
    public function test_a_misplaced_accent_does_not_turn_a_child_into_another(): void
    {
        $this->assertSame(PersonNameKey::for('Ana Pérez'), PersonNameKey::for('Ana Peréz'));
        $this->assertSame(PersonNameKey::for('Ana Pérez'), PersonNameKey::for('Ana Perez'));
    }

    /**
     * ⚠️⚠️ **El respaldo no es decorativo**: sin él, un nombre en una escritura que `Str::ascii()` no
     * sabe transliterar se quedaría en `''` y **todos** esos niños colisionarían en la misma clave —
     * que en el justificante significa «un niño, un papel» aplicado a personas distintas.
     *
     * ⚠️ **El ejemplo está MEDIDO, y la primera versión de este caso usaba uno falso.** `Str::ascii()`
     * sí transitera el cirílico («Александр Петров» → `aleksandr petrov`), el griego y el árabe, así
     * que con ellos el respaldo no entra nunca y el caso no probaba nada: quitándolo seguía en verde.
     * Los que de verdad se vacían, medidos el 2026-09-17 en el contenedor: **chino, japonés, coreano,
     * tailandés, hebreo y emoji**.
     */
    public function test_a_name_in_an_untransliterable_script_keeps_its_own_key(): void
    {
        $first = PersonNameKey::for('李小明');
        $second = PersonNameKey::for('王大偉');

        $this->assertNotSame('', $first, 'un nombre que no se translitera no puede quedarse sin clave');
        $this->assertNotSame($first, $second, 'dos niños con nombres así no pueden colisionar');
    }

    /** Y lo que SÍ se translitera se normaliza como cualquier otro nombre (medido, no supuesto). */
    public function test_a_transliterable_alphabet_is_normalised_like_any_other_name(): void
    {
        $this->assertSame('aleksandr petrov', PersonNameKey::for('Александр Петров'));
    }

    public function test_nothing_to_normalise_gives_an_empty_key(): void
    {
        $this->assertSame('', PersonNameKey::for('   '));
        $this->assertSame('', PersonNameKey::for(''));
    }

    /**
     * Se corta AQUÍ y no en la base de datos: una clave más larga que su columna la truncaría el
     * motor —en MySQL con error, en SQLite en silencio— y dos niños distintos acabarían con la misma
     * clave según dónde corriera.
     */
    public function test_the_key_is_cut_to_the_width_of_its_column(): void
    {
        $key = PersonNameKey::for(str_repeat('a', 300).' '.str_repeat('b', 300));

        $this->assertSame(PersonNameKey::MAX, mb_strlen($key));
        $this->assertSame(255, GuardianAuthorization::KEY_MAX, 'la columna y el normalizador tienen que cortar igual');
    }

    /**
     * **PARIDAD** (§4.4): la normalización subió a Platform y `GuardianAuthorization::keyFor()` delega.
     * La salida tiene que ser IDÉNTICA — si divergiera, los justificantes ya escritos dejarían de
     * emparejar con su propio menor y «un niño, un papel» se rompería en silencio.
     */
    public function test_the_guardian_authorization_key_is_exactly_the_shared_one(): void
    {
        $cases = [
            ['Ana', 'Pérez'],
            ['  ANA  ', '  PÉREZ GIL '],
            ['Александр', 'Петров'],
            ['Hugo', ''],
            ['', ''],
            [str_repeat('a', 200), str_repeat('b', 200)],
        ];

        foreach ($cases as [$name, $surname]) {
            $this->assertSame(
                PersonNameKey::for($name.' '.$surname),
                GuardianAuthorization::keyFor($name, $surname),
                "la clave de «{$name} {$surname}» divergió del normalizador compartido",
            );
        }
    }
}
