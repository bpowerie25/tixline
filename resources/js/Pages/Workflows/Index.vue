<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    workflows: Array,
    teams: Array,
    agents: Array,
    labels: Array,
});

const showForm = ref(false);
const editingWorkflow = ref(null);

const form = useForm({
    name: '',
    description: '',
    trigger_event: 'ticket_created',
    conditions: { match: 'all', rules: [] },
    actions: [],
    is_active: true,
    priority: 0,
});

function openCreate() {
    editingWorkflow.value = null;
    form.reset();
    form.conditions = { match: 'all', rules: [] };
    form.actions = [];
    showForm.value = true;
}

function openEdit(workflow) {
    editingWorkflow.value = workflow;
    form.name = workflow.name;
    form.description = workflow.description || '';
    form.trigger_event = workflow.trigger_event;
    form.conditions = JSON.parse(JSON.stringify(workflow.conditions));
    form.actions = JSON.parse(JSON.stringify(workflow.actions));
    form.is_active = workflow.is_active;
    form.priority = workflow.priority;
    showForm.value = true;
}

function addRule() {
    form.conditions.rules.push({ field: 'subject', operator: 'contains', value: '' });
}

function removeRule(index) {
    form.conditions.rules.splice(index, 1);
}

function addAction() {
    form.actions.push({ type: 'assign_to_team', value: '' });
}

function removeAction(index) {
    form.actions.splice(index, 1);
}

function submit() {
    if (editingWorkflow.value) {
        form.put(route('workflows.update', editingWorkflow.value.id), {
            onSuccess: () => { showForm.value = false; },
        });
    } else {
        form.post(route('workflows.store'), {
            onSuccess: () => { showForm.value = false; form.reset(); },
        });
    }
}

function deleteWorkflow(workflow) {
    if (confirm(`Delete workflow "${workflow.name}"?`)) {
        router.delete(route('workflows.destroy', workflow.id));
    }
}

function toggleActive(workflow) {
    router.put(route('workflows.update', workflow.id), {
        ...workflow,
        is_active: !workflow.is_active,
    }, { preserveScroll: true });
}

const conditionFields = [
    { value: 'subject', label: 'Subject' },
    { value: 'body', label: 'Body' },
    { value: 'requester_email', label: 'Requester Email' },
    { value: 'priority', label: 'Priority' },
    { value: 'status', label: 'Status' },
    { value: 'source', label: 'Source' },
];

const operators = [
    { value: 'equals', label: 'Equals' },
    { value: 'not_equals', label: 'Does not equal' },
    { value: 'contains', label: 'Contains' },
    { value: 'not_contains', label: 'Does not contain' },
    { value: 'starts_with', label: 'Starts with' },
    { value: 'ends_with', label: 'Ends with' },
    { value: 'is_empty', label: 'Is empty' },
    { value: 'is_not_empty', label: 'Is not empty' },
];

const actionTypes = [
    { value: 'assign_to_agent', label: 'Assign to Agent' },
    { value: 'assign_to_team', label: 'Assign to Team' },
    { value: 'round_robin', label: 'Round Robin (Team)' },
    { value: 'set_priority', label: 'Set Priority' },
    { value: 'set_status', label: 'Set Status' },
    { value: 'add_label', label: 'Add Label' },
];
</script>

<template>
    <Head title="Workflows" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Workflows</h2>
                <button @click="openCreate" class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    New Workflow
                </button>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
                <!-- Workflow Form -->
                <div v-if="showForm" class="mb-6 overflow-hidden bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium mb-4">{{ editingWorkflow ? 'Edit' : 'Create' }} Workflow</h3>
                    <form @submit.prevent="submit" class="space-y-6">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Name</label>
                                <input v-model="form.name" type="text" required class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Trigger</label>
                                <select v-model="form.trigger_event" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                                    <option value="ticket_created">Ticket Created</option>
                                    <option value="ticket_updated">Ticket Updated</option>
                                    <option value="ticket_assigned">Ticket Assigned</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Description</label>
                            <input v-model="form.description" type="text" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Priority (higher runs first)</label>
                                <input v-model.number="form.priority" type="number" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            </div>
                            <div class="flex items-end">
                                <label class="flex items-center gap-2">
                                    <input v-model="form.is_active" type="checkbox" class="rounded text-indigo-600" />
                                    <span class="text-sm font-medium text-gray-700">Active</span>
                                </label>
                            </div>
                        </div>

                        <!-- Conditions -->
                        <div class="rounded-lg border border-gray-200 p-4">
                            <div class="mb-3 flex items-center justify-between">
                                <h4 class="text-sm font-medium text-gray-700">Conditions</h4>
                                <div class="flex items-center gap-2">
                                    <span class="text-sm text-gray-500">Match</span>
                                    <select v-model="form.conditions.match" class="rounded-md border-gray-300 text-sm shadow-sm">
                                        <option value="all">All</option>
                                        <option value="any">Any</option>
                                    </select>
                                </div>
                            </div>
                            <div class="space-y-2">
                                <div v-for="(rule, i) in form.conditions.rules" :key="i" class="flex items-center gap-2">
                                    <select v-model="rule.field" class="rounded-md border-gray-300 text-sm shadow-sm">
                                        <option v-for="f in conditionFields" :key="f.value" :value="f.value">{{ f.label }}</option>
                                    </select>
                                    <select v-model="rule.operator" class="rounded-md border-gray-300 text-sm shadow-sm">
                                        <option v-for="op in operators" :key="op.value" :value="op.value">{{ op.label }}</option>
                                    </select>
                                    <input v-if="!['is_empty', 'is_not_empty'].includes(rule.operator)" v-model="rule.value" type="text" placeholder="Value" class="flex-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                    <button type="button" @click="removeRule(i)" class="text-red-500 hover:text-red-700">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                    </button>
                                </div>
                            </div>
                            <button type="button" @click="addRule" class="mt-2 text-sm text-indigo-600 hover:text-indigo-800">+ Add condition</button>
                        </div>

                        <!-- Actions -->
                        <div class="rounded-lg border border-gray-200 p-4">
                            <h4 class="mb-3 text-sm font-medium text-gray-700">Actions</h4>
                            <div class="space-y-2">
                                <div v-for="(action, i) in form.actions" :key="i" class="flex items-center gap-2">
                                    <select v-model="action.type" class="rounded-md border-gray-300 text-sm shadow-sm">
                                        <option v-for="at in actionTypes" :key="at.value" :value="at.value">{{ at.label }}</option>
                                    </select>

                                    <!-- Dynamic value input based on action type -->
                                    <select v-if="action.type === 'assign_to_agent'" v-model="action.value" class="flex-1 rounded-md border-gray-300 text-sm shadow-sm">
                                        <option value="">Select agent...</option>
                                        <option v-for="agent in agents" :key="agent.id" :value="agent.id">{{ agent.name }}</option>
                                    </select>
                                    <select v-else-if="['assign_to_team', 'round_robin'].includes(action.type)" v-model="action.value" class="flex-1 rounded-md border-gray-300 text-sm shadow-sm">
                                        <option value="">Select team...</option>
                                        <option v-for="team in teams" :key="team.id" :value="team.id">{{ team.name }}</option>
                                    </select>
                                    <select v-else-if="action.type === 'set_priority'" v-model="action.value" class="flex-1 rounded-md border-gray-300 text-sm shadow-sm">
                                        <option value="low">Low</option>
                                        <option value="normal">Normal</option>
                                        <option value="high">High</option>
                                        <option value="urgent">Urgent</option>
                                    </select>
                                    <select v-else-if="action.type === 'set_status'" v-model="action.value" class="flex-1 rounded-md border-gray-300 text-sm shadow-sm">
                                        <option value="open">Open</option>
                                        <option value="pending">Pending</option>
                                        <option value="resolved">Resolved</option>
                                        <option value="closed">Closed</option>
                                    </select>
                                    <select v-else-if="action.type === 'add_label'" v-model="action.value" class="flex-1 rounded-md border-gray-300 text-sm shadow-sm">
                                        <option value="">Select label...</option>
                                        <option v-for="label in labels" :key="label.id" :value="label.id">{{ label.name }}</option>
                                    </select>
                                    <input v-else v-model="action.value" type="text" placeholder="Value" class="flex-1 rounded-md border-gray-300 text-sm shadow-sm" />

                                    <button type="button" @click="removeAction(i)" class="text-red-500 hover:text-red-700">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                    </button>
                                </div>
                            </div>
                            <button type="button" @click="addAction" class="mt-2 text-sm text-indigo-600 hover:text-indigo-800">+ Add action</button>
                        </div>

                        <div class="flex gap-3">
                            <button type="submit" :disabled="form.processing" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                                {{ editingWorkflow ? 'Update' : 'Create' }}
                            </button>
                            <button type="button" @click="showForm = false" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Workflows List -->
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="divide-y divide-gray-200">
                        <div v-for="workflow in workflows" :key="workflow.id" class="px-6 py-4">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <button @click="toggleActive(workflow)" :class="workflow.is_active ? 'bg-green-500' : 'bg-gray-300'" class="relative inline-flex h-5 w-9 shrink-0 rounded-full transition-colors">
                                        <span :class="workflow.is_active ? 'translate-x-4' : 'translate-x-0'" class="inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition" />
                                    </button>
                                    <div>
                                        <div class="font-medium text-gray-900">{{ workflow.name }}</div>
                                        <div class="text-sm text-gray-500">
                                            Trigger: {{ workflow.trigger_event.replace(/_/g, ' ') }}
                                            &middot; {{ workflow.conditions?.rules?.length || 0 }} conditions
                                            &middot; {{ workflow.actions?.length || 0 }} actions
                                            <span v-if="workflow.description"> &middot; {{ workflow.description }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex gap-2">
                                    <button @click="openEdit(workflow)" class="text-sm text-indigo-600 hover:text-indigo-800">Edit</button>
                                    <button @click="deleteWorkflow(workflow)" class="text-sm text-red-600 hover:text-red-800">Delete</button>
                                </div>
                            </div>
                        </div>
                        <div v-if="!workflows.length" class="px-6 py-8 text-center text-gray-500">No workflows yet.</div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
