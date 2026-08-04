<script setup>
import { computed } from 'vue';

const props = defineProps({
    ticket: Object,
});

const badge = computed(() => {
    const s = props.ticket.sla_status;
    if (!s || s === 'met') return null;

    return {
        breached: { label: 'SLA Breached', class: 'bg-red-100 text-red-700 ring-1 ring-red-300' },
        warning: { label: 'SLA At Risk', class: 'bg-amber-100 text-amber-700 ring-1 ring-amber-300' },
        on_track: { label: 'SLA OK', class: 'bg-green-100 text-green-700' },
    }[s] || null;
});

function timeRemaining(due) {
    if (!due) return null;
    const now = new Date();
    const dueDate = new Date(due);
    const diff = dueDate - now;
    if (diff <= 0) return 'Overdue';
    const hours = Math.floor(diff / 3600000);
    const mins = Math.floor((diff % 3600000) / 60000);
    if (hours > 24) return `${Math.floor(hours / 24)}d ${hours % 24}h`;
    if (hours > 0) return `${hours}h ${mins}m`;
    return `${mins}m`;
}
</script>

<template>
    <span v-if="badge" :class="[badge.class, 'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium']">
        {{ badge.label }}
        <span v-if="ticket.sla_resolution_due_at && ticket.sla_status !== 'met'" class="text-[10px] opacity-75">
            ({{ timeRemaining(ticket.sla_resolution_due_at) }})
        </span>
    </span>
</template>
