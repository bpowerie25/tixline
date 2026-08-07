<?php

namespace App\Providers;

use App\Models\MailConfiguration;
use Illuminate\Support\ServiceProvider;

class MailConfigServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Skip during migrations/CLI install
        if ($this->app->runningInConsole() && ! $this->app->runningUnitTests()) {
            try {
                $this->applyMailConfig();
            } catch (\Throwable) {
                // Table may not exist yet
            }

            return;
        }

        $this->applyMailConfig();
    }

    protected function applyMailConfig(): void
    {
        $config = MailConfiguration::active();

        if (! $config) {
            return;
        }

        config([
            'mail.default' => $config->mailer,
            "mail.mailers.{$config->mailer}.transport" => $config->mailer,
            "mail.mailers.{$config->mailer}.host" => $config->host,
            "mail.mailers.{$config->mailer}.port" => $config->port,
            "mail.mailers.{$config->mailer}.encryption" => $config->encryption,
            "mail.mailers.{$config->mailer}.username" => $config->username,
            "mail.mailers.{$config->mailer}.password" => $config->password,
            'mail.from.address' => $config->from_address ?: config('mail.from.address'),
            'mail.from.name' => $config->from_name ?: config('mail.from.name'),
        ]);
    }
}
