<div class="auth">
    @if ($sent)
        {{-- Confirmación genérica (no revela si el email existe). Al cerrar recarga (fresco). --}}
        <div x-init="$store.auth.completed = true">
            <div class="auth__head">
                <span class="eyebrow">{{ __('account.forgot.eyebrow') }}</span>
                <h2 class="auth__title">{{ __('account.forgot.sent_title') }}</h2>
            </div>
            <p class="auth__sub">{{ __('account.forgot.sent_msg') }}</p>
        </div>
    @else
        <div class="auth__head">
            <span class="eyebrow">{{ __('account.forgot.eyebrow') }}</span>
            <h2 class="auth__title">{{ __('account.forgot.title') }}</h2>
            <p class="auth__sub">{{ __('account.forgot.intro') }}</p>
        </div>

        <form wire:submit="sendLink" class="form auth__form" novalidate>
            <div class="form__field">
                <label class="form__label" for="forgot-email">{{ __('account.forgot.email') }}</label>
                <input id="forgot-email" type="email" wire:model="email" autocomplete="email" required>
                @error('email') <span class="form__error">{{ $message }}</span> @enderror
            </div>

            <button type="submit" class="btn btn--zone auth__submit" wire:loading.attr="disabled" wire:target="sendLink">
                <span wire:loading.remove.delay wire:target="sendLink">{{ __('account.forgot.submit') }}</span>
                <span class="btn__loading" wire:loading.delay wire:target="sendLink">
                    <x-ui.spinner size="xs" :decorative="true" /> {{ __('account.forgot.submitting') }}
                </span>
            </button>

            <p class="auth__switch">
                <button type="button" @click="$store.auth.open('login')">{{ __('account.forgot.back_to_login') }}</button>
            </p>
        </form>
    @endif
</div>
