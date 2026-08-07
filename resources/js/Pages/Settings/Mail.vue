<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    config: Object,
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
    imap_host: props.config?.imap_host || '',
    imap_port: props.config?.imap_port || 993,
    imap_encryption: props.config?.imap_encryption || 'ssl',
    imap_username: props.config?.imap_username || '',
    imap_password: '',
    imap_folder: props.config?.imap_folder || 'INBOX',
    imap_poll_interval: props.config?.imap_poll_interval || 5,
    imap_delete_after_process: props.config?.imap_delete_after_process ?? false,
});

const testForm = useForm({ test_email: '' });
const imapTestForm = useForm({});

function save() {
    form.post(route('mail-config.store'));
}

function sendTest() {
    testForm.post(route('mail-config.test'));
}

function testImap() {
    imapTestForm.post(route('mail-config.test-imap'));
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
                                    <template v-if="form.inbound_method === 'imap'">Connects to a mailbox and polls for new unread emails on a schedule.</template>
                                    <template v-else-if="form.inbound_method === 'webhook'">Receives emails via HTTP POST to /inbound/email (requires HMAC secret in .env).</template>
                                    <template v-else-if="form.inbound_method === 'postfix'">Postfix delivers emails directly via pipe transport.</template>
                                    <template v-else>No inbound email processing.</template>
                                </p>
                            </div>

                            <!-- IMAP Settings -->
                            <template v-if="form.inbound_method === 'imap'">
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                    <div class="sm:col-span-2">
                                        <label class="block text-sm font-medium text-gray-700">IMAP Host</label>
                                        <input v-model="form.imap_host" type="text" placeholder="imap.titan.email" required class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Port</label>
                                        <input v-model="form.imap_port" type="number" placeholder="993" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Encryption</label>
                                        <select v-model="form.imap_encryption" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            <option value="ssl">SSL</option>
                                            <option value="tls">TLS</option>
                                            <option :value="null">None</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Username</label>
                                        <input v-model="form.imap_username" type="text" placeholder="support@yoursite.com" required class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">
                                            Password
                                            <span v-if="config?.has_imap_password" class="text-gray-400 font-normal">(saved)</span>
                                        </label>
                                        <input v-model="form.imap_password" type="password" placeholder="Leave blank to keep current" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Folder</label>
                                        <input v-model="form.imap_folder" type="text" placeholder="INBOX" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Poll Interval (minutes)</label>
                                        <input v-model="form.imap_poll_interval" type="number" min="1" max="60" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                    </div>
                                    <div class="flex items-end">
                                        <label class="flex items-center gap-2">
                                            <input v-model="form.imap_delete_after_process" type="checkbox" class="rounded text-indigo-600" />
                                            <span class="text-sm font-medium text-gray-700">Delete after processing</span>
                                        </label>
                                    </div>
                                </div>
                            </template>

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

                <!-- Test IMAP -->
                <div v-if="config?.inbound_method === 'imap'" class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-1">Test IMAP Connection</h3>
                    <p class="text-sm text-gray-500 mb-4">Run a manual IMAP poll to test the connection and process any unread emails.</p>

                    <button @click="testImap" :disabled="imapTestForm.processing" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50">
                        {{ imapTestForm.processing ? 'Polling...' : 'Poll Now' }}
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
