<?php

namespace App\Filament\Support;

use App\Support\ThemeSettings;
use Filament\AvatarProviders\Contracts\AvatarProvider;
use Illuminate\Database\Eloquent\Model;

/**
 * Avatar LOCAL (data-URI SVG con iniciales) para el panel admin.
 *
 * El proveedor por defecto de Filament (`UiAvatarsProvider`) genera el avatar en
 * `ui-avatars.com` (externo), y la CSP estricta del sitio (`img-src 'self' data:`,
 * ver `SecurityHeaders`) lo BLOQUEA → error de consola + avatar roto. Este proveedor
 * devuelve un `data:` URI (permitido por la CSP), sin llamadas externas ni fuga de la
 * inicial del usuario a un tercero.
 */
class InitialsAvatarProvider implements AvatarProvider
{
    public function get(Model $record): string
    {
        $name = trim((string) ($record->name ?? $record->email ?? '?'));
        $parts = preg_split('/\s+/', $name, -1, PREG_SPLIT_NO_EMPTY) ?: ['?'];
        $initials = mb_strtoupper(
            mb_substr($parts[0] ?? '?', 0, 1).(isset($parts[1]) ? mb_substr($parts[1], 0, 1) : '')
        );

        $bg = ltrim((string) ThemeSettings::brand(), '#');
        if (preg_match('/^[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', $bg) !== 1) {
            $bg = '18181b';
        }

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">'
            .'<rect width="64" height="64" rx="8" fill="#'.$bg.'"/>'
            .'<text x="32" y="34" fill="#ffffff" font-family="Arial,Helvetica,sans-serif" '
            .'font-size="26" font-weight="600" text-anchor="middle" dominant-baseline="central">'
            .htmlspecialchars($initials, ENT_QUOTES).'</text></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
