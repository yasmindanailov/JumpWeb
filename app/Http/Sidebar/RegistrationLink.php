<?php

namespace App\Http\Sidebar;

use App\Domain\Platform\Models\Setting;
use App\Providers\AppServiceProvider;

/**
 * El bloque de REGISTRO EXTERNO del cajón (#216): el enlace al sistema de registro del negocio que
 * se ofrece al cliente junto a su reserva confirmada.
 *
 * Es *data-driven* de principio a fin —URL, etiqueta y descripción salen de `settings`, las dos
 * últimas por idioma— y por eso no puede vivir quemado en ninguna vista. Lo componía
 * `Livewire\Tickets\Purchase` en privado; con `GET /api/v1/config` aparece el segundo consumidor,
 * así que la composición sube a un solo sitio (Fase 4 · paso 4.0b).
 *
 * **Vive en la capa de ENTREGA a propósito.** Lo que hace es leer ajustes, sanear una URL y caer a
 * un texto traducido: la i18n es presentación, y sus dos consumidores son de entrega. Subirlo a
 * Platform obligaría además a mover `safeExternalUrl` —que tiene diez puntos de uso y es el sink
 * de `SEC-07`— sin que nadie lo hubiera pedido.
 *
 * ⚠️ **`SEC-07` se aplica AQUÍ, y ese es el punto crítico.** La URL la edita un operador en el
 * panel, y `safeExternalUrl()` es lo único que impide que un `javascript:` llegue a un `href`. En
 * la web la defensa la remataba el escape de Blade; **un cliente JSON no tiene escape que lo
 * remate** —Vue no filtra esquemas en un `:href`—, así que si el saneado no ocurre antes de
 * serializar, no ocurre en ninguna parte. Por eso `current()` devuelve `null` cuando la URL no es
 * `http(s)`: el bloque entero desaparece en vez de viajar a medias.
 */
final readonly class RegistrationLink
{
    private function __construct(
        public string $url,
        public string $label,
        public string $description,
    ) {}

    /**
     * El bloque configurado en esta instalación, o `null` si no hay URL válida.
     *
     * Los textos caen al idioma activo y, si el operador no los ha traducido, al literal de `lang/`
     * — la misma cascada que tenía el sidebar.
     */
    public static function current(): ?self
    {
        $url = AppServiceProvider::safeExternalUrl(Setting::value('registration.url'));

        if ($url === null) {
            return null;
        }

        $locale = app()->getLocale();

        return new self(
            url: $url,
            label: (string) (Setting::value('registration.label.'.$locale) ?: __('landing.nav.register')),
            description: (string) (Setting::value('registration.description.'.$locale) ?: __('landing.nav.register_info')),
        );
    }

    /**
     * La forma que espera la vista del sidebar Livewire, que consume un array desde antes de que
     * esta clase existiera. Muere con `Purchase.php` en el paso 4.7.
     *
     * @return array{url: string, label: string, description: string}
     */
    public function toArray(): array
    {
        return ['url' => $this->url, 'label' => $this->label, 'description' => $this->description];
    }
}
