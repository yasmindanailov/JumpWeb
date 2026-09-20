<?php

namespace Tests\Feature\GoogleBusiness;

use App\Domain\Content\Services\GoogleReviewText;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * **T2·2 · El texto de una reseña, separado de la traducción de Google**
 * (`docs/specs/google-business-profile.md` §4.3·8; `DECISIONES #524`, `#728`).
 *
 * Lo que fija este caso es que **se publica lo que escribió el autor** y que, cuando no se puede
 * saber con certeza cuál de las dos versiones es la suya, **se dice** en vez de adivinar.
 *
 * ⚠️⚠️ El formato NO está documentado en la API y la medición contra la ficha real está pendiente
 * (§6·T2①, bloqueada hasta que haya conexión). Estos casos fijan el comportamiento contra el formato
 * que describe la spec **y**, sobre todo, fijan que lo desconocido cae del lado seguro.
 */
class GoogleReviewTextTest extends TestCase
{
    public function test_un_texto_sin_marcadores_se_publica_tal_cual(): void
    {
        $texto = GoogleReviewText::from('Los niños salieron encantados.');

        $this->assertSame('Los niños salieron encantados.', $texto->text);
        $this->assertFalse($texto->ambiguous);
    }

    public function test_con_la_traduccion_delante_se_publica_el_original(): void
    {
        $texto = GoogleReviewText::from('(Translated by Google) Great park (Original) Gran parque');

        // ❗ Lo que se publica es lo del autor. La traducción la escribió una máquina y no se puede
        // firmar con el nombre y la cara de una persona.
        $this->assertSame('Gran parque', $texto->text);
        $this->assertFalse($texto->ambiguous);
    }

    public function test_con_el_original_delante_tambien_se_publica_el_original(): void
    {
        // §4.3·8 dice que el ORDEN VARÍA, así que los dos sentidos tienen caso.
        $texto = GoogleReviewText::from('(Original) Gran parque (Translated by Google) Great park');

        $this->assertSame('Gran parque', $texto->text);
        $this->assertFalse($texto->ambiguous);
    }

    public function test_el_corte_no_parte_un_caracter_multibyte(): void
    {
        // ⚠️ El analizador mide en BYTES. Los marcadores son ASCII, así que el corte cae en un límite
        // seguro — pero si alguien mezclara `strpos` con `mb_substr`, esto saldría con un rombo.
        $texto = GoogleReviewText::from('(Translated by Google) Great (Original) Estupendo: ñandú, café, 日本語 🎈');

        $this->assertSame('Estupendo: ñandú, café, 日本語 🎈', $texto->text);
        $this->assertFalse($texto->ambiguous);
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    public static function ambiguos(): array
    {
        return [
            'solo la marca de la traducción' => [
                '(Translated by Google) Great park',
                '(Translated by Google) Great park',
            ],
            'solo la marca del original' => [
                '(Original) Gran parque',
                '(Original) Gran parque',
            ],
            'la marca del original, repetida' => [
                '(Translated by Google) Great (Original) Gran parque (Original) otra vez',
                '(Translated by Google) Great (Original) Gran parque (Original) otra vez',
            ],
            'la marca de la traducción, repetida' => [
                '(Translated by Google) Great (Translated by Google) Good (Original) Gran parque',
                '(Translated by Google) Great (Translated by Google) Good (Original) Gran parque',
            ],
            'marcas bien puestas y ningún original detrás' => [
                '(Translated by Google) Great park (Original)',
                '(Translated by Google) Great park (Original)',
            ],
            'un paréntesis que nombra a Google y no se supo separar' => [
                'Buen sitio (Traducido por Google) Good place',
                'Buen sitio (Traducido por Google) Good place',
            ],
        ];
    }

    #[DataProvider('ambiguos')]
    public function test_lo_que_no_se_puede_separar_se_guarda_crudo_y_se_marca(string $crudo, string $esperado): void
    {
        $texto = GoogleReviewText::from($crudo);

        // ❗❗ **Falla cerrado**: el texto entero, sin tocar, y la marca puesta. Un autor puede escribir
        // «(Original)» dentro de su reseña, y cortar por ahí se llevaría por delante lo que dijo.
        $this->assertSame($esperado, $texto->text);
        $this->assertTrue($texto->ambiguous);
    }

    public function test_nombrar_a_google_fuera_de_un_parentesis_no_marca_nada(): void
    {
        // ⚠️ El CONTROL de la red de seguridad: si marcara cualquier mención de Google, marcaría media
        // ficha y la señal dejaría de significar nada.
        $texto = GoogleReviewText::from('Lo encontramos por Google y repetiremos.');

        $this->assertSame('Lo encontramos por Google y repetiremos.', $texto->text);
        $this->assertFalse($texto->ambiguous);
    }

    public function test_un_parentesis_que_no_nombra_a_google_no_marca_nada(): void
    {
        $texto = GoogleReviewText::from('Fuimos el sábado (por la tarde) y estaba lleno.');

        $this->assertSame('Fuimos el sábado (por la tarde) y estaba lleno.', $texto->text);
        $this->assertFalse($texto->ambiguous);
    }

    public function test_un_texto_vacio_no_es_ambiguo_es_vacio(): void
    {
        // Sin texto no hay candidata (§4.3·2), y eso lo decide quien filtra. Aquí «vacío» no es un
        // fallo del analizador y no se marca como tal.
        $texto = GoogleReviewText::from('   ');

        $this->assertSame('', $texto->text);
        $this->assertFalse($texto->ambiguous);
    }

    public function test_los_espacios_de_alrededor_se_recortan(): void
    {
        $texto = GoogleReviewText::from("  (Translated by Google) Great park  (Original)   Gran parque   \n");

        $this->assertSame('Gran parque', $texto->text);
    }
}
