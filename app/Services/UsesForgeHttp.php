<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

trait UsesForgeHttp {
    public function getForgeHttpRequest() {
        $this->token ??= Storage::get('forge-api-token.txt');
        if (!$this->token) {
            $this->error('No API token found.');
            $this->warn('Please run `app:store-forge-api-token` with your Forge API Token first.');
            return;
        }

        return Http::withToken($this->token)->acceptJson()->contentType('application/json');
    }
}