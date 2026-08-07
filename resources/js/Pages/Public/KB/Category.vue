<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { computed } from 'vue';

const props = defineProps({
    category: Object,
});

const tenant = computed(() => usePage().props.tenant);
const primaryColor = computed(() => tenant.value?.primary_color || '#be123c');
const primaryColorLight = computed(() => (tenant.value?.primary_color || '#be123c') + '15');
</script>

<template>
    <Head :title="category.name + ' - Knowledge Base'" />

    <PublicLayout>
        <section :style="{ backgroundColor: primaryColorLight }" class="py-8 px-4">
            <div class="mx-auto max-w-3xl">
                <Link :href="route('kb.portal')" :style="{ color: primaryColor }" class="text-sm opacity-80 hover:opacity-100">&larr; Back to Knowledge Base</Link>
                <h1 :style="{ color: primaryColor }" class="mt-2 text-2xl font-bold">{{ category.name }}</h1>
                <p v-if="category.description" class="mt-1 text-gray-600">{{ category.description }}</p>
            </div>
        </section>

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
    </PublicLayout>
</template>
