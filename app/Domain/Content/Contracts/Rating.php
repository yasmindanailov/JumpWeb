<?php

namespace App\Domain\Content\Contracts;

/**
 * La cifra agregada de prueba social (`DECISIONES #490`).
 *
 * ❗❗ **Hoy solo puede construirla una fuente de tercero.** Existe como tipo desde ya —y no cuando
 * llegue Google— porque es lo que permite que la vista se escriba UNA vez: pinta la chapa si hay
 * `Rating` y no la pinta si no, sin saber por qué no lo hay.
 */
final readonly class Rating
{
    public function __construct(
        /** La media, tal como la da la fuente (p. ej. 4.8). */
        public float $value,
        /** Cuántas opiniones la sostienen. Es el segundo argumento de la sección, no un adorno. */
        public int $count,
        /** La ficha pública de la fuente. ⚠️ Es URL de un tercero: pasa por `safeExternalUrl` (`SEC-07`). */
        public ?string $url,
        /** Quién lo dice. La atribución que exige R3 depende de esto, así que viaja con el dato. */
        public string $source,
    ) {}
}
