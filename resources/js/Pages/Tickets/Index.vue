<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SlaBadge from '@/Components/SlaBadge.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';

const props = defineProps({
    tickets: Object,
    filters: Object,
    teams: Array,
    agents: Array,
});

const search = ref(props.filters.search || '');
const status = ref(props.filters.status || 'open');
const priority = ref(props.filters.priority || '');
const teamId = ref(props.filters.team_id || '');
const assignedTo = ref(props.filters.assigned_to || '');

let debounceTimer;
function applyFilters() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        router.get(route('tickets.index'), {
            search: search.value || undefined,
            status: status.value || undefined,
            priority: priority.value || undefined,
            team_id: teamId.value || undefined,
            assigned_to: assignedTo.value || undefined,
        }, { preserveState: true, replace: true });
    }, 300);
}

watch([search, status, priority, teamId, assignedTo], applyFilters);

// Bulk selection
const selected = ref([]);
const selectAll = ref(false);

function toggleSelectAll() {
    if (selectAll.value) {
        selected.value = props.tickets.data.map(t => t.id);
    } else {
        selected.value = [];
    }
}

const hasSelection = computed(() => selected.value.length > 0);

const bulkAssignTo = ref('');
const bulkTeamId = ref('');

function bulkAction(action, extra = {}) {
    const labels = {
        close: `Close ${selected.value.length} ticket(s)?`,
        resolve: `Resolve ${selected.value.length} ticket(s)?`,
        delete: `Delete ${selected.value.length} ticket(s)? This cannot be undone.`,
        spam: `Mark ${selected.value.length} ticket(s) as spam? This will delete them, blocklist the sender(s), and learn spam patterns.`,
        assign: `Assign ${selected.value.length} ticket(s)?`,
    };

    if (confirm(labels[action])) {
        router.post(route('tickets.bulk'), {
            ids: selected.value,
            action: action,
            ...extra,
        }, {
            onSuccess: () => {
                selected.value = [];
                selectAll.value = false;
                bulkAssignTo.value = '';
                bulkTeamId.value = '';
            },
        });
    }
}

const canBulkAssign = computed(() => bulkAssignTo.value || bulkTeamId.value);

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
    <Head title="Tickets" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Tickets</h2>
                <Link :href="route('tickets.create')" class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    New Ticket
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <!-- Flash messages -->
                <div v-if="$page.props.flash?.success" class="mb-4 rounded-md bg-green-50 border border-green-200 p-4">
                    <p class="text-sm text-green-800">{{ $page.props.flash.success }}</p>
                </div>

                <!-- Filters -->
                <div class="mb-6 flex flex-wrap gap-3">
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Search tickets..."
                        class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                    <select v-model="status" class="rounded-md border-gray-300 text-sm shadow-sm">
                        <option value="all">All Statuses</option>
                        <option value="open">Open</option>
                        <option value="pending">Pending</option>
                        <option value="resolved">Resolved</option>
                        <option value="closed">Closed</option>
                    </select>
                    <select v-model="priority" class="rounded-md border-gray-300 text-sm shadow-sm">
                        <option value="">All Priorities</option>
                        <option value="low">Low</option>
                        <option value="normal">Normal</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                    </select>
                    <select v-model="teamId" class="rounded-md border-gray-300 text-sm shadow-sm">
                        <option value="">All Teams</option>
                        <option v-for="team in teams" :key="team.id" :value="team.id">{{ team.name }}</option>
                    </select>
                    <select v-model="assignedTo" class="rounded-md border-gray-300 text-sm shadow-sm">
                        <option value="">All Agents</option>
                        <option v-for="agent in agents" :key="agent.id" :value="agent.id">{{ agent.name }}</option>
                    </select>
                </div>

                <!-- Bulk Action Bar -->
                <div v-if="hasSelection" class="mb-4 flex items-center gap-3 rounded-md bg-indigo-50 border border-indigo-200 px-4 py-3">
                    <span class="text-sm font-medium text-indigo-800">{{ selected.length }} selected</span>
                    <button @click="bulkAction('close')" class="rounded bg-gray-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-gray-700">Close</button>
                    <button @click="bulkAction('resolve')" class="rounded bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-700">Resolve</button>
                    <button @click="bulkAction('spam')" class="rounded bg-orange-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-orange-700">Spam</button>
                    <button @click="bulkAction('delete')" class="rounded bg-red-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-red-700">Delete</button>
                    <span class="mx-1 text-gray-300">|</span>
                    <select v-model="bulkTeamId" class="rounded border-gray-300 text-xs shadow-sm py-1.5">
                        <option value="">Team...</option>
                        <option v-for="team in teams" :key="team.id" :value="team.id">{{ team.name }}</option>
                    </select>
                    <select v-model="bulkAssignTo" class="rounded border-gray-300 text-xs shadow-sm py-1.5">
                        <option value="">Agent...</option>
                        <option v-for="agent in agents" :key="agent.id" :value="agent.id">{{ agent.name }}</option>
                    </select>
                    <button @click="bulkAction('assign', { assigned_to: bulkAssignTo || undefined, team_id: bulkTeamId || undefined })" :disabled="!canBulkAssign" :class="[canBulkAssign ? 'bg-indigo-600 hover:bg-indigo-700' : 'bg-indigo-300 cursor-not-allowed', 'rounded px-3 py-1.5 text-xs font-medium text-white']">Assign</button>
                    <button @click="selected = []; selectAll = false" class="ml-auto text-xs text-gray-500 hover:text-gray-700">Clear selection</button>
                </div>

                <!-- Ticket List -->
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="divide-y divide-gray-200">
                        <!-- Select all header -->
                        <div class="flex items-center px-6 py-2 bg-gray-50 border-b border-gray-200">
                            <label class="flex items-center gap-2">
                                <input type="checkbox" v-model="selectAll" @change="toggleSelectAll" class="rounded text-indigo-600" />
                                <span class="text-xs text-gray-500">Select all</span>
                            </label>
                        </div>

                        <div
                            v-for="ticket in tickets.data"
                            :key="ticket.id"
                            class="flex items-center px-6 py-4 hover:bg-gray-50"
                        >
                            <input
                                type="checkbox"
                                :value="ticket.id"
                                v-model="selected"
                                class="mr-4 rounded text-indigo-600 shrink-0"
                                @click.stop
                            />
                            <Link
                                :href="route('tickets.show', ticket.id)"
                                class="flex flex-1 items-center justify-between min-w-0"
                            >
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-3">
                                        <span class="text-sm font-mono text-gray-500">{{ ticket.reference }}</span>
                                        <span class="text-sm font-medium text-gray-900 truncate">{{ ticket.subject }}</span>
                                        <span
                                            v-for="label in ticket.labels"
                                            :key="label.id"
                                            :style="{ backgroundColor: label.color + '22', color: label.color }"
                                            class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                        >
                                            {{ label.name }}
                                        </span>
                                    </div>
                                    <div class="mt-1 text-sm text-gray-500">
                                        {{ ticket.requester_name }} &lt;{{ ticket.requester_email }}&gt;
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
                                    <SlaBadge :ticket="ticket" />
                                    <span v-if="ticket.assignee" class="text-xs text-gray-500">
                                        {{ ticket.assignee.name }}
                                    </span>
                                </div>
                            </Link>
                        </div>
                        <div v-if="!tickets.data.length" class="px-6 py-8 text-center text-gray-500">
                            No {{ status === 'all' ? '' : status }} tickets found.
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
