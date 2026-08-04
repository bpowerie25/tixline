<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    categories: Array,
});

const searchQuery = ref('');

function search() {
    if (searchQuery.value.trim()) {
        router.get(route('kb.search'), { q: searchQuery.value });
    }
}
</script>

<template>
    <Head title="Knowledge Base" />

    <div class="min-h-screen bg-gray-100">
        <div class="bg-indigo-600 py-12">
            <div class="mx-auto max-w-3xl px-4 text-center">
                <h1 class="text-3xl font-bold text-white">How can we help?</h1>
                <form @submit.prevent="search" class="mt-6">
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search articles..."
                        class="w-full rounded-lg border-0 px-6 py-3 text-lg shadow-lg focus:ring-2 focus:ring-indigo-300"
                    />
                </form>
            </div>
        </div>

        <div class="mx-auto max-w-5xl px-4 py-12">
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <Link
                    v-for="category in categories"
                    :key="category.id"
                    :href="route('kb.category', category.slug)"
                    class="rounded-lg bg-white p-6 shadow-sm hover:shadow-md transition-shadow"
                >
                    <h3 class="text-lg font-semibold text-gray-900">{{ category.name }}</h3>
                    <p v-if="category.description" class="mt-2 text-sm text-gray-500">{{ category.description }}</p>
                    <p class="mt-3 text-sm text-indigo-600">{{ category.published_articles_count }} articles</p>
                </Link>
            </div>

            <div v-if="!categories.length" class="text-center py-16 text-gray-500">
                No knowledge base articles yet.
            </div>
        </div>
    </div>
</template>
