<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Storage;
use LaravelZero\Framework\Commands\Command;

class StoreForgeApiToken extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'app:store-forge-api-token {token : The API token for Laravel Forge}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Store a new API token for Laravel Forge. Required for commands that interact with Forge.';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        Storage::put('forge-api-token.txt', $this->argument('token'));
        $this->info('API token stored successfully.');
    }

    /**
     * Define the command's schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    public function schedule(Schedule $schedule): void
    {
        // $schedule->command(static::class)->everyMinute();
    }
}
