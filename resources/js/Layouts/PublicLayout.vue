<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref, onMounted } from 'vue';

const tenant = computed(() => usePage().props.tenant);
const primaryColor = computed(() => tenant.value?.primary_color || '#be123c');

const showCookieBanner = ref(false);

onMounted(() => {
    if (!localStorage.getItem('cookie_consent')) {
        showCookieBanner.value = true;
    }
});

function acceptCookies() {
    localStorage.setItem('cookie_consent', 'accepted');
    showCookieBanner.value = false;
}
</script>

<template>
    <div class="min-h-screen bg-gray-100 flex flex-col">
        <!-- Header -->
        <header class="border-b border-gray-200 bg-white">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
                <Link href="/" class="flex items-center gap-3">
                    <img v-if="tenant?.logo_url" :src="tenant.logo_url" class="h-9" :alt="tenant?.name" />
                    <span class="text-xl font-bold text-gray-800">{{ tenant?.portal_title || 'Support' }}</span>
                </Link>

                <nav class="flex items-center gap-3">
                    <Link
                        :href="route('submit.create')"
                        :style="{ backgroundColor: primaryColor }"
                        class="rounded px-4 py-2 text-sm font-medium text-white transition opacity-90 hover:opacity-100"
                    >
                        Raise a Ticket
                    </Link>

                    <Link
                        v-if="$page.props.auth?.user"
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
                </nav>
            </div>
        </header>

        <!-- Content -->
        <main class="flex-1">
            <slot />
        </main>

        <!-- Footer -->
        <footer class="border-t border-gray-200 bg-gray-50 px-6 py-6">
            <div class="mx-auto max-w-6xl text-sm text-gray-500">
                <div class="flex flex-col sm:flex-row items-center justify-between gap-3">
                    <div class="flex flex-wrap gap-4">
                        <Link :href="route('kb.portal')" class="hover:text-gray-700">Knowledge Base</Link>
                        <Link :href="route('submit.create')" class="hover:text-gray-700">Raise a Ticket</Link>
                        <Link :href="route('cookie-policy')" class="hover:text-gray-700">Cookie Policy</Link>
                    </div>
                    <a href="https://github.com/bpowerie25/tixline" target="_blank" rel="noopener" class="hover:text-gray-700">
                        Powered by Tixline
                    </a>
                </div>
            </div>
        </footer>

        <!-- Cookie Consent Banner -->
        <div v-if="showCookieBanner" class="fixed bottom-0 inset-x-0 z-50 bg-white border-t border-gray-200 shadow-lg px-6 py-4">
            <div class="mx-auto max-w-6xl flex flex-col sm:flex-row items-center justify-between gap-4">
                <p class="text-sm text-gray-600">
                    We use essential cookies to keep you signed in and ensure the site functions correctly. No tracking or advertising cookies are used.
                    <Link :href="route('cookie-policy')" class="text-indigo-600 hover:underline">Learn more</Link>
                </p>
                <button @click="acceptCookies" :style="{ backgroundColor: primaryColor }" class="shrink-0 rounded px-5 py-2 text-sm font-medium text-white opacity-90 hover:opacity-100">
                    Got it
                </button>
            </div>
        </div>
    </div>
</template>
