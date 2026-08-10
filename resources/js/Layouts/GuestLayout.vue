<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import ThemeProvider from '@/Components/ThemeProvider.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const tenant = computed(() => usePage().props.tenant);
</script>

<template>
    <ThemeProvider>
    <div
        class="flex min-h-screen flex-col items-center bg-gray-100 pt-6 sm:justify-center sm:pt-0"
    >
        <div>
            <Link href="/">
                <img v-if="tenant?.logo_url" :src="tenant.logo_url" class="h-16" :alt="tenant?.name" />
                <ApplicationLogo v-else class="h-20 w-20 fill-current text-gray-500" />
            </Link>
        </div>

        <div
            class="mt-6 w-full overflow-hidden bg-white px-6 py-4 shadow-md sm:max-w-md sm:rounded-lg"
        >
            <slot />
        </div>
    </div>
    </ThemeProvider>
</template>
