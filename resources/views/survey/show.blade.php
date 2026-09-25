@php
    /**
     * **La PÁGINA de la encuesta del correo** (`docs/specs/encuestas.md` §4.3, T3). La abre el cliente desde el
     * botón del correo, sin sesión, y contesta una vez.
     *
     * ▶ Usa el molde de las hojas enfocadas (`focused-layout` + `.gf-*`), igual que la invitación y el
     * justificante. **Sin una línea de JS**: cada opción es un radio/checkbox oculto con su etiqueta vestida de
     * píldora táctil (`.survey__*` en `site.css`), y el formulario es un POST normal. Los errores vuelven por
     * pregunta (`answers.<clave>`) y lo marcado se conserva (`old()`).
     */
    $textMax = \App\Domain\Platform\Services\Surveys\QuestionSchema::TEXT_MAX;
    $scaleMin = \App\Domain\Platform\Services\Surveys\QuestionSchema::SCALE_MIN;
    $scaleMax = \App\Domain\Platform\Services\Surveys\QuestionSchema::SCALE_MAX;
@endphp
<x-focused-layout :title="__('surveys.page.title')">
    <div class="gf-page">
        @php $clientLogo = @filemtime(public_path('img/client-logo.svg')); @endphp
        <div class="gf-mark">
            @if ($clientLogo)
                <img class="gf-mark__logo" src="{{ asset('img/client-logo.svg') }}?v={{ $clientLogo }}"
                     alt="{{ $site['name'] ?? config('app.name') }}">
            @else
                <span class="gf-mark__brand">{{ $site['name'] ?? config('app.name') }}</span>
            @endif
            <span class="gf-mark__sub">{{ __('surveys.page.title') }}</span>
        </div>

        <main class="gf-sheet">
            <div class="gf-stub" data-surface="ink" data-survey-stub>
                <div class="gf-stub__top">
                    <span class="gf-stub__badge">{{ __('surveys.page.badge') }}</span>
                </div>
                <h1 class="gf-stub__title" data-survey-name>{{ $name }}</h1>
                <p class="survey__lede">{{ $intro ?? __('surveys.page.intro_default') }}</p>
            </div>

            <form method="post" action="{{ route('survey.answer', ['token' => $token]) }}" class="gf-form survey" data-survey-form>
                @csrf
                @foreach ($questions as $q)
                    @php $old = old('answers.'.$q['key']); @endphp
                    <fieldset class="survey__q" data-survey-question="{{ $q['key'] }}" data-survey-type="{{ $q['type'] }}">
                        <legend class="survey__label">
                            {{ $q['label'] }}
                            @if ($q['required'])
                                <span class="survey__req">· {{ __('surveys.page.required') }}</span>
                            @endif
                        </legend>

                        @if ($q['type'] === 'choice')
                            <div class="survey__options">
                                @foreach ($q['options'] as $o)
                                    <input type="radio" id="q-{{ $q['key'] }}-{{ $o['key'] }}" class="survey__input" name="answers[{{ $q['key'] }}]" value="{{ $o['key'] }}" @checked($old === $o['key'])>
                                    <label for="q-{{ $q['key'] }}-{{ $o['key'] }}" class="survey__pill">{{ $o['label'] }}</label>
                                @endforeach
                            </div>
                        @elseif ($q['type'] === 'multi')
                            <div class="survey__options">
                                @foreach ($q['options'] as $o)
                                    <input type="checkbox" id="q-{{ $q['key'] }}-{{ $o['key'] }}" class="survey__input" name="answers[{{ $q['key'] }}][]" value="{{ $o['key'] }}" @checked(in_array($o['key'], (array) ($old ?? []), true))>
                                    <label for="q-{{ $q['key'] }}-{{ $o['key'] }}" class="survey__pill">{{ $o['label'] }}</label>
                                @endforeach
                            </div>
                        @elseif ($q['type'] === 'scale')
                            <div class="survey__options">
                                @foreach (range($scaleMin, $scaleMax) as $n)
                                    <input type="radio" id="q-{{ $q['key'] }}-{{ $n }}" class="survey__input" name="answers[{{ $q['key'] }}]" value="{{ $n }}" @checked((string) $old === (string) $n)>
                                    <label for="q-{{ $q['key'] }}-{{ $n }}" class="survey__pill survey__pill--scale">{{ $n }}</label>
                                @endforeach
                            </div>
                        @elseif ($q['type'] === 'yesno')
                            <div class="survey__options">
                                <input type="radio" id="q-{{ $q['key'] }}-yes" class="survey__input" name="answers[{{ $q['key'] }}]" value="1" @checked($old === '1')>
                                <label for="q-{{ $q['key'] }}-yes" class="survey__pill">{{ __('surveys.page.yes') }}</label>
                                <input type="radio" id="q-{{ $q['key'] }}-no" class="survey__input" name="answers[{{ $q['key'] }}]" value="0" @checked($old === '0')>
                                <label for="q-{{ $q['key'] }}-no" class="survey__pill">{{ __('surveys.page.no') }}</label>
                            </div>
                        @else
                            <input type="text" id="q-{{ $q['key'] }}" class="survey__text" name="answers[{{ $q['key'] }}]" maxlength="{{ $textMax }}" autocomplete="off" value="{{ is_string($old) ? $old : '' }}" placeholder="{{ __('surveys.page.text_placeholder') }}">
                        @endif

                        @error('answers.'.$q['key'])
                            <p class="survey__error" role="alert" data-survey-error="{{ $q['key'] }}">{{ $message }}</p>
                        @enderror
                    </fieldset>
                @endforeach

                <div class="survey__actions">
                    <button type="submit" class="btn" data-survey-submit>{{ __('surveys.page.submit') }}</button>
                </div>
            </form>
        </main>
    </div>
</x-focused-layout>
