<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ConditionGroup from '@/Components/ConditionGroup.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    workflows: Array,
    teams: Array,
    agents: Array,
    labels: Array,
    customFields: Array,
});

const showForm = ref(false);
const editingWorkflow = ref(null);

const form = useForm({
    name: '',
    description: '',
    trigger_event: 'ticket_created',
    events: [],
    conditions: { match: 'all', rules: [] },
    actions: [],
    is_active: true,
    priority: 0,
});

function openCreate() {
    editingWorkflow.value = null;
    form.reset();
    form.conditions = { match: 'all', rules: [] };
    form.events = [{ entity: 'ticket', action: 'ticket_created' }];
    form.actions = [];
    showForm.value = true;
}

function openEdit(workflow) {
    editingWorkflow.value = workflow;
    form.name = workflow.name;
    form.description = workflow.description || '';
    form.trigger_event = workflow.trigger_event;
    form.events = workflow.events ? JSON.parse(JSON.stringify(workflow.events)) : [{ entity: 'ticket', action: workflow.trigger_event }];
    form.conditions = JSON.parse(JSON.stringify(workflow.conditions || { match: 'all', rules: [] }));
    form.actions = JSON.parse(JSON.stringify(workflow.actions || []));
    form.is_active = workflow.is_active;
    form.priority = workflow.priority;
    showForm.value = true;
}

function addEvent() {
    form.events.push({ entity: 'ticket', action: 'ticket_created' });
}

function removeEvent(index) {
    form.events.splice(index, 1);
}

function addAction() {
    form.actions.push({ type: 'assign_to_team', value: '' });
}

function removeAction(index) {
    form.actions.splice(index, 1);
}

function submit() {
    if (form.events.length > 0) {
        form.trigger_event = form.events[0].action;
    }

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

const standardFields = [
    { value: 'subject', label: 'Subject' },
    { value: 'body', label: 'Body' },
    { value: 'requester_email', label: 'Requester Email' },
    { value: 'requester_name', label: 'Requester Name' },
    { value: 'priority', label: 'Priority', options: ['low', 'normal', 'high', 'urgent'] },
    { value: 'status', label: 'Status', options: ['open', 'pending', 'resolved', 'closed'] },
    { value: 'source', label: 'Source', options: ['web', 'email', 'api'] },
    { value: 'team_id', label: 'Team' },
    { value: 'assigned_to', label: 'Assigned Agent' },
];

const customFieldOptions = (props.customFields || []).map(f => ({
    value: f.name,
    label: f.label,
    options: f.options,
}));

const conditionFields = [
    ...standardFields,
    ...customFieldOptions,
];

const operators = [
    { value: 'equals', label: 'Is Equal To' },
    { value: 'not_equals', label: 'Is Not Equal To' },
    { value: 'contains', label: 'Contains' },
    { value: 'not_contains', label: 'Does Not Contain' },
    { value: 'starts_with', label: 'Starts With' },
    { value: 'ends_with', label: 'Ends With' },
    { value: 'is_empty', label: 'Is Empty' },
    { value: 'is_not_empty', label: 'Is Not Empty' },
    { value: 'matches_regex', label: 'Matches Regex' },
];

const eventActions = [
    { value: 'ticket_created', label: 'Created' },
    { value: 'ticket_updated', label: 'Updated' },
    { value: 'ticket_assigned', label: 'Assigned' },
    { value: 'ticket_status_changed', label: 'Status Changed' },
    { value: 'ticket_priority_changed', label: 'Priority Changed' },
    { value: 'sla_response_breached', label: 'SLA Response Breached' },
    { value: 'sla_resolution_breached', label: 'SLA Resolution Breached' },
    { value: 'sla_warning', label: 'SLA At Risk (75%)' },
];

const actionTypes = [
    { value: 'assign_to_agent', label: 'Assign to Agent' },
    { value: 'assign_to_team', label: 'Assign to Team' },
    { value: 'assign_to_matching_team', label: 'Assign to Matching Team (by field)' },
    { value: 'round_robin', label: 'Round Robin (Team)' },
    { value: 'set_priority', label: 'Set Priority' },
    { value: 'set_status', label: 'Set Status' },
    { value: 'add_label', label: 'Add Label' },
    { value: 'remove_label', label: 'Remove Label' },
    { value: 'mail_agent', label: 'Mail Agent' },
    { value: 'mail_requester', label: 'Mail Requester' },
    { value: 'add_note', label: 'Add Internal Note' },
    { value: 'send_webhook', label: 'Send Webhook' },
];

function countRules(conditions) {
    if (!conditions?.rules) return 0;
    let count = 0;
    for (const rule of conditions.rules) {
        count += rule.match !== undefined ? countRules(rule) : 1;
    }
    return count;
}
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
                    <h3 class="text-lg font-medium mb-6">{{ editingWorkflow ? 'Edit' : 'Create' }} Workflow</h3>
                    <form @submit.prevent="submit" class="space-y-6">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Name</label>
                                <input v-model="form.name" type="text" required class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            </div>
                            <div class="grid grid-cols-2 gap-4">
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
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Description</label>
                            <input v-model="form.description" type="text" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        </div>

                        <!-- Events -->
                        <div class="rounded-lg border border-gray-200 p-4">
                            <div class="mb-3">
                                <h4 class="text-sm font-medium text-gray-700">Events</h4>
                                <p class="text-xs text-gray-400">When these events occur, check conditions and run actions</p>
                            </div>
                            <div class="space-y-2">
                                <div v-for="(event, i) in form.events" :key="i" class="flex items-center gap-2">
                                    <select v-model="event.entity" class="rounded-md border-gray-300 text-sm shadow-sm">
                                        <option value="ticket">Ticket</option>
                                    </select>
                                    <select v-model="event.action" class="rounded-md border-gray-300 text-sm shadow-sm">
                                        <option v-for="a in eventActions" :key="a.value" :value="a.value">{{ a.label }}</option>
                                    </select>
                                    <button v-if="form.events.length > 1" type="button" @click="removeEvent(i)" class="text-red-400 hover:text-red-600">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                    </button>
                                </div>
                            </div>
                            <button type="button" @click="addEvent" class="mt-2 text-sm text-indigo-600 hover:text-indigo-800">+ Add Event</button>
                        </div>

                        <!-- Conditions -->
                        <div>
                            <div class="mb-2">
                                <h4 class="text-sm font-medium text-gray-700">Conditions</h4>
                                <p class="text-xs text-gray-400">Build nested AND/OR groups to match specific scenarios</p>
                            </div>
                            <ConditionGroup
                                :group="form.conditions"
                                :fields="conditionFields"
                                :operators="operators"
                                @update:group="form.conditions = $event"
                            />
                        </div>

                        <!-- Actions -->
                        <div class="rounded-lg border border-gray-200 p-4">
                            <div class="mb-3">
                                <h4 class="text-sm font-medium text-gray-700">Actions</h4>
                                <p class="text-xs text-gray-400">What to do when conditions are met</p>
                            </div>
                            <div class="space-y-2">
                                <div v-for="(action, i) in form.actions" :key="i" class="flex items-center gap-2 rounded-md bg-gray-50 p-2">
                                    <select v-model="action.type" class="rounded-md border-gray-300 text-sm shadow-sm">
                                        <option v-for="at in actionTypes" :key="at.value" :value="at.value">{{ at.label }}</option>
                                    </select>

                                    <select v-if="action.type === 'assign_to_agent' || action.type === 'mail_agent'" v-model="action.value" class="flex-1 rounded-md border-gray-300 text-sm shadow-sm">
                                        <option value="">Select agent...</option>
                                        <option v-for="agent in agents" :key="agent.id" :value="agent.id">{{ agent.name }}</option>
                                    </select>
                                    <select v-else-if="['assign_to_team', 'round_robin'].includes(action.type)" v-model="action.value" class="flex-1 rounded-md border-gray-300 text-sm shadow-sm">
                                        <option value="">Select team...</option>
                                        <option v-for="team in teams" :key="team.id" :value="team.id">{{ team.name }}</option>
                                    </select>
                                    <select v-else-if="action.type === 'assign_to_matching_team'" v-model="action.value" class="flex-1 rounded-md border-gray-300 text-sm shadow-sm">
                                        <option value="">Select field to match...</option>
                                        <option v-for="cf in customFields" :key="cf.name" :value="cf.name">{{ cf.label }}</option>
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
                                    <select v-else-if="['add_label', 'remove_label'].includes(action.type)" v-model="action.value" class="flex-1 rounded-md border-gray-300 text-sm shadow-sm">
                                        <option value="">Select label...</option>
                                        <option v-for="label in labels" :key="label.id" :value="label.id">{{ label.name }}</option>
                                    </select>
                                    <textarea v-else-if="['add_note', 'mail_requester'].includes(action.type)" v-model="action.value" rows="2" placeholder="Message text..." class="flex-1 rounded-md border-gray-300 text-sm shadow-sm" />
                                    <input v-else-if="action.type === 'send_webhook'" v-model="action.value" type="url" placeholder="https://..." class="flex-1 rounded-md border-gray-300 text-sm shadow-sm" />
                                    <input v-else v-model="action.value" type="text" placeholder="Value" class="flex-1 rounded-md border-gray-300 text-sm shadow-sm" />

                                    <button type="button" @click="removeAction(i)" class="text-red-400 hover:text-red-600">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                    </button>
                                </div>
                            </div>
                            <button type="button" @click="addAction" class="mt-2 text-sm text-indigo-600 hover:text-indigo-800">+ Add Action</button>
                        </div>

                        <div class="flex gap-3 pt-2">
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
                                    <span class="inline-flex items-center justify-center rounded bg-gray-100 px-2 py-0.5 text-xs font-mono font-medium text-gray-600 w-8 text-center">{{ workflow.priority }}</span>
                                    <div>
                                        <div class="font-medium text-gray-900">{{ workflow.name }}</div>
                                        <div class="text-sm text-gray-500">
                                            <span v-if="workflow.events?.length">
                                                Events: {{ workflow.events.map(e => e.action.replace(/_/g, ' ')).join(', ') }}
                                            </span>
                                            <span v-else>
                                                Trigger: {{ workflow.trigger_event?.replace(/_/g, ' ') }}
                                            </span>
                                            &middot; {{ countRules(workflow.conditions) }} conditions
                                            &middot; {{ workflow.actions?.length || 0 }} actions
                                            <span v-if="workflow.description"> &middot; {{ workflow.description }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex gap-2">
                                    <button @click="openEdit(workflow)" class="text-sm text-indigo-600 hover:text-indigo-800">Edit</button>
                                    <button @click="router.post(route('workflows.duplicate', workflow.id))" class="text-sm text-gray-600 hover:text-gray-800">Duplicate</button>
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
