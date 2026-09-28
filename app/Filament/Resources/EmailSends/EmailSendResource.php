<?php

namespace App\Filament\Resources\EmailSends;

use App\Domain\Platform\Models\EmailSend;
use App\Filament\Resources\EmailSends\Pages\ListEmailSends;
use App\Filament\Resources\EmailSends\Tables\EmailSendTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * **Los correos enviados a los clientes** (`specs/correos-salientes.md` §4.2, `DECISIONES #794`, la C1): quién, cuál, cuándo,
 * si salió, y la vista previa TAL CUAL salió.
 *
 * ⚠️ **Permiso propio** (`emails.view`, fuera del staff por defecto): leer un correo es leer datos de una persona, y tres
 * llevan el nombre de un menor. Solo lectura: ni crear, ni editar, ni borrar (la poda y la supresión son las únicas que
 * quitan algo). **Fuera del menú plano** (`#223`): se llega desde «Clientes» y desde la ficha de cada cliente.
 */
class EmailSendResource extends Resource
{
    public const PERMISSION = 'emails.view';

    protected static ?string $model = EmailSend::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?string $slug = 'correos-enviados';

    public static function getNavigationLabel(): string
    {
        return __('admin.email_sends.nav_label');
    }

    public static function getModelLabel(): string
    {
        return __('admin.email_sends.model_label_singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.email_sends.model_label_plural');
    }

    public static function table(Table $table): Table
    {
        return EmailSendTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmailSends::route('/'),
        ];
    }

    /** La página filtrada a los correos de una cuenta (desde su ficha). */
    public static function urlForUser(int $userId): string
    {
        return self::getUrl('index', ['filters' => ['user_id' => ['value' => $userId]]]);
    }

    // ─── Autorización: `emails.view`, re-evaluada en cada petición (`SEC-04`) ─────────────────

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermission(self::PERMISSION) ?? false;
    }

    public static function canView($record): bool
    {
        return self::canViewAny();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
}
