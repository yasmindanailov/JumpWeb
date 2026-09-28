<?php

namespace App\Console\Commands;

use App\Domain\Platform\Models\EmailSend;
use Illuminate\Console\Command;

/**
 * **Borra la COPIA de los correos de más de seis meses** (`specs/correos-salientes.md` §4.1, `DECISIONES #794`,
 * `[DECIDIDO owner]`): tres correos llevan el nombre de un menor, y para atender una reclamación basta medio año. La fila y
 * sus cifras se quedan hasta los 24 meses (`model:prune`).
 */
class TrimEmailCopies extends Command
{
    protected $signature = 'email-sends:trim';

    protected $description = 'Borra la copia de los correos enviados hace más de seis meses (la fila y sus cifras se quedan).';

    public function handle(): int
    {
        $this->info(sprintf('Copias borradas: %d.', EmailSend::purgeOldCopies()));

        return self::SUCCESS;
    }
}
