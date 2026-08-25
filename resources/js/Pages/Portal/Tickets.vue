<script setup>
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const tenant = computed(() => usePage().props.tenant);

const props = defineProps({
    tickets: Object,
    customer: Object,
});

const showNewForm = ref(false);
const form = useForm({
    subject: '',
    body: '',
});

function submitTicket() {
    form.post(route('portal.tickets.store'), {
        onSuccess: () => { showNewForm.value = false; form.reset(); },
    });
}

const statusColors = {
    open: 'bg-green-100 text-green-700',
    pending: 'bg-yellow-100 text-yellow-700',
    resolved: 'bg-blue-100 text-blue-700',
    closed: 'bg-gray-100 text-gray-700',
};
</script>

<template>
    <Head title="My Tickets" />

    <div class="min-h-screen bg-gray-100">
        <nav class="border-b border-gray-200 bg-white">
            <div class="mx-auto max-w-4xl px-4 py-4 flex items-center justify-between">
                <h1 class="text-lg font-semibold text-gray-900">My Support Tickets</h1>
                <div class="flex items-center gap-4">
                    <span class="text-sm text-gray-500">{{ customer.name }}</span>
                    <Link :href="route('portal.logout')" method="post" as="button" class="text-sm text-gray-500 hover:text-gray-700">Logout</Link>
                </div>
            </div>
        </nav>

        <!-- Announcement Banner -->
        <div v-if="tenant?.announcement_enabled && tenant?.announcement_text" class="border-b border-amber-200 bg-amber-50 px-4 py-3">
            <div class="mx-auto max-w-4xl flex items-start gap-3">
                <svg class="h-5 w-5 shrink-0 text-amber-500 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 6a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 6zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" /></svg>
                <p class="text-sm text-amber-800">{{ tenant.announcement_text }}</p>
            </div>
        </div>

        <div class="mx-auto max-w-4xl px-4 py-8">
            <div class="mb-6 flex justify-end">
                <button @click="showNewForm = !showNewForm" class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    New Ticket
                </button>
            </div>

            <!-- New ticket form -->
            <div v-if="showNewForm" class="mb-6 overflow-hidden bg-white shadow-sm sm:rounded-lg p-6">
                <form @submit.prevent="submitTicket" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Subject</label>
                        <input v-model="form.subject" type="text" required class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Description</label>
                        <textarea v-model="form.body" rows="4" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                    </div>
                    <div class="flex gap-3">
                        <button type="submit" :disabled="form.processing" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">Submit</button>
                        <button type="button" @click="showNewForm = false" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                    </div>
                </form>
            </div>

            <!-- Tickets list -->
            <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <div class="divide-y divide-gray-200">
                    <Link
                        v-for="ticket in tickets.data"
                        :key="ticket.id"
                        :href="route('portal.ticket', ticket.id)"
                        class="flex items-center justify-between px-6 py-4 hover:bg-gray-50"
                    >
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-3">
                                <span class="font-mono text-sm text-gray-500">{{ ticket.reference }}</span>
                                <span class="text-sm font-medium text-gray-900">{{ ticket.subject }}</span>
                            </div>
                            <div class="mt-1 text-xs text-gray-500">
                                {{ new Date(ticket.created_at).toLocaleDateString() }}
                                <span v-if="ticket.team"> &middot; {{ ticket.team.name }}</span>
                            </div>
                        </div>
                        <span :class="[statusColors[ticket.status], 'inline-flex rounded-full px-2 py-0.5 text-xs font-medium capitalize']">
                            {{ ticket.status }}
                        </span>
                    </Link>
                    <div v-if="!tickets.data.length" class="px-6 py-8 text-center text-gray-500">
                        No tickets yet. Click "New Ticket" to create one.
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
