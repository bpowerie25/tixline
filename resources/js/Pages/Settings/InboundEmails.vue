<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    emails: Object,
    filter: String,
    counts: Object,
});

const statusColors = {
    processed: 'bg-green-100 text-green-700',
    rejected: 'bg-red-100 text-red-700',
    failed: 'bg-orange-100 text-orange-700',
    pending: 'bg-yellow-100 text-yellow-700',
};

function filterBy(status) {
    router.get(route('inbound-emails.index'), status === 'all' ? {} : { status }, { preserveState: true });
}

function reprocess(id) {
    if (confirm('Reprocess this email? It will bypass the spam filter and create a ticket.')) {
        router.post(route('inbound-emails.reprocess', id));
    }
}

function deleteEmail(id) {
    if (confirm('Delete this email record?')) {
        router.delete(route('inbound-emails.destroy', id));
    }
}
</script>

<template>
    <Head title="Inbound Emails" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Inbound Emails</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">
                <!-- Flash messages -->
                <div v-if="$page.props.flash?.success" class="rounded-md bg-green-50 border border-green-200 p-4">
                    <p class="text-sm text-green-800">{{ $page.props.flash.success }}</p>
                </div>
                <div v-if="$page.props.flash?.error" class="rounded-md bg-red-50 border border-red-200 p-4">
                    <p class="text-sm text-red-800">{{ $page.props.flash.error }}</p>
                </div>

                <!-- Filters -->
                <div class="flex flex-wrap gap-2">
                    <button v-for="[key, label] in [['all', 'All'], ['processed', 'Processed'], ['rejected', 'Rejected'], ['failed', 'Failed'], ['pending', 'Pending']]"
                        :key="key"
                        @click="filterBy(key)"
                        :class="filter === key ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'"
                        class="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium"
                    >
                        {{ label }} ({{ counts[key] }})
                    </button>
                </div>

                <!-- List -->
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="divide-y divide-gray-200">
                        <div v-for="email in emails.data" :key="email.id" class="flex items-center justify-between px-6 py-4">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <span :class="[statusColors[email.status], 'inline-flex rounded-full px-2 py-0.5 text-xs font-medium']">
                                        {{ email.status }}
                                    </span>
                                    <span class="text-sm font-medium text-gray-900 truncate">{{ email.subject || '(No Subject)' }}</span>
                                </div>
                                <div class="mt-1 text-sm text-gray-500">
                                    {{ email.from_name || email.from_email }}
                                    <span v-if="email.from_name"> &lt;{{ email.from_email }}&gt;</span>
                                    <span class="ml-2">&middot; {{ email.created_at }}</span>
                                    <span v-if="email.result" class="ml-2 text-xs text-gray-400">{{ email.result }}</span>
                                </div>
                            </div>
                            <div class="ml-4 flex items-center gap-3">
                                <Link :href="route('inbound-emails.show', email.id)" class="text-sm text-indigo-600 hover:text-indigo-800">View</Link>
                                <button v-if="email.status === 'rejected'" @click="reprocess(email.id)" class="text-sm text-green-600 hover:text-green-800">Reprocess</button>
                                <button @click="deleteEmail(email.id)" class="text-sm text-red-600 hover:text-red-800">Delete</button>
                            </div>
                        </div>
                        <div v-if="!emails.data.length" class="px-6 py-8 text-center text-gray-500">
                            No inbound emails found.
                        </div>
                    </div>

                    <!-- Pagination -->
                    <div v-if="emails.last_page > 1" class="border-t border-gray-200 px-6 py-3 flex items-center justify-between">
                        <p class="text-sm text-gray-500">Showing {{ emails.from }}-{{ emails.to }} of {{ emails.total }}</p>
                        <div class="flex gap-1">
                            <Link v-for="link in emails.links" :key="link.label"
                                :href="link.url || '#'"
                                :class="link.active ? 'bg-indigo-600 text-white' : link.url ? 'text-gray-700 hover:bg-gray-50' : 'text-gray-300 cursor-default'"
                                class="rounded px-3 py-1 text-sm border border-gray-300"
                                v-html="link.label"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
