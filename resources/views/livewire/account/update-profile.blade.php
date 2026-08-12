@php($_user = auth()->user())
<div class="account__card-body">
    <h2 class="account__card-title">{{ __('account.account.profile.title') }}</h2>
    <p class="account__card-sub">{{ __('account.account.profile.intro') }}</p>

    {{-- Cambio de email pendiente: muestra al usuario que tiene un cambio en curso, su caducidad
         y los botones cancelar / reenviar. Sin esto, el flujo `pending_email` (auditoría
         2026-05-26 #89) es invisible para el cliente (C-01/C-02/C-03 — 2ª auditoría). --}}
    @if ($_user->pending_email)
        @php($_minutes = $this->pendingEmailMinutesLeft())
        <div class="account__pending" role="status">
            <div class="account__pending-body">
                <strong class="account__pending-title">{{ __('account.account.profile.pending_email_title') }}</strong>
                <p class="account__pending-msg">
                    {{ __('account.account.profile.pending_email_msg', [
                        'email' => $_user->pending_email,
                        'minutes' => $_minutes,
                        'current' => $_user->email,
                    ]) }}
                </p>
            </div>
            <div class="account__pending-actions">
                <button type="button" class="btn btn--ghost" wire:click="resendPendingEmail" wire:loading.attr="disabled" wire:target="resendPendingEmail">
                    <span wire:loading.remove.delay wire:target="resendPendingEmail">{{ __('account.account.profile.pending_email_resend') }}</span>
                    <span wire:loading.delay wire:target="resendPendingEmail">…</span>
                </button>
                <button type="button" class="btn btn--ghost" wire:click="cancelEmailChange" wire:loading.attr="disabled" wire:target="cancelEmailChange">
                    {{ __('account.account.profile.pending_email_cancel') }}
                </button>
            </div>
        </div>
    @endif

    <form wire:submit="save" class="form" novalidate>
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
            <label class="form__label" for="up-profile-name">{{ __('account.account.profile.name') }}</label>
            <input id="up-profile-name" type="text" wire:model="name" autocomplete="name" required>
            @error('name') <span class="form__error">{{ $message }}</span> @enderror
        </div>

        <div class="form__row">
            <div class="form__field">
                <label class="form__label" for="up-profile-email">{{ __('account.account.profile.email') }}</label>
                <input id="up-profile-email" type="email" wire:model="email" autocomplete="email" required>
                @error('email') <span class="form__error">{{ $message }}</span> @enderror
            </div>
            <div class="form__field">
                <label class="form__label" for="up-profile-phone">{{ __('account.account.profile.phone') }}</label>
                <input id="up-profile-phone" type="tel" wire:model="phone" autocomplete="tel" required>
                @error('phone') <span class="form__error">{{ $message }}</span> @enderror
            </div>
        </div>

        <div class="form__field">
            <label class="form__label" for="up-profile-locale">{{ __('account.account.profile.locale') }}</label>
            <select id="up-profile-locale" wire:model="locale">
                <option value="es">Español</option>
                <option value="en">English</option>
                <option value="fr">Français</option>
            </select>
            @error('locale') <span class="form__error">{{ $message }}</span> @enderror
        </div>

        <div class="form__field">
            <label class="form__label" for="up-profile-current_password">{{ __('account.account.profile.current_password') }}</label>
            <x-ui.password-input id="up-profile-current_password" model="current_password" autocomplete="current-password" :required="false" />
            <small class="form__hint">{{ __('account.account.profile.email_change_hint') }}</small>
            @error('current_password') <span class="form__error">{{ $message }}</span> @enderror
        </div>

        <button type="submit" class="btn btn--zone auth__submit" wire:loading.attr="disabled" wire:target="save">
            <span wire:loading.remove.delay wire:target="save">{{ __('account.account.profile.save') }}</span>
            <span class="btn__loading" wire:loading.delay wire:target="save">
                <x-ui.spinner size="xs" :decorative="true" /> {{ __('account.account.profile.saving') }}
            </span>
        </button>
    </form>
</div>
