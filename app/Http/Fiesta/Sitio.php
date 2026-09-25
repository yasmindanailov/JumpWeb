<?php

namespace App\Http\Fiesta;

use App\Domain\Platform\Models\Setting;
use App\Providers\AppServiceProvider;

/**
 * LOS DATOS DEL SITIO que las páginas de la fiesta necesitan en el CONTROLADOR (`specs/fiesta-sistema-nuevo.md` §4.7):
 * el nombre y la ciudad del parque, su teléfono, su dirección, el enlace de mapas y la imagen de la vista previa.
 *
 * ⚠️⚠️ **`view()->shared('site')` en un controlador es `null`**: el `site` de las vistas lo pone un *view composer*
 * (`AppServiceProvider`), que corre al PINTAR la vista, y los modelos de página se componen ANTES. La T1a y la T2 lo
 * leían ahí y las páginas vivas decían el nombre de la aplicación en vez del parque y no tenían «Cómo llegar»; lo cazó
 * el ojo en la T3. Aquí se leen los MISMOS ajustes con las MISMAS reglas (el enlace externo pasa por
 * `safeExternalUrl`; el teléfono marcable son solo dígitos y `+`), sin depender de cuándo corre el composer.
 */
final class Sitio
{
    /**
     * @return array{name: string, city: string, phone: string, phone_tel: string, address1: string, address2: string, maps: string, og_image: ?string}
     */
    public static function datos(): array
    {
        $telefono = trim((string) (Setting::value('contact.phone') ?? ''));
        $nombre = trim((string) (Setting::value('business.name') ?? ''));

        return [
            'name' => $nombre !== '' ? $nombre : (string) config('app.name'),
            'city' => trim((string) (Setting::value('business.city') ?? '')),
            'phone' => $telefono,
            'phone_tel' => (string) preg_replace('/[^0-9+]/', '', $telefono),
            'address1' => trim((string) (Setting::value('address.line1') ?? '')),
            'address2' => trim((string) (Setting::value('address.line2') ?? '')),
            'maps' => AppServiceProvider::safeExternalUrl(Setting::value('address.maps_url')) ?? '',
            'og_image' => AppServiceProvider::safeExternalUrl(Setting::value('seo.og_image')),
        ];
    }
}
