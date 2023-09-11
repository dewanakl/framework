<?php

/**
 * Template untuk membuat file job dengan saya console.
 *
 * @return string
 */

return '<?php

namespace App\Jobs;

use Core\Queue\Job;

final class NAME extends Job
{
    /**
     * Eksekusi perintah ada disini.
     *
     * @return void
     */
    public function handle()
    {
        //
    }
}
';
