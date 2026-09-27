<?php

namespace App\Http\Sidebar;

/**
 * **La compra que salió a Google y VUELVE** (T3e·4 de `docs/specs/isla-y-landing-nueva.md` §4.10, `DECISIONES #695`).
 *
 * «Continuar con Google» dentro de la compra de la isla es una redirección de navegador que vuelve a `next`
 * (`GoogleAuthController`), y la isla pone ahí LA MISMA página con `?compra=reanudar`. Con ese parámetro el layout
 * sirve la página con la compra ABIERTA, como `/entradas` —el mecanismo es el suyo, `data-purchase-open`— y lo dice
 * en `data-purchase-resume` para que la apertura se cuente como lo que es (`resume`, no un enlace profundo). Dónde
 * estaba la compra lo sabe la pestaña, no la URL (`resources/js/sidebar/reanudar.js`).
 *
 * ⚠️ **Decidirlo en el servidor es lo que no cuesta nada**: comprobarlo en el navegador en cada carga añadía 0,57 KiB
 * a la entrada de TODA página pública (medido) para algo que casi nadie hace. Y el parámetro no abre más que lo que
 * ya abre `/entradas`: un enlace con él sin marca en la pestaña abre la compra en su pantalla 0.
 *
 * ⚠️ El nombre y el valor son los de `resources/js/sidebar/reanudar.js` (`PARAMETRO`, `VALOR`): lo vigila su test.
 */
final class PurchaseResume
{
    public const PARAM = 'compra';

    public const VALUE = 'reanudar';

    public static function requested(): bool
    {
        return request()->query(self::PARAM) === self::VALUE;
    }

    /**
     * ¿Esta URL es la VUELTA de una compra? (`#785`) La usa `GoogleAuthController`: una cuenta NUEVA que salió de una
     * compra completa su alta DENTRO de ella (la compra la encuentra en `GET /auth/google/pending`), no en
     * `/registro/google`, que la sacaba del camino de pagar a «Mi cuenta».
     */
    public static function isResumeUrl(string $url): bool
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return ($query[self::PARAM] ?? null) === self::VALUE;
    }
}
