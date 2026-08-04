<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    articles: Array,
    categories: Array,
});

const showCatForm = ref(false);
const catForm = useForm({ name: '', description: '', icon: '' });

function createCategory() {
    catForm.post(route('kb.admin.categories.store'), {
        onSuccess: () => { showCatForm.value = false; catForm.reset(); },
    });
}

function deleteArticle(article) {
    if (confirm(`Delete "${article.title}"?`)) {
        router.delete(route('kb.admin.destroy', article.id));
    }
}

const statusColors = {
    draft: 'bg-gray-100 text-gray-600',
    published: 'bg-green-100 text-green-700',
};
</script>

<template>
    <Head title="Knowledge Base" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Knowledge Base</h2>
                <div class="flex gap-2">
                    <button @click="showCatForm = !showCatForm" class="inline-flex items-center rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        New Category
                    </button>
                    <Link :href="route('kb.admin.create')" class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                        New Article
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
                <!-- Category form -->
                <div v-if="showCatForm" class="mb-6 overflow-hidden bg-white shadow-sm sm:rounded-lg p-6">
                    <form @submit.prevent="createCategory" class="flex items-end gap-4">
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-gray-700">Category Name</label>
                            <input v-model="catForm.name" type="text" required class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        </div>
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-gray-700">Description</label>
                            <input v-model="catForm.description" type="text" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        </div>
                        <button type="submit" :disabled="catForm.processing" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Create</button>
                        <button type="button" @click="showCatForm = false" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                    </form>
                </div>

                <!-- Articles list -->
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="divide-y divide-gray-200">
                        <Link
                            v-for="article in articles"
                            :key="article.id"
                            :href="route('kb.admin.show', article.id)"
                            class="flex items-center justify-between px-6 py-4 hover:bg-gray-50"
                        >
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium text-gray-900">{{ article.title }}</span>
                                    <span :class="[statusColors[article.status], 'inline-flex rounded-full px-2 py-0.5 text-xs font-medium']">{{ article.status }}</span>
                                </div>
                                <div class="mt-1 text-sm text-gray-500">
                                    {{ article.category?.name }} &middot; {{ article.views }} views
                                    <span v-if="article.author"> &middot; by {{ article.author.name }}</span>
                                </div>
                            </div>
                            <button @click.prevent="deleteArticle(article)" class="text-sm text-red-600 hover:text-red-800">Delete</button>
                        </Link>
                        <div v-if="!articles.length" class="px-6 py-8 text-center text-gray-500">No articles yet.</div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
