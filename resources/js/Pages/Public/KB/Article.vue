<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import SandboxedHtml from '@/Components/SandboxedHtml.vue';
import { computed } from 'vue';

const props = defineProps({
    category: Object,
    article: Object,
});

const tenant = computed(() => usePage().props.tenant);
const primaryColor = computed(() => tenant.value?.primary_color || '#be123c');
const primaryColorLight = computed(() => (tenant.value?.primary_color || '#be123c') + '15');
</script>

<template>
    <Head :title="article.title + ' - Knowledge Base'" />

    <PublicLayout>
        <section :style="{ backgroundColor: primaryColorLight }" class="py-8 px-4">
            <div class="mx-auto max-w-3xl">
                <div class="flex gap-2 text-sm" :style="{ color: primaryColor }">
                    <Link :href="route('kb.portal')" class="opacity-80 hover:opacity-100">Knowledge Base</Link>
                    <span class="opacity-50">/</span>
                    <Link :href="route('kb.category', category.slug)" class="opacity-80 hover:opacity-100">{{ category.name }}</Link>
                </div>
                <h1 :style="{ color: primaryColor }" class="mt-2 text-2xl font-bold">{{ article.title }}</h1>
                <p v-if="article.author" class="mt-1 text-sm text-gray-500">
                    By {{ article.author.name }} &middot; {{ new Date(article.published_at || article.created_at).toLocaleDateString() }}
                </p>
            </div>
        </section>

        <div class="mx-auto max-w-3xl px-4 py-8">
            <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg p-8">
                <div class="prose max-w-none">
                    <SandboxedHtml :html="article.body" />
                </div>
            </div>
        </div>
    </PublicLayout>
</template>
