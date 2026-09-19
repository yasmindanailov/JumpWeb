<?php

namespace Tests\Feature\Site;

use App\Domain\Platform\Services\LocalNumber;
use App\Domain\Platform\Services\Money;
use Tests\TestCase;

/**
 * **Cómo se escribe un número en cada idioma, en UN solo sitio** (F5 · T2b, `DECISIONES #651`).
 *
 * ❗❗ Afirma sobre el DATO y no sobre el marcado (`#649`), así que sobrevive el día que la portada se
 * vaya a su instancia — que es justo lo que no hacía la guarda anterior, porque no había ninguna: la
 * regla estaba escrita a mano dentro de `home.blade.php` y **nadie la vigilaba**.
 */
class LocalNumberTest extends TestCase
{
    /**
     * ⚠️ **El defecto que esto cierra, medido en el navegador el 19-09**: la portada inglesa enseñaba
     * la nota de Google como `4,8`. No fallaba nada — un número mal escrito se pinta igual de bien.
     */
    public function test_the_decimal_separator_follows_the_language(): void
    {
        foreach (['es' => '4,8', 'fr' => '4,8', 'en' => '4.8'] as $idioma => $esperado) {
            app()->setLocale($idioma);

            $this->assertSame($esperado, LocalNumber::decimal(4.8), "en «{$idioma}» la nota se escribe mal");
        }
    }

    /**
     * ⚠️ Y el de MILLARES es su espejo: `1.234 reviews` en inglés se lee *uno coma doscientos treinta y
     * cuatro*, o sea que el recuento de opiniones dice una cifra que no es.
     */
    public function test_the_thousands_separator_follows_the_language(): void
    {
        foreach (['es' => '1.234', 'fr' => '1.234', 'en' => '1,234'] as $idioma => $esperado) {
            app()->setLocale($idioma);

            $this->assertSame($esperado, LocalNumber::count(1234), "en «{$idioma}» el recuento se escribe mal");
        }
    }

    /**
     * ❗❗❗ **La guarda de que el refactor NO movió dinero.**
     *
     * `Money::showcase()` tenía esta misma regla escrita a mano y ahora la delega. Delegar un trozo de
     * una función que imprime IMPORTES es exactamente donde un refactor «equivalente» deja de serlo,
     * así que aquí se fija lo que imprimía ANTES, valor por valor: si algún día alguien unifica
     * también el separador de millares —que sigue a mano a propósito—, este caso se pone rojo y la
     * decisión pasa por el owner, que es lo que toca con dinero a la vista de un cliente.
     */
    public function test_the_showcase_price_prints_exactly_what_it_printed_before(): void
    {
        $casos = [
            'es' => ['1000' => '10', '1495' => '14,95', '123400' => '1.234', '123456' => '1.234,56'],
            'en' => ['1000' => '10', '1495' => '14.95', '123400' => '1.234', '123456' => '1.234.56'],
        ];

        foreach ($casos as $idioma => $esperados) {
            app()->setLocale($idioma);

            foreach ($esperados as $centimos => $esperado) {
                $this->assertSame(
                    $esperado,
                    Money::showcase((int) $centimos),
                    "«{$idioma}»: el importe de escaparate de {$centimos} céntimos cambió con el refactor",
                );
            }
        }
    }
}
