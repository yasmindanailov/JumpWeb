{{--
    Contenido del modal «Enlace del formulario de invitados» (#263). Muestra el enlace FIRMADO de la
    reserva para que el operador lo copie y lo mande por WhatsApp/SMS. Vista server-rendered: el
    `value` del input lleva el enlace EN EL HTML (no depende de JS ni del estado del formulario), así
    nunca sale vacío. El botón copia al portapapeles con Alpine (con fallback `execCommand`); el input
    es de solo lectura y se autoselecciona al enfocarlo como alternativa manual.
--}}
<div class="space-y-3" x-data="{
    copied: false,
    copy() {
        const el = this.$refs.input;
        const done = () => { this.copied = true; setTimeout(() => this.copied = false, 1500) };
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(el.value).then(done).catch(() => this.fallback());
        } else {
            this.fallback();
        }
    },
    fallback() {
        const el = this.$refs.input;
        el.removeAttribute('readonly'); el.select();
        try { document.execCommand('copy') } catch (e) {}
        el.setAttribute('readonly', 'readonly');
        this.copied = true; setTimeout(() => this.copied = false, 1500);
    },
}">
    <div class="flex items-center gap-2">
        <input
            x-ref="input"
            type="text"
            readonly
            value="{{ $url }}"
            onfocus="this.select()"
            class="block min-h-11 w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm text-gray-700 shadow-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5 dark:text-gray-200"
        />
        <x-filament::button
            x-on:click="copy()"
            icon="heroicon-o-clipboard-document"
            color="gray"
            class="min-h-11 shrink-0"
        >
            <span x-show="! copied">{{ __('admin.orders.copy_guest_form.copy') }}</span>
            <span x-show="copied" x-cloak>{{ __('admin.orders.copy_guest_form.copied') }}</span>
        </x-filament::button>
    </div>

    {{-- ⚠️ **La pista la decide quien incluye** (`#467`): en el modal de la ficha este partial es TODO
         el contenido y la pista orienta; en una LISTA de enlaces se repetiría por fila y sería ruido,
         porque la sección ya dice para qué son. Por defecto se pinta, que es como estaba. --}}
    @if ($hint ?? true)
        <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ __('admin.orders.copy_guest_form.hint') }}
        </p>
    @endif
</div>
