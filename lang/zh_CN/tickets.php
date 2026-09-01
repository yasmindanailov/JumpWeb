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
    'gate_mixed_party_credit_line' => ':count 位较低价年龄段来宾的折扣|:count 位较低价年龄段来宾的折扣',
    'gate_mixed_party_credit_line_named' => ':count 位来宾应属于 :target 的折扣|:count 位来宾应属于 :target 的折扣',
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

    // El LIBRO del pedido (`specs/desglose-libro.md` §4.3, T2): sus etiquetas las compone el dominio
    // (`Booking\Services\MovementLabel`) con UN diccionario para cliente y panel (D1), así que el
    // panel en chino las necesita aquí — sin ellas caería al inglés, como `gate_mixed_party_line`.
    'journal' => [
        'booking' => '已完成预订',
        'quantity' => '数量：:old → :new',
        'addon_quantity' => ':name：:old → :new',
        'product_change' => '更改为 :name',
        'slot_change' => '日期更改为 :when',
        'price_change' => '当日价格：:old → :new',
        'edit_fallback' => ':product 的变更',
        'cancel' => '已取消：:name · :quantity',
        'courtesy' => '补偿',
        'paid_online' => '已在线支付',
        'paid_desk' => '已在前台支付',
        'refund_card' => '已退回至银行卡',
        'refund_manual' => '已在园区退款（已登记）',
        'refund_pending' => '退款处理中',
        'refund_failed' => '退款失败',
        // T5 · D9: 前台并未登记收款，因此是「结清」而非「已支付」。
        'gate' => '已在园区结清',
        'with_reservation' => ':reservation · :label',
        // T3·1：账簿的标题与余额的标签（每个 `balance.kind` 一条；`settled` 无标签）。
        'movements_title' => '变动明细',
        'settlements_title' => '付款与退款',
        'total' => '合计',
        'paid' => '已付',
        'balance_pay_at_park' => '待在园区支付',
        'balance_refund_at_park' => '待在园区退还',
        'balance_refund_pending' => '待退款',
        'balance_pay_online' => '待在线支付',
        'balance_rest_at_park' => '另有 :amount 在园区支付',
        'email_title' => '您的订单（截至今日）',
        'balance_settled' => '无待处理款项',
        'balance_expired' => '已过期，未付款',
        'balance_under_review' => '金额审核中',
    ],
];
