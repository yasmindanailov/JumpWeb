<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fase 2 (prerequisito morphMap, DEUDA §Alta): las columnas polimórficas guardaban el FQCN
 * (`App\Models\Order`…). Con `Relation::enforceMorphMap` (AppServiceProvider) los valores
 * nuevos son alias estables ('order'…); esta migración convierte los datos EXISTENTES para
 * que las consultas por alias (`where('payable_type', …getMorphClass())`) los vean.
 *
 * Idempotente y reversible. Cubre las 3 columnas polimórficas del esquema:
 * `payments.payable_type` · `prices.priceable_type` · `audit_logs.target_type`.
 */
return new class extends Migration
{
    /**
     * ⛔ **CONGELADO — no reescribir estos FQCN.** Son los valores que la BD tenía GUARDADOS
     * en 2026-08-12, no rutas de código: describen el pasado, no el presente. La modularización
     * de Fase 2 mueve las clases (`App\Domain\<Módulo>\Models\…`), pero las filas antiguas
     * siguen diciendo `App\Models\…` y es a ESAS a las que esta migración tiene que llegar.
     * Un `sed` global las rompería EN SILENCIO (la migración es idempotente: no fallaría, solo
     * dejaría las filas legacy sin convertir). Lo vigila `MorphMapTest`.
     *
     * @var array<string, string> alias => FQCN histórico
     */
    private const MAP = [
        'attraction' => 'App\Models\Attraction',
        'audit_log' => 'App\Models\AuditLog',
        'consent' => 'App\Models\Consent',
        'cookie_consent_log' => 'App\Models\CookieConsentLog',
        'faq' => 'App\Models\Faq',
        'landing_service' => 'App\Models\LandingService',
        'offer' => 'App\Models\Offer',
        'opening_hour' => 'App\Models\OpeningHour',
        'order' => 'App\Models\Order',
        'order_adjustment' => 'App\Models\OrderAdjustment',
        'order_item' => 'App\Models\OrderItem',
        'page' => 'App\Models\Page',
        'park_rule' => 'App\Models\ParkRule',
        'payment' => 'App\Models\Payment',
        'payment_refund' => 'App\Models\PaymentRefund',
        'permission' => 'App\Models\Permission',
        'price' => 'App\Models\Price',
        'product_addon' => 'App\Models\ProductAddon',
        'rate_type' => 'App\Models\RateType',
        'role' => 'App\Models\Role',
        'room' => 'App\Models\Room',
        'season' => 'App\Models\Season',
        'setting' => 'App\Models\Setting',
        'slot' => 'App\Models\Slot',
        'slot_template' => 'App\Models\SlotTemplate',
        'special_date' => 'App\Models\SpecialDate',
        'ticket' => 'App\Models\Ticket',
        'ticket_type' => 'App\Models\TicketType',
        'user' => 'App\Models\User',
        'zone' => 'App\Models\Zone',
    ];

    private const COLUMNS = [
        'payments' => 'payable_type',
        'prices' => 'priceable_type',
        'audit_logs' => 'target_type',
    ];

    public function up(): void
    {
        foreach (self::COLUMNS as $table => $column) {
            foreach (self::MAP as $alias => $fqcn) {
                DB::table($table)->where($column, $fqcn)->update([$column => $alias]);
            }
        }
    }

    public function down(): void
    {
        foreach (self::COLUMNS as $table => $column) {
            foreach (self::MAP as $alias => $fqcn) {
                DB::table($table)->where($column, $alias)->update([$column => $fqcn]);
            }
        }
    }
};
