<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    departments: Array,
    users: Array,
});

const showForm = ref(false);
const editing = ref(null);

const form = useForm({
    name: '',
    description: '',
    manager_id: null,
});

function openCreate() {
    editing.value = null;
    form.reset();
    showForm.value = true;
}

function openEdit(dept) {
    editing.value = dept;
    form.name = dept.name;
    form.description = dept.description || '';
    form.manager_id = dept.manager_id;
    showForm.value = true;
}

function submit() {
    if (editing.value) {
        form.put(route('departments.update', editing.value.id), {
            onSuccess: () => { showForm.value = false; },
        });
    } else {
        form.post(route('departments.store'), {
            onSuccess: () => { showForm.value = false; form.reset(); },
        });
    }
}

function deleteDept(dept) {
    if (confirm(`Delete department "${dept.name}"?`)) {
        router.delete(route('departments.destroy', dept.id));
    }
}
</script>

<template>
    <Head title="Departments" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Departments</h2>
                <button @click="openCreate" class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    New Department
                </button>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-4xl sm:px-6 lg:px-8">
                <div v-if="showForm" class="mb-6 overflow-hidden bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium mb-4">{{ editing ? 'Edit' : 'Create' }} Department</h3>
                    <form @submit.prevent="submit" class="space-y-4">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Name</label>
                                <input v-model="form.name" type="text" required class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Manager</label>
                                <select v-model="form.manager_id" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                                    <option :value="null">None</option>
                                    <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Description</label>
                            <textarea v-model="form.description" rows="2" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        </div>
                        <div class="flex gap-3">
                            <button type="submit" :disabled="form.processing" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                                {{ editing ? 'Update' : 'Create' }}
                            </button>
                            <button type="button" @click="showForm = false" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>

                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="divide-y divide-gray-200">
                        <div v-for="dept in departments" :key="dept.id" class="flex items-center justify-between px-6 py-4">
                            <div>
                                <div class="font-medium text-gray-900">{{ dept.name }}</div>
                                <div class="text-sm text-gray-500">
                                    {{ dept.teams_count }} teams
                                    <span v-if="dept.manager"> &middot; Manager: {{ dept.manager.name }}</span>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <button @click="openEdit(dept)" class="text-sm text-indigo-600 hover:text-indigo-800">Edit</button>
                                <button @click="deleteDept(dept)" class="text-sm text-red-600 hover:text-red-800">Delete</button>
                            </div>
                        </div>
                        <div v-if="!departments.length" class="px-6 py-8 text-center text-gray-500">No departments yet.</div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
