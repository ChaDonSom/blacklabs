<?php

namespace App\Commands;

use Illuminate\Support\Facades\Storage;
use LaravelZero\Framework\Commands\Command;

class StoreForgeOrgId extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'app:store-forge-org-id {orgId : The organization ID from Laravel Forge}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Store your Forge organization ID. Required for commands that interact with Forge.';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        Storage::put('forge-org-id.txt', $this->argument('orgId'));
        $this->info('Forge organization ID stored successfully.');
    }
}
