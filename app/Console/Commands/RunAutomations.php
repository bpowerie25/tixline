<?php

namespace App\Console\Commands;

use App\Services\AutomationEngine;
use Illuminate\Console\Command;

class RunAutomations extends Command
{
    protected $signature = 'support:run-automations';

    protected $description = 'Run time-based automations against eligible tickets';

    public function handle(AutomationEngine $engine): int
    {
        $results = $engine->runAll();

        if (empty($results)) {
            $this->info('No automations triggered.');
        } else {
            foreach ($results as $result) {
                $this->line("  {$result['automation']} -> {$result['ticket']}");
            }
            $this->info(count($results).' automation(s) triggered.');
        }

        return self::SUCCESS;
    }
}
