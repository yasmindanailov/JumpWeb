<?php

namespace App\Filament\Resources\Testimonials;

use App\Domain\Content\Models\Testimonial;
use App\Filament\Concerns\ProvidesGlobalSearch;
use App\Filament\Resources\Testimonials\Pages\CreateTestimonial;
use App\Filament\Resources\Testimonials\Pages\EditTestimonial;
use App\Filament\Resources\Testimonials\Pages\ListTestimonials;
use App\Filament\Resources\Testimonials\Schemas\TestimonialForm;
use App\Filament\Resources\Testimonials\Tables\TestimonialTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * **Las opiniones PROPIAS del parque** (`DECISIONES #490`, sección 06 de la portada).
 *
 * ❗❗❗ **La ayuda de esta pantalla NO puede decir «por si Google falla», y su spec lo advierte
 * expresamente** (`specs/google-reviews.md` §4.4.bis): sería falso, y además es **la razón por la
 * que el parque las dejaría vacías**. Por §3.3, esto es lo que ve **todo visitante que no acepta
 * cookies de terceros** —sin consentimiento no se puede servir ni una reseña de Google—, o sea una
 * parte del tráfico **cada día**. Los textos de `admin.testimonials.*` están escritos con esas
 * palabras a propósito.
 *
 * **Acceso solo admin** (`content.manage`, el mismo permiso que FAQ y zonas). Vive en «Ajustes».
 */
class TestimonialResource extends Resource
{
    use ProvidesGlobalSearch;

    protected static ?string $model = Testimonial::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $slug = 'opiniones';

    protected static ?int $navigationSort = 21;

    public static function getNavigationLabel(): string
    {
        return __('admin.testimonials.nav_label');
    }

    public static function getModelLabel(): string
    {
        return __('admin.testimonials.model_label_singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.testimonials.model_label_plural');
    }

    public static function form(Schema $schema): Schema
    {
        return TestimonialForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TestimonialTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTestimonials::route('/'),
            'create' => CreateTestimonial::route('/create'),
            'edit' => EditTestimonial::route('/{record}/edit'),
        ];
    }

    // ─── Autorización: solo admin (Gate::before) vía `content.manage` ─────────

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermission('content.manage') ?? false;
    }

    public static function canView($record): bool
    {
        return auth()->user()?->hasPermission('content.manage') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasPermission('content.manage') ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->hasPermission('content.manage') ?? false;
    }

    public static function canDelete($record): bool
    {
        return ($record instanceof Testimonial)
            && (auth()->user()?->hasPermission('content.manage') ?? false);
    }

    /**
     * Fuera del menú lateral (`#223`): es pantalla de puesta en marcha y se entra por «Ajustes».
     * ⚠️ Ocultar NO es autorizar: el acceso lo siguen decidiendo `canViewAny()`/`canAccess()`.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
}
