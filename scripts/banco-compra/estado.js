/*
 * El ESTADO de una pantalla del banco de la compra (`scripts/banco-compra.php`), normalizado IGUAL para los dos
 * lados: lo cargan como guion plano la página A (el diseño) y la B (el producto), después de `compra/datos.js` del
 * diseño, y los dos parten de este objeto. El pedido lo arma `PJC.pedidoDe()` del propio diseño a partir de lo
 * que diría la pantalla 0; las cantidades, como las pone su `abrir()`; el formulario, desde el vacío de su hook.
 * No es código del producto: es el banco.
 */
window.bancoCompraEstado = function (E) {
  var D = window.PJC;
  var ofertas = E.ofertas !== false;
  var pd = null;
  if (E.pedido) {
    pd = D.pedidoDe(E.pedido, ofertas);
    if (E.pedidoExtra) pd = Object.assign({}, pd, E.pedidoExtra);
  }
  var q = pd ? { lineas: pd.lineas ? Object.fromEntries(pd.lineas.map(function (l) { return [l.id, l.n]; })) : {}, calcetines: pd.calcetines || 0, ninos: pd.pack ? pd.pack.n : 0 } : null;
  if (q && E.q) q = Object.assign({}, q, E.q, { lineas: Object.assign({}, q.lineas, (E.q && E.q.lineas) || {}) });
  var f = Object.assign({ nombre: '', correo: '', telefono: '', contrasena: '', descargo: false, firmado: false, cuenta: 'nueva', editar: false, via: null, pedirTel: false }, E.f || {});
  return {
    paso: E.paso,
    vista: E.vista || null,
    borrador: E.borrador || null,
    ent: Object.assign({ paso: 'id', valor: '', pass: '', error: '' }, E.ent || {}),
    pd: pd,
    q: q,
    hora: E.hora || (pd ? pd.hora : null),
    f: f,
    err: E.err || {},
    llena: Boolean(E.llena),
    horaNueva: E.horaNueva || null,
    hold: E.hold || D.guardadaHasta(),
    ocupado: E.ocupado || null,
    insta: Boolean(E.insta),
    hecho: E.hecho || null,
    motivo: E.motivo || D.T.propuesta.motivo,
    desdeCuando: Boolean(E.desdeCuando),
    clave: E.clave || E.paso,
    dir: E.dir || null,
    ofertas: ofertas,
    principal: E.principal === 'bizum' ? 'bizum' : 'tarjeta',
    wallets: E.wallets !== false,
    whatsapp: E.whatsapp !== false,
    manual: E.manual !== false
  };
};
