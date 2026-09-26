<?php

namespace App\Filament\Resources\BirthdayReminders;

use App\Domain\Identity\Models\BirthdayReminder;
use App\Filament\Resources\BirthdayReminders\Pages\ListBirthdayReminders;
use App\Filament\Resources\BirthdayReminders\Tables\BirthdayReminderTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * **«Avísame de fechas» en el panel** (`docs/specs/avisame-de-fechas.md` §4.4, `[DECIDIDO owner]` `#750`): quién pidió,
 * al firmar la autorización de un invitado, que le escribamos antes del cumpleaños de su hijo; para cuándo; y en qué
 * estado está (esperando · mandado · de baja). Solo se mira y se BORRA (la supresión a petición): la fila la escribe la
 * casilla del recibo, y el correo, `birthday-reminders:send`.
 *
 * **Acceso con `users.manage`**, el de los clientes: son datos de adultos con el cumpleaños de un menor. Vive en
 * «Ajustes → Precios y productos» (`AdminSettingsHub`), fuera del menú plano (#223).
 */
class BirthdayReminderResource extends Resource
{
    public const PERMISSION = 'users.manage';

    protected static ?string $model = BirthdayReminder::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCake;

    protected static ?string $slug = 'avisos-de-cumple';

    public static function getNavigationLabel(): string
    {
        return __('admin.birthday_reminders.nav_label');
    }

    public static function getModelLabel(): string
    {
        return __('admin.birthday_reminders.model_label_singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.birthday_reminders.model_label_plural');
    }

    public static function table(Table $table): Table
    {
        return BirthdayReminderTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBirthdayReminders::route('/'),
        ];
    }

    // ─── Autorización: `users.manage` ────────────────────────────────────────

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermission(self::PERMISSION) ?? false;
    }

    public static function canView($record): bool
    {
        return self::canViewAny();
    }

    /** Nace de la casilla del recibo, nunca del panel. */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    /** Borrar es la supresión a petición del interesado; la autorización firmada (la prueba del descargo) se queda. */
    public static function canDelete($record): bool
    {
        return self::canViewAny();
    }

    /** #223 — fuera del menú lateral: se entra por «Ajustes». Ocultar NO es autorizar (`canViewAny()`). */
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
}
