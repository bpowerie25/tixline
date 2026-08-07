<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { ref, computed } from 'vue';

const props = defineProps({
    query: String,
    articles: Array,
});

const tenant = computed(() => usePage().props.tenant);
const primaryColor = computed(() => tenant.value?.primary_color || '#be123c');
const primaryColorLight = computed(() => (tenant.value?.primary_color || '#be123c') + '15');

const searchQuery = ref(props.query);

function search() {
    router.get(route('kb.search'), { q: searchQuery.value });
}
</script>

<template>
    <Head :title="'Search: ' + query + ' - Knowledge Base'" />

    <PublicLayout>
        <section :style="{ backgroundColor: primaryColorLight }" class="py-8 px-4">
            <div class="mx-auto max-w-3xl">
                <Link :href="route('kb.portal')" :style="{ color: primaryColor }" class="text-sm opacity-80 hover:opacity-100">&larr; Back to Knowledge Base</Link>
                <form @submit.prevent="search" class="mt-4">
                    <input v-model="searchQuery" type="text" placeholder="Search articles..." class="w-full rounded-lg border border-gray-300 bg-white px-6 py-3 text-lg shadow-sm focus:ring-2 focus:ring-gray-300 focus:border-gray-400" />
                </form>
            </div>
        </section>

        <div class="mx-auto max-w-3xl px-4 py-8">
            <p class="mb-4 text-sm text-gray-500">{{ articles.length }} results for "{{ query }}"</p>
            <div class="space-y-3">
                <Link
                    v-for="article in articles"
                    :key="article.id"
                    :href="route('kb.article', [article.category.slug, article.slug])"
                    class="block rounded-lg bg-white p-5 shadow-sm hover:shadow-md transition-shadow"
                >
                    <div :style="{ color: primaryColor }" class="text-xs mb-1">{{ article.category.name }}</div>
                    <h3 class="font-medium text-gray-900">{{ article.title }}</h3>
                    <p v-if="article.excerpt" class="mt-1 text-sm text-gray-500">{{ article.excerpt }}</p>
                </Link>
            </div>

            <div v-if="!articles.length" class="text-center py-16 text-gray-500">
                No articles found matching your search.
            </div>
        </div>
    </PublicLayout>
</template>
