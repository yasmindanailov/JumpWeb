<?php

namespace App\Filament\Pages;

use App\Domain\Booking\Models\OpeningHour;
use App\Domain\Platform\Services\AuditLogger;
use App\Filament\Concerns\RegeneratesSlots;
use BackedEnum;
use Carbon\Carbon;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Fase 7.7 — Horario semanal general del parque (`opening_hours`): por día de la semana,
 * apertura/cierre o cerrado. Es la fuente única que usan las RESERVAS (vía `OperatingSchedule` →
 * generador de franjas + compra) y la LANDING (card de «ubicación y horario»). Las
 * excepciones puntuales se hacen en «Fechas especiales»; los tramos estacionales en
 * «Temporadas» (#207).
 *
 * **Acceso solo admin** (`slots.manage`). Orden de los días: lunes→domingo (europeo); el
 * valor almacenado es el `weekday` de Carbon (0=domingo..6=sábado).
 */
class WeeklySchedule extends Page
{
    use RegeneratesSlots;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?string $slug = 'horario';

    // Tras Tarifas, dentro del cluster "Configuración".
    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.pages.weekly-schedule';

    /** Estado del formulario: day_{N}_open / day_{N}_close / day_{N}_closed por weekday. */
    public ?array $data = [];

    /** Orden de presentación (lunes primero); el valor es el weekday de Carbon. */
    private const DISPLAY_ORDER = [1, 2, 3, 4, 5, 6, 0];

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermission('slots.manage') ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasPermission('slots.manage') ?? false;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav_groups.programacion');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.weekly_schedule.nav_label');
    }

    public function getTitle(): string
    {
        return __('admin.weekly_schedule.title');
    }

    /** «Regenerar franjas» a mano: tras cambiar el horario, aplica los cambios a las franjas. */
    protected function getHeaderActions(): array
    {
        return [
            $this->regenerateSlotsAction(),
        ];
    }

    public function mount(): void
    {
        $rows = OpeningHour::all()->keyBy('weekday');

        $values = [];
        foreach (self::DISPLAY_ORDER as $weekday) {
            $row = $rows->get($weekday);
            $values["day_{$weekday}_closed"] = (bool) ($row?->is_closed ?? false);
            $values["day_{$weekday}_open"] = $row?->open_time !== null ? substr((string) $row->open_time, 0, 5) : null;
            $values["day_{$weekday}_close"] = $row?->close_time !== null ? substr((string) $row->close_time, 0, 5) : null;
        }

        $this->form->fill($values);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components(array_map(fn (int $weekday): Section => $this->dayRow($weekday), self::DISPLAY_ORDER));
    }

    private function dayRow(int $weekday): Section
    {
        return Section::make(__('admin.rate_types.weekdays.'.$weekday))
            ->columns(3)
            ->schema([
                Toggle::make("day_{$weekday}_closed")
                    ->label(__('admin.weekly_schedule.closed'))
                    ->live()
                    ->default(false),
                TimePicker::make("day_{$weekday}_open")
                    ->label(__('admin.weekly_schedule.open_time'))
                    ->seconds(false)
                    ->native(false)
                    ->visible(fn (Get $get): bool => ! $get("day_{$weekday}_closed")),
                TimePicker::make("day_{$weekday}_close")
                    ->label(__('admin.weekly_schedule.close_time'))
                    ->seconds(false)
                    ->native(false)
                    ->visible(fn (Get $get): bool => ! $get("day_{$weekday}_closed")),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        // 1) Validar los 7 días ANTES de persistir ninguno (atómico: o todo o nada).
        $rows = [];
        foreach (range(0, 6) as $weekday) {
            $closed = (bool) ($data["day_{$weekday}_closed"] ?? false);
            $open = $closed ? null : self::time($data["day_{$weekday}_open"] ?? null);
            $close = $closed ? null : self::time($data["day_{$weekday}_close"] ?? null);

            if ($open !== null && $close !== null && Carbon::parse($close)->lessThanOrEqualTo(Carbon::parse($open))) {
                Notification::make()
                    ->title(__('admin.weekly_schedule.close_before_open', ['day' => __('admin.rate_types.weekdays.'.$weekday)]))
                    ->danger()
                    ->send();

                // En una Page custom, el método `save()` no pasa por el lifecycle de Filament que
                // captura `Halt`: se aborta el guardado COMPLETO con un `return` (nada se persiste).
                return;
            }

            $rows[$weekday] = ['open_time' => $open, 'close_time' => $close, 'is_closed' => $closed];
        }

        // 2) Persistir (upsert por día) y contar lo que realmente cambió para la auditoría.
        $changed = 0;
        foreach ($rows as $weekday => $new) {
            $existing = OpeningHour::where('weekday', $weekday)->first();
            // Comparar al mismo nivel: la BD guarda 'HH:MM:SS' y $new ya está en 'HH:MM:SS';
            // recortar ambos a 'HH:MM' evita contar un cambio falso en cada guardado idéntico.
            if ($existing === null
                || (bool) $existing->is_closed !== $new['is_closed']
                || substr((string) $existing->open_time, 0, 5) !== substr((string) $new['open_time'], 0, 5)
                || substr((string) $existing->close_time, 0, 5) !== substr((string) $new['close_time'], 0, 5)) {
                $changed++;
            }

            OpeningHour::updateOrCreate(['weekday' => $weekday], $new);
        }

        if ($changed > 0) {
            AuditLogger::log('slots.weekly_schedule_updated', null, ['days_changed' => $changed]);
        }

        Notification::make()
            ->title(__('admin.weekly_schedule.saved'))
            ->success()
            ->send();
    }

    /** Normaliza una hora del form ('HH:MM' o vacío) a 'HH:MM:00' o null. */
    private static function time(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : Carbon::parse($value)->format('H:i:s');
    }
}
