<?php

namespace App\Domain\Content\Services;

use App\Domain\Platform\Services\MaintenanceSettings;

/**
 * **LOS DESTINOS DEL SITIO: el inventario de páginas y las secciones de la portada**
 * (`DECISIONES #521`, carril de diseño Fase 3 · T3a·1, `specs/rediseno-desde-canvas.md` §5.5).
 *
 * El menú y el pie ofrecen LO MISMO y lo leen de aquí. Mientras cada uno compuso su lista —el menú
 * con zonas y servicios del panel, el pie con dos arrays escritos a mano— un destino podía existir en
 * uno y no en el otro sin que nada lo avisara.
 *
 * `[DECIDIDO owner, 2026-09-11]`: **los destinos son el INVENTARIO del canvas**. Su regla es
 * *«el inventario de páginas es la fuente también para el menú y el pie; un enlace que no está en él
 * es relleno»*. Las zonas se eligen en la sección 01 y los servicios son UNA página, así que dejan de
 * ser destinos sueltos.
 *
 * ⚠️ Solo compone datos de NAVEGACIÓN —rótulo, URL, ruta escrita—. Qué se pinta y cómo lo decide
 * cada plantilla.
 */
final class SiteDestinations
{
    /**
     * Las páginas del inventario, en SU orden (`Layout Paginas PJP`): nombre de ruta → clave del
     * rótulo en `landing.nav.pages`.
     *
     * ✅ **`/bar` ENTRA en `#536`**, que es cuando nace su ruta. Esta nota decía «está en el
     * inventario y NO aquí porque la página no existe, y un destino a una ruta inexistente es el
     * ancla muerta que la regla del canvas prohíbe. Entra el día que nazca su ruta». Ese día es hoy.
     * ⚠️⚠️ **Pero `/bar` puede existir y no estar publicada**: sin nombre de bar en el panel la ruta
     * da 404, así que es la ÚNICA de las siete con una condición extra en `pages()`. Sin ella, el
     * menú y el pie ofrecerían un destino que responde 404 — exactamente el ancla muerta que la regla
     * prohíbe, entrando por la otra puerta.
     * ⚠️ El rótulo es el del INVENTARIO y no el titular de cada página: `/contacto` se titula
     * «Hablamos» y `/atracciones` «Todo lo que hay dentro», que son frases de su cabecera y no
     * nombres de destino. Y `/bar` se titula con el NOMBRE del bar, que lo pone el panel.
     */
    public const PAGES = [
        'precios' => 'pricing',
        'cumpleanos' => 'events',
        'atracciones' => 'attractions',
        'bar' => 'bar',
        'normas' => 'rules',
        'servicios' => 'services',
        'contacto' => 'contact',
    ];

    /**
     * Las secciones de la portada que llevan a sí mismas, en el orden de la portada: ancla → clave
     * del rótulo que la PROPIA sección lleva escrito.
     *
     * ⚠️ **Son cinco de ocho, y las tres que faltan faltan por una regla del canvas** (marco 1d):
     * «Cuánto» y «Cumpleaños» son sección Y página, y el destino es la PÁGINA —la versión larga—;
     * «Reseñas» no tiene página y **desaparece sin consentimiento de cookies**, así que sería un
     * enlace a la nada para quien dijo que no.
     * ⚠️ **El rótulo es el de la sección, no uno propio**: si el menú dijera «Zonas» y la sección
     * «Para quién», serían dos nombres para el mismo sitio, y el canvas lo avisa por lo mismo con los
     * números. Por eso la clave apunta al rótulo de la sección y no a una del menú.
     */
    /**
     * Las páginas del inventario que contestan una pregunta sin que nadie tenga que escribir, en el
     * orden en que se ofrecen (`answersItself()`, `#535`).
     *
     * ⚠️ Son las dos que el artboard de `/contacto` nombra, y **no todas**: ofrecer el inventario
     * entero convertiría la chapa en un segundo menú, que es justo lo que la sección dice no ser.
     */
    public const SELF_ANSWERING = ['precios', 'cumpleanos'];

    public const HOME_SECTIONS = [
        'zones' => 'landing.zones.eyebrow',
        'rides' => 'landing.rides.eyebrow',
        'before' => 'landing.before.eyebrow',
        'info' => 'landing.info.eyebrow',
        'faq' => 'landing.faq.eyebrow',
    ];

    /**
     * Las páginas del inventario que hoy se pueden visitar.
     *
     * ⚠️ **Una página en mantenimiento no se anuncia**: su enlace llevaría a una pantalla de «vuelve
     * luego». Es la misma regla que el menú ya aplicaba a `/servicios` desde el lanzamiento, ahora
     * para cualquiera de las páginas que el panel puede poner en mantenimiento
     * (`MaintenanceSettings::PAGE_KEYS`; una clave que no está ahí nunca lo está).
     *
     * @param  ?string  $currentRoute  la ruta de la página que pinta el menú, para marcarla
     * @return list<array{route: string, t: string, url: string, s: string, current: bool}>
     */
    public static function pages(?string $currentRoute = null): array
    {
        $pages = [];

        foreach (self::PAGES as $route => $label) {
            if (MaintenanceSettings::pageInMaintenance($route)) {
                continue;
            }

            /*
             * ⚠️ **El bar solo se ofrece si está publicado** (`#536`): sin nombre en el panel su ruta
             * responde 404, y un destino que lleva a un 404 es peor que no tenerlo. Es la misma regla
             * que el mantenimiento —no se anuncia lo que no se puede visitar— con otro motivo.
             * ⚠️ Cuesta CERO consultas nuevas: `isPublished()` solo lee `settings`, que están
             * memoizados para toda la petición, y esto corre en las doce vistas.
             */
            if ($route === 'bar' && ! BarPage::isPublished()) {
                continue;
            }

            $url = route($route);

            $pages[] = [
                'route' => $route,
                't' => (string) __('landing.nav.pages.'.$label),
                'url' => $url,
                's' => self::writtenPath($url),
                'current' => $currentRoute === $route,
            ];
        }

        return $pages;
    }

    /**
     * **La RUTA ESCRITA de una URL**: lo que el menú pone debajo de cada destino y lo que la cabecera
     * de una página pone en su rótulo (`DECISIONES #525`, T3a·3).
     *
     * ⚠️ Sale de la URL real y no de una tabla aparte, así que no puede decir una dirección que no
     * es. Y es UNA función para los dos a propósito: con dos derivaciones, el menú y la cabecera de
     * la misma página podían escribir rutas distintas sin que nada lo avisara — `/atracciones` la
     * llevaba escrita a mano en los ficheros de idioma hasta `#525`.
     */
    public static function writtenPath(string $url): string
    {
        return (string) (parse_url($url, PHP_URL_PATH) ?: '/');
    }

    /**
     * Las secciones de la portada que el menú y el pie ofrecen.
     *
     * ⚠️ **Solo las que la portada PINTA**: un ancla a una sección que no está no falla —el navegador
     * se queda donde estaba— y por eso nadie lo vería. Hoy la única condicional de las cinco es
     * «Dudas», que desaparece con cero dudas en el panel; quien la sabe es el controlador de la
     * portada, que es quien decide pintarla.
     *
     * @return list<array{t: string, url: string}>
     */
    public static function homeSections(bool $withFaq = true): array
    {
        $sections = [];

        foreach (self::HOME_SECTIONS as $anchor => $label) {
            if ($anchor === 'faq' && ! $withFaq) {
                continue;
            }

            $sections[] = [
                't' => (string) __($label),
                'url' => url('/#'.$anchor),
            ];
        }

        return $sections;
    }

    /**
     * **LOS DESTINOS QUE CONTESTAN SOLOS**: la chapa «Quizá ya está contestado» de `/contacto`
     * (`DECISIONES #535`, carril de diseño Fase 3 · T3b).
     *
     * El artboard lo razona así: *«un contacto que se puede evitar es un correo que no hay que
     * contestar; tres cuartos de lo que se pregunta ya está en Dudas y en Tarifas»*.
     *
     * ⚠️ **Salen del INVENTARIO y no de tres `href` escritos en la plantilla**, que es lo que
     * `#521` vino a cerrar: así heredan gratis la regla de mantenimiento —una página apagada deja
     * de ofrecerse aquí también— y no pueden apuntar a una ruta que no existe.
     *
     * ⚠️ **Y los rótulos son los del inventario, no los del artboard.** Él escribe «Todas las
     * tarifas» y «Los packs de cumpleaños»; aquí dicen «Tarifas» y «Cumpleaños», que es como los
     * llama el menú y el pie. El propio canvas prohíbe lo contrario: dos nombres para el mismo
     * sitio son dos sitios para quien lee.
     *
     * @param  bool  $withFaq  ¿la portada pinta hoy la sección de Dudas? (cero dudas en el panel la
     *                         retira, `#488`) — la misma condición que `homeSections()`
     * @return list<array{t: string, url: string}>
     */
    public static function answersItself(bool $withFaq = true): array
    {
        $out = [];

        if ($withFaq) {
            $out[] = [
                't' => (string) __(self::HOME_SECTIONS['faq']),
                'url' => url('/#faq'),
            ];
        }

        foreach (self::pages() as $page) {
            if (in_array($page['route'], self::SELF_ANSWERING, true)) {
                $out[] = ['t' => $page['t'], 'url' => $page['url']];
            }
        }

        return $out;
    }
}
