<?php

namespace App\Console\Commands;

use App\Domain\Booking\Models\OrderItem;
use Illuminate\Console\Command;

/**
 * **Borra los datos de los invitados 14 días después de la visita** (`specs/textos-legales.md` §4.4, `DECISIONES #863`,
 * `[DECIDIDO owner]`): nombres y alergias de menores. El pedido conserva cantidades e importes.
 */
class ForgetPastGuests extends Command
{
    protected $signature = 'guest-data:forget';

    protected $description = 'Borra los datos de los invitados de las visitas de hace más de 14 días (el pedido se queda).';

    public function handle(): int
    {
        $this->info(sprintf('Líneas sin invitados: %d.', OrderItem::forgetGuestsOfPastVisits()));

        return self::SUCCESS;
    }
}
