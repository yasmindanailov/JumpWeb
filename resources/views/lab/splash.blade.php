{{-- ══ LABORATORIO · LAS MANCHAS ══════════════════════════════════════════════════════════════
     Siete maneras de colocar la misma pieza. Todas usan `<x-site.ilu>`, o sea el mecanismo real:
     lo que se ve aquí es lo que se vería en la web, no una maqueta aparte. --}}
<x-lab.frame titulo="Splash · dónde puede caer una mancha"
             lede="Las seis manchas del kit, colocadas de siete maneras. Todas salen del paquete de la instalación por el mismo hueco, así que ninguna clava arte de este cliente en el producto. Dime cuáles quieres y en qué secciones.">

    {{-- ── A · lo que ya está en el árbol ───────────────────────────────────────────────── --}}
    <figure class="lab-v">
        <div class="lab-v__head"><span class="lab-v__code">A</span><span class="lab-v__name">Detrás del titular</span></div>
        <div class="lab-box">
            <x-site.ilu clave="slot-splash-1" class="lab-p" style="left: -34px; top: -18px; width: 165px; --ilu-fg: var(--strip-1); opacity: .30;" />
            <p class="lab-box__eyebrow">Reseñas</p>
            <h2 class="lab-box__title">Lo dicen los que ya han venido</h2>
            <p class="lab-box__p">La mancha asoma por detrás de la primera palabra y no toca el párrafo.</p>
        </div>
        <p class="lab-v__note"><b>Es lo que hay hoy en la portada</b> (Reseñas, Visítanos y Dudas). Cuesta 0 px de alto:
            vive en el hueco que el titular ya deja. Medido: 10.087 px² sobre el titular y 0 sobre el párrafo.</p>
    </figure>

    {{-- ── B · XXL de fondo ─────────────────────────────────────────────────────────────── --}}
    <figure class="lab-v">
        <div class="lab-v__head"><span class="lab-v__code">B</span><span class="lab-v__name">XXL de fondo, recortada por la caja</span></div>
        <div class="lab-box lab-box--tall">
            <x-site.ilu clave="slot-splash-4" class="lab-p" style="right: -120px; bottom: -160px; width: 620px; --ilu-fg: var(--strip-1); opacity: .14;" />
            <p class="lab-box__eyebrow">Visítanos</p>
            <h2 class="lab-box__title">Dónde estamos y cuándo abrimos</h2>
            <p class="lab-box__p">La pieza es enorme y la caja la corta: se lee como textura, no como dibujo.</p>
        </div>
        <p class="lab-v__note">La más barata de todas en atención: a esa opacidad no compite con nada. <b>Necesita
            que la caja recorte</b> (`overflow: hidden`), o la mancha se sale de la sección y empuja el ancho.</p>
    </figure>

    {{-- ── C · sangrando por el borde ───────────────────────────────────────────────────── --}}
    <figure class="lab-v">
        <div class="lab-v__head"><span class="lab-v__code">C</span><span class="lab-v__name">Asomando por el borde de una tarjeta</span></div>
        <div class="lab-box lab-box--bleed">
            <x-site.ilu clave="slot-splash-2" class="lab-p" style="right: -56px; top: -44px; width: 190px; --ilu-fg: var(--strip-1); opacity: .55;" />
            <p class="lab-box__eyebrow">Cuánto</p>
            <h2 class="lab-box__title">Una hora, dos o el día</h2>
            <p class="lab-box__p">La mancha se sale de la tarjeta por la esquina, como una pegatina mal pegada.</p>
        </div>
        <p class="lab-v__note">La que más se ve, y la que más cuidado pide: al salirse de la caja <b>puede tapar lo que
            haya al lado</b> y en móvil se va fuera de pantalla. Aquí va al 55 % porque a 30 no se entendería el gesto.</p>
    </figure>

    {{-- ── D · dentro de una tarjeta, recortada por su radio ────────────────────────────── --}}
    <figure class="lab-v">
        <div class="lab-v__head"><span class="lab-v__code">D</span><span class="lab-v__name">Dentro de la tarjeta, en su esquina</span></div>
        <div class="lab-box">
            <x-site.ilu clave="slot-splash-3" class="lab-p" style="left: -40px; bottom: -40px; width: 220px; --ilu-fg: var(--strip-1); opacity: .22;" />
            <p class="lab-box__eyebrow">Antes de venir</p>
            <h2 class="lab-box__title">Tu registro es este QR</h2>
            <p class="lab-box__p">El radio de la tarjeta la corta en curva; la mancha queda dentro y no molesta al texto.</p>
        </div>
        <p class="lab-v__note">Es la lectura literal del canvas: <b>«el fondo es papel y el material vive dentro de las
            tarjetas»</b>. Escala bien porque la tarjeta ya tiene su propio recorte.</p>
    </figure>

    {{-- ── E · troquel ──────────────────────────────────────────────────────────────────── --}}
    <figure class="lab-v">
        <div class="lab-v__head"><span class="lab-v__code">E</span><span class="lab-v__name">Troquel: la mancha recorta un color</span></div>
        <div class="lab-box lab-box--ink">
            <x-site.ilu clave="slot-splash-5" trato="troquel" class="lab-p" style="left: 50%; top: -70px; translate: -50% 0; width: 420px; --ilu-fg: var(--strip-1); opacity: 1;" />
            <p class="lab-box__eyebrow">Qué hay dentro</p>
            <h2 class="lab-box__title">Salta, trepa y déjate caer</h2>
            <p class="lab-box__p">Aquí la mancha no se pinta: recorta. Lo que se ve por el agujero es el color.</p>
        </div>
        <p class="lab-v__note">El tratamiento con más presencia y el único que <b>no admite baja opacidad</b>: o recorta o
            no recorta. Es también el más delicado — con un símbolo sucio pinta la caja entera, y por eso el mecanismo
            lleva dos defensas escritas.</p>
    </figure>

    {{-- ── F · contorno ─────────────────────────────────────────────────────────────────── --}}
    <figure class="lab-v">
        <div class="lab-v__head"><span class="lab-v__code">F</span><span class="lab-v__name">Contorno: solo la línea</span></div>
        <div class="lab-box">
            <x-site.ilu clave="slot-splash-6" trato="contorno" class="lab-p" style="right: 24px; top: 24px; width: 260px; --ilu-fg: var(--strip-1); opacity: .9;" />
            <p class="lab-box__eyebrow">Dudas</p>
            <h2 class="lab-box__title">Lo que más nos preguntáis</h2>
            <p class="lab-box__p">Sin relleno, la mancha pesa mucho menos y se puede poner a tamaño grande.</p>
        </div>
        <p class="lab-v__note">La misma geometría que las de arriba, sin rellenar. <b>Aguanta tamaños que el relleno no
            aguanta</b> y convive con texto encima sin bajar la opacidad.</p>
    </figure>

    {{-- ── G · el muestrario ────────────────────────────────────────────────────────────── --}}
    <figure class="lab-v">
        <div class="lab-v__head"><span class="lab-v__code">G</span><span class="lab-v__name">Las seis, para elegir cuáles</span></div>
        <div class="lab-grid">
            @for ($i = 1; $i <= 6; $i++)
                <div class="lab-cell">
                    <x-site.ilu :clave="'slot-splash-'.$i" style="--ilu-fg: var(--strip-1);" />
                    <span class="lab-cell__n">mancha {{ $i }}</span>
                </div>
            @endfor
        </div>
        <p class="lab-v__note">Son las seis formas fijas del kit. <b>Tres de ellas ya están colocadas</b> en la portada
            (A). El artboard dice que se repartan sin repetir la misma en una pantalla.</p>
    </figure>

</x-lab.frame>
