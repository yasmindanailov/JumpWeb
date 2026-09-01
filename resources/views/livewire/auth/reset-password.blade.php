<div class="auth">
    <form wire:submit="resetPassword" class="form auth__form" novalidate>
        <div class="form__field">
            <label class="form__label" for="reset-email">{{ __('account.reset.email') }}</label>
            <input id="reset-email" type="email" wire:model="email" autocomplete="email" readonly>
            @error('email') <span class="form__error">{{ $message }}</span> @enderror
        </div>

        <div class="form__field">
            <label class="form__label" for="reset-password">{{ __('account.reset.password') }}</label>
            <x-ui.password-input id="reset-password" model="password" autocomplete="new-password" />
            @error('password') <span class="form__error">{{ $message }}</span> @enderror
            <small class="form__hint">{{ __('account.register.password_hint') }}</small>
        </div>

        <div class="form__field">
            <label class="form__label" for="reset-password-confirmation">{{ __('account.reset.password_confirmation') }}</label>
            <x-ui.password-input id="reset-password-confirmation" model="password_confirmation" autocomplete="new-password" />
        </div>

        <button type="submit" class="btn auth__submit" wire:loading.attr="disabled" wire:target="resetPassword">
            <span wire:loading.remove.delay wire:target="resetPassword">{{ __('account.reset.submit') }}</span>
            <span class="btn__loading" wire:loading.delay wire:target="resetPassword">
                <x-ui.spinner size="xs" :decorative="true" /> {{ __('account.reset.submitting') }}
            </span>
        </button>
    </form>
</div>
