<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    keys: Array,
    abilities: Object,
    plaintextKey: String,
});

const copied = ref(false);

const form = useForm({
    name: '',
    abilities: ['tickets:read'],
    expires_in_days: 90,
});

const EXPIRY_OPTIONS = [
    { value: 30, label: '30 days' },
    { value: 90, label: '90 days' },
    { value: 365, label: '1 year' },
    { value: null, label: 'Never' },
];

function submit() {
    form.post(route('api-keys.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

async function copyKey() {
    await navigator.clipboard.writeText(props.plaintextKey);
    copied.value = true;
    setTimeout(() => (copied.value = false), 2000);
}

function revoke(key) {
    if (confirm(`Revoke "${key.name}"? Any integration using it will stop working immediately.`)) {
        router.delete(route('api-keys.destroy', key.id), { preserveScroll: true });
    }
}

function formatDate(value) {
    return value ? new Date(value).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' }) : null;
}
</script>

<template>
    <Head title="API Keys" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">API Keys</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-4xl sm:px-6 lg:px-8 space-y-6">
                <!-- Shown exactly once, immediately after creation -->
                <div v-if="plaintextKey" class="rounded-md bg-green-50 border border-green-200 p-4">
                    <p class="text-sm font-medium text-green-900">Your new API key</p>
                    <p class="mt-1 text-sm text-green-800">Copy it now — it is stored hashed and cannot be shown again.</p>
                    <div class="mt-3 flex items-center gap-2">
                        <code class="flex-1 overflow-x-auto rounded border border-green-300 bg-white px-3 py-2 font-mono text-sm text-gray-900">{{ plaintextKey }}</code>
                        <button type="button" @click="copyKey" class="shrink-0 rounded-md bg-green-600 px-3 py-2 text-sm font-medium text-white hover:bg-green-700">
                            {{ copied ? 'Copied' : 'Copy' }}
                        </button>
                    </div>
                </div>

                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-1">Create a key</h3>
                    <p class="text-sm text-gray-500 mb-4">
                        Authenticate with <code class="font-mono text-xs">Authorization: Bearer &lt;key&gt;</code> against
                        <code class="font-mono text-xs">/api/v1/tickets</code>. Requests act as you, and see only this account's tickets.
                    </p>

                    <form @submit.prevent="submit" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Name</label>
                            <input v-model="form.name" type="text" required placeholder="e.g. Zapier integration" class="mt-1 w-full max-w-md rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                        </div>

                        <div>
                            <span class="block text-sm font-medium text-gray-700">Permissions</span>
                            <label v-for="(description, ability) in abilities" :key="ability" class="mt-2 flex items-start gap-2">
                                <input v-model="form.abilities" type="checkbox" :value="ability" class="mt-0.5 rounded text-indigo-600" />
                                <span class="text-sm">
                                    <code class="font-mono text-xs text-gray-900">{{ ability }}</code>
                                    <span class="ml-2 text-gray-500">{{ description }}</span>
                                </span>
                            </label>
                            <p v-if="form.errors.abilities" class="mt-1 text-sm text-red-600">{{ form.errors.abilities }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Expires</label>
                            <select v-model="form.expires_in_days" class="mt-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option v-for="option in EXPIRY_OPTIONS" :key="option.label" :value="option.value">{{ option.label }}</option>
                            </select>
                        </div>

                        <button type="submit" :disabled="form.processing" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                            Create Key
                        </button>
                    </form>
                </div>

                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="divide-y divide-gray-200">
                        <div v-for="key in keys" :key="key.id" class="flex items-center justify-between px-6 py-4">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="font-medium text-gray-900">{{ key.name }}</span>
                                    <span v-if="key.is_expired" class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700">Expired</span>
                                </div>
                                <div class="mt-1 flex flex-wrap gap-1">
                                    <code v-for="ability in key.abilities" :key="ability" class="rounded bg-gray-100 px-1.5 py-0.5 font-mono text-xs text-gray-700">{{ ability }}</code>
                                </div>
                                <div class="mt-1 text-sm text-gray-500">
                                    <span v-if="key.created_by">Created by {{ key.created_by }} &middot; </span>
                                    <span>{{ key.last_used_at ? `Last used ${formatDate(key.last_used_at)}` : 'Never used' }}</span>
                                    <span v-if="key.expires_at"> &middot; Expires {{ formatDate(key.expires_at) }}</span>
                                </div>
                            </div>
                            <button @click="revoke(key)" class="text-sm text-red-600 hover:text-red-800">Revoke</button>
                        </div>
                        <div v-if="!keys.length" class="px-6 py-8 text-center text-gray-500">
                            No API keys yet.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
