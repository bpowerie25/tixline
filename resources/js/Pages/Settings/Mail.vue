<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

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
});

const testForm = useForm({
    test_email: '',
});

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

                <!-- Mail Settings -->
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-1">SMTP Settings</h3>
                    <p class="text-sm text-gray-500 mb-4">Credentials are encrypted in the database. The password field is never displayed after saving.</p>

                    <form @submit.prevent="save" class="space-y-4">
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
                                <p v-if="form.errors.host" class="mt-1 text-sm text-red-600">{{ form.errors.host }}</p>
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
                                    <span v-if="config?.has_password" class="text-gray-400 font-normal">(saved, leave blank to keep)</span>
                                </label>
                                <input v-model="form.password" type="password" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">From Address</label>
                                <input v-model="form.from_address" type="email" placeholder="support@yoursite.com" required class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                <p v-if="form.errors.from_address" class="mt-1 text-sm text-red-600">{{ form.errors.from_address }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">From Name</label>
                                <input v-model="form.from_name" type="text" placeholder="Support Team" required class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            </div>
                        </div>

                        <div>
                            <button type="submit" :disabled="form.processing" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                                Save Configuration
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Test Email -->
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-1">Send Test Email</h3>
                    <p class="text-sm text-gray-500 mb-4">Verify your mail configuration is working by sending a test email.</p>

                    <form @submit.prevent="sendTest" class="flex items-end gap-3">
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-gray-700">Recipient Email</label>
                            <input v-model="testForm.test_email" type="email" required placeholder="you@example.com" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            <p v-if="testForm.errors.test_email" class="mt-1 text-sm text-red-600">{{ testForm.errors.test_email }}</p>
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
