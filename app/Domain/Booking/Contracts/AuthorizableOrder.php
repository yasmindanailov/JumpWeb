<?php

namespace App\Domain\Booking\Contracts;

use Carbon\CarbonImmutable;

/**
 * Un pedido visto por el subsistema del JUSTIFICANTE de un menor invitado
 * ({@see AuthorizableOrders}; `docs/specs/waiver-por-reserva.md` §4.6, §4.7).
 *
 * Solo lo que hace falta para decidir si una autorización cabe y para que el adulto que firma sepa
 * QUÉ está autorizando. Ni precios, ni productos, ni nombres de nadie: quien abre ese enlace es un
 * desconocido y **cada campo de aquí acaba delante de sus ojos**.
 */
final readonly class AuthorizableOrder
{
    /**
     * @param  int  $orderId  la llave estable del pedido
     * @param  string  $code  la referencia que el cliente ya conoce («R-AB12CD»): sin ella, el
     *                        justificante no dice a qué reserva pertenece
     * @param  bool  $isPaid  ⚠️ un pedido con SEÑAL **también** es `paid` —medido: 30,00 € cobrados
     *                        de 88,00 € de valor y el estado es `paid`—, así que exigirlo NO deja
     *                        fuera a las excursiones, que se venden con señal
     * @param  int  $capacity  Σ de las unidades de las líneas PRINCIPALES VIVAS. ⚠️ No es
     *                         `SUM(quantity)` a secas: eso cuenta complementos, los portadores de
     *                         suplemento de fiesta mixta y las líneas canceladas — medido, ocho
     *                         pedidos de la BD local divergen y uno íntegramente cancelado admitiría
     *                         dos autorizaciones
     * @param  bool  $visitFinished  todas sus líneas con fecha ya terminaron. `false` cuando NINGUNA
     *                               tiene franja: no ha pasado nada que cerrar
     * @param  CarbonImmutable  $linkExpiresAt  la última fecha + 14 días, la MISMA fuente que el
     *                                          enlace del post-form (`RGPD-03`)
     * @param  list<string>  $visitDates  los días de la visita (`Y-m-d`), sin repetir y ordenados
     */
    public function __construct(
        public int $orderId,
        public string $code,
        public bool $isPaid,
        public int $capacity,
        public bool $visitFinished,
        public CarbonImmutable $linkExpiresAt,
        public array $visitDates,
    ) {}
}
