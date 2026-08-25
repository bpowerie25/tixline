<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    email: String,
    requesterName: String,
    tickets: Object,
});

const statusColors = {
    open: 'bg-green-100 text-green-700',
    pending: 'bg-yellow-100 text-yellow-700',
    resolved: 'bg-blue-100 text-blue-700',
    closed: 'bg-gray-100 text-gray-700',
};

const priorityColors = {
    low: 'bg-gray-100 text-gray-700',
    normal: 'bg-blue-100 text-blue-700',
    high: 'bg-orange-100 text-orange-700',
    urgent: 'bg-red-100 text-red-700',
};
</script>

<template>
    <Head :title="requesterName + ' — Requester'" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('tickets.index')" class="text-gray-400 hover:text-gray-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                </Link>
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ requesterName }}</h2>
                    <p class="text-sm text-gray-500">{{ email }}</p>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="mb-4 text-sm text-gray-500">
                    {{ tickets.total }} ticket{{ tickets.total === 1 ? '' : 's' }}
                </div>

                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="divide-y divide-gray-200">
                        <div
                            v-for="ticket in tickets.data"
                            :key="ticket.id"
                            class="flex items-center px-6 py-4 hover:bg-gray-50"
                        >
                            <Link
                                :href="route('tickets.show', ticket.id)"
                                class="flex flex-1 items-center justify-between min-w-0"
                            >
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-3">
                                        <span class="text-sm font-mono text-gray-500">{{ ticket.reference }}</span>
                                        <span class="text-sm font-medium text-gray-900 truncate">{{ ticket.subject }}</span>
                                    </div>
                                    <div class="mt-1 text-sm text-gray-500">
                                        {{ new Date(ticket.created_at).toLocaleDateString() }}
                                        <span v-if="ticket.team" class="ml-2">&middot; {{ ticket.team.name }}</span>
                                    </div>
                                </div>
                                <div class="ml-4 flex items-center gap-2">
                                    <span :class="[statusColors[ticket.status], 'inline-flex rounded-full px-2 py-0.5 text-xs font-medium']">
                                        {{ ticket.status }}
                                    </span>
                                    <span :class="[priorityColors[ticket.priority], 'inline-flex rounded-full px-2 py-0.5 text-xs font-medium']">
                                        {{ ticket.priority }}
                                    </span>
                                    <span v-if="ticket.assignee" class="text-xs text-gray-500">
                                        {{ ticket.assignee.name }}
                                    </span>
                                </div>
                            </Link>
                        </div>
                        <div v-if="!tickets.data.length" class="px-6 py-8 text-center text-gray-500">
                            No tickets found.
                        </div>
                    </div>

                    <!-- Pagination -->
                    <div v-if="tickets.last_page > 1" class="flex items-center justify-between border-t border-gray-200 px-6 py-3">
                        <div class="text-sm text-gray-500">
                            Showing {{ tickets.from }} to {{ tickets.to }} of {{ tickets.total }}
                        </div>
                        <div class="flex gap-1">
                            <Link
                                v-for="link in tickets.links"
                                :key="link.label"
                                :href="link.url || '#'"
                                :class="[
                                    'rounded px-3 py-1 text-sm',
                                    link.active ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-gray-100',
                                    !link.url ? 'cursor-default opacity-50' : '',
                                ]"
                                v-html="link.label"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
