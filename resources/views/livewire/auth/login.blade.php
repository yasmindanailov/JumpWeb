<div class="auth">
    <div class="auth__head">
        <span class="eyebrow">{{ __('account.login.eyebrow') }}</span>
        <h2 class="auth__title">{{ __('account.login.title') }}</h2>
    </div>

    <form wire:submit="login" class="form auth__form" novalidate>
        {{-- Errores genéricos (sin campo asociado) en banner. El de credenciales/bloqueo va a
             la clave `_global` para distinguirlo del campo email (L-02): así nunca duplicamos. --}}
        @if ($errors->has('_global'))
            <div class="auth__errors" role="alert">
                @foreach ($errors->get('_global') as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <div class="form__field">
            <label class="form__label" for="login-email">{{ __('account.login.email') }}</label>
            <input id="login-email" type="email" wire:model="email" autocomplete="email" required>
            @error('email') <span class="form__error">{{ $message }}</span> @enderror
        </div>

        <div class="form__field">
            <label class="form__label" for="login-password">{{ __('account.login.password') }}</label>
            <x-ui.password-input id="login-password" model="password" autocomplete="current-password" />
            @error('password') <span class="form__error">{{ $message }}</span> @enderror
        </div>

        <div class="auth__row">
            <label class="check check--opt">
                <input type="checkbox" wire:model="remember">
                <span>{{ __('account.login.remember') }}</span>
            </label>
            @unless ($embedded)
                <button type="button" class="auth__link" @click="$store.auth.open('forgot')">{{ __('account.login.forgot') }}</button>
            @endunless
        </div>

        <button type="submit" class="btn btn--zone auth__submit" wire:loading.attr="disabled" wire:target="login">
            <span wire:loading.remove.delay wire:target="login">{{ __('account.login.submit') }}</span>
            <span class="btn__loading" wire:loading.delay wire:target="login">
                <x-ui.spinner size="xs" :decorative="true" /> {{ __('account.login.submitting') }}
            </span>
        </button>

        @unless ($embedded)
            <p class="auth__switch">
                {{ __('account.login.no_account') }}
                <button type="button" @click="$store.auth.open('register')">{{ __('account.register.cta') }}</button>
            </p>
        @endunless
    </form>
</div>
