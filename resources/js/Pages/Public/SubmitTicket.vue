<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

const props = defineProps({
    form: Object,
    forms: Array,
});

const ticketForm = useForm({
    subject: '',
    body: '',
    requester_name: '',
    requester_email: '',
    form_id: props.form?.id || null,
    custom_fields: {},
});

function shouldShowField(field) {
    if (!field.conditions) return true;
    const { field: depField, operator, value } = field.conditions;
    if (!depField) return true;
    const depValue = ticketForm.custom_fields[depField] || '';
    switch (operator) {
        case 'equals': return depValue === value;
        case 'not_equals': return depValue !== value;
        case 'contains': return depValue.includes(value);
        case 'is_not_empty': return depValue !== '' && depValue !== false && depValue !== null;
        default: return true;
    }
}

function submit() {
    ticketForm.post(route('submit.store'));
}
</script>

<template>
    <Head title="Submit a Request" />

    <div class="min-h-screen bg-gray-100">
        <div class="bg-indigo-600 py-8">
            <div class="mx-auto max-w-2xl px-4">
                <h1 class="text-2xl font-bold text-white">Submit a Support Request</h1>
                <p class="mt-1 text-indigo-200">We'll get back to you as soon as possible.</p>
            </div>
        </div>

        <div class="mx-auto max-w-2xl px-4 py-8">
            <!-- Form selector if multiple forms -->
            <div v-if="forms.length > 1" class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">Request Type</label>
                <div class="flex flex-wrap gap-2">
                    <a
                        v-for="f in forms"
                        :key="f.id"
                        :href="route('submit.create', { form: f.slug })"
                        :class="form?.id === f.id ? 'border-indigo-500 bg-indigo-50 text-indigo-700' : 'border-gray-300 hover:border-gray-400'"
                        class="rounded-lg border px-4 py-2 text-sm font-medium"
                    >
                        {{ f.name }}
                    </a>
                </div>
            </div>

            <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg p-6">
                <form @submit.prevent="submit" class="space-y-4">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Your Name <span class="text-red-500">*</span></label>
                            <input v-model="ticketForm.requester_name" type="text" required class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            <p v-if="ticketForm.errors.requester_name" class="mt-1 text-sm text-red-600">{{ ticketForm.errors.requester_name }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Your Email <span class="text-red-500">*</span></label>
                            <input v-model="ticketForm.requester_email" type="email" required class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            <p v-if="ticketForm.errors.requester_email" class="mt-1 text-sm text-red-600">{{ ticketForm.errors.requester_email }}</p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Subject <span class="text-red-500">*</span></label>
                        <input v-model="ticketForm.subject" type="text" required class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        <p v-if="ticketForm.errors.subject" class="mt-1 text-sm text-red-600">{{ ticketForm.errors.subject }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Description</label>
                        <textarea v-model="ticketForm.body" rows="5" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                    </div>

                    <!-- Custom Form Fields with Conditional Visibility -->
                    <template v-if="form?.fields">
                        <div v-for="field in form.fields" :key="field.id" v-show="shouldShowField(field)">
                            <label class="block text-sm font-medium text-gray-700">
                                {{ field.label }}
                                <span v-if="field.is_required" class="text-red-500">*</span>
                            </label>

                            <input
                                v-if="['text', 'email', 'number', 'date'].includes(field.type)"
                                v-model="ticketForm.custom_fields[field.name]"
                                :type="field.type"
                                :required="field.is_required && shouldShowField(field)"
                                class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />

                            <textarea
                                v-else-if="field.type === 'textarea'"
                                v-model="ticketForm.custom_fields[field.name]"
                                rows="3"
                                :required="field.is_required && shouldShowField(field)"
                                class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />

                            <select
                                v-else-if="field.type === 'select'"
                                v-model="ticketForm.custom_fields[field.name]"
                                :required="field.is_required && shouldShowField(field)"
                                class="mt-1 w-full rounded-md border-gray-300 shadow-sm"
                            >
                                <option value="">Select...</option>
                                <option v-for="opt in field.options" :key="opt" :value="opt">{{ opt }}</option>
                            </select>

                            <div v-else-if="field.type === 'radio'" class="mt-1 space-y-1">
                                <label v-for="opt in field.options" :key="opt" class="flex items-center gap-2">
                                    <input v-model="ticketForm.custom_fields[field.name]" type="radio" :value="opt" class="text-indigo-600" />
                                    <span class="text-sm text-gray-700">{{ opt }}</span>
                                </label>
                            </div>

                            <div v-else-if="field.type === 'checkbox'" class="mt-1">
                                <label class="flex items-center gap-2">
                                    <input v-model="ticketForm.custom_fields[field.name]" type="checkbox" class="rounded text-indigo-600" />
                                    <span class="text-sm text-gray-700">{{ field.label }}</span>
                                </label>
                            </div>
                        </div>
                    </template>

                    <div class="pt-4">
                        <button type="submit" :disabled="ticketForm.processing" class="w-full rounded-md bg-indigo-600 px-4 py-3 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                            Submit Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
