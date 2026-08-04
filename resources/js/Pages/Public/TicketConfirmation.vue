<script setup>
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    ticket: Object,
});
</script>

<template>
    <Head title="Request Submitted" />

    <div class="min-h-screen bg-gray-100 flex items-center justify-center">
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
                <a :href="route('submit.create')" class="mt-6 inline-block text-sm text-indigo-600 hover:text-indigo-800">
                    Submit another request
                </a>
            </div>
        </div>
    </div>
</template>
