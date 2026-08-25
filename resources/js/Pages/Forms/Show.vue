<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    form: Object,
    teams: Array,
});

const isNew = computed(() => !props.form?.id);

const formData = useForm({
    name: props.form?.name || '',
    description: props.form?.description || '',
    is_active: props.form?.is_active ?? true,
    team_id: props.form?.team_id || null,
    fields: props.form?.fields ? JSON.parse(JSON.stringify(props.form.fields)) : [],
});

const fieldTypes = [
    { value: 'text', label: 'Text' },
    { value: 'textarea', label: 'Textarea' },
    { value: 'select', label: 'Dropdown' },
    { value: 'checkbox', label: 'Checkbox' },
    { value: 'radio', label: 'Radio' },
    { value: 'email', label: 'Email' },
    { value: 'number', label: 'Number' },
    { value: 'date', label: 'Date' },
    { value: 'file', label: 'File Upload' },
];

function addField() {
    formData.fields.push({
        id: null,
        name: '',
        label: '',
        description: '',
        type: 'text',
        options: [],
        is_required: false,
        sort_order: formData.fields.length,
        conditions: null,
    });
}

function removeField(index) {
    formData.fields.splice(index, 1);
    formData.fields.forEach((f, i) => f.sort_order = i);
}

function moveField(index, direction) {
    const newIndex = index + direction;
    if (newIndex < 0 || newIndex >= formData.fields.length) return;
    const temp = formData.fields[index];
    formData.fields[index] = formData.fields[newIndex];
    formData.fields[newIndex] = temp;
    formData.fields.forEach((f, i) => f.sort_order = i);
}

function addOption(field) {
    if (!field.options) field.options = [];
    field.options.push('');
}

function removeOption(field, index) {
    field.options.splice(index, 1);
}

function toggleConditions(field) {
    if (field.conditions) {
        field.conditions = null;
    } else {
        field.conditions = { field: '', operator: 'equals', value: '' };
    }
}

function autoName(field) {
    if (!field.name || field.name === '') {
        field.name = field.label.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '');
    }
}

function submit() {
    if (isNew.value) {
        formData.post(route('forms.store'));
    } else {
        formData.put(route('forms.update', props.form.id));
    }
}
</script>

<template>
    <Head :title="isNew ? 'New Form' : 'Edit Form'" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('forms.index')" class="text-gray-400 hover:text-gray-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                </Link>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ isNew ? 'New Form' : 'Edit Form' }}</h2>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-4xl sm:px-6 lg:px-8">
                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Form Details -->
                    <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg p-6">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Form Name</label>
                                <input v-model="formData.name" type="text" required class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            </div>
                            <div class="flex items-end">
                                <label class="flex items-center gap-2">
                                    <input v-model="formData.is_active" type="checkbox" class="rounded text-indigo-600" />
                                    <span class="text-sm font-medium text-gray-700">Active</span>
                                </label>
                            </div>
                        </div>
                        <div class="mt-4">
                            <label class="block text-sm font-medium text-gray-700">Description</label>
                            <textarea v-model="formData.description" rows="2" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        </div>
                        <div class="mt-4">
                            <label class="block text-sm font-medium text-gray-700">Auto-assign to Team</label>
                            <select v-model="formData.team_id" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:w-1/2">
                                <option :value="null">None (use workflow rules)</option>
                                <option v-for="team in teams" :key="team.id" :value="team.id">{{ team.name }}</option>
                            </select>
                            <p class="mt-1 text-xs text-gray-500">If set, tickets submitted via this form will be automatically assigned to this team.</p>
                        </div>
                    </div>

                    <!-- Fields -->
                    <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-medium text-gray-900">Fields</h3>
                            <button type="button" @click="addField" class="text-sm text-indigo-600 hover:text-indigo-800">+ Add Field</button>
                        </div>

                        <div class="space-y-4">
                            <div v-for="(field, index) in formData.fields" :key="index" class="rounded-lg border border-gray-200 p-4">
                                <div class="flex items-start justify-between mb-3">
                                    <span class="text-sm font-medium text-gray-500">Field {{ index + 1 }}</span>
                                    <div class="flex gap-1">
                                        <button type="button" @click="moveField(index, -1)" :disabled="index === 0" class="text-gray-400 hover:text-gray-600 disabled:opacity-30">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" /></svg>
                                        </button>
                                        <button type="button" @click="moveField(index, 1)" :disabled="index === formData.fields.length - 1" class="text-gray-400 hover:text-gray-600 disabled:opacity-30">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                        </button>
                                        <button type="button" @click="removeField(index)" class="text-red-500 hover:text-red-700 ml-2">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                        </button>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500">Label</label>
                                        <input v-model="field.label" @blur="autoName(field)" type="text" required class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500">Field Name</label>
                                        <input v-model="field.name" type="text" required class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500">Type</label>
                                        <select v-model="field.type" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                                            <option v-for="ft in fieldTypes" :key="ft.value" :value="ft.value">{{ ft.label }}</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="mt-3">
                                    <label class="block text-xs font-medium text-gray-500">Help Text <span class="font-normal text-gray-400">(optional — shown below the field)</span></label>
                                    <input v-model="field.description" type="text" placeholder="e.g. Please select the module you need help with" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                </div>

                                <!-- Options for select/radio -->
                                <div v-if="['select', 'radio', 'checkbox'].includes(field.type)" class="mt-3">
                                    <label class="block text-xs font-medium text-gray-500 mb-1">Options</label>
                                    <div class="space-y-1">
                                        <div v-for="(opt, oi) in field.options" :key="oi" class="flex gap-2">
                                            <input v-model="field.options[oi]" type="text" placeholder="Option value" class="flex-1 rounded-md border-gray-300 text-sm shadow-sm" />
                                            <button type="button" @click="removeOption(field, oi)" class="text-red-400 hover:text-red-600 text-sm">Remove</button>
                                        </div>
                                    </div>
                                    <button type="button" @click="addOption(field)" class="mt-1 text-xs text-indigo-600 hover:text-indigo-800">+ Add option</button>
                                </div>

                                <div class="mt-3 flex items-center gap-4">
                                    <label class="flex items-center gap-1.5">
                                        <input v-model="field.is_required" type="checkbox" class="rounded text-indigo-600" />
                                        <span class="text-sm text-gray-600">Required</span>
                                    </label>
                                    <button type="button" @click="toggleConditions(field)" class="text-sm" :class="field.conditions ? 'text-yellow-600' : 'text-gray-400 hover:text-gray-600'">
                                        {{ field.conditions ? 'Remove conditions' : '+ Add visibility condition' }}
                                    </button>
                                </div>

                                <!-- Conditional visibility -->
                                <div v-if="field.conditions" class="mt-3 rounded bg-yellow-50 border border-yellow-200 p-3">
                                    <p class="text-xs font-medium text-yellow-700 mb-2">Show this field only when:</p>
                                    <div class="flex gap-2">
                                        <select v-model="field.conditions.field" class="rounded-md border-gray-300 text-sm shadow-sm">
                                            <option value="">Select field...</option>
                                            <option v-for="(f, fi) in formData.fields" :key="fi" :value="f.name" v-show="fi !== index">
                                                {{ f.label || f.name }}
                                            </option>
                                        </select>
                                        <select v-model="field.conditions.operator" class="rounded-md border-gray-300 text-sm shadow-sm">
                                            <option value="equals">Equals</option>
                                            <option value="not_equals">Not equals</option>
                                            <option value="contains">Contains</option>
                                            <option value="is_not_empty">Is filled</option>
                                        </select>
                                        <input v-if="field.conditions.operator !== 'is_not_empty'" v-model="field.conditions.value" type="text" placeholder="Value" class="flex-1 rounded-md border-gray-300 text-sm shadow-sm" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-if="!formData.fields.length" class="text-center py-8 text-gray-500">
                            No fields yet. Click "Add Field" to start building your form.
                        </div>
                    </div>

                    <div class="flex justify-end gap-3">
                        <Link :href="route('forms.index')" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            Cancel
                        </Link>
                        <button type="submit" :disabled="formData.processing" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                            {{ isNew ? 'Create Form' : 'Update Form' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
