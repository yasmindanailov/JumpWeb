<div class="account__card-body">
    <h2 class="account__card-title">{{ __('account.account.sessions.title') }}</h2>
    <p class="account__card-sub">{{ __('account.account.sessions.intro') }}</p>

    <form wire:submit="confirm" class="form" novalidate>
        <x-ui.global-errors :bag="$errors" />

        <div class="form__field">
            <label class="form__label" for="lod-current_password">{{ __('account.account.sessions.current_password') }}</label>
            <x-ui.password-input id="lod-current_password" model="current_password" autocomplete="current-password" />
            @error('current_password') <span class="form__error">{{ $message }}</span> @enderror
        </div>

        <button type="submit" class="btn nav__logout account__danger-btn" wire:loading.attr="disabled" wire:target="confirm">
            <span wire:loading.remove.delay wire:target="confirm">{{ __('account.account.sessions.logout_others') }}</span>
            <span class="btn__loading" wire:loading.delay wire:target="confirm">
                <x-ui.spinner size="xs" :decorative="true" /> {{ __('account.account.sessions.working') }}
            </span>
        </button>
    </form>
</div>
