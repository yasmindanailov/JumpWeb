<x-layout :title="__('account.account.title')" :auth-modal="null">
<div x-data="landing">
    <x-site.nav />

    <main class="page wrap account">
        <div class="page__head">
            <div class="eyebrow">{{ __('account.account.eyebrow') }}</div>
            <h1 class="page__title">{{ __('account.account.title') }}</h1>
            <p class="account__intro">{{ __('account.account.subtitle') }}</p>
        </div>

        <div class="account__grid">
            <section class="account__card">
                <div class="account__card-body">
                    <h2 class="account__card-title">{{ __('account.orders.title') }}</h2>
                    <p class="account__card-sub">{{ __('account.orders.intro') }}</p>
                    <a href="{{ route('account.orders') }}" class="btn btn--zone">{{ __('account.orders.view') }}</a>
                </div>
            </section>

            <section class="account__card">
                @livewire('account.update-profile')
            </section>

            <section class="account__card">
                @livewire('account.update-password')
            </section>

            <section class="account__card">
                @livewire('account.logout-other-devices')
            </section>

            <section class="account__card">
                <div class="account__card-body">
                    <h2 class="account__card-title">{{ __('account.account.privacy.title') }}</h2>
                    <p class="account__card-sub">{{ __('account.account.privacy.intro') }}</p>

                    <h3 class="account__subhead">{{ __('account.account.privacy.consents_title') }}</h3>
                    @php $consents = auth()->user()->consents()->orderByDesc('accepted_at')->get(); @endphp
                    @if ($consents->isEmpty())
                        <p class="account__muted">{{ __('account.account.privacy.no_consents') }}</p>
                    @else
                        <ul class="account__consents">
                            @foreach ($consents as $consent)
                                <li>
                                    <span class="account__consent-type">{{ __('account.account.privacy.consent_types.'.$consent->type) }}</span>
                                    <span class="account__consent-meta">{{ \App\Domain\Platform\Services\DisplayTime::format($consent->accepted_at, 'd/m/Y') }} · v{{ $consent->version }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    <a href="{{ route('account.export') }}" class="btn btn--zone account__export-btn">{{ __('account.account.privacy.export_btn') }}</a>
                </div>
            </section>

            <section class="account__card account__card--danger">
                @livewire('account.delete-account')
            </section>
        </div>

        {{-- Cerrar sesión (#221): el logout vive también aquí, no solo en el bloque del sidebar. --}}
        <form method="POST" action="{{ route('logout') }}" class="account__logout">
            @csrf
            <button type="submit" class="btn btn--ghost">{{ __('account.nav.sign_out') }}</button>
        </form>

        <a href="{{ url('/') }}" class="page__back">{{ __('site.back_home') }}</a>
    </main>

    <x-site.footer />
</div>
</x-layout>
