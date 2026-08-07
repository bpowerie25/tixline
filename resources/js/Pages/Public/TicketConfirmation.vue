<script setup>
import { Head } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';

const props = defineProps({
    ticket: Object,
    accountCreated: Boolean,
});
</script>

<template>
    <Head title="Request Submitted" />

    <PublicLayout>
        <div class="flex items-center justify-center py-16">
            <div class="mx-auto max-w-md px-4">
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg p-8 text-center">
                    <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-green-100">
                        <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <h2 class="text-xl font-semibold text-gray-900">Request Submitted</h2>
                    <p class="mt-2 text-gray-600">Your support request has been received.</p>
                    <div class="mt-4 rounded-lg bg-gray-50 p-4">
                        <p class="text-sm text-gray-500">Reference Number</p>
                        <p class="text-lg font-mono font-bold text-indigo-600">{{ ticket.reference }}</p>
                        <p class="mt-2 text-sm text-gray-500">{{ ticket.subject }}</p>
                    </div>

                    <div v-if="accountCreated" class="mt-4 rounded-lg bg-blue-50 border border-blue-200 p-4 text-left">
                        <p class="text-sm font-medium text-blue-800">A portal account has been created for you.</p>
                        <p class="mt-1 text-sm text-blue-700">Check your email for your login details so you can track and reply to your tickets.</p>
                        <a :href="route('portal.login')" class="mt-3 inline-block rounded bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                            Sign in to portal
                        </a>
                    </div>

                    <a :href="route('submit.create')" class="mt-6 inline-block text-sm text-indigo-600 hover:text-indigo-800">
                        Submit another request
                    </a>
                </div>
            </div>
        </div>
    </PublicLayout>
</template>
