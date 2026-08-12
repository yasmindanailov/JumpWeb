<div class="account__card-body">
    <h2 class="account__card-title">{{ __('account.account.password.title') }}</h2>
    <p class="account__card-sub">{{ __('account.account.password.intro') }}</p>

    <form wire:submit="save" class="form" novalidate>
        <div class="form__field">
            <label class="form__label" for="up-current_password">{{ __('account.account.password.current') }}</label>
            <x-ui.password-input id="up-current_password" model="current_password" autocomplete="current-password" />
            @error('current_password') <span class="form__error">{{ $message }}</span> @enderror
        </div>

        <div class="form__field">
            <label class="form__label" for="up-password">{{ __('account.account.password.new') }}</label>
            <x-ui.password-input id="up-password" model="password" autocomplete="new-password" />
            <small class="form__hint">{{ __('account.register.password_hint') }}</small>
            @error('password') <span class="form__error">{{ $message }}</span> @enderror
        </div>

        <div class="form__field">
            <label class="form__label" for="up-password_confirmation">{{ __('account.account.password.confirm') }}</label>
            <x-ui.password-input id="up-password_confirmation" model="password_confirmation" autocomplete="new-password" />
        </div>

        <button type="submit" class="btn btn--zone auth__submit" wire:loading.attr="disabled" wire:target="save">
            <span wire:loading.remove.delay wire:target="save">{{ __('account.account.password.save') }}</span>
            <span class="btn__loading" wire:loading.delay wire:target="save">
                <x-ui.spinner size="xs" :decorative="true" /> {{ __('account.account.password.saving') }}
            </span>
        </button>
    </form>
</div>
