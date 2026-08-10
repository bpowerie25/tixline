<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    agents: Array,
    teams: Array,
    roles: Array,
});

const showForm = ref(false);
const editingAgent = ref(null);

const form = useForm({
    name: '',
    email: '',
    password: '',
    role_id: '',
    team_ids: [],
});

function openCreate() {
    editingAgent.value = null;
    form.reset();
    form.role_id = '';
    showForm.value = true;
}

function openEdit(agent) {
    editingAgent.value = agent;
    form.name = agent.name;
    form.email = agent.email;
    form.password = '';
    form.role_id = agent.role_id || '';
    form.team_ids = (agent.teams || []).map(t => t.id);
    showForm.value = true;
}

function submit() {
    if (editingAgent.value) {
        form.put(route('agents.update', editingAgent.value.id), {
            onSuccess: () => { showForm.value = false; form.reset(); },
        });
    } else {
        form.post(route('agents.store'), {
            onSuccess: () => { showForm.value = false; form.reset(); },
        });
    }
}

function deleteAgent(agent) {
    if (confirm(`Delete ${agent.name}? This cannot be undone.`)) {
        form.delete(route('agents.destroy', agent.id));
    }
}

function sendInvite(agent) {
    router.post(route('agents.invite', agent.id));
}
</script>

<template>
    <Head title="Agents" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Agents</h2>
                <button @click="openCreate" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    New Agent
                </button>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">
                <div v-if="$page.props.flash?.success" class="rounded-md bg-green-50 border border-green-200 p-4">
                    <p class="text-sm text-green-800">{{ $page.props.flash.success }}</p>
                </div>
                <!-- Form -->
                <div v-if="showForm" class="overflow-hidden bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">{{ editingAgent ? 'Edit Agent' : 'Create Agent' }}</h3>
                    <form @submit.prevent="submit" class="space-y-4">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Name</label>
                                <input v-model="form.name" type="text" required class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Email</label>
                                <input v-model="form.email" type="email" required class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">
                                    Password
                                    <span v-if="editingAgent" class="text-gray-400 font-normal">(leave blank to keep current)</span>
                                </label>
                                <input v-model="form.password" type="password" :required="!editingAgent" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                <p v-if="form.errors.password" class="mt-1 text-sm text-red-600">{{ form.errors.password }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Role</label>
                                <select v-model="form.role_id" required class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">Select role...</option>
                                    <option v-for="role in roles" :key="role.id" :value="role.id">{{ role.display_name }}</option>
                                </select>
                                <p v-if="form.errors.role_id" class="mt-1 text-sm text-red-600">{{ form.errors.role_id }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Teams</label>
                                <div class="max-h-40 overflow-y-auto space-y-1 rounded-md border border-gray-300 p-2">
                                    <label v-for="team in teams" :key="team.id" class="flex items-center gap-2">
                                        <input type="checkbox" :value="team.id" v-model="form.team_ids" class="rounded text-indigo-600" />
                                        <span class="text-sm text-gray-700">{{ team.name }}</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <button type="submit" :disabled="form.processing" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                                {{ editingAgent ? 'Update Agent' : 'Create Agent' }}
                            </button>
                            <button type="button" @click="showForm = false" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>

                <!-- List -->
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="divide-y divide-gray-200">
                        <div v-for="agent in agents" :key="agent.id" class="flex items-center justify-between px-6 py-4">
                            <div class="flex items-center gap-4">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gray-200 text-sm font-medium text-gray-600">
                                    {{ agent.name.charAt(0).toUpperCase() }}
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-medium text-gray-900">{{ agent.name }}</span>
                                        <span v-if="agent.role" class="inline-flex rounded-full bg-indigo-100 text-indigo-700 px-2 py-0.5 text-xs font-medium">
                                            {{ agent.role?.display_name }}
                                        </span>
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        {{ agent.email }}
                                        <span v-if="agent.teams?.length" class="ml-2">&middot; {{ agent.teams.map(t => t.name).join(', ') }}</span>
                                        <span class="ml-2">&middot; {{ agent.assigned_tickets_count }} tickets</span>
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <button @click="sendInvite(agent)" class="text-sm text-gray-600 hover:text-gray-800">Send Invite</button>
                                <button @click="openEdit(agent)" class="text-sm text-indigo-600 hover:text-indigo-800">Edit</button>
                                <button @click="deleteAgent(agent)" class="text-sm text-red-600 hover:text-red-800">Delete</button>
                            </div>
                        </div>
                        <div v-if="!agents.length" class="px-6 py-8 text-center text-gray-500">
                            No agents yet. Click "New Agent" to create one.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
