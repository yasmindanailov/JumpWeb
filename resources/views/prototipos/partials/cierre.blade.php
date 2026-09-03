{{-- El hero del cierre con el minijuego, TAL CUAL (copiado sin comentarios de `home.blade.php`): es el
     único momento de sorpresa y no se toca (`[owner, 2026-09-02]`). --}}
<section id="reserve" class="reserve" x-data="cierreChoreo">
    <div class="reserve__box" data-surface="ink"
         x-data="saltaJuego"
         @pointerdown="toca($event)"
         @pointerup="sueltaTap($event)"
         :class="fase === 'jugando' && 'reserve__box--jugando'">
        <div class="grain" aria-hidden="true"></div>
        <canvas class="salta__lienzo" x-ref="lienzo" aria-hidden="true"
                :style="fase === 'off' ? 'pointer-events:none' : 'pointer-events:auto;cursor:pointer'"></canvas>
        <button type="button" class="salta__invita" x-show="fase === 'listo'" @click="juega()" :aria-label="@js(__('landing.game.aria'))">
            <span class="salta__invita-ico" aria-hidden="true"><x-icons.play :width="11" :height="11" /></span>
            <span class="salta__invita-t" x-text="tactil ? @js(__('landing.game.play_touch')) : @js(__('landing.game.play'))"></span>
            <span class="salta__rec" x-show="record > 0" x-text="@js(__('landing.game.rec', ['m' => '§'])).replace('§', record)"></span>
        </button>
        <div class="salta__hud" x-show="fase === 'jugando'" aria-live="polite" aria-atomic="true">
            <span class="salta__chip"><strong x-text="metros">0</strong><span class="salta__u">{{ __('landing.game.m') }}</span></span>
            <span class="salta__chip salta__chip--band"><span class="salta__aro" aria-hidden="true"></span><strong x-text="pulseras">0</strong><span class="sr-only">{{ __('landing.game.bands') }}</span></span>
            <span class="salta__pista"><span x-text="@js(__('landing.game.record', ['m' => '§'])).replace('§', record)"></span> · <span x-text="tactil ? @js(__('landing.game.hint_touch')) : @js(__('landing.game.hint'))"></span></span>
        </div>
        <div class="salta__fin" x-show="fase === 'fin'" role="status">
            <span class="salta__fin-rec" x-show="nuevoRecord">{{ __('landing.game.newrec') }}</span>
            <span class="salta__fin-m"><strong x-text="metros">0</strong><span class="salta__fin-u"> {{ __('landing.game.m') }}</span><span class="salta__fin-band"><span class="salta__aro" aria-hidden="true"></span><span x-text="pulseras">0</span></span></span>
            <span class="salta__fin-best" x-text="@js(__('landing.game.record', ['m' => '§'])).replace('§', record)"></span>
            <span class="salta__fin-acts">
                <button type="button" class="salta__btn" @click="juega()" x-text="tactil ? @js(__('landing.game.again_touch')) : @js(__('landing.game.again'))"></button>
                @if ($site['sales_online'])
                <button type="button" class="salta__btn salta__btn--ghost" @click="sal(); $store.purchase.open()">{{ __('landing.game.book') }}</button>
                @elseif ($site['has_phone'])
                <a class="salta__btn salta__btn--ghost" href="tel:{{ $site['phone_tel'] }}">{{ __('landing.pricing.call') }}</a>
                @endif
            </span>
        </div>
        <span class="reserve__tag" aria-hidden="true"></span>
        <div class="reserve__body">
            <h2>{{ __('landing.reserve.title') }}<br /><span class="stroke">{{ __('landing.reserve.stroke') }}</span> <span class="fill">{{ __('landing.reserve.fill') }}</span></h2>
            <p>{{ __('landing.reserve.copy') }}</p>
            <div class="reserve__actions">
                <a href="{{ $site['sales_online'] ? route('entradas') : ($site['has_phone'] ? 'tel:'.$site['phone_tel'] : route('precios')) }}"
                   @if ($site['sales_online']) @click.prevent="$store.purchase.open()" @endif class="reserve__act">{{ __('landing.reserve.cta') }}</a>
                @if ($site['has_phone'])
                    <a href="tel:{{ $site['phone_tel'] }}" class="reserve__act reserve__act--alt">{{ __('landing.reserve.cta2') }} {{ $site['phone'] }}</a>
                @endif
            </div>
        </div>
    </div>
</section>
