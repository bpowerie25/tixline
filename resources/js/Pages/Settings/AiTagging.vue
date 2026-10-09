<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';

const props = defineProps({
    config: Object,
    providers: Object,
    tags: Array,
});

// Config form
const configForm = useForm({
    is_enabled: props.config?.is_enabled ?? false,
    provider: props.config?.provider ?? 'gemini',
    model: props.config?.model ?? '',
    api_key: '',
    auto_apply: props.config?.auto_apply ?? false,
    confidence_threshold: props.config?.confidence_threshold ?? 0.7,
    flag_miscategorised: props.config?.flag_miscategorised ?? true,
    tag_on_create: props.config?.tag_on_create ?? true,
});

const availableModels = computed(() => {
    return props.providers[configForm.provider]?.models ?? [];
});

// Set first model when provider changes
watch(() => configForm.provider, () => {
    const models = availableModels.value;
    if (models.length && !models.includes(configForm.model)) {
        configForm.model = models[0];
    }
});

// Set initial model if empty
if (!configForm.model && availableModels.value.length) {
    configForm.model = availableModels.value[0];
}

function saveConfig() {
    configForm.post(route('ai-tagging.store'), {
        preserveScroll: true,
    });
}

// Tag form
const tagForm = useForm({
    name: '',
    color: '#6b7280',
    description: '',
});

const editingTag = ref(null);
const editForm = useForm({
    name: '',
    color: '',
    description: '',
});

function createTag() {
    tagForm.post(route('ai-tagging.tags.store'), {
        preserveScroll: true,
        onSuccess: () => tagForm.reset(),
    });
}

function startEdit(tag) {
    editingTag.value = tag.id;
    editForm.name = tag.name;
    editForm.color = tag.color;
    editForm.description = tag.description || '';
}

function saveEdit(tag) {
    editForm.put(route('ai-tagging.tags.update', tag.id), {
        preserveScroll: true,
        onSuccess: () => editingTag.value = null,
    });
}

function cancelEdit() {
    editingTag.value = null;
}

function deleteTag(tag) {
    if (confirm(`Delete tag "${tag.name}"? This will remove it from all tickets.`)) {
        router.delete(route('ai-tagging.tags.destroy', tag.id), {
            preserveScroll: true,
        });
    }
}
</script>

<template>
    <Head title="AI Tagging" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">AI Tagging</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-4xl sm:px-6 lg:px-8 space-y-6">
                <div v-if="$page.props.flash?.success" class="rounded-md bg-green-50 border border-green-200 p-4">
                    <p class="text-sm text-green-800">{{ $page.props.flash.success }}</p>
                </div>
                <div v-if="$page.props.flash?.error" class="rounded-md bg-red-50 border border-red-200 p-4">
                    <p class="text-sm text-red-800">{{ $page.props.flash.error }}</p>
                </div>

                <!-- Overview -->
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-2">About AI Tagging</h3>
                    <div class="prose prose-sm text-gray-600 max-w-none">
                        <p>AI tagging uses a language model to automatically categorise incoming tickets and flag potential miscategorisations. When enabled, the system will:</p>
                        <ul class="mt-2 space-y-1">
                            <li>Analyse each ticket's subject and body text to suggest matching tags from the list you define below.</li>
                            <li>Optionally flag tickets that appear to be assigned to the wrong team, so agents can review the assignment.</li>
                        </ul>
                    </div>
                    <div class="mt-4 rounded-md bg-blue-50 border border-blue-200 p-4">
                        <div class="flex gap-2">
                            <svg class="h-5 w-5 text-blue-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            <div class="text-sm text-blue-800">
                                <p class="font-medium">Data privacy</p>
                                <p class="mt-1">Ticket data is anonymised before being sent to the AI provider. Email addresses, phone numbers, and requester names are stripped and replaced with placeholders. Only the ticket subject and sanitised body text are sent. No attachments or internal notes are included. Long messages are truncated to 3,000 characters.</p>
                            </div>
                        </div>
                    </div>
                    <p class="mt-3 text-xs text-gray-500">This feature is entirely optional. The helpdesk works fully without it.</p>
                </div>

                <!-- AI Configuration -->
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">AI Provider Configuration</h3>
                    <form @submit.prevent="saveConfig" class="space-y-4">
                        <div class="flex items-center gap-3">
                            <label class="flex items-center gap-2">
                                <input type="checkbox" v-model="configForm.is_enabled" class="rounded text-indigo-600" />
                                <span class="text-sm font-medium text-gray-700">Enable AI tagging</span>
                            </label>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Provider</label>
                                <select v-model="configForm.provider" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                                    <option v-for="(info, key) in providers" :key="key" :value="key">{{ info.name }}</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Model</label>
                                <select v-model="configForm.model" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                                    <option v-for="m in availableModels" :key="m" :value="m">{{ m }}</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">API Key</label>
                            <input
                                type="password"
                                v-model="configForm.api_key"
                                :placeholder="config?.has_api_key ? '••••••••  (leave blank to keep current)' : 'Enter API key'"
                                class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm"
                            />
                            <p v-if="configForm.errors.api_key" class="mt-1 text-xs text-red-600">{{ configForm.errors.api_key }}</p>
                            <p class="mt-1 text-xs text-gray-500">Your API key is stored encrypted and never exposed in the interface. You can get a key from your chosen provider's developer console.</p>
                        </div>

                        <div class="border-t border-gray-200 pt-4 space-y-4">
                            <h4 class="text-sm font-medium text-gray-700">Behaviour</h4>

                            <div class="space-y-3">
                                <div>
                                    <label class="flex items-center gap-2">
                                        <input type="checkbox" v-model="configForm.tag_on_create" class="rounded text-indigo-600" />
                                        <span class="text-sm text-gray-700">Tag on ticket creation</span>
                                    </label>
                                    <p class="text-xs text-gray-500 ml-6 mt-1">Analyse tickets in real time as they arrive. If disabled, tickets are only tagged when you run the batch command (<code class="text-xs bg-gray-100 px-1 rounded">php artisan support:tag-tickets</code>).</p>
                                </div>

                                <div>
                                    <label class="flex items-center gap-2">
                                        <input type="checkbox" v-model="configForm.auto_apply" class="rounded text-indigo-600" />
                                        <span class="text-sm text-gray-700">Auto-apply tags</span>
                                    </label>
                                    <p v-if="!configForm.auto_apply" class="text-xs text-gray-500 ml-6 mt-1">
                                        Tags will appear as suggestions on each ticket. Agents can confirm or dismiss them.
                                    </p>
                                    <p v-else class="text-xs text-amber-600 ml-6 mt-1">
                                        Tags will be applied to tickets automatically without agent review.
                                    </p>
                                </div>

                                <div>
                                    <label class="flex items-center gap-2">
                                        <input type="checkbox" v-model="configForm.flag_miscategorised" class="rounded text-indigo-600" />
                                        <span class="text-sm text-gray-700">Flag miscategorised tickets</span>
                                    </label>
                                    <p class="text-xs text-gray-500 ml-6 mt-1">Show a warning banner on tickets where the AI believes the team assignment doesn't match the ticket content.</p>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">
                                    Confidence Threshold: {{ (configForm.confidence_threshold * 100).toFixed(0) }}%
                                </label>
                                <input
                                    type="range"
                                    v-model.number="configForm.confidence_threshold"
                                    min="0" max="1" step="0.05"
                                    class="mt-1 w-full"
                                />
                                <p class="text-xs text-gray-500 mt-1">Only tags where the AI is at least this confident will be suggested. Higher values mean fewer but more accurate suggestions.</p>
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <button
                                type="submit"
                                :disabled="configForm.processing"
                                class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                            >
                                Save Configuration
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Tag Management -->
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Tags</h3>
                    <p class="text-sm text-gray-500 mb-4">Define the tags that AI can assign to tickets. The AI will only suggest tags from this list.</p>

                    <!-- Add Tag Form -->
                    <form @submit.prevent="createTag" class="flex items-end gap-3 mb-6">
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-gray-700">Name</label>
                            <input v-model="tagForm.name" type="text" placeholder="e.g. Billing Issue" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm" />
                            <p v-if="tagForm.errors.name" class="mt-1 text-xs text-red-600">{{ tagForm.errors.name }}</p>
                        </div>
                        <div class="w-20">
                            <label class="block text-sm font-medium text-gray-700">Colour</label>
                            <input v-model="tagForm.color" type="color" class="mt-1 h-9 w-full rounded-md border-gray-300 shadow-sm cursor-pointer" />
                        </div>
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-gray-700">Description <span class="text-gray-400">(optional)</span></label>
                            <input v-model="tagForm.description" type="text" placeholder="Helps AI understand when to use this tag" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm" />
                        </div>
                        <button
                            type="submit"
                            :disabled="tagForm.processing || !tagForm.name"
                            class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50 whitespace-nowrap"
                        >
                            Add Tag
                        </button>
                    </form>

                    <!-- Tag List -->
                    <div v-if="tags.length" class="divide-y divide-gray-100">
                        <div v-for="tag in tags" :key="tag.id" class="flex items-center gap-3 py-3">
                            <template v-if="editingTag === tag.id">
                                <input v-model="editForm.name" type="text" class="flex-1 rounded-md border-gray-300 text-sm shadow-sm" />
                                <input v-model="editForm.color" type="color" class="h-8 w-12 rounded border-gray-300 shadow-sm cursor-pointer" />
                                <input v-model="editForm.description" type="text" placeholder="Description" class="flex-1 rounded-md border-gray-300 text-sm shadow-sm" />
                                <button @click="saveEdit(tag)" :disabled="editForm.processing" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">Save</button>
                                <button @click="cancelEdit" class="text-sm text-gray-500 hover:text-gray-700">Cancel</button>
                            </template>
                            <template v-else>
                                <span class="h-3 w-3 rounded-full shrink-0" :style="{ backgroundColor: tag.color }" />
                                <span class="font-medium text-sm text-gray-900">{{ tag.name }}</span>
                                <span v-if="tag.description" class="text-xs text-gray-500 truncate flex-1">{{ tag.description }}</span>
                                <span v-else class="flex-1" />
                                <button @click="startEdit(tag)" class="text-sm text-gray-500 hover:text-gray-700">Edit</button>
                                <button @click="deleteTag(tag)" class="text-sm text-red-500 hover:text-red-700">Delete</button>
                            </template>
                        </div>
                    </div>
                    <p v-else class="text-sm text-gray-400 text-center py-6">No tags defined yet. Add tags above for the AI to use.</p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
