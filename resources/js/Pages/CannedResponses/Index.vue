<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    cannedResponses: Array,
});

const showForm = ref(false);
const editing = ref(null);

const form = useForm({
    name: '',
    shortcode: '',
    body: '',
    is_shared: true,
});

function openCreate() {
    editing.value = null;
    form.reset();
    showForm.value = true;
}

function openEdit(response) {
    editing.value = response;
    form.name = response.name;
    form.shortcode = response.shortcode;
    form.body = response.body;
    form.is_shared = response.is_shared;
    showForm.value = true;
}

function submit() {
    if (editing.value) {
        form.put(route('canned-responses.update', editing.value.id), {
            onSuccess: () => { showForm.value = false; },
        });
    } else {
        form.post(route('canned-responses.store'), {
            onSuccess: () => { showForm.value = false; form.reset(); },
        });
    }
}

function deleteResponse(response) {
    if (confirm(`Delete "${response.name}"?`)) {
        router.delete(route('canned-responses.destroy', response.id));
    }
}

const variables = [
    { var: '{{requester_name}}', desc: 'Customer name' },
    { var: '{{requester_email}}', desc: 'Customer email' },
    { var: '{{ticket_reference}}', desc: 'Ticket reference (e.g. TKT-000001)' },
    { var: '{{ticket_subject}}', desc: 'Ticket subject' },
    { var: '{{agent_name}}', desc: 'Assigned agent name' },
];
</script>

<template>
    <Head title="Canned Responses" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Canned Responses</h2>
                <button @click="openCreate" class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    New Response
                </button>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-4xl sm:px-6 lg:px-8">
                <div v-if="showForm" class="mb-6 overflow-hidden bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium mb-4">{{ editing ? 'Edit' : 'Create' }} Canned Response</h3>
                    <form @submit.prevent="submit" class="space-y-4">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Name</label>
                                <input v-model="form.name" type="text" required class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="e.g. Welcome Reply" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Shortcode</label>
                                <div class="mt-1 flex rounded-md shadow-sm">
                                    <span class="inline-flex items-center rounded-l-md border border-r-0 border-gray-300 bg-gray-50 px-3 text-sm text-gray-500">#</span>
                                    <input v-model="form.shortcode" type="text" required class="flex-1 rounded-none rounded-r-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" placeholder="welcome" />
                                </div>
                                <p v-if="form.errors.shortcode" class="mt-1 text-sm text-red-600">{{ form.errors.shortcode }}</p>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Body</label>
                            <textarea v-model="form.body" rows="6" required class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Hi {{requester_name}},&#10;&#10;Thanks for reaching out..." />
                            <div class="mt-2 flex flex-wrap gap-1">
                                <button v-for="v in variables" :key="v.var" type="button" @click="form.body += v.var" class="rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-600 hover:bg-gray-200" :title="v.desc">
                                    {{ v.var }}
                                </button>
                            </div>
                        </div>
                        <div>
                            <label class="flex items-center gap-2">
                                <input v-model="form.is_shared" type="checkbox" class="rounded text-indigo-600" />
                                <span class="text-sm text-gray-700">Shared with all agents</span>
                            </label>
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
                        <div v-for="response in cannedResponses" :key="response.id" class="px-6 py-4">
                            <div class="flex items-start justify-between">
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <span class="font-medium text-gray-900">{{ response.name }}</span>
                                        <code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-600">#{{ response.shortcode }}</code>
                                        <span v-if="!response.is_shared" class="rounded-full bg-yellow-100 px-2 py-0.5 text-xs text-yellow-700">Personal</span>
                                    </div>
                                    <p class="mt-1 text-sm text-gray-500 line-clamp-2">{{ response.body }}</p>
                                    <p v-if="response.user" class="mt-1 text-xs text-gray-400">Created by {{ response.user.name }}</p>
                                </div>
                                <div class="ml-4 flex gap-2">
                                    <button @click="openEdit(response)" class="text-sm text-indigo-600 hover:text-indigo-800">Edit</button>
                                    <button @click="deleteResponse(response)" class="text-sm text-red-600 hover:text-red-800">Delete</button>
                                </div>
                            </div>
                        </div>
                        <div v-if="!cannedResponses.length" class="px-6 py-8 text-center text-gray-500">No canned responses yet.</div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
