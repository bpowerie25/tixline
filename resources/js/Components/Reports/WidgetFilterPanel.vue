<script setup>
import { reactive, watch } from 'vue';

const props = defineProps({
    filters: {
        type: Object,
        default: () => ({}),
    },
    teams: { type: Array, default: () => [] },
    agents: { type: Array, default: () => [] },
    labels: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:filters']);

const local = reactive({
    date_range: props.filters.date_range || '30d',
    date_from: props.filters.date_from || '',
    date_to: props.filters.date_to || '',
    team_id: props.filters.team_id || '',
    agent_id: props.filters.agent_id || '',
    status: props.filters.status || '',
    priority: props.filters.priority || '',
    label_ids: props.filters.label_ids || [],
});

watch(() => props.filters, (val) => {
    Object.assign(local, {
        date_range: val.date_range || '30d',
        date_from: val.date_from || '',
        date_to: val.date_to || '',
        team_id: val.team_id || '',
        agent_id: val.agent_id || '',
        status: val.status || '',
        priority: val.priority || '',
        label_ids: val.label_ids || [],
    });
}, { deep: true });

function emitUpdate() {
    emit('update:filters', { ...local });
}

function setDateRange(preset) {
    local.date_range = preset;
    local.date_from = '';
    local.date_to = '';
    emitUpdate();
}

function toggleLabel(id) {
    const idx = local.label_ids.indexOf(id);
    if (idx >= 0) {
        local.label_ids.splice(idx, 1);
    } else {
        local.label_ids.push(id);
    }
    emitUpdate();
}

const datePresets = ['7d', '30d', '90d', '365d'];
const statuses = ['open', 'pending', 'resolved', 'closed'];
const priorities = ['low', 'normal', 'high', 'urgent'];
</script>

<template>
    <div class="space-y-4">
        <!-- Date Range -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Date Range</label>
            <div class="flex gap-1 mb-2">
                <button
                    v-for="preset in datePresets" :key="preset"
                    @click="setDateRange(preset)"
                    :class="local.date_range === preset && !local.date_from ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'"
                    class="rounded-md px-3 py-1.5 text-sm font-medium border border-gray-300"
                >
                    {{ preset }}
                </button>
            </div>
            <div class="flex gap-2 items-center">
                <input
                    v-model="local.date_from"
                    type="date"
                    class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    @change="local.date_range = 'custom'; emitUpdate()"
                />
                <span class="text-gray-400 text-sm">to</span>
                <input
                    v-model="local.date_to"
                    type="date"
                    class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    @change="local.date_range = 'custom'; emitUpdate()"
                />
            </div>
        </div>

        <!-- Team -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Team</label>
            <select v-model="local.team_id" @change="emitUpdate" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">All Teams</option>
                <option v-for="team in teams" :key="team.id" :value="team.id">{{ team.name }}</option>
            </select>
        </div>

        <!-- Agent -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Agent</label>
            <select v-model="local.agent_id" @change="emitUpdate" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">All Agents</option>
                <option v-for="agent in agents" :key="agent.id" :value="agent.id">{{ agent.name }}</option>
            </select>
        </div>

        <!-- Status -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select v-model="local.status" @change="emitUpdate" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">All Statuses</option>
                <option v-for="s in statuses" :key="s" :value="s" class="capitalize">{{ s }}</option>
            </select>
        </div>

        <!-- Priority -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Priority</label>
            <select v-model="local.priority" @change="emitUpdate" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">All Priorities</option>
                <option v-for="p in priorities" :key="p" :value="p" class="capitalize">{{ p }}</option>
            </select>
        </div>

        <!-- Labels -->
        <div v-if="labels.length">
            <label class="block text-sm font-medium text-gray-700 mb-1">Labels</label>
            <div class="flex flex-wrap gap-1.5">
                <button
                    v-for="label in labels" :key="label.id"
                    @click="toggleLabel(label.id)"
                    :class="local.label_ids.includes(label.id) ? 'bg-indigo-100 text-indigo-700 border-indigo-300' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50'"
                    class="rounded-full px-2.5 py-0.5 text-xs font-medium border"
                >
                    {{ label.name }}
                </button>
            </div>
        </div>
    </div>
</template>
