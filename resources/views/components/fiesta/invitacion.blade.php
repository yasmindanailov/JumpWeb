@props(['theme' => 'confeti', 'name' => '', 'age' => '', 'title' => '', 'when' => '', 'date' => '', 'time' => '', 'place' => '', 'host' => '', 'words' => '', 'gifts' => '', 'phone' => '', 'phoneHref' => '', 'art' => null, 'logo' => null, 'variant' => 'card', 'titleTag' => 'h3', 'mini' => false, 'animate' => false, 'labels' => []])
@php
    /*
     * LA INVITACIÓN de un cumpleaños, pintada con su tema (`invitados/InviteCard.jsx`), con sus estilos EN LÍNEA como
     * el JSX: `mini` es solo la banda (el selector de tema); `variant="thumb"`, la miniatura fija de WhatsApp. Los
     * temas del diseño pintan con la paleta del parque: aquí, roles `--fiesta-*`; con el kit de ilustración, hasta
     * seis (cada tema es una entrada más, con su `art`). Cada tema trae su entrada, una vez: confeti cae; fiesta
     * cuelga banderines y el confeti estalla desde la chapa; sereno sube burbujas. Nunca lleva foto del menor.
     */
    $temas = [
        'confeti' => ['band' => 'var(--fiesta-agua-500)', 'chip' => 'var(--fiesta-sol-500)', 'chipFg' => 'var(--fiesta-tinta-900)', 'accent' => 'var(--fiesta-agua-700)', 'tint' => 'var(--fiesta-agua-100)', 'bits' => ['var(--fiesta-sol-500)', 'var(--fiesta-baya-500)', 'var(--fiesta-lima-500)', 'var(--fiesta-nieve)'], 'decor' => 'confeti', 'entrance' => 'fall'],
        'fiesta' => ['band' => 'var(--fiesta-baya-500)', 'chip' => 'var(--fiesta-lima-500)', 'chipFg' => 'var(--fiesta-tinta-900)', 'accent' => 'var(--fiesta-baya-700)', 'tint' => 'var(--fiesta-baya-100)', 'bits' => ['var(--fiesta-sol-500)', 'var(--fiesta-lima-500)', 'var(--fiesta-agua-400)', 'var(--fiesta-nieve)'], 'decor' => 'fiesta', 'entrance' => 'pop'],
        'sereno' => ['band' => 'var(--fiesta-agua-100)', 'chip' => 'var(--fiesta-tinta-900)', 'chipFg' => 'var(--fiesta-nieve)', 'accent' => 'var(--fiesta-agua-700)', 'tint' => 'var(--fiesta-tinta-050)', 'bits' => ['var(--fiesta-agua-600)', 'var(--fiesta-agua-400)', 'var(--fiesta-tinta-300)'], 'decor' => 'burbujas', 'entrance' => 'rise'],
    ];
    // Confeti fijo (siempre cae igual): x e y en %, tamaño, forma y giro. Burbujas de sereno: pocas y grandes.
    $bits = [[6, 22, 8, 'dot', 0], [14, 64, 12, 'bar', 28], [22, 30, 9, 'sq', 18], [31, 72, 7, 'dot', 0], [38, 18, 13, 'bar', -32], [46, 54, 8, 'sq', 40], [53, 26, 7, 'dot', 0], [60, 70, 12, 'bar', 64], [67, 36, 9, 'sq', -14], [74, 16, 8, 'dot', 0], [80, 60, 13, 'bar', -52], [87, 30, 8, 'sq', 30], [93, 68, 7, 'dot', 0], [10, 44, 6, 'dot', 0], [42, 82, 7, 'dot', 0], [70, 86, 6, 'sq', 12], [97, 12, 9, 'bar', 80], [27, 52, 6, 'dot', 0]];
    $bubbles = [[7, 34, 22], [18, 64, 12], [31, 22, 16], [45, 60, 26], [59, 24, 12], [71, 62, 18], [83, 20, 24], [93, 58, 10]];
    $t = $temas[$theme] ?? $temas['confeti'];
    $L = array_merge([
        'host' => __('fiesta.invitacion.host'), 'words' => __('fiesta.invitacion.words'), 'gifts' => __('fiesta.invitacion.gifts'),
        'call' => __('fiesta.invitacion.call'), 'unit' => __('fiesta.invitacion.unit'), 'thumb' => __('fiesta.invitacion.thumb'),
    ], $labels);
    $conEdad = $age !== '' && $age !== null;
    $num = static fn (float|int $v): string => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
    $anim = static fn (string $nombre, int $dur, int $retardo, string $ease = 'var(--ease-out)') => $animate ? $nombre.' '.$dur.'ms '.$ease.' '.$retardo.'ms both' : 'none';

    // El adorno de la banda: burbujas, o confeti (y en fiesta, además, la cuerda y los banderines delante).
    $decor = static function (bool $miniDecor, bool $animar) use ($t, $bits, $bubbles, $num, $anim): string {
        $out = '';
        if ($t['decor'] === 'burbujas') {
            foreach ($bubbles as $i => [$x, $y, $s]) {
                $size = max(6, (int) round($s * ($miniDecor ? 0.6 : 1)));
                $ring = $i % 2 === 0;
                $c = $t['bits'][$i % count($t['bits'])];
                $out .= '<span style="position: absolute; left: '.$x.'%; top: '.$y.'%; width: '.$size.'px; height: '.$size.'px; border-radius: 50%; background: '.($ring ? 'transparent' : $c).'; box-shadow: '.($ring ? 'inset 0 0 0 '.($miniDecor ? '1.5' : '2').'px '.$c : 'none').'; opacity: '.($ring ? '1' : '0.75').'; animation: '.($animar ? 'fiesta-bubble-rise 1400ms var(--ease-out) '.(80 + $i * 90).'ms both' : 'none').';"></span>';
            }

            return $out;
        }
        $fiesta = $t['decor'] === 'fiesta';
        $piezas = '';
        $i = 0;
        foreach ($bits as $b) {
            if ($fiesta && $b[1] <= 30) {
                continue;
            }
            [$x, $y, $s, $shape, $r] = $b;
            $size = $miniDecor ? max(4, (int) round($s * 0.6)) : $s;
            $w = $shape === 'bar' ? $num($size * 0.42) : (string) $size;
            $animacion = $t['entrance'] === 'pop'
                ? ($animar ? 'fiesta-confetti-pop 620ms var(--ease-spring) '.(160 + (int) round($x * 4.2)).'ms both' : 'none')
                : ($animar ? 'fiesta-confetti-fall 900ms var(--ease-out) '.($i * 28).'ms both' : 'none');
            $piezas .= '<span style="position: absolute; left: '.$x.'%; top: '.$y.'%; width: '.$w.'px; height: '.$size.'px; border-radius: '.($shape === 'dot' ? '50%' : '2px').'; background: '.$t['bits'][$i % count($t['bits'])].'; --r: '.$r.'deg; transform: rotate('.$r.'deg); animation: '.$animacion.';"></span>';
            $i++;
        }
        if (! $fiesta) {
            return $piezas;
        }
        $n = $miniDecor ? 8 : 11;
        $w = $miniDecor ? 9 : 16;
        $h = $miniDecor ? 11 : 20;
        $cs = [$t['bits'][0], $t['bits'][3], $t['bits'][1], $t['bits'][2]];
        $out .= '<span style="position: absolute; left: 0px; right: 0px; top: 0px; height: '.($miniDecor ? '1.5' : '2').'px; background: var(--fiesta-nieve); opacity: 0.7;"></span>';
        $out .= '<span style="position: absolute; left: 0px; right: 0px; top: 0px; display: flex; justify-content: space-between; padding: 0 '.($miniDecor ? 4 : 10).'px;">';
        for ($k = 0; $k < $n; $k++) {
            $out .= '<span style="width: '.$w.'px; height: '.$h.'px; background: '.$cs[$k % 4].'; clip-path: polygon(0 0, 100% 0, 50% 100%); animation: '.($animar ? 'fiesta-pennant-drop 520ms var(--ease-spring) '.(40 + $k * 45).'ms both' : 'none').';"></span>';
        }
        $out .= '</span>';

        return $out.$piezas;
    };

    // La banda acaba en ondas; la máscara va en su capa y la chapa de edad asoma por debajo sin recortarse.
    $banda = static function (bool $miniBanda, bool $animar, ?string $unidad) use ($t, $age, $conEdad, $art, $decor, $num): string {
        $h = $miniBanda ? '58px' : 'clamp(96px, 26vw, 124px)';
        $chip = $miniBanda ? 34 : 70;
        $r = $miniBanda ? 5 : 9;
        $mask = 'linear-gradient(#000 0 0) top / 100% calc(100% - '.$r.'px) no-repeat, radial-gradient(circle at 50% 0, #000 '.$r.'px, transparent '.$num($r + 0.5).'px) bottom / '.(2 * $r).'px '.$r.'px repeat-x';
        $out = '<div aria-hidden="true" style="position: relative; height: '.$h.'; flex: 0 0 auto;">';
        $out .= '<div style="position: absolute; inset: 0px; overflow: hidden; background: '.$t['band'].'; -webkit-mask: '.$mask.'; mask: '.$mask.';">';
        if ($art) {
            $out .= '<img src="'.e($art).'" alt="" style="position: absolute; inset: 0px; width: 100%; height: 100%; object-fit: cover;">';
        }
        $out .= $decor($miniBanda, $animar).'</div>';
        if ($conEdad) {
            $out .= '<span style="position: absolute; left: '.($miniBanda ? 12 : 20).'px; bottom: '.($miniBanda ? -$chip / 2 + 4 : -$chip / 2 + 6).'px; display: inline-flex; flex-direction: column; align-items: center; justify-content: center; width: '.$chip.'px; height: '.$chip.'px; border-radius: 50%; background: '.$t['chip'].'; color: '.$t['chipFg'].'; box-shadow: 0 0 0 '.($miniBanda ? 3 : 4).'px var(--fiesta-nieve)'.($miniBanda ? '' : ', var(--shadow-sm)').'; animation: '.($animar ? 'fiesta-chip-pop 560ms var(--ease-spring) 220ms both' : 'none').';">';
            $out .= '<span style="font-family: var(--font-display); font-weight: 900; font-size: '.($miniBanda ? 17 : 32).'px; line-height: 0.9; font-variation-settings: &quot;wdth&quot; 104; font-variant-numeric: tabular-nums;">'.e((string) $age).'</span>';
            if (! $miniBanda && $unidad !== null && $unidad !== '') {
                $out .= '<span style="margin-top: 2px; font-family: var(--font-ui); font-size: 10.5px; font-weight: 800; letter-spacing: 0.06em; text-transform: uppercase;">'.e($unidad).'</span>';
            }
            $out .= '</span>';
        }

        return $out.'</div>';
    };

    $txt = 'font-family: var(--font-ui); font-size: var(--fs-body-sm); line-height: 1.45;';
    $resto = $conEdad ? __('fiesta.invitacion.rest', ['age' => $age]) : __('fiesta.invitacion.rest_sin_edad');
    $H = in_array($titleTag, ['h1', 'h2', 'h3', 'h4'], true) ? $titleTag : 'h3';
    $detalles = array_values(array_filter([['calendar-days', $date], ['clock', $time], ['map-pin', $place]], static fn (array $d): bool => $d[1] !== '' && $d[1] !== null));
    $inicial = mb_strtoupper(mb_substr(trim($host ?: $name ?: ''), 0, 1));
    $estiloExtra = $attributes->has('style') ? ' '.$attributes->get('style') : '';
@endphp
@if ($mini)
<div style="border-radius: var(--r-md); overflow: hidden; background: var(--fiesta-nieve); box-shadow: inset 0 0 0 1px var(--border-subtle); padding-bottom: 22px;{{ $estiloExtra }}" {{ $attributes->except('style') }}>{!! $banda(true, $animate, null) !!}</div>
@elseif ($variant === 'thumb')
<div role="img" aria-label="{{ $L['thumb'] }}" style="position: relative; aspect-ratio: 1.91 / 1; border-radius: var(--r-md); overflow: hidden; background: {{ $t['band'] }};{{ $estiloExtra }}" {{ $attributes->except('style') }}>@if ($art)<img src="{{ $art }}" alt="" style="position: absolute; inset: 0px; width: 100%; height: 100%; object-fit: cover;">@endif{!! $decor(false, false) !!}@if ($logo)<span style="position: absolute; left: 50%; top: 54%; transform: translate(-50%, -50%); width: 48%; padding: 4% 5%; box-sizing: border-box; border-radius: var(--r-lg); background: var(--fiesta-nieve); box-shadow: var(--shadow-sm);"><img src="{{ $logo }}" alt="" style="display: block; width: 100%; height: auto;"></span>@endif</div>
@else
<article style="display: flex; flex-direction: column; min-width: 0; border-radius: var(--invite-radius, var(--r-lg)); overflow: hidden; background: var(--fiesta-nieve); color: var(--fiesta-tinta-600); box-shadow: inset 0 0 0 1px var(--fiesta-tinta-200), var(--shadow-sm);{{ $estiloExtra }}" {{ $attributes->except('style') }}>{!! $banda(false, $animate, $L['unit']) !!}<div style="display: grid; gap: 14px; padding: 46px 20px 20px;"><{{ $H }} style="margin: 0; display: grid; gap: 4px; font-family: var(--font-display); font-weight: 900; letter-spacing: -0.02em; color: var(--fiesta-tinta-900); text-wrap: balance;">@if ($title !== '')<span style="font-size: clamp(1.25rem, 4.6vw, 1.5rem); line-height: 1.08;">{{ $title }}</span>@else<span data-inv-nombre style="font-size: clamp(2rem, 9vw, 2.5rem); line-height: 0.95; letter-spacing: -0.035em; color: {{ $t['accent'] }}; overflow-wrap: anywhere;">{{ $name }}</span><span style="font-size: clamp(1.125rem, 4.2vw, 1.3rem); line-height: 1.12;">{{ $resto }}</span>@endif</{{ $H }}>@if ($detalles !== [])<ul style="margin: 0; padding: 0; list-style: none; display: grid; gap: 7px;">@foreach ($detalles as [$ic, $tx])<li style="{{ $txt }} display: flex; align-items: flex-start; gap: 9px; color: var(--fiesta-tinta-900); font-weight: {{ $ic === 'calendar-days' ? 700 : 500 }};"><span aria-hidden="true" style="display: inline-flex; flex: 0 0 auto; margin-top: 1px; color: {{ $t['accent'] }};"><x-lucide :name="$ic" :size="16" /></span><span style="min-width: 0;">{{ $tx }}</span></li>@endforeach</ul>@elseif ($when !== '')<p style="{{ $txt }} margin: 0; color: var(--fiesta-tinta-600);">{{ $when }}</p>@endif{{ $slot }}@if ($words !== '' || $host !== '')<figure style="margin: 0; display: grid; gap: 8px; padding-top: 14px; border-top: 1px solid var(--fiesta-tinta-100);">@if ($words !== '')<span style="font-family: var(--font-ui); font-size: var(--fs-caption); font-weight: 700; letter-spacing: 0.02em; color: var(--fiesta-tinta-500);">{{ $L['words'] }}</span><div style="display: flex; align-items: flex-end; gap: 10px; min-width: 0;"><span aria-hidden="true" style="flex: 0 0 auto; display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 50%; background: {{ $t['chip'] }}; color: {{ $t['chipFg'] }}; box-shadow: 0 0 0 3px var(--fiesta-nieve); font-family: var(--font-display); font-weight: 900; font-size: 16px; line-height: 1;">{{ $inicial }}</span><blockquote style="margin: 0; min-width: 0; padding: 11px 15px; border-radius: 18px 18px 18px 6px; background: {{ $t['tint'] }}; font-family: var(--font-ui); font-weight: 600; font-size: 1.0625rem; line-height: 1.4; color: var(--fiesta-tinta-900); text-wrap: pretty; overflow-wrap: anywhere; transform-origin: 0 100%; animation: {{ $animate ? 'fiesta-bubble-in 460ms var(--ease-spring) 1100ms both' : 'none' }};">{{ $words }}</blockquote></div>@endif{{ '' }}@if ($host !== '' && $words !== '')<figcaption style="padding-left: 46px; display: flex; flex-wrap: wrap; align-items: baseline; gap: 2px 12px; font-family: var(--font-ui); font-size: var(--fs-caption); line-height: 1.45; color: var(--fiesta-tinta-500);"><span>{{ $L['host'] }}: <strong style="font-weight: 700; color: var(--fiesta-tinta-900);">{{ $host }}</strong></span>@if ($phone !== '')<{{ $phoneHref !== '' ? 'a' : 'span' }}@if ($phoneHref !== '') href="{{ $phoneHref }}"@endif style="display: inline-flex; align-items: center; gap: 4px; font-weight: 700; color: var(--fiesta-agua-700); text-decoration: {{ $phoneHref !== '' ? 'underline' : 'none' }}; text-underline-offset: 3px; white-space: nowrap;"><x-lucide name="phone" :size="13" />{{ $L['call'] }}</{{ $phoneHref !== '' ? 'a' : 'span' }}>@endif</figcaption>@elseif ($host !== '')<figcaption><p style="{{ $txt }} margin: 0; display: flex; flex-wrap: wrap; align-items: center; gap: 4px 12px; color: var(--fiesta-tinta-900);"><span><span style="color: var(--fiesta-tinta-500);">{{ $L['host'] }}: </span><strong style="font-weight: 700;">{{ $host }}</strong></span>@if ($phone !== '')<{{ $phoneHref !== '' ? 'a' : 'span' }}@if ($phoneHref !== '') href="{{ $phoneHref }}"@endif style="display: inline-flex; align-items: center; gap: 5px;{{ $phoneHref !== '' ? ' min-height: 44px;' : '' }} font-weight: 700; color: var(--fiesta-agua-700); text-decoration: {{ $phoneHref !== '' ? 'underline' : 'none' }}; text-underline-offset: 3px;"><x-lucide name="phone" :size="15" />{{ $L['call'] }}</{{ $phoneHref !== '' ? 'a' : 'span' }}>@endif</p></figcaption>@endif</figure>@endif{{ '' }}@if ($gifts !== '')<p style="margin: 0; display: flex; align-items: flex-start; gap: 7px; padding-top: 10px; border-top: 1px solid var(--fiesta-tinta-100); font-family: var(--font-ui); font-size: var(--fs-caption); line-height: 1.45; color: var(--fiesta-tinta-500); overflow-wrap: anywhere;"><span aria-hidden="true" style="display: inline-flex; flex: 0 0 auto; margin-top: 1px;"><x-lucide name="gift" :size="14" /></span><span>{{ $L['gifts'] }}: {{ $gifts }}</span></p>@endif</div></article>
@endif