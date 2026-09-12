{{-- ══ LABORATORIO · LAS SILUETAS ═════════════════════════════════════════════════════════════
     Las doce poses del kit (9 de adulto + 3 de niño), colocadas de siete maneras. Mismo mecanismo
     real que la web: `<x-site.ilu>` sobre el paquete de la instalación. --}}
<x-lab.frame titulo="Siluetas XXL · dónde puede caer una figura"
             lede="Las doce poses del kit, a tamaño grande y colocadas de siete maneras. Una figura pesa mucho más que una mancha: ocupa sitio, mira hacia algún lado y se lee como personaje. Dime cuáles quieres, a qué tamaño y en qué secciones.">

    {{-- ── A · XXL cortada por la sección ───────────────────────────────────────────────── --}}
    <figure class="lab-v">
        <div class="lab-v__head"><span class="lab-v__code">A</span><span class="lab-v__name">XXL, cortada por el borde de la sección</span></div>
        <div class="lab-box lab-box--tall">
            <x-site.ilu clave="slot-pose-p5" class="lab-p" style="right: -40px; bottom: -90px; height: 520px; width: auto; --ilu-fg: var(--strip-1); opacity: .16;" />
            <p class="lab-box__eyebrow">Para quién</p>
            <h2 class="lab-box__title">Cada uno tiene su zona</h2>
            <p class="lab-box__p">La figura es más alta que la caja y la caja la corta: se ve media persona saltando.</p>
        </div>
        <p class="lab-v__note">La manera más barata de meter una figura grande sin robar sitio: <b>no añade alto</b>,
            porque vive en el que la sección ya tiene. A esta opacidad no compite con el texto.</p>
    </figure>

    {{-- ── B · silueta maciza, protagonista ─────────────────────────────────────────────── --}}
    <figure class="lab-v">
        <div class="lab-v__head"><span class="lab-v__code">B</span><span class="lab-v__name">Maciza y a plena opacidad, sobre tinta</span></div>
        <div class="lab-box lab-box--ink lab-box--tall">
            <x-site.ilu clave="slot-pose-p8" class="lab-p" style="right: 40px; bottom: -60px; height: 480px; width: auto; --ilu-fg: var(--strip-1); opacity: 1;" />
            <p class="lab-box__eyebrow">Vamos a saltar</p>
            <h2 class="lab-box__title">Elige día y hora</h2>
            <p class="lab-box__p">Aquí la figura no es textura: es el sujeto de la pantalla.</p>
        </div>
        <p class="lab-v__note">Es lo que el mural del parque hace de verdad. <b>Pide sitio propio</b>: sobre papel a plena
            opacidad la figura se come el texto, así que o va sobre tinta o va con su columna.</p>
    </figure>

    {{-- ── C · asomando por arriba, saliéndose de la caja ───────────────────────────────── --}}
    <figure class="lab-v">
        <div class="lab-v__head"><span class="lab-v__code">C</span><span class="lab-v__name">Saliéndose de la tarjeta por arriba</span></div>
        <div class="lab-box lab-box--bleed">
            <x-site.ilu clave="slot-pose-p2" class="lab-p" style="right: 48px; top: -130px; height: 250px; width: auto; --ilu-fg: var(--strip-1); opacity: 1;" />
            <p class="lab-box__eyebrow">Cumpleaños</p>
            <h2 class="lab-box__title">El cumple, resuelto</h2>
            <p class="lab-box__p">La figura se apoya en el borde de la tarjeta y asoma por encima.</p>
        </div>
        <p class="lab-v__note">El gesto más alegre de los siete, y el que más cuesta de colocar: <b>se sale de la caja</b>,
            así que necesita aire reservado arriba y en móvil hay que reubicarla o encogerla.</p>
    </figure>

    {{-- ── D · dentro de una caja pequeña, a escala normal ──────────────────────────────── --}}
    <figure class="lab-v">
        <div class="lab-v__head"><span class="lab-v__code">D</span><span class="lab-v__name">Dentro de una caja, a su tamaño</span></div>
        <div class="lab-box" style="display: grid; grid-template-columns: 132px 1fr; gap: var(--sp-28); align-items: center;">
            <div style="display: grid; place-items: center;">
                <x-site.ilu clave="slot-pose-k2" style="height: 168px; width: auto; --ilu-fg: var(--strip-1);" />
            </div>
            <div>
                <p class="lab-box__eyebrow">Antes de venir</p>
                <h2 class="lab-box__title">Tu registro es este QR</h2>
                <p class="lab-box__p">Aquí no es decoración de fondo: es una ilustración en su columna, dentro del flujo.</p>
            </div>
        </div>
        <p class="lab-v__note">La única de las siete que <b>no va absoluta</b>: ocupa sitio de verdad y empuja al texto.
            A cambio es la más previsible — no se sale, no tapa y en móvil se apila sola.</p>
    </figure>

    {{-- ── E · troquel con la figura ────────────────────────────────────────────────────── --}}
    <figure class="lab-v">
        <div class="lab-v__head"><span class="lab-v__code">E</span><span class="lab-v__name">Troquel: la figura recorta un color</span></div>
        <div class="lab-box lab-box--tall">
            <x-site.ilu clave="slot-pose-p7" trato="troquel" class="lab-p" style="left: 40px; bottom: 0; height: 380px; width: auto; --ilu-fg: var(--strip-1); opacity: 1;" />
            <p class="lab-box__eyebrow">Qué hay dentro</p>
            <h2 class="lab-box__title" style="text-align: right;">Salta, trepa y déjate caer</h2>
            <p class="lab-box__p" style="margin-left: auto; text-align: right;">La silueta troquelada deja ver el color por dentro.</p>
        </div>
        <p class="lab-v__note">Con una foto detrás en vez de un color plano, éste es el tratamiento del mural.
            <b>Hoy no hay foto detrás</b>: eso sería una pieza nueva, no una colocación.</p>
    </figure>

    {{-- ── F · varias en fila, como friso ───────────────────────────────────────────────── --}}
    <figure class="lab-v">
        <div class="lab-v__head"><span class="lab-v__code">F</span><span class="lab-v__name">Varias en fila, de remate</span></div>
        <div class="lab-box" style="padding-bottom: 0;">
            <p class="lab-box__eyebrow">Visítanos</p>
            <h2 class="lab-box__title">Dónde estamos y cuándo abrimos</h2>
            <div style="display: flex; align-items: flex-end; justify-content: center; gap: var(--sp-16); margin-top: var(--sp-28);">
                @foreach (['p3', 'k1', 'p6', 'k3', 'p9'] as $p)
                    <x-site.ilu :clave="'slot-pose-'.$p" style="height: 120px; width: auto; --ilu-fg: var(--strip-1); opacity: .85;" />
                @endforeach
            </div>
        </div>
        <p class="lab-v__note">Es el friso familiar del artboard, montado con poses sueltas. ⚠️ Su propia regla dice
            <b>«una pose no se repite dos veces en la misma pantalla»</b> — y su propio dibujo la incumple (14 copias
            para 9 poses). Aquí son cinco distintas.</p>
    </figure>

    {{-- ── G · el muestrario ────────────────────────────────────────────────────────────── --}}
    <figure class="lab-v">
        <div class="lab-v__head"><span class="lab-v__code">G</span><span class="lab-v__name">Las doce, para elegir cuáles</span></div>
        <div class="lab-grid">
            @foreach (['p1' => 'abierto', 'p2' => 'picado', 'p3' => 'zancada', 'p4' => 'tijera',
                       'p5' => 'victoria', 'p6' => 'impulso', 'p7' => 'estrella', 'p8' => 'cohete',
                       'p9' => 'puños', 'k1' => 'aspas (niño)', 'k2' => 'antena (niño)', 'k3' => 'estrellita (niño)'] as $cod => $nombre)
                <div class="lab-cell">
                    <x-site.ilu :clave="'slot-pose-'.$cod" style="--ilu-fg: var(--strip-1); height: 108px; width: auto;" />
                    <span class="lab-cell__n">{{ strtoupper($cod) }} · {{ $nombre }}</span>
                </div>
            @endforeach
        </div>
        <p class="lab-v__note">Nueve de adulto y tres de niño, con el nombre que les da el artboard. <b>Son arte de este
            parque</b>: entran por el paquete de la instalación y nunca al repo del producto.</p>
    </figure>

</x-lab.frame>
