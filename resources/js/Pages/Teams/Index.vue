<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    teams: Array,
});

const showForm = ref(false);
const editingTeam = ref(null);

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
                                <input v-model="form.color" type="color" class="mt-1 h-10 w-20 rounded border-gray-300" />
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
                        <div v-for="team in teams" :key="team.id" class="flex items-center justify-between px-6 py-4">
                            <div class="flex items-center gap-3">
                                <span class="h-4 w-4 rounded-full" :style="{ backgroundColor: team.color }" />
                                <div>
                                    <div class="font-medium text-gray-900">{{ team.name }}</div>
                                    <div class="text-sm text-gray-500">{{ team.members_count }} members &middot; {{ team.tickets_count }} tickets</div>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <button @click="openEdit(team)" class="text-sm text-indigo-600 hover:text-indigo-800">Edit</button>
                                <button @click="deleteTeam(team)" class="text-sm text-red-600 hover:text-red-800">Delete</button>
                            </div>
                        </div>
                        <div v-if="!teams.length" class="px-6 py-8 text-center text-gray-500">No teams yet.</div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
