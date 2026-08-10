<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    logs: Object,
    filters: Object,
    actions: Array,
    agents: Array,
});

const search = ref(props.filters?.search || '');
const action = ref(props.filters?.action || '');
const userId = ref(props.filters?.user_id || '');
const dateFrom = ref(props.filters?.date_from || '');
const dateTo = ref(props.filters?.date_to || '');

let debounceTimer;
watch(search, (val) => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => applyFilters(), 300);
});

function applyFilters() {
    router.get(route('activity-logs.index'), {
        search: search.value || undefined,
        action: action.value || undefined,
        user_id: userId.value || undefined,
        date_from: dateFrom.value || undefined,
        date_to: dateTo.value || undefined,
    }, { preserveState: true, replace: true });
}

function clearFilters() {
    search.value = '';
    action.value = '';
    userId.value = '';
    dateFrom.value = '';
    dateTo.value = '';
    router.get(route('activity-logs.index'), {}, { preserveState: true, replace: true });
}

function formatDate(dateStr) {
    return new Date(dateStr).toLocaleString();
}

function actionLabel(action) {
    return action.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
}

const actionColors = {
    login: 'bg-green-100 text-green-700',
    logout: 'bg-gray-100 text-gray-700',
    ticket_created: 'bg-blue-100 text-blue-700',
    ticket_updated: 'bg-yellow-100 text-yellow-700',
    ticket_deleted: 'bg-red-100 text-red-700',
    agent_created: 'bg-indigo-100 text-indigo-700',
    agent_updated: 'bg-indigo-100 text-indigo-700',
    agent_deleted: 'bg-red-100 text-red-700',
    tenant_created: 'bg-purple-100 text-purple-700',
    tenant_updated: 'bg-purple-100 text-purple-700',
};

function actionColor(action) {
    return actionColors[action] || 'bg-gray-100 text-gray-600';
}
</script>

<template>
    <Head title="Activity Logs" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Activity Logs</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">
                <!-- Filters -->
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Search</label>
                            <input v-model="search" type="text" placeholder="Search logs..." class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Action</label>
                            <select v-model="action" @change="applyFilters" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">All actions</option>
                                <option v-for="a in actions" :key="a" :value="a">{{ actionLabel(a) }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Agent</label>
                            <select v-model="userId" @change="applyFilters" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">All agents</option>
                                <option v-for="agent in agents" :key="agent.id" :value="agent.id">{{ agent.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">From</label>
                            <input v-model="dateFrom" type="date" @change="applyFilters" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">To</label>
                            <div class="flex gap-2">
                                <input v-model="dateTo" type="date" @change="applyFilters" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                <button @click="clearFilters" class="shrink-0 rounded-md border border-gray-300 px-3 py-2 text-xs font-medium text-gray-600 hover:bg-gray-50">Clear</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Log Table -->
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">When</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Agent</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Action</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Description</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">IP</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <tr v-for="log in logs.data" :key="log.id" class="hover:bg-gray-50">
                                <td class="whitespace-nowrap px-6 py-3 text-sm text-gray-500">{{ formatDate(log.created_at) }}</td>
                                <td class="whitespace-nowrap px-6 py-3 text-sm text-gray-900">{{ log.user?.name || 'System' }}</td>
                                <td class="whitespace-nowrap px-6 py-3">
                                    <span :class="actionColor(log.action)" class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium">
                                        {{ actionLabel(log.action) }}
                                    </span>
                                </td>
                                <td class="px-6 py-3 text-sm text-gray-600 max-w-md truncate">{{ log.description }}</td>
                                <td class="whitespace-nowrap px-6 py-3 text-sm text-gray-400 font-mono text-xs">{{ log.ip_address }}</td>
                            </tr>
                            <tr v-if="!logs.data.length">
                                <td colspan="5" class="px-6 py-8 text-center text-gray-500">No activity logs found.</td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Pagination -->
                    <div v-if="logs.last_page > 1" class="flex items-center justify-between border-t border-gray-200 bg-white px-6 py-3">
                        <p class="text-sm text-gray-500">
                            Showing {{ logs.from }} to {{ logs.to }} of {{ logs.total }} entries
                        </p>
                        <div class="flex gap-1">
                            <template v-for="link in logs.links" :key="link.label">
                                <button
                                    v-if="link.url"
                                    @click="router.get(link.url, {}, { preserveState: true })"
                                    :class="link.active ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'"
                                    class="rounded-md border border-gray-300 px-3 py-1 text-sm"
                                    v-html="link.label"
                                />
                                <span v-else class="rounded-md border border-gray-200 bg-gray-50 px-3 py-1 text-sm text-gray-400" v-html="link.label" />
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
