<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    stats: Object,
    recentTickets: Array,
});

const priorityColors = {
    low: 'bg-gray-100 text-gray-700',
    normal: 'bg-blue-100 text-blue-700',
    high: 'bg-orange-100 text-orange-700',
    urgent: 'bg-red-100 text-red-700',
};

const statusColors = {
    open: 'bg-green-100 text-green-700',
    pending: 'bg-yellow-100 text-yellow-700',
    resolved: 'bg-blue-100 text-blue-700',
    closed: 'bg-gray-100 text-gray-700',
};
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Dashboard
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <!-- Stats -->
                <div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Link :href="route('tickets.index', { status: 'open' })" class="overflow-hidden rounded-lg bg-white p-6 shadow transition hover:shadow-md hover:ring-1 hover:ring-green-200 cursor-pointer">
                        <div class="text-sm font-medium text-gray-500">Open Tickets</div>
                        <div class="mt-2 text-3xl font-bold text-green-600">{{ stats.open }}</div>
                    </Link>
                    <Link :href="route('tickets.index', { status: 'pending' })" class="overflow-hidden rounded-lg bg-white p-6 shadow transition hover:shadow-md hover:ring-1 hover:ring-yellow-200 cursor-pointer">
                        <div class="text-sm font-medium text-gray-500">Pending</div>
                        <div class="mt-2 text-3xl font-bold text-yellow-600">{{ stats.pending }}</div>
                    </Link>
                    <Link :href="route('tickets.index', { status: 'resolved' })" class="overflow-hidden rounded-lg bg-white p-6 shadow transition hover:shadow-md hover:ring-1 hover:ring-blue-200 cursor-pointer">
                        <div class="text-sm font-medium text-gray-500">Resolved Today</div>
                        <div class="mt-2 text-3xl font-bold text-blue-600">{{ stats.resolved_today }}</div>
                    </Link>
                    <Link :href="route('tickets.index')" class="overflow-hidden rounded-lg bg-white p-6 shadow transition hover:shadow-md hover:ring-1 hover:ring-gray-200 cursor-pointer">
                        <div class="text-sm font-medium text-gray-500">Total Tickets</div>
                        <div class="mt-2 text-3xl font-bold text-gray-700">{{ stats.total }}</div>
                    </Link>
                </div>

                <!-- Recent Tickets -->
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="border-b border-gray-200 p-6">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-medium text-gray-900">Recent Tickets</h3>
                            <Link :href="route('tickets.index')" class="text-sm text-indigo-600 hover:text-indigo-500">
                                View all
                            </Link>
                        </div>
                    </div>
                    <div class="divide-y divide-gray-200">
                        <Link
                            v-for="ticket in recentTickets"
                            :key="ticket.id"
                            :href="route('tickets.show', ticket.id)"
                            class="flex items-center justify-between px-6 py-4 hover:bg-gray-50"
                        >
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-3">
                                    <span class="text-sm font-mono text-gray-500">{{ ticket.reference }}</span>
                                    <span class="text-sm font-medium text-gray-900 truncate">{{ ticket.subject }}</span>
                                </div>
                                <div class="mt-1 text-sm text-gray-500">
                                    {{ ticket.requester_name }} &lt;{{ ticket.requester_email }}&gt;
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
                        <div v-if="!recentTickets.length" class="px-6 py-8 text-center text-gray-500">
                            No tickets yet.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
