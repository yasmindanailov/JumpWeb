<?php

namespace App\Http\Middleware;

use App\Domain\Platform\Services\MaintenanceSettings;
use App\Http\Api\ApiErrorCode;
use App\Http\Api\ApiErrorResponse;
use App\Http\Api\ApiSurface;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mantenimiento de SITIO ENTERO (#218, item 2).
 *
 * Si el panel activa `maintenance.site`, la web pública responde **503 Service Unavailable** con
 * una página de mantenimiento on-brand, en lugar del contenido normal. SEO-correcto: 503 +
 * `Retry-After` (un 200 haría que un buscador indexase el aviso de mantenimiento como contenido).
 *
 * **Exclusiones (NUNCA se bloquean), por path:**
 *  - `admin` / `admin/*` — el panel DEBE seguir vivo para poder desactivar el mantenimiento.
 *  - `pago/redsys/*` — callbacks firmadas de Redsys: un pago YA iniciado debe poder finalizar.
 *  - `lang/*` — cambio de idioma (que el bypass-staff pueda alternar idioma de la web).
 *  - `up` — health check de Laravel (monitoring).
 *
 * **Bypass:** un usuario autenticado del panel (admin/staff) ve la web REAL para hacer QA; el
 * layout le inyecta un banner de aviso («solo tú la ves»). Idéntico criterio que
 * `User::canAccessPanel()` (admin || staff), sin acoplar a un Panel de Filament.
 * ⚠️ El bypass se resuelve con el guard por defecto (sesión), porque este middleware corre ANTES
 * del `auth:` de ruta: en `/api/v1` funciona para la SPA (comparte sesión) y NO para un cliente
 * Bearer. Es irrelevante mientras no se emitan tokens (paso 3 de Fase 3); cuando se emitan, si un
 * admin necesita QA desde la app, aquí es donde hay que consultar también el guard `sanctum`.
 *
 * **`/api/v1` también entra** (Fase 3 · paso 0, spec §4.7), con el 503 renderizado como sobre de
 * error JSON — ver `maintenanceResponse`.
 *
 * El helper es fail-safe (un setting corrupto NO activa el mantenimiento), así que el camino por
 * defecto de este middleware es no hacer nada. Orden de registro: corre DESPUÉS de `SetLocale`
 * (para renderizar el 503 en el idioma correcto) y DENTRO de `SecurityHeaders` (que envuelve la
 * respuesta 503 y le añade las cabeceras de seguridad). Ver `bootstrap/app.php` y `DECISIONES.md` #218.
 */
class EnsureSiteAvailable
{
    /** 1 h: pista para buscadores y clientes; no es un compromiso exacto de reapertura. */
    private const RETRY_AFTER_SECONDS = 3600;

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->isExcluded($request)) {
            return $next($request);
        }

        // Bypass del personal del panel (admin/staff): ve la web real para hacer QA, tanto del
        // mantenimiento de sitio como del de una página concreta (el layout pinta el aviso).
        $user = $request->user();
        $isStaff = $user !== null && ($user->hasRole('admin') || $user->hasRole('staff'));

        // Precedencia: sitio entero (item 2) por encima de página concreta (item 1).
        if (MaintenanceSettings::siteInMaintenance() && ! $isStaff) {
            return $this->maintenanceResponse($request, 'errors.maintenance');
        }

        $pageKey = $this->currentPageKey($request);
        if ($pageKey !== null && MaintenanceSettings::pageInMaintenance($pageKey) && ! $isStaff) {
            return $this->maintenanceResponse($request, 'errors.page-maintenance');
        }

        return $next($request);
    }

    /**
     * Mapea la ruta ACTUAL a una de las páginas gestionables (`PAGE_KEYS`), o null si no aplica.
     * El nombre de ruta coincide con la clave de página (home/precios/cumpleanos/servicios/normas/
     * contacto); `entradas` (deep-link de compra) comparte el estado de `home`.
     */
    private function currentPageKey(Request $request): ?string
    {
        $name = $request->route()?->getName();
        if ($name === null) {
            return null;
        }

        if ($name === 'entradas') {
            $name = 'home';
        }

        return in_array($name, MaintenanceSettings::PAGE_KEYS, true) ? $name : null;
    }

    /**
     * El mismo 503 en los dos idiomas del sistema: HTML on-brand para la web, sobre de error JSON
     * para `/api/v1` (Fase 3 · paso 0, spec §4.7).
     *
     * La decisión que el spec dejó escrita: **la API entra en el mantenimiento**, no queda fuera.
     * Un kill-switch que apaga la web pero deja el dominio abierto por otra puerta no es un
     * kill-switch. Lo que había que resolver era el formato: sin esto, un cliente JSON recibiría la
     * página HTML de mantenimiento y fallaría al parsearla, convirtiendo un 503 explicable en un
     * error incomprensible. `Retry-After` viaja en ambos (SEO en web, reintento en API).
     */
    private function maintenanceResponse(Request $request, string $view): Response
    {
        if (ApiSurface::handles($request)) {
            return ApiErrorResponse::make(
                ApiErrorCode::Maintenance,
                Response::HTTP_SERVICE_UNAVAILABLE,
                headers: ['Retry-After' => (string) self::RETRY_AFTER_SECONDS],
            );
        }

        return response()
            ->view($view, [], Response::HTTP_SERVICE_UNAVAILABLE)
            ->header('Retry-After', (string) self::RETRY_AFTER_SECONDS);
    }

    private function isExcluded(Request $request): bool
    {
        // El endpoint de actualización de Livewire (POST, nombre `*livewire.update`) es COMPARTIDO
        // por todo el panel y la web. El formulario de login de Filament POSTea AQUÍ, no a
        // `/admin/*` → sin esta exclusión, un admin/staff DESLOGUEADO no podría autenticarse
        // durante el mantenimiento (el `GET /admin/login` renderiza por estar excluido por path,
        // pero el ENVÍO del formulario quedaba en 503) = self-lockout del panel que controla el
        // propio kill-switch (auditoría Fase 1, Sistema 5). Se casa por NOMBRE de ruta porque el
        // path lleva un hash aleatorio (`livewire-<hash>/update`). Excluirlo NO reabre la web
        // pública: sus GET ya devuelven 503, así que un invitado nunca monta un componente Livewire
        // de una página pública desde el que POSTear (Livewire valida el snapshot).
        if ($request->routeIs('*livewire.update')) {
            return true;
        }

        return $request->is(
            'admin',
            'admin/*',
            'pago/redsys/*',
            'lang/*',
            'up',
        );
    }
}
