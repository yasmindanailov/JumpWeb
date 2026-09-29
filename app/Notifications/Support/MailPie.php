<?php

namespace App\Notifications\Support;

use App\Domain\Booking\Contracts\OperatingCalendar;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\DisplayTime;
use Carbon\CarbonInterface;

/**
 * **EL PIE de todos los correos** (la R1a, `specs/correos-rediseno.md` §4.1.1): lo que dice el brief —«dirección,
 * horario de hoy, teléfono, WhatsApp y correo»—, del PANEL y del calendario de operación. Nada del parque en el código.
 *
 * ⚠️ **Cada línea solo si su dato existe**, como el pie de septiembre (`#503`): una instalación sin dirección no pinta
 * una línea vacía, y una sin WhatsApp no ofrece un enlace roto.
 *
 * ⚠️ **El horario de HOY lleva su día** («Hoy, jueves 24, abrimos de 16:30 a 21:30.»): el correo se puede leer mañana.
 * Sale de `OperatingCalendar::windowFor()`, que aplica la prioridad del dominio (fecha especial > temporada > semana);
 * abierto SIN ventana —el recinto sin horario configurado— no se pinta: sería inventar unas horas.
 *
 * El teléfono y el WhatsApp, normalizados como en `MaintenanceSettings`: el `tel:` sin espacios y el WhatsApp en dígitos.
 */
final readonly class MailPie
{
    public function __construct(
        public string $nombre,
        public ?string $direccion,
        public ?string $hoy,
        public ?string $telefono,
        public ?string $tel,
        public ?string $whatsapp,
        public ?string $correo,
    ) {}

    public static function current(?CarbonInterface $dia = null): self
    {
        $direccion = implode(', ', array_filter([
            trim((string) Setting::value('address.line1', '')),
            trim((string) Setting::value('address.line2', '')),
        ], static fn (string $t): bool => $t !== ''));
        $telefono = trim((string) Setting::value('contact.phone', ''));
        $whatsapp = (string) preg_replace('/\D/', '', (string) Setting::value('contact.whatsapp', ''));
        $correo = trim((string) Setting::value('contact.email', ''));

        return new self(
            nombre: Setting::businessName(),
            direccion: $direccion !== '' ? $direccion : null,
            hoy: self::hoy($dia ?? DisplayTime::today()),
            telefono: $telefono !== '' ? $telefono : null,
            tel: $telefono !== '' ? (string) preg_replace('/\s+/', '', $telefono) : null,
            whatsapp: $whatsapp !== '' ? $whatsapp : null,
            correo: filter_var($correo, FILTER_VALIDATE_EMAIL) !== false ? $correo : null,
        );
    }

    private static function hoy(CarbonInterface $dia): ?string
    {
        $ventana = app(OperatingCalendar::class)->windowFor($dia);
        $cuando = DisplayTime::dayInSentence($dia);

        if (! $ventana->isOpen) {
            return (string) __('emails.pie.hoy_cerrado', ['dia' => $cuando]);
        }

        if (! $ventana->hasHours()) {
            return null;
        }

        return (string) __('emails.pie.hoy_abierto', [
            'dia' => $cuando,
            'desde' => substr((string) $ventana->opensAt, 0, 5),
            'hasta' => substr((string) $ventana->closesAt, 0, 5),
        ]);
    }
}
