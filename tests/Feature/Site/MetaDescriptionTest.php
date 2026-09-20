<?php

namespace Tests\Feature\Site;

use App\Domain\Platform\Services\MetaDescription;
use Tests\TestCase;

/**
 * **Cómo se escribe un resumen, en UN solo sitio** (F5 · T2b, `DECISIONES #653`).
 *
 * ❗❗ **Esta guarda afirma sobre el DATO, no sobre el marcado** (`#649`): la regla de cómo se resume un
 * contenido —el separador, el tope de 155 y los puntos suspensivos— es del PRODUCTO, y tiene que seguir
 * viva el día que las cuatro páginas que la usan se vayan a su instancia. Hasta hoy la vigilaba
 * nadie: estaba escrita en línea en cuatro vistas, cada una a su manera, y ninguna prueba la miraba.
 */
class MetaDescriptionTest extends TestCase
{
    public function test_names_are_joined_with_the_middle_dot(): void
    {
        $this->assertSame(
            'Saltos libres · Tirolina · Parkour',
            MetaDescription::fromNames(['Saltos libres', 'Tirolina', 'Parkour']),
        );

        // Una colección vale igual que un array: la vista pasa lo que tiene a mano.
        $this->assertSame('Uno · Dos', MetaDescription::fromNames(collect(['Uno', 'Dos'])));
    }

    /**
     * ⚠️⚠️ **El tope es 155 y se corta con puntos suspensivos AQUÍ**, no en el buscador: lo que cabe en
     * un fragmento de resultado. Un resumen de 155 justos pasa entero; uno más largo se acota.
     *
     * ⚠️ **Y se corta en PALABRA ENTERA**: las cuatro copias de la regla cortaban a mitad de palabra
     * («de que e...», medido el 20-09), que es lo que hace un `Str::limit` a secas. Se comprueba que lo
     * que queda delante de los puntos es un prefijo del original que termina justo antes de un espacio.
     */
    public function test_a_summary_is_capped_at_the_snippet_length_and_at_a_whole_word(): void
    {
        $justo = str_repeat('a', MetaDescription::MAX);
        $this->assertSame($justo, MetaDescription::fromNames([$justo]));
        $this->assertSame($justo, MetaDescription::fromText($justo));

        $nombres = array_fill(0, 40, 'Nombre de diez');
        $this->assertCortadoEnPalabraEntera(implode(' · ', $nombres), MetaDescription::fromNames($nombres));

        $parrafo = trim(str_repeat('palabra larga ', 60));
        $this->assertCortadoEnPalabraEntera($parrafo, MetaDescription::fromText($parrafo));
    }

    private function assertCortadoEnPalabraEntera(string $entero, ?string $resumen): void
    {
        $this->assertNotNull($resumen);
        $this->assertStringEndsWith('...', $resumen);
        $this->assertLessThanOrEqual(MetaDescription::MAX + 3, mb_strlen($resumen));
        $this->assertGreaterThan(MetaDescription::MAX - 20, mb_strlen($resumen), 'retrocedió mucho más que una palabra');

        $cuerpo = mb_substr($resumen, 0, -3);
        $this->assertStringStartsWith($cuerpo, $entero);
        $this->assertSame(' ', mb_substr($entero, mb_strlen($cuerpo), 1), 'cortado a mitad de palabra');
    }

    /**
     * ⚠️ **Un nombre en blanco no deja dos separadores seguidos**, y sin ninguno no hay resumen: `null`,
     * para que la página ponga su respaldo y la API no publique nada.
     */
    public function test_blank_names_are_dropped_and_none_is_null(): void
    {
        $this->assertSame('Saltos · Parkour', MetaDescription::fromNames(['Saltos', '', '   ', null, 'Parkour']));
        $this->assertNull(MetaDescription::fromNames([]));
        $this->assertNull(MetaDescription::fromNames(['', ' ']));
    }

    /**
     * ⚠️ Un párrafo legal se guarda con HTML y saltos de línea dentro; dentro del atributo `content`
     * de una `<meta>` no puede quedar ni lo uno ni lo otro.
     */
    public function test_a_text_loses_its_tags_and_its_line_breaks(): void
    {
        $this->assertSame(
            'En cumplimiento del artículo 10 de la Ley 34/2002',
            MetaDescription::fromText("<p>En  cumplimiento\n del <strong>artículo 10</strong> de la Ley 34/2002</p>"),
        );

        $this->assertNull(MetaDescription::fromText(null));
        $this->assertNull(MetaDescription::fromText(''));
        $this->assertNull(MetaDescription::fromText('<p>  </p>'));
    }
}
