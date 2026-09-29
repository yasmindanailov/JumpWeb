<?php

namespace App\Filament\Resources\EmailSends\Tables;

use App\Domain\Platform\Models\EmailSend;
use App\Domain\Platform\Services\Analytics\EmailUtm;
use App\Domain\Platform\Services\AuditLogger;
use App\Domain\Platform\Services\DisplayTime;
use App\Filament\Resources\EmailSends\EmailSendResource;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;

/**
 * **La lista de los correos enviados** (`specs/correos-salientes.md` §4.2, `#794`): cuándo, a quién, cuál y si salió; y la
 * vista previa, que deja rastro al abrirse (`emails.previewed`, sin el contenido: `RGPD-02`).
 */
class EmailSendTable
{
    public const SENT = 'sent';

    public const SENT_AFTER_FAILURES = 'sent_after_failures';

    public const FAILED = 'failed';

    public const CLICKED = 'clicked';

    public const NOT_CLICKED = 'not_clicked';

    public const NOT_MEASURED = 'not_measured';

    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(static fn (Builder $query): Builder => self::withCounts($query))
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('admin.email_sends.col.when'))
                    ->getStateUsing(static fn (EmailSend $r): string => DisplayTime::format($r->sent_at ?? $r->failed_at ?? $r->created_at, 'd/m/Y H:i'))
                    ->sortable(),
                TextColumn::make('recipient')
                    ->label(__('admin.email_sends.col.to'))
                    ->getStateUsing(static fn (EmailSend $r): string => self::who($r))
                    ->description(static fn (EmailSend $r): string => $r->user !== null ? (string) ($r->recipient ?? '') : '')
                    ->wrap(),
                TextColumn::make('mail_key')
                    ->label(__('admin.email_sends.col.mail'))
                    ->formatStateUsing(static fn (string $state): string => self::label($state))
                    ->description(static fn (EmailSend $r): string => (string) ($r->subject ?? ''))
                    ->wrap(),
                TextColumn::make('status')
                    ->label(__('admin.email_sends.col.status'))
                    ->badge()
                    ->getStateUsing(static fn (EmailSend $r): string => self::state($r))
                    ->formatStateUsing(static fn (string $state, EmailSend $r): string => trans_choice('admin.email_sends.state.'.$state, $r->failures, ['count' => $r->failures]))
                    ->color(static fn (string $state): string => match ($state) {
                        self::SENT => 'success',
                        self::SENT_AFTER_FAILURES => 'warning',
                        default => 'danger',
                    }),
                // Los clics (la C2, §4.8): los de una persona; los de un escáner, aparte y sin contar. Sin marca, «no se mide».
                TextColumn::make('clicks_counted')
                    ->label(__('admin.email_sends.col.clicks'))
                    ->getStateUsing(static fn (EmailSend $r): string => self::clicks($r))
                    ->description(static fn (EmailSend $r): string => self::scannerClicks($r)),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('user_id')
                    ->label(__('admin.email_sends.filter.customer'))
                    ->relationship('user', 'name')
                    ->searchable(),
                SelectFilter::make('mail_key')
                    ->label(__('admin.email_sends.col.mail'))
                    ->options(self::mailOptions()),
                SelectFilter::make('status')
                    ->label(__('admin.email_sends.col.status'))
                    ->options([
                        self::SENT => __('admin.email_sends.filter.sent'),
                        self::FAILED => __('admin.email_sends.filter.failed'),
                    ])
                    ->query(static fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        self::SENT => $query->whereNotNull('sent_at'),
                        self::FAILED => $query->whereNull('sent_at')->whereNotNull('failed_at'),
                        default => $query,
                    }),
                SelectFilter::make('clicks')
                    ->label(__('admin.email_sends.col.clicks'))
                    ->options([
                        self::CLICKED => __('admin.email_sends.filter.clicked'),
                        self::NOT_CLICKED => __('admin.email_sends.filter.not_clicked'),
                        self::NOT_MEASURED => __('admin.email_sends.filter.not_measured'),
                    ])
                    ->query(static fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        self::CLICKED => $query->whereHas('clicks', static fn (Builder $clicks) => $clicks->whereNull('verdict')),
                        self::NOT_CLICKED => $query->where('tracks_clicks', true)->whereDoesntHave('clicks', static fn (Builder $clicks) => $clicks->whereNull('verdict')),
                        self::NOT_MEASURED => $query->where('tracks_clicks', false),
                        default => $query,
                    }),
            ])
            ->recordActions([self::previewAction(), self::activityAction()])
            ->toolbarActions([]);
    }

    /**
     * «Ver»: el correo TAL CUAL salió, en un `iframe` aislado. Solo si aún hay copia (6 meses, `#794`). Deja rastro al
     * abrirse, con el envío y la clave, nunca el contenido ni la dirección.
     */
    public static function previewAction(): Action
    {
        return Action::make('preview')
            ->label(__('admin.email_sends.preview'))
            ->icon(Heroicon::OutlinedEye)
            ->visible(static fn (EmailSend $record): bool => EmailSendResource::canViewAny() && $record->hasCopy())
            ->modalHeading(static fn (EmailSend $record): string => (string) ($record->subject ?? self::label($record->mail_key)))
            ->modalWidth('3xl')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('admin.email_sends.close'))
            ->mountUsing(static function (EmailSend $record): void {
                AuditLogger::log('emails.previewed', $record, ['mail_key' => $record->mail_key]);
            })
            ->modalContent(static fn (EmailSend $record): View => view('filament.email-sends.preview', ['html' => self::inert((string) $record->html, (string) $record->send_key)]));
    }

    /**
     * ⚠️⚠️ **La copia, con los enlaces DESACTIVADOS para pintarla aquí** (`#796`): un `iframe` con `sandbox` vacío no abre
     * ventanas, pero SÍ navega dentro de sí mismo. Un enlace pulsado en la vista previa cargaría la web con la marca del envío
     * y contaría como un clic del CLIENTE. Dos capas: `<base target="_blank">` (sin `allow-popups`, el navegador lo bloquea) y
     * la marca fuera de sus enlaces. La copia guardada no se toca; lo que se ve es idéntico.
     */
    public static function inert(string $html, string $sendKey): string
    {
        if ($sendKey !== '') {
            $html = str_replace(['&amp;'.EmailUtm::MARK.'='.$sendKey, '&'.EmailUtm::MARK.'='.$sendKey], '', $html);
        }

        $base = '<base target="_blank">';
        $withBase = preg_replace('/<head(\s[^>]*)?>/i', '$0'.$base, $html, 1, $count);

        return $count === 1 && is_string($withBase) ? $withBase : $base.$html;
    }

    /**
     * «Actividad»: CUÁNDO salió, se abrió y se pulsó (`#796`, §4.10). Deja rastro al abrirse, con el envío y sin las horas.
     * Solo si sus clics se miden (la marca, la C2).
     */
    public static function activityAction(): Action
    {
        return Action::make('activity')
            ->label(__('admin.email_sends.activity.action'))
            ->icon(Heroicon::OutlinedClock)
            ->visible(static fn (EmailSend $record): bool => EmailSendResource::canViewAny() && $record->tracks_clicks)
            ->modalHeading(static fn (EmailSend $record): string => __('admin.email_sends.activity.heading', ['mail' => self::label($record->mail_key)]))
            ->modalWidth('2xl')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('admin.email_sends.close'))
            ->mountUsing(static function (EmailSend $record): void {
                AuditLogger::log('emails.activity_viewed', $record, ['mail_key' => $record->mail_key]);
            })
            ->modalContent(static fn (EmailSend $record): View => view('filament.email-sends.activity', ['events' => self::activity($record)]));
    }

    /**
     * La línea de tiempo de un envío, en la hora del PARQUE: su salida y cada visita con su marca —la hora, cuánto después del
     * envío, qué enlace y desde qué—. Las de una máquina van también, con su porqué y sin contar.
     *
     * @return list<array{kind: string, at: string, after: string|null, route: string|null, device: string|null, counts: bool, why: string|null}>
     */
    public static function activity(EmailSend $record): array
    {
        $sent = $record->sent_at;
        $events = [];

        if ($sent !== null) {
            $events[] = ['kind' => 'sent', 'at' => DisplayTime::format($sent, 'd/m/Y H:i'), 'after' => null, 'route' => null, 'device' => null, 'counts' => true, 'why' => null];
        }

        foreach ($record->clicks()->orderBy('clicked_at')->orderBy('id')->get() as $click) {
            $events[] = [
                'kind' => 'click',
                'at' => DisplayTime::format($click->clicked_at, 'd/m/Y H:i:s'),
                'after' => $sent !== null ? self::after($sent, $click->clicked_at) : null,
                'route' => $click->route,
                'device' => $click->device !== null ? (string) __('admin.email_sends.device.'.$click->device) : null,
                'counts' => $click->verdict === null,
                'why' => $click->verdict !== null ? (string) __('admin.email_sends.verdict.'.$click->verdict) : null,
            ];
        }

        return $events;
    }

    /** «13 minutos después», «1 hora 5 minutos después»: cuánto después del envío, en el idioma del panel. */
    private static function after(CarbonInterface $sent, CarbonInterface $at): string
    {
        return CarbonImmutable::instance($at)->locale(app()->getLocale())
            ->diffForHumans($sent, ['syntax' => CarbonInterface::DIFF_RELATIVE_TO_OTHER, 'parts' => 2]);
    }

    /** A quién: el nombre de la cuenta o, sin cuenta, la dirección. */
    public static function who(EmailSend $record): string
    {
        return $record->user !== null ? (string) $record->user->name : (string) ($record->recipient ?? __('admin.email_sends.forgotten'));
    }

    public static function state(EmailSend $record): string
    {
        return match (true) {
            $record->wasSent() && $record->failures > 0 => self::SENT_AFTER_FAILURES,
            $record->wasSent() => self::SENT,
            default => self::FAILED,
        };
    }

    /**
     * Sus clics de una persona («3 clics», «Sin clics»), o «No se mide» si salió sin la marca del envío (a quien se opuso,
     * sin cuenta, la encuesta, o con el interruptor apagado): un cero ahí mentiría. Lee `withClickCounts()` si se cargó.
     */
    public static function clicks(EmailSend $record): string
    {
        if (! $record->tracks_clicks) {
            return (string) __('admin.email_sends.clicks_not_measured');
        }

        $count = (int) ($record->clicks_counted ?? $record->clicks()->whereNull('verdict')->count());

        return trans_choice('admin.email_sends.clicks', $count, ['count' => $count]);
    }

    /** «+N de escáner» si los hubo (no cuentan: `EmailClick::SCANNER_VERDICTS`); vacío si no. */
    public static function scannerClicks(EmailSend $record): string
    {
        $count = (int) ($record->clicks_scanner ?? 0);

        return $count > 0 ? trans_choice('admin.email_sends.clicks_scanner', $count, ['count' => $count]) : '';
    }

    /**
     * La cuenta de cada fila y sus dos recuentos de clics (`EmailSend::scopeWithClickCounts()`).
     *
     * @param  Builder<EmailSend>  $query
     * @return Builder<EmailSend>
     */
    private static function withCounts(Builder $query): Builder
    {
        return $query->with('user')->withClickCounts();
    }

    /** El nombre del correo por su clave (`EmailUtm::keyOf()`); una clave sin rótulo se enseña tal cual. */
    public static function label(string $key): string
    {
        $line = 'admin.email_sends.mails.'.$key;

        return __($line) === $line ? $key : __($line);
    }

    /** @return array<string, string> */
    private static function mailOptions(): array
    {
        $keys = array_values(array_filter(EmailUtm::keys(), static fn (string $k): bool => EmailUtm::isCustomerKey($k)));

        return array_combine($keys, array_map(static fn (string $k): string => self::label($k), $keys));
    }
}
