<?php

return [
    // 面板 wordmark 下方的副标题 (#215)。
    'panel_subtitle' => '控制面板',

    'clusters' => [
        'configuracion' => '设置',
    ],

    // 侧边栏分组 (Plan B · L1)。'operativa'/'sistema' 复用既有术语 (运营/系统);
    // 其余为临时译法，最终文案在 copys 阶段确认。
    'nav_groups' => [
        'operativa' => '运营',
        'programacion' => '排期',
        'catalogo' => '商品与价格',
        'contenido' => '网站内容',
        'sistema' => '系统',
    ],

    'puerta' => [
        'validar' => [
            'nav_group' => '门口',
            'title' => '验证注册',
            'intro' => '请输入客户的电子邮件或电话号码，以确认客户是否已注册并已签署免责声明。',
            'back_to_panel' => '返回管理面板',
            'input_placeholder' => '电子邮件或电话',
            'button' => '验证',
            'button_loading' => '正在验证…',
            'new_search' => '新查询',
            'searched_for' => '查询结果',

            // Resultados (3 estados, decisión #126).
            'registered_with_waiver' => '已注册',
            'waiver_date' => '免责声明已于 :date 签署。',
            'registered_no_waiver' => '已注册，免责声明待签署',
            'registered_no_waiver_cta' => '请将平板交给客户，让客户在跳跃前签署免责声明。',
            'registered' => '已注册的客户',
            'registered_sub' => '在系统中已有账户。',
            'not_registered' => '未注册',

            // Edge cases.
            'invalid_input' => '请输入有效的电子邮件或电话。',
            'rate_limited' => '查询次数过多。请等待一分钟后重试。',
        ],
    ],

    // Calendario unificado (Fase 7.4, decisión #14).
    'calendar' => [
        'nav_label' => '日历',
        'nav_group' => '运营',
        'title' => '日历',
        'filter' => [
            'all' => '全部',
            'entries' => '门票',
            'packs' => '生日派对',
        ],
        'legend' => [
            // 可折叠图例的触发器（客户决定 2026-06-13）：图例移至日历下方，默认折叠，点击此处展开。
            'toggle' => '颜色和图标的含义？',
            'zones' => '区域',
            'packs_note' => '（生日 = 其区域颜色）',
            // P8：日历中按图标区分产品类型。
            'entry' => '门票',
            'pack' => '生日会',
            'finished' => '已结束',
        ],
        // 预订后表单状态 (#217)：卡片徽标的提示与图例。
        'guest_form_ok' => '预订表单已完成',
        'guest_form_pending' => '预订表单待填写',
        'item_modal' => [
            'heading' => '产品详情',
            'close' => '关闭',
            'not_found' => '未找到该产品。',
            'field_date' => '日期',
            'field_time' => '时段',
            'field_duration' => '时长',
            'field_quantity' => '数量',
            'section_event_data' => '活动信息',
            'section_guests' => '预订表单',
            'guests_hint' => '打印预订单或打开订单以查看表单内容。',
            'guests_hint_empty' => '客户尚未填写预订表单。',
            // 「查看表单」：预订表单填写完成时，在模态框中展开各儿童的数据。
            'guest_form_show' => '查看表单',
            'guest_form_hide' => '隐藏表单',
            'section_customer' => '客户信息',
            'field_customer_name' => '姓名',
            'field_customer_email' => '电子邮件',
            'field_customer_phone' => '电话',
            'print_slip' => '打印预订单',
            'view_order' => '查看订单 :code',
        ],

        // Resumen del día (PDF #184). NOTA: el PDF se renderiza SIEMPRE en español
        // (lo fuerza el controlador); en el panel solo se muestran btn/modal_*/
        // date_*/type_*. El resto existe por paridad.
        'day_summary' => [
            'btn' => '打印当日汇总',
            'modal_heading' => '打印当日汇总',
            'submit' => '打印',
            'date_label' => '日期',
            'type_label' => '包含内容',
            'type_all' => '全部',
            'type_packs' => '仅生日会',
            'type_entries' => '仅门票',
            'title' => '当日汇总',
            'count_reservations' => '{0}无预订|{1}:count 个预订|[2,*]:count 个预订',
            'total_guests' => ':count 位宾客',
            'total_entries' => ':count 张门票',
            'col_time' => '时间',
            'col_type' => '类型',
            'col_product' => '产品',
            'col_customer' => '客户',
            'col_phone' => '电话',
            'col_qty' => '数量',
            'col_celebrant' => '寿星',
            'type_pack' => '生日会',
            'type_entry' => '门票',
            'empty' => '当日没有预订。',
            'printed_at' => '打印于 :when',
        ],
    ],

    // Dashboard del panel (Fase 7.4 iter2, decisión #14): widgets operativos +
    // filtro de periodo compartido (hoy / esta semana / este mes).
    'dashboard' => [
        'period' => [
            'label' => '时间范围',
            'today' => '今天',
            'week' => '本周',
            'month' => '本月',
        ],
        'stats' => [
            'reservations' => '预订',
            'occupancy' => '占用',
            'occupancy_value' => ':count 个名额',
        ],
        'reservations' => [
            'heading' => '预订',
            'empty' => '该时间范围内没有预订。',
            'col_time' => '时间',
            'col_product' => '产品',
            'col_customer' => '客户',
            'col_quantity' => '数量',
            'col_status' => '状态',
            'col_form' => '表单',
        ],
        'status' => [
            'active' => '进行中',
            'finished' => '已结束',
        ],
        'form_status' => [
            'ok' => '已提交',
            'pending' => '待填写',
        ],
    ],

    // Pedidos (Fase 7.1b, decisión #127).
    'orders' => [
        'nav_label' => '订单',
        'nav_group' => '运营',
        'model_label_singular' => '订单',
        'model_label_plural' => '订单',

        // 后台手动下单（阶段 7.3，#120）。
        'create_manual' => [
            'nav_label' => '创建订单',
            'title' => '手动创建订单',
            'step_customer' => '客户',
            'step_products' => '产品',
            'step_payment' => '付款',
            'customer' => '客户',
            'customer_search_placeholder' => '按邮箱、电话或姓名搜索',
            'customer_help' => '选择此订单的客户。如果有邮箱，将收到确认邮件。',
            'customer_no_email' => '（无邮箱）',
            'register_cta' => '没有账户？为客户注册',
            'register_heading' => '注册新客户',
            'register_description' => '立即为客户创建账户。邮箱为可选项：若填写，客户将收到含临时密码的邮件以查看订单。若不填，预订将以电话保存在后台（不发送邮件）。',
            'register_name' => '客户姓名',
            'register_email' => '客户邮箱',
            'register_email_optional' => '可选。如果没有，请留空：预订将以电话保存在系统中（不发送邮件，表单链接可从产品图标复制）。',
            'register_phone' => '客户电话',
            'register_privacy' => '我已向客户说明隐私政策，并经其同意创建账户。',
            'register_privacy_required' => '你必须确认已向客户说明隐私政策。',
            'register_submit' => '创建账户',
            'register_done' => '已为 :email 创建账户并选中。我们已通过邮件发送其密码。',
            'register_no_email_done' => '已创建无邮箱客户「:name」并选中。该客户不会收到邮件；如果是生日派对，请从产品图标通过 WhatsApp 分享表单链接。',
            'register_exists' => '该邮箱已有账户：已为你选中（未发送任何邮件）。',
            'register_throttled' => '尝试次数过多，请稍后再试。',
            'register_failed' => '账户创建失败，请稍后重试。',
            'register_email_failed' => '账户已创建，但未能发送含密码的欢迎邮件。请在邮件恢复后通知客户或重置其密码。',
            'register_phone_required' => '电话为必填项：没有邮箱时它是客户的标识。',
            // 按电话查重提示（无邮箱注册）：「提醒并让其选择」。
            'phone_match_title' => '已存在使用该电话的客户',
            'phone_match_help' => '找到使用电话 :phone 的客户。要使用现有客户还是新建一个？',
            'phone_match_use' => '使用 :name',
            'phone_match_create_new' => '仍然新建客户',
            'phone_match_dismiss' => '取消',
            'phone_match_used' => '已选择现有客户。',
            'add_product' => '添加产品',
            'product' => '产品',
            'date' => '日期',
            'time' => '时间段',
            'time_help' => '仅显示有空位的时间段。',
            'no_times_for_date' => '该日期没有可用时间段。请重新生成或开放时间段，或选择其他日期。',
            'quantity' => '数量',
            'guests' => '人数',
            'qty_out_of_range' => '数量必须在 :min 到 :max 之间。',
            'seats' => ':n 个空位',
            'addons' => '附加项',
            'addon' => '附加项',
            'addon_qty' => '数量',
            'add_addon' => '添加附加项',
            'add_to_cart' => '加入订单',
            'line_added' => '产品已加入订单。',
            'line_incomplete' => '请先填写产品、日期、时间段和数量。',
            'cart_title' => '订单摘要',
            'cart_empty_hint' => '尚未添加任何产品。',
            'cart_total' => '合计',
            // P5（#225，展示）：手动建单购物车中的定金提示。
            'deposit_line_note' => '定金：:amount € · 余款在园区',
            'pay_now_deposit' => '现在收取',
            'pay_at_park' => '在园区收取',
            'deposit_hint' => '含定金的产品现在只收取定金；余款在活动当天于园区收取。',
            'remove_line' => '移除',
            'payment_method' => '付款方式',
            'method_cash' => '现金',
            'method_datafono' => '刷卡机',
            'back' => '上一步',
            'next' => '下一步',
            'confirm' => '确认收款并创建订单?',
            'confirm_description' => '将登记收款并为该客户创建预订。',
            'submit' => '收款并创建订单',
            'no_customer' => '请选择客户。',
            'cart_empty' => '请至少添加一个产品。',
            'invalid_method' => '付款方式无效。',
            'reservation_failed' => '无法创建订单。',
            'created' => '订单 :code 已创建并收款。',
        ],

        // Columnas.
        'col_code' => '编号',
        'col_customer' => '客户',
        'col_status' => '状态',
        'col_operative' => '运营状态',
        // P3：详情页 H1 两个状态徽章的悬停提示。
        'status_badge_tooltip' => '订单状态：已付款、待付款、已取消或已退款。',
        'operative_badge_tooltip' => '相对于活动的状态：尚未开始、进行中或已结束。',
        // P1/P10：摘要中尚未收款时的状态。已收款的方式标签（线上付款 / 现金或POS收款）在 order_financial 中按方式区分。
        'not_paid_yet' => '待付款',
        'col_total' => '总额',
        'col_paid_at' => '付款时间',
        'col_created_at' => '创建时间',
        'col_expires_at' => '过期时间',
        'col_refunded' => '退款',
        // #179: columna "Pagado" + icono calendario.
        'col_collected' => '已收',
        'btn_view_in_calendar' => '在日历中查看',
        'filter_status' => '状态',
        // 退款筛选 (#146)。
        'filter_refunded' => '退款情况',
        'filter_refunded_all' => '全部',
        'filter_refunded_yes' => '仅显示已退款',
        'filter_refunded_no' => '仅显示未退款',
        // 订单详情和列表中的副徽标 (#146)。
        'refunded_badge' => '已退款',

        // #145: 订单状态 "Pagado" → "已完成"（与 #143 一致：订单可同时有退款）。
        // 实际付款的状态（在「付款记录」中显示）保留为「已付款」。
        'status' => [
            'pending' => '待付款',
            'paid' => '已完成',
            'cancelled' => '已取消',
            'refunded' => '已退款',
            'expired' => '已过期',
        ],

        'operative' => [
            'active' => '进行中',
            'in_progress' => '部分结束',
            'finished' => '已结束',
        ],

        'heading_id_label' => 'ID',  // 编号前的紧凑标签 (#136).
        'heading_pedido' => '订单',  // H1 + 浏览器标签 (#132).
        'section_summary' => '概要',
        'section_items' => '订单产品',
        'no_items' => '此订单没有产品。',

        'item_status' => [
            'finished' => '已结束',
            'cancelled' => '已取消',
        ],

        // Hoja de reserva imprimible (PDF A4, decisión #183). NOTA: el PDF se
        // renderiza SIEMPRE en español (lo fuerza el controlador); estas claves
        // existen por paridad y solo `btn_print` se muestra en el panel (idioma
        // del staff). Las demás no llegan a renderizarse en zh_CN.
        'slip' => [
            'btn_print' => '打印预订单',
            'print_operational' => '场地单(不含价格)',
            'print_with_prices' => '含价格与明细',
            'title' => '预订单',
            'order_ref' => '订单',
            'created_at' => '创建于 :when',
            'printed_at' => '打印于 :when',
            'datetime_heading' => '日期和时间',
            'duration_heading' => '时长',
            'guests_heading' => '宾客',
            'entries_heading' => '门票',
            'entries_count' => '{1}:count 张门票|[2,*]:count 张门票',
            'no_slot' => '未分配时段',
            'addons_heading' => '附加项',
            'reservation_data_heading' => '预订信息',
            'guests_heading' => '预订表单',
            'guests_pending' => '待办：客户尚未填写表单。请手动补充。',
            'guardian_label' => '父母或法定监护人',
            'client_label' => '客户姓名',
            'prepared_check' => '已准备',
            'pending_at_gate' => '到场支付',
            // #225：定金余款（未在线收取的部分）。
            'deposit_remainder_line' => '定金余款',
            // #200: 待退还款项的醒目方框(与「到场支付」对称)。
            'pending_refund' => '待退还',
            'pending_refund_caption' => '因减少数量或取消而多付的款项,尚待退还给客户。',
            'cancelled_notice' => '预订已取消',
        ],

        // Datos del evento por item (#86, sub-fase 7.2a).
        'event_data_section' => '活动信息',
        'party_data_section' => '预订表单',
        'show_more' => '查看更多',
        'show_less' => '收起',
        // #225 F3:「在门店收取」明细 ↳ 的开关(默认折叠)。
        'show_breakdown' => '查看明细',
        'hide_breakdown' => '收起明细',
        // #225:标注「定金余款」所属产品的连接词。
        'deposit_for_product' => '（:product）',
        'guest_badge_ok' => '已完成',
        'guest_badge_pending' => '待填写',
        'guests_empty' => '客户尚未填写预订表单。',

        // 「详情」卡 (#134): 默认折叠，操作员需要时再展开。
        'section_details' => '详情',
        'details_order' => '订单详情',
        'details_customer' => '客户详情',

        'customer_name' => '姓名',
        'customer_email' => '电子邮件',
        'customer_phone' => '电话',
        'customer_locale' => '联系语言',
        'customer_locale_value' => [
            'es' => '西班牙语',
            'en' => '英语',
            'fr' => '法语',
        ],
        'customer_waiver' => '免责声明',
        'customer_waiver_missing' => '未签署',
        'customer_waiver_outdated' => '旧版本',

        // 订单总额 (#131): 显示在「概要」卡片内，不再独立成卡片。
        'amount_total' => '总额',
        // 财务汇总 (#144): 仅当订单有退款记录时显示在「概要」卡片中。
        'amount_refunded' => '已退款',

        // Sección de pagos (sub-fase 7.2a).
        'section_payments' => '付款记录',
        'payments' => [
            'portal_intro' => '如需与银行手动对账：打开 Redsys 门户并按订单号搜索。',
            'portal_link' => '打开 Redsys 门户 (:env)',
            'env_test' => '测试环境',
            'env_live' => '生产环境',

            'empty' => '尚无付款尝试记录。',
            'attempt_at' => ':when 的尝试',

            // 7.2e.1bis (决议 #154): 「支付」卡片分为 2 个子区域,
            // 区分真实银行卡上的资金变动与仅内部记录的退款。
            'section_bank' => '📋 在客户银行(Redsys)',
            'section_bank_help' => '已实际在客户卡上产生资金变动的收款和退款。',
            'section_internal' => '📝 内部记录',
            'section_internal_help' => 'Redsys 之外的款项:在门店的手动收款(现金或刷卡机),以及通过其他渠道(银行后台、现金等)处理的退款。',

            'status' => [
                'pending' => '待处理',
                'authorized' => '已授权',
                'paid' => '已付款',
                'failed' => '已拒绝',
                'refunded' => '已退款',
            ],

            'provider_redsys' => 'Redsys (银行卡)',
            'provider_cash' => '现金',
            'provider_datafono' => '实体 POS',

            'gateway_order' => 'Redsys 订单号',
            'auth_code' => '银行授权码',
            'ds_response' => '付款结果',
            'ds_response_authorized' => '已授权',
            'bank_timestamp' => '付款日期和时间',
            'card_brand' => '银行卡',
            'card_brand_unknown' => '品牌 :code',
            'card_country_unknown' => '国家 :code',
            'paid_at' => '确认时间',

            'copy' => '复制',
            'copied' => '已复制',

            // Redsys 事件时间线 (#144)：每张子卡顶部都有类型徽标，区分「付款」和「退款」。
            'events' => [
                'type_payment' => '付款',
                'type_refund' => '退款',
            ],

            // 退款 — 时间线中的事件 (#144)。
            'refunds' => [
                'status' => [
                    'pending' => '处理中',
                    'succeeded' => '已确认',
                    'failed' => '失败',
                ],

                'mode_rest' => '自动 (Redsys)',
                'mode_manual' => '手动登记',

                // 7.2e.1bis5(#158,反馈点 6):每条退款子卡片的首行展示
                // 已退款产品或附加项的名称,便于操作员核对真实物品。
                'subject_label' => '退款产品',
                'subject_full_order' => '整个订单',
                'subject_item_missing' => '— (产品已从目录中移除)',

                'auth_code' => '银行授权码',
                'result_label' => '结果',
                'code_refund_ok' => '退款成功',
                'result_manual' => '已在系统外退款，仅登记。',

                'failure_label' => '失败原因',
                'failure_reason' => [
                    'transport_error_check_portal' => '无法与银行通讯。',
                    'gateway_denied' => '银行未授权此次退款。',
                    'unknown' => '银行响应无法解析。',
                ],
                'transport_hint' => '在再次从面板尝试前，请到银行门户确认退款是否已被处理。',

                'processed_at_label' => '处理时间',
            ],
        ],

        // 订单管理操作 (子阶段 7.2b, 决定 #138)。取消 / 退款 / 重发确认邮件，
        // 从详情页的头部按钮调用。C1 处理延后至按项管理子阶段。
        'actions' => [
            // P11：分组订单操作的铅笔图标的标签/提示。
            'group_label' => '订单操作',
            'cancel' => [
                'label' => '取消订单',
                'modal_heading' => '确定要取消此订单吗？',
                'modal_description_paid' => '订单将变为「已取消」状态，客户将收到邮件通知。此操作不会自动退款：如需退款，请单独使用「退款」操作。',
                'modal_description_pending' => '订单将变为「已取消」状态，名额将释放回库存。客户将收到邮件通知。',
                'submit' => '是的，取消',
                'success' => '订单已取消。已向客户发送通知。',
                'blocked' => '无法取消此订单：:reason。',
            ],
            'refund' => [
                'label' => '退款',
                // #143：面向员工的简洁文案，去掉技术术语。
                'modal_heading' => '确定为此订单退款？',
                'modal_description' => '我们将款项退还给客户，并在确认后以邮件通知客户。',
                'modal_description_finished_service' => '客户已享受过服务（所有产品已结束）。本操作仅将款项退还给客户；订单保持「已付款」并标注退款。',
                'submit' => '退款',

                'mode_label' => '如何处理？',
                'mode_rest' => '立即退款（推荐）',
                'mode_rest_desc' => '将款项退到客户的银行卡。银行确认后更新订单并发送邮件。若出现问题，订单保持不变，并会告知具体原因。',
                'mode_manual' => '仅登记（已在外部退款）',
                'mode_manual_desc' => '仅在已经通过其他途径完成退款时使用（例如已在银行门户处理）。这里只是登记并通知客户。',

                'also_cancel' => '同时取消订单',
                'also_cancel_help' => '勾选则同时取消订单。若与客户约定保留服务（例如：现场换取实体票），请取消勾选。',

                'success_with_cancel_rest' => '✓ 退款已确认，订单已取消。客户已收到邮件。',
                'success_only_rest' => '✓ 退款已确认。客户已收到邮件。',
                'success_with_cancel_manual' => '✓ 退款已登记，订单已取消。客户已收到邮件。',
                'success_only_manual' => '✓ 退款已登记。客户已收到邮件。',

                'blocked' => '无法为此订单退款：:reason。',

                'failed_title' => '退款未能完成',
                'transport_error' => '目前无法与银行通讯。订单未做任何更改。重要：银行可能已经在自己一侧处理了退款——请先到银行门户核实，再决定是否重试。如果门户中已有该退款记录，请返回这里并选择「仅登记」。',
                'gateway_denied' => '银行未能完成退款（代码：:code）。请到银行门户查看具体原因。订单未做任何更改。',
                'inflight_title' => '已有正在处理的退款',
                'inflight_body' => '此付款的另一次退款仍在处理中。请等待几秒并查看历史，再决定是否重试。',
                'no_paid_payment' => '未找到此订单的原始付款记录，无法从这里退款。',
            ],
            // 统一的「重发邮件」(#140)：一个 action + 类型选择。只显示已发生事件的类型。
            'resend' => [
                'label' => '重发邮件',
                'modal_heading' => '要重发哪封邮件给客户？',
                'modal_description' => '将向客户 (:email) 重新发送所选邮件，与第一次收到的版本相同。仅显示订单中已发生事件对应的邮件类型。每次发送都会记录到历史中。',
                'type_label' => '邮件类型',
                'submit' => '重发',
                'success' => ':type 已重发至 :email。',
                'blocked' => '所选邮件类型与订单当前状态不符。请关闭弹窗后重新打开。',
                'types' => [
                    'confirmation' => '购买确认邮件',
                    'refund' => '退款通知邮件',
                    'cancellation' => '取消通知邮件',
                    'payment_retry' => '完成付款提醒邮件',
                    'guest_form' => '预订表单链接',
                ],
            ],
            'reasons' => [
                'already_cancelled' => '订单已取消',
                'already_refunded' => '订单已退款',
                'expired' => '订单未付款已过期',
                'not_paid' => '订单未付款',
                'already_finished' => '订单的所有产品均已结束（服务已完成）',
            ],
        ],

        // 子阶段 7.2c — 单个 OrderItem 的详情弹窗。点击「查看/编辑」按钮打开。
        // 两个标签页：「详情」(只读数据 + 套餐的 event_data 编辑) 和「历史」
        // (本产品最近 N 条审计记录 + 跳转完整订单历史链接，由 7.2d 提供)。
        'item_detail' => [
            'btn_open' => '管理',
            'btn_aria' => '管理 :name',
            'modal_heading' => '产品详情',
            'modal_close' => '关闭',

            'tab_details' => '详情',
            'tab_history' => '历史',
            'tab_event_data' => '活动数据',
            'unit_entries' => '张门票',
            // F13: 与 es 平价 (lang/es/admin.php 'product_amount')
            'product_amount' => '产品金额',

            'details_heading' => '产品信息',
            'details_ticket_type' => '类型',
            'details_zone' => '区域',
            'details_duration' => '时长',
            'duration' => [
                'hours_only' => ':hours 小时',
                'minutes_only' => ':minutes 分钟',
                'hours_and_minutes' => ':hours 小时 :minutes 分钟',
            ],
            'details_slot' => '时段',
            'details_slot_capacity' => '已预订 :booked / 共 :total 位',
            'details_quantity' => '数量',
            'details_unit_price' => '单价',
            'details_subtotal' => '小计',
            'details_seats' => '占用位数',
            'details_parent' => '附加于',
            'details_children' => '附加项',
            'details_no_slot' => '未分配时段',
            'details_addon_inherits' => '该附加项的准备状态与主产品同步。',

            'event_data_heading' => '活动数据',
            'event_data_intro' => '客户在预订时填写的数据。如需更正（例如寿星姓名），请在此处编辑。',
            'event_data_save' => '保存修改',
            'event_data_no_permission' => '你没有权限编辑活动数据。',
            'event_data_no_fields' => '该产品没有可编辑的活动数据字段。',
            'event_data_legacy_heading' => '历史字段',
            'event_data_legacy_intro' => '这些字段在预订时已保存，但已不在当前的产品配置中。如需删除请手动操作。',
            'event_data_required_indicator' => '*',
            'event_data_optional_indicator' => '（选填）',

            'flash_saved' => '数据已更新。',
            'flash_no_changes' => '无需保存的修改。',
            'flash_stale' => '其他操作员已在此期间编辑过该产品。请先刷新页面查看最新数据，再重新编辑。',
            'flash_required_missing' => '缺少必填字段：:missing',
            'flash_blocked' => [
                'not_pack' => '该产品不是套餐：没有可编辑的活动数据。',
                'no_event_fields' => '该套餐未配置活动数据字段。',
                'not_found' => '该订单中未找到此产品。',
                'generic' => '无法更新该产品。',
            ],

            'history_heading' => '本产品的最近历史',
            'history_intro' => '本产品最近 :limit 条操作记录。',
            'history_empty' => '本产品暂无任何操作记录。',
            'history_full_link' => '查看订单完整历史 →',
            'history_full_link_pending' => '（订单完整历史 — 将由下一子阶段提供）',
            'history_by' => '操作员：:who',
            'history_by_unknown' => '操作员未知',

            'history_actions' => [
                'order_items' => [
                    'prepared' => '已标记为已准备',
                    'unprepared' => '已标记为未准备',
                    'toggle_blocked' => '准备状态变更被拦截',
                    'event_data_updated' => '已更新活动数据',
                    'event_data_blocked' => '编辑活动数据被拦截',
                ],
            ],

            'history_diff_changed' => '· :label：「:old」→「:new」',
            'history_diff_added' => '· :label 新增：「:new」',
            'history_diff_removed' => '· :label 已删除（原值「:old」）',
            'history_diff_reason' => '· 原因：:reason',
        ],

        'audit_cta' => [
            'title' => '订单历史',
            'description' => '查看此订单及其产品的所有操作记录：状态变更、准备、编辑、退款、邮件重发等。',
            'button' => '查看完整历史',
        ],

        'audit_modal' => [
            'heading' => '订单历史',
            'description' => '此订单及其产品的所有操作记录，按时间倒序排列（最新在上）。',
            'empty' => '此订单暂无任何操作记录。',
            'target_order' => '订单',
            'target_item' => '产品',
            'by' => '操作员：:who',
            'by_unknown' => '操作员未知',
            'context_item' => '订单 :code',
            'related_item' => '产品：:name',
            'showing_of' => '显示 :shown / 共 :total 条',
            'load_more' => '再加载 :count 条',
            'reason' => '原因：:reason',
            'previous_status' => '原状态：:status',
            'previous_prepared_at' => '从 :when 起被标记为已准备',
            'previously_unprepared' => '此前未标记为已准备',
            'refund_mode' => '模式：:mode',
            'refund_amount' => '金额：:amount €',
            'resend_type' => '类型：:type',

            'diff_changed' => '· :label：「:old」→「:new」',
            'diff_added' => '· :label 新增：「:new」',
            'diff_removed' => '· :label 已删除（原值「:old」）',

            'actions' => [
                'orders' => [
                    'cancelled' => '订单已取消',
                    'cancel_blocked' => '取消订单被拦截',
                    'refunded' => '退款已确认',
                    'refund_blocked' => '退款操作被拦截',
                    'refund_failed' => '退款失败',
                    'email_resent' => '邮件已重发',
                    'email_resent_blocked' => '邮件重发被拦截',
                    'processed_after_expiration' => '订单过期后被处理 (C1)',
                    'slip_printed' => '预订单已打印',
                ],
                'order_items' => [
                    'prepared' => '产品已标记为已准备',
                    'unprepared' => '产品已标记为未准备',
                    'toggle_blocked' => '准备状态变更被拦截',
                    'event_data_updated' => '已更新活动数据',
                    'event_data_blocked' => '编辑活动数据被拦截',
                    'item_refunded' => '产品已退款',
                    'item_cancel_blocked' => '产品取消被拦截',
                    'item_refund_blocked' => '产品退款被拦截',
                    'item_refund_failed' => '产品退款失败',
                ],
            ],
        ],

        // #263 —「宾客表单链接」模态框：由每个带表单预订的链接图标打开（产品列表）。显示已签名链接，
        // 便于复制并通过 WhatsApp/短信发送（客户无邮箱时尤其有用）。
        'copy_guest_form' => [
            'btn_aria' => '宾客表单链接',
            'modal_heading' => '宾客表单链接',
            'modal_description' => '复制链接并通过 WhatsApp 或短信发送给客户。无需账户即可打开表单，并在活动后过期。',
            'copy' => '复制',
            'copied' => '已复制！',
            'hint' => '点击「复制」（或选中链接）并粘贴到给客户的消息中。',
            'close' => '关闭',
        ],

        // 7.2e.2 — 「管理产品」模态框(决议 #159)。
        'manage_item' => [
            'modal_heading' => '管理产品',
            'save' => '保存更改',
            // #171/#172：退款与取消按钮位于管理弹窗底部。
            'refund_button' => '退款',
            'cancel_button' => '取消产品',

            // #173：标签重构 —「预约」(日期+时段)+「编辑产品」(产品 与 附加项目 两节)。
            'tab_reservation' => '预约',
            'tab_edit_product' => '编辑产品',
            'section_product' => '产品',
            'section_addons' => '附加项目',
            'tab_product' => '产品与预约',
            'tab_event_data' => '活动详情',
            'tab_addons' => '附加项目',

            'field_date' => '日期',
            'field_time' => '时间',
            'current_marker' => '(当前)',
            // Plural rule — debe invocarse con trans_choice (NO __()).
            'seats_available' => '{0}无空位|{1}1 个空位|[2,*]:count 个空位',

            'permission_denied' => '你没有权限编辑此产品。',
            'blocked' => '无法保存更改::reason。',

            'success_slot_changed' => '✓ 产品的日期和时间已更新。已通过邮件通知客户。',

            'read_only_title' => '此产品当前无法编辑。',
            'read_only_intro' => '原因::reason。你可以查看数据,但字段已锁定。',

            // 7.2e.4 (#170):附加项目标签页不可用时的提示。
            'addons_placeholder_title' => '产品不可编辑',
            'addons_placeholder_body' => '该产品目前不可编辑(已结束或已取消,或它是通过主产品管理的附加项目)。',

            // ── 7.2e.4 (#170):附加项目管理(标签页 2) ─────────────
            'addons_intro' => '为此产品添加或移除附加项目。增加的部分在门口收取;移除附加项目后将处于待退款状态(用产品列表中的 ↩ 按钮退款)。',
            'addons_current' => '当前附加项目',
            'group_choice_label' => '套餐',
            'group_choice_hint' => '选择一个选项。保存时将替换当前套餐(差额在门口收取)。',
            'addons_current_empty' => '此产品没有附加项目。',
            'addons_add' => '添加附加项目',
            'addons_add_button' => '添加附加项目',
            'addons_add_none' => '没有可添加的兼容附加项目。',
            'addon_name' => '附加项目',
            'addon_quantity' => '数量',
            'addon_remove_hint' => '将数量设为 0 以移除该附加项目。',
            'addon_locked_hint' => '已包含在产品中:不可移除。如需更换套餐,请在下方添加该组的其他选项。',
            'addon_min_hint' => '已包含:不可低于 :min。增加数量即为加购(收费)。',
            'addon_add_per_guest_hint' => '数量自动:每位宾客一份(不可编辑)。',
            'addon_add_per_guest_qty' => '每位宾客一份 · :count 位宾客。',
            'addon_add_group_hint' => '属于一个选择组:保存时将替换同组中已有的另一个加购项。',
            'addon_add_requires_hint' => '需要「:name」:必须在订单中(请先添加,或在同一批次中添加)。',
            'addons_price_heading' => '附加项目:变更金额',
            'addons_upcharge_extra' => '▲ 将在门口为附加项目额外收取 :amount €。',
            'addons_upcharge_removed' => '▼ 你移除了附加项目:将处于待退款状态(用 ↩ 按钮退款)。',
            'addons_upcharge_none' => '附加项目金额无变化。',

            // 7.2e.2bis6 (#160) — 日历组件。
            'calendar_prev' => '上一月',
            'calendar_next' => '下一月',
            'calendar_back_to_current' => '↩ 回到当前月',
            'calendar_legend_selected' => '已选择',
            'calendar_legend_current' => '当前时段',
            // 7.2e.2bis10 (#164):每日饱和度热图。
            'calendar_legend_sat_high' => '空位充足',
            'calendar_legend_sat_medium' => '空位适中',
            'calendar_legend_sat_low' => '快满',
            'calendar_empty_month' => '当前月份无日历数据。',
            'times_for_day' => ':date 可选时间',
            'times_prev' => '上一时段',
            'times_next' => '下一时段',
            'no_times_available' => '该日无可选时间。',
            // P5：尚未选择日期时的提示（模态右栏）。
            'pick_a_date' => '在日历中选择一个日期以查看时间段。',
            'selection_summary' => '新日期和时间::date · :time',

            // ── 7.2e.3 (#167):修改产品数量 + 产品 ──────
            'field_product' => '产品',
            'field_quantity' => '数量',
            'field_guests' => '宾客人数',
            'field_guests_help' => ':min 到 :max 位宾客。',
            'product_scope_note' => '仅列出同一区域、同一类型的产品。如需更换区域或在门票与套餐之间切换,请使用手动订单(即将推出)。',

            'price_heading' => '产品金额',
            'price_current' => '当前',
            'price_new' => '更新后',
            'price_diff_extra' => '▲ 将在门口额外收取 :amount €。',
            'price_diff_refund' => '▼ 将向客户退款 :amount €。',
            // #225 (D8)：减少数量＝仅取消；不自动退款。
            'price_diff_reduce' => '▼ 将取消移除的数量（−:amount €）。不会自动退款；如需退款请用「退款」。',
            'price_diff_none' => '金额不变。',

            'orphan_addons_warning' => '新产品不支持以下附加项目::list。请在「附加项目」标签页将其数量设为 0,在本次保存中一并移除。',
            'event_data_legacy_warning' => '新产品使用不同的活动详情。保存后请检查「活动详情」标签页。',

            'success_edited' => '✓ 产品已更新。已通过邮件通知客户。',
            'success_edited_extra_due' => '✓ 产品已更新。将在门口收取 :amount €。已通过邮件通知客户。',
            'success_edited_refunded' => '✓ 产品已更新,并已向客户退款 :amount €。已通过邮件通知客户。',
            'success_edited_mixed' => '✓ 产品已更新。将在门口收取 :extra €,并向客户退款 :refund €。已通过邮件通知客户。',
            'success_edited_refund_failed' => '⚠ 产品已更新,但 :amount € 的退款未完成。请使用产品列表中的 ↩ 按钮重试并查看历史记录。',
            // #225 (D8)：减少数量仅取消；如需退款另行处理。
            'success_edited_reduced' => '✓ 产品已更新：已取消相应数量。未自动退款。如需退款，请使用「退款」。已通过邮件通知客户。',
            'success_edited_mixed_reduced' => '✓ 产品已更新。将在门口收取 :extra €；移除的数量已取消且未自动退款（如需退款请用「退款」）。已通过邮件通知客户。',
        ],

        // 7.2e.1bis — 子卡片图标动作(决议 #154)。
        'cancel_item' => [
            'label' => '取消此产品',
            'tooltip' => '取消此产品',
            'modal_heading' => '是否取消订单中的此产品?',
            'modal_description' => '该产品将被标记为已取消并释放席位。**此操作不会退款**:如需退款,请单独使用「退款」按钮。客户将收到取消通知邮件。',
            'submit' => '是,取消产品',
            'success' => '✓ 产品已取消。已通过邮件通知客户。',
            'meta' => ':when 由 :who 取消',

            'blocked' => '无法取消此产品::reason。',

            // 7.2e.1bis5(#158,反馈点 5):取消主产品时级联取消其附加项目,
            // 模态框列出所有附加项以便操作员确认完整取消范围。
            'cascade_intro' => '取消此产品时也会取消其附加项目(:count 个):',
            'cascade_total' => '将取消的总额',
        ],

        'refund_item' => [
            'label' => '退款此产品',
            'tooltip' => '退款此产品',
            'modal_heading' => '是否对此产品退款?',
            'modal_description' => '勾选要退款给客户的产品。每个勾选的产品都会被标记为已取消并全额退款。如果勾选主产品,其附加项目也会自动勾选(退款主产品却保留附加项不合理)。',
            'submit' => '退款所选',

            'mode_label' => '如何处理退款?',
            'mode_rest' => '现在退款(推荐)',
            'mode_rest_desc' => '将款项退至客户银行卡。如果出错,通知你并不修改其余项目。',
            'mode_manual' => '仅记录(已在外部退款)',
            'mode_manual_desc' => '只留下记录(适用于你已在其他渠道完成退款时)。',

            'items_label' => '要退款的产品',
            'items_help' => '只列出仍有待退款金额的产品。已完全退款的产品不会出现。',

            'success_all_rest' => '✓ 已向客户退还 :count 件产品 · 总计 :amount €。已通过邮件通知客户。',
            'success_all_manual' => '✓ 已记录 :count 件产品退款 · 总计 :amount €。已通过邮件通知客户。',
            'success_partial_rest' => '⚠ 已退款 :count_ok 件产品 · 总计 :amount €。:count_failed 件未退款 — 请查看历史记录并稍后重试。',
            'success_partial_manual' => '⚠ 已记录 :count_ok 件产品退款 · 总计 :amount €。:count_failed 件未记录 — 请查看历史记录。',

            'blocked' => '无法对此产品退款::reason。',

            'failed_title' => '退款未完成',
            'transport_error' => '现在无法联系到银行。产品 未 被修改。重要提示:银行可能已经处理了退款 — 请先到银行后台核实再重试。如果在那里看到退款,回到这里选择「仅记录」。',
            'gateway_denied' => '银行无法退款(代码 :code)。请在银行后台查看原因。产品 未 被修改。',
            'inflight_title' => '已有进行中的退款',
            'inflight_body' => '该产品上另一笔退款仍在处理中。请等待几秒钟并查看历史记录后再尝试。',
        ],

        'item_financial' => [
            // 7.2e.1bis5(#158,反馈点 4):标题用以与“订单总计”区分。
            'heading' => '产品总计',
            'principal' => '主产品',
            'addons' => '附加项目',
            'total' => '产品总计',
            // 分项明细的稳健性(#196):产品总计拆分为线上已付 + 门店部分(待收或已收)。
            'paid_online' => '线上已付',
            'at_gate' => '在门店收取',
            // #225 F2:产品卡内「在门店收取」的定金余款明细行。
            'deposit_remainder_line' => '定金余款',
            'collected_at_gate' => '已在门店收取',
            'refunded_label' => '已退款',
            'pending_refund_label' => '待退还',
            'pending_refund_caption' => '客户因该产品的变更（减少数量或取消）多付了款,尚待退还。',

            // 兼容 legacy。
            'refunded' => '↩ 已退款::amount €',
            'pending_refund' => '⚠ 待退款::amount €',
        ],

        // 7.2e.1bis5(#158,反馈点 4):订单总计紧凑分组,位于摘要卡末尾。
        'order_financial' => [
            'heading' => '订单总计',
            // 7.2e.3(润色 #168):因编辑产品导致金额上调、尚需在门店收取的差额。
            'pending_at_gate' => '在门店收取',
            'pending_at_gate_caption' => '客户到场时在前台收取的金额（定金余款及/或因产品变更产生的差额）。',
            // #225：定金余款（未在线收取的部分），作为「在门店收取」的明细行。
            'deposit_remainder_line' => '定金余款',
            // 分项明细的稳健性(#196):尚未处理的应退款项 + 最终净额(= 产品价值)。
            'pendiente_devolucion' => '待退还',
            'pendiente_devolucion_caption' => '客户因订单变更(减少数量或取消)多付了款,尚待退还。',
            // 价值优先重构(2026-06-06):订单区块改为各产品卡片之和,使用相同词汇 →
            // 最终价值 = 线上已付 + 在门店收取 + 已在门店收取。
            'valor_final' => '订单最终价值',
            'pagado_online' => '线上已付',
            // P1/P10：若为现金/POS 手动收款（非网站），不写「线上」。
            'cobrado_manual' => '现金/POS 已收款',
            'pagado_puerta' => '已在门店收取',
            'pendiente_devolucion_caption_web' => '客户通过网站支付了 :total;因减少数量或取消,将退还 :pendiente。',
            // #171:明细子行的紧凑标签。
            'breakdown' => [
                'product_change' => '改为 :name',
            ],
        ],

        'item_actions' => [
            // 7.2e.1bis5(#158,反馈点 7B):订单整体被取消/全额退款时,“订单产品”
            // 卡片顶部展示横幅,提示按产品的取消/退款按钮已不适用。
            'banner' => [
                'order_cancelled' => '此订单已取消。按产品的取消或退款操作已不再适用 — 整个订单作为整体已被取消。',
                'order_fully_refunded' => '此订单已全额退款：已无可退金额。仍可按产品取消（取消 ≠ 退款）。',
            ],

            'reasons' => [
                'not_found' => '找不到此产品',
                'not_in_order' => '该产品不属于此订单',
                'item_cancelled' => '该产品已被取消',
                'item_finished' => '该产品已结束(服务已提供)',
                'item_is_addon' => '附加项目通过主产品管理',
                'order_not_operational' => '订单目前不允许修改其产品',
                'order_not_paid' => '订单未支付',
                'no_paid_payment' => '没有已确认的付款可以关联退款',
                'insufficient_refundable' => '订单可退款金额不足以覆盖该产品金额',
                'already_fully_refunded' => '订单已退款全部已收款金额',
                'item_already_fully_refunded' => '该产品已退款全部金额',
                'expired' => '订单已过期',
                'invalid_amount' => '退款金额无效',
                'stale_item_version' => '当你打开模态框时产品已被修改;请关闭后重试',
                'capacity_changed' => '当你打开模态框时订单可退款金额已变化;请关闭后重试',
                'no_items_selected' => '你尚未勾选任何要退款的产品',
                'invalid_item_selection' => '所选退款产品无效;请关闭后重试',

                // 7.2e.2(#159):管理日期/时间相关阻拦原因。
                'invalid_slot_selection' => '所选日期和时间在系统中不存在',
                'cross_zone_change_forbidden' => '不能从此处切换到其他区域的时段(请改用产品切换)',
                'slot_closed' => '所选时段对公众关闭',
                'slot_in_past' => '所选日期已过',
                'park_closed' => '当日公园关闭',
                'product_window' => '该产品不在所选时段提供',
                'insufficient_capacity_at_save' => '该时段已没有足够空位',
                'beyond_horizon' => '该日期超过允许预约的范围',

                // 子阶段 7.2e.3(决议 #167):修改数量 + 产品的原因。
                'cross_type_change_forbidden' => '无法在此处于门票与套餐之间切换;请使用手动订单',
                'cross_zone_change_forbidden_product' => '只能更换为同一区域的产品;如需更换区域请使用手动订单',
                'invalid_product' => '所选产品无效或未在售',
                'invalid_quantity' => '所填数量无效',
                'pack_quantity_range' => '宾客人数超出该套餐的允许范围',
                'product_unavailable_on_date' => '新产品在所选日期没有价格',
                'orphan_addons' => '新产品不支持当前产品的某些附加项目;请先移除它们',

                // 子阶段 7.2e.4(决议 #170):附加项目管理(标签页 2)的原因。
                'addon_not_in_parent' => '其中一个附加项目不属于此产品',
                'addon_incompatible_with_product' => '该附加项目与此产品不兼容',
                'addon_already_added' => '该附加项目已存在于产品中',
                'addon_quantity_invalid' => '附加项目数量无效',
                'addon_partial_reduce_unsupported' => '如需减少附加项目,请先移除(数量 0)再以所需数量重新添加;退款另行处理',
                'addon_locked' => '该附加项目已包含在产品中,不可移除或减少(如需更换套餐,请选择该组的其他选项)',
                'addon_no_extra' => '该附加项目为包含项,不可在包含数量之外额外付费添加',
                'addon_group_conflict' => '每个选择组只能选择一个附加项目',
                'addon_unavailable_on_date' => '该附加项目在所选日期没有价格',
                'addon_requires_missing' => '该附加项目需要订单中已有另一个附加项目(例如第二个蛋糕需要蛋糕);请先添加所需的附加项目',
            ],
        ],
    ],

    // 第 7.5 阶段 — 用户管理(GDPR),决策 #180。
    'users' => [
        'nav_label' => '用户',
        'model_label_singular' => '用户',
        'model_label_plural' => '用户',
        'heading' => '用户',

        'col_name' => '姓名',
        'col_email' => '邮箱',
        'col_phone' => '电话',
        'col_roles' => '角色',
        'col_status' => '状态',
        'col_verified' => '邮箱已验证',
        'col_last_login' => '最后登录',
        'col_created_at' => '注册时间',
        'col_locale' => '语言',
        'col_marketing' => '营销通讯',

        'verified' => '已验证',
        'unverified' => '未验证',
        'never' => '从未',
        'yes' => '是',
        'no' => '否',

        'status' => [
            'active' => '正常',
            'anonymized' => '已匿名化',
        ],

        'roles' => [
            'admin' => '管理员',
            'customer' => '客户',
            'staff' => '员工',
        ],

        'locale_value' => [
            'es' => '西班牙语',
            'en' => '英语',
            'fr' => '法语',
        ],

        'section_data' => '账户信息',
        'section_roles' => '角色',
        'section_consents' => '同意记录',
        'section_orders' => '订单',

        'orders_summary' => [
            'empty' => '该客户还没有订单。',
        ],

        'consents' => [
            'empty' => '该账户没有同意记录。',
            'types' => [
                'privacy' => '隐私政策',
                'terms' => '条款与条件',
                'waiver' => '免责声明(waiver)',
                'marketing' => '营销通讯',
            ],
        ],

        'filter_anonymized' => '已匿名化的账户',
        'filter_anonymized_all' => '全部',
        'filter_anonymized_yes' => '仅已匿名化',
        'filter_anonymized_no' => '仅正常',

        'actions' => [
            'send_reset' => [
                'label' => '发送密码链接',
                'modal_heading' => '发送密码重置链接',
                'modal_description' => '将向 :email 发送一封邮件,内含让客户设置新密码的链接。',
                'submit' => '发送链接',
                'success' => '已向 :email 发送密码重置链接。',
                'throttled' => '刚刚已发送过链接。请稍候一分钟再重试。',
                'blocked' => '无法向该账户发送链接。',
            ],
            'anonymize' => [
                'label' => '匿名化',
                'modal_heading' => '匿名化用户(GDPR)',
                'modal_description' => '此操作不可逆。将删除个人数据(姓名、邮箱、电话),删除同意记录并解除角色。订单因税务义务予以保留。该账户将无法登录,其邮箱将被释放以供重新使用。',
                'reason' => '原因(将记入审计日志)',
                'submit' => '永久匿名化',
                'success' => '用户已成功匿名化。',
                'blocked' => '无法匿名化该账户。',
            ],
        ],
    ],

    'audit' => [
        'nav_label' => '事件',
        'model_label_singular' => '事件',
        'model_label_plural' => '事件',
        'col_when' => '时间',
        'col_action' => '事件',
        'col_actor' => '用户',
        'actor_system' => '系统',
        'col_target' => '关联对象',
        'target_order' => '订单 :code',
        'col_detail' => '详情',
        'col_ip' => 'IP',
        'filter_critical_only' => '仅关键事件',
        'actions' => [
            'payments_duplicate_capture' => '重复/孤立收款',
            'payments_overbooked_capture' => '过期后收款',
            'orders_refund_failed' => '退款失败',
            'orders_item_refund_failed' => '退款失败（项目）',
            'orders_payment_init_failed' => '发起支付失败',
            'users_anonymize_blocked' => '匿名化被阻止（GDPR）',
            'access_user_roles_update_blocked' => '角色变更被阻止',
            'registrations_validate_rate_limited' => '验证频率限制（入口）',
        ],
    ],

    'access' => [
        'nav_label' => '角色与权限',
        'model_label_singular' => '角色',
        'model_label_plural' => '角色与权限',
        'edit_title' => '角色权限：:role',

        'col_role' => '角色',
        'col_name' => '标识符',
        'col_permissions' => '权限',
        'all_permissions' => '全部',

        'section_identity' => '身份',
        'field_name' => '技术标识符',
        'field_name_hint' => '不可更改:代码使用它(无法重命名)。',
        'field_display' => '角色',

        'admin_notice_title' => '管理员',
        'admin_notice' => '管理员是超级用户:自动拥有全部权限,因此此处不进行编辑。',
        'customer_notice_title' => '客户',
        'customer_notice' => '客户角色无法访问管理面板;其权限在此不适用。',
        'access_manage_admin_only' => '仅限管理员(无法授予其他角色)。',

        'groups' => [
            'operativa' => '日常运营',
            'gestion' => '管理与配置',
            'sistema' => '系统',
        ],

        'permissions' => [
            'registrations_validate' => '在门口验证注册/免责声明',
            'orders_view' => '查看订单',
            'orders_create_manual' => '创建手动订单(后台)',
            'orders_cancel' => '取消订单',
            'orders_refund' => '订单退款',
            'orders_edit_event_data' => '编辑订单的活动信息(寿星、年龄、备注)',
            'orders_edit_item' => '编辑订单产品(日期、数量、产品、信息、附加项)',
            'orders_cancel_item' => '取消订单中的单个产品',
            'orders_refund_item' => '对订单中的单个产品退款',
            'calendar_view' => '查看日历与工作台',
            'users_search_minimal' => '用户最小化搜索(符合 GDPR)',
            'catalog_manage' => '管理商品目录(门票、套餐、附加项)',
            'slots_manage' => '管理时段与容量',
            'prices_manage' => '管理费率与价格',
            'content_manage' => '管理内容(区域、游乐设施、常见问题、规则、页面)',
            'settings_manage' => '管理配置与税务信息',
            'users_manage' => '管理用户(资料、匿名化、密码)',
            'users_anonymize' => '匿名化用户(GDPR)',
            'consents_view' => '查看用户同意记录',
            'waiver_view' => '查看免责声明签署记录(签名与PDF)',
            'reports_view' => '查看报表与导出',
            'audit_view' => '查看审计日志',
            'access_manage' => '管理角色与权限',
        ],

        'user_roles' => [
            'label' => '管理角色',
            'modal_heading' => '用户角色',
            'modal_description' => '勾选此账户的角色。分配「员工」或「管理员」将授予其访问面板的权限;取消则收回该权限。',
            'field' => '已分配角色',
            'submit' => '保存角色',
            'success' => '角色已更新。',
            'blocked' => '无法管理该账户的角色。',
            'blocked_self' => '你不能移除自己的管理员角色。',
            'blocked_last_admin' => '无法移除系统中最后一名管理员。',
        ],
    ],

    'catalog' => [
        'nav_label' => '商品目录',
        'model_label_singular' => '商品',
        'model_label_plural' => '商品目录',

        'col_name' => '名称',
        'col_type' => '类型',
        'col_zone' => '区域',
        'col_duration' => '时长',
        'col_price' => '价格',
        'col_featured' => '推荐',
        'col_active' => '启用',
        'col_sellable' => '可售',
        'col_position' => '排序',

        'types' => [
            'entry' => '门票',
            'pack' => '套餐',
            'addon' => '附加项',
        ],

        'active_yes' => '启用',
        'active_no' => '停用',
        'sellable_yes' => '在售',
        'sellable_no' => '停售',
        'duration_unlimited' => '不限时',

        'price_from' => ':amount 起',
        'price_readonly_hint' => '价格在每个商品的「价格」区编辑。',

        'edit_title' => '编辑::name',
        'create_title' => '创建商品',
        'created_draft_hint' => '商品已创建。若要销售,请勾选「在线销售」并设置价格。',
        'type_create_hint' => '选择商品类型。创建后将无法更改(影响容量和订单)。',
        'type_locked_hint' => '类型不可更改(影响容量和订单)。',

        'section_classification' => '分类与状态',
        'section_classification_hint' => '类型不可更改(影响容量和订单)。显示顺序在目录列表中拖动调整。',
        'section_operational' => '容量与时段',
        'section_operational_hint' => '时长、每单位占用的名额,以及在营业时段的哪个区间可以开始。',
        'section_pack' => '套餐(生日会)',
        'section_pack_hint' => '宾客人数、定金以及布置/清理时间。注意:定金目前仅显示在网站上;当前收取的是全额(部分收款待接入)。',
        'section_price' => '价格',
        'price_section_hint' => '当日价格由适用费率决定。留空某费率的金额,则该费率适用的日期不销售此商品。',
        'price_rate_normal_hint' => '平日价格。',
        'price_rate_special_hint' => '节假日、周末及前夜的价格。',
        'price_no_rates' => '没有启用的费率。请先配置费率才能设置价格。',

        'lang' => [
            'es' => '西班牙语',
            'en' => '英语',
            'fr' => '法语',
        ],

        'field_name' => '名称',
        'field_period_label' => '价格单位',
        'period_label_hint' => '例如:「每人」「每个孩子」。',
        'field_description' => '描述',
        'field_badge' => '推荐标签',
        'badge_hint' => '卡片上的短标签,如「Top」。留空 = 无标签。',
        'field_features' => '亮点',
        'features_hint' => '每行一个亮点。',

        'field_is_active' => '在网站上显示',
        'is_active_hint' => '停用后将不再显示在公开网站上。',
        'field_is_sellable' => '在线销售',
        'is_sellable_hint' => '必须有价格才能销售。',
        'field_featured' => '推荐',
        'featured_hint' => '在网站上突出该商品。',

        'zone_hint' => '可进入的区域。',
        'zone_locked_sold' => '无法更改区域:该商品已有销售记录(会改变其容量占用)。',

        'field_duration_min' => '时长(分钟)',
        'duration_min_hint' => '留空 = 不限时(全天)。',
        'field_seats_per_unit' => '每单位占用名额',
        'seats_per_unit_hint' => '每售出一个单位所占用的容量名额。',
        'field_available_after_open_min' => '开园后可用',
        'available_after_open_hint' => '开园后多少分钟才能开始(0 = 不限制)。',
        'field_available_before_close_min' => '闭园前停售',
        'available_before_close_hint' => '闭园前多少分钟起不能开始(0 = 不限制)。',
        'minutes' => '分钟',
        'field_min_advance' => '最少提前预订',
        'min_advance_hint' => '相对于今天需提前多久才能预订(0 = 不限制)。与上面的当日时间窗不同:这里是参观前的天数/小时数。',
        'field_min_advance_unit' => '提前单位',
        'min_advance_units' => [
            'days' => '天(当天不可)',
            'hours' => '小时(时段开始前)',
        ],

        'field_min_qty' => '最少人数',
        'min_qty_hint' => '预订所需的最少宾客人数。',
        'field_max_qty' => '最多人数',
        'max_qty_hint' => '不能小于最少人数。',
        'max_qty_below_min' => '最多人数不能小于最少人数。',
        'field_deposit_type' => '定金类型',
        'deposit_types' => [
            'none' => '无定金(全额支付)',
            'percent' => '总额的百分比',
            'fixed' => '固定金额',
        ],
        'field_deposit_value' => '定金金额',
        'deposit_value_percent' => '作为定金收取的总额百分比(%)。',
        'deposit_value_fixed' => '固定金额,以分为单位(3000 = 30.00 €)。',
        'deposit_value_none' => '不适用(全额支付)。',
        'field_prep_before_min' => '布置(提前分钟数)',
        'prep_before_hint' => '派对前占用名额的准备时间。',
        'field_prep_after_min' => '清理(之后分钟数)',
        'prep_after_hint' => '派对后占用名额的清理时间。',

        'field_event_fields' => '活动信息(字段模板)',
        'event_fields_hint' => '预订该套餐时要求填写的字段(例如寿星姓名)。',
        'event_field_key' => '键名',
        'event_field_key_hint' => '技术标识符:小写字母、数字、连字符或下划线。',
        'event_field_type' => '类型',
        'event_field_types' => [
            'text' => '文本',
            'number' => '数字',
            'textarea' => '长文本',
        ],
        'event_field_required' => '必填',
        'event_field_label' => '标签',
        'event_field_add' => '添加字段',
        'event_field_duplicate' => '活动信息中键名「:key」重复。',
        'event_field_stage' => '何时填写',
        'event_field_stages' => [
            'booking' => '预订时',
            'postform' => '后续表单',
        ],
        'event_field_stage_hint' => '「预订时」在购买过程中填写;「后续表单」在之后与每位儿童信息一起填写。',

        'field_guest_fields' => '每位儿童信息(字段模板)',
        'guest_fields_hint' => '在预订后的表单中,针对每位来宾要求填写的列(默认:姓名、过敏、备注、特殊餐食)。',
        'guest_field_add' => '添加列',
        'guest_field_duplicate' => '每位儿童信息中键名「:key」重复。',

        'warn_sellable_no_price' => '该商品已标记为可售,但基础费率没有价格:在「费率与价格」(7.8)中设置价格之前无法销售。',

        'actions' => [
            'create' => '创建商品',
            'delete' => [
                'label' => '删除商品',
                'modal_heading' => '从目录中删除该商品',
                'modal_description' => '只能删除从未销售过的商品。其价格和附加项关联也将一并删除。此操作不可逆。如果商品已有销售记录,请停用而不是删除。',
                'submit' => '永久删除',
                'blocked' => '无法删除:该商品已有销售记录。请改为停用。',
                'success' => '商品已从目录中删除。',
            ],
        ],

        'addons' => [
            'title' => '可用附加项',
            'col_name' => '附加项',
            'col_status' => '状态',
            'col_price' => '价格',
            'col_position' => '排序',
            'col_config' => '提供方式',
            'status_visible' => '已提供',
            'status_hidden' => '隐藏(停用或停售)',
            'attach' => '添加附加项',
            'attach_heading' => '为该商品添加附加项',
            'attach_select' => '附加项',
            'position' => '排序',
            'empty' => '该商品尚无附加项。请添加适用的附加项。',
            'already_attached' => '该附加项已添加到此商品。',
            'configure' => '配置',
            'configure_heading' => '该附加项的提供方式',
            'configured' => '附加项已配置。',
            'is_included' => '包含(免费)',
            'is_included_hint' => '前几份随商品免费赠送,超出部分按其价格收费。',
            'included_quantity' => '包含数量',
            'included_quantity_hint' => '免费赠送的份数(例如 1 = 第一个蛋糕)。',
            'is_mandatory' => '必选(始终启用)',
            'is_mandatory_hint' => '购买时不可取消,也不能低于最低数量。',
            'quantity_mode' => '数量',
            'quantity_mode_hint' => '固定 = 由顾客选择数量。按宾客 = 套餐每位宾客一份。',
            'mode_fixed' => '固定数量(+额外)',
            'mode_per_guest' => '每位宾客一份',
            'allow_extra' => '允许加购(按正常价格)',
            'allow_extra_hint' => '启用后,顾客可在包含份数之外加购。',
            'max_qty' => '每次预订上限',
            'max_qty_hint' => '此加购项每次预订的数量上限(仅限固定数量)。留空 = 无限制。',
            'choice_group' => '选择组',
            'choice_group_hint' => '同一商品的多个附加项使用相同的键 = 顾客只能选其一(例如 menu:菜单 1 ⊻ 菜单 2)。',
            'requires_addon' => '需要其他附加项目',
            'requires_addon_hint' => '只有已选择指定附加项目时,顾客才能添加此项(例如「第二个蛋糕」需要「蛋糕」)。仅限固定数量。',
            'requires_addon_none' => '无依赖',
            'requires_cleared' => '已清除 :count 个依赖于所解绑附加项目的「需要」依赖关系。',
            'badge_included' => '包含',
            'badge_mandatory' => '必选',
            'badge_per_guest' => '按宾客',
            'badge_group' => '组::group',
            'badge_requires' => '需要::name',
        ],
    ],

    'rate_types' => [
        'nav_label' => '费率',
        'model_label_singular' => '费率',
        'model_label_plural' => '费率',

        'col_label' => '费率',
        'col_weekdays' => '适用日',
        'col_priority' => '优先级',
        'col_special' => '特殊',
        'col_prices_count' => '价格数',
        'col_active' => '启用',
        'prices_count_hint' => '使用此费率定义的产品价格数量。若有价格则无法删除（请改为停用）。',

        'active_yes' => '启用',
        'active_no' => '停用',

        'weekdays' => [
            0 => '星期日',
            1 => '星期一',
            2 => '星期二',
            3 => '星期三',
            4 => '星期四',
            5 => '星期五',
            6 => '星期六',
        ],
        'weekdays_short' => [
            0 => '周日',
            1 => '周一',
            2 => '周二',
            3 => '周三',
            4 => '周四',
            5 => '周五',
            6 => '周六',
        ],

        'lang' => [
            'es' => '西班牙语',
            'en' => '英语',
            'fr' => '法语',
        ],

        'section_identity' => '标识',
        'section_identity_hint' => '技术标识符以及在目录和价格中显示的名称。',
        'field_key' => '标识符',
        'key_create_hint' => '技术标识符：小写字母、数字和下划线（如 "finde"、"festivo"）。创建后不可更改。',
        'key_locked_hint' => '标识符不可更改（网站和价格计算会用到它）。',
        'field_label' => '名称',

        'section_applicability' => '适用时间',
        'section_applicability_hint' => '激活此费率的星期几。若某天有多个费率，优先级高者生效。具体的节假日和前夜请使用特殊日期（在容量与日历中）。',
        'field_weekdays' => '星期几',
        'weekdays_hint' => '勾选适用此费率的日子。留空表示基础费率（当没有其他日子或特殊日期适用时使用）。',
        'field_priority' => '优先级',
        'priority_hint' => '若某天有多个启用的费率，优先级高者生效。基础费率通常为 0。',

        'section_status' => '状态',
        'field_is_special' => '特殊费率',
        'is_special_hint' => '仅作标记（节假日/周末/前夜）。不影响计算，只便于识别。',
        'field_is_active' => '启用',
        'is_active_hint' => '停用后将不再按星期几应用（产品不会以此费率出售）。这是安全地停用在用费率而不删除它的方式。',

        'create_title' => '创建费率',
        'edit_title' => '编辑费率：:name',
        'warn_deactivated_fallback' => '你已停用基础费率。网站仍可运行，但请确认这是你想要的：当没有其他日子或特殊日期匹配时，会应用此费率。',

        'actions' => [
            'create' => '创建费率',
            'delete' => [
                'label' => '删除费率',
                'modal_heading' => '删除此费率',
                'modal_description' => '只能删除未被使用的费率：不是基础费率、没有产品价格、也没有特殊日期引用它。此操作不可撤销。要停用在用的费率，请将其停用而非删除。',
                'submit' => '永久删除',
                'success' => '费率已删除。',
                'blocked' => [
                    'fallback_normal' => '无法删除基础费率：价格计算需要它。若不想使用，请将其停用。',
                    'has_prices' => '无法删除：有产品在此费率下设有价格（将会丢失）。请先移除这些价格或停用该费率。',
                    'referenced_by_special_dates' => '无法删除：有特殊日期在使用它。请先更改这些日期或停用该费率。',
                ],
            ],
        ],
    ],

    'special_dates' => [
        'nav_label' => '特殊日期',
        'model_label_singular' => '特殊日期',
        'model_label_plural' => '特殊日期',

        'col_date' => '日期',
        'col_note' => '备注',
        'col_state' => '状态',
        'col_window' => '营业时间',
        'col_rate' => '费率',

        'closed' => '关闭',
        'open' => '开放',
        'window_weekly' => '每周时间',
        'window_weekly_short' => '每周',
        'rate_by_weekday' => '按星期几',
        'filter_all' => '全部',
        'filter_upcoming' => '仅未来（从今天起）',

        'lang' => [
            'es' => '西班牙语',
            'en' => '英语',
            'fr' => '法语',
        ],

        'section_day' => '日期',
        'field_date' => '日期',
        'date_hint' => '偏离每周常规的具体日子（节假日、关闭、特殊营业时间或特殊费率）。',
        'field_note' => '内部备注',
        'note_hint' => '用于标识该日期（如"圣诞节"、"因维护关闭"）。不向客户显示。',

        'section_state' => '当日状态',
        'field_is_closed' => '当日关闭',
        'is_closed_hint' => '勾选后，当天不售票也不生成时段（并会阻止在已生成时段上预订）。',
        'field_open_time' => '开门时间',
        'open_time_hint' => '特殊开门时间。留空 = 使用每周时间。',
        'field_close_time' => '关门时间',
        'close_time_hint' => '特殊关门时间。留空 = 使用每周时间。必须晚于开门时间。',
        'field_rate_type' => '当日费率',
        'rate_type_hint' => '当天适用的价格费率（如按周末计价的节假日）。',
        'rate_type_placeholder' => '按星期几（无特殊费率）',
        'rate_inactive_suffix' => '（已停用）',
        'close_before_open' => '关门时间必须晚于开门时间。',

        'create_title' => '添加特殊日期',
        'edit_title' => '编辑日期：:date',

        'actions' => [
            'create' => '添加日期',
            'delete' => [
                'label' => '删除日期',
                'modal_heading' => '删除此特殊日期',
                'modal_description' => '该日将恢复按其星期几的常规时间和费率运行。此操作不可撤销。（不影响该日已支付的预订。）',
                'submit' => '删除',
                'success' => '特殊日期已删除。',
            ],
        ],
    ],

    // 维护 — 可用性子系统（#218，路线图之外）。
    'maintenance' => [
        'nav_label' => '维护',
        'title' => '维护',
        'save' => '保存更改',
        'saved' => '维护设置已更新。',
        'section_site' => '整站维护',
        'section_site_hint' => '启用后，访客会看到「马上回来」页面（503）。后台始终可访问；你（员工）仍可查看真实网站并带有提示横幅，便于在开关前后检查。',
        'site_enabled' => '启用整站维护',
        'site_enabled_hint' => '对访客关闭公开网站（用于临时更新）。不会中断 Redsys 进行中的支付，后台也绝不会被锁定。',
        'message' => '给访客的消息（可选）',
        'message_hint' => '留空则显示默认文案。可说明原因或恢复时间（例如「6 月 20 日恢复」）。',
        'section_reservations' => '在线预订',
        'section_reservations_hint' => '仅暂停在线预订系统；网站其余部分仍可浏览。当你想临时改为电话受理预订时很有用。',
        'reservations_paused' => '暂停在线预订',
        'reservations_paused_hint' => '启用后，全站的「预订」按钮都会改为引导访客拨打电话（取自设置 → 联系方式的号码）并显示提示。后台手动下单和 Redsys 进行中的支付不受影响。',
        'reservations_title' => '提示标题（可选）',
        'reservations_title_hint' => '暂停在线预订时，顾客在购买面板中看到的标题。留空则显示默认文案。',
        'reservations_message' => '提示消息（可选）',
        'reservations_message_hint' => '暂停在线预订时，顾客在购买面板中看到的文案。留空则显示默认文案。',
        'section_pages' => '单个页面',
        'section_pages_hint' => '将某个页面单独设为维护：显示「该版块不可用」并保留导航栏和页脚，访客仍可浏览网站其余部分。适合在不关闭整站的情况下修改某个页面。',
        'page_home' => '首页',
        'page_precios' => '价格',
        'page_cumpleanos' => '生日派对',
        'page_servicios' => '服务',
        'page_normas' => '规则',
        'page_contacto' => '联系',
    ],

    'settings' => [
        'nav_label' => '设置',
        'title' => '设置',
        'save' => '保存更改',
        'saved' => '设置已保存。',

        // 顶层选项卡（阶段 3 · 方案 B · L2）：日常项在前，技术项在后。
        'tab_business' => '你的业务',
        'tab_web' => '网站文案与外观',
        'tab_fiscal' => '税务信息',
        'tab_advanced' => '高级',

        'section_identity' => '身份信息',
        'section_identity_hint' => '在网站显示的名称、城市和域名。',
        'section_fiscal' => '税务信息',
        'section_fiscal_hint' => '用于开票和法律文本的税务身份（可能与商号及园区地址不同）。',
        'business_name' => '商号',
        'business_city' => '城市',
        'business_legal_name' => '法定名称',
        'business_legal_name_hint' => '公司的税务名称（用于发票）。不同于商号。',
        'business_nif' => '税号（NIF/CIF）',
        'business_domain' => '网站域名',
        'business_domain_hint' => '出现在法律文本中的域名（例如 miparque.es）。留空则自动使用提供网站的域名。',
        'business_address' => '税务地址',
        'business_address_hint' => '用于开票的税务地址（可能与园区地址不同）。',

        'section_contact_data' => '联系方式',
        'section_contact_data_hint' => '在公开网站显示的邮箱、电话和 WhatsApp。',
        'section_address' => '地址与地图',
        'section_address_hint' => '园区地址和嵌入的谷歌地图。',
        'section_social' => '社交媒体',
        'section_social_hint' => '社交链接和「实时」社交动态。',
        'contact_email' => '联系邮箱',
        'contact_phone' => '电话',
        'address_line1' => '地址（第一行）',
        'address_line2' => '地址（第二行）',
        'address_maps_url' => '谷歌地图链接',
        'address_maps_url_hint' => '在谷歌地图中打开位置的链接（"如何前往"按钮）。即"分享 → 发送链接"的那个。',
        'address_maps_embed_url' => '嵌入地图（插入网址）',
        'address_maps_embed_url_hint' => '用于在网站内显示地图。在谷歌地图中：分享 → "嵌入地图" → 复制 <iframe> 代码（或仅其 src，以 https://www.google.com/maps/embed 开头）。留空 = 显示装饰性标记。',
        'maps_embed_not_recognized' => '"嵌入地图"字段中未识别到有效的谷歌地图插入网址：地图未更改。请粘贴谷歌地图"嵌入地图"提供的 <iframe> 代码。',
        'contact_instagram' => 'Instagram（网址）',
        'contact_tiktok' => 'TikTok（网址）',
        'contact_whatsapp' => 'WhatsApp（号码）',
        'contact_whatsapp_hint' => '含国际区号的号码（例如 34600112233）。用于联系页面的 WhatsApp 按钮。留空 = 不显示。',
        'social_feed' => '「实时」社交动态（嵌入网址）',
        'social_feed_hint' => '在该板块展示你 Instagram/TikTok 的最新动态。在 SnapWidget 或 LightWidget 免费创建一个连接你账户的小组件，复制其 <iframe> 代码（或仅其 src）粘贴到这里。留空 = 显示默认相册。GDPR 提示：该动态仅在 Cookie 同意后加载，并会向提供商（可能在美国）传输数据 — 请与法律顾问确认传输保障（数据隐私框架或标准合同条款）。',
        'social_feed_not_recognized' => '「社交动态」字段中未识别到有效的嵌入网址：动态未更改。请粘贴 SnapWidget 或 LightWidget 小组件的 <iframe> 代码。',

        'section_web_appearance' => '网站外观与选项',
        'section_web_appearance_hint' => '品牌色、社交分享图片、目录搜索框和 Cookie 横幅。',
        'seo_og_image' => '分享图片（网址）',
        'seo_og_image_hint' => '在社交平台分享网站时显示的图片（Open Graph）。留空 = 无图片。',

        'section_landing_texts' => '落地页文案',
        'section_landing_texts_hint' => '浏览器标签/谷歌标题、页脚标语和版权附言。可按语言编辑；某语言留空则使用默认文案。',
        'lang_es' => '西班牙语',
        'lang_en' => '英语',
        'lang_fr' => '法语',
        'seo_title' => '网站标题（标签页与谷歌）',
        'seo_title_hint' => '首页在浏览器标签页和谷歌搜索结果中显示的标题。例如：「MI PARQUE - Parque de saltos」。留空 = 默认标题。',
        'landing_tagline' => '页脚标语',
        'landing_tagline_hint' => '页脚名称下方的短语。例如：「Parque de saltos para toda la familia · Murcia」。',
        'landing_footer_rights' => '版权附言',
        'landing_footer_rights_hint' => '页脚「© 年份 名称 —」之后的文字。例如：「Hecho para reír.」。',

        'section_registration' => '注册（外部系统）',
        'section_registration_hint' => '网站头部的「注册」按钮跳转到你们的外部注册/免责声明系统。标签和副标题可按语言编辑；若网址留空，按钮将打开内部预订注册。',
        'registration_url' => '外部注册网址',
        'registration_url_hint' => '注册系统的完整网址（https://…）。在新标签页打开。留空 = 使用内部注册。',
        'registration_label' => '按钮标签',
        'registration_label_hint' => '按钮的主文字。例如：「Registro」。',
        'registration_subtitle' => '按钮副标题',
        'registration_description' => '说明文字（确认页）',
        'registration_description_hint' => '在「预订已确认」页面、注册按钮旁显示的段落。留空则使用默认文字。',

        'theme_brand' => '品牌色',
        'theme_brand_hint' => '品牌主色（格式 #RRGGBB）。不影响按区域配置的区域颜色。',

        'cookies_banner_enabled' => '显示 Cookie 横幅',
        'cookies_banner_enabled_hint' => '关闭它并不会取消预先拦截：在访客同意之前，地图和社交动态仍不会加载；只是隐藏提示。除非你通过其他方式管理同意，否则请保持开启。',

        'catalog_search_min_items' => '产品数量超过 N 时显示搜索框',
        'catalog_search_min_items_hint' => '只有当可售产品总数超过此数字时，目录搜索框才会出现。留空使用默认值（12）。0 = 始终显示搜索框。',

        'section_sales' => '销售',
        'section_sales_hint' => '销售的技术参数。请谨慎修改：超出范围的值在保存时会被拒绝。',
        'section_door' => '门口',
        'section_door_hint' => '门口验证的行为。请谨慎修改。',
        'section_capacity' => '生日会容量',
        'section_capacity_hint' => '每时段生日会的容量上限。请谨慎修改。',
        'hold_minutes' => '支付期间的占位保留（分钟）',
        'hold_minutes_hint' => '客户支付时保留名额的分钟数。必须 ≥ POS 的超时。',
        'order_prefix' => '订单编号前缀',
        'order_prefix_hint' => '字母/数字/连字符，最多 8 位（如 «R-»）。仅适用于新订单；已生成的订单不变。',
        'purchase_horizon_months' => '可预订范围（月）',
        'purchase_horizon_months_hint' => '最多可提前几个月预订。',
        'incidents_alert_email' => '收款事件通知邮箱',
        'incidents_alert_email_hint' => '发生重复、孤立或过期后收款时通知到哪里。留空则使用联系邮箱。',
        'puerta_rate_limit' => '门口验证限制（每分钟）',
        'puerta_rate_limit_hint' => '每名员工每分钟允许的验证查询次数（防滥用）。',
        'puerta_waiver_check' => '在门口检查免责声明',
        'puerta_waiver_check_hint' => '启用：门口显示客户是否已签署免责声明（3 种状态）。停用：仅显示是否已注册（2 种状态），适用于免责声明由你们的外部系统管理时。',
        'tax_rate' => '默认增值税（%）',
        'tax_rate_hint' => '默认增值税百分比（仅供参考）。',
        'packs_max_per_slot' => '每时段生日会（上限）',
        'packs_cap_hint' => '0 = 无上限。',
        'packs_max_guests_per_slot' => '每时段宾客总数（上限）',
        'packs_prep_blocks_cupo' => '布置/清洁占用名额',
        'packs_prep_blocks_cupo_hint' => '启用后，布置和清洁的时间窗会占用相邻时段的名额（不仅是开始时段）。',

        'section_redsys' => '支付（Redsys）',
        'section_redsys_hint' => 'POS 配置。密钥不在此管理（出于安全存放在服务器上）。仅在确知后果时修改这些值：错误可能导致无法收款。',
        'redsys_live_requires_credentials' => '未配置银行的商户代码和终端，无法切换到正式（生产）环境。请先配置。未保存任何更改。',
        'redsys_live_requires_secret' => '无法切换到正式（生产）环境：服务器上未配置银行密钥（仍为测试密钥或无效）。激活生产前必须在服务器上安装密钥。未保存任何更改。',
        'redsys_environment' => '环境',
        'redsys_environment_hint' => '"测试"（sandbox）或"正式"（生产）。切换到正式需已配置真实凭据。',
        'redsys_env_test' => '测试（sandbox）',
        'redsys_env_live' => '正式（生产）',
        'redsys_currency' => '货币（ISO-4217 数字）',
        'redsys_currency_hint' => '978 = 欧元。三位数字。',
        'redsys_merchant_code' => '商户代码（FUC）',
        'redsys_merchant_code_hint' => '由银行提供。',
        'redsys_terminal' => '终端',
        'redsys_merchant_name' => '商户名称',
        'redsys_merchant_url' => '通知网址（MerchantURL）',
        'redsys_merchant_url_hint' => '接收银行在线确认的端点。留空 = 使用站点的自动网址。',
    ],

    'weekly_schedule' => [
        'nav_label' => '营业时间',
        'title' => '每周营业时间',
        'intro' => '按星期几设置的园区常规营业时间。这是预订和网站使用的基础。单个日子（节假日、关闭）在"特殊日期"中管理；较长的时段（夏季等）在"季节"中管理。',
        'save' => '保存营业时间',
        'saved' => '营业时间已保存。',
        'closed' => '关闭',
        'open_time' => '开门',
        'close_time' => '关门',
        'close_before_open' => ':day 的关门时间必须晚于开门时间。',
    ],

    'seasons' => [
        'nav_label' => '季节',
        'model_label_singular' => '季节',
        'model_label_plural' => '季节',

        'col_name' => '名称',
        'col_range' => '日期',
        'col_hours' => '营业时间',
        'col_active' => '启用',
        'active_yes' => '启用',
        'active_no' => '停用',

        'section_period' => '季节',
        'section_period_hint' => '一段日期，具有自己的营业时间，在生效期间替代每周营业时间（如夏季）。该范围内每天都以此时间开放。',
        'field_name' => '名称',
        'field_name_hint' => '用于标识。会显示在网站上（如"夏季营业时间"）。',
        'field_start_date' => '从',
        'field_end_date' => '至',

        'section_hours' => '季节营业时间',
        'section_hours_hint' => '在该范围内每天适用的开门和关门时间。',
        'field_open_time' => '开门',
        'field_close_time' => '关门',
        'field_is_active' => '启用',
        'field_is_active_hint' => '停用后不再生效（预订和网站均不适用），但会保留。',

        'end_before_start' => '结束日期不能早于开始日期。',
        'close_before_open' => '关门时间必须晚于开门时间。',

        'create_title' => '创建季节',
        'edit_title' => '编辑季节：:name',

        'actions' => [
            'create' => '创建季节',
            'delete' => [
                'label' => '删除季节',
                'modal_heading' => '删除此季节',
                'modal_description' => '其日期范围将恢复按每周营业时间运行。不影响已完成的预订。此操作不可撤销。',
                'submit' => '删除',
                'success' => '季节已删除。',
            ],
        ],
    ],

    'slots' => [
        'nav_label' => '时段',
        'model_label_singular' => '时段',
        'model_label_plural' => '时段',

        'col_zone' => '区域',
        'col_date' => '日期',
        'col_time' => '时间',
        'col_online_capacity' => '在线名额',
        'col_sale' => '在线销售',
        'col_overridden' => '名额',
        'by_cupo' => '按配额',
        'sale_open' => '开放',
        'sale_closed' => '关闭',
        'overridden_yes' => '手动调整',
        'filter_upcoming' => '仅未来',

        'section_context' => '时段',
        'occupancy' => '当前占用',
        'occupancy_seats' => '已占用 :n / :cap 个在线名额',
        'occupancy_guests' => '已预订 :n 位来宾',

        'section_sale' => '销售与名额',
        'section_sale_hint' => '开启或关闭该时段的在线销售，并临时调整其在线名额。整天关闭请在"特殊日期"或"营业时间"中管理。',
        'field_sale' => '在线销售开放',
        'field_sale_hint' => '关闭后，该时段不再通过网站销售（已完成的预订不受影响）。',
        'field_online_capacity' => '在线名额',
        'field_online_capacity_hint' => '该时段可通过网站销售的名额。更改后，该时段将标记为"手动调整"，"重新生成时段"不会覆盖它。',
        'pack_aforo_note' => '生日区的名额按配额管理（在"设置"中），不按时段管理。',

        'edit_title' => '时段：:zone · :date :time',

        'errors' => [
            'negative' => '名额不能为负。',
            'above_total' => '在线名额不能超过该时段的总名额（:total）。',
            'below_occupancy' => '名额不能小于当前占用（:occupancy）。',
        ],

        'actions' => [
            'reset' => [
                'label' => '重置为模板名额',
                'modal_heading' => '重置该时段的名额',
                'modal_description' => '名额将恢复为每周模板的名额，并取消"手动调整"标记（未来重新生成时可更改）。不影响已完成的预订。',
                'submit' => '重置',
                'success' => '名额已重置为模板。',
                'blocked_below_occupancy' => '无法重置：模板名额（:template）小于当前占用（:occupancy）。',
            ],
        ],

        'regenerate' => [
            'btn' => '重新生成时段',
            'modal_heading' => '重新生成时段',
            'modal_description' => '根据当前营业时间和模板创建并更新该范围内的时段。清理无预订的过时时段（超出营业时间）；有预订的将关闭而非删除。手动调整的名额会保留。不影响过去的日期。',
            'submit' => '重新生成',
            'from_label' => '从',
            'to_label' => '至',
            'invalid_range' => '"至"日期必须等于或晚于"从"日期。',
            'done' => '重新生成完成：:generated 个已创建/更新，:deleted 个过时已删除，:closed 个已关闭（有预订）。',
        ],
    ],

    'slot_templates' => [
        'nav_label' => '时段模板',
        'model_label_singular' => '时段模板',
        'model_label_plural' => '时段模板',

        'col_zone' => '区域',
        'col_weekday' => '星期',
        'col_time' => '时间',
        'col_duration' => '时长',
        'col_capacity' => '总名额',
        'col_online_capacity' => '在线名额',
        'col_active' => '启用',
        'active_yes' => '启用',
        'active_no' => '停用',

        'section_when' => '时间',
        'section_when_hint' => '按区域设置每周循环时段的星期和时间。具体时段由此生成。修改模板不会影响已生成的时段：请点击"重新生成时段"以应用。',
        'field_zone' => '区域',
        'field_weekday' => '星期',
        'field_start_time' => '开始时间',
        'field_duration' => '时长（分钟）',
        'field_duration_hint' => '时段的分钟数（名额网格）。也决定结束时间。',

        'section_capacity' => '名额',
        'section_capacity_hint' => '时段的总名额，以及通过网站销售的名额（其余留给现场）。生日区的名额按配额管理（设置），不按这些数字。',
        'field_capacity' => '总名额',
        'field_capacity_hint' => '时段的总名额（网站 + 现场）。',
        'field_online_capacity' => '在线名额',
        'field_online_capacity_hint' => '可通过网站销售的名额。不能超过总名额。',
        'field_is_active' => '启用',
        'field_is_active_hint' => '停用后，此模板不再生成时段。',

        'errors' => [
            'online_above_total' => '在线名额不能超过总名额。',
            'duplicate' => '该区域、星期和时间已存在模板。',
        ],

        'generate' => [
            'btn' => '生成模板',
            'modal_heading' => '批量生成时段模板',
            'modal_description' => '一次性为某个区域创建所有模板：选择星期、营业时间（开始→结束）、每个时段的时长和名额。适合快速启用一个区域，无需逐条录入。不会重复已存在的模板。',
            'submit' => '生成',
            'weekdays' => '星期',
            'start_time' => '首个时段（开始时间）',
            'end_time' => '结束（最后一个时段在此时间结束）',
            'end_time_hint' => '不会创建任何在此时间之后结束的时段。',
            'interval' => '时段开始间隔（分钟）',
            'interval_hint' => '留空表示连续时段（= 时长）。',
            'replace' => '替换这些星期的现有模板',
            'replace_hint' => '先删除该区域所选星期的模板再重新创建。关闭则只补充缺少的模板。',
            'regenerate_after' => '完成后重新生成时段',
            'regenerate_after_hint' => '在日历中生成具体时段（从今天到销售范围），无需单独点击"重新生成时段"。',
            'no_weekdays' => '请至少选择一个星期。',
            'no_slots' => '该时间和时长无法容纳任何时段。请检查开始、结束和时长。',
            'done' => '完成：创建了 :created 个模板，:skipped 个已存在。',
        ],

        'create_title' => '创建时段模板',
        'edit_title' => '编辑时段模板',

        'actions' => [
            'create' => '创建模板',
            'delete' => [
                'label' => '删除模板',
                'modal_heading' => '删除此模板',
                'modal_description' => '将不再生成时段。已生成的时段不受影响（如需可用"重新生成时段"清理）。此操作不可撤销。',
                'submit' => '删除',
                'success' => '模板已删除。',
            ],
        ],
    ],

    'zones' => [
        'nav_label' => '区域',
        'model_label_singular' => '区域',
        'model_label_plural' => '区域',

        'col_name' => '名称',
        'col_slug' => '标识',
        'col_cupo' => '配额（每时段儿童数）',
        'col_active' => '启用',
        'active_yes' => '启用',
        'active_no' => '停用',
        'cupo_global' => '全局',

        'section_identity' => '标识',
        'field_slug' => '标识',
        'field_slug_hint' => '唯一内部键（如 "jump"、"kids"、"cumpleanos"）。仅限字母、数字和连字符。',
        'field_accent' => '品牌颜色（网站）',
        'field_accent_hint' => '落地页的颜色类。"jump"/"kids" 已有样式；其他需要 CSS（属于 7.9）。',
        'field_color' => '颜色',
        'field_color_hint' => '该区域在面板中的颜色（日历、手环、单据）。',
        'field_position' => '排序',
        'field_area_sqm' => '面积（㎡）',
        'field_rides_count' => '设施数量',
        'field_image' => '图片（路径）',
        'field_image_hint' => '相对于 public/ 的路径（如 images/attractions/park_jump.webp）。落地页用此图片绘制区域卡片；留空则卡片无图片。文件上传将随图库功能一起推出。',
        'field_is_active' => '启用（运营）',
        'field_is_active_hint' => '停用后该区域不再运营（不销售）。与是否在网站显示无关。',
        'field_show_in_landing' => '在落地页显示',
        'field_show_in_landing_hint' => '是否作为区域卡片显示在公开网站上。某区域可以运营但不出现在落地页（如生日）。',

        'lang' => [
            'es' => '西班牙语',
            'en' => '英语',
            'fr' => '法语',
        ],
        'field_name' => '名称',
        'field_subtitle' => '副标题',
        'field_description' => '描述',
        'field_age_label' => '年龄标签',
        'field_age_range' => '年龄范围',

        'section_cupo' => '按区域的派对名额（配额）',
        'section_cupo_hint' => '仅当区域容纳派对（生日）时适用。留空 = 使用"设置"中的全局值；填值则覆盖全局。占用按区域计算。',
        'field_max_per_slot' => '每时段派对数',
        'field_max_guests_per_slot' => '每时段儿童数',
        'field_cupo_hint' => '留空 = 使用全局值。0 = 无上限。',
        'field_prep_blocks_cupo' => '布置/清理占用配额',
        'field_prep_blocks_cupo_hint' => '布置和清理是否也占用相邻时段。',
        'cupo_use_global' => '使用全局值',
        'yes' => '是',
        'no' => '否',

        'create_title' => '创建区域',
        'edit_title' => '编辑区域：:name',

        'actions' => [
            'create' => '创建区域',
            'delete' => [
                'label' => '删除区域',
                'modal_heading' => '删除此区域',
                'modal_description' => '只能删除没有产品或时段的区域。此操作不可撤销。',
                'submit' => '删除',
                'success' => '区域已删除。',
                'blocked' => '无法删除：该区域有产品或时段。请改为停用。',
            ],
        ],
    ],

    'attractions' => [
        'nav_label' => '设施',
        'model_label_singular' => '设施',
        'model_label_plural' => '设施',

        'col_zone' => '区域',
        'col_name' => '名称',
        'col_badge' => '标签',
        'col_complement' => '附加项',
        'col_special' => '特色',
        'special_yes' => '特色',
        'special_no' => '否',
        'col_active' => '启用',
        'active_yes' => '启用',
        'active_no' => '停用',

        'section_identity' => '标识',
        'field_zone' => '区域',
        'field_zone_hint' => '只有在落地页显示的区域才会展示其设施。',
        'zone_hidden_suffix' => '（落地页隐藏）',
        'field_position' => '排序',
        'field_position_hint' => '在所属区域内的显示顺序（数字小的在前）。',
        'field_image' => '图片（路径）',
        'field_image_hint' => '相对于 public/ 的路径（如 images/attractions/attraction-01.jpg）。文件上传将随图库功能一起推出。',
        'field_is_active' => '启用',
        'field_is_active_hint' => '停用后该设施不在落地页显示。',
        'field_is_special' => '特色',
        'field_is_special_hint' => '在落地页视觉上突出此设施（与是否售卖无关）。',
        'field_complement' => '付费附加项',
        'field_complement_hint' => '关联的可售附加项（可选）。选择后，落地页会显示其价格和「购买」按钮。仅列出可售附加项。',
        'complement_not_attached_warning' => '注意：此附加项在本区域的落地页无法购买（设施将仅作展示）。请在「目录」中确认它已设价格，并作为「付费」附加项（非「包含」）关联到本区域的可售门票。',

        'lang' => [
            'es' => '西班牙语',
            'en' => '英语',
            'fr' => '法语',
        ],
        'field_name' => '名称',
        'field_description' => '描述',
        'field_age' => '年龄',
        'field_badge' => '标签（子标签）',
        'field_badge_hint' => '用于区分相似设施的可选短标签（如 "XL"、"PRO"）。留空 = 无标签。',

        'create_title' => '创建设施',
        'edit_title' => '编辑设施：:name',

        'actions' => [
            'create' => '创建设施',
            'delete' => [
                'label' => '删除设施',
                'modal_heading' => '删除此设施',
                'modal_description' => '此操作不可撤销。',
                'submit' => '删除',
                'success' => '设施已删除。',
            ],
        ],
    ],

    'landing_services' => [
        'nav_label' => '服务（网站）',
        'model_label_singular' => '服务',
        'model_label_plural' => '网站服务',

        'col_title' => '标题',
        'col_slug' => '锚点',
        'col_pack' => '关联套餐',
        'contact_only' => '仅联系',
        'col_active' => '在 /servicios',
        'active_yes' => '显示',
        'active_no' => '隐藏',
        'col_nav' => '在菜单',
        'nav_yes' => '是',
        'nav_no' => '否',

        'section_identity' => '标识',
        'field_slug' => '锚点（slug）',
        'field_slug_hint' => '该板块的稳定标识：菜单链接到 /servicios#anchor。创建后固定，不可更改（会破坏链接）。',
        'field_position' => '顺序',
        'field_position_hint' => '在 /servicios 和菜单中的显示顺序（数字小的在前）。',
        'field_image' => '图片（路径）',
        'field_image_hint' => '相对于 public/ 的路径（例如 images/attractions/park_jump.webp）。文件上传将随图库功能推出。',
        'field_pack' => '关联套餐（可选）',
        'field_pack_hint' => '关联可售套餐后，板块会显示价格和“预订”按钮；不关联则显示“咨询”。关联套餐会将其从“生日”板块中移除。',
        'pack_not_purchasable_warning' => '提示：该套餐在网站上无法购买（板块将显示“咨询”）。请在“目录”中确认其已在线发售、已启用、有价格且属于运营中的区域。',
        'field_is_active' => '在 /servicios 显示',
        'field_is_active_hint' => '关闭后，该板块不会出现在 /servicios 页面。',
        'field_show_in_nav' => '在“服务”菜单显示',
        'field_show_in_nav_hint' => '开启后，该服务会出现在导航菜单的“服务”下拉中。',

        'lang' => [
            'es' => '西班牙语',
            'en' => '英语',
            'fr' => '法语',
        ],
        'field_title' => '标题',
        'field_accent_word' => '强调词',
        'field_accent_word_hint' => '图片上方的大号轮廓词（例如“学校”）。不是序号。',
        'field_zone_label' => '区域标签',
        'field_zone_label_hint' => '徽章/信息卡的文字（例如“Kids + Jump”或“整个公园”）。不是目录中的区域。',
        'field_nav_subtitle' => '菜单副标题',
        'field_nav_subtitle_hint' => '“服务”下拉中标题下方的小字（例如“30 分钟时段”）。',
        'field_body' => '描述',
        'field_specs' => '快速条件',
        'field_specs_hint' => '信息卡的条目（标签 + 值）：时段、最少人数等。',
        'field_spec_label' => '标签',
        'field_spec_value' => '值',
        'add_spec' => '添加条件',

        'create_title' => '创建服务',
        'edit_title' => '编辑服务：:name',

        'actions' => [
            'create' => '创建服务',
            'delete' => [
                'label' => '删除服务',
                'modal_heading' => '删除此服务',
                'modal_description' => '此操作不可撤销。如果关联了套餐，它将返回“生日”板块。',
                'submit' => '删除',
                'success' => '服务已删除。',
            ],
        ],
    ],

    'faqs' => [
        'nav_label' => '常见问题',
        'model_label_singular' => '常见问题',
        'model_label_plural' => '常见问题',

        'col_question' => '问题',
        'col_active' => '启用',
        'active_yes' => '启用',
        'active_no' => '停用',

        'section_classification' => '分类',
        'field_position' => '排序',
        'field_position_hint' => '显示顺序（数字小的在前）。',
        'field_is_active' => '启用',
        'field_is_active_hint' => '停用后该问题不在落地页显示。',

        'lang' => [
            'es' => '西班牙语',
            'en' => '英语',
            'fr' => '法语',
        ],
        'field_question' => '问题',
        'field_answer' => '回答',

        'create_title' => '创建常见问题',
        'edit_title' => '编辑问题：:name',

        'actions' => [
            'create' => '创建问题',
            'delete' => [
                'label' => '删除问题',
                'modal_heading' => '删除此问题',
                'modal_description' => '此操作不可撤销。',
                'submit' => '删除',
                'success' => '问题已删除。',
            ],
        ],
    ],

    'offers' => [
        'nav_label' => '优惠',
        'model_label_singular' => '优惠',
        'model_label_plural' => '优惠',

        'col_image' => '图片',
        'col_title' => '标题',
        'col_active' => '启用',
        'active_yes' => '启用',
        'active_no' => '停用',

        'section_classification' => '分类',
        'field_position' => '排序',
        'field_position_hint' => '小组件轮播中的显示顺序（数字小的在前）。',
        'field_is_active' => '启用',
        'field_is_active_hint' => '停用后该优惠不显示。只有至少存在一个启用的优惠时，"礼物盒"小组件才会显示。',
        'field_image' => '图片',
        'field_image_hint' => '优惠图片（webp、jpg 或 png；最大 2 MB）。打开礼盒时显示在弹窗中。',

        'lang' => [
            'es' => '西班牙语',
            'en' => '英语',
            'fr' => '法语',
        ],
        'field_title' => '标题',

        'create_title' => '创建优惠',
        'edit_title' => '编辑优惠：:name',

        'actions' => [
            'create' => '创建优惠',
            'delete' => [
                'label' => '删除优惠',
                'modal_heading' => '删除此优惠',
                'modal_description' => '此操作不可撤销。其图片也将一并删除。',
                'submit' => '删除',
                'success' => '优惠已删除。',
            ],
        ],
    ],

    'park_rules' => [
        'nav_label' => '园区规则',
        'model_label_singular' => '规则',
        'model_label_plural' => '园区规则',

        'col_name' => '规则',
        'col_active' => '启用',
        'active_yes' => '启用',
        'active_no' => '停用',

        'section_classification' => '分类',
        'field_position' => '排序',
        'field_position_hint' => '显示顺序（数字小的在前）。',
        'field_is_active' => '启用',
        'field_is_active_hint' => '停用后该规则不在落地页及 /normas 显示。',

        'lang' => [
            'es' => '西班牙语',
            'en' => '英语',
            'fr' => '法语',
        ],
        'field_name' => '标题',
        'field_description' => '描述',

        'create_title' => '创建规则',
        'edit_title' => '编辑规则：:name',

        'actions' => [
            'create' => '创建规则',
            'delete' => [
                'label' => '删除规则',
                'modal_heading' => '删除此规则',
                'modal_description' => '此操作不可撤销。',
                'submit' => '删除',
                'success' => '规则已删除。',
            ],
        ],
    ],

    'pages' => [
        'nav_label' => '法律页面',
        'model_label_singular' => '法律页面',
        'model_label_plural' => '法律页面',

        'col_slug' => '标识',
        'col_title' => '标题',
        'col_active' => '状态',
        'active_yes' => '已发布',
        'active_no' => '隐藏（404）',

        'section_settings' => '页面设置',
        'tokens_hint' => '正文中可使用以下占位符，显示页面时会自动替换：:legal_name（公司名称）、:legal_nif（税号）、:legal_address（地址）、:legal_email（邮箱）。在"配置 › 设置"中填写。',
        'field_slug' => '标识',
        'field_slug_hint' => '页面标识，与其网址绑定。不可编辑。',
        'field_is_active' => '已发布',
        'is_active_hint' => '隐藏后该页面返回 404，且会破坏页脚链接。除非正在重写，否则请保持发布。',

        'lang' => [
            'es' => '西班牙语',
            'en' => '英语',
            'fr' => '法语',
        ],
        'field_title' => '标题',
        'field_body' => '正文段落',
        'field_h' => '小标题（可选）',
        'field_p' => '段落',
        'add_section' => '添加段落',

        'edit_title' => '编辑页面：:name',

        'actions' => [
            'view' => '在网站查看',
        ],
    ],
];
