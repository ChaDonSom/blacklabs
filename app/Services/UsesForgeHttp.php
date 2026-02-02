<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

trait UsesForgeHttp {
    protected $token;
    
    public function getForgeHttpRequest() {
        $this->token ??= Storage::get('forge-api-token.txt');
        if (!$this->token) {
            if (!app()->runningUnitTests()) {
                $this->error('No API token found.');
                $this->warn('Please run `app:store-forge-api-token` with your Forge API Token first.');
            }
            return Http::acceptJson()->contentType('application/json');
        }

        return Http::withToken($this->token)->acceptJson()->contentType('application/json');
    }
}
