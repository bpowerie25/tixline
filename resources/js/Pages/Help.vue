<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';

const activeTab = ref('general');
</script>

<template>
    <Head title="Help Guide" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Help Guide</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-4xl sm:px-6 lg:px-8 space-y-8">

                <!-- Tabs -->
                <div class="border-b border-gray-200">
                    <nav class="-mb-px flex space-x-8">
                        <button
                            @click="activeTab = 'general'"
                            :class="[
                                'whitespace-nowrap border-b-2 py-3 px-1 text-sm font-medium',
                                activeTab === 'general'
                                    ? 'border-indigo-500 text-indigo-600'
                                    : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'
                            ]"
                        >
                            General
                        </button>
                        <button
                            @click="activeTab = 'technical'"
                            :class="[
                                'whitespace-nowrap border-b-2 py-3 px-1 text-sm font-medium',
                                activeTab === 'technical'
                                    ? 'border-indigo-500 text-indigo-600'
                                    : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'
                            ]"
                        >
                            Technical
                        </button>
                    </nav>
                </div>

                <!-- ==================== GENERAL TAB ==================== -->
                <template v-if="activeTab === 'general'">

                    <!-- Getting Started -->
                    <div class="bg-white shadow-sm sm:rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Getting Started</h3>
                        <p class="text-gray-600 mb-3">
                            Welcome to Tixline. This guide covers everything you need to know to manage support tickets effectively.
                        </p>
                        <ul class="list-disc list-inside text-gray-600 space-y-1">
                            <li>Your <strong>Dashboard</strong> shows an overview of open, pending, and resolved tickets.</li>
                            <li>Click any stat card to jump to the filtered ticket list.</li>
                            <li>Use the <strong>Tickets</strong> tab to browse and manage all tickets visible to you.</li>
                        </ul>
                    </div>

                    <!-- How Tickets Arrive -->
                    <div class="bg-white shadow-sm sm:rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">How Tickets Arrive</h3>
                        <p class="text-gray-600 mb-3">
                            Tickets enter the system in three ways:
                        </p>
                        <ul class="list-disc list-inside text-gray-600 space-y-2">
                            <li><strong>Customer Portal</strong> &mdash; customers log in and submit a ticket, choosing the relevant category. These are routed to the correct team automatically.</li>
                            <li><strong>Email</strong> &mdash; emails sent to a support address are converted into tickets. Because the system cannot determine the correct team from an email alone, <strong>all email tickets are assigned to General Support</strong> by default. These may need to be reassigned to the appropriate team manually.</li>
                            <li><strong>Created by an agent</strong> &mdash; agents can create tickets on behalf of a customer from the Tickets page.</li>
                        </ul>
                        <div class="mt-4 rounded-md bg-blue-50 p-4">
                            <p class="text-sm text-blue-700">
                                <strong>Tip:</strong> We recommend directing customers to submit tickets through the portal where possible. This ensures tickets are categorised correctly and assigned to the right team from the start.
                            </p>
                        </div>
                    </div>

                    <!-- Working with Tickets -->
                    <div class="bg-white shadow-sm sm:rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Working with Tickets</h3>

                        <h4 class="font-medium text-gray-800 mt-4 mb-2">Viewing Tickets</h4>
                        <p class="text-gray-600 mb-3">
                            The ticket list shows all tickets you have access to. Use the filters at the top to narrow by status, priority, team, or assignee. Click any ticket to open the full conversation.
                        </p>

                        <h4 class="font-medium text-gray-800 mt-4 mb-2">Ticket Statuses</h4>
                        <div class="grid grid-cols-2 gap-2 mb-3">
                            <div class="flex items-center gap-2">
                                <span class="inline-flex rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">open</span>
                                <span class="text-sm text-gray-600">New or reopened, needs attention</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="inline-flex rounded-full bg-yellow-100 px-2 py-0.5 text-xs font-medium text-yellow-700">pending</span>
                                <span class="text-sm text-gray-600">Waiting on the customer</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="inline-flex rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700">resolved</span>
                                <span class="text-sm text-gray-600">Issue addressed, awaiting confirmation</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">closed</span>
                                <span class="text-sm text-gray-600">Completed, no further action needed</span>
                            </div>
                        </div>

                        <h4 class="font-medium text-gray-800 mt-4 mb-2">Priority Levels</h4>
                        <div class="grid grid-cols-2 gap-2 mb-3">
                            <div class="flex items-center gap-2">
                                <span class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">low</span>
                                <span class="text-sm text-gray-600">General enquiry, no urgency</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="inline-flex rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700">normal</span>
                                <span class="text-sm text-gray-600">Standard request</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="inline-flex rounded-full bg-orange-100 px-2 py-0.5 text-xs font-medium text-orange-700">high</span>
                                <span class="text-sm text-gray-600">Impacting work, needs prompt action</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="inline-flex rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700">urgent</span>
                                <span class="text-sm text-gray-600">Critical issue, immediate response required</span>
                            </div>
                        </div>

                        <h4 class="font-medium text-gray-800 mt-4 mb-2">Replying to Tickets</h4>
                        <ul class="list-disc list-inside text-gray-600 space-y-1">
                            <li><strong>Reply</strong> sends a response to the customer via email.</li>
                            <li><strong>Reply &amp; Close</strong> sends the reply and closes the ticket in one step.</li>
                            <li><strong>Internal Note</strong> adds a private comment only visible to agents (highlighted in yellow).</li>
                            <li>You can attach up to 5 files (max 10MB each) to any reply or note.</li>
                        </ul>

                        <h4 class="font-medium text-gray-800 mt-4 mb-2">Canned Responses</h4>
                        <p class="text-gray-600">
                            Click the <strong>Canned Response</strong> button below the reply box to insert a pre-written template. Variables like <code class="bg-gray-100 px-1 rounded text-sm" v-pre>{{requester_name}}</code> and <code class="bg-gray-100 px-1 rounded text-sm" v-pre>{{ticket_reference}}</code> are automatically filled in.
                        </p>

                        <h4 class="font-medium text-gray-800 mt-4 mb-2">Updating Ticket Details</h4>
                        <p class="text-gray-600">
                            Use the sidebar on a ticket to change the status, priority, team assignment, agent assignment, or labels. Click <strong>Update</strong> to save your changes.
                        </p>
                    </div>

                    <!-- Requester History -->
                    <div class="bg-white shadow-sm sm:rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Requester History</h3>
                        <p class="text-gray-600 mb-3">
                            When viewing a ticket, the sidebar shows the requester's previous tickets under <strong>Requester History</strong>. This lets you see what the customer has contacted about before without leaving the current ticket.
                        </p>
                        <ul class="list-disc list-inside text-gray-600 space-y-1">
                            <li>Up to 10 recent tickets are shown, each with a reference number, subject, status badge, and date.</li>
                            <li>Click any ticket in the list to open it. The back arrow will return you to the ticket you came from.</li>
                            <li>If the requester has no other tickets, the panel shows <strong>No previous tickets</strong>.</li>
                        </ul>

                        <h4 class="font-medium text-gray-800 mt-4 mb-2">Requester Profile</h4>
                        <p class="text-gray-600">
                            Click the requester's <strong>name</strong> in the sidebar to open their full profile page. This shows all tickets from that requester in a searchable, paginated list &mdash; useful for reviewing a customer's complete support history.
                        </p>
                    </div>

                    <!-- Reassigning Tickets -->
                    <div class="bg-white shadow-sm sm:rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Reassigning Tickets</h3>
                        <p class="text-gray-600 mb-3">
                            If a ticket has landed in your queue but belongs to another team or agent, you should reassign it so the right people can handle it.
                        </p>

                        <h4 class="font-medium text-gray-800 mt-4 mb-2">Reassigning a Single Ticket</h4>
                        <ol class="list-decimal list-inside text-gray-600 space-y-1">
                            <li>Open the ticket.</li>
                            <li>In the sidebar, use the <strong>Team</strong> dropdown to move it to the correct team.</li>
                            <li>Optionally, use the <strong>Assigned to</strong> dropdown to assign it to a specific agent.</li>
                            <li>Click <strong>Update</strong> to save.</li>
                        </ol>

                        <h4 class="font-medium text-gray-800 mt-4 mb-2">Reassigning Multiple Tickets (Bulk Assign)</h4>
                        <ol class="list-decimal list-inside text-gray-600 space-y-1">
                            <li>On the ticket list, tick the checkbox next to each ticket you want to reassign.</li>
                            <li>A bulk action bar will appear at the top showing how many tickets are selected.</li>
                            <li>Click <strong>Assign</strong> and choose the target team and/or agent.</li>
                            <li>Confirm to apply the change to all selected tickets.</li>
                        </ol>

                        <div class="mt-4 rounded-md bg-yellow-50 p-4">
                            <p class="text-sm text-yellow-700">
                                <strong>Note:</strong> If you receive a ticket that falls outside your team's responsibilities, please reassign it rather than leaving it unactioned. If you're unsure where it should go, reassign it to <strong>General Support</strong> and it will be routed from there.
                            </p>
                        </div>
                    </div>

                    <!-- Bulk Actions -->
                    <div class="bg-white shadow-sm sm:rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Bulk Actions</h3>
                        <p class="text-gray-600 mb-3">
                            You can perform actions on multiple tickets at once from the ticket list. Select tickets using the checkboxes, then choose an action from the bulk action bar:
                        </p>
                        <ul class="list-disc list-inside text-gray-600 space-y-1">
                            <li><strong>Assign</strong> &mdash; reassign selected tickets to a different team or agent.</li>
                            <li><strong>Resolve</strong> &mdash; mark all selected tickets as resolved.</li>
                            <li><strong>Close</strong> &mdash; close all selected tickets.</li>
                            <li><strong>Delete</strong> &mdash; permanently remove the selected tickets.</li>
                            <li><strong>Spam</strong> &mdash; mark as spam, blocklist the senders, and delete the tickets.</li>
                        </ul>
                        <p class="text-gray-600 mt-3 text-sm">
                            Use the <strong>Select All</strong> checkbox in the table header to select all tickets on the current page.
                        </p>
                    </div>

                    <!-- Auto-reopen -->
                    <div class="bg-white shadow-sm sm:rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Auto-Reopen</h3>
                        <p class="text-gray-600">
                            When a customer replies to a <strong>resolved</strong> or <strong>closed</strong> ticket (via email or the customer portal), the ticket is automatically reopened with an <strong>open</strong> status so it reappears in your queue.
                        </p>
                    </div>

                    <!-- SLA -->
                    <div class="bg-white shadow-sm sm:rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">SLA Tracking</h3>
                        <p class="text-gray-600 mb-3">
                            Tickets may have SLA policies applied based on their priority. The SLA badge on each ticket shows the current status:
                        </p>
                        <ul class="list-disc list-inside text-gray-600 space-y-1">
                            <li><strong class="text-green-600">On track</strong> &mdash; response and resolution deadlines are not near.</li>
                            <li><strong class="text-yellow-600">Warning</strong> &mdash; a deadline is approaching.</li>
                            <li><strong class="text-red-600">Breached</strong> &mdash; a deadline has been missed. Address these first.</li>
                        </ul>
                        <p class="text-gray-600 mt-3">
                            Response and resolution due dates are shown in the ticket sidebar.
                        </p>
                    </div>

                    <!-- Teams -->
                    <div class="bg-white shadow-sm sm:rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Teams &amp; Departments</h3>
                        <p class="text-gray-600 mb-3">
                            Tickets are organised by <strong>teams</strong> (e.g. General Support, Admissions, IT). Each team has its own queue of tickets. You will see tickets assigned to your team(s) in your ticket list.
                        </p>
                        <ul class="list-disc list-inside text-gray-600 space-y-1">
                            <li>Teams are grouped under <strong>departments</strong> for broader organisational structure.</li>
                            <li>Your visibility depends on your role &mdash; agents see their own team tickets, while managers and admins have wider access.</li>
                            <li>When reassigning a ticket, choose the team that best matches the issue.</li>
                        </ul>
                    </div>

                    <!-- Workflows (simplified) -->
                    <div class="bg-white shadow-sm sm:rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Workflows (Automation)</h3>
                        <p class="text-gray-600 mb-3">
                            Workflows are automated rules that run when tickets are created or updated. They can automatically assign tickets to teams, set priorities, add labels, and more.
                        </p>
                        <p class="text-gray-600">
                            You don't need to configure workflows yourself &mdash; these are set up by admins. Just be aware that some ticket routing and labelling may happen automatically.
                        </p>
                    </div>

                    <!-- Knowledge Base -->
                    <div class="bg-white shadow-sm sm:rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Knowledge Base</h3>
                        <p class="text-gray-600 mb-3">
                            The knowledge base is a collection of help articles that customers can search and browse. When replying to tickets, consider linking to relevant articles to help customers find answers faster.
                        </p>
                        <ul class="list-disc list-inside text-gray-600 space-y-1">
                            <li>Articles are organised by category.</li>
                            <li>Team leads and above can create and manage articles from the <strong>KB</strong> tab.</li>
                            <li>Articles support <strong>draft</strong> and <strong>published</strong> states &mdash; drafts are not visible to customers.</li>
                        </ul>
                    </div>

                    <!-- Customer Portal -->
                    <div class="bg-white shadow-sm sm:rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Customer Portal</h3>
                        <p class="text-gray-600 mb-3">
                            Customers can access the portal to submit and track their tickets. From the portal, customers can:
                        </p>
                        <ul class="list-disc list-inside text-gray-600 space-y-1">
                            <li>View all their submitted tickets and check statuses</li>
                            <li>Reply to existing tickets</li>
                            <li>Submit new tickets and choose the relevant category</li>
                        </ul>
                        <p class="text-gray-600 mt-3">
                            A portal account is <strong>automatically created</strong> when a customer submits their first ticket (via the web form or email). They receive their login credentials by email.
                        </p>
                    </div>

                    <!-- Roles -->
                    <div class="bg-white shadow-sm sm:rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Roles &amp; Permissions</h3>
                        <p class="text-gray-600 mb-3">
                            There are four built-in roles. Admins can also create <strong>custom roles</strong> with any combination of permissions via the <strong>Roles</strong> page.
                        </p>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm text-left">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-2 font-medium text-gray-700">Role</th>
                                        <th class="px-4 py-2 font-medium text-gray-700">Access</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <tr>
                                        <td class="px-4 py-2 font-medium text-gray-900">Agent</td>
                                        <td class="px-4 py-2 text-gray-600">View &amp; reply to assigned/team tickets, assign tickets, use canned responses</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-2 font-medium text-gray-900">Team Lead</td>
                                        <td class="px-4 py-2 text-gray-600">Everything agents can do, plus reports (including custom reports), Knowledge Base management, bulk actions, and ticket deletion</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-2 font-medium text-gray-900">Group Manager</td>
                                        <td class="px-4 py-2 text-gray-600">Same as Team Lead with broader ticket visibility across the department</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-2 font-medium text-gray-900">Admin</td>
                                        <td class="px-4 py-2 text-gray-600">Full access: teams, agents, roles, labels, workflows, forms, SLA policies, mail settings, departments, activity logs, tenant settings</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4 rounded-md bg-blue-50 p-4">
                            <p class="text-sm text-blue-700">
                                <strong>Tip:</strong> If a team (e.g. QA) needs access to reports but the default Agent role doesn't include it, create a custom role with the permissions they need. Go to <strong>Roles &gt; Create Role</strong>, tick the relevant permissions, then assign the new role to those users.
                            </p>
                        </div>
                    </div>

                    <!-- External Agents -->
                    <div class="bg-white shadow-sm sm:rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">External Agents</h3>
                        <p class="text-gray-600 mb-3">
                            An agent can be marked as <strong>external</strong> in the agent settings. External agents have restricted ticket visibility compared to internal agents:
                        </p>
                        <ul class="list-disc list-inside text-gray-600 space-y-1">
                            <li>They can only see tickets that are <strong>directly assigned to them</strong> or <strong>assigned to their teams</strong>.</li>
                            <li>They cannot see tickets across other teams, even if their role would normally allow it.</li>
                        </ul>
                        <p class="text-gray-600 mt-3">
                            This is useful for contractors, part-time staff, or third-party agents who should only see tickets relevant to their specific teams.
                        </p>
                    </div>

                    <!-- Announcement Banner -->
                    <div class="bg-white shadow-sm sm:rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Announcement Banner</h3>
                        <p class="text-gray-600 mb-3">
                            Admins can display a notice banner to customers on the portal and ticket submission pages. This is useful for communicating service disruptions, holiday closures, or expected delays.
                        </p>
                        <ul class="list-disc list-inside text-gray-600 space-y-1">
                            <li>Go to the <strong>Tenant settings</strong> and find the <strong>Announcement Banner</strong> section.</li>
                            <li>Toggle <strong>Show announcement</strong> on and enter your message.</li>
                            <li>The banner appears on the public ticket submission page and the customer portal.</li>
                            <li>Toggle it off again when it's no longer needed.</li>
                        </ul>
                    </div>

                    <!-- Reports -->
                    <div class="bg-white shadow-sm sm:rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Reports</h3>
                        <p class="text-gray-600 mb-3">
                            The <strong>Reports</strong> page provides an overview of ticket volume, response times, agent performance, and SLA compliance. Access requires the <strong>View Reports</strong> permission (Team Leads and above by default).
                        </p>

                        <h4 class="font-medium text-gray-800 mt-4 mb-2">Custom Reports</h4>
                        <p class="text-gray-600 mb-3">
                            Custom reports let you build your own dashboards with configurable widgets. Access requires the <strong>Manage Custom Reports</strong> permission.
                        </p>
                        <ul class="list-disc list-inside text-gray-600 space-y-1">
                            <li>Create a report and add widgets for metrics like tickets by status, priority, team, agent performance, SLA compliance, average resolution time (including business hours), and more.</li>
                            <li>Widgets support different chart types (bar, pie, line, table, number).</li>
                            <li>Each widget can be filtered by date range, team, agent, status, priority, or labels.</li>
                            <li>Drag and resize widgets to arrange your layout.</li>
                            <li>Mark a report as <strong>shared</strong> to make it visible to other users with reports access.</li>
                            <li>Export report data to CSV.</li>
                        </ul>

                        <h4 class="font-medium text-gray-800 mt-4 mb-2">Chart Colors</h4>
                        <p class="text-gray-600 mb-3">
                            Bar and pie chart widgets use meaningful default colors &mdash; for example, status charts use green for open, yellow for pending, blue for resolved, and gray for closed. Priority charts use blue for normal, orange for high, and red for urgent.
                        </p>
                        <ul class="list-disc list-inside text-gray-600 space-y-1">
                            <li>To customise colors, click the <strong>configure</strong> (gear) icon on a bar or pie chart widget.</li>
                            <li>A <strong>Colors</strong> section shows each label with a color swatch &mdash; click any swatch to pick a new color.</li>
                            <li>Custom colors are saved per widget, so different widgets can use different color schemes.</li>
                            <li>Click <strong>Reset to defaults</strong> to revert to the standard colors.</li>
                        </ul>
                    </div>

                    <!-- Tips -->
                    <div class="bg-white shadow-sm sm:rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Tips</h3>
                        <ul class="list-disc list-inside text-gray-600 space-y-1">
                            <li>Use the search and filter bar on the tickets page to quickly find tickets.</li>
                            <li>Set tickets to <strong>pending</strong> after replying to keep your open queue focused on tickets needing action.</li>
                            <li>Use <strong>internal notes</strong> to share context with colleagues without the customer seeing it.</li>
                            <li>Check SLA badges regularly &mdash; breached tickets should be prioritised.</li>
                            <li>Create canned responses for common questions to speed up reply times.</li>
                            <li>If a ticket isn't yours, <strong>reassign it</strong> &mdash; don't leave it sitting in your queue.</li>
                        </ul>
                    </div>

                </template>

                <!-- ==================== TECHNICAL TAB ==================== -->
                <template v-if="activeTab === 'technical'">

                    <!-- Mail Configuration -->
                    <div class="bg-white shadow-sm sm:rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Mail Configuration</h3>
                        <p class="text-gray-600 mb-3">
                            Mail settings are managed under <strong>Settings &gt; Mail</strong>. Credentials are encrypted in the database.
                        </p>

                        <h4 class="font-medium text-gray-800 mt-4 mb-2">Outbound Mail</h4>
                        <p class="text-gray-600 mb-3">
                            Configure SMTP (or other provider) settings for sending emails. Use the <strong>Send Test</strong> button to verify your configuration. Tick <strong>Use database config</strong> to override the .env settings.
                        </p>

                        <h4 class="font-medium text-gray-800 mt-4 mb-2">Inbound Mail</h4>
                        <p class="text-gray-600 mb-2">Three methods for receiving emails:</p>
                        <ul class="list-disc list-inside text-gray-600 space-y-1">
                            <li><strong>IMAP Polling</strong> &mdash; connects to a mailbox (e.g. Titan, Gmail, Outlook), fetches unread emails, processes them into tickets, and marks them as read. Runs on a schedule.</li>
                            <li><strong>HTTP Webhook</strong> &mdash; receives emails via POST to <code class="bg-gray-100 px-1 rounded text-sm">/inbound/email</code>. Requires <code class="bg-gray-100 px-1 rounded text-sm">INBOUND_WEBHOOK_SECRET</code> in .env.</li>
                            <li><strong>Postfix Pipe</strong> &mdash; Postfix delivers directly to the app via command line.</li>
                        </ul>

                        <h4 class="font-medium text-gray-800 mt-4 mb-2">Inbound Email Review</h4>
                        <p class="text-gray-600">
                            All inbound emails (processed, rejected, failed) are logged under <strong>Settings &gt; Inbound Emails</strong>. You can view the full email content, headers, and spam filter result. Rejected emails can be <strong>reprocessed</strong> to create a ticket if they were falsely flagged as spam.
                        </p>

                        <h4 class="font-medium text-gray-800 mt-4 mb-2">Spam Filtering</h4>
                        <p class="text-gray-600 mb-2">
                            Spam filtering runs automatically on all inbound emails:
                        </p>
                        <ul class="list-disc list-inside text-gray-600 space-y-1">
                            <li><strong>Blocklist/Allowlist</strong> &mdash; block or allow specific email addresses or domains</li>
                            <li><strong>SpamAssassin</strong> &mdash; detects spam headers and score thresholds</li>
                            <li><strong>Bounce detection</strong> &mdash; filters out mailer-daemon, NDR, and auto-reply messages</li>
                            <li><strong>Rate limiting</strong> &mdash; limits tickets per sender per hour</li>
                        </ul>
                        <p class="text-gray-600 mt-2">
                            Configure spam settings via environment variables: <code class="bg-gray-100 px-1 rounded text-sm">SPAM_BLOCKLIST</code>, <code class="bg-gray-100 px-1 rounded text-sm">SPAM_ALLOWLIST</code>, <code class="bg-gray-100 px-1 rounded text-sm">SPAM_SCORE_THRESHOLD</code>, <code class="bg-gray-100 px-1 rounded text-sm">SPAM_MAX_PER_HOUR</code>.
                        </p>
                    </div>

                    <!-- Cron Job -->
                    <div class="bg-white shadow-sm sm:rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Cron Job (Required)</h3>
                        <p class="text-gray-600 mb-3">
                            A cron job is required to run the Laravel scheduler, which handles IMAP polling, SLA checks, and workflow automations.
                        </p>
                        <p class="text-gray-600 mb-2">Add this to your server's crontab:</p>
                        <code class="block bg-gray-100 rounded p-3 text-sm font-mono text-gray-800">* * * * * cd /path/to/tixline &amp;&amp; php artisan schedule:run &gt;&gt; /dev/null 2&gt;&amp;1</code>
                        <p class="text-gray-600 mt-3">
                            Without this cron job, IMAP polling, SLA breach detection, and scheduled automations will not run.
                        </p>
                    </div>

                    <!-- Workflows (detailed) -->
                    <div class="bg-white shadow-sm sm:rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Workflows (Configuration)</h3>
                        <p class="text-gray-600 mb-3">
                            Workflows are managed under <strong>Settings &gt; Workflows</strong>. Each workflow consists of a trigger event, conditions, and actions.
                        </p>

                        <h4 class="font-medium text-gray-800 mt-4 mb-2">Trigger Events</h4>
                        <ul class="list-disc list-inside text-gray-600 space-y-1">
                            <li><strong>ticket_created</strong> &mdash; runs when a new ticket is submitted</li>
                            <li><strong>ticket_updated</strong> &mdash; runs when a ticket is modified</li>
                            <li><strong>ticket_assigned</strong> &mdash; runs when a ticket is assigned</li>
                            <li><strong>ticket_status_changed</strong> &mdash; runs when the status changes</li>
                            <li><strong>ticket_priority_changed</strong> &mdash; runs when the priority changes</li>
                        </ul>

                        <h4 class="font-medium text-gray-800 mt-4 mb-2">Conditions</h4>
                        <p class="text-gray-600 mb-2">
                            Match ticket fields using operators (equals, contains, starts_with, regex). Conditions can be grouped with AND/OR logic.
                        </p>

                        <h4 class="font-medium text-gray-800 mt-4 mb-2">Actions</h4>
                        <ul class="list-disc list-inside text-gray-600 space-y-1">
                            <li><strong>assign_to_agent</strong> &mdash; assign to a specific agent</li>
                            <li><strong>assign_to_team</strong> &mdash; route to a specific team</li>
                            <li><strong>round_robin</strong> &mdash; distribute evenly across team members</li>
                            <li><strong>set_priority</strong> &mdash; change the priority level</li>
                            <li><strong>set_status</strong> &mdash; change the ticket status</li>
                            <li><strong>add_label</strong> &mdash; apply a label</li>
                        </ul>
                        <p class="text-gray-600 mt-3 text-sm">
                            Workflows execute in priority order (highest first) and can be toggled active or inactive.
                        </p>
                    </div>

                    <!-- SLA Configuration -->
                    <div class="bg-white shadow-sm sm:rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">SLA Policy Configuration</h3>
                        <p class="text-gray-600 mb-3">
                            SLA policies are managed under <strong>Settings &gt; SLA Policies</strong>. Each policy defines response and resolution time targets per priority level.
                        </p>
                        <ul class="list-disc list-inside text-gray-600 space-y-1">
                            <li><strong>First response SLA</strong> &mdash; maximum hours before an agent must respond.</li>
                            <li><strong>Resolution SLA</strong> &mdash; maximum hours to fully resolve the ticket.</li>
                            <li>A warning is shown at 75% of the elapsed time.</li>
                            <li>Breached tickets are flagged in the ticket list and detail views.</li>
                        </ul>
                    </div>

                    <!-- Forms -->
                    <div class="bg-white shadow-sm sm:rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Custom Forms</h3>
                        <p class="text-gray-600 mb-3">
                            Custom forms allow you to collect structured information when tickets are submitted. Managed under <strong>Settings &gt; Forms</strong>.
                        </p>
                        <ul class="list-disc list-inside text-gray-600 space-y-1">
                            <li>Supported field types: text, textarea, select, checkbox, radio, email, number, date, file.</li>
                            <li>Fields can be conditionally shown or hidden based on other field values.</li>
                            <li>Drag to reorder fields within a form.</li>
                            <li>Forms can be activated or deactivated &mdash; only active forms appear on the submission page.</li>
                            <li>Custom field data is stored on each ticket and visible in the ticket detail view.</li>
                        </ul>
                    </div>

                    <!-- API -->
                    <div class="bg-white shadow-sm sm:rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">REST API</h3>
                        <p class="text-gray-600 mb-3">
                            Tixline provides a REST API at <code class="bg-gray-100 px-1 rounded text-sm">/api/v1/tickets</code> for programmatic access. Authentication is via Sanctum API tokens.
                        </p>
                        <p class="text-gray-600">
                            API tokens can be generated in user settings. All API requests require the token in the <code class="bg-gray-100 px-1 rounded text-sm">Authorization: Bearer {token}</code> header.
                        </p>
                    </div>

                </template>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
