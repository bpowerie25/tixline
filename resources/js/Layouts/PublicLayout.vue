<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const tenant = computed(() => usePage().props.tenant);
const primaryColor = computed(() => tenant.value?.primary_color || '#be123c');
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
            <div class="mx-auto flex max-w-6xl items-center justify-between text-sm text-gray-500">
                <div class="flex gap-4">
                    <Link :href="route('kb.portal')" class="hover:text-gray-700">Knowledge Base</Link>
                    <Link :href="route('submit.create')" class="hover:text-gray-700">Raise a Ticket</Link>
                </div>
                <span>Powered by Tixline</span>
            </div>
        </footer>
    </div>
</template>
