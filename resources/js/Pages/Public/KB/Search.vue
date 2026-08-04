<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    query: String,
    articles: Array,
});

const searchQuery = ref(props.query);

function search() {
    router.get(route('kb.search'), { q: searchQuery.value });
}
</script>

<template>
    <Head :title="'Search: ' + query + ' - Knowledge Base'" />

    <div class="min-h-screen bg-gray-100">
        <div class="bg-indigo-600 py-8">
            <div class="mx-auto max-w-3xl px-4">
                <Link :href="route('kb.portal')" class="text-indigo-200 hover:text-white text-sm">&larr; Back to Knowledge Base</Link>
                <form @submit.prevent="search" class="mt-4">
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search articles..."
                        class="w-full rounded-lg border-0 px-6 py-3 text-lg shadow-lg focus:ring-2 focus:ring-indigo-300"
                    />
                </form>
            </div>
        </div>

        <div class="mx-auto max-w-3xl px-4 py-8">
            <p class="mb-4 text-sm text-gray-500">{{ articles.length }} results for "{{ query }}"</p>
            <div class="space-y-3">
                <Link
                    v-for="article in articles"
                    :key="article.id"
                    :href="route('kb.article', [article.category.slug, article.slug])"
                    class="block rounded-lg bg-white p-5 shadow-sm hover:shadow-md transition-shadow"
                >
                    <div class="text-xs text-indigo-600 mb-1">{{ article.category.name }}</div>
                    <h3 class="font-medium text-gray-900">{{ article.title }}</h3>
                    <p v-if="article.excerpt" class="mt-1 text-sm text-gray-500">{{ article.excerpt }}</p>
                </Link>
            </div>

            <div v-if="!articles.length" class="text-center py-16 text-gray-500">
                No articles found matching your search.
            </div>
        </div>
    </div>
</template>
