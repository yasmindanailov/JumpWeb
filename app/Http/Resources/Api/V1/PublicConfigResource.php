<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Booking\Services\CatalogSettings;
use App\Domain\Booking\Services\OrderCreator;
use App\Domain\Platform\Services\Turnstile;
use App\Http\Sidebar\RegistrationLink;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Fase 4 · paso 4.0b — los ajustes de INSTALACIÓN que un cliente necesita para pintar el cajón bien
 * a la primera (`docs/specs/sidebar-spa.md` §4.4).
 *
 * Son cuatro cosas que hoy Blade le inyecta a la vista y que ningún endpoint publicaba: el bloque de
 * registro externo, el umbral del buscador, la clave pública del anti-bot y el tope de líneas de la
 * cesta. Sin ellas, un cliente **aprende las reglas chocándose** —descubre el tope con un 422— y eso
 * es exactamente una regla de negocio naciendo en el cliente.
 *
 * **Van juntas y no repartidas** por el número de peticiones: sueltas serían dos endpoints más y uno
 * de ellos caería en el paso de identificación, con el usuario esperando. Juntas son **una** petición
 * pública en el arranque, paralela a las del catálogo.
 *
 * ⚠️ **Regla de admisión de este endpoint, para que no degenere en cajón de sastre**: solo entra lo
 * que es (a) de la INSTALACIÓN —no del titular, no del catálogo, no de un pedido—, (b) **estático
 * entre despliegues o cambios de ajuste**, y (c) necesario para PINTAR, no para decidir. Lo que
 * cambia mientras el cliente navega —la pausa de reservas— vive en `GET /booking/status`, porque un
 * snapshot de arranque mentiría en cuanto la dueña accionara el interruptor.
 *
 * ⚠️ **Nunca puede llevar**: nada del titular autenticado (es público y cacheable), ningún secreto
 * (la *sitekey* de Turnstile es pública POR DISEÑO; la `secret` no sale de aquí jamás) y ninguna URL
 * sin sanear (`SEC-07`, ver `RegistrationLink`).
 */
class PublicConfigResource extends JsonResource
{
    /** El recurso va en la raíz: lo devuelve el propio controlador (§4.3 del spec de la API). */
    public static $wrap = null;

    /** No envuelve ningún modelo: los cuatro valores salen de sus lectores defensivos. */
    public function __construct()
    {
        parent::__construct(null);
    }

    /**
     * ⚠️ **Los dos números llevan su OPERADOR en la descripción del contrato, no solo su valor.**
     * Medido: el buscador aparece con `total > search_min_items` —estrictamente mayor, y sobre el
     * catálogo SIN filtrar— y el servidor rechaza una cesta con `líneas > cart_max_lines`. Publicar
     * el número sin el operador reparte la regla entre servidor y cliente, que es como divergen.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $registration = RegistrationLink::current();

        return [
            'registration' => $registration?->toArray(),
            'catalog_search_min_items' => CatalogSettings::searchMinItems(),
            'cart_max_lines' => OrderCreator::MAX_LINES_PER_CART,
            // `null` cuando la instalación no tiene anti-bot configurado, que es un estado NORMAL:
            // sin claves el widget es un no-op y el registro funciona igual (`SEGURIDAD` regla 5).
            // Decirlo con `null` es más honesto que omitir el campo — el cliente sabe que preguntó.
            //
            // ⚠️ **`enabled()`, no `siteKey()`, y la diferencia es medible**: el anti-bot exige las DOS
            // claves, y con solo la pública `Turnstile::enabled()` es `false` — la web **no pinta el
            // widget** y `verify()` deja pasar el alta—. Publicar la clave en ese estado le decía al
            // cliente que dibujara un captcha que su propio servidor no comprueba: un árbol distinto
            // al de la web, un script de terceros de más y un obstáculo para el usuario a cambio de
            // ninguna defensa. Lo destapó el paso de registro de la SPA, que es el primer cliente que
            // lee este campo para decidir.
            'turnstile_site_key' => Turnstile::enabled() ? Turnstile::siteKey() : null,
        ];
    }
}
