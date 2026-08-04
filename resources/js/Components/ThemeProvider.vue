<script setup>
import { usePage, Head } from '@inertiajs/vue3';
import { computed, onMounted, watch } from 'vue';

const tenant = computed(() => usePage().props.tenant);

const cssVars = computed(() => {
    if (!tenant.value) return '';
    return `:root { ${tenant.value.css_variables}; }`;
});

const customCss = computed(() => tenant.value?.custom_css || '');

const faviconUrl = computed(() => tenant.value?.favicon_url);
</script>

<template>
    <Head v-if="faviconUrl">
        <link rel="icon" :href="faviconUrl" />
    </Head>
    <component :is="'style'" v-if="cssVars">{{ cssVars }}</component>
    <component :is="'style'" v-if="customCss">{{ customCss }}</component>
    <slot />
</template>
