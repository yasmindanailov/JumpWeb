<div class="account__card-body">
    <h2 class="account__card-title">{{ __('account.account.privacy.delete_title') }}</h2>
    <p class="account__card-sub">{{ __('account.account.privacy.delete_intro') }}</p>

    <form wire:submit="destroy" wire:confirm="{{ __('account.account.privacy.delete_confirm') }}" class="form" novalidate>
        <div class="form__field">
            <label class="form__label" for="del-current_password">{{ __('account.account.privacy.delete_password') }}</label>
            <x-ui.password-input id="del-current_password" model="current_password" autocomplete="current-password" />
            @error('current_password') <span class="form__error">{{ $message }}</span> @enderror
        </div>

        <button type="submit" class="btn account__delete-btn" wire:loading.attr="disabled" wire:target="destroy">
            <span wire:loading.remove.delay wire:target="destroy">{{ __('account.account.privacy.delete_btn') }}</span>
            <span class="btn__loading" wire:loading.delay wire:target="destroy">
                <x-ui.spinner size="xs" :decorative="true" /> {{ __('account.account.privacy.deleting') }}
            </span>
        </button>
    </form>
</div>
