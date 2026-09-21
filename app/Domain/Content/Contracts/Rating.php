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
    /**
     * **La ESCALA: sobre cuántas estrellas se cuenta una nota** (`#662`).
     *
     * ❗❗ **Vive aquí porque estaba escrita DOS VECES en la portada** —`str_repeat('★', 5 - $rating)`
     * en la tarjeta de cada opinión y `@for ($e = 1; $e <= 5; …)` en la chapa de la media—, y esa
     * vista se muda al paquete de una instalación (`paquete-de-instancia.md` §4.7). Con el número
     * dentro de la vista, cada instancia re-decidiría la escala, y una que escribiera 4 dibujaría
     * cuatro estrellas mientras el rótulo accesible —que sale de `lang/`— seguiría diciendo «sobre
     * 5». *Una escala no se teclea en el sitio donde se dibuja.*
     *
     * ⚠️ **No es configurable y no debe serlo**: la fija la FUENTE. Places devuelve 1–5 y el panel
     * ofrece un desplegable de 1 a 5 (`TestimonialForm`). Un ajuste aquí sería una promesa que
     * ninguna de las dos fuentes puede cumplir.
     * ⚠️ El texto «sobre 5» de `landing.reviews.{stars,out_of,score_aria}` es COPIA y se queda en
     * `lang/`: son nueve cadenas que escribe un traductor, no aritmética.
     */
    public const MAX = 5;

    public function __construct(
        /** La media, tal como la da la fuente (p. ej. 4.8). */
        public float $value,
        /** Cuántas opiniones la sostienen. Es el segundo argumento de la sección, no un adorno. */
        public int $count,
        /** La ficha pública de la fuente. ⚠️ Es URL de un tercero: pasa por `safeExternalUrl` (`SEC-07`). */
        public ?string $url,
        /** Quién lo dice. La atribución que exige R3 depende de esto, así que viaja con el dato. */
        public string $source,
        /**
         * **Cuándo se midió esta cifra** (T2·6, §4.3·10: «con *a fecha de …*»), o `null`.
         *
         * ❗❗ No es un adorno: con Business Profile la media la trajo **nuestra pasada**, que corre
         * una vez al día, así que la cifra que se enseña puede tener horas. Publicarla a secas la
         * presenta como «ahora mismo», que es una afirmación que no podemos sostener sobre un dato
         * de un tercero. Con la fecha al lado, lo que se dice es verdad.
         *
         * ⚠️ `null` en las fuentes que no la tienen —Places la sirve de una caché de minutos— y ahí
         * la landing no escribe la coletilla. Nulo es «no lo sé», no «es de hoy».
         */
        public ?\DateTimeInterface $asOf = null,
    ) {}
}
