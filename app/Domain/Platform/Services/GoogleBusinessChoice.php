<?php

namespace App\Domain\Platform\Services;

/**
 * **El desenlace de elegir una ficha** (`docs/specs/google-business-profile.md` §4.2·4).
 *
 * Existe por una frontera: el aviso a los admins del §4.2·4 hay que mandarlo desde la capa de
 * ENTREGA —`User` vive en Identity y **Platform no puede mirar a ningún módulo**
 * (`ModuleBoundariesTest`: `'Platform' => []`)—, así que el dominio tiene que **contar lo que pasó**
 * en vez de avisar él.
 *
 * ⚠️ Y contarlo entero: sin `$previousTitle` el correo diría «ha cambiado» sin poder decir **desde
 * qué**, que es la mitad de lo que necesita quien lo lee para saber si fue un error.
 */
final readonly class GoogleBusinessChoice
{
    public function __construct(
        public GoogleBusinessLocation $location,
        /** `true` solo si había una ficha ANTES y era otra. La primera elección no es un cambio. */
        public bool $changed,
        /** El rótulo de la anterior, si lo había. */
        public ?string $previousTitle,
    ) {}
}
