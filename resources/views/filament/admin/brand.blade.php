{{-- Marca del panel (#215): el wordmark del negocio (business.name) con «Panel de Control» debajo. Se renderiza
     en la cabecera del sidebar vía `brandLogo`; Filament lo oculta solo cuando el sidebar está
     colapsado a iconos, así que no necesita CSS a medida. --}}
{{-- Lanzamiento 2026-09-01 (`#325`): con el LOGOTIPO de la instalación presente
     (`public/img/client-logo.svg`, el mismo hueco que la web) el panel lo enseña en vez del
     wordmark; el nombre sigue en el `alt`. Sin el fichero, el texto de siempre. --}}
@php($clientLogo = file_exists(public_path('img/client-logo.svg')))
<span class="flex h-full flex-col justify-center items-start text-left leading-tight">
    @if ($clientLogo)
    <img src="{{ asset('img/client-logo.svg') }}?v={{ @filemtime(public_path('img/client-logo.svg')) }}"
         alt="{{ \App\Domain\Platform\Models\Setting::value('business.name') ?: config('app.name') }}"
         class="h-9 w-auto max-w-[11rem]">
    @else
    <span class="text-lg font-bold tracking-tight text-gray-950 dark:text-white">
        {{ \App\Domain\Platform\Models\Setting::value('business.name') ?: config('app.name') }}<span class="text-primary-500">.</span>
    </span>
    @endif
    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">
        {{ __('admin.panel_subtitle') }}
    </span>
</span>
