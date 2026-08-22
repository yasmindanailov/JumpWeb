<?php

namespace App\Http\Sidebar;

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
    ];

    /**
     * La zona con la que abrir el cajón en la petición actual, o `''` si esta ruta no es una puerta.
     *
     * Cadena vacía y no `null` porque el consumidor es un atributo HTML: `data-account-zone=""` es lo
     * mismo que no ponerlo, y así el layout no necesita un `@if`.
     */
    public static function zone(): string
    {
        return self::ZONE_BY_ROUTE[(string) Route::currentRouteName()] ?? '';
    }

    /** ¿Esta petición es una de las puertas? Lo pregunta el layout para abrir el cajón. */
    public static function isDoor(): bool
    {
        return self::zone() !== '';
    }
}
