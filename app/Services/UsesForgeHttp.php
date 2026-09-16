<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

trait UsesForgeHttp
{
    protected $token;

    protected $orgId;

    public function getForgeHttpRequest()
    {
        $this->token ??= Storage::get('forge-api-token.txt');
        if (! $this->token) {
            if (! app()->runningUnitTests()) {
                $this->error('No API token found.');
                $this->warn('Please run `app:store-forge-api-token` with your Forge API Token first.');
            }

            return Http::acceptJson()->contentType('application/json');
        }

        return Http::withToken($this->token)->acceptJson()->contentType('application/json');
    }

    public function getForgeOrgId()
    {
        $this->orgId ??= Storage::get('forge-org-id.txt');
        if (! $this->orgId) {
            if (! app()->runningUnitTests()) {
                $this->error('No Forge organization ID found.');
                $this->warn('Please run `app:store-forge-org-id` with your Forge organization ID first.');
            }

            return null;
        }

        return trim($this->orgId);
    }
}
