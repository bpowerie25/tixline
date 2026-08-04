<?php

namespace Database\Seeders;

use App\Models\Form;
use App\Models\Label;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Workflow;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command->error('Seeder cannot run in production.');

            return;
        }

        // Teams
        $support = Team::create(['name' => 'General Support', 'slug' => 'general-support', 'color' => '#6366f1']);
        $billing = Team::create(['name' => 'Billing', 'slug' => 'billing', 'color' => '#f59e0b']);
        $technical = Team::create(['name' => 'Technical', 'slug' => 'technical', 'color' => '#10b981']);

        // Admin
        $admin = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'role' => 'admin',
            'team_id' => null,
        ]);

        // Agents
        $agent1 = User::factory()->create([
            'name' => 'Sarah Chen',
            'email' => 'sarah@example.com',
            'role' => 'agent',
            'team_id' => $support->id,
        ]);

        $agent2 = User::factory()->create([
            'name' => 'Marcus Johnson',
            'email' => 'marcus@example.com',
            'role' => 'agent',
            'team_id' => $billing->id,
        ]);

        $agent3 = User::factory()->create([
            'name' => 'Emily Park',
            'email' => 'emily@example.com',
            'role' => 'agent',
            'team_id' => $technical->id,
        ]);

        // Labels
        $bugLabel = Label::create(['name' => 'Bug', 'slug' => 'bug', 'color' => '#ef4444']);
        $featureLabel = Label::create(['name' => 'Feature Request', 'slug' => 'feature-request', 'color' => '#8b5cf6']);
        $questionLabel = Label::create(['name' => 'Question', 'slug' => 'question', 'color' => '#3b82f6']);
        $urgentLabel = Label::create(['name' => 'Urgent', 'slug' => 'urgent', 'color' => '#dc2626']);

        // Workflows
        Workflow::create([
            'name' => 'Auto-assign billing tickets',
            'description' => 'Route billing-related tickets to the billing team',
            'trigger_event' => 'ticket_created',
            'conditions' => [
                'match' => 'any',
                'rules' => [
                    ['field' => 'subject', 'operator' => 'contains', 'value' => 'billing'],
                    ['field' => 'subject', 'operator' => 'contains', 'value' => 'invoice'],
                    ['field' => 'subject', 'operator' => 'contains', 'value' => 'payment'],
                ],
            ],
            'actions' => [
                ['type' => 'assign_to_team', 'value' => $billing->id],
                ['type' => 'round_robin', 'value' => $billing->id],
            ],
            'is_active' => true,
            'priority' => 10,
        ]);

        Workflow::create([
            'name' => 'Escalate urgent tickets',
            'description' => 'Set priority to urgent for critical keywords',
            'trigger_event' => 'ticket_created',
            'conditions' => [
                'match' => 'any',
                'rules' => [
                    ['field' => 'subject', 'operator' => 'contains', 'value' => 'down'],
                    ['field' => 'subject', 'operator' => 'contains', 'value' => 'emergency'],
                    ['field' => 'subject', 'operator' => 'contains', 'value' => 'critical'],
                ],
            ],
            'actions' => [
                ['type' => 'set_priority', 'value' => 'urgent'],
                ['type' => 'add_label', 'value' => $urgentLabel->id],
            ],
            'is_active' => true,
            'priority' => 20,
        ]);

        Workflow::create([
            'name' => 'Round-robin general support',
            'description' => 'Distribute unassigned tickets across the support team',
            'trigger_event' => 'ticket_created',
            'conditions' => ['match' => 'all', 'rules' => []],
            'actions' => [
                ['type' => 'assign_to_team', 'value' => $support->id],
                ['type' => 'round_robin', 'value' => $support->id],
            ],
            'is_active' => true,
            'priority' => 0,
        ]);

        // Form with conditional fields
        $form = Form::create([
            'name' => 'Bug Report',
            'slug' => 'bug-report',
            'description' => 'Report a bug or issue',
            'is_active' => true,
        ]);

        $form->fields()->createMany([
            [
                'name' => 'category',
                'label' => 'Category',
                'type' => 'select',
                'options' => ['UI/Design', 'Performance', 'Crash/Error', 'Data Loss', 'Other'],
                'is_required' => true,
                'sort_order' => 0,
            ],
            [
                'name' => 'browser',
                'label' => 'Browser',
                'type' => 'select',
                'options' => ['Chrome', 'Firefox', 'Safari', 'Edge', 'Other'],
                'is_required' => false,
                'sort_order' => 1,
                'conditions' => ['field' => 'category', 'operator' => 'equals', 'value' => 'UI/Design'],
            ],
            [
                'name' => 'error_message',
                'label' => 'Error Message',
                'type' => 'textarea',
                'is_required' => true,
                'sort_order' => 2,
                'conditions' => ['field' => 'category', 'operator' => 'equals', 'value' => 'Crash/Error'],
            ],
            [
                'name' => 'steps_to_reproduce',
                'label' => 'Steps to Reproduce',
                'type' => 'textarea',
                'is_required' => false,
                'sort_order' => 3,
            ],
            [
                'name' => 'severity',
                'label' => 'Severity',
                'type' => 'radio',
                'options' => ['Minor', 'Major', 'Critical'],
                'is_required' => true,
                'sort_order' => 4,
            ],
        ]);

        // Sample tickets
        $sampleTickets = [
            ['subject' => 'Cannot login to my account', 'body' => '<p>I\'ve been trying to login since this morning but keep getting a 500 error. I\'ve tried clearing cookies and using incognito mode.</p>', 'requester_name' => 'John Smith', 'requester_email' => 'john@acme.com', 'status' => 'open', 'priority' => 'high', 'assigned_to' => $agent1->id, 'team_id' => $support->id],
            ['subject' => 'Invoice #4521 is incorrect', 'body' => '<p>The amount on my latest invoice doesn\'t match what was quoted. Could you please check?</p>', 'requester_name' => 'Lisa Wang', 'requester_email' => 'lisa@widgets.co', 'status' => 'open', 'priority' => 'normal', 'assigned_to' => $agent2->id, 'team_id' => $billing->id],
            ['subject' => 'Feature request: dark mode', 'body' => '<p>Would love to see a dark mode option for the dashboard. Light theme is too bright for late-night work.</p>', 'requester_name' => 'Alex Turner', 'requester_email' => 'alex@startup.io', 'status' => 'pending', 'priority' => 'low', 'assigned_to' => null, 'team_id' => $support->id],
            ['subject' => 'API rate limits too restrictive', 'body' => '<p>Our integration is hitting the 100 req/min limit regularly. Can this be increased for enterprise plans?</p>', 'requester_name' => 'Dev Team', 'requester_email' => 'dev@bigcorp.com', 'status' => 'open', 'priority' => 'normal', 'assigned_to' => $agent3->id, 'team_id' => $technical->id],
            ['subject' => 'Site is down - CRITICAL', 'body' => '<p>Our entire site has been down for the last 15 minutes. Getting 502 errors across all pages.</p>', 'requester_name' => 'Ops Team', 'requester_email' => 'ops@bigcorp.com', 'status' => 'open', 'priority' => 'urgent', 'assigned_to' => $agent3->id, 'team_id' => $technical->id],
            ['subject' => 'How do I export my data?', 'body' => '<p>I need to export all my records as CSV for an audit. Where is this option?</p>', 'requester_name' => 'Mary Johnson', 'requester_email' => 'mary@nonprofit.org', 'status' => 'resolved', 'priority' => 'normal', 'assigned_to' => $agent1->id, 'team_id' => $support->id, 'resolved_at' => now()],
            ['subject' => 'Payment method not updating', 'body' => '<p>Trying to update my credit card but the save button does nothing.</p>', 'requester_name' => 'Tom Brown', 'requester_email' => 'tom@freelancer.dev', 'status' => 'open', 'priority' => 'high', 'assigned_to' => $agent2->id, 'team_id' => $billing->id],
        ];

        foreach ($sampleTickets as $data) {
            $ticket = Ticket::create(array_merge($data, ['source' => 'web']));

            if (str_contains(strtolower($data['subject']), 'feature')) {
                $ticket->labels()->attach($featureLabel);
            }
            if ($data['priority'] === 'urgent') {
                $ticket->labels()->attach($urgentLabel);
            }
            if (str_contains(strtolower($data['body']), 'error') || str_contains(strtolower($data['subject']), 'cannot')) {
                $ticket->labels()->attach($bugLabel);
            }

            if (in_array($data['status'], ['pending', 'resolved'])) {
                $ticket->comments()->create([
                    'user_id' => $data['assigned_to'] ?? $agent1->id,
                    'body' => '<p>Thanks for reaching out! I\'m looking into this and will get back to you shortly.</p>',
                    'type' => 'reply',
                    'is_internal' => false,
                ]);
                $ticket->update(['first_responded_at' => now()->subHours(2)]);
            }
        }
    }
}
