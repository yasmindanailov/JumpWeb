{{--
    «Explícamelo con IA» (T3d de `specs/analitica-para-decidir.md` §4.7 y §4.13): el texto va EN EL HTML, de solo lectura —no
    depende de JavaScript ni del estado del formulario—, con su tamaño y «Copiar» (Alpine, con la vuelta de `execCommand` como en
    `orders/partials/guest-form-link`). Si la guarda lo rechazó (algo con pinta de correo o de teléfono), el aviso y nada más.

    @var array{text: ?string, bytes: int, metrics: int, refused: bool} $explanation
--}}
@if ($explanation['refused'] || $explanation['text'] === null)
    <p class="text-sm text-danger-600 dark:text-danger-400" data-explain-refused>{{ __('admin.analytics.explain.refused') }}</p>
@else
    <div class="space-y-3" data-explain x-data="{
        copied: false,
        copy() {
            const el = this.$refs.text;
            const done = () => { this.copied = true; setTimeout(() => this.copied = false, 1500) };
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(el.value).then(done).catch(() => this.fallback());
            } else {
                this.fallback();
            }
        },
        fallback() {
            const el = this.$refs.text;
            el.select();
            try { document.execCommand('copy') } catch (e) {}
            this.copied = true; setTimeout(() => this.copied = false, 1500);
        },
    }">
        <textarea
            x-ref="text"
            readonly
            rows="14"
            onfocus="this.select()"
            data-explain-text
            class="block w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 font-mono text-xs leading-5 text-gray-700 shadow-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5 dark:text-gray-200"
        >{{ $explanation['text'] }}</textarea>

        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="text-sm text-gray-500 dark:text-gray-400" data-explain-size>
                {{ __('admin.analytics.explain.size', ['n' => $explanation['metrics'], 'kb' => number_format($explanation['bytes'] / 1024, 1, ',', '.')]) }}
            </p>
            <x-filament::button x-on:click="copy()" icon="heroicon-o-clipboard-document" class="min-h-11" data-explain-copy>
                <span x-show="! copied">{{ __('admin.analytics.explain.copy') }}</span>
                <span x-show="copied" x-cloak>{{ __('admin.analytics.explain.copied') }}</span>
            </x-filament::button>
        </div>
    </div>
@endif
