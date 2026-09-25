<?php

namespace App\Http\Fiesta;

/**
 * LA MARCA de la instalación en las páginas de la fiesta: su logotipo (`img/client-logo.svg`, del paquete de
 * instalación, `INSTALACION-CLIENTE.md`) o, sin él, su nombre. Lo comparten la lista, la invitación y la autorización:
 * el producto no nombra el logotipo de ningún cliente, solo el hueco.
 */
final class Marca
{
    /**
     * @param  array<string, mixed>  $site
     * @return array{src: ?string, alt: string}
     */
    public static function logo(array $site): array
    {
        $svg = @filemtime(public_path('img/client-logo.svg'));

        return [
            'src' => $svg ? asset('img/client-logo.svg').'?v='.$svg : null,
            'alt' => (string) ($site['name'] ?? config('app.name')),
        ];
    }
}
