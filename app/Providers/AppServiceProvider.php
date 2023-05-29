<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Set logging path per Phar or not
        config([
            'logging.channels.single.path' => \Phar::running()
                ? dirname(\Phar::running(false)) . '/logs/blacklabs.log'
                : storage_path('logs/blacklabs.log')
        ]);

        // Set cache path per Phar or not
        config([
            'cache.stores.file.path' => \Phar::running()
                ? dirname(\Phar::running(false)) . '/cache/data'
                : storage_path('framework/cache/data')
        ]);

        // Set filesystem path per Phar or not
        config([
            'filesystems.disks.local.root' => \Phar::running()
                ? dirname(\Phar::running(false)) . '/app'
                : storage_path('app')
        ]);
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }
}
