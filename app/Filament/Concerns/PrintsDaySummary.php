<?php

namespace App\Filament\Concerns;

use App\Domain\Booking\Services\DailyReservationsSummary;
use App\Domain\Platform\Services\DisplayTime;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;

/**
 * Acción de cabecera "Imprimir resumen del día" (decisión #184), compartida por
 * la página de Calendario y el Escritorio. Abre un modal (Fecha + Tipo) y, al
 * enviar, redirige a la ruta `admin.calendario.resumen-dia`, que genera el PDF
 * A4 inline listo para imprimir.
 *
 * El gate real (`calendar.view`) lo aplica TANTO `->visible()` (oculta el botón)
 * COMO el controlador de la ruta (defensa real, por si se invoca sin pasar por
 * la UI) — el handler de la action no toca datos, solo construye una URL.
 */
trait PrintsDaySummary
{
    public function printDaySummaryAction(): Action
    {
        return Action::make('printDaySummary')
            ->label(__('admin.calendar.day_summary.btn'))
            ->icon(Heroicon::OutlinedPrinter)
            ->color('gray')
            ->visible(fn (): bool => auth()->user()?->hasPermission('calendar.view') ?? false)
            ->modalHeading(__('admin.calendar.day_summary.modal_heading'))
            ->modalSubmitActionLabel(__('admin.calendar.day_summary.submit'))
            ->modalWidth('md')
            ->schema([
                DatePicker::make('date')
                    ->label(__('admin.calendar.day_summary.date_label'))
                    ->default(now(DisplayTime::timezone())->toDateString())
                    ->required()
                    ->native(false)
                    ->closeOnDateSelection(),
                Select::make('type')
                    ->label(__('admin.calendar.day_summary.type_label'))
                    ->options([
                        DailyReservationsSummary::TYPE_ALL => __('admin.calendar.day_summary.type_all'),
                        DailyReservationsSummary::TYPE_PACK => __('admin.calendar.day_summary.type_packs'),
                        DailyReservationsSummary::TYPE_ENTRY => __('admin.calendar.day_summary.type_entries'),
                    ])
                    ->default(DailyReservationsSummary::TYPE_ALL)
                    ->selectablePlaceholder(false)
                    ->required()
                    ->native(false),
            ])
            ->action(function (array $data) {
                // El DatePicker puede devolver 'Y-m-d' o un datetime; normalizar.
                $date = Carbon::parse((string) ($data['date'] ?? ''))->toDateString();
                $type = (string) ($data['type'] ?? DailyReservationsSummary::TYPE_ALL);

                // Pestaña NUEVA: la acción lleva formulario en modal y no puede ser
                // un <a target="_blank">. Emite el evento que el listener global del
                // panel (`open-url-listener`) abre en otra pestaña (con fallback a la
                // misma pestaña si el navegador bloquea el popup).
                $this->dispatch('open-url-new-tab', url: route('admin.calendario.resumen-dia', [
                    'date' => $date,
                    'type' => $type,
                ]));
            });
    }
}
