<?php

/**
 * BANCO DE LA FIESTA — las PIEZAS del sistema nuevo contra las del diseño, con los mismos datos, y las PÁGINAS en
 * reposo (`specs/fiesta-sistema-nuevo.md` §4.4, T1a las piezas · T1b, T2 y T3 las páginas; `DECISIONES #743`, `#765`,
 * `#768`).
 *
 * Por cada pieza y estado escribe DOS páginas con el mismo marco (el de `components/invitados/invitados.card.html`):
 *   · `a/<pieza>.html`: la pieza React del diseño (`_ds_bundle.js`, con Babel), montada con esos datos;
 *   · `b/<pieza>.html`: nuestra pieza Blade (`components/pieza/*`, `components/fiesta/*`) con los MISMOS datos, la
 *     hoja del PRODUCTO (`resources/js/fiesta/fiesta.css`, los roles neutros) y DESPUÉS las de la instancia
 *     (`fuentes.css`, `saltia.css`, `fiesta.css`), como las cargará la página viva por el contrato de hojas.
 * Por cada página y estado (la pasada LIGERA, `#768`): A es la ficha del diseño tal cual (`paginas/*.card.html`, con
 * sus rutas reescritas al diseño enlazado) montando la página sin su barra de pruebas; B es nuestra página ENTERA
 * (`view()->render()`) con el modelo de `banco-fiesta/modelos.php`, que `FiestaModeloTest` iguala en forma al del
 * controlador. Y deja `lote.json` para `scripts/pixel.mjs`: cada par a 390 y a 1000/1280.
 *
 * ⚠️ El lado B lleva `<html class="js">`: el A es React (siempre con JavaScript), y las piezas con estado (la ficha
 *    del niño cerrada, los botones − y + del selector) solo se ven así con la clase que pone el JS de la página.
 * ⚠️ Los iconos: A los baja de jsDelivr (el juez los sirve desde su caché en memoria) y B los lleva en línea
 *    (`<x-lucide>`, la misma versión): 0 píxeles medido en `banco-lucide.php`.
 *
 *   cp -r ../instancias/playjump/publico/instancia public/        # las hojas y las fuentes de la instancia
 *   docker compose exec -u sail -T laravel.test php scripts/banco-fiesta.php \
 *       /var/www/instancias/playjump/diseno/playjump-design-system storage/app/pixel/banco-fiesta http://127.0.0.1:8132
 *   docker compose exec -u sail -T laravel.test php -S 127.0.0.1:8132 -t storage/app/pixel/banco-fiesta   # aparte
 *   docker compose exec -u sail -T laravel.test node scripts/pixel.mjs \
 *       --lote storage/app/pixel/banco-fiesta/lote.json --reloj 2026-09-23T16:05:00+02:00 --rehacer --reintentos 2 \
 *       --salida storage/app/pixel/banco-fiesta/juicio
 *
 * Con nombres de pieza o de página al final, solo esas (`… http://127.0.0.1:8132 invitacion-confeti lista-guardado`).
 */

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Blade;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$modelos = require __DIR__.'/banco-fiesta/modelos.php';

[$diseno, $salida, $base] = [$argv[1] ?? '', $argv[2] ?? '', $argv[3] ?? ''];
$solo = array_slice($argv, 4);
if (! is_file($diseno.'/_ds_bundle.js') || $salida === '' || $base === '') {
    fwrite(STDERR, "uso: php scripts/banco-fiesta.php <diseño> <salida> <url base> [pieza…]\n");
    exit(1);
}
foreach (['css/fuentes.css', 'css/saltia.css', 'css/fiesta.css'] as $fichero) {
    if (! is_file(public_path('instancia/'.$fichero))) {
        fwrite(STDERR, "falta public/instancia/{$fichero}: cp -r ../instancias/playjump/publico/instancia public/\n");
        exit(1);
    }
}
if (! is_file(resource_path('js/fiesta/fiesta.css'))) {
    fwrite(STDERR, "falta resources/js/fiesta/fiesta.css\n");
    exit(1);
}

app()->setLocale('es');

/** Un estado con el puntero encima (`pasar`) o con algo pulsado antes de la foto (`clics`), en los dos lados. */
$pasar = static fn (string $selector, array $clics = [], bool $movimiento = false): array => ['pasar' => $selector, 'clics' => $clics, 'movimiento' => $movimiento];

// Los datos de la ficha del diseño (`invitados.card.html`), tal cual.
$W = ['date' => 'Sábado 26 de septiembre', 'time' => 'De 17:00 a 19:00', 'place' => 'Play Jump Park, Lorca'];
$LOGO = '../assets/logo/logo-playjump.png';
$TEMAS = [['value' => 'confeti', 'label' => 'Confeti', 'note' => 'Por defecto'], ['value' => 'fiesta', 'label' => 'Fiesta'], ['value' => 'sereno', 'label' => 'Sereno']];
$W_JSX = '{ date: "Sábado 26 de septiembre", time: "De 17:00 a 19:00", place: "Play Jump Park, Lorca" }';

/**
 * Las piezas: el JSX que monta el diseño (con `W`, `LOGO` y `TEMAS` a mano), el Blade nuestro con los mismos datos
 * (`$W`, `$LOGO`, `$TEMAS` en `$datos`), el ancho del marco y si va dentro de la caja blanca (`.box`).
 */
$piezas = [
    'invitacion-confeti' => [
        'react' => '<InviteCard animate theme="confeti" name="Vera" age={7} {...W} host="Lucía, la madre de Vera"/>',
        'blade' => '<x-fiesta.invitacion animate theme="confeti" name="Vera" age="7" :date="$W[\'date\']" :time="$W[\'time\']" :place="$W[\'place\']" host="Lucía, la madre de Vera" />',
        'ancho' => 320,
    ],
    'invitacion-fiesta' => [
        'react' => '<InviteCard animate theme="fiesta" name="Vera" age={7} {...W} host="Lucía, la madre de Vera" words="Traed ganas de saltar" phone="655 120 387"/>',
        'blade' => '<x-fiesta.invitacion animate theme="fiesta" name="Vera" age="7" :date="$W[\'date\']" :time="$W[\'time\']" :place="$W[\'place\']" host="Lucía, la madre de Vera" words="Traed ganas de saltar" phone="655 120 387" />',
        'ancho' => 320,
    ],
    'invitacion-sereno' => [
        'react' => '<InviteCard animate theme="sereno" name="Vera" age={7} {...W} host="Lucía, la madre de Vera" gifts="Le encantan los libros de animales"/>',
        'blade' => '<x-fiesta.invitacion animate theme="sereno" name="Vera" age="7" :date="$W[\'date\']" :time="$W[\'time\']" :place="$W[\'place\']" host="Lucía, la madre de Vera" gifts="Le encantan los libros de animales" />',
        'ancho' => 320,
    ],
    'invitacion-llamar' => [
        'react' => '<InviteCard theme="confeti" name="Vera" age={7} {...W} host="Lucía, la madre de Vera" phone="655 120 387" phoneHref="tel:+34655120387" titleTag="h1"/>',
        'blade' => '<x-fiesta.invitacion theme="confeti" name="Vera" age="7" :date="$W[\'date\']" :time="$W[\'time\']" :place="$W[\'place\']" host="Lucía, la madre de Vera" phone="655 120 387" phoneHref="tel:+34655120387" titleTag="h1" />',
        'ancho' => 320,
    ],
    'invitacion-thumbs' => [
        'react' => '<div className="g3"><InviteCard variant="thumb" theme="confeti" logo={LOGO}/><InviteCard variant="thumb" theme="fiesta" logo={LOGO}/><InviteCard variant="thumb" theme="sereno" logo={LOGO}/></div>',
        'blade' => '<div class="g3"><x-fiesta.invitacion variant="thumb" theme="confeti" :logo="$LOGO" /><x-fiesta.invitacion variant="thumb" theme="fiesta" :logo="$LOGO" /><x-fiesta.invitacion variant="thumb" theme="sereno" :logo="$LOGO" /></div>',
        'ancho' => 960,
    ],
    'tema' => [
        'react' => '<div className="box"><ThemePicker label="Tema" name="tema" age={7} value="confeti" onChange={()=>{}} items={TEMAS}/></div>',
        'blade' => '<div class="box"><x-fiesta.tema label="Tema" name="tema" age="7" value="confeti" :items="$TEMAS" /></div>',
        'ancho' => 480,
    ],
    'complementos' => [
        'react' => '<div className="g2"><AddonCard name="Cubo de 6" line="Refrescos o aguas, con hielo" serves="Para 6 adultos" price="16 €" due="Hasta el sábado 26" changeNote="Lo cambias hasta el sábado 26" imageNote="Foto real: el cubo de bebidas en la mesa" value={2} onChange={()=>{}} total="32 € en total"/><AddonCard name="Combo café" line="Café o infusión y bollería" serves="Para 6 adultos" price="39 €" imageNote="Foto real: el combo en la cafetería" closed reason={<React.Fragment>El plazo pasó. <a href="tel:+34641995714">Llámanos</a> y lo vemos.</React.Fragment>}/></div>',
        'blade' => '<div class="g2"><x-fiesta.complemento name="Cubo de 6" line="Refrescos o aguas, con hielo" serves="Para 6 adultos" price="16 €" due="Hasta el sábado 26" changeNote="Lo cambias hasta el sábado 26" imageNote="Foto real: el cubo de bebidas en la mesa" :value="2" total="32 € en total" /><x-fiesta.complemento name="Combo café" line="Café o infusión y bollería" serves="Para 6 adultos" price="39 €" imageNote="Foto real: el combo en la cafetería" closed><x-slot:reason>El plazo pasó. <a href="tel:+34641995714">Llámanos</a> y lo vemos.</x-slot:reason></x-fiesta.complemento></div>',
        'ancho' => 620,
    ],
    'complemento-sin-pedido' => [
        'react' => '<div style={{maxWidth: 300}}><AddonCard name="Cubo de 10" line="Refrescos o aguas, con hielo" serves="Para 10 adultos" price="24 €" due="Hasta el sábado 26" changeNote="Lo cambias hasta el sábado 26" imageNote="Foto real: el cubo de 10 en la mesa de los padres" value={0} onChange={()=>{}}/></div>',
        'blade' => '<div style="max-width: 300px;"><x-fiesta.complemento name="Cubo de 10" line="Refrescos o aguas, con hielo" serves="Para 10 adultos" price="24 €" due="Hasta el sábado 26" changeNote="Lo cambias hasta el sábado 26" imageNote="Foto real: el cubo de 10 en la mesa de los padres" :value="0" /></div>',
        'ancho' => 620,
    ],
    'plazas' => [
        'react' => '<div className="box" style={{display:"grid",gap:14}}><PlacesMeter total={10} confirmed={3} label="3 de 10 plazas ocupadas"/><PlacesMeter total={10} confirmed={10} label="10 de 10 plazas ocupadas"/><PlacesMeter total={11} reserved={8} confirmed={8} pending={3} label="11 en la lista: 3 más que las 8 plazas de tu reserva, sin confirmar"/><PlacesMeter total={1} confirmed={1} label="1 de 1"/><PlacesMeter total={30} confirmed={12} pending={4} label="30"/><PlacesMeter total={48} confirmed={20} pending={6} label="48"/></div>',
        'blade' => '<div class="box" style="display: grid; gap: 14px;"><x-fiesta.plazas :total="10" :confirmed="3" label="3 de 10 plazas ocupadas" /><x-fiesta.plazas :total="10" :confirmed="10" label="10 de 10 plazas ocupadas" /><x-fiesta.plazas :total="11" :reserved="8" :confirmed="8" :pending="3" label="11 en la lista: 3 más que las 8 plazas de tu reserva, sin confirmar" /><x-fiesta.plazas :total="1" :confirmed="1" label="1 de 1" /><x-fiesta.plazas :total="30" :confirmed="12" :pending="4" label="30" /><x-fiesta.plazas :total="48" :confirmed="20" :pending="6" label="48" /></div>',
        'ancho' => 520,
    ],
    'anadir' => [
        'react' => '<div className="box"><GuestComposer id="demo" defaultAge="7" autoFocus={false} onAdd={()=>null} onClose={()=>{}}/></div>',
        'blade' => '<div class="box"><x-fiesta.anadir-invitado id="demo" defaultAge="7" :autoFocus="false" /></div>',
        'ancho' => 520,
    ],
    'filas' => [
        'react' => '<div className="box"><ul><GuestRow id="d0" name="Vera" age="7" birthday signed onToggle={()=>{}}/><GuestRow id="d1" name="Hugo Martín Sáez" age="7" state="confirmado" viaInvite signed onToggle={()=>{}}/><GuestRow id="d2" name="Lola Pérez Soto" age="6" allergies="Huevo" state="sin-contestar" dirty open onToggle={()=>{}} onChange={()=>{}} onRemove={()=>{}}/><GuestRow id="d3" name="Mateo Gil Serrano" state="sin-contestar" onToggle={()=>{}}/><GuestRow id="d4" name="Daniel Ortiz Mora" age="8" state="confirmado" viaInvite skipped onUndo={()=>{}}/><GuestRow id="d5" name="Leo Sánchez Prieto" state="no" viaInvite onRejoin={()=>{}} last/></ul></div>',
        'blade' => '<div class="box"><ul><x-fiesta.fila-invitado id="d0" name="Vera" age="7" birthday signed /><x-fiesta.fila-invitado id="d1" name="Hugo Martín Sáez" age="7" state="confirmado" viaInvite signed /><x-fiesta.fila-invitado id="d2" name="Lola Pérez Soto" age="6" allergies="Huevo" state="sin-contestar" dirty open quitar /><x-fiesta.fila-invitado id="d3" name="Mateo Gil Serrano" state="sin-contestar" /><x-fiesta.fila-invitado id="d4" name="Daniel Ortiz Mora" age="8" state="confirmado" viaInvite skipped deshacer /><x-fiesta.fila-invitado id="d5" name="Leo Sánchez Prieto" state="no" viaInvite volver last /></ul></div>',
        'ancho' => 520,
        'estados' => ['pasar' => $pasar('button[aria-expanded="false"] >> nth=0'), 'ficha' => ['clics' => ['button[aria-expanded="false"] >> nth=0']]],
    ],
    'fila-omitir' => [
        'react' => '<div className="box"><ul><GuestRow id="e1" name="Nora Jiménez Vidal" age="7" allergies="Frutos secos" state="confirmado" viaInvite signed open onToggle={()=>{}} onChange={()=>{}} onSkip={()=>{}} last/></ul></div>',
        'blade' => '<div class="box"><ul><x-fiesta.fila-invitado id="e1" name="Nora Jiménez Vidal" age="7" allergies="Frutos secos" state="confirmado" viaInvite signed open omitir last /></ul></div>',
        'ancho' => 520,
    ],
    'barra' => [
        'react' => '<div style={{display:"grid",gap:14}}><SaveBar state="dirty" status="2 cambios sin guardar" detail="4 respuestas por repasar · Borrador en este móvil" label="Guardar" buttonType="submit"/><SaveBar state="saved" status="Guardado hoy a las 16:05" label="Guardar"/><SaveBar state="clean" status="Nada que guardar todavía" label="Guardar"/><SaveBar state="conflict" status="Sin guardar" detail="Borrador en este móvil" label="Guardar" notice={<InfoCallout tone="warn" size="sm" role="alert" icon={<Icon name="triangle-alert" size={17}/>} title="La reserva ha cambiado, revísala.">No hemos guardado nada y tu borrador sigue aquí. La fiesta pasa a las 17:30 (antes, a las 17:00). Cuando lo veas, vuelve a guardar.</InfoCallout>}/></div>',
        'blade' => '<div style="display: grid; gap: 14px;"><x-fiesta.barra-guardar state="dirty" status="2 cambios sin guardar" detail="4 respuestas por repasar · Borrador en este móvil" label="Guardar" buttonType="submit" /><x-fiesta.barra-guardar state="saved" status="Guardado hoy a las 16:05" label="Guardar" /><x-fiesta.barra-guardar state="clean" status="Nada que guardar todavía" label="Guardar" /><x-fiesta.barra-guardar state="conflict" status="Sin guardar" detail="Borrador en este móvil" label="Guardar"><x-slot:aviso><x-pieza.aviso tone="warn" size="sm" role="alert" title="La reserva ha cambiado, revísala."><x-slot:icono><x-lucide name="triangle-alert" :size="17" /></x-slot:icono>{{ \'\' }}No hemos guardado nada y tu borrador sigue aquí. La fiesta pasa a las 17:30 (antes, a las 17:00). Cuando lo veas, vuelve a guardar.</x-pieza.aviso></x-slot:aviso></x-fiesta.barra-guardar></div>',
        'ancho' => 520,
    ],
    'botones' => [
        'react' => '<div className="box" style={{display:"grid",gap:12}}>{["primary","secondary","outline","ghost","quiet","volt","inverse"].map((v)=><div key={v} style={{display:"flex",flexWrap:"wrap",gap:10,alignItems:"center"}}><Button variant={v} size="sm" iconLeft={<Icon name="message-circle" size={17}/>}>Reenviar</Button><Button variant={v} size="md">Compartir por WhatsApp</Button><Button variant={v} size="lg" full={false}>Crear la invitación</Button><Button variant={v} size="md" disabled>Guardar</Button></div>)}<Button variant="primary" size="lg" full>Crear la invitación</Button></div>',
        'blade' => '<div class="box" style="display: grid; gap: 12px;">@foreach ([\'primary\', \'secondary\', \'outline\', \'ghost\', \'quiet\', \'volt\', \'inverse\'] as $v)<div style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center;"><x-pieza.boton :variant="$v" size="sm"><x-slot:izquierda><x-lucide name="message-circle" :size="17" /></x-slot:izquierda>{{ \'\' }}Reenviar</x-pieza.boton><x-pieza.boton :variant="$v" size="md">Compartir por WhatsApp</x-pieza.boton><x-pieza.boton :variant="$v" size="lg">Crear la invitación</x-pieza.boton><x-pieza.boton :variant="$v" size="md" disabled>Guardar</x-pieza.boton></div>@endforeach<x-pieza.boton variant="primary" size="lg" full>Crear la invitación</x-pieza.boton></div>',
        'ancho' => 720,
        'estados' => ['pasar' => $pasar('text=Compartir por WhatsApp >> nth=0', [], true)],
    ],
    'enlaces-chapas' => [
        'react' => '<div className="box" style={{display:"grid",gap:14}}><div style={{display:"flex",flexWrap:"wrap",gap:14,alignItems:"center"}}><Link size="sm" underline="always" onClick={()=>{}}>Ver todos</Link><Link size="md" onClick={()=>{}} icon={<Icon name="message-square-text" size={18}/>}>Escribir el recordatorio</Link><Link size="lg" href="https://example.com" external>Cómo llegar</Link><Link size="md" arrow onClick={()=>{}}>Seguir</Link><Link size="sm" variant="quiet" onClick={()=>{}}>Cancelar</Link><Link size="md" disabled onClick={()=>{}}>Nada</Link></div><div style={{display:"flex",flexWrap:"wrap",gap:8,alignItems:"center"}}><Badge tone="berry" size="sm">Es su cumple</Badge><Badge tone="aqua" size="sm">por la invitación</Badge><Badge tone="neutral" variant="outline" size="sm">Sin contestar</Badge><Badge tone="warn" size="sm">Ha cambiado</Badge><Badge tone="success">Reservado</Badge><Badge tone="danger" variant="solid">Cancelado</Badge><Badge tone="volt" dot>En vivo</Badge></div><div style={{display:"flex",flexWrap:"wrap",gap:8,alignItems:"center"}}><Tag selected count={6}>Confirmados</Tag><Tag onClick={()=>{}} count={2}>No pueden</Tag><Tag>Sin contestar</Tag><Tag onClick={()=>{}} disabled>Nada</Tag></div></div>',
        'blade' => '<div class="box" style="display: grid; gap: 14px;"><div style="display: flex; flex-wrap: wrap; gap: 14px; align-items: center;"><x-pieza.enlace size="sm" underline="always">Ver todos</x-pieza.enlace><x-pieza.enlace size="md"><x-slot:icono><x-lucide name="message-square-text" :size="18" /></x-slot:icono>{{ \'\' }}Escribir el recordatorio</x-pieza.enlace><x-pieza.enlace size="lg" href="https://example.com" external>Cómo llegar</x-pieza.enlace><x-pieza.enlace size="md" arrow>Seguir</x-pieza.enlace><x-pieza.enlace size="sm" variant="quiet">Cancelar</x-pieza.enlace><x-pieza.enlace size="md" disabled>Nada</x-pieza.enlace></div><div style="display: flex; flex-wrap: wrap; gap: 8px; align-items: center;"><x-pieza.chapa tone="berry" size="sm">Es su cumple</x-pieza.chapa><x-pieza.chapa tone="aqua" size="sm">por la invitación</x-pieza.chapa><x-pieza.chapa tone="neutral" variant="outline" size="sm">Sin contestar</x-pieza.chapa><x-pieza.chapa tone="warn" size="sm">Ha cambiado</x-pieza.chapa><x-pieza.chapa tone="success">Reservado</x-pieza.chapa><x-pieza.chapa tone="danger" variant="solid">Cancelado</x-pieza.chapa><x-pieza.chapa tone="volt" dot>En vivo</x-pieza.chapa></div><div style="display: flex; flex-wrap: wrap; gap: 8px; align-items: center;"><x-pieza.etiqueta selected :count="6">Confirmados</x-pieza.etiqueta><x-pieza.etiqueta boton :count="2">No pueden</x-pieza.etiqueta><x-pieza.etiqueta>Sin contestar</x-pieza.etiqueta><x-pieza.etiqueta boton disabled>Nada</x-pieza.etiqueta></div></div>',
        'ancho' => 720,
        'estados' => ['pasar' => $pasar('text=Escribir el recordatorio')],
    ],
    'campos' => [
        'react' => '<div className="box" style={{display:"grid",gap:14}}><Field id="c1" label="Nombre" value="Mateo Gil" onChange={()=>{}} autoComplete="off"/><Field id="c2" label="Edad" value="7" onChange={()=>{}} inputMode="numeric" maxLength={2} suffix="años"/><Field id="c3" label="Alergias o menú especial" optional value="" onChange={()=>{}} hint="Solo si hace falta."/><Field id="c4" label="Su nombre" required value="" onChange={()=>{}} error="Escribe su nombre."/><Field id="c5" label="Grande" size="lg" value="" onChange={()=>{}} placeholder="Escribe aquí"/><Checkbox id="k1" label="Enseñar mi teléfono" description="Con «Llamar» en la invitación: 655 120 387." checked onChange={()=>{}}/><Checkbox id="k2" label="Avísame de fechas para el cumple de mi hijo" checked={false} onChange={()=>{}}/><Checkbox id="k3" label="Acepto el descargo" required checked={false} onChange={()=>{}} error="Marca la casilla para firmar."><a href="#">Leer el descargo</a></Checkbox><Textarea id="t1" label="Pega los nombres, uno por línea" hint="Sirve la lista del grupo de WhatsApp." rows={4} value={"Hugo\nCarla"} onChange={()=>{}}/><Textarea id="t2" label="Unas palabras" optional maxLength={90} counter value="Traed ganas de saltar" onChange={()=>{}}/></div>',
        'blade' => '<div class="box" style="display: grid; gap: 14px;"><x-pieza.campo id="c1" label="Nombre" value="Mateo Gil" autocomplete="off" /><x-pieza.campo id="c2" label="Edad" value="7" inputmode="numeric" maxlength="2"><x-slot:sufijo>años</x-slot:sufijo></x-pieza.campo><x-pieza.campo id="c3" label="Alergias o menú especial" optional value="" hint="Solo si hace falta." /><x-pieza.campo id="c4" label="Su nombre" required value="" error="Escribe su nombre." /><x-pieza.campo id="c5" label="Grande" size="lg" value="" placeholder="Escribe aquí" /><x-pieza.casilla id="k1" label="Enseñar mi teléfono" description="Con «Llamar» en la invitación: 655 120 387." checked /><x-pieza.casilla id="k2" label="Avísame de fechas para el cumple de mi hijo" /><x-pieza.casilla id="k3" label="Acepto el descargo" required error="Marca la casilla para firmar."><a href="#">Leer el descargo</a></x-pieza.casilla><x-pieza.area id="t1" label="Pega los nombres, uno por línea" hint="Sirve la lista del grupo de WhatsApp." :rows="4" :value="$PEGADO" /><x-pieza.area id="t2" label="Unas palabras" optional :maxlength="90" counter value="Traed ganas de saltar" /></div>',
        'ancho' => 520,
        'estados' => ['foco' => ['clics' => ['#c1']]],
    ],
    'cantidades-opciones' => [
        'react' => '<div className="box" style={{display:"grid",gap:14}}><QuantityStepper variant="bare" label="Niños" value={10} min={8} max={Infinity} onChange={()=>{}} labels={["Uno menos","Uno más"]} name="numero"/><QuantityStepper variant="bare" label="Adultos" value={0} min={0} max={60} onChange={()=>{}} name="adultos"/><QuantityStepper label="Cubo de 6" sublabel="Para 6 adultos" price="16 €" value={2} min={0} max={10} onChange={()=>{}} name="x"/><QuantityStepper label="Niños" value={3} min={0} max={20} format={(n)=>n+" niños"} onChange={()=>{}}/><OptionCards name="tarta" label="¿La tarta?" hint="Hasta hoy a las 17:00" columns={3} value="nuestra" onChange={()=>{}} items={[{value:"nuestra",title:"La nuestra",description:"De 12 raciones",price:"25 €"},{value:"traemos",title:"Traemos la nuestra",description:"Se cobra el cubierto",price:"10 €"},{value:"sin",title:"Sin tarta"}]}/><OptionCards name="menu" label="Menú" value="m2" onChange={()=>{}} items={[{value:"m1",title:"Menú 1",includes:["Refresco","Sándwich"],note:"El de siempre"},{value:"m2",title:"Menú 2",price:"3 €",was:"5 €",highlight:"El más pedido",disabled:false},{value:"m3",title:"Sin menú",disabled:true}]}/></div>',
        'blade' => '<div class="box" style="display: grid; gap: 14px;"><x-pieza.cantidad variant="bare" label="Niños" :value="10" :min="8" :max="INF" :labels="[\'Uno menos\', \'Uno más\']" name="numero" /><x-pieza.cantidad variant="bare" label="Adultos" :value="0" :min="0" :max="60" name="adultos" /><x-pieza.cantidad label="Cubo de 6" sublabel="Para 6 adultos" price="16 €" :value="2" :min="0" :max="10" name="x" /><x-pieza.cantidad label="Niños" :value="3" :min="0" :max="20" format=":n niños" /><x-pieza.opciones name="tarta" label="¿La tarta?" hint="Hasta hoy a las 17:00" :columns="3" value="nuestra" :items="[[\'value\' => \'nuestra\', \'title\' => \'La nuestra\', \'description\' => \'De 12 raciones\', \'price\' => \'25 €\'], [\'value\' => \'traemos\', \'title\' => \'Traemos la nuestra\', \'description\' => \'Se cobra el cubierto\', \'price\' => \'10 €\'], [\'value\' => \'sin\', \'title\' => \'Sin tarta\']]" /><x-pieza.opciones name="menu" label="Menú" value="m2" :items="[[\'value\' => \'m1\', \'title\' => \'Menú 1\', \'includes\' => [\'Refresco\', \'Sándwich\'], \'note\' => \'El de siempre\'], [\'value\' => \'m2\', \'title\' => \'Menú 2\', \'price\' => \'3 €\', \'was\' => \'5 €\', \'highlight\' => \'El más pedido\'], [\'value\' => \'m3\', \'title\' => \'Sin menú\', \'disabled\' => true]]" /></div>',
        'ancho' => 520,
    ],
    'avisos-marco-compartir' => [
        'react' => '<div className="box" style={{display:"grid",gap:14}}><InfoCallout tone="info" size="sm" icon={<Icon name="ruler" size={17}/>}>Dos niños tienen 8 años: saltan en Jump, con su pack.</InfoCallout><InfoCallout tone="warn" size="sm" icon={<Icon name="clock-alert" size={17}/>}>El plazo del número pasó. <a href="tel:+34641995714">Llámanos</a> y lo vemos.</InfoCallout><InfoCallout tone="neutral" size="sm" icon={<Icon name="lock" size={17}/>}>Las respuestas se cerraron el viernes 25 a las 17:00.</InfoCallout><InfoCallout tone="success" size="sm" icon={<Icon name="check" size={17}/>}>Copiado. Pégalo en tu grupo de WhatsApp.</InfoCallout><InfoCallout tone="info" title="Título" action={<Button variant="quiet" size="sm">Ver</Button>}>Un aviso grande con acción.</InfoCallout><InfoCallout tone="danger" title="Peligro">Con su franja arriba.</InfoCallout><MediaFrame kind="image" src={null} alt="La tarta de la fiesta" note="Foto real: la tarta de 12 raciones del parque" aspect="16 / 9" flat/><MediaFrame kind="image" src={null} aspect="16 / 7" rounded="0" badge="Nuevo" caption="Un pie"/><ShareRow items={[{kind:"copy",label:"Copiar el enlace"},{kind:"whatsapp",label:"WhatsApp",href:"https://wa.me/?text=hola"}]} value="https://playjump.es/i/7K2P4V" confirm="Enlace copiado"/></div>',
        'blade' => '<div class="box" style="display: grid; gap: 14px;"><x-pieza.aviso tone="info" size="sm"><x-slot:icono><x-lucide name="ruler" :size="17" /></x-slot:icono>{{ \'\' }}Dos niños tienen 8 años: saltan en Jump, con su pack.</x-pieza.aviso><x-pieza.aviso tone="warn" size="sm"><x-slot:icono><x-lucide name="clock-alert" :size="17" /></x-slot:icono>{{ \'\' }}El plazo del número pasó. <a href="tel:+34641995714">Llámanos</a> y lo vemos.</x-pieza.aviso><x-pieza.aviso tone="neutral" size="sm"><x-slot:icono><x-lucide name="lock" :size="17" /></x-slot:icono>{{ \'\' }}Las respuestas se cerraron el viernes 25 a las 17:00.</x-pieza.aviso><x-pieza.aviso tone="success" size="sm"><x-slot:icono><x-lucide name="check" :size="17" /></x-slot:icono>{{ \'\' }}Copiado. Pégalo en tu grupo de WhatsApp.</x-pieza.aviso><x-pieza.aviso tone="info" title="Título"><x-slot:accion><x-pieza.boton variant="quiet" size="sm">Ver</x-pieza.boton></x-slot:accion>{{ \'\' }}Un aviso grande con acción.</x-pieza.aviso><x-pieza.aviso tone="danger" title="Peligro">Con su franja arriba.</x-pieza.aviso><x-pieza.marco kind="image" alt="La tarta de la fiesta" note="Foto real: la tarta de 12 raciones del parque" aspect="16 / 9" flat /><x-pieza.marco kind="image" aspect="16 / 7" rounded="0" badge="Nuevo" caption="Un pie" /><x-pieza.compartir :items="[[\'kind\' => \'copy\', \'label\' => \'Copiar el enlace\'], [\'kind\' => \'whatsapp\', \'label\' => \'WhatsApp\', \'href\' => \'https://wa.me/?text=hola\']]" value="https://playjump.es/i/7K2P4V" confirm="Enlace copiado" /></div>',
        'ancho' => 520,
    ],
];

is_dir($salida) || mkdir($salida, 0775, true);
foreach (['diseno' => realpath($diseno), 'assets' => realpath($diseno.'/assets'), 'instancia' => public_path('instancia'), 'fuente' => resource_path('js'), 'build' => public_path('build')] as $nombre => $destino) {
    if (! file_exists($salida.'/'.$nombre)) {
        symlink($destino, $salida.'/'.$nombre);
    }
}
foreach (['a', 'b'] as $lado) {
    is_dir($salida.'/'.$lado) || mkdir($salida.'/'.$lado, 0775, true);
}

// El marco de la ficha del diseño (`invitados.card.html`), a los dos lados.
$marco = 'body{margin:0;padding:20px;background:var(--bg-subtle);font-family:var(--font-ui)}.g3{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.g2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.box{background:var(--snow, var(--fiesta-nieve));border-radius:var(--r-xl);box-shadow:inset 0 0 0 1px var(--border-subtle);padding:16px}ul{margin:0;padding:0}';

$lote = [];
$datos = ['W' => $W, 'LOGO' => $LOGO, 'TEMAS' => $TEMAS, 'PEGADO' => "Hugo\nCarla"];
foreach ($piezas as $nombre => $pieza) {
    if ($solo !== [] && ! in_array($nombre, $solo, true)) {
        continue;
    }
    $ancho = (int) $pieza['ancho'];
    file_put_contents($salida."/a/{$nombre}.html", <<<HTML
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="../diseno/styles.css">
<script src="https://unpkg.com/react@18.3.1/umd/react.development.js" integrity="sha384-hD6/rw4ppMLGNu3tX5cjIb+uRZ7UkRJ6BPkLpg4hAu/6onKUg4lLsHAs9EBPT82L" crossorigin="anonymous"></script>
<script src="https://unpkg.com/react-dom@18.3.1/umd/react-dom.development.js" integrity="sha384-u6aeetuaXnQ38mYT8rp6sbXaQe3NL9t+IBXmnYxwkUI2Hw4bsp2Wvmx4yRQF1uAm" crossorigin="anonymous"></script>
<script src="https://unpkg.com/@babel/standalone@7.29.0/babel.min.js" integrity="sha384-m08KidiNqLdpJqLq95G/LEi8Qvjl/xUYll3QILypMoQ65QorJ9Lvtp2RXYGBFj1y" crossorigin="anonymous"></script>
<script src="../diseno/_ds_bundle.js"></script>
<style>{$marco}</style>
</head><body><div id="root"></div>
<script type="text/babel">
const { InviteCard, ThemePicker, GuestRow, AddonCard, PlacesMeter, GuestComposer, SaveBar, Button, Link, Badge, Tag, Field, Checkbox, Textarea, QuantityStepper, OptionCards, InfoCallout, MediaFrame, ShareRow, Icon } = window.SaltiaDesignSystem_33397c;
const W = {$W_JSX};
const LOGO = "{$LOGO}";
const TEMAS = [{value:"confeti",label:"Confeti",note:"Por defecto"},{value:"fiesta",label:"Fiesta"},{value:"sereno",label:"Sereno"}];
ReactDOM.createRoot(document.getElementById("root")).render(<div style={{maxWidth: {$ancho}}}>{$pieza['react']}</div>);
</script>
</body></html>
HTML);

    $cuerpo = Blade::render($pieza['blade'], $datos);
    file_put_contents($salida."/b/{$nombre}.html", <<<HTML
<!doctype html><html lang="es" class="js"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="../fuente/fiesta/fiesta.css">
<link rel="stylesheet" href="../instancia/css/fuentes.css"><link rel="stylesheet" href="../instancia/css/saltia.css"><link rel="stylesheet" href="../instancia/css/fiesta.css">
<style>{$marco}</style>
</head><body><div style="max-width: {$ancho}px;">
{$cuerpo}
</div></body></html>
HTML);

    foreach (['390x844', '1000x900'] as $ventana) {
        foreach (['' => [], ...($pieza['estados'] ?? [])] as $estado => $pasos) {
            $lote[] = [
                'nombre' => "{$nombre}-".strtok($ventana, 'x').($estado !== '' ? "-{$estado}" : ''),
                'a' => "{$base}/a/{$nombre}.html",
                'b' => "{$base}/b/{$nombre}.html",
                'viewport' => $ventana,
                'completa' => true,
                'clics' => $pasos['clics'] ?? [],
                ...(isset($pasos['pasar']) ? ['pasar' => $pasos['pasar'], 'movimiento' => $pasos['movimiento']] : []),
            ];
        }
    }
}

// ── LAS PÁGINAS: la pasada LIGERA (`#768`) — la invitación en reposo, con los datos del diseño a los dos lados ────────
// A es el propio `paginas/invitacion.card.html` (la misma fuente, nunca una copia) con sus rutas apuntando al diseño
// enlazado, en español y montando `InvPagina` sin la barra de pruebas; B es `fiesta.invitacion` con el modelo mapeado a
// mano desde `invitacion/datos.js` (`FIESTA`, `T.es`, `P.es`), con el logotipo del diseño. Sin los bloques opcionales
// (`opc=false`: sin merienda, palabras, pistas ni teléfono), que son dato que el producto aún no tiene (spec §1.4).
// ⚠️ Se juzga la VENTANA (`completa: false`), no la página entera: el aviso de privacidad bajo la barra (spec hermana
//    §7.2·R7) es del producto y el diseño no lo dibuja en la invitación. El recibo se juzga sin A hasta T3 (`AuthForm`).
$card = (string) file_get_contents($diseno.'/paginas/invitacion.card.html');
$hojas = ['instancia/css/fuentes.css', 'instancia/css/saltia.css', 'instancia/css/fiesta.css'];
foreach (['invitacion-viva' => 'viva', 'invitacion-cerrada' => 'cerrada'] as $nombre => $estado) {
    if ($solo !== [] && ! in_array($nombre, $solo, true)) {
        continue;
    }
    // A: la ficha del diseño, tal cual, con sus rutas al diseño enlazado y el montaje de la página en reposo.
    $a = str_replace(
        ['"../styles.css"', '"invitacion/datos.js"', '"invitacion/invitacion.css"', '"invitacion/vistas.jsx"', '"../components/', '"../_ds_bundle.js"'],
        ['"../diseno/styles.css"', '"../diseno/paginas/invitacion/datos.js"', '"../diseno/paginas/invitacion/invitacion.css"', '"../diseno/paginas/invitacion/vistas.jsx"', '"../diseno/components/', '"../diseno/_ds_bundle.js"'],
        $card,
    );
    $montaje = 'localStorage.setItem("pj-invitacion-idioma", JSON.stringify("es")); localStorage.removeItem("pj-invitacion-respuestas"); '
        .'window.invCargar(FUENTES).then(() => ReactDOM.createRoot(document.getElementById("root")).render(<InvPagina estado="'.$estado.'" tema="confeti" opc={false} foto={false} />));';
    $a = (string) preg_replace('/window\.invCargar\(FUENTES\)\.then\(\(\) => ReactDOM\.createRoot\(document\.getElementById\("root"\)\)\.render\(<InvBanco \/>\)\);/', $montaje, $a, 1, $n);
    if ($n !== 1) {
        fwrite(STDERR, "la ficha de la invitación cambió: no encuentro su montaje\n");
        exit(1);
    }
    file_put_contents($salida."/a/{$nombre}.html", $a);

    // B: la página del producto, entera, con el modelo del diseño y las hojas de la instancia por el contrato.
    $b = view('fiesta.invitacion', ['m' => $modelos['invitacion']($estado === 'viva'), 'hojas' => $hojas])->render();
    // ⚠️ Los assets van por el MISMO origen que la página del banco (`../build`, `../instancia`, enlazados): servidos
    //    desde `APP_URL` el navegador bloquea las fuentes y el módulo de Vite (CORS) y B sale sin `js` y con la
    //    fuente del sistema. Medido: 35 % de píxeles distintos que no eran de la piel.
    $b = str_replace(rtrim((string) config('app.url'), '/').'/', '../', $b);
    file_put_contents($salida."/b/{$nombre}.html", $b);
    // El par de DIAGNÓSTICO: la misma B sin el aviso de privacidad (que el diseño no dibuja en la invitación y el
    // producto exige). Tiene que dar 0: así el par real solo puede diferir en esa línea, y se ve cuánto.
    file_put_contents($salida."/b/{$nombre}-sin-legal.html", (string) preg_replace('#<p class="inv-legal"[^>]*>.*?</p>#s', '', $b));

    foreach (['390x844', '1280x900'] as $ventana) {
        foreach (['', '-sin-legal'] as $variante) {
            $lote[] = [
                'nombre' => "{$nombre}-".strtok($ventana, 'x').$variante,
                'a' => "{$base}/a/{$nombre}.html",
                'b' => "{$base}/b/{$nombre}{$variante}.html",
                'viewport' => $ventana,
                'completa' => false,
                'clics' => [],
            ];
        }
    }
}

// ── LA AUTORIZACIÓN (T3): el formulario en reposo (`recibo`) y el Listo (`firmada`), con los datos del diseño ──────────
// B lleva lo que el producto pide y el brief no (`#745`: nacimiento y relación) y el descargo en el flujo; el par de
// DIAGNÓSTICO (`$m['diagnostico']`) los omite y tiene que dar 0: así el par real solo puede diferir en eso.
$cardAut = (string) file_get_contents($diseno.'/paginas/autorizacion.card.html');
foreach (['autorizacion-recibo' => 'recibo', 'autorizacion-firmada' => 'firmada'] as $nombre => $estado) {
    if ($solo !== [] && ! in_array($nombre, $solo, true)) {
        continue;
    }
    $a = str_replace(
        ['"../styles.css"', '"invitacion/datos.js"', '"autorizacion/datos.js"', '"invitacion/invitacion.css"', '"invitacion/vistas.jsx"', '"../components/', '"../_ds_bundle.js"'],
        ['"../diseno/styles.css"', '"../diseno/paginas/invitacion/datos.js"', '"../diseno/paginas/autorizacion/datos.js"', '"../diseno/paginas/invitacion/invitacion.css"', '"../diseno/paginas/invitacion/vistas.jsx"', '"../diseno/components/', '"../diseno/_ds_bundle.js"'],
        $cardAut,
    );
    $montaje = 'localStorage.setItem("pj-invitacion-idioma", JSON.stringify("es")); '
        .'window.invCargar(FUENTES).then(() => ReactDOM.createRoot(document.getElementById("root")).render(<AutPagina estado="'.$estado.'" tema="confeti" />));';
    $a = (string) preg_replace('/window\.invCargar\(FUENTES\)\.then\(\(\) => ReactDOM\.createRoot\(document\.getElementById\("root"\)\)\.render\(<AutBanco \/>\)\);/', $montaje, $a, 1, $n);
    if ($n !== 1) {
        fwrite(STDERR, "la ficha de la autorización cambió: no encuentro su montaje\n");
        exit(1);
    }
    file_put_contents($salida."/a/{$nombre}.html", $a);

    foreach (['' => false, '-diagnostico' => true] as $variante => $diagnostico) {
        $b = view('fiesta.autorizacion', ['m' => $modelos['autorizacion']($estado, $diagnostico), 'hojas' => $hojas])->render();
        $b = str_replace(rtrim((string) config('app.url'), '/').'/', '../', $b);
        file_put_contents($salida."/b/{$nombre}{$variante}.html", $b);
        foreach (['390x844', '1280x900'] as $ventana) {
            $lote[] = [
                'nombre' => "{$nombre}-".strtok($ventana, 'x').$variante,
                'a' => "{$base}/a/{$nombre}.html",
                'b' => "{$base}/b/{$nombre}{$variante}.html",
                'viewport' => $ventana,
                'completa' => false,
                'clics' => [],
            ];
        }
    }
}

// ── LA LISTA (T1b): la primera pantalla (`recien`) y la lista completa y guardada (`guardado`), a página ENTERA ────────
// A es `paginas/lista-invitados.card.html` tal cual, montando `PliPagina` con el `id` del estado (sin la barra de
// pruebas y con el `localStorage` del estado limpio antes de montar); B es `fiesta.lista` con `modelos.php → lista`.
// El `guardado` del diseño se monta AJUSTADO a lo que HAY (spec §1.4, y lo mismo en el modelo B): sin los «no» (k7, k8:
// «Al final viene» FALTA), con las edades a 7 (la nota de las edades FALTA), sin palabras ni pistas, sin fecha de
// guardado. El par de DIAGNÓSTICO esconde en los dos lados lo que el producto no tiene: la fila de quien cumple (A), la
// zona 4 entera (la tarta y los padres, A y B) y la línea de privacidad bajo la barra (B). Tiene que dar 0. Las páginas
// REALES de `guardado` se escriben (para el ojo: `a/lista-guardado.html`, `b/lista-guardado.html`) pero no entran en el
// lote: no miden lo mismo (A 1280×4040, B 1280×2932: la tarta y los padres) y el juez no puede dar un número.
$cardLista = (string) file_get_contents($diseno.'/paginas/lista-invitados.card.html');
$ajusteGuardado = 'const E = window.PLI.ESTADOS.guardado; E.guardadoEn = null; E.form.invitacion.palabras = ""; E.form.invitacion.pistas = ""; '
    .'E.form.ninos = E.form.ninos.filter((x) => x.respuesta !== "no").map((x) => Object.assign({}, x, { edad: x.edad === "8" ? "7" : x.edad })); ';
$esconderA = '<style>section[data-zona="2"] > ul.pli-ul:first-of-type, [data-zona="4"] { display: none; }</style>';
$esconderB = '<style>[data-zona="4"], [data-zona="5"] > .pli-sub { display: none; }</style>';
foreach (['lista-recien' => 'recien', 'lista-guardado' => 'guardado'] as $nombre => $estado) {
    if ($solo !== [] && ! in_array($nombre, $solo, true)) {
        continue;
    }
    $a = str_replace(
        ['"../styles.css"', '"lista-invitados/datos.js"', '"lista-invitados/estado.jsx"', '"lista-invitados/zonas-1-2.jsx"', '"lista-invitados/zonas-3-5.jsx"', '"../_ds_bundle.js"'],
        ['"../diseno/styles.css"', '"../diseno/paginas/lista-invitados/datos.js"', '"../diseno/paginas/lista-invitados/estado.jsx"', '"../diseno/paginas/lista-invitados/zonas-1-2.jsx"', '"../diseno/paginas/lista-invitados/zonas-3-5.jsx"', '"../diseno/_ds_bundle.js"'],
        $cardLista,
    );
    $montaje = '["pj-lista-v3-servidor-'.$estado.'", "pj-lista-v3-borrador-'.$estado.'"].forEach((k) => localStorage.removeItem(k)); '
        .($estado === 'guardado' ? $ajusteGuardado : '')
        .'ReactDOM.createRoot(document.getElementById("root")).render(<PliPagina id="'.$estado.'" conflictoRef={{ current: false }} onConflictoUsado={() => {}} pruebaRef={{ current: null }} onPrueba={() => {}} />);';
    $a = str_replace('ReactDOM.createRoot(document.getElementById("root")).render(<PliBanco />);', $montaje, $a, $n);
    if ($n !== 1) {
        fwrite(STDERR, "la ficha de la lista cambió: no encuentro su montaje\n");
        exit(1);
    }
    file_put_contents($salida."/a/{$nombre}.html", $a);

    $b = view('fiesta.lista', ['m' => $modelos['lista']($estado), 'hojas' => $hojas])->render();
    $b = str_replace(rtrim((string) config('app.url'), '/').'/', '../', $b);
    file_put_contents($salida."/b/{$nombre}.html", $b);
    $variantes = [''];
    if ($estado === 'guardado') {
        file_put_contents($salida."/a/{$nombre}-diagnostico.html", str_replace('</head>', $esconderA.'</head>', $a));
        file_put_contents($salida."/b/{$nombre}-diagnostico.html", str_replace('</head>', $esconderB.'</head>', $b));
        $variantes = ['-diagnostico'];
    }
    foreach (['390x844', '1280x900'] as $ventana) {
        foreach ($variantes as $variante) {
            $lote[] = [
                'nombre' => "{$nombre}-".strtok($ventana, 'x').$variante,
                'a' => "{$base}/a/{$nombre}{$variante}.html",
                'b' => "{$base}/b/{$nombre}{$variante}.html",
                'viewport' => $ventana,
                'completa' => true,
                'clics' => [],
            ];
        }
    }
}

file_put_contents($salida.'/lote.json', json_encode($lote, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo '✓ '.count($lote).' pares en '.$salida."/lote.json\n";
