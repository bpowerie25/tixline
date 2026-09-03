<script setup>
import Modal from '@/Components/Modal.vue';

defineProps({
    show: Boolean,
});

const emit = defineEmits(['close', 'add']);

const widgetTypes = [
    { type: 'tickets_by_status', name: 'Tickets by Status', description: 'Breakdown of tickets by their current status', icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z' },
    { type: 'tickets_by_priority', name: 'Tickets by Priority', description: 'Distribution of tickets across priority levels', icon: 'M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12' },
    { type: 'tickets_by_team', name: 'Tickets by Team', description: 'How tickets are distributed among teams', icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z' },
    { type: 'tickets_by_agent', name: 'Tickets by Agent', description: 'Ticket count per assigned agent', icon: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z' },
    { type: 'tickets_by_source', name: 'Tickets by Source', description: 'Where tickets are coming from (web, email, API)', icon: 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z' },
    { type: 'tickets_by_label', name: 'Tickets by Label', description: 'Ticket distribution across labels', icon: 'M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z' },
    { type: 'ticket_volume', name: 'Ticket Volume Over Time', description: 'Track ticket creation trends over time', icon: 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6' },
    { type: 'avg_response_time', name: 'Avg Response Time', description: 'Average time to first response', icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z' },
    { type: 'avg_resolution_time', name: 'Avg Resolution Time', description: 'Average time to resolve tickets', icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z' },
    { type: 'avg_resolution_time_business', name: 'Avg Resolution Time (Business Hours)', description: 'Average resolution time excluding weekends', icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z' },
    { type: 'sla_compliance', name: 'SLA Compliance', description: 'Percentage of tickets meeting SLA targets', icon: 'M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z' },
    { type: 'agent_performance', name: 'Agent Performance', description: 'Detailed agent metrics table', icon: 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
    { type: 'ticket_list', name: 'Ticket List', description: 'Table of individual tickets with assignee, team, and submission date', icon: 'M4 6h16M4 10h16M4 14h16M4 18h16' },
];

function selectType(type) {
    emit('add', type);
    emit('close');
}
</script>

<template>
    <Modal :show="show" max-width="2xl" @close="$emit('close')">
        <div class="p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Add Widget</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <button
                    v-for="wt in widgetTypes" :key="wt.type"
                    @click="selectType(wt.type)"
                    class="flex items-start gap-3 rounded-lg border border-gray-200 p-4 text-left hover:border-indigo-300 hover:bg-indigo-50 transition-colors"
                >
                    <svg class="w-6 h-6 text-indigo-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" :d="wt.icon" />
                    </svg>
                    <div class="min-w-0">
                        <div class="text-sm font-medium text-gray-900">{{ wt.name }}</div>
                        <div class="text-xs text-gray-500 mt-0.5">{{ wt.description }}</div>
                    </div>
                </button>
            </div>
            <div class="mt-4 flex justify-end">
                <button @click="$emit('close')" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Cancel
                </button>
            </div>
        </div>
    </Modal>
</template>
