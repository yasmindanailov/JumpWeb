<?php

namespace App\Http\Middleware;

use App\Http\Api\ApiSurface;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `Cache-Control: no-store` en TODA respuesta de la web (`RGPD-04`), 2026-08-23.
 *
 * ⚠️⚠️ **Existe porque hasta hoy esa cabecera la ponía un ACCIDENTE, y el accidente está a punto de
 * desaparecer** (`docs/specs/account-context-vue.md` §4.7). No la ponía ningún middleware de este
 * proyecto: la ponía **Livewire**. `SupportDisablingBackButtonCache::boot()` es un *hook de
 * componente* —corre cuando un componente Livewire arranca— y enciende un flag estático que un
 * middleware GLOBAL del propio paquete usa para estampar la cabecera. O sea que el sitio entero
 * llevaba `no-store` **porque el layout renderizaba un componente Livewire**, y el último que
 * quedaba es el bloque de cuenta del cajón, que se retira.
 *
 * Medido A/B con `curl -s -D -` sobre la misma URL, el mismo minuto:
 *
 *     con el componente → max-age=0, must-revalidate, no-cache, no-store, private  (+ Pragma, Expires)
 *     sin el componente → no-cache, private
 *
 * ⚠️ **`no-cache` NO es `no-store`**, y la diferencia es justo la que importa aquí: `no-cache`
 * permite que los bytes persistan en la caché EN DISCO del navegador —de un dispositivo compartido—
 * mientras que `no-store` lo impide, y es además **lo único que inhabilita el bfcache**. Sin él,
 * cerrar sesión y pulsar «Atrás» restaura la página anterior con su árbol y su `data-boot` intactos.
 * El mismo razonamiento, escrito para los PDF, está en {@see NoStore}.
 *
 * ⚠️ **Y la PII de la web no se va con el bloque**: `site/nav.blade.php` pinta el nombre de pila del
 * titular y el aviso de formulario pendiente en **toda** página con sesión, y el nav no se migra.
 *
 * **Va INCONDICIONAL, no solo con sesión, y es deliberado**: incondicional es exactamente lo que hay
 * hoy, así que reponerlo así tiene riesgo cero. Hacer cacheable en disco el HTML anónimo es una
 * decisión de RENDIMIENTO con efectos secundarios reales —cada página lleva el
 * `<meta name="csrf-token">` de *esa* sesión, y servirlo desde disco produce 419— que merece medirse
 * aparte y no colarse de propina en un trabajo de migración.
 *
 * **Diferencia deliberada con la cabecera de Livewire**: se emite el idioma de este repo
 * (`no-store, max-age=0, private` + `Pragma`), sin `must-revalidate` ni `no-cache` ni el
 * `Expires: 1990`. Los tres son redundantes bajo `no-store, max-age=0` —`no-store` es estrictamente
 * más fuerte que `no-cache`, `must-revalidate` habla de respuestas rancias que no van a existir, y
 * `Pragma` ya cubre a un cliente HTTP/1.0—. Lo que la propiedad de seguridad necesita, que es
 * `no-store`, se conserva idéntico.
 *
 * ⚠️ **Convive con el middleware global de Livewire mientras queden componentes** (hoy,
 * `/restablecer-contrasena/{token}`): el suyo es global, así que corre por fuera y su cabecera gana
 * en esas rutas. Las dos llevan `no-store`, así que la propiedad se cumple por los dos caminos —y
 * por eso la guarda asevera **`no-store`**, no la cadena exacta: fijar la cadena la haría fallar por
 * un motivo que no es el que vigila.
 *
 * ⚠️⚠️ **Va GLOBAL y no en el grupo `web`, y eso lo decidió una MEDICIÓN, no el gusto.** Registrado
 * en el grupo, la tabla de verdad daba **un rojo** que no se esperaba: el middleware de grupo solo
 * corre en rutas que CASAN, y un 404 de URI desconocida no entra en el grupo `web` — pero **sí
 * renderiza el layout**, o sea el nav con el nombre del titular. Hoy esa página lleva `no-store`
 * precisamente porque el middleware de Livewire es global; reponerlo en el grupo habría dejado
 * fuera justo las páginas de error. Global es lo que reproduce el estado real.
 *
 * ⚠️ **Y por eso hace falta la puerta de la API**: global alcanzaría también a `/api/v1`, donde
 * `Api\NoStoreWhenAuthenticated` decide **por identidad** a propósito, para que el catálogo y la
 * disponibilidad ANÓNIMOS conserven su cacheabilidad — que es la que `PERF-02` protege. Se pregunta
 * a {@see ApiSurface}, que es la fuente única del prefijo, en vez de copiar la cadena aquí.
 */
class NoStoreWebResponses
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (ApiSurface::handles($request)) {
            return $response;
        }

        $response->headers->set('Cache-Control', 'no-store, max-age=0, private');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }
}
