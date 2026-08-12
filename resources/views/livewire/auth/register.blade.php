<div class="auth">
    @if ($sent)
        {{-- Pantalla de confirmación: correo enviado + reenviar (espera 30 s, con límite). --}}
        <div x-data="{ cooldown: 30, tick: null,
                start() { this.cooldown = 30; clearInterval(this.tick); this.tick = setInterval(() => { if (this.cooldown > 0) { this.cooldown--; } else { clearInterval(this.tick); } }, 1000); } }"
             x-init="start(); @unless ($embedded) $store.auth.completed = true; @endunless">
            <div class="auth__head">
                <span class="eyebrow">{{ __('account.verify.eyebrow') }}</span>
                <h2 class="auth__title">{{ __('account.verify.title') }}</h2>
            </div>

            <p class="auth__sent">{!! __('account.verify.sent_to', ['email' => '<strong>'.e($email).'</strong>']) !!}</p>
            <p class="auth__sub">{{ __('account.verify.spam_hint') }}</p>

            @if ($resendsLeft > 0)
                <button type="button" class="btn btn--zone auth__submit"
                        x-bind:disabled="cooldown > 0"
                        wire:loading.attr="disabled" wire:target="resend"
                        wire:click="resend"
                        x-on:click="cooldown === 0 && start()">
                    <span wire:loading.remove.delay wire:target="resend" x-show="cooldown === 0">{{ __('account.verify.resend') }}</span>
                    <span wire:loading.remove.delay wire:target="resend" x-show="cooldown > 0" x-cloak>{{ __('account.verify.resend_in') }} <span x-text="cooldown"></span>s</span>
                    <span class="btn__loading" wire:loading.delay wire:target="resend">
                        <x-ui.spinner size="xs" :decorative="true" /> {{ __('account.verify.resending') }}
                    </span>
                </button>
                <p class="form__hint">{{ __('account.verify.resends_left', ['n' => $resendsLeft]) }}</p>
            @else
                <p class="auth__sub">{{ __('account.verify.resend_limit') }}</p>
            @endif

            {{-- Escape "¿ya tienes cuenta?" — decisión #112 (2026-05-28). Visible SIEMPRE
                 (anti-enumeración #46 intacta: aparece igual en registro real que en duplicado).
                 Resuelve el edge case en que el usuario intentó registrarse con un email
                 que ya existía: el correo `AccountAlreadyExists` ya le sugiere "inicia sesión",
                 y aquí tiene también el escape en la propia pestaña sin tener que cerrarla. --}}
            <p class="auth__switch auth__switch--from-sent">
                {{ __('account.verify.already_have_account') }}
                @if ($embedded)
                    <button type="button" wire:click="requestSwitchToLogin">{{ __('account.login.cta') }}</button>
                @else
                    <button type="button" @click="$store.auth.open('login')">{{ __('account.login.cta') }}</button>
                @endif
            </p>
        </div>
    @else
        <div class="auth__head">
            <span class="eyebrow">{{ __('account.register.eyebrow') }}</span>
            <h2 class="auth__title">{{ __('account.register.title') }}</h2>
            <p class="auth__sub">{{ __('account.register.subtitle') }}</p>
        </div>

        <form wire:submit="register" class="form auth__form" novalidate>
            {{-- Honeypot anti-bot: display:none para que el autocompletar no lo rellene. --}}
            <div class="hp" aria-hidden="true">
                <label>{{ __('account.register.leave_blank') }}
                    <input type="text" wire:model="website" tabindex="-1" autocomplete="off">
                </label>
            </div>

            {{-- Banner-resumen para formulario largo (A11y). Mantenemos esta lista — los detalles
                 bajo cada campo permiten corregir uno a uno; el banner permite ver el conjunto. --}}
            @if ($errors->any())
                <div class="auth__errors" role="alert">
                    <strong>{{ __('account.register.fix_errors') }}</strong>
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="form__field">
                <label class="form__label" for="reg-name">{{ __('account.register.name') }}</label>
                <input id="reg-name" type="text" wire:model="name" autocomplete="name" required>
                @error('name') <span class="form__error">{{ $message }}</span> @enderror
            </div>

            <div class="form__row">
                <div class="form__field">
                    <label class="form__label" for="reg-email">{{ __('account.register.email') }}</label>
                    <input id="reg-email" type="email" wire:model="email" autocomplete="email" required>
                    @error('email') <span class="form__error">{{ $message }}</span> @enderror
                </div>
                <div class="form__field">
                    <label class="form__label" for="reg-phone">{{ __('account.register.phone') }}</label>
                    <input id="reg-phone" type="tel" wire:model="phone" autocomplete="tel" required>
                    @error('phone') <span class="form__error">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="form__field">
                <label class="form__label" for="reg-password">{{ __('account.register.password') }}</label>
                <x-ui.password-input id="reg-password" model="password" autocomplete="new-password" />
                @error('password') <span class="form__error">{{ $message }}</span> @enderror
                <small class="form__hint">{{ __('account.register.password_hint') }}</small>
            </div>

            <div class="form__checks">
                <label class="check">
                    <input type="checkbox" wire:model="accept_privacy">
                    <span>{!! __('account.register.accept_privacy', ['url' => route('legal.privacidad')]) !!}</span>
                </label>
                @error('accept_privacy') <span class="form__error">{{ $message }}</span> @enderror

                <label class="check">
                    <input type="checkbox" wire:model="accept_terms">
                    <span>{!! __('account.register.accept_terms', ['url' => route('legal.condiciones')]) !!}</span>
                </label>
                @error('accept_terms') <span class="form__error">{{ $message }}</span> @enderror

                {{-- #216: la casilla del waiver sale del flujo de registro (el waiver lo gestiona el
                     sistema externo de la clienta). El consent 'waiver' y la página /waiver se conservan. --}}

                <label class="check check--opt">
                    <input type="checkbox" wire:model="marketing">
                    <span>{{ __('account.register.marketing') }}</span>
                </label>
            </div>

            @if ($turnstileEnabled)
                {{-- Turnstile DENTRO de un componente Livewire que puede llegar por un MORPH (registro
                     embebido en el flujo de compra): un <script> plano inyectado en un morph NO lo
                     ejecuta el navegador (clonado del DOM ≠ creado por el parser), así que api.js nunca
                     cargaba, el widget no se dibujaba y el token llegaba vacío → "no eres un robot" sin
                     correo ni log (la rama de token vacío de Turnstile::verify corta antes del POST).
                     Solución: usar Alpine (x-init SÍ se ejecuta en nodos inyectados por morph, a diferencia
                     de un <script>) para (1) cargar api.js bajo demanda con createElement (esto SÍ ejecuta)
                     y (2) renderizar el widget EXPLÍCITAMENTE (el auto-render de api.js solo detecta los
                     .cf-turnstile presentes en la carga inicial, no los inyectados). `wire:ignore` evita
                     que Livewire pise el <iframe> de Turnstile en los re-render de validación. --}}
                <div wire:ignore x-data="turnstileField(@js($turnstileSiteKey))"></div>
            @endif

            <button type="submit" class="btn btn--zone auth__submit" wire:loading.attr="disabled" wire:target="register">
                <span wire:loading.remove.delay wire:target="register">{{ __('account.register.submit') }}</span>
                <span class="btn__loading" wire:loading.delay wire:target="register">
                    <x-ui.spinner size="xs" :decorative="true" /> {{ __('account.register.submitting') }}
                </span>
            </button>

            @unless ($embedded)
                <p class="auth__switch">
                    {{ __('account.register.has_account') }}
                    <button type="button" @click="$store.auth.open('login')">{{ __('account.login.cta') }}</button>
                </p>
            @endunless
        </form>
    @endif
</div>
