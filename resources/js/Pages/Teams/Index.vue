<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    teams: Array,
    agents: Array,
});

const showForm = ref(false);
const editingTeam = ref(null);
const managingTeam = ref(null);
const addAgentId = ref('');

const form = useForm({
    name: '',
    description: '',
    color: '#6366f1',
});

function openCreate() {
    editingTeam.value = null;
    form.reset();
    showForm.value = true;
}

function openEdit(team) {
    editingTeam.value = team;
    form.name = team.name;
    form.description = team.description || '';
    form.color = team.color;
    showForm.value = true;
}

function submit() {
    if (editingTeam.value) {
        form.put(route('teams.update', editingTeam.value.id), {
            onSuccess: () => { showForm.value = false; },
        });
    } else {
        form.post(route('teams.store'), {
            onSuccess: () => { showForm.value = false; form.reset(); },
        });
    }
}

function deleteTeam(team) {
    if (confirm(`Delete team "${team.name}"?`)) {
        router.delete(route('teams.destroy', team.id));
    }
}

function toggleManage(team) {
    managingTeam.value = managingTeam.value?.id === team.id ? null : team;
    addAgentId.value = '';
}

const availableAgents = computed(() => {
    if (!managingTeam.value) return [];
    return props.agents.filter(a => a.team_id !== managingTeam.value.id);
});

function addMember() {
    if (!addAgentId.value) return;
    router.post(route('teams.add-member', managingTeam.value.id), {
        user_id: addAgentId.value,
    }, {
        onSuccess: () => { addAgentId.value = ''; },
    });
}

function removeMember(userId) {
    router.delete(route('teams.remove-member', [managingTeam.value.id, userId]));
}
</script>

<template>
    <Head title="Teams" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Teams</h2>
                <button @click="openCreate" class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    New Team
                </button>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-4xl sm:px-6 lg:px-8">
                <!-- Flash -->
                <div v-if="$page.props.flash?.success" class="mb-4 rounded-md bg-green-50 border border-green-200 p-4">
                    <p class="text-sm text-green-800">{{ $page.props.flash.success }}</p>
                </div>

                <!-- Form -->
                <div v-if="showForm" class="mb-6 overflow-hidden bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium mb-4">{{ editingTeam ? 'Edit' : 'Create' }} Team</h3>
                    <form @submit.prevent="submit" class="space-y-4">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Name</label>
                                <input v-model="form.name" type="text" required class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Color</label>
                                <div class="mt-1 relative">
                                    <input v-model="form.color" type="color" class="absolute left-2 top-1/2 -translate-y-1/2 h-6 w-6 shrink-0 cursor-pointer rounded border-0 p-0" style="appearance: auto; -webkit-appearance: auto;" />
                                    <input v-model="form.color" type="text" class="w-full rounded-md border-gray-300 text-sm shadow-sm font-mono pl-10" />
                                </div>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Description</label>
                            <textarea v-model="form.description" rows="2" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        </div>
                        <div class="flex gap-3">
                            <button type="submit" :disabled="form.processing" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                                {{ editingTeam ? 'Update' : 'Create' }}
                            </button>
                            <button type="button" @click="showForm = false" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Teams List -->
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="divide-y divide-gray-200">
                        <div v-for="team in teams" :key="team.id">
                            <div class="flex items-center justify-between px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <span class="h-4 w-4 rounded-full shrink-0" :style="{ backgroundColor: team.color }" />
                                    <div>
                                        <div class="font-medium text-gray-900">{{ team.name }}</div>
                                        <div class="text-sm text-gray-500">{{ team.members_count }} members &middot; {{ team.tickets_count }} tickets</div>
                                    </div>
                                </div>
                                <div class="flex gap-2">
                                    <button @click="toggleManage(team)" class="text-sm" :class="managingTeam?.id === team.id ? 'text-indigo-800 font-medium' : 'text-indigo-600 hover:text-indigo-800'">
                                        Members
                                    </button>
                                    <button @click="openEdit(team)" class="text-sm text-indigo-600 hover:text-indigo-800">Edit</button>
                                    <button @click="deleteTeam(team)" class="text-sm text-red-600 hover:text-red-800">Delete</button>
                                </div>
                            </div>

                            <!-- Members Panel -->
                            <div v-if="managingTeam?.id === team.id" class="border-t border-gray-100 bg-gray-50 px-6 py-4">
                                <!-- Add agent -->
                                <div class="flex items-center gap-2 mb-3">
                                    <select v-model="addAgentId" class="flex-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="">Select agent to add...</option>
                                        <option v-for="agent in availableAgents" :key="agent.id" :value="agent.id">
                                            {{ agent.name }} ({{ agent.email }})
                                        </option>
                                    </select>
                                    <button @click="addMember" :disabled="!addAgentId" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                                        Add
                                    </button>
                                </div>

                                <!-- Current members -->
                                <div v-if="team.members?.length" class="space-y-2">
                                    <div v-for="member in team.members" :key="member.id" class="flex items-center justify-between rounded bg-white px-3 py-2">
                                        <div>
                                            <span class="text-sm font-medium text-gray-900">{{ member.name }}</span>
                                            <span class="ml-2 text-sm text-gray-500">{{ member.email }}</span>
                                        </div>
                                        <button @click="removeMember(member.id)" class="text-xs text-red-500 hover:text-red-700">Remove</button>
                                    </div>
                                </div>
                                <p v-else class="text-sm text-gray-400">No members in this team.</p>
                            </div>
                        </div>
                        <div v-if="!teams.length" class="px-6 py-8 text-center text-gray-500">No teams yet.</div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
