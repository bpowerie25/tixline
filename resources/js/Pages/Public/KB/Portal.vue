<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { ref, computed } from 'vue';

const props = defineProps({
    categories: Array,
});

const tenant = computed(() => usePage().props.tenant);
const primaryColor = computed(() => tenant.value?.primary_color || '#be123c');
const primaryColorLight = computed(() => (tenant.value?.primary_color || '#be123c') + '15');

const searchQuery = ref('');

function search() {
    if (searchQuery.value.trim()) {
        router.get(route('kb.search'), { q: searchQuery.value });
    }
}
</script>

<template>
    <Head title="Knowledge Base" />

    <PublicLayout>
        <section :style="{ backgroundColor: primaryColorLight }" class="py-12 px-4 text-center">
            <h1 :style="{ color: primaryColor }" class="text-3xl font-bold">Knowledge Base</h1>
            <form @submit.prevent="search" class="mx-auto mt-6 max-w-2xl">
                <input v-model="searchQuery" type="text" placeholder="Search articles..." class="w-full rounded-lg border border-gray-300 bg-white px-6 py-3 text-lg shadow-sm focus:ring-2 focus:ring-gray-300 focus:border-gray-400" />
            </form>
        </section>

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
                    <p :style="{ color: primaryColor }" class="mt-3 text-sm">{{ category.published_articles_count }} articles</p>
                </Link>
            </div>

            <div v-if="!categories.length" class="text-center py-16 text-gray-500">
                No knowledge base articles yet.
            </div>
        </div>
    </PublicLayout>
</template>
