<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    days: Number,
    volumeByDay: Object,
    statusBreakdown: Object,
    priorityBreakdown: Object,
    agentStats: Array,
    sourceBreakdown: Object,
    avgResolutionHours: Number,
    avgResolutionBusinessHours: Number,
});

function changePeriod(days) {
    router.get(route('reports.index'), { days }, { preserveState: true });
}

const maxVolume = computed(() => Math.max(...Object.values(props.volumeByDay), 1));

const statusColors = { open: '#22c55e', pending: '#eab308', resolved: '#3b82f6', closed: '#9ca3af' };
const priorityColors = { low: '#9ca3af', normal: '#3b82f6', high: '#f97316', urgent: '#ef4444' };

const totalTickets = computed(() => Object.values(props.statusBreakdown).reduce((a, b) => a + b, 0) || 1);

function formatHours(hours) {
    if (!hours) return '-';
    if (hours < 1) return `${Math.round(hours * 60)}m`;
    if (hours < 24) return `${hours}h`;
    return `${Math.floor(hours / 24)}d ${Math.round(hours % 24)}h`;
}
</script>

<template>
    <Head title="Reports" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Reports</h2>
                <div class="flex gap-1">
                    <button v-for="d in [7, 30, 90]" :key="d" @click="changePeriod(d)"
                        :class="days === d ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'"
                        class="rounded-md px-3 py-1.5 text-sm font-medium border border-gray-300">
                        {{ d }}d
                    </button>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">
                <!-- Summary Cards -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    <div class="rounded-lg bg-white p-6 shadow">
                        <div class="text-sm font-medium text-gray-500">Total Tickets</div>
                        <div class="mt-2 text-3xl font-bold text-gray-900">{{ totalTickets }}</div>
                    </div>
                    <div class="rounded-lg bg-white p-6 shadow">
                        <div class="text-sm font-medium text-gray-500">Open</div>
                        <div class="mt-2 text-3xl font-bold text-green-600">{{ statusBreakdown.open || 0 }}</div>
                    </div>
                    <div class="rounded-lg bg-white p-6 shadow">
                        <div class="text-sm font-medium text-gray-500">Avg Resolution Time</div>
                        <div class="mt-2 text-3xl font-bold text-indigo-600">{{ formatHours(avgResolutionHours) }}</div>
                    </div>
                    <div class="rounded-lg bg-white p-6 shadow">
                        <div class="text-sm font-medium text-gray-500">Avg Resolution (Business)</div>
                        <div class="mt-2 text-3xl font-bold text-purple-600">{{ formatHours(avgResolutionBusinessHours) }}</div>
                    </div>
                    <div class="rounded-lg bg-white p-6 shadow">
                        <div class="text-sm font-medium text-gray-500">Resolved (period)</div>
                        <div class="mt-2 text-3xl font-bold text-blue-600">{{ statusBreakdown.resolved || 0 }}</div>
                    </div>
                </div>

                <!-- Volume Chart -->
                <div class="rounded-lg bg-white p-6 shadow">
                    <h3 class="text-sm font-medium text-gray-500 mb-4">Ticket Volume (last {{ days }} days)</h3>
                    <div class="flex items-end gap-1 h-40">
                        <div v-for="(count, date) in volumeByDay" :key="date" class="flex-1 flex flex-col items-center">
                            <span class="text-xs text-gray-500 mb-1">{{ count }}</span>
                            <div class="w-full bg-indigo-500 rounded-t" :style="{ height: (count / maxVolume * 100) + '%', minHeight: count > 0 ? '4px' : '0' }" />
                        </div>
                    </div>
                    <div class="flex justify-between mt-2 text-xs text-gray-400">
                        <span>{{ Object.keys(volumeByDay)[0] }}</span>
                        <span>{{ Object.keys(volumeByDay).pop() }}</span>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <!-- Status Breakdown -->
                    <div class="rounded-lg bg-white p-6 shadow">
                        <h3 class="text-sm font-medium text-gray-500 mb-4">Status Breakdown</h3>
                        <div class="space-y-3">
                            <div v-for="(count, status) in statusBreakdown" :key="status" class="flex items-center gap-3">
                                <span class="w-20 text-sm text-gray-700 capitalize">{{ status }}</span>
                                <div class="flex-1 h-6 bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full rounded-full" :style="{ width: (count / totalTickets * 100) + '%', backgroundColor: statusColors[status] }" />
                                </div>
                                <span class="w-10 text-sm text-gray-600 text-right">{{ count }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Priority Breakdown -->
                    <div class="rounded-lg bg-white p-6 shadow">
                        <h3 class="text-sm font-medium text-gray-500 mb-4">Priority Breakdown</h3>
                        <div class="space-y-3">
                            <div v-for="(count, priority) in priorityBreakdown" :key="priority" class="flex items-center gap-3">
                                <span class="w-20 text-sm text-gray-700 capitalize">{{ priority }}</span>
                                <div class="flex-1 h-6 bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full rounded-full" :style="{ width: (count / totalTickets * 100) + '%', backgroundColor: priorityColors[priority] }" />
                                </div>
                                <span class="w-10 text-sm text-gray-600 text-right">{{ count }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Agent Performance -->
                <div class="rounded-lg bg-white p-6 shadow">
                    <h3 class="text-sm font-medium text-gray-500 mb-4">Agent Performance</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Agent</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Assigned</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Open</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Resolved</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Avg Response</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="agent in agentStats" :key="agent.id">
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ agent.name }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600 text-right">{{ agent.total_assigned }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600 text-right">{{ agent.open_count }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600 text-right">{{ agent.resolved_count }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600 text-right">{{ formatHours(agent.avg_response_hours) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Source Breakdown -->
                <div class="rounded-lg bg-white p-6 shadow">
                    <h3 class="text-sm font-medium text-gray-500 mb-4">Ticket Sources</h3>
                    <div class="flex gap-6">
                        <div v-for="(count, source) in sourceBreakdown" :key="source" class="text-center">
                            <div class="text-2xl font-bold text-gray-900">{{ count }}</div>
                            <div class="text-sm text-gray-500 capitalize">{{ source }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
