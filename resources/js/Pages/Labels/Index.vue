<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    labels: Array,
});

const showForm = ref(false);
const editingLabel = ref(null);

const form = useForm({
    name: '',
    color: '#8b5cf6',
});

function openCreate() {
    editingLabel.value = null;
    form.reset();
    showForm.value = true;
}

function openEdit(label) {
    editingLabel.value = label;
    form.name = label.name;
    form.color = label.color;
    showForm.value = true;
}

function submit() {
    if (editingLabel.value) {
        form.put(route('labels.update', editingLabel.value.id), {
            onSuccess: () => { showForm.value = false; },
        });
    } else {
        form.post(route('labels.store'), {
            onSuccess: () => { showForm.value = false; form.reset(); },
        });
    }
}

function deleteLabel(label) {
    if (confirm(`Delete label "${label.name}"?`)) {
        router.delete(route('labels.destroy', label.id));
    }
}
</script>

<template>
    <Head title="Labels" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Labels</h2>
                <button @click="openCreate" class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    New Label
                </button>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-4xl sm:px-6 lg:px-8">
                <div v-if="showForm" class="mb-6 overflow-hidden bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium mb-4">{{ editingLabel ? 'Edit' : 'Create' }} Label</h3>
                    <form @submit.prevent="submit" class="flex items-end gap-4">
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-gray-700">Name</label>
                            <input v-model="form.name" type="text" required class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Color</label>
                            <input v-model="form.color" type="color" class="mt-1 h-10 w-20 rounded border-gray-300" />
                        </div>
                        <button type="submit" :disabled="form.processing" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                            {{ editingLabel ? 'Update' : 'Create' }}
                        </button>
                        <button type="button" @click="showForm = false" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            Cancel
                        </button>
                    </form>
                </div>

                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="divide-y divide-gray-200">
                        <div v-for="label in labels" :key="label.id" class="flex items-center justify-between px-6 py-4">
                            <div class="flex items-center gap-3">
                                <span class="inline-flex rounded-full px-3 py-1 text-sm font-medium" :style="{ backgroundColor: label.color + '22', color: label.color }">
                                    {{ label.name }}
                                </span>
                                <span class="text-sm text-gray-500">{{ label.tickets_count }} tickets</span>
                            </div>
                            <div class="flex gap-2">
                                <button @click="openEdit(label)" class="text-sm text-indigo-600 hover:text-indigo-800">Edit</button>
                                <button @click="deleteLabel(label)" class="text-sm text-red-600 hover:text-red-800">Delete</button>
                            </div>
                        </div>
                        <div v-if="!labels.length" class="px-6 py-8 text-center text-gray-500">No labels yet.</div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
