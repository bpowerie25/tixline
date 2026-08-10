<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import { Head, useForm, router, Link } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    reports: Array,
});

const showCreateModal = ref(false);

const form = useForm({
    name: '',
    description: '',
});

function openCreate() {
    form.reset();
    showCreateModal.value = true;
}

function submit() {
    form.post(route('custom-reports.store'), {
        onSuccess: () => {
            showCreateModal.value = false;
            form.reset();
        },
    });
}

function deleteReport(report) {
    if (confirm(`Delete report "${report.name}"?`)) {
        router.delete(route('custom-reports.destroy', report.id));
    }
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}
</script>

<template>
    <Head title="Custom Reports" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Custom Reports</h2>
                <button @click="openCreate" class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    New Report
                </button>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="divide-y divide-gray-200">
                        <div v-for="report in reports" :key="report.id" class="px-6 py-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <Link :href="route('custom-reports.show', report.id)" class="font-medium text-indigo-600 hover:text-indigo-800">
                                            {{ report.name }}
                                        </Link>
                                        <span v-if="report.is_shared" class="inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">
                                            Shared
                                        </span>
                                    </div>
                                    <div class="text-sm text-gray-500 mt-0.5">
                                        <span v-if="report.description">{{ report.description }} &middot; </span>
                                        Created {{ formatDate(report.created_at) }}
                                        <span v-if="report.widgets_count !== undefined"> &middot; {{ report.widgets_count }} widget{{ report.widgets_count !== 1 ? 's' : '' }}</span>
                                    </div>
                                </div>
                                <div class="flex gap-2">
                                    <Link :href="route('custom-reports.show', report.id)" class="text-sm text-indigo-600 hover:text-indigo-800">Edit</Link>
                                    <button @click="deleteReport(report)" class="text-sm text-red-600 hover:text-red-800">Delete</button>
                                </div>
                            </div>
                        </div>
                        <div v-if="!reports.length" class="px-6 py-8 text-center text-gray-500">
                            No custom reports yet. Create one to get started.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create Modal -->
        <Modal :show="showCreateModal" max-width="md" @close="showCreateModal = false">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Create Custom Report</h3>
                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Name</label>
                        <input v-model="form.name" type="text" required class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Monthly Overview" />
                        <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Description</label>
                        <input v-model="form.description" type="text" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Optional description" />
                        <p v-if="form.errors.description" class="mt-1 text-sm text-red-600">{{ form.errors.description }}</p>
                    </div>
                    <div class="flex gap-3 pt-2">
                        <button type="submit" :disabled="form.processing" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                            Create
                        </button>
                        <button type="button" @click="showCreateModal = false" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
