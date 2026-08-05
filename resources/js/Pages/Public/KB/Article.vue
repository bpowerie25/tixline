<script setup>
import { Head, Link } from '@inertiajs/vue3';
import SandboxedHtml from '@/Components/SandboxedHtml.vue';

const props = defineProps({
    category: Object,
    article: Object,
});
</script>

<template>
    <Head :title="article.title + ' - Knowledge Base'" />

    <div class="min-h-screen bg-gray-100">
        <div class="bg-indigo-600 py-8">
            <div class="mx-auto max-w-3xl px-4">
                <div class="flex gap-2 text-sm text-indigo-200">
                    <Link :href="route('kb.portal')" class="hover:text-white">Knowledge Base</Link>
                    <span>/</span>
                    <Link :href="route('kb.category', category.slug)" class="hover:text-white">{{ category.name }}</Link>
                </div>
                <h1 class="mt-2 text-2xl font-bold text-white">{{ article.title }}</h1>
                <p v-if="article.author" class="mt-1 text-sm text-indigo-200">
                    By {{ article.author.name }} &middot; {{ new Date(article.published_at || article.created_at).toLocaleDateString() }}
                </p>
            </div>
        </div>

        <div class="mx-auto max-w-3xl px-4 py-8">
            <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg p-8">
                <div class="prose prose-indigo max-w-none">
                    <SandboxedHtml :html="article.body" />
                </div>
            </div>
        </div>
    </div>
</template>
