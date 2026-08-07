<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    entries: Array,
});

const blocklist = computed(() => props.entries.filter(e => e.type === 'blocklist'));
const allowlist = computed(() => props.entries.filter(e => e.type === 'allowlist'));

const form = useForm({
    type: 'blocklist',
    value: '',
    reason: '',
});

function submit() {
    form.post(route('spam-filters.store'), {
        onSuccess: () => form.reset('value', 'reason'),
    });
}

function remove(id) {
    if (confirm('Remove this entry?')) {
        router.delete(route('spam-filters.destroy', id));
    }
}
</script>

<template>
    <Head title="Spam Filters" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Spam Filters</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-4xl sm:px-6 lg:px-8 space-y-6">
                <!-- Flash messages -->
                <div v-if="$page.props.flash?.success" class="rounded-md bg-green-50 border border-green-200 p-4">
                    <p class="text-sm text-green-800">{{ $page.props.flash.success }}</p>
                </div>

                <!-- Add Entry -->
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Add Entry</h3>
                    <form @submit.prevent="submit" class="flex flex-wrap items-end gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Type</label>
                            <select v-model="form.type" class="mt-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="blocklist">Blocklist</option>
                                <option value="allowlist">Allowlist</option>
                            </select>
                        </div>
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-gray-700">Email or Domain</label>
                            <input v-model="form.value" type="text" required placeholder="spammer@example.com or spamdomain.com" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            <p v-if="form.errors.value" class="mt-1 text-sm text-red-600">{{ form.errors.value }}</p>
                        </div>
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-gray-700">Reason (optional)</label>
                            <input v-model="form.reason" type="text" placeholder="Why blocked/allowed" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        </div>
                        <button type="submit" :disabled="form.processing" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                            Add
                        </button>
                    </form>
                </div>

                <!-- Blocklist -->
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-1">Blocklist</h3>
                    <p class="text-sm text-gray-500 mb-4">Emails from these addresses or domains will be rejected.</p>

                    <div class="divide-y divide-gray-200">
                        <div v-for="entry in blocklist" :key="entry.id" class="flex items-center justify-between py-3">
                            <div>
                                <span class="font-mono text-sm text-gray-900">{{ entry.value }}</span>
                                <span v-if="entry.reason" class="ml-2 text-sm text-gray-400">&mdash; {{ entry.reason }}</span>
                            </div>
                            <button @click="remove(entry.id)" class="text-sm text-red-600 hover:text-red-800">Remove</button>
                        </div>
                        <div v-if="!blocklist.length" class="py-4 text-center text-gray-400 text-sm">No blocklist entries.</div>
                    </div>
                </div>

                <!-- Allowlist -->
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-1">Allowlist</h3>
                    <p class="text-sm text-gray-500 mb-4">If any allowlist entries exist, <strong>only</strong> emails from these addresses or domains will be accepted. Leave empty to accept from anyone.</p>

                    <div class="divide-y divide-gray-200">
                        <div v-for="entry in allowlist" :key="entry.id" class="flex items-center justify-between py-3">
                            <div>
                                <span class="font-mono text-sm text-gray-900">{{ entry.value }}</span>
                                <span v-if="entry.reason" class="ml-2 text-sm text-gray-400">&mdash; {{ entry.reason }}</span>
                            </div>
                            <button @click="remove(entry.id)" class="text-sm text-red-600 hover:text-red-800">Remove</button>
                        </div>
                        <div v-if="!allowlist.length" class="py-4 text-center text-gray-400 text-sm">No allowlist entries. All senders accepted (subject to blocklist).</div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
