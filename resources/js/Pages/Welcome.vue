<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

defineProps({
    canLogin: {
        type: Boolean,
    },
    canRegister: {
        type: Boolean,
    },
});

const tenant = computed(() => usePage().props.tenant);

const searchQuery = ref('');

function search() {
    if (searchQuery.value.trim()) {
        router.get(route('kb.search'), { q: searchQuery.value });
    }
}
</script>

<template>
    <Head title="Support" />

    <div class="min-h-screen bg-white">
        <!-- Header -->
        <header class="border-b border-gray-200 bg-white">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
                <div class="flex items-center gap-3">
                    <img v-if="tenant?.logo_url" :src="tenant.logo_url" class="h-9" :alt="tenant?.name" />
                    <span class="text-xl font-bold text-gray-800">{{ tenant?.portal_title || 'Support' }}</span>
                </div>

                <nav class="flex items-center gap-3">
                    <Link
                        :href="route('submit.create')"
                        class="rounded bg-rose-700 px-4 py-2 text-sm font-medium text-white transition hover:bg-rose-800"
                    >
                        Contact Us
                    </Link>

                    <template v-if="canLogin">
                        <Link
                            v-if="$page.props.auth.user"
                            :href="route('dashboard')"
                            class="rounded bg-gray-800 px-4 py-2 text-sm font-medium text-white transition hover:bg-gray-900"
                        >
                            Dashboard
                        </Link>
                        <Link
                            v-else
                            :href="route('portal.login')"
                            class="rounded bg-gray-800 px-4 py-2 text-sm font-medium text-white transition hover:bg-gray-900"
                        >
                            Sign In
                        </Link>
                    </template>
                </nav>
            </div>
        </header>

        <!-- Hero with search -->
        <section class="bg-rose-50 px-6 py-16 text-center">
            <h1 class="text-3xl font-semibold text-rose-700 md:text-4xl">
                {{ tenant?.portal_welcome_text || 'Hi! How can we help you?' }}
            </h1>

            <form @submit.prevent="search" class="mx-auto mt-8 max-w-2xl">
                <div class="flex items-center rounded-lg border border-gray-300 bg-white shadow-sm">
                    <svg
                        class="ml-4 h-5 w-5 text-gray-400"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="2"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"
                        />
                    </svg>
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Enter search keyword"
                        class="w-full border-0 bg-transparent px-4 py-4 text-gray-700 placeholder-gray-400 focus:outline-none focus:ring-0"
                    />
                </div>
            </form>
        </section>

        <!-- Explore KB -->
        <section class="mx-auto max-w-6xl px-6 py-12 text-center">
            <h2 class="text-xl font-semibold text-gray-600">Explore the knowledge base</h2>
            <p class="mt-2 text-gray-500">
                Check out our knowledge base to see if your question has already been answered.
            </p>

            <div class="mt-6">
                <Link
                    :href="route('kb.portal')"
                    class="text-rose-700 underline transition hover:text-rose-800"
                >
                    Browse articles
                </Link>
            </div>
        </section>

        <hr class="mx-auto max-w-6xl border-gray-200" />

        <!-- Contact section -->
        <section class="mx-auto max-w-6xl px-6 py-12">
            <div class="flex items-start gap-6">
                <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded bg-rose-700 text-white">
                    <svg class="h-8 w-8" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3-3c-1.354 0-2.694-.055-4.02-.163a2.115 2.115 0 0 1-.825-.242m9.345-8.334a2.126 2.126 0 0 0-.476-.095 48.64 48.64 0 0 0-8.048 0c-1.131.094-1.976 1.057-1.976 2.192v4.286c0 .837.46 1.58 1.155 1.951m9.345-8.334V6.637c0-1.621-1.152-3.026-2.76-3.235A48.455 48.455 0 0 0 11.25 3c-2.115 0-4.198.137-6.24.402-1.608.209-2.76 1.614-2.76 3.235v6.226c0 1.621 1.152 3.026 2.76 3.235.577.075 1.157.14 1.74.194V21l4.155-4.155" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-700">Contact Our Team</h3>
                    <p class="mt-1 text-gray-500">
                        If you still can't find an answer to what you're looking for, or you have a specific question, open a new ticket and we'd be happy to help!
                    </p>
                    <Link
                        :href="route('submit.create')"
                        class="mt-4 inline-block rounded bg-rose-700 px-5 py-2 text-sm font-medium text-white transition hover:bg-rose-800"
                    >
                        Contact Us
                    </Link>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="border-t border-gray-200 bg-gray-50 px-6 py-6">
            <div class="mx-auto flex max-w-6xl items-center justify-between text-sm text-gray-500">
                <div class="flex gap-4">
                    <Link :href="route('kb.portal')" class="hover:text-gray-700">Knowledge Base</Link>
                    <Link :href="route('submit.create')" class="hover:text-gray-700">Contact Us</Link>
                </div>
                <span>Powered by Tixline</span>
            </div>
        </footer>
    </div>
</template>
