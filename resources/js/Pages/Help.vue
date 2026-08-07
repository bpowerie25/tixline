<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Help Guide" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Help Guide</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-4xl sm:px-6 lg:px-8 space-y-8">

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

                <!-- Tickets -->
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

                <!-- Workflows -->
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Workflows (Automation)</h3>
                    <p class="text-gray-600 mb-3">
                        Workflows automatically perform actions on tickets when certain conditions are met. They run on ticket creation, update, or assignment.
                    </p>
                    <p class="text-gray-600 mb-2"><strong>Example actions:</strong></p>
                    <ul class="list-disc list-inside text-gray-600 space-y-1">
                        <li>Auto-assign tickets to a team or agent based on subject keywords</li>
                        <li>Set priority based on the requester's email domain</li>
                        <li>Add labels automatically</li>
                        <li>Round-robin ticket distribution across a team</li>
                    </ul>
                    <p class="text-gray-600 mt-3 text-sm italic">Workflows are managed by admins under Settings &gt; Workflows.</p>
                </div>

                <!-- Knowledge Base -->
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Knowledge Base</h3>
                    <p class="text-gray-600 mb-3">
                        The knowledge base is a public-facing collection of help articles organised by category. Customers can search and browse articles at <code class="bg-gray-100 px-1 rounded text-sm">/kb</code>.
                    </p>
                    <ul class="list-disc list-inside text-gray-600 space-y-1">
                        <li>Team leads and above can create, edit, and manage articles from the <strong>KB</strong> tab.</li>
                        <li>Articles support <strong>draft</strong> and <strong>published</strong> states &mdash; drafts are not visible to customers.</li>
                        <li>Point customers to relevant articles in your replies to reduce repeat questions.</li>
                    </ul>
                </div>

                <!-- Customer Portal -->
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Customer Portal</h3>
                    <p class="text-gray-600 mb-3">
                        Customers can access the portal at <code class="bg-gray-100 px-1 rounded text-sm">/portal</code> to:
                    </p>
                    <ul class="list-disc list-inside text-gray-600 space-y-1">
                        <li>View all their submitted tickets</li>
                        <li>Reply to existing tickets</li>
                        <li>Submit new tickets</li>
                    </ul>
                    <p class="text-gray-600 mt-3">
                        A portal account is <strong>automatically created</strong> when a customer submits their first ticket (via the web form or email). They receive their login credentials by email.
                    </p>
                </div>

                <!-- Inbound Email -->
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Inbound Email</h3>
                    <p class="text-gray-600 mb-3">
                        Tickets can be created by customers sending an email to the configured support address. Replies to existing tickets are automatically threaded if the ticket reference (e.g. <code class="bg-gray-100 px-1 rounded text-sm">[TKT-000001]</code>) is in the subject line.
                    </p>
                    <p class="text-gray-600">
                        Spam filtering is applied automatically, including blocklist/allowlist checks, SpamAssassin header detection, bounce filtering, and rate limiting.
                    </p>
                </div>

                <!-- Roles -->
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Roles &amp; Permissions</h3>
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
                                    <td class="px-4 py-2 text-gray-600">View &amp; reply to assigned/team tickets, use canned responses</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-2 font-medium text-gray-900">Team Lead</td>
                                    <td class="px-4 py-2 text-gray-600">Everything agents can do, plus Reports and Knowledge Base management</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-2 font-medium text-gray-900">Group Manager</td>
                                    <td class="px-4 py-2 text-gray-600">Same as Team Lead with broader ticket visibility</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-2 font-medium text-gray-900">Admin</td>
                                    <td class="px-4 py-2 text-gray-600">Full access: teams, labels, workflows, forms, SLA policies, tenants, departments</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Keyboard shortcuts / Tips -->
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Tips</h3>
                    <ul class="list-disc list-inside text-gray-600 space-y-1">
                        <li>Use the search and filter bar on the tickets page to quickly find tickets.</li>
                        <li>Set tickets to <strong>pending</strong> after replying to keep your open queue focused on tickets needing action.</li>
                        <li>Use <strong>internal notes</strong> to share context with colleagues without the customer seeing it.</li>
                        <li>Check SLA badges regularly &mdash; breached tickets should be prioritised.</li>
                        <li>Create canned responses for common questions to speed up reply times.</li>
                    </ul>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
