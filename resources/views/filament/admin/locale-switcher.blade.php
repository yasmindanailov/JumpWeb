@php
    use App\Http\Middleware\SetAdminLocale;

    // Nombres de los idiomas en su grafía nativa — convención i18n universal:
    // cualquier lector reconoce su lengua escrita en su propia escritura,
    // sin depender de tener strings traducidos antes de mostrar el selector.
    $labels = [
        'es' => 'Español',
        'zh_CN' => '中文',
    ];

    $current = SetAdminLocale::resolve(auth()->user());
@endphp

{{-- 100% componentes nativos de Filament 5: el trigger usa `<x-filament::icon-button>`
     (`fi-icon-btn fi-size-lg fi-color-gray` traen el styling del topbar), y cada item
     usa `<x-filament::dropdown.list.item tag="form">` que genera el `<form>` + `@csrf`
     automáticamente. `me-3` separa el selector del user-menu/avatar siguiente
     (verificado empíricamente: `me-1` = 4px era insuficiente y los iconos quedaban
     pegados; `me-3` = 12px coincide con el spacing interno del topbar Filament). --}}
<x-filament::dropdown
    placement="bottom-end"
    teleport
    class="fi-jj-locale-switcher me-3"
>
    <x-slot name="trigger">
        <x-filament::icon-button
            icon="heroicon-o-language"
            icon-size="lg"
            color="gray"
            :label="$labels[$current]"
            class="fi-jj-locale-switcher-btn"
        />
    </x-slot>

    <x-filament::dropdown.list>
        @foreach (SetAdminLocale::SUPPORTED as $locale)
            @php($isActive = $locale === $current)
            <x-filament::dropdown.list.item
                tag="form"
                method="POST"
                :action="route('admin.lang.switch', $locale)"
                :icon="$isActive ? 'heroicon-m-check' : 'heroicon-m-chevron-right'"
                :color="$isActive ? 'primary' : 'gray'"
            >
                {{ $labels[$locale] }}
            </x-filament::dropdown.list.item>
        @endforeach
    </x-filament::dropdown.list>
</x-filament::dropdown>
