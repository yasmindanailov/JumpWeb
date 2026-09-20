{{--
    Ficha de Google — el estado de la conexión (`specs/google-business-profile.md` §4.2·1).
    ⚠️ El resultado del viaje a Google llega por `session('status')`, que es lo que deja el
    controlador de §4.2·2 al volver. Filament no lo pinta solo: esta vista es quien lo enseña.
--}}
<x-filament-panels::page>
    @php($estado = $this->estado())
    @php($conexion = $this->conexion())
    @php($aviso = session('status'))

    @if (is_string($aviso) && str_starts_with($aviso, 'google-business-'))
        <div @class([
            'rounded-lg p-4 text-sm',
            'bg-success-50 text-success-700 dark:bg-success-400/10 dark:text-success-400' => $aviso === 'google-business-connected',
            'bg-warning-50 text-warning-700 dark:bg-warning-400/10 dark:text-warning-400' => $aviso !== 'google-business-connected',
        ])>
            {{ __('admin.google_business.results.'.str_replace('google-business-', '', $aviso)) }}
        </div>
    @endif

    <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ __('admin.google_business.state_label') }}
        </p>
        <p class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">
            {{ __('admin.google_business.states.'.$estado->value.'.label') }}
        </p>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
            {{ __('admin.google_business.states.'.$estado->value.'.what_to_do') }}
        </p>

        @if ($conexion?->location_title)
            <p class="mt-4 text-sm text-gray-600 dark:text-gray-300">
                {{ __('admin.google_business.linked_location', ['name' => $conexion->location_title]) }}
            </p>
        @endif
    </div>

    @if ($this->puedeConectar())
        {{--
            POST y no un enlace: abrir el reto escribe en la sesión del admin, y un GET lo dejaría al
            alcance de cualquier página que le cargue una imagen. El token CSRF hace que el viaje a
            Google empiece SIEMPRE por un gesto suyo.
        --}}
        <form method="POST" action="{{ route('admin.google_business.connect') }}">
            @csrf
            <x-filament::button type="submit" icon="heroicon-o-link">
                {{ __('admin.google_business.connect') }}
            </x-filament::button>
        </form>
    @endif
</x-filament-panels::page>
