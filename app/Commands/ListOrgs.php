<?php

namespace App\Commands;

use App\Services\UsesForgeHttp;
use Illuminate\Support\Facades\Storage;
use LaravelZero\Framework\Commands\Command;

class ListOrgs extends Command
{
    use UsesForgeHttp;

    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'app:list-orgs';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'List your Forge organizations. Need token first.';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->info('Fetching Forge organizations...');
        $request = $this->getForgeHttpRequest();
        $orgs = $request->get('https://forge.laravel.com/api/orgs')->getBody()->getContents();
        $orgs = collect(json_decode($orgs)->data);

        $this->table(['ID', 'Name', 'Slug'], $orgs->map(function ($org) {
            return [
                'id' => $org->id,
                'name' => $org->attributes->name,
                'slug' => $org->attributes->slug,
            ];
        }));
    }
}
