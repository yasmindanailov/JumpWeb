<?php

namespace App\Http\Sidebar;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/**
 * **Las rutas de «Mi cuenta» que SOBREVIVEN a la retirada de sus vistas** (tanda 3,
 * `docs/specs/area-cliente.md` §4.8).
 *
 * Muere la VISTA, vive la RUTA. No es un matiz: **8 notificaciones por correo ya entregadas** apuntan
 * a `route('account.orders')` —un correo enviado no se puede editar— y **11 redirecciones del
 * servidor** aterrizan en `route('account')` con un `->with('status', …)` que el layout pinta. Borrar
 * las rutas en crudo convertiría todo eso en un 404 para siempre.
 *
 * ▶ **El mecanismo no se inventa: es el de `/entradas`.** Aquella ruta sirve la home con
 * `data-purchase-open="1"` y el cajón se abre solo desde la Fase 5.2. Aquí se añade una segunda señal
 * —**qué zona** del área de cliente— y nada más.
 *
 * ⚠️ **Los identificadores de zona son los de `resources/js/sidebar/account/navigation.js`**, y por
 * eso hay un test que cruza las dos listas: una errata aquí no rompería nada visible —el cajón caería
 * en el índice— y el cliente que viene de un correo aterrizaría en otra pantalla sin que nadie lo
 * notara. Es la familia de `DECISIONES #117`: algo que «no falla y no hace nada».
 */
final readonly class AccountDoor
{
    /**
     * Ruta → zona del área de cliente.
     *
     * ⚠️ **`account` abre el ÍNDICE y no una pantalla concreta**, y es deliberado: ahí aterrizan las
     * once redirecciones del servidor —verificación de correo, cambio de email, perfil, contraseña,
     * sesiones— y cada una trae su propio mensaje flash. Llevarlas a una zona concreta acertaría con
     * unas y mentiría con otras.
     *
     * @var array<string, string>
     */
    public const ZONE_BY_ROUTE = [
        'account' => 'home',
        'account.orders' => 'orders',
        // «Añade a tus hijos» (T5d de `isla-y-landing-nueva.md` §4.13, `#777`): la URL de la tarea de «Antes de venir»
        // (`Http\Cuenta\AntesDeVenir`), que sirve a cualquier cliente. Con la isla abre su pantalla de alta; con el
        // cajón, su zona de menores.
        'account.dependents' => 'dependents',

        // ── Las puertas de AUTH (`specs/auth-en-cajon.md` §4.4) ──────────────────────────────
        //
        // ⚠️⚠️ **Las tres rutas sobreviven, y `login` no es opcional**: es el destino al que Laravel
        // redirige desde el middleware `auth`, así que borrarla rompería `/mi-cuenta` y toda ruta
        // autenticada. Las otras dos se conservan por lo mismo que las de arriba: son enlaces que ya
        // están escritos fuera de este repo.
        // ▶ Y no nace un segundo mapa para ellas **a propósito**: un `AuthDoor` aparte sería otro
        // sitio donde equivocarse, y este ya lo cruza `AccountAccessTest` contra las zonas del cajón.
        'registro' => 'register',
        'login' => 'login',
        'password.request' => 'forgot',

        // La CUARTA puerta de auth (`specs/auth-con-google.md` §7): aquí aterriza quien vuelve de
        // Google **sin cuenta**, y el cajón abre la pantalla que completa el alta.
        // ⚠️ Es una puerta como las otras tres, y por eso hereda sus dos reglas sin escribir nada: no
        // se indexa, y con sesión abre el índice en vez del formulario — quien ya entró no tiene un
        // alta que completar.
        'registro.google' => 'google-signup',
    ];

    /**
     * Las puertas que solo tienen sentido SIN sesión.
     *
     * Se declaran aparte del mapa porque el mapa dice *a dónde lleva cada ruta* y esto dice *para
     * quién*. Cruzarlas es lo que permite las dos reglas de abajo sin escribir la lista dos veces.
     *
     * @var list<string>
     */
    public const GUEST_ROUTES = ['registro', 'login', 'password.request', 'registro.google'];

    /**
     * La zona con la que abrir el cajón en la petición actual, o `''` si esta ruta no es una puerta.
     *
     * Cadena vacía y no `null` porque el consumidor es un atributo HTML: `data-account-zone=""` es lo
     * mismo que no ponerlo, y así el layout no necesita un `@if`.
     */
    public static function zone(): string
    {
        $route = (string) Route::currentRouteName();
        $zone = self::ZONE_BY_ROUTE[$route] ?? '';

        // ⚠️ **Una puerta de invitado con SESIÓN abre el índice, no el formulario.** Quien ya ha
        // entrado y aterriza en `/login` —desde un marcador, un enlace viejo o el «atrás» del
        // navegador— no puede encontrarse un formulario de identificarse: sería pedirle algo que ya
        // ha hecho. Se le lleva a su cuenta, que es lo que venía a buscar.
        // ▶ Antes de esto no pasaba nada en ese caso, porque el modal era `@guest` y no se
        // renderizaba. La puerta sí abre el cajón siempre, así que la regla hay que escribirla.
        if ($zone !== '' && in_array($route, self::GUEST_ROUTES, true) && Auth::check()) {
            return self::ZONE_BY_ROUTE['account'];
        }

        return $zone;
    }

    /**
     * ¿Esta petición es una puerta de AUTH? Lo pregunta `HomeController` para no indexarla.
     *
     * ⚠️ **Existe porque su `noindex` se quedaba sin dueño.** Hasta el 2026-08-23 las tres se servían
     * `noindex, nofollow` por un **efecto lateral**: el prop `authModal` del layout decidía a la vez
     * si se pintaba el modal y qué robots emitir. Al dejar de emitir ese prop —el modal ya no lo abre
     * nadie— el `noindex` se habría ido con él y las tres URL de auth habrían entrado en el índice de
     * Google sin que nada fallara. Lo fija `SeoTest::test_the_auth_doors_are_never_indexable`.
     */
    public static function isAuthDoor(): bool
    {
        return in_array((string) Route::currentRouteName(), self::GUEST_ROUTES, true);
    }

    /** ¿Esta petición es una de las puertas? Lo pregunta el layout para abrir el cajón. */
    public static function isDoor(): bool
    {
        return self::zone() !== '';
    }
}
