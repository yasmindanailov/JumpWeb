<?php

namespace App\Livewire\Admin\Puerta;

use Filament\Support\Icons\Heroicon;

/**
 * Fase 6 · subsistema A — el SEMÁFORO de la pantalla de puerta, como DATO
 * (`docs/specs/identidad-qr-puerta.md` §9.7 C·5).
 *
 * Antes esto era un `@switch` de 8 casos en `validar.blade.php` y cada caso repetía la MISMA tarjeta
 * con otra paleta escrita a mano (medido: 8 bloques, 167 utilidades distintas en la vista y la paleta
 * quemada). Repetir el continente es lo que hace que un estado nuevo salga con otro aspecto que los
 * demás, o que alguien arregle el color en siete sitios de ocho. Aquí solo vive **el tono** de cada
 * estado; el continente es UN `x-filament::callout` en la vista.
 *
 * El color se nombra con los semánticos que el panel ya registra (`AdminPanelProvider::colors()`):
 * `success` verde, `warning` naranja, `danger` rojo, `gray` neutro. No se inventan paletas: las
 * variables `--success-*` las emite `@filamentStyles` y son las mismas del resto del panel.
 *
 * ⚠️ La tabla tiene que ser TOTAL: un `ValidarRegistro::STATUS_*` sin tono se pintaría sin callout y
 * el empleado no vería NADA tras pulsar «Verificar». `GateSemaphoreTest` recorre las constantes por
 * reflexión y cae si aparece una sin entrada.
 */
final class GateSemaphore
{
    /**
     * Estado → [color semántico, icono]. Los rótulos NO viven aquí: son claves de `lang` y las
     * resuelve {@see self::for()}, que es lo que la vista consume.
     *
     * @var array<string, array{string, Heroicon}>
     */
    private const TONES = [
        // Verdes: el cliente está cubierto y puede pasar.
        ValidarRegistro::STATUS_REGISTERED_WITH_WAIVER => ['success', Heroicon::OutlinedCheckCircle],
        ValidarRegistro::STATUS_REGISTERED => ['success', Heroicon::OutlinedCheckCircle],

        // Naranjas: hay cliente, pero falta un paso que el empleado tiene que dar AHORA.
        ValidarRegistro::STATUS_REGISTERED_NO_WAIVER => ['warning', Heroicon::OutlinedExclamationCircle],
        ValidarRegistro::STATUS_CARD_REVOKED => ['warning', Heroicon::OutlinedExclamationCircle],

        // Rojo: no hay nadie con ese dato. Es el único estado sin camino dentro del sistema.
        ValidarRegistro::STATUS_NOT_REGISTERED => ['danger', Heroicon::OutlinedXCircle],

        // Grises: no dicen nada del cliente, sino de la BÚSQUEDA. Que NO sean rojos es deliberado —
        // un carné no reconocido no significa «no registrado» y teñirlo de rojo empuja al empleado a
        // negar la entrada a alguien que sí está en el sistema.
        ValidarRegistro::STATUS_CARD_UNKNOWN => ['gray', Heroicon::OutlinedQuestionMarkCircle],
        ValidarRegistro::STATUS_INVALID_INPUT => ['gray', Heroicon::OutlinedQuestionMarkCircle],
        ValidarRegistro::STATUS_RATE_LIMITED => ['gray', Heroicon::OutlinedClock],
        ValidarRegistro::STATUS_LOOKUP_LIMITED => ['warning', Heroicon::OutlinedClock],
    ];

    /** Los colores que el panel registra (`AdminPanelProvider::colors()` + los de Filament). */
    public const ALLOWED_COLORS = ['success', 'warning', 'danger', 'gray', 'info', 'primary'];

    /** @return array<string, array{string, Heroicon}> */
    public static function tones(): array
    {
        return self::TONES;
    }

    /**
     * El semáforo listo para pintar: color, icono y los textos YA traducidos.
     *
     * `heading` puede ser `null` a propósito: los tres estados que hablan de la búsqueda
     * (`invalid_input`, `rate_limited`, `lookup_limited`) tienen una frase completa como rótulo y
     * partirla en título + cuerpo obligaría a inventar rótulos nuevos para no decir nada más.
     *
     * @param  array<string, mixed>  $result  lo que `ValidarRegistro::$result` guarda
     * @return array{color: string, icon: Heroicon, heading: ?string, body: ?string, note: ?string}
     */
    public static function for(array $result): array
    {
        $status = (string) ($result['status'] ?? '');
        [$color, $icon] = self::TONES[$status] ?? ['gray', Heroicon::OutlinedQuestionMarkCircle];

        [$heading, $body] = match ($status) {
            ValidarRegistro::STATUS_REGISTERED_WITH_WAIVER => [
                __('admin.puerta.validar.registered_with_waiver'),
                __('admin.puerta.validar.waiver_date', ['date' => (string) ($result['date'] ?? '')]),
            ],
            ValidarRegistro::STATUS_REGISTERED => [
                __('admin.puerta.validar.registered'),
                __('admin.puerta.validar.registered_sub'),
            ],
            ValidarRegistro::STATUS_REGISTERED_NO_WAIVER => [
                __('admin.puerta.validar.registered_no_waiver'),
                __('admin.puerta.validar.registered_no_waiver_cta'),
            ],
            ValidarRegistro::STATUS_NOT_REGISTERED => [
                __('admin.puerta.validar.not_registered'),
                null,
            ],
            ValidarRegistro::STATUS_CARD_REVOKED => [
                __('admin.puerta.validar.card_revoked'),
                __('admin.puerta.validar.card_revoked_cta'),
            ],
            ValidarRegistro::STATUS_CARD_UNKNOWN => [
                __('admin.puerta.validar.card_unknown'),
                __('admin.puerta.validar.card_unknown_cta'),
            ],
            ValidarRegistro::STATUS_INVALID_INPUT => [null, __('admin.puerta.validar.invalid_input')],
            ValidarRegistro::STATUS_RATE_LIMITED => [null, __('admin.puerta.validar.rate_limited')],
            ValidarRegistro::STATUS_LOOKUP_LIMITED => [null, __('admin.puerta.validar.lookup_limited')],
            default => [null, null],
        };

        return [
            'color' => $color,
            'icon' => $icon,
            'heading' => $heading,
            'body' => $body,
            // Fase 6 · waiver (§4.8): firmó una versión ANTERIOR del texto. Se señala y se DEJA PASAR;
            // la re-firma se pide en la siguiente compra o inicio de sesión, no en el mostrador.
            'note' => ($status === ValidarRegistro::STATUS_REGISTERED_WITH_WAIVER && ($result['outdated'] ?? false))
                ? __('admin.waiver.gate_outdated')
                : null,
        ];
    }
}
