/*
 * Lado A del banco de la compra: la VISTA del hook del diseño (`paginas/compra/compra.jsx`, `usePjcCompra`, de
 * «const enCuando» a «const checkout»), TRANSCRITA con los mismos nombres y el mismo orden. Solo cambia de dónde
 * lee: del estado normalizado (`estado.js`) en lugar de sus `useState`; y las acciones no hacen nada. Si el
 * diseño cambia ese bloque, se vuelve a transcribir comparando línea con línea.
 */
function bancoCompraVista(S) {
  const D = window.PJC, T = D.T;
  const nada = () => {};
  const { paso, vista, borrador, ent, pd, q, hora, f, err, llena, horaNueva, hold, ocupado, insta, hecho, motivo, desdeCuando, ofertas, principal, wallets, whatsapp, manual } = S;

  const enCuando = paso === "cuando";
  const pdCuando = enCuando && borrador && borrador.zona && borrador.modo === "nuevo" && (borrador.zona !== "cumple" || (borrador.edad != null && borrador.dia)) ? D.pedidoDe(borrador, ofertas) : null;
  const qCuando = pdCuando ? { lineas: pdCuando.lineas ? Object.fromEntries(pdCuando.lineas.map((l) => [l.id, l.n])) : {}, calcetines: borrador.cal || 0, ninos: borrador.n } : null;
  const P = pdCuando || pd, Q = qCuando || q;
  const c = P ? D.cuenta(P, Q) : null;
  const importe = c ? D.eur(c.importe) : "";
  const primero = principal === "bizum" ? "bizum" : "tarjeta";
  const segundo = primero === "bizum" ? "tarjeta" : "bizum";

  let action = null, after = null, body = null, step = null, progress = null, onBack = null, conResumen = true, note = null;
  const pasoN = (n, nombre) => { step = <React.Fragment><b style={{ color: "var(--snow)", fontWeight: 700 }}>{"Paso " + n + " de 2"}</b>{" · " + nombre}</React.Fragment>; progress = [n, 2]; };

  if (enCuando) {
    step = T.cuando.banda;
    const listoC = borrador.modo === "otra" ? Boolean(borrador.zona) : borrador.zona === "cumple" ? Boolean(borrador.edad != null && borrador.dia && borrador.hora) : Boolean(borrador.zona && borrador.hora);
    action = { label: T.cuando.boton, onClick: nada, disabled: !listoC };
    body = <window.PjcCuando c={borrador} setC={nada} ofertas={ofertas} />;
    if (borrador.modo === "otra") onBack = nada;
    else if (borrador.desde === "selector") onBack = nada;
    conResumen = Boolean(P);
  } else if (paso === "datos") {
    pasoN(1, T.datos.banda);
    action = llena ? { label: T.datos.elegir, onClick: nada, disabled: !horaNueva } : { label: T.datos.boton, onClick: nada, loading: ocupado === "datos" ? T.propuesta.cargando.datos : false };
    body = vista === "descargo"
      ? <window.PjcDescargo />
      : vista === "entrar"
        ? <window.PjcEntrar ent={ent} setEnt={nada} insta={insta} onProveedor={nada} />
        : <window.PjcDatos pd={pd} q={q} f={f} setF={nada} err={err} llena={llena} horaNueva={horaNueva} setHoraNueva={nada} insta={insta}
            buscar={nada} onProveedor={nada} onDescargo={nada} onEntrar={nada} />;
    if (vista === "descargo") { action = null; onBack = nada; }
    else if (vista === "entrar") {
      action = ent.paso === "id" ? { label: T.entrar.continuar, onClick: nada, disabled: !ent.valor.trim() || !ent.pass, loading: ocupado === "entrar" ? T.propuesta.cargando.entrar : false } : null;
      onBack = nada;
    } else if (desdeCuando) onBack = nada;
  } else if (paso === "pagar") {
    pasoN(2, T.pagar.banda);
    note = T.pagar.retencion(hold);
    action = { label: T.pagar[primero](importe), onClick: nada };
    after = <window.PjcPagoJunto label={T.pagar[segundo](importe)} onPagar={nada} wallets={wallets} />;
    body = <window.PjcPagar pd={pd} q={q} setQ={nada} onOtra={nada} />;
    onBack = nada;
  } else if (paso === "banco") {
    pasoN(2, T.pagar.banda);
    note = T.pagar.retencion(hold);
    action = { label: T.pagar.saliendoBoton, onClick: nada };
    body = <window.PjcSaliendo />;
  } else if (paso === "fallido") {
    pasoN(2, T.pagar.banda);
    action = { label: T.fallido.bizum, onClick: nada };
    after = <window.PjcFallidoJunto onTarjeta={nada} onAyuda={manual ? nada : null} onEscribir={nada} />;
    body = <window.PjcFallido hold={hold} motivo={motivo} />;
  } else if (paso === "verificando") {
    pasoN(2, T.pagar.banda);
    body = <window.PjcVerificando />;
  } else if (paso === "perdida") {
    pasoN(2, T.pagar.banda);
    action = { label: T.perdida.boton, onClick: nada, disabled: !horaNueva };
    body = <window.PjcPerdida cercanas={pd.cercanas || []} horaNueva={horaNueva} setHoraNueva={nada} />;
  } else if (paso === "listo") {
    conResumen = false;
    action = { label: T.listo.miQr, onClick: nada };
    after = <window.SaltiaDesignSystem_33397c.Button variant="quiet" full onClick={nada}>{T.listo.otra}</window.SaltiaDesignSystem_33397c.Button>;
    body = <window.PjcListo pd={pd} q={q} hora={hora} hecho={hecho} whatsapp={whatsapp} />;
  }

  const checkout = {
    key: S.clave,
    dir: S.dir,
    step, progress, onBack, onClose: nada, action, after, note,
    summary: conResumen && P ? D.linea(P, Q, enCuando ? borrador.hora : hora) : null,
    total: conResumen && c ? D.eur(c.total) : null,
    today: conResumen && P && P.tipo === "cumple" ? T.hoyPagas : null
  };
  return { checkout, body };
}
