<?php

namespace App\Domain\Booking\Services;

/**
 * Contrato ÚNICO de "qué es una línea de cesta válida".
 *
 * Antes esta normalización vivía DUPLICADA byte a byte en {@see OrderCreator::sanitize()}
 * (defensa server-side al crear el pedido) y en `Tickets\Purchase::sanitizeCart()` —el componente
 * Livewire, retirado en 4.7·2b·3, que saneaba la sesión al montar el wizard—. Tenerla en dos sitios
 * significaba que un cambio en la
 * forma de la línea (un campo nuevo, una regla de complementos) había que portarlo a ambos
 * o la cesta de sesión y la validación del alta dejaban de coincidir. Aquí es una sola fuente.
 *
 * NO valida disponibilidad ni precios (eso lo hace OrderCreator en servidor, con bloqueo de
 * aforo): solo deja líneas con el formato esperado, descartando ruido de cestas antiguas o
 * manipuladas.
 */
class Cart
{
    /**
     * Deja solo líneas con el formato esperado (defensa ante cestas antiguas o manipuladas).
     * Los complementos van ANIDADOS en cada línea (#87): solo producto + cantidad (>0).
     *
     * @param  array<mixed>  $cart
     * @return array<int, array{ticket_type_id:int, date:string, time:string, qty:int, event_data:array<string,mixed>, addons:array<int,array{ticket_type_id:int,qty:int}>}>
     */
    public static function sanitize(array $cart): array
    {
        $clean = [];
        foreach ($cart as $line) {
            if (is_array($line) && isset($line['ticket_type_id'], $line['date'], $line['time'], $line['qty'])) {
                $addons = [];
                foreach ($line['addons'] ?? [] as $addon) {
                    if (is_array($addon) && isset($addon['ticket_type_id'], $addon['qty']) && (int) $addon['qty'] > 0) {
                        $addons[] = ['ticket_type_id' => (int) $addon['ticket_type_id'], 'qty' => max(1, (int) $addon['qty'])];
                    }
                }
                $clean[] = [
                    'ticket_type_id' => (int) $line['ticket_type_id'],
                    'date' => (string) $line['date'],
                    'time' => (string) $line['time'],
                    'qty' => max(1, (int) $line['qty']),
                    // Respuestas del evento (solo packs, #86); se saneará contra el esquema del pack.
                    'event_data' => isset($line['event_data']) && is_array($line['event_data']) ? $line['event_data'] : [],
                    'addons' => $addons,
                ];
            }
        }

        return $clean;
    }
}
