<?php

namespace App\Livewire\Admin\Puerta;

/**
 * **El VEREDICTO de la Puerta nueva** (`docs/specs/puerta-nueva.md` §4.4, la P1): lo que el empleado lee de reojo, en
 * PALABRAS y en color —el color nunca va solo—, y el sonido que lo acompaña. Sustituye al semáforo de
 * `identidad-qr-puerta.md` §9.7 C·5, como DATO fuera de la plantilla: la vista pinta, no decide.
 *
 * ▶ **El veredicto es el DESCARGO, no la reserva** (el mockup, 27-09): verde si el titular y TODOS sus menores a cargo lo
 * tienen —tenga reserva hoy o no—; ámbar si falta alguno. Uno de una versión ANTERIOR del texto deja pasar y lo dice su
 * tarea, como hoy (§4.8 de la puerta). Con el descargo APAGADO (`#216`), verde siempre: no hay nada que firmar.
 *
 * ⚠️ La tabla es TOTAL: un `ValidarRegistro::STATUS_*` sin entrada dejaría al empleado sin nada que leer tras escanear.
 * `GateVerdictTest` recorre las constantes por reflexión.
 */
final class GateVerdict
{
    public const VERDE = 'verde';

    public const AMBAR = 'ambar';

    public const ROJO = 'rojo';

    public const GRIS = 'gris';

    /**
     * Estado → [tono, clave del texto, clave de la línea de debajo o `null`, ¿es un AVISO de la búsqueda?]. Los avisos
     * no hablan del cliente sino de lo tecleado: van en gris y sin sonido. El rojo es SOLO «No encontrado» (el mockup):
     * un QR que no se reconoce no significa que el cliente no exista.
     *
     * @var array<string, array{string, string, ?string, bool}>
     */
    private const STATES = [
        ValidarRegistro::STATUS_REGISTERED_WITH_WAIVER => [self::VERDE, 'pass', null, false],
        ValidarRegistro::STATUS_REGISTERED => [self::VERDE, 'pass', null, false],
        ValidarRegistro::STATUS_REGISTERED_NO_WAIVER => [self::AMBAR, 'sign', null, false],
        ValidarRegistro::STATUS_NOT_REGISTERED => [self::ROJO, 'not_found', 'not_found_sub', false],
        ValidarRegistro::STATUS_CARD_REVOKED => [self::AMBAR, 'card_revoked', 'card_revoked_sub', false],
        ValidarRegistro::STATUS_CARD_UNKNOWN => [self::GRIS, 'card_unknown', 'card_unknown_sub', false],
        ValidarRegistro::STATUS_INVALID_INPUT => [self::GRIS, 'invalid', null, true],
        ValidarRegistro::STATUS_RATE_LIMITED => [self::GRIS, 'rate_limited', null, true],
        ValidarRegistro::STATUS_LOOKUP_LIMITED => [self::GRIS, 'lookup_limited', null, true],
    ];

    /** @return list<string> los estados con veredicto, para la guarda de totalidad */
    public static function states(): array
    {
        return array_keys(self::STATES);
    }

    /**
     * @param  array<string, mixed>  $result  el `$result` de `ValidarRegistro`
     * @param  array<string, mixed>|null  $profile  la FICHA, si el empleado puede verla; manda sobre el estado
     * @return array{tone: string, text: string, sub: ?string, notice: bool, sound: ?string}
     */
    public static function for(array $result, ?array $profile): array
    {
        if ($profile !== null) {
            return self::coveredByWaiver($profile)
                ? self::of(self::VERDE, 'pass', null, false)
                : self::of(self::AMBAR, 'sign', null, false);
        }

        $status = (string) ($result['status'] ?? '');
        [$tone, $text, $sub, $notice] = self::STATES[$status] ?? [self::GRIS, 'invalid', null, true];
        $verdict = self::of($tone, $text, $sub, $notice);

        // SIN ficha (sin `puerta.profile`: solo el semáforo) el veredicto conserva la línea que daba el semáforo, que es
        // lo único que ese empleado puede saber: la fecha del descargo —y que es de una versión anterior, que deja pasar—,
        // «tiene cuenta» con el descargo apagado y qué hacer si le falta.
        $verdict['sub'] = match ($status) {
            ValidarRegistro::STATUS_REGISTERED_WITH_WAIVER => trim(__('admin.puerta.validar.waiver_date', ['date' => (string) ($result['date'] ?? '')])
                .(($result['outdated'] ?? false) ? ' '.__('admin.waiver.gate_outdated') : '')),
            ValidarRegistro::STATUS_REGISTERED => (string) __('admin.puerta.validar.registered_sub'),
            ValidarRegistro::STATUS_REGISTERED_NO_WAIVER => (string) __('admin.puerta.validar.registered_no_waiver_cta'),
            default => $verdict['sub'],
        };

        return $verdict;
    }

    /**
     * ¿Pasa? Con el descargo apagado, sí; si no, el titular firmado y ningún menor a cargo SIN descargo (`missing`). Un
     * `outdated` deja pasar (lo dice su tarea) y un `null` es un menor fuera del modo interno, que no se mide aquí.
     *
     * @param  array<string, mixed>  $profile
     */
    public static function coveredByWaiver(array $profile): bool
    {
        $waiver = (array) ($profile['waiver'] ?? []);
        if (! ($waiver['enabled'] ?? false)) {
            return true;
        }
        if (! ($waiver['signed'] ?? false)) {
            return false;
        }

        foreach ((array) ($profile['dependents'] ?? []) as $minor) {
            if (is_array($minor) && ($minor['waiver'] ?? null) === 'missing') {
                return false;
            }
        }

        return true;
    }

    /** @return array{tone: string, text: string, sub: ?string, notice: bool, sound: ?string} */
    private static function of(string $tone, string $text, ?string $sub, bool $notice): array
    {
        return [
            'tone' => $tone,
            'text' => (string) __('admin.puerta.veredicto.'.$text),
            'sub' => $sub === null ? null : (string) __('admin.puerta.veredicto.'.$sub),
            'notice' => $notice,
            // Un sonido corto distinto para verde, ámbar y rojo; el gris y los avisos, callados (el mockup).
            'sound' => $notice || $tone === self::GRIS ? null : $tone,
        ];
    }
}
