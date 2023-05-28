<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use CzProject\GitPhp\Git;

class GitServiceProvider extends ServiceProvider {
    /**
     * Register services.
     *
     * @return void
     */
    public function register() {
        $this->app->singleton(Git::class, function ($app) {
            return new Git;
        });
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot() {
        //
    }
}
