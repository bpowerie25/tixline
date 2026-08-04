<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    teams: Array,
    agents: Array,
    labels: Array,
});

const form = useForm({
    subject: '',
    body: '',
    requester_name: '',
    requester_email: '',
    priority: 'normal',
    team_id: null,
    assigned_to: null,
    labels: [],
});

function submit() {
    form.post(route('tickets.store'));
}
</script>

<template>
    <Head title="Create Ticket" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('tickets.index')" class="text-gray-400 hover:text-gray-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                </Link>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Create Ticket</h2>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg p-6">
                    <form @submit.prevent="submit" class="space-y-4">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Requester Name</label>
                                <input v-model="form.requester_name" type="text" required class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                <p v-if="form.errors.requester_name" class="mt-1 text-sm text-red-600">{{ form.errors.requester_name }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Requester Email</label>
                                <input v-model="form.requester_email" type="email" required class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                <p v-if="form.errors.requester_email" class="mt-1 text-sm text-red-600">{{ form.errors.requester_email }}</p>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Subject</label>
                            <input v-model="form.subject" type="text" required class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            <p v-if="form.errors.subject" class="mt-1 text-sm text-red-600">{{ form.errors.subject }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Description</label>
                            <textarea v-model="form.body" rows="6" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Priority</label>
                                <select v-model="form.priority" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                                    <option value="low">Low</option>
                                    <option value="normal">Normal</option>
                                    <option value="high">High</option>
                                    <option value="urgent">Urgent</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Team</label>
                                <select v-model="form.team_id" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                                    <option :value="null">None</option>
                                    <option v-for="team in teams" :key="team.id" :value="team.id">{{ team.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Assign To</label>
                                <select v-model="form.assigned_to" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                                    <option :value="null">Unassigned</option>
                                    <option v-for="agent in agents" :key="agent.id" :value="agent.id">{{ agent.name }}</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Labels</label>
                            <div class="flex flex-wrap gap-2">
                                <label v-for="label in labels" :key="label.id" class="flex items-center gap-1.5 rounded-full border px-3 py-1 text-sm cursor-pointer" :class="form.labels.includes(label.id) ? 'border-indigo-300 bg-indigo-50' : 'border-gray-200'">
                                    <input type="checkbox" :value="label.id" v-model="form.labels" class="hidden" />
                                    <span class="h-2 w-2 rounded-full" :style="{ backgroundColor: label.color }" />
                                    {{ label.name }}
                                </label>
                            </div>
                        </div>

                        <div class="flex justify-end gap-3 pt-4">
                            <Link :href="route('tickets.index')" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                Cancel
                            </Link>
                            <button type="submit" :disabled="form.processing" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                                Create Ticket
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
