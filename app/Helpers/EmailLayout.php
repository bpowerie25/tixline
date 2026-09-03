<?php

namespace App\Helpers;

use App\Models\Tenant;
use App\Models\Ticket;

class EmailLayout
{
    public static function portalUrl(?Tenant $tenant = null): string
    {
        $tenant ??= app()->bound('tenant') ? app('tenant') : null;

        if ($tenant?->domain) {
            $scheme = request()?->isSecure() ? 'https' : 'http';

            return "{$scheme}://{$tenant->domain}/portal/login";
        }

        return url('/portal/login');
    }

    public static function agentTicketUrl(Ticket $ticket, ?Tenant $tenant = null): string
    {
        $tenant ??= app()->bound('tenant') ? app('tenant') : null;

        if ($tenant?->domain) {
            $scheme = request()?->isSecure() ? 'https' : 'http';

            return "{$scheme}://{$tenant->domain}/tickets/{$ticket->id}";
        }

        return url("/tickets/{$ticket->id}");
    }

    public static function portalTicketUrl(Ticket $ticket, ?Tenant $tenant = null): string
    {
        $tenant ??= app()->bound('tenant') ? app('tenant') : null;

        if ($tenant?->domain) {
            $scheme = request()?->isSecure() ? 'https' : 'http';

            return "{$scheme}://{$tenant->domain}/portal/tickets/{$ticket->id}";
        }

        return url("/portal/tickets/{$ticket->id}");
    }

    public static function wrap(string $body, ?string $portalUrl = null, ?Tenant $tenant = null): string
    {
        $tenant ??= app()->bound('tenant') ? app('tenant') : null;
        $tenantName = e($tenant?->name ?? config('app.name', 'Support'));
        $primaryColor = $tenant?->primary_color ?? '#be123c';
        $logoUrl = $tenant?->logo_url;
        if ($logoUrl && ! str_starts_with($logoUrl, 'http')) {
            $baseUrl = $tenant?->domain
                ? ((request()?->isSecure() ? 'https' : 'http') . '://' . $tenant->domain)
                : config('app.url');
            $logoUrl = rtrim($baseUrl, '/') . '/' . ltrim($logoUrl, '/');
        }

        $logoHtml = $logoUrl
            ? '<img src="' . e($logoUrl) . '" alt="' . $tenantName . '" style="max-height: 40px; max-width: 200px;" />'
            : '<span style="font-size: 20px; font-weight: 700; color: ' . $primaryColor . ';">' . $tenantName . '</span>';

        $portalLinkHtml = '';
        if ($portalUrl) {
            $portalLinkHtml = <<<HTML
            <p style="margin: 24px 0 0;">
                <a href="{$portalUrl}" style="display: inline-block; background: {$primaryColor}; color: #fff; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-weight: 600;">
                    View your tickets
                </a>
            </p>
            HTML;
        }

        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head><meta charset="utf-8" /></head>
        <body style="margin: 0; padding: 0; background-color: #f3f4f6;">
            <div style="font-family: sans-serif; max-width: 600px; margin: 0 auto; padding: 24px 0;">
                <!-- Header -->
                <div style="text-align: center; padding: 24px 0;">
                    {$logoHtml}
                </div>

                <!-- Body -->
                <div style="background: #ffffff; border-radius: 8px; padding: 32px; margin: 0 16px;">
                    {$body}
                    {$portalLinkHtml}
                </div>

                <!-- Footer -->
                <div style="text-align: center; padding: 24px 16px; color: #9ca3af; font-size: 12px;">
                    &copy; {$tenantName}
                </div>
            </div>
        </body>
        </html>
        HTML;
    }
}
