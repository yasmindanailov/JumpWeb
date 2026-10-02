{{--
    «Textos de los correos» (R1·T de `specs/correos-rediseno.md` §4.2.1, `#802`). Sin `?correo=`, la LISTA por tipo con las
    tarjetas del hub (`jj-hub__*`, que leen los tokens del panel: sin utilidades de color de Tailwind, `panel-navegacion.md`
    §4.5); con él, el CORREO: sus bloques en pestañas es/en/fr, «Guardar» y la vista previa.

    ⚠️ La vista previa es el correo DE VERDAD con lo que hay en la pantalla, INERTE: `iframe` con `sandbox` vacío y el HTML
    desactivado (`EmailSendTable::inert`), como la de «Correos enviados» (`#794`, `#796`). Encima, lo que se lee en la
    bandeja (el asunto y el adelanto): son bloques que se editan y el cuerpo no los enseña.
--}}
<x-filament-panels::page>
    @if ($this->correo === null)
        <p class="jj-hub__desc">{{ __('admin.mail_texts.intro') }}</p>
        <div class="jj-hub" data-email-texts-list>
            @foreach ($this->tipos() as $tipo)
                <x-filament::section :heading="$tipo['label']">
                    <div class="jj-hub__grid">
                        @foreach ($tipo['items'] as $item)
                            <a href="{{ $item['url'] }}" class="jj-hub__card" data-correo="{{ $item['correo'] }}">
                                <x-filament::icon icon="heroicon-o-envelope" class="jj-hub__ico" />
                                <span class="jj-hub__text">
                                    <span class="jj-hub__label">{{ $item['label'] }}</span>
                                    <span class="jj-hub__desc">{{ $item['description'] }}</span>
                                    <span class="flex flex-wrap gap-1 pt-1">
                                        @if ($item['propios'] === 0)
                                            <x-filament::badge color="gray" size="sm">{{ __('admin.mail_texts.estado.fabrica') }}</x-filament::badge>
                                        @else
                                            <x-filament::badge color="primary" size="sm">{{ trans_choice('admin.mail_texts.estado.propios', $item['propios'], ['count' => $item['propios']]) }}</x-filament::badge>
                                        @endif
                                        @if ($item['sinTraducir'] !== [])
                                            <x-filament::badge color="warning" size="sm">{{ __('admin.mail_texts.estado.sin_traducir', ['idiomas' => implode(', ', array_map(static fn (string $l): string => __('admin.settings.lang_'.$l), $item['sinTraducir']))]) }}</x-filament::badge>
                                        @endif
                                        @if ($item['desfasados'] > 0)
                                            <x-filament::badge color="danger" size="sm">{{ trans_choice('admin.mail_texts.estado.desfasados', $item['desfasados'], ['count' => $item['desfasados']]) }}</x-filament::badge>
                                        @endif
                                    </span>
                                </span>
                            </a>
                        @endforeach
                    </div>
                </x-filament::section>
            @endforeach
        </div>
    @else
        <div class="space-y-6" data-email-texts-editor>
            <x-filament::link :href="static::getUrl()" icon="heroicon-o-arrow-left" size="sm">{{ __('admin.mail_texts.volver_lista') }}</x-filament::link>
            <p class="jj-hub__desc">{{ __('admin.mail_texts.intro_correo') }}</p>

            <form wire:submit="guardar" class="space-y-6">
                {{ $this->form }}

                <div class="flex flex-wrap justify-end gap-3">
                    <x-filament::button type="button" color="gray" icon="heroicon-o-eye" wire:click="verVista" wire:loading.attr="disabled">
                        {{ __('admin.mail_texts.ver') }}
                    </x-filament::button>
                    <x-filament::button type="submit" wire:loading.attr="disabled">
                        {{ __('admin.mail_texts.guardar') }}
                    </x-filament::button>
                </div>
            </form>

            @if ($this->vistaHtml !== null || $this->vistaMotivo !== null)
                <x-filament::section :heading="__('admin.mail_texts.vista')" data-email-texts-preview>
                    <div class="flex flex-wrap items-center gap-2 pb-3">
                        @foreach (\App\Domain\Platform\Services\SiteLocales::SUPPORTED as $locale)
                            <x-filament::button size="sm" :color="$this->vistaIdioma === $locale ? 'primary' : 'gray'" wire:click="verVista('{{ $locale }}')">
                                {{ __('admin.settings.lang_'.$locale) }}
                            </x-filament::button>
                        @endforeach
                        <x-filament::button size="sm" :color="$this->vistaOscuro ? 'gray' : 'primary'" wire:click="verVista(null, false)">{{ __('admin.mail_texts.claro') }}</x-filament::button>
                        <x-filament::button size="sm" :color="$this->vistaOscuro ? 'primary' : 'gray'" wire:click="verVista(null, true)">{{ __('admin.mail_texts.oscuro') }}</x-filament::button>
                        {{-- La SITUACIÓN (R1·T2, `#809`): solo en los correos con textos que salen a veces. El valor lo valida la
                             página contra las del correo (`MailSituations::elegida`). --}}
                        @if ($this->situaciones() !== [])
                            <label class="ms-auto flex items-center gap-2 text-sm" data-email-texts-situation>
                                <span class="text-gray-600 dark:text-gray-300">{{ __('admin.mail_texts.situacion') }}</span>
                                <x-filament::input.wrapper>
                                    <x-filament::input.select wire:change="verVista(null, null, $event.target.value)">
                                        @foreach ($this->situaciones() as $situacion => $nombre)
                                            <option value="{{ $situacion }}" @selected($this->vistaSituacion === $situacion)>{{ $nombre }}</option>
                                        @endforeach
                                    </x-filament::input.select>
                                </x-filament::input.wrapper>
                            </label>
                        @endif
                    </div>
                    @if ($this->vistaHtml !== null)
                        {{-- Lo que se lee en la BANDEJA antes de abrirlo: el asunto y el adelanto, que el cuerpo no enseña. --}}
                        <div class="mb-3 rounded-lg border border-gray-200 px-4 py-3 dark:border-white/10" data-email-texts-inbox>
                            <p class="jj-hub__desc">{{ __('admin.mail_texts.bandeja') }}</p>
                            <p class="font-semibold" data-email-texts-subject>{{ $this->vistaAsunto }}</p>
                            @if (filled($this->vistaAdelanto))
                                <p class="jj-hub__desc" data-email-texts-preheader>{{ $this->vistaAdelanto }}</p>
                            @endif
                        </div>
                        <iframe
                            title="{{ __('admin.mail_texts.vista') }}"
                            sandbox=""
                            referrerpolicy="no-referrer"
                            srcdoc="{{ $this->vistaHtml }}"
                            class="w-full rounded-lg border border-gray-200 bg-white dark:border-white/10"
                            style="height: 70vh;"
                        ></iframe>
                        <p class="jj-hub__desc pt-2">{{ __('admin.mail_texts.vista_nota') }}</p>
                        @if ($this->situaciones() !== [])
                            <p class="jj-hub__desc">{{ __('admin.mail_texts.situacion_nota') }}</p>
                        @endif
                    @else
                        <p class="jj-hub__desc">{{ __('admin.mail_texts.sin_caso.'.$this->vistaMotivo) }}</p>
                    @endif
                </x-filament::section>
            @endif
        </div>
    @endif
</x-filament-panels::page>
