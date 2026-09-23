<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    config: Object,
    mailboxes: Array,
    teams: Array,
});

const form = useForm({
    mailer: props.config?.mailer || 'smtp',
    host: props.config?.host || '',
    port: props.config?.port || 587,
    encryption: props.config?.encryption || 'tls',
    username: props.config?.username || '',
    password: '',
    from_address: props.config?.from_address || '',
    from_name: props.config?.from_name || '',
    is_active: props.config?.is_active ?? false,
    inbound_method: props.config?.inbound_method || 'none',
});

const testForm = useForm({ test_email: '' });

// Mailbox management
const showMailboxForm = ref(false);
const editingMailbox = ref(null);

const mailboxForm = useForm({
    name: '',
    imap_host: '',
    imap_port: 993,
    imap_encryption: 'ssl',
    imap_username: '',
    imap_password: '',
    imap_folder: 'INBOX',
    poll_interval: 5,
    delete_after_process: false,
    team_id: null,
    is_active: true,
});

function openCreateMailbox() {
    editingMailbox.value = null;
    mailboxForm.reset();
    showMailboxForm.value = true;
}

function openEditMailbox(mailbox) {
    editingMailbox.value = mailbox;
    mailboxForm.name = mailbox.name;
    mailboxForm.imap_host = mailbox.imap_host;
    mailboxForm.imap_port = mailbox.imap_port;
    mailboxForm.imap_encryption = mailbox.imap_encryption;
    mailboxForm.imap_username = mailbox.imap_username;
    mailboxForm.imap_password = '';
    mailboxForm.imap_folder = mailbox.imap_folder;
    mailboxForm.poll_interval = mailbox.poll_interval;
    mailboxForm.delete_after_process = mailbox.delete_after_process;
    mailboxForm.team_id = mailbox.team_id;
    mailboxForm.is_active = mailbox.is_active;
    showMailboxForm.value = true;
}

function saveMailbox() {
    if (editingMailbox.value) {
        mailboxForm.put(route('inbound-mailboxes.update', editingMailbox.value.id), {
            onSuccess: () => { showMailboxForm.value = false; },
        });
    } else {
        mailboxForm.post(route('inbound-mailboxes.store'), {
            onSuccess: () => { showMailboxForm.value = false; mailboxForm.reset(); },
        });
    }
}

function deleteMailbox(mailbox) {
    if (confirm(`Delete mailbox "${mailbox.name}"?`)) {
        router.delete(route('inbound-mailboxes.destroy', mailbox.id));
    }
}

function testMailbox(mailbox) {
    router.post(route('inbound-mailboxes.test', mailbox.id));
}

function save() {
    form.post(route('mail-config.store'));
}

function sendTest() {
    testForm.post(route('mail-config.test'));
}
</script>

<template>
    <Head title="Mail Configuration" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Mail Configuration</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-3xl sm:px-6 lg:px-8 space-y-6">
                <!-- Flash messages -->
                <div v-if="$page.props.flash?.success" class="rounded-md bg-green-50 border border-green-200 p-4">
                    <p class="text-sm text-green-800">{{ $page.props.flash.success }}</p>
                </div>
                <div v-if="$page.props.flash?.error" class="rounded-md bg-red-50 border border-red-200 p-4">
                    <p class="text-sm text-red-800">{{ $page.props.flash.error }}</p>
                </div>

                <form @submit.prevent="save" class="space-y-6">
                    <!-- Outbound Mail Settings -->
                    <div class="bg-white shadow-sm sm:rounded-lg p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-1">Outbound Mail (SMTP)</h3>
                        <p class="text-sm text-gray-500 mb-4">Configure how Tixline sends emails. Credentials are encrypted in the database.</p>

                        <div class="space-y-4">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Mailer</label>
                                    <select v-model="form.mailer" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="smtp">SMTP</option>
                                        <option value="ses">Amazon SES</option>
                                        <option value="postmark">Postmark</option>
                                        <option value="sendmail">Sendmail</option>
                                        <option value="log">Log (testing)</option>
                                    </select>
                                </div>
                                <div class="flex items-end">
                                    <label class="flex items-center gap-2">
                                        <input v-model="form.is_active" type="checkbox" class="rounded text-indigo-600" />
                                        <span class="text-sm font-medium text-gray-700">Use database config</span>
                                    </label>
                                    <p v-if="!form.is_active" class="ml-2 text-xs text-gray-400">(using .env fallback)</p>
                                </div>
                            </div>

                            <div v-if="form.mailer === 'smtp'" class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                <div class="sm:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700">SMTP Host</label>
                                    <input v-model="form.host" type="text" placeholder="smtp.example.com" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Port</label>
                                    <input v-model="form.port" type="number" placeholder="587" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                </div>
                            </div>

                            <div v-if="form.mailer === 'smtp'" class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Encryption</label>
                                    <select v-model="form.encryption" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option :value="null">None</option>
                                        <option value="tls">TLS</option>
                                        <option value="ssl">SSL</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Username</label>
                                    <input v-model="form.username" type="text" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">
                                        Password
                                        <span v-if="config?.has_password" class="text-gray-400 font-normal">(saved)</span>
                                    </label>
                                    <input v-model="form.password" type="password" placeholder="Leave blank to keep current" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">From Address</label>
                                    <input v-model="form.from_address" type="email" placeholder="support@yoursite.com" required class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">From Name</label>
                                    <input v-model="form.from_name" type="text" placeholder="Support Team" required class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Inbound Mail Settings -->
                    <div class="bg-white shadow-sm sm:rounded-lg p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-1">Inbound Mail</h3>
                        <p class="text-sm text-gray-500 mb-4">Configure how Tixline receives emails to create and update tickets.</p>

                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Inbound Method</label>
                                <select v-model="form.inbound_method" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:w-auto">
                                    <option value="none">Disabled</option>
                                    <option value="imap">IMAP Polling</option>
                                    <option value="webhook">HTTP Webhook</option>
                                    <option value="postfix">Postfix Pipe</option>
                                </select>
                                <p class="mt-1 text-xs text-gray-400">
                                    <template v-if="form.inbound_method === 'imap'">Connects to mailboxes and polls for new unread emails on a schedule.</template>
                                    <template v-else-if="form.inbound_method === 'webhook'">Receives emails via HTTP POST to /inbound/email (requires HMAC secret in .env).</template>
                                    <template v-else-if="form.inbound_method === 'postfix'">Postfix delivers emails directly via pipe transport.</template>
                                    <template v-else>No inbound email processing.</template>
                                </p>
                            </div>

                            <!-- Webhook info -->
                            <div v-if="form.inbound_method === 'webhook'" class="rounded-md bg-gray-50 p-4 text-sm text-gray-600">
                                <p class="font-medium text-gray-700 mb-1">Webhook Endpoint</p>
                                <code class="text-xs bg-white px-2 py-1 rounded border">POST /inbound/email</code>
                                <p class="mt-2">Set <code class="bg-white px-1 rounded border text-xs">INBOUND_WEBHOOK_SECRET</code> in your .env file for HMAC signature verification.</p>
                            </div>

                            <!-- Postfix info -->
                            <div v-if="form.inbound_method === 'postfix'" class="rounded-md bg-gray-50 p-4 text-sm text-gray-600">
                                <p class="font-medium text-gray-700 mb-1">Postfix Pipe Transport</p>
                                <p>Add to your Postfix aliases:</p>
                                <code class="mt-1 block text-xs bg-white px-2 py-1 rounded border">support: |"/path/to/artisan support:process-email"</code>
                            </div>
                        </div>
                    </div>

                    <!-- Save button -->
                    <div>
                        <button type="submit" :disabled="form.processing" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                            Save Configuration
                        </button>
                    </div>
                </form>

                <!-- IMAP Mailboxes -->
                <div v-if="form.inbound_method === 'imap' || config?.inbound_method === 'imap'" class="bg-white shadow-sm sm:rounded-lg p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">IMAP Mailboxes</h3>
                            <p class="text-sm text-gray-500">Each mailbox polls independently. Optionally route to a specific team.</p>
                        </div>
                        <button @click="openCreateMailbox" class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                            Add Mailbox
                        </button>
                    </div>

                    <!-- Mailbox Form -->
                    <div v-if="showMailboxForm" class="mb-6 rounded-md border border-gray-200 p-4 bg-gray-50">
                        <h4 class="text-sm font-medium text-gray-900 mb-3">{{ editingMailbox ? 'Edit' : 'Add' }} Mailbox</h4>
                        <form @submit.prevent="saveMailbox" class="space-y-4">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Name</label>
                                    <input v-model="mailboxForm.name" type="text" required placeholder="e.g. Support Inbox" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Route to Team</label>
                                    <select v-model="mailboxForm.team_id" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option :value="null">Default (General Support)</option>
                                        <option v-for="team in teams" :key="team.id" :value="team.id">{{ team.name }}</option>
                                    </select>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                <div class="sm:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700">IMAP Host</label>
                                    <input v-model="mailboxForm.imap_host" type="text" required placeholder="imap.titan.email" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Port</label>
                                    <input v-model="mailboxForm.imap_port" type="number" placeholder="993" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Encryption</label>
                                    <select v-model="mailboxForm.imap_encryption" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="ssl">SSL</option>
                                        <option value="tls">TLS</option>
                                        <option :value="null">None</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Username</label>
                                    <input v-model="mailboxForm.imap_username" type="text" required placeholder="support@yoursite.com" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">
                                        Password
                                        <span v-if="editingMailbox?.has_imap_password" class="text-gray-400 font-normal">(saved)</span>
                                    </label>
                                    <input v-model="mailboxForm.imap_password" type="password" :required="!editingMailbox" :placeholder="editingMailbox ? 'Leave blank to keep current' : ''" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Folder</label>
                                    <input v-model="mailboxForm.imap_folder" type="text" placeholder="INBOX" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Poll Interval (minutes)</label>
                                    <input v-model="mailboxForm.poll_interval" type="number" min="1" max="60" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                </div>
                                <div class="flex items-end gap-4">
                                    <label class="flex items-center gap-2">
                                        <input v-model="mailboxForm.delete_after_process" type="checkbox" class="rounded text-indigo-600" />
                                        <span class="text-sm font-medium text-gray-700">Delete after processing</span>
                                    </label>
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                <input v-model="mailboxForm.is_active" type="checkbox" id="mailbox_active" class="rounded text-indigo-600" />
                                <label for="mailbox_active" class="text-sm font-medium text-gray-700">Active</label>
                            </div>

                            <div class="flex gap-3">
                                <button type="submit" :disabled="mailboxForm.processing" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                                    {{ editingMailbox ? 'Update' : 'Create' }}
                                </button>
                                <button type="button" @click="showMailboxForm = false" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Mailbox List -->
                    <div class="divide-y divide-gray-200 rounded-md border border-gray-200" v-if="mailboxes?.length">
                        <div v-for="mailbox in mailboxes" :key="mailbox.id" class="flex items-center justify-between px-4 py-3">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="font-medium text-gray-900">{{ mailbox.name }}</span>
                                    <span v-if="!mailbox.is_active" class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">Inactive</span>
                                </div>
                                <div class="text-sm text-gray-500">
                                    {{ mailbox.imap_username }}
                                    <span v-if="mailbox.team_name" class="ml-1">&rarr; {{ mailbox.team_name }}</span>
                                    <span v-else class="ml-1">&rarr; General Support</span>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <button @click="testMailbox(mailbox)" class="text-sm text-indigo-600 hover:text-indigo-800">Poll Now</button>
                                <button @click="openEditMailbox(mailbox)" class="text-sm text-indigo-600 hover:text-indigo-800">Edit</button>
                                <button @click="deleteMailbox(mailbox)" class="text-sm text-red-600 hover:text-red-800">Delete</button>
                            </div>
                        </div>
                    </div>
                    <p v-else-if="!showMailboxForm" class="text-sm text-gray-400">No mailboxes configured. Add one to start polling.</p>
                </div>

                <!-- Test Outbound -->
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-1">Test Outbound Email</h3>
                    <p class="text-sm text-gray-500 mb-4">Send a test email to verify your outbound mail configuration.</p>

                    <form @submit.prevent="sendTest" class="flex items-end gap-3">
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-gray-700">Recipient Email</label>
                            <input v-model="testForm.test_email" type="email" required placeholder="you@example.com" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        </div>
                        <button type="submit" :disabled="testForm.processing" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50">
                            {{ testForm.processing ? 'Sending...' : 'Send Test' }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
