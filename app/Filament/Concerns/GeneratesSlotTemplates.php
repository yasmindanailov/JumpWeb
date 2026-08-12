<?php

namespace App\Filament\Concerns;

use App\Models\SlotTemplate;
use App\Models\Zone;
use App\Support\AuditLogger;
use App\Support\PaymentSettings;
use App\Support\SlotGenerator;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Generador EN BLOQUE de plantillas de franja para una zona (P15). En vez de crear las plantillas
 * día a día / hora a hora, una sola acción toma zona + días + horario (inicio→cierre) + duración +
 * aforo y crea TODAS las plantillas de golpe — la misma forma en que se sembraron las de cumpleaños
 * (bucle weekday × hora), ahora configurable para CUALQUIER zona. Opcionalmente regenera las franjas
 * al terminar (mismo {@see SlotGenerator} que «Regenerar franjas»).
 *
 * Invariantes (espejo de InteractsWithSlotTemplateForm): online ≤ total; convenio de weekday de
 * Carbon (0=domingo..6=sábado, igual que el generador de franjas). NO duplica (una plantilla ya
 * existente zona+día+hora se omite, salvo que se marque «reemplazar»). Gate real `slots.manage`.
 */
trait GeneratesSlotTemplates
{
    public function generateSlotTemplatesAction(): Action
    {
        return Action::make('generateSlotTemplates')
            ->label(__('admin.slot_templates.generate.btn'))
            ->icon(Heroicon::OutlinedSquares2x2)
            ->color('gray')
            ->visible(fn (): bool => auth()->user()?->hasPermission('slots.manage') ?? false)
            ->modalHeading(__('admin.slot_templates.generate.modal_heading'))
            ->modalDescription(__('admin.slot_templates.generate.modal_description'))
            ->modalSubmitActionLabel(__('admin.slot_templates.generate.submit'))
            ->modalWidth('lg')
            ->schema([
                Select::make('zone_id')
                    ->label(__('admin.slot_templates.field_zone'))
                    ->options(fn (): array => Zone::orderBy('position')->get()
                        ->mapWithKeys(fn (Zone $zone): array => [$zone->id => (string) $zone->tr('name')])
                        ->all())
                    ->required()
                    ->native(false),
                CheckboxList::make('weekdays')
                    ->label(__('admin.slot_templates.generate.weekdays'))
                    // Convenio Carbon (0=domingo..6=sábado), mostrado L→D.
                    ->options([
                        1 => __('admin.rate_types.weekdays.1'),
                        2 => __('admin.rate_types.weekdays.2'),
                        3 => __('admin.rate_types.weekdays.3'),
                        4 => __('admin.rate_types.weekdays.4'),
                        5 => __('admin.rate_types.weekdays.5'),
                        6 => __('admin.rate_types.weekdays.6'),
                        0 => __('admin.rate_types.weekdays.0'),
                    ])
                    ->columns(4)
                    ->required(),
                TimePicker::make('start_time')
                    ->label(__('admin.slot_templates.generate.start_time'))
                    ->seconds(false)
                    ->native(false)
                    ->default('11:00')
                    ->required(),
                TimePicker::make('end_time')
                    ->label(__('admin.slot_templates.generate.end_time'))
                    ->helperText(__('admin.slot_templates.generate.end_time_hint'))
                    ->seconds(false)
                    ->native(false)
                    ->default('21:00')
                    ->required(),
                TextInput::make('duration_min')
                    ->label(__('admin.slot_templates.field_duration'))
                    ->helperText(__('admin.slot_templates.field_duration_hint'))
                    ->numeric()
                    ->minValue(1)
                    ->default(60)
                    ->required(),
                TextInput::make('interval_min')
                    ->label(__('admin.slot_templates.generate.interval'))
                    ->helperText(__('admin.slot_templates.generate.interval_hint'))
                    ->numeric()
                    ->minValue(1), // opcional → si se deja vacío, = duración (franjas seguidas)
                TextInput::make('capacity')
                    ->label(__('admin.slot_templates.field_capacity'))
                    ->helperText(__('admin.slot_templates.field_capacity_hint'))
                    ->numeric()
                    ->minValue(0)
                    ->required(),
                TextInput::make('online_capacity')
                    ->label(__('admin.slot_templates.field_online_capacity'))
                    ->helperText(__('admin.slot_templates.field_online_capacity_hint'))
                    ->numeric()
                    ->minValue(0)
                    ->required(),
                Toggle::make('replace_existing')
                    ->label(__('admin.slot_templates.generate.replace'))
                    ->helperText(__('admin.slot_templates.generate.replace_hint'))
                    ->default(false),
                Toggle::make('regenerate_after')
                    ->label(__('admin.slot_templates.generate.regenerate_after'))
                    ->helperText(__('admin.slot_templates.generate.regenerate_after_hint'))
                    ->default(true),
            ])
            ->action(fn (array $data) => $this->runSlotTemplateGeneration($data));
    }

    /**
     * @param  array<string,mixed>  $data
     */
    private function runSlotTemplateGeneration(array $data): void
    {
        $zoneId = (int) ($data['zone_id'] ?? 0);
        $weekdays = array_values(array_unique(array_map('intval', (array) ($data['weekdays'] ?? []))));
        $duration = max(1, (int) ($data['duration_min'] ?? 0));
        $interval = (int) ($data['interval_min'] ?? 0);
        $interval = $interval > 0 ? $interval : $duration;
        $capacity = max(0, (int) ($data['capacity'] ?? 0));
        $online = max(0, (int) ($data['online_capacity'] ?? 0));
        $replace = (bool) ($data['replace_existing'] ?? false);

        if ($online > $capacity) {
            Notification::make()->title(__('admin.slot_templates.errors.online_above_total'))->danger()->send();

            return;
        }
        if ($weekdays === []) {
            Notification::make()->title(__('admin.slot_templates.generate.no_weekdays'))->danger()->send();

            return;
        }

        // Inicios de franja: cada `interval` desde la hora de inicio, mientras el FIN (inicio+duración)
        // no pase del cierre. Así ninguna franja termina después de la hora de cierre. El `$guard`
        // acota el bucle (defensa: nunca más de 200 franjas/día por mucho que se configure).
        $start = Carbon::parse((string) ($data['start_time'] ?? ''));
        $end = Carbon::parse((string) ($data['end_time'] ?? ''));
        $startTimes = [];
        $cursor = $start->copy();
        $guard = 0;
        while ($cursor->copy()->addMinutes($duration)->lte($end) && $guard++ < 200) {
            $startTimes[] = $cursor->format('H:i:s');
            $cursor->addMinutes($interval);
        }

        if ($startTimes === []) {
            Notification::make()->title(__('admin.slot_templates.generate.no_slots'))->danger()->send();

            return;
        }

        $created = 0;
        $skipped = 0;
        foreach ($weekdays as $weekday) {
            if ($replace) {
                SlotTemplate::where('zone_id', $zoneId)->where('weekday', $weekday)->delete();
            }
            foreach ($startTimes as $time) {
                $exists = SlotTemplate::where('zone_id', $zoneId)
                    ->where('weekday', $weekday)
                    ->where('start_time', $time)
                    ->exists();
                if ($exists) {
                    $skipped++;

                    continue;
                }
                SlotTemplate::create([
                    'zone_id' => $zoneId,
                    'weekday' => $weekday,
                    'start_time' => $time,
                    'duration_min' => $duration,
                    'capacity' => $capacity,
                    'online_capacity' => $online,
                    'is_active' => true,
                ]);
                $created++;
            }
        }

        // Opcional: materializa ya las franjas (de hoy al horizonte de venta) con el MISMO servicio
        // y poda segura que «Regenerar franjas». Evita tener que pulsarlo aparte.
        $slotsGenerated = null;
        if ((bool) ($data['regenerate_after'] ?? true)) {
            $from = now()->startOfDay();
            $to = now()->addMonths(PaymentSettings::purchaseHorizonMonths())->startOfDay();
            $slotsGenerated = app(SlotGenerator::class)->generate($from, $to, prune: true)['generated'];
        }

        AuditLogger::log('slot_templates.generated', null, [
            'zone_id' => $zoneId,
            'weekdays' => $weekdays,
            'created' => $created,
            'skipped' => $skipped,
            'replaced' => $replace,
            'slots_generated' => $slotsGenerated,
        ]);

        Notification::make()
            ->title(__('admin.slot_templates.generate.done', ['created' => $created, 'skipped' => $skipped]))
            ->success()
            ->send();
    }
}
