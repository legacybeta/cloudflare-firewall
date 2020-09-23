<?php

namespace LegacyBeta\CloudflareFirewall;

use Illuminate\Support\ServiceProvider;
use LegacyBeta\CloudflareFirewall\Console\CloudflareGenerateFirewallRules;

class CloudflareFirewallServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->commands([
            CloudflareGenerateFirewallRules::class
        ]);
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/cloudflare-firewall.php' => config_path('cloudflare-firewall.php'),
            ], 'config');
        }
    }
}