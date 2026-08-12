<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Support\AuditLogger;
use App\Support\MaintenanceSettings;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;

/**
 * Mantenimiento — subsistema de disponibilidad (#218, fuera de roadmap).
 *
 * Centro de control para apagar/encender la web pública (item 2), las reservas (item 3) y páginas
 * concretas (item 1). El *enforcement* vive fuera (middleware `EnsureSiteAvailable`, los CTAs y
 * los guards de servidor); aquí solo está la SUPERFICIE de edición.
 *
 * **Iteración 1:** mantenimiento de SITIO ENTERO (toggle + mensaje opcional por idioma). Las
 * reservas y el per-página se añaden a ESTA MISMA página en iteraciones siguientes.
 *
 * Acceso **solo admin** (`settings.manage`). Patrón form-en-Page idéntico a `Settings` (mount/save/
 * audit; booleanos como '1'/'0'; lectura en vivo sin caché → el toggle surte efecto al instante).
 */
class Maintenance extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static ?string $slug = 'maintenance';

    // Justo tras «Ajustes» (10) dentro del cluster «Configuración».
    protected static ?int $navigationSort = 20;

    protected string $view = 'filament.pages.maintenance';

    /** Estado del formulario (claves de `settings` anidadas por el punto). */
    public ?array $data = [];

    /**
     * Claves gestionadas → su `group` (fuente única para cargar/guardar). Las claves por página se
     * DERIVAN de `MaintenanceSettings::PAGE_KEYS` (un único punto de verdad: añadir una página allí
     * la propaga aquí, a `boolKeys()` y al formulario, sin riesgo de desincronización).
     *
     * @return array<string,string>
     */
    private static function managed(): array
    {
        $managed = [
            'maintenance.site' => 'maintenance',
            'maintenance.message.es' => 'maintenance',
            'maintenance.message.en' => 'maintenance',
            'maintenance.message.fr' => 'maintenance',
            'reservations.paused' => 'maintenance',
            'reservations.title.es' => 'maintenance',
            'reservations.title.en' => 'maintenance',
            'reservations.title.fr' => 'maintenance',
            'reservations.message.es' => 'maintenance',
            'reservations.message.en' => 'maintenance',
            'reservations.message.fr' => 'maintenance',
        ];

        foreach (MaintenanceSettings::PAGE_KEYS as $key) {
            $managed['maintenance.page.'.$key] = 'maintenance';
        }

        return $managed;
    }

    /**
     * Ajustes booleanos (se guardan como '1'/'0'). Derivados de la misma fuente que `managed()`
     * para que ningún toggle se guarde como texto por un descuido de sincronización.
     *
     * @return list<string>
     */
    private static function boolKeys(): array
    {
        return array_merge(
            ['maintenance.site', 'reservations.paused'],
            array_map(fn (string $k): string => 'maintenance.page.'.$k, MaintenanceSettings::PAGE_KEYS),
        );
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermission('settings.manage') ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasPermission('settings.manage') ?? false;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav_groups.sistema');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.maintenance.nav_label');
    }

    public function getTitle(): string
    {
        return __('admin.maintenance.title');
    }

    public function mount(): void
    {
        $values = [];
        $boolKeys = self::boolKeys();
        foreach (array_keys(self::managed()) as $key) {
            $raw = Setting::value($key);
            $value = in_array($key, $boolKeys, true)
                ? ((string) $raw === '1')
                : ($raw ?? '');
            data_set($values, $key, $value);
        }

        $this->form->fill($values);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                $this->siteSection(),
                $this->reservationsSection(),
                $this->pagesSection(),
            ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();

        /** @var array<string,mixed> $flat */
        $flat = Arr::dot($state);

        $changes = [];
        $boolKeys = self::boolKeys();
        foreach (self::managed() as $key => $group) {
            $value = $flat[$key] ?? null;
            if (in_array($key, $boolKeys, true)) {
                $value = $value ? '1' : '0';
            }
            $value = $value === null ? '' : trim((string) $value);

            $old = (string) (Setting::value($key) ?? '');
            if ($old !== $value) {
                $changes[$key] = ['from' => $old, 'to' => $value];
            }

            Setting::updateOrCreate(['key' => $key], ['value' => $value, 'group' => $group]);
        }

        if ($changes !== []) {
            AuditLogger::log('maintenance.updated', null, ['changed' => $changes]);
        }

        Notification::make()
            ->title(__('admin.maintenance.saved'))
            ->success()
            ->send();
    }

    // ─── Secciones del formulario ──────────────────────────────────────────────

    private function siteSection(): Section
    {
        return Section::make(__('admin.maintenance.section_site'))
            ->description(__('admin.maintenance.section_site_hint'))
            ->schema([
                Toggle::make('maintenance.site')
                    ->label(__('admin.maintenance.site_enabled'))
                    ->helperText(__('admin.maintenance.site_enabled_hint')),
                $this->localeTabs('maintenance_message', fn (string $loc): array => [
                    Textarea::make("maintenance.message.{$loc}")
                        ->label(__('admin.maintenance.message'))
                        ->helperText(__('admin.maintenance.message_hint'))
                        ->rows(3)
                        ->maxLength(500),
                ]),
            ]);
    }

    /**
     * Tabs es/en/fr de textos EDITABLES POR IDIOMA. `$fields($loc)` devuelve los componentes de ese
     * idioma → reutilizable para 1 campo (mensaje de SITIO) o varios (RESERVAS: título + cuerpo).
     *
     * @param  callable(string): array<int, mixed>  $fields
     */
    private function localeTabs(string $name, callable $fields): Tabs
    {
        return Tabs::make($name)->tabs([
            Tab::make(__('admin.settings.lang_es'))->schema($fields('es')),
            Tab::make(__('admin.settings.lang_en'))->schema($fields('en')),
            Tab::make(__('admin.settings.lang_fr'))->schema($fields('fr')),
        ]);
    }

    private function reservationsSection(): Section
    {
        return Section::make(__('admin.maintenance.section_reservations'))
            ->description(__('admin.maintenance.section_reservations_hint'))
            ->schema([
                Toggle::make('reservations.paused')
                    ->label(__('admin.maintenance.reservations_paused'))
                    ->helperText(__('admin.maintenance.reservations_paused_hint')),
                $this->localeTabs('reservations_message', fn (string $loc): array => [
                    TextInput::make("reservations.title.{$loc}")
                        ->label(__('admin.maintenance.reservations_title'))
                        ->helperText(__('admin.maintenance.reservations_title_hint'))
                        ->maxLength(120),
                    Textarea::make("reservations.message.{$loc}")
                        ->label(__('admin.maintenance.reservations_message'))
                        ->helperText(__('admin.maintenance.reservations_message_hint'))
                        ->rows(3)
                        ->maxLength(500),
                ]),
            ]);
    }

    /**
     * Un toggle por PÁGINA concreta (`PAGE_KEYS`): al activarlo, esa sección muestra «no disponible»
     * (503) con el nav/pie para navegar al resto. Útil para retocar una página sin cerrar la web.
     */
    private function pagesSection(): Section
    {
        return Section::make(__('admin.maintenance.section_pages'))
            ->description(__('admin.maintenance.section_pages_hint'))
            ->columns(2)
            ->collapsed()
            ->schema(
                array_map(
                    fn (string $key): Toggle => Toggle::make("maintenance.page.{$key}")
                        ->label(__('admin.maintenance.page_'.$key)),
                    MaintenanceSettings::PAGE_KEYS,
                ),
            );
    }
}
