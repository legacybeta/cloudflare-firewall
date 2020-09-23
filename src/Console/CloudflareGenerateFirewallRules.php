<?php

namespace LegacyBeta\CloudflareFirewall\Console;

use Cloudflare\API\Adapter\Guzzle;
use Cloudflare\API\Auth\APIKey;
use Cloudflare\API\Configurations\FirewallRuleOptions;
use Cloudflare\API\Endpoints\Firewall;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Console\Command;

class CloudflareGenerateFirewallRules extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cloudflare:generate-firewall-rules';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate Firewall Rules for allowed POST requests';

    const RuleDescriptionBlockPosts = 'Block all incoming POSTs';
    const RulePriorityBlockPost = 2;

    const RuleDescriptionAllowPosts = 'Allow specific POSTs';
    const RulePriorityAllowPosts = 1;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $key     = new APIKey(config('laravel-cloudflare-firewall-rules.email'), config('laravel-cloudflare-firewall-rules.api-key'));
        $adapter = new Guzzle($key);
        $firewall = new Firewall($adapter);

        $zoneId = config('laravel-cloudflare-firewall-rules.zone-id');

        $this->info("Zone Id: {$zoneId}");

        $this->info('Laravel POST routes:');
        foreach ($this->getPostUris() as $postUris) {
            $this->line($postUris);
        }

        $rules = $firewall->listFirewallRules($zoneId);
        // The rules aren't exist
        if (empty($rules)) {
            $this->info('The Firewall rules are empty');

            if (!$this->confirm('Do you want to create new firewall rules?')) {
                return;
            }

            try {
                $this->info(self::RuleDescriptionAllowPosts . ' - creating...');
                $this->line($this->getAllowRulesExpression());
                $options = new FirewallRuleOptions();
                $options->setActionAllow();
                $firewall->createFirewallRule($zoneId, $this->getAllowRulesExpression(), $options,
                    self::RuleDescriptionAllowPosts, self::RulePriorityAllowPosts);
                $this->info(self::RuleDescriptionAllowPosts . ' - was created');

                $this->info(self::RuleDescriptionBlockPosts . ' - creating...');
                $options = new FirewallRuleOptions();
                $options->setActionBlock();
                $firewall->createFirewallRule($zoneId, 'http.request.method eq "POST"', $options,
                    self::RuleDescriptionBlockPosts, self::RulePriorityBlockPost);
                $this->info(self::RuleDescriptionBlockPosts . ' - was created');
            } catch (ClientException $e) {
                $this->error($e->getResponse()->getBody()->getContents());
            }
        } else {
            $existRules = [];
            $allowRuleId = null;
            $allowFilterId = null;
            $this->info('Firewall rules');
            foreach ($rules->result as $index => $rule) {
                $this->info("Rule [{$index}]:");
                $this->line('RuleId: ' . $rule->id);
                $this->line('Description: ' . $rule->description);
                $this->line('FilterId: ' . $rule->filter->id);
                $this->line('FilterExpression: ' . $rule->filter->expression);

                $existRules[$rule->description] = $rule->filter->expression;
                if ($rule->description === self::RuleDescriptionAllowPosts) {
                    $allowRuleId = $rule->id;
                    $allowFilterId = $rule->filter->id;
                }
            }

            if (array_key_exists(self::RuleDescriptionAllowPosts, $existRules) && $allowRuleId !== null && $allowFilterId !== null) {
                // The new and exist rules are identical don't need to update them
                if ($existRules[self::RuleDescriptionAllowPosts] === $this->getAllowRulesExpression()) {
                    $this->question('The rules are up to date');
                } else {
                    // Update rule
                    if (!$this->confirm('The rules are different, do you want to update?')) {
                        return;
                    }

                    try {
                        $this->line($this->getAllowRulesExpression());
                        $options = new FirewallRuleOptions();
                        $options->setActionAllow();
                        $this->info(self::RuleDescriptionAllowPosts . ' - updating...');
                        $firewall->updateFirewallRule($zoneId, $allowRuleId, $allowFilterId, $this->getAllowRulesExpression(), $options,
                            self::RuleDescriptionAllowPosts, self::RulePriorityAllowPosts);
                        $this->question(self::RuleDescriptionAllowPosts . ' - was updated');
                    } catch (ClientException $e) {
                        $this->error($e->getResponse()->getBody()->getContents());
                    }
                }
            } else {
                // Create new rules
                if (!$this->confirm(sprintf(
                    'The rule "%s" and "%s" a don\'t exist, do you want to create new?',
                    self::RuleDescriptionAllowPosts, self::RuleDescriptionBlockPosts))) {
                    return;
                }

                try {
                    $this->info(self::RuleDescriptionAllowPosts . ' - creating...');
                    $this->line($this->getAllowRulesExpression());
                    $options = new FirewallRuleOptions();
                    $options->setActionAllow();
                    $firewall->createFirewallRule($zoneId, $this->getAllowRulesExpression(), $options,
                        self::RuleDescriptionAllowPosts, self::RulePriorityAllowPosts);
                    $this->question(self::RuleDescriptionAllowPosts . ' - was created');

                    $this->info(self::RuleDescriptionBlockPosts . ' - creating...');
                    $options = new FirewallRuleOptions();
                    $options->setActionBlock();
                    $firewall->createFirewallRule($zoneId, 'http.request.method eq "POST"', $options,
                        self::RuleDescriptionBlockPosts, self::RulePriorityBlockPost);
                    $this->info(self::RuleDescriptionBlockPosts . ' - was created');
                } catch (ClientException $e) {
                    $this->error($e->getResponse()->getBody()->getContents());
                }
            }
        }
    }

    protected function getPostUris()
    {
        $postRoutes = collect(Route::getRoutes())->filter(function ($route) {
            return in_array('POST', $route->methods());
        });
        $uris = $postRoutes->map(function ($route) {
            $uri = $route->uri();
            // Remove placeholders in routes like foo/{resource}/bar
            $startPlaceholder = strpos($uri, '{');
            if ($startPlaceholder !== false) {
                $uri = substr($uri, 0, $startPlaceholder);
            }
            return $uri;
        })->unique();
        return $uris;
    }

    protected function getAllowRulesExpression()
    {
        $uris = $this->getPostUris();
        $rulesExpression = '';
        $index = 0;
        foreach ($uris as $uri) {
            $rulesExpression .= sprintf('(http.request.uri.path contains "%s")%s', $uri, ($index === count($uris) - 1) ? '' : ' or ');
            $index++;
        }
        return $rulesExpression;
    }
}