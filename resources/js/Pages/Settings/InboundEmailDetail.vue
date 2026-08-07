<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SandboxedHtml from '@/Components/SandboxedHtml.vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    email: Object,
});

const statusColors = {
    processed: 'bg-green-100 text-green-700',
    rejected: 'bg-red-100 text-red-700',
    failed: 'bg-orange-100 text-orange-700',
    pending: 'bg-yellow-100 text-yellow-700',
};

function reprocess() {
    if (confirm('Reprocess this email? It will bypass the spam filter and create a ticket.')) {
        router.post(route('inbound-emails.reprocess', props.email.id));
    }
}
</script>

<template>
    <Head :title="'Email: ' + (email.subject || '(No Subject)')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('inbound-emails.index')" class="text-gray-400 hover:text-gray-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                </Link>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Inbound Email</h2>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-4xl sm:px-6 lg:px-8 space-y-6">
                <!-- Flash messages -->
                <div v-if="$page.props.flash?.success" class="rounded-md bg-green-50 border border-green-200 p-4">
                    <p class="text-sm text-green-800">{{ $page.props.flash.success }}</p>
                </div>

                <!-- Metadata -->
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-2">
                            <span :class="[statusColors[email.status], 'inline-flex rounded-full px-2 py-0.5 text-xs font-medium']">{{ email.status }}</span>
                            <span v-if="email.result" class="text-sm text-gray-500">{{ email.result }}</span>
                        </div>
                        <button v-if="email.status === 'rejected'" @click="reprocess" class="rounded-md bg-green-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-green-700">
                            Reprocess (create ticket)
                        </button>
                    </div>

                    <dl class="grid grid-cols-1 gap-3 sm:grid-cols-2 text-sm">
                        <div>
                            <dt class="text-gray-500">From</dt>
                            <dd class="font-medium text-gray-900">{{ email.from_name }} &lt;{{ email.from_email }}&gt;</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Subject</dt>
                            <dd class="font-medium text-gray-900">{{ email.subject || '(No Subject)' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Received</dt>
                            <dd class="text-gray-900">{{ email.created_at }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Processed</dt>
                            <dd class="text-gray-900">{{ email.processed_at || 'Not yet' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Message ID</dt>
                            <dd class="text-gray-900 font-mono text-xs break-all">{{ email.message_id }}</dd>
                        </div>
                        <div v-if="email.auth_results">
                            <dt class="text-gray-500">Auth Results</dt>
                            <dd class="text-gray-900">
                                <span v-for="(val, key) in email.auth_results" :key="key" class="mr-2">
                                    {{ key }}: <span :class="val === 'pass' ? 'text-green-600' : 'text-red-600'">{{ val }}</span>
                                </span>
                            </dd>
                        </div>
                    </dl>
                </div>

                <!-- Body -->
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-sm font-medium text-gray-500 mb-3">Email Body</h3>
                    <div class="border rounded-lg p-4 bg-gray-50">
                        <SandboxedHtml v-if="email.body" :html="email.body" />
                        <p v-else class="text-gray-400 italic">No body content</p>
                    </div>
                </div>

                <!-- Headers -->
                <div v-if="email.headers && Object.keys(email.headers).length" class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-sm font-medium text-gray-500 mb-3">Headers</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs font-mono">
                            <tbody>
                                <tr v-for="(val, key) in email.headers" :key="key" class="border-b border-gray-100">
                                    <td class="py-1 pr-4 text-gray-500 whitespace-nowrap align-top">{{ key }}</td>
                                    <td class="py-1 text-gray-900 break-all">{{ val }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
