<script setup>
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    category: Object,
});
</script>

<template>
    <Head :title="category.name + ' - Knowledge Base'" />

    <div class="min-h-screen bg-gray-100">
        <div class="bg-indigo-600 py-8">
            <div class="mx-auto max-w-3xl px-4">
                <Link :href="route('kb.portal')" class="text-indigo-200 hover:text-white text-sm">&larr; Back to Knowledge Base</Link>
                <h1 class="mt-2 text-2xl font-bold text-white">{{ category.name }}</h1>
                <p v-if="category.description" class="mt-1 text-indigo-200">{{ category.description }}</p>
            </div>
        </div>

        <div class="mx-auto max-w-3xl px-4 py-8">
            <div class="space-y-3">
                <Link
                    v-for="article in category.published_articles"
                    :key="article.id"
                    :href="route('kb.article', [category.slug, article.slug])"
                    class="block rounded-lg bg-white p-5 shadow-sm hover:shadow-md transition-shadow"
                >
                    <h3 class="font-medium text-gray-900">{{ article.title }}</h3>
                    <p v-if="article.excerpt" class="mt-1 text-sm text-gray-500">{{ article.excerpt }}</p>
                </Link>
            </div>

            <div v-if="!category.published_articles?.length" class="text-center py-16 text-gray-500">
                No articles in this category yet.
            </div>
        </div>
    </div>
</template>
