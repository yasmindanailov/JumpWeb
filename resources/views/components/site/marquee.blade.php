<div class="marquee">
    <div class="marquee__track">
        @for ($r = 0; $r < 4; $r++)
            @foreach (__('landing.marquee') as $k => $word)
                <span class="marquee__item">
                    <span class="{{ $k % 3 === 1 ? 'outline' : '' }}">{{ $word }}</span>
                    <span class="jj-block jj-block--xl"></span>
                </span>
            @endforeach
        @endfor
    </div>
</div>
