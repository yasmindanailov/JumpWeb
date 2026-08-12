<?php

namespace App\Filament\Concerns;

use App\Support\AuditLogger;
use App\Support\PaymentSettings;
use App\Support\SlotGenerator;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Fase 7.7 iter.3 — Acción de cabecera «Regenerar franjas», compartida por el listado de
 * franjas y el Horario semanal (es la consecuencia natural de cambiar el horario). Abre un
 * modal con el rango (desde/hasta, por defecto hoy → horizonte de venta) y, al enviar, llama
 * al servicio único {@see SlotGenerator} con poda segura: crea/actualiza el horario vigente,
 * borra las franjas obsoletas sin reservas y cierra (no borra) las que tengan reservas. El
 * aforo ajustado a mano se preserva. Registra un audit `slots.regenerated` con los conteos.
 *
 * Gate real `slots.manage` en `->visible()`; el servicio no expone ruta propia (no hace falta
 * doble defensa: solo se invoca desde estas páginas, ya gateadas por el Resource/Page).
 */
trait RegeneratesSlots
{
    public function regenerateSlotsAction(): Action
    {
        return Action::make('regenerateSlots')
            ->label(__('admin.slots.regenerate.btn'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->visible(fn (): bool => auth()->user()?->hasPermission('slots.manage') ?? false)
            ->modalHeading(__('admin.slots.regenerate.modal_heading'))
            ->modalDescription(__('admin.slots.regenerate.modal_description'))
            ->modalSubmitActionLabel(__('admin.slots.regenerate.submit'))
            ->modalWidth('md')
            ->schema([
                DatePicker::make('from')
                    ->label(__('admin.slots.regenerate.from_label'))
                    ->default(now()->toDateString())
                    ->required()
                    ->native(false)
                    ->closeOnDateSelection(),
                DatePicker::make('to')
                    ->label(__('admin.slots.regenerate.to_label'))
                    ->default(now()->addMonths(PaymentSettings::purchaseHorizonMonths())->toDateString())
                    ->required()
                    ->native(false)
                    ->closeOnDateSelection()
                    ->afterOrEqual('from'), // semántica inclusiva (un solo día permitido), igual que el servicio/CLI
            ])
            ->action(function (array $data): void {
                $from = Carbon::parse((string) ($data['from'] ?? ''))->startOfDay();
                $to = Carbon::parse((string) ($data['to'] ?? ''))->startOfDay();

                // Defensa: el ->after('from') no siempre resuelve bajo el statePath del modal.
                if ($to->lt($from)) {
                    Notification::make()->title(__('admin.slots.regenerate.invalid_range'))->danger()->send();

                    return;
                }

                $result = app(SlotGenerator::class)->generate($from, $to, prune: true);

                AuditLogger::log('slots.regenerated', null, [
                    'from' => $from->toDateString(),
                    'to' => $to->toDateString(),
                    'generated' => $result['generated'],
                    'deleted' => $result['deleted'],
                    'closed' => $result['closed'],
                ]);

                Notification::make()
                    ->title(__('admin.slots.regenerate.done', [
                        'generated' => $result['generated'],
                        'deleted' => $result['deleted'],
                        'closed' => $result['closed'],
                    ]))
                    ->success()
                    ->send();
            });
    }
}
