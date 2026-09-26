{{-- EL VISOR de «Ver el parque» (`content/ClipViewer.jsx`, F1c): el vídeo de portada a pantalla completa, con sonido,
     y abajo el nombre del parque, su línea y, con las respuestas abiertas, «Vamos» —el momento de más ganas, justo
     después de ver a alguien en el aire— con la nota de Google debajo. UN solo clip: sin anterior ni siguiente.
     ❗ Es un ESQUELETO servido y oculto (`hidden`): en el diseño es estado de React y no existe hasta que se abre; aquí
     lo abre, lo reproduce y lo cierra `invitacion.js` (`[data-visor]`). Los nodos y los estilos son los del React al
     abrirse; lo que depende del ancho de la VENTANA (por debajo de 640 px ocupa la pantalla entera, sin radio) va en la
     hoja (`.inv-visor--estrecho`, que pone el JS con el `matchMedia` del diseño). El vídeo no se descarga hasta tocar
     (`preload="none"`: `#743`, «se descarga solo al tocar»). Sin JavaScript no hay píldora que lo abra: se queda oculto. --}}
@php($parque = $m['parque'])
<div class="inv-visor" role="dialog" aria-modal="true" aria-label="{{ $parque['rotulo'] }}" hidden data-visor data-invitation-park-viewer style="position: fixed; inset: 0px; z-index: 95; display: flex; align-items: center; justify-content: center; gap: var(--space-6); background: color-mix(in srgb, var(--fiesta-tinta-900) 94%, transparent); -webkit-backdrop-filter: var(--blur-veil); backdrop-filter: var(--blur-veil); animation: fiesta-fade-in var(--dur-base) var(--ease-out) both;">
    <div class="inv-visor-escena" data-visor-escena style="position: relative; overflow: hidden; background: var(--fiesta-tinta-900);">
        <video data-visor-video src="{{ $parque['video'] }}"@if ($parque['poster'] !== '') poster="{{ $parque['poster'] }}"@endif playsinline loop preload="none" aria-label="{{ $parque['nombre'] }}" style="position: absolute; inset: 0px; width: 100%; height: 100%; object-fit: cover; object-position: 50% 50%; cursor: pointer;"></video>
        <span data-visor-pausa aria-hidden="true" hidden style="position: absolute; left: 50%; top: 46%; transform: translate(-50%, -50%); display: inline-flex; align-items: center; justify-content: center; width: 64px; height: 64px; border-radius: 50%; background: rgba(255, 255, 255, 0.22); border: 1px solid rgba(255, 255, 255, 0.4); color: var(--fiesta-nieve); pointer-events: none;"><x-lucide name="play" :size="26" style="margin-left: 3px;" /></span>
        {{-- Arriba: la marca del único clip y la salida. --}}
        <div class="inv-visor-arriba" style="position: absolute; left: 0px; right: 0px; top: 0px; display: grid; gap: 12px; background: linear-gradient(180deg, color-mix(in srgb, var(--fiesta-tinta-900) 60%, transparent), transparent);">
            <div style="display: flex; gap: 4px;"><span style="flex: 1; height: 14px; padding: 5px 0;"><span style="display: block; height: 3px; border-radius: 2px; background: var(--fiesta-nieve);"></span></span></div>
            <div style="display: flex; align-items: center; justify-content: space-between; gap: 10px;"><span></span><button type="button" data-visor-cerrar aria-label="{{ $parque['cerrar'] }}" style="display: inline-flex; align-items: center; justify-content: center; width: 44px; height: 44px; padding: 0px; border-radius: 50%; border: 1px solid rgba(255, 255, 255, 0.28); background: color-mix(in srgb, var(--fiesta-tinta-900) 50%, transparent); -webkit-backdrop-filter: var(--blur-veil); backdrop-filter: var(--blur-veil); color: var(--fiesta-nieve); cursor: pointer;"><x-lucide name="x" :size="20" /></button></div>
        </div>
        {{-- Abajo: qué es y la acción. --}}
        <div class="inv-visor-abajo" style="position: absolute; left: 0px; right: 0px; bottom: 0px; display: grid; gap: 14px; background: linear-gradient(0deg, color-mix(in srgb, var(--fiesta-tinta-900) 90%, transparent) 30%, transparent);">
            <span style="font-family: var(--font-display); font-weight: var(--fw-black); font-size: 1.5rem; line-height: 1.1; letter-spacing: var(--tracking-heading); color: var(--fiesta-nieve); text-wrap: balance;">{{ $parque['nombre'] }}</span>
            @if ($parque['linea'] !== '')<span style="margin-top: -6px; font-family: var(--font-ui); font-size: var(--fs-body-sm); line-height: 1.45; color: var(--fiesta-nieve); text-wrap: pretty;">{{ $parque['linea'] }}</span>@endif
            @if ($parque['accion'] !== null)<x-pieza.boton variant="primary" size="lg" full :href="$parque['accion']['href']" data-visor-accion>{{ $parque['accion']['label'] }}</x-pieza.boton>{{ '' }}@if ($parque['nota'] !== null)<span style="margin-top: -4px; text-align: center; font-family: var(--font-ui); font-size: var(--fs-caption); color: rgba(255, 255, 255, 0.84);">★ {{ $parque['nota']['texto'] }}</span>@endif{{ '' }}@endif
        </div>
    </div>
</div>
