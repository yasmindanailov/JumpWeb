<?php

namespace App\Domain\Content\Contracts;

/**
 * **El texto tal y como lo escribió su autor, cuando lo que se enseña es una traducción**
 * (`DECISIONES #494` · `specs/google-reviews.md` §4.7).
 *
 * ❗❗❗ **Existe porque la política de Places lo exige y hoy se incumplía**: *«Make end users aware
 * when a review has been translated from its original language. You can enable end users to view the
 * original non-translated text `originalText`»*. Y no es un borde teórico — medido contra la API
 * real el 2026-09-10: el parque tiene sus dos reseñas escritas en español, así que **en inglés y en
 * francés Google devuelve LAS DOS traducidas** (`text.languageCode` = `en`/`fr` sobre
 * `originalText.languageCode` = `es`). La landing es ES/EN/FR: son dos de sus tres idiomas.
 *
 * ⚠️⚠️ **Es un OBJETO y no dos campos sueltos en `Testimonial`, a propósito.** Con
 * `?string $originalText` y `?string $originalLanguage` por separado existen dos estados imposibles
 * —texto sin idioma e idioma sin texto— que nadie construye a mano pero que un refactor deja pasar
 * en silencio: la frase saldría «Traducida del ` `» o el enlace al original no llevaría a ninguna
 * parte. Aquí el tipo lo impide, y no hace falta ninguna guarda que lo vigile.
 *
 * ⚠️ **La ausencia significa «no está traducida»**, y esa es toda la señal: {@see Testimonial}
 * deriva de aquí que hay que avisar. Un `bool $translated` aparte podría contradecir al texto.
 */
final readonly class OriginalText
{
    public function __construct(
        /**
         * El texto sin traducir. ⚠️ **Nunca se recorta ni se corrige**: R4 prohíbe alterar el
         * contenido del usuario, y aquí el contenido es justamente lo que el autor escribió.
         */
        public string $text,
        /**
         * El código de idioma que declara la fuente (`es`, `de`, `pt-BR`…).
         *
         * ⚠️ **Se guarda tal cual lo da la fuente y no se normaliza.** Sirve para dos cosas: escribir
         * «Traducida del español» —resolviendo el nombre del idioma en el idioma de la página— y
         * poner `lang` en el elemento que muestra el original, que es lo que le dice a un lector de
         * pantalla que cambie de voz.
         */
        public string $language,
    ) {}

    /**
     * El idioma escrito en el idioma de la página («español», «Spanish», «espagnol»), o `null`.
     *
     * ⚠️⚠️ **`ext-intl` NO es un requisito declarado de este producto** — no está en `composer.json`
     * ni lo usa ninguna otra línea del repo, aunque este contenedor lo traiga—, así que llamar a
     * `Locale` a pelo metería un **fatal en la portada** de cualquier instalación que no lo tenga, y
     * solo se vería al desplegar. Se usa **si está**, y si no se calla.
     *
     * ⚠️ **Callar es una salida legítima y no una degradación del cumplimiento**: la política obliga
     * a avisar de que la reseña está traducida, no a nombrar de qué idioma. Sin `intl` el aviso sigue
     * saliendo, solo que sin el nombre.
     *
     * ⚠️ Y si el resolutor devuelve el propio código —lo que hace con un idioma que no conoce—,
     * también se calla: «Traducida del pt-BR» es peor que «Traducida».
     */
    public function languageName(): ?string
    {
        if (! class_exists(\Locale::class)) {
            return null;
        }

        $nombre = trim((string) \Locale::getDisplayLanguage($this->language, app()->getLocale()));

        if ($nombre === '' || strcasecmp($nombre, $this->language) === 0) {
            return null;
        }

        return $nombre;
    }
}
