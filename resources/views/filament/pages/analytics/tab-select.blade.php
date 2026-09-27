{{--
    Las pestañas en el móvil: un `<select>` NATIVO (T3a de `specs/analitica-para-decidir.md` §4.11, `#759`). Medido el 28-09:
    a 390 px la fila de pestañas enseñaba 3 de 6 y el resto quedaba escondido sin aviso. Cambia la propiedad `tab` de la
    página (la misma que las pestañas y la URL). Desde tableta no se ve: mandan las pestañas.

    @var array<string, string> $tabs
--}}
<div class="md:hidden" data-analytics-tab-select>
    <label for="analitica-pestana" class="sr-only">{{ __('admin.analytics.tab_select') }}</label>
    <x-filament::input.wrapper>
        <x-filament::input.select id="analitica-pestana" wire:model.live="tab">
            @foreach ($tabs as $key => $label)
                <option value="{{ $key }}">{{ $label }}</option>
            @endforeach
        </x-filament::input.select>
    </x-filament::input.wrapper>
</div>
