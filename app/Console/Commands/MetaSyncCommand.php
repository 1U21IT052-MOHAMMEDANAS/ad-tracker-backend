<?php

namespace App\Console\Commands;

use App\Services\Meta\MetaApiException;
use App\Services\Meta\MetaSync;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class MetaSyncCommand extends Command
{
    protected $signature = 'meta:sync';

    protected $description = 'Pull ad accounts, campaigns and insights from the Meta Marketing API';

    public function handle(MetaSync $sync): int
    {
        // Shared with the "Sync now" button so two syncs never run at once.
        $lock = Cache::lock('meta-sync', 600);
        if (! $lock->get()) {
            $this->warn('A Meta sync is already running.');

            return self::SUCCESS;
        }

        try {
            $result = $sync->run();
        } catch (MetaApiException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } finally {
            $lock->release();
        }

        $this->info("Synced {$result['accounts']} ad accounts and {$result['campaigns']} campaigns.");
        foreach ($result['errors'] as $error) {
            $this->warn($error);
        }

        return $result['errors'] ? self::FAILURE : self::SUCCESS;
    }
}
