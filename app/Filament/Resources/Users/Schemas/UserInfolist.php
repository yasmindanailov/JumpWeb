<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Domain\Identity\Models\User;
use App\Domain\Platform\Services\DisplayTime;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

/**
 * Fase 7.5 — Ficha de detalle del usuario (solo lectura, decisiones #180 + #181).
 *
 * Layout en 2 columnas (Flex, flexbox real como `OrderInfolist`) para que la ficha NO
 * sea una pila larga de cards: izquierda = datos de contacto; derecha = resumen de
 * pedidos + consentimientos. El **rol** y el **estado** (activa/anonimizada) viven en
 * el H1 (`ViewUser::getHeading`), no en una card. En móvil el Flex apila a 1 columna.
 */
class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Flex::make([
                // ── Columna izquierda: datos de contacto ──
                Group::make([
                    Section::make(__('admin.users.section_data'))
                        ->columns(2)
                        ->schema([
                            TextEntry::make('email')
                                ->label(__('admin.users.col_email'))
                                ->copyable()
                                ->placeholder('—'),

                            TextEntry::make('phone')
                                ->label(__('admin.users.col_phone'))
                                ->copyable()
                                ->placeholder('—'),

                            TextEntry::make('locale')
                                ->label(__('admin.users.col_locale'))
                                ->state(fn (User $record): string => $record->locale
                                    ? __('admin.users.locale_value.'.$record->locale)
                                    : '—'),

                            TextEntry::make('marketing_opt_in')
                                ->label(__('admin.users.col_marketing'))
                                ->state(fn (User $record): string => $record->marketing_opt_in
                                    ? __('admin.users.yes')
                                    : __('admin.users.no'))
                                ->badge()
                                ->color(fn (User $record): string => $record->marketing_opt_in ? 'success' : 'gray'),

                            TextEntry::make('email_verified_at')
                                ->label(__('admin.users.col_verified'))
                                ->state(fn (User $record): string => $record->email_verified_at
                                    ? DisplayTime::format($record->email_verified_at)
                                    : __('admin.users.unverified'))
                                ->badge()
                                ->color(fn (User $record): string => $record->email_verified_at ? 'success' : 'warning'),

                            TextEntry::make('last_login_at')
                                ->label(__('admin.users.col_last_login'))
                                ->state(fn (User $record): string => $record->last_login_at
                                    ? DisplayTime::format($record->last_login_at)
                                    : __('admin.users.never')),

                            TextEntry::make('created_at')
                                ->label(__('admin.users.col_created_at'))
                                ->state(fn (User $record): string => DisplayTime::format($record->created_at)),
                        ]),
                ]),

                // ── Columna derecha: consentimientos ──
                // (Los pedidos del cliente viven en el RelationManager de abajo —
                //  tabla con paginación/orden nativos, #182.)
                Group::make([
                    // Consentimientos: solo con `consents.view` (admin-only por matriz).
                    Section::make(__('admin.users.section_consents'))
                        ->visible(fn (): bool => auth()->user()?->hasPermission('consents.view') ?? false)
                        ->schema([
                            View::make('filament.users.partials.consents-list'),
                        ]),
                ]),
            ])
                ->from('lg')
                ->columnSpanFull(),
        ]);
    }
}
