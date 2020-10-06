# Laravel Cloudflare Firewall Rules
Create and Update Cloudflare Firewall Rules Based on your Laravel Application's Routes

Adds a new Laravel artisan a new command `cloudflare:generate-firewall-rules` that creates or updates two rules in Cloudflare Firewall:

1. Allow specific POSTs 
2. Block all incoming POSTs.

To install this package via composer:

1. Run the commend `composer require legacybeta/cloudflare-firewall`;
2. Then run `php artisan vendor:publish --provider="LegacyBeta\CloudflareFirewall\CloudflareFirewallServiceProvider"`;
3. Put the Cloudflare API email, API key and Zone Id in the .env for your project:

```
CF_EMAIL=
CF_API_KEY=
CF_ZONE_ID=
```

Blog post with a bit more details is [here](https://medium.com/legacybeta/laravel-cloudflare-firewall-rules-313dae4d0404). 
