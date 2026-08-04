<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    policies: Array,
});

const showForm = ref(false);
const editing = ref(null);

const form = useForm({
    name: '',
    description: '',
    priority: 'normal',
    first_response_hours: 4,
    resolution_hours: 24,
    is_active: true,
});

const usedPriorities = props.policies.map(p => p.priority);

function openCreate() {
    editing.value = null;
    form.reset();
    showForm.value = true;
}

function openEdit(policy) {
    editing.value = policy;
    form.name = policy.name;
    form.description = policy.description || '';
    form.priority = policy.priority;
    form.first_response_hours = policy.first_response_hours;
    form.resolution_hours = policy.resolution_hours;
    form.is_active = policy.is_active;
    showForm.value = true;
}

function submit() {
    if (editing.value) {
        form.put(route('sla-policies.update', editing.value.id), {
            onSuccess: () => { showForm.value = false; },
        });
    } else {
        form.post(route('sla-policies.store'), {
            onSuccess: () => { showForm.value = false; form.reset(); },
        });
    }
}

function deletePolicy(policy) {
    if (confirm(`Delete SLA policy "${policy.name}"?`)) {
        router.delete(route('sla-policies.destroy', policy.id));
    }
}

const priorityColors = {
    low: 'bg-gray-100 text-gray-700',
    normal: 'bg-blue-100 text-blue-700',
    high: 'bg-orange-100 text-orange-700',
    urgent: 'bg-red-100 text-red-700',
};

function formatHours(hours) {
    if (hours < 24) return `${hours}h`;
    const days = Math.floor(hours / 24);
    const rem = hours % 24;
    return rem ? `${days}d ${rem}h` : `${days}d`;
}
</script>

<template>
    <Head title="SLA Policies" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">SLA Policies</h2>
                <button @click="openCreate" class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    New Policy
                </button>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-4xl sm:px-6 lg:px-8">
                <div v-if="showForm" class="mb-6 overflow-hidden bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium mb-4">{{ editing ? 'Edit' : 'Create' }} SLA Policy</h3>
                    <form @submit.prevent="submit" class="space-y-4">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Name</label>
                                <input v-model="form.name" type="text" required class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="e.g. Urgent SLA" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Priority</label>
                                <select v-model="form.priority" :disabled="!!editing" class="mt-1 w-full rounded-md border-gray-300 shadow-sm disabled:opacity-50">
                                    <option value="low">Low</option>
                                    <option value="normal">Normal</option>
                                    <option value="high">High</option>
                                    <option value="urgent">Urgent</option>
                                </select>
                                <p v-if="form.errors.priority" class="mt-1 text-sm text-red-600">{{ form.errors.priority }}</p>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Description</label>
                            <input v-model="form.description" type="text" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        </div>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">First Response Target (hours)</label>
                                <input v-model.number="form.first_response_hours" type="number" min="1" required class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Resolution Target (hours)</label>
                                <input v-model.number="form.resolution_hours" type="number" min="1" required class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            </div>
                        </div>
                        <label class="flex items-center gap-2">
                            <input v-model="form.is_active" type="checkbox" class="rounded text-indigo-600" />
                            <span class="text-sm text-gray-700">Active</span>
                        </label>
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
                        <div v-for="policy in policies" :key="policy.id" class="flex items-center justify-between px-6 py-4">
                            <div class="flex items-center gap-4">
                                <span :class="[priorityColors[policy.priority], 'inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium capitalize']">
                                    {{ policy.priority }}
                                </span>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-medium text-gray-900">{{ policy.name }}</span>
                                        <span v-if="!policy.is_active" class="text-xs text-gray-400">(inactive)</span>
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        First response: {{ formatHours(policy.first_response_hours) }} &middot;
                                        Resolution: {{ formatHours(policy.resolution_hours) }}
                                    </div>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <button @click="openEdit(policy)" class="text-sm text-indigo-600 hover:text-indigo-800">Edit</button>
                                <button @click="deletePolicy(policy)" class="text-sm text-red-600 hover:text-red-800">Delete</button>
                            </div>
                        </div>
                        <div v-if="!policies.length" class="px-6 py-8 text-center text-gray-500">
                            No SLA policies configured. Create one per priority level.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
