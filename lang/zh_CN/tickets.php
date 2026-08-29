<?php

// Panel admin (zh_CN). El grueso de `tickets.*` pertenece al flujo de compra
// PÚBLICO, que solo se sirve en ES/EN/FR (la web pública no tiene zh_CN); por
// eso aquí solo se traduce la única clave de este namespace que SÍ se renderiza
// dentro del panel: `guests_count` (meta del calendario + resúmenes de pedido).
// Sin esta clave, un admin con el panel en chino la vería en inglés por el
// fallback a `en`. Redacción coherente con `admin.create_manual.field_guests`
// ("位宾客"). Limpieza de paridad i18n — Fase 7.4 iter2.
return [
    'guests_count' => ':count 位宾客',
    'mixed_party_badge' => '混龄',
    'mixed_party_product_name' => ':name · :badge',
    'gate_mixed_party_line' => ':count 位其他年龄段来宾的补差价|:count 位其他年龄段来宾的补差价',
    'gate_mixed_party_line_named' => ':count 位来宾应属于 :target 的补差价|:count 位来宾应属于 :target 的补差价',
    'time_no_limit' => '无限制',

    // Complementos (#190): el view-model compartido `AddonResolver::viewModel` se reutiliza en el
    // panel (crear pedido), así que estas claves SÍ se renderizan en chino.
    'addon_choose_one' => '请选择一项:',
    'addon_badge_included' => '已包含',
    'addon_badge_free' => '免费',
    'addon_more_info' => '更多信息',
    'addon_included' => '已包含',
    'addon_included_partial' => ':count 份免费',
    'addon_included_extra' => '已包含 · 加购 :price/份',
    'addon_per_unit' => ':price/每位',
    'addon_per_guest_qty' => ':count(每位一份)',
];
