<?php

namespace Tests\Feature\Architecture;

use Tests\TestCase;

/**
 * **UN SOLO NOMBRE PARA EL DOCUMENTO QUE LA GENTE FIRMA** (`DECISIONES #339`,
 * `[DECIDIDO owner, 2026-09-02]`).
 *
 * El encargo del owner fue literal: *«todo se llama “exención” no “waiver”, que en todos los sitios
 * ponga lo mismo o comienza la confusión»*. Al medirlo había **CINCO** formas conviviendo para la
 * misma cosa —«exención de responsabilidad (waiver)», «Descargo de responsabilidad (waiver)»,
 * «Descarga de responsabilidad», «waiver» a pelo y un enlace legal del pie titulado «Waiver»—, y
 * tres de ellas le llegaban al CLIENTE, no solo al operador.
 *
 * ⚠️⚠️ **Esta guarda existe porque un vocabulario no se rompe de golpe: se erosiona.** Nadie va a
 * volver a poner las cinco; alguien va a escribir UNA cadena nueva que diga «waiver» porque es como
 * se llama en el código, y sin nada que lo mire, en tres meses vuelve a haber cinco. Es la misma
 * lección que `TagSystemTest` dejó escrita para las etiquetas.
 *
 * ⚠️ **Lo que esto NO vigila, a propósito: el CÓDIGO.** `waiver` sigue siendo el vocabulario del
 * dominio —la tabla `waiver_signatures`, `WaiverSigner`, el slug del documento, las CLAVES de estos
 * mismos ficheros de idioma y sobre todo los **códigos de error de la API**, que son contrato— y
 * renombrarlo no arreglaría nada que se vea, mientras que rompería clientes y tocaría el
 * `CRITICAL_RE`. El mapeo término ↔ código vive en `docs/GLOSARIO.md`. Aquí solo se mira **lo que
 * lee una persona**: los VALORES.
 *
 * ⚠️ **Cada idioma tiene su propio término, y no es el mismo** — en inglés «waiver» es justamente la
 * palabra natural, así que prohibirla allí sería absurdo. Lo que se prohíbe en cada idioma es lo que
 * COMPITE con su término elegido.
 */
class WaiverWordingIsOneTermTest extends TestCase
{
    /**
     * Por idioma: el término elegido y las formas retiradas que no pueden volver.
     *
     * @var array<string, array{termino: string, retirados: list<string>}>
     */
    private const VOCABULARIO = [
        'es' => [
            'termino' => 'descargo de responsabilidad',
            // ⚠️ «descarga» es un CALCO y en una web se lee como bajar un fichero: es la peor de las
            // tres, y era la que producción tenía publicada.
            'retirados' => ['waiver', 'exención', 'exencion', 'descarga de responsab'],
        ],
        'en' => [
            'termino' => 'liability waiver',
            // Aquí «waiver» se QUEDA: lo retirado son las variantes que competían con ella.
            'retirados' => ['waiver of liability', 'liability release'],
        ],
        'fr' => [
            'termino' => 'décharge de responsabilité',
            'retirados' => ['waiver'],
        ],
    ];

    /**
     * @return list<array{clave: string, valor: string}>
     */
    private function cadenasDe(string $locale): array
    {
        $base = lang_path($locale);
        $planas = [];

        $recorrer = function (array $arr, string $ruta) use (&$recorrer, &$planas): void {
            foreach ($arr as $clave => $valor) {
                $p = $ruta === '' ? (string) $clave : $ruta.'.'.$clave;
                if (is_array($valor)) {
                    $recorrer($valor, $p);

                    continue;
                }
                if (is_string($valor)) {
                    $planas[] = ['clave' => $p, 'valor' => $valor];
                }
            }
        };

        foreach (glob($base.'/*.php') ?: [] as $fichero) {
            $recorrer(require $fichero, basename($fichero, '.php'));
        }

        return $planas;
    }

    /**
     * ⚠️ **El control de la propia guarda**: si el localizador dejara de encontrar ficheros, todo lo
     * de abajo pasaría en verde sobre CERO cadenas. Es el fallo que este proyecto ha pagado varias
     * veces —un caso sin sujeto no vigila nada—, así que se comprueba que hay material que mirar.
     */
    public function test_the_probe_actually_reads_the_translation_files(): void
    {
        foreach (array_keys(self::VOCABULARIO) as $locale) {
            $cadenas = $this->cadenasDe($locale);
            $this->assertGreaterThan(200, count($cadenas), "el escáner no ve las cadenas de «{$locale}»");
        }
    }

    /**
     * Ninguna cadena que lea una persona puede usar una forma retirada.
     *
     * ⚠️ **Se ACUMULAN las violaciones y se asevera UNA vez**, y no es cosmética: aseverar dentro del
     * bucle mata el caso en la primera, así que arreglas una, vuelves a correr, aparece la siguiente
     * — y además mete ~16.000 aserciones en el contador vivo del proyecto por un solo caso. Así el
     * fallo trae la lista entera de una vez.
     */
    public function test_no_visible_string_uses_a_retired_wording(): void
    {
        $violaciones = [];

        foreach (self::VOCABULARIO as $locale => $reglas) {
            foreach ($this->cadenasDe($locale) as ['clave' => $clave, 'valor' => $valor]) {
                foreach ($reglas['retirados'] as $retirado) {
                    if (mb_stripos($valor, $retirado) !== false) {
                        $violaciones[] = "  · {$locale}.{$clave} dice «{$retirado}» → en {$locale} se llama «{$reglas['termino']}»";
                    }
                }
            }
        }

        $this->assertSame(
            [],
            $violaciones,
            "Hay texto visible con una forma retirada del documento (`DECISIONES #339`):\n"
            .implode("\n", $violaciones)
            ."\n\nSi lo que necesitas es una CLAVE, esa no se toca: aquí solo se miran los VALORES."
        );
    }

    /**
     * Y el término elegido tiene que estar REALMENTE en uso: sin esto, retirar las cinco formas y no
     * poner ninguna pasaría en verde. La guarda vigila que se diga de UNA manera, no que no se diga.
     */
    public function test_the_chosen_wording_is_the_one_in_use(): void
    {
        foreach (self::VOCABULARIO as $locale => $reglas) {
            $usos = 0;
            foreach ($this->cadenasDe($locale) as ['valor' => $valor]) {
                if (mb_stripos($valor, $reglas['termino']) !== false) {
                    $usos++;
                }
            }

            $this->assertGreaterThanOrEqual(
                3,
                $usos,
                "en «{$locale}» apenas se usa «{$reglas['termino']}»: o el término cambió sin actualizar "
                .'esta guarda, o alguien retiró las cadenas en vez de reescribirlas.'
            );
        }
    }
}
