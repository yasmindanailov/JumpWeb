{{-- Marca del panel (#215): el wordmark del negocio (business.name) con «Panel de Control» debajo. Se renderiza
     en la cabecera del sidebar vía `brandLogo`; Filament lo oculta solo cuando el sidebar está
     colapsado a iconos, así que no necesita CSS a medida. --}}
<span class="flex h-full flex-col justify-center items-start text-left leading-tight">
    <span class="text-lg font-bold tracking-tight text-gray-950 dark:text-white">
        {{ \App\Domain\Platform\Models\Setting::value('business.name') ?: config('app.name') }}<span class="text-primary-500">.</span>
    </span>
    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">
        {{ __('admin.panel_subtitle') }}
    </span>
</span>
