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

        {{-- Quién conectó y cuándo (§4.2·1): la pregunta que se hace quien llega y no estaba. --}}
        @if ($this->conectadaPor())
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ __('admin.google_business.connected_by', [
                    'name' => $this->conectadaPor(),
                    'date' => $conexion?->connected_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '—',
                ]) }}
            </p>
        @endif

        @if ($this->puedeDesconectar())
            {{--
                POST y con CSRF: retirar el permiso sobre la ficha del parque no puede depender de
                que alguien abra un enlace (§4.2·8).
            --}}
            <form method="POST" action="{{ route('admin.google_business.disconnect') }}" class="mt-5">
                @csrf
                <x-filament::button type="submit" size="sm" color="danger" outlined>
                    {{ __('admin.google_business.disconnect') }}
                </x-filament::button>
                <span class="ml-2 text-xs text-gray-500 dark:text-gray-400">
                    {{ __('admin.google_business.disconnect_hint') }}
                </span>
            </form>
        @endif
    </div>

    {{-- Elegir la ficha (§4.2·4). Solo cuando hay permiso: sin él no hay nada que listar. --}}
    @php($fichas = $this->fichas())
    @php($elegida = $this->fichaElegida())

    @if ($this->estado() === \App\Domain\Platform\Enums\GoogleBusinessStatus::Connected)
        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <p class="text-sm font-semibold text-gray-950 dark:text-white">
                {{ __('admin.google_business.choose_title') }}
            </p>

            @if ($this->fichasError())
                <p class="mt-2 text-sm text-warning-700 dark:text-warning-400">
                    {{ __('admin.google_business.choose_failed') }}
                </p>
            @elseif ($fichas === [])
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                    {{ __('admin.google_business.no_locations') }}
                </p>
            @else
                <ul class="mt-4 space-y-3">
                    @foreach ($fichas as $ficha)
                        <li class="flex flex-wrap items-center justify-between gap-3 rounded-lg bg-gray-50 p-3 dark:bg-white/5">
                            <span class="text-sm text-gray-950 dark:text-white">
                                <strong>{{ $ficha->title }}</strong>
                                @if ($ficha->address)
                                    <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $ficha->address }}</span>
                                @endif
                            </span>

                            @if ($elegida === $ficha->name)
                                <span class="text-xs font-semibold text-success-700 dark:text-success-400">
                                    {{ __('admin.google_business.current') }}
                                </span>
                            @else
                                <form method="POST" action="{{ route('admin.google_business.choose') }}">
                                    @csrf
                                    <input type="hidden" name="location" value="{{ $ficha->name }}">
                                    {{--
                                        Cambiar de ficha cambia de qué negocio son las reseñas de la
                                        portada, así que la primera vez se rechaza y se vuelve con el
                                        aviso; este botón es el «sí» explícito del §4.2·4.
                                    --}}
                                    @if ($elegida !== null)
                                        <input type="hidden" name="confirmed" value="{{ session('status') === 'google-business-location-changed' ? '1' : '0' }}">
                                    @endif
                                    <x-filament::button type="submit" size="sm" color="gray">
                                        {{ session('status') === 'google-business-location-changed' && $elegida !== null
                                            ? __('admin.google_business.confirm_change')
                                            : __('admin.google_business.choose') }}
                                    </x-filament::button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif

    {{--
        T2·5 · «Ocultar» (§4.3·7, `DECISIONES #731`). Lo exigió la revisión de privacidad: aquí se
        publican reseñas de terceros SIN pedirles permiso —riesgo aceptado por el owner (§8·R1)— y
        esto es la mitigación que lo hace defendible.
        ⚠️ Solo se pinta con la conexión en pie: sin ficha no hay reseñas que ocultar, y la lista de
        ocultas de abajo se sigue enseñando siempre, porque sobrevive a todo.
    --}}
    @if ($this->estado() === \App\Domain\Platform\Enums\GoogleBusinessStatus::Connected)
        <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                {{ __('admin.google_business.reviews_title') }}
            </h3>

            @php($resumen = $this->resumen())
            @if ($resumen?->publishable())
                {{-- ⚠️ La media y el total NO los toca «Ocultar» (§4.3·7): son de Google. --}}
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ __('admin.google_business.reviews_count', [
                        'count' => $resumen->total_review_count,
                        'rating' => number_format($resumen->average_rating, 1, ',', '.'),
                        'date' => $resumen->fetched_at->timezone(config('app.timezone'))->format('d/m/Y'),
                    ]) }}
                </p>
            @endif

            @php($resenas = $this->resenas())
            @if ($resenas->isEmpty())
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">
                    {{ __('admin.google_business.reviews_empty') }}
                </p>
            @else
                <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                    {{ __('admin.google_business.hide_hint') }}
                </p>

                <ul class="mt-4 space-y-3">
                    @foreach ($resenas as $resena)
                        <li class="rounded-lg bg-gray-50 p-3 dark:bg-white/5">
                            <div class="flex flex-wrap items-baseline gap-2">
                                <strong class="text-sm text-gray-950 dark:text-white">
                                    {{ $resena->publishableAuthor() ?? __('admin.google_business.anonymous_author') }}
                                </strong>
                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ str_repeat('★', $resena->star_rating) }}
                                    · {{ $resena->review_created_at->timezone(config('app.timezone'))->format('d/m/Y') }}
                                </span>
                            </div>

                            {{--
                                ⚠️ `{{ }}` y `pre-line`, nunca `nl2br` sin escapar (§4.3·8): es texto
                                que escribió un desconocido. `dir="auto"` porque puede venir en
                                cualquier idioma.
                            --}}
                            <p class="mt-1 whitespace-pre-line text-sm text-gray-700 dark:text-gray-300" dir="auto">{{ $resena->comment }}</p>

                            <form method="POST" action="{{ route('admin.google_business.hide_review') }}" class="mt-3 flex flex-wrap items-center gap-2">
                                @csrf
                                <input type="hidden" name="review" value="{{ $resena->id }}">
                                <label class="sr-only" for="motivo-{{ $resena->id }}">
                                    {{ __('admin.google_business.hide_reason') }}
                                </label>
                                <select id="motivo-{{ $resena->id }}" name="reason" class="fi-input rounded-lg border-gray-300 text-sm dark:border-white/20 dark:bg-white/5 dark:text-white">
                                    @foreach ($this->motivos() as $motivo)
                                        <option value="{{ $motivo->value }}">{{ $motivo->label() }}</option>
                                    @endforeach
                                </select>
                                <x-filament::button type="submit" size="sm" color="danger" outlined>
                                    {{ __('admin.google_business.hide') }}
                                </x-filament::button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif

    @php($ocultas = $this->ocultas())
    @if ($ocultas->isNotEmpty())
        <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                {{ __('admin.google_business.hidden_title') }}
            </h3>
            {{--
                De una oculta solo guardamos su huella. El panel NO puede decir cuál era porque no lo
                sabemos, y eso es lo correcto: saberlo obligaría a conservar su texto para siempre.
            --}}
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                {{ __('admin.google_business.hidden_hint') }}
            </p>

            <ul class="mt-4 space-y-2">
                @foreach ($ocultas as $oculta)
                    <li class="flex flex-wrap items-center justify-between gap-3 rounded-lg bg-gray-50 p-3 dark:bg-white/5">
                        <span class="text-sm text-gray-950 dark:text-white">
                            {{ $oculta->reason->label() }}
                            <span class="block text-xs text-gray-500 dark:text-gray-400">
                                {{ __('admin.google_business.hidden_since', [
                                    'date' => $oculta->created_at->timezone(config('app.timezone'))->format('d/m/Y'),
                                ]) }}
                                · <code>{{ substr($oculta->review_hash, 0, 12) }}…</code>
                            </span>
                        </span>

                        <form method="POST" action="{{ route('admin.google_business.unhide_review') }}">
                            @csrf
                            <input type="hidden" name="hash" value="{{ $oculta->review_hash }}">
                            <x-filament::button type="submit" size="sm" color="gray">
                                {{ __('admin.google_business.unhide') }}
                            </x-filament::button>
                        </form>
                    </li>
                @endforeach
            </ul>

            <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                {{ __('admin.google_business.unhide_hint') }}
            </p>
        </div>
    @endif

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
