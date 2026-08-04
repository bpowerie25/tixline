<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    article: Object,
    categories: Array,
});

const isNew = computed(() => !props.article);

const form = useForm({
    title: props.article?.title || '',
    category_id: props.article?.category_id || (props.categories[0]?.id || ''),
    excerpt: props.article?.excerpt || '',
    body: props.article?.body || '',
    status: props.article?.status || 'draft',
});

function submit() {
    if (isNew.value) {
        form.post(route('kb.admin.store'));
    } else {
        form.put(route('kb.admin.update', props.article.id));
    }
}
</script>

<template>
    <Head :title="isNew ? 'New Article' : 'Edit Article'" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('kb.admin.index')" class="text-gray-400 hover:text-gray-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                </Link>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ isNew ? 'New Article' : 'Edit Article' }}</h2>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-4xl sm:px-6 lg:px-8">
                <form @submit.prevent="submit" class="space-y-6">
                    <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg p-6 space-y-4">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Title</label>
                                <input v-model="form.title" type="text" required class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Category</label>
                                    <select v-model="form.category_id" required class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                                        <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Status</label>
                                    <select v-model="form.status" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                                        <option value="draft">Draft</option>
                                        <option value="published">Published</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Excerpt</label>
                            <input v-model="form.excerpt" type="text" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Brief summary for search results" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Body</label>
                            <textarea v-model="form.body" rows="16" required class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono text-sm" />
                            <p class="mt-1 text-xs text-gray-400">HTML is supported.</p>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3">
                        <Link :href="route('kb.admin.index')" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</Link>
                        <button type="submit" :disabled="form.processing" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                            {{ isNew ? 'Create Article' : 'Update Article' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
