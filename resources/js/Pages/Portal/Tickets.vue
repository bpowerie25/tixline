<script setup>
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';

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
