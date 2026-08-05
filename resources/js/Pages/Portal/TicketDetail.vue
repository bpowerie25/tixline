<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import SandboxedHtml from '@/Components/SandboxedHtml.vue';

const props = defineProps({
    ticket: Object,
    customer: Object,
});

const replyForm = useForm({
    body: '',
});

function submitReply() {
    replyForm.post(route('portal.ticket.reply', props.ticket.id), {
        preserveScroll: true,
        onSuccess: () => replyForm.reset(),
    });
}

const statusColors = {
    open: 'bg-green-100 text-green-700',
    pending: 'bg-yellow-100 text-yellow-700',
    resolved: 'bg-blue-100 text-blue-700',
    closed: 'bg-gray-100 text-gray-700',
};
</script>

<template>
    <Head :title="ticket.reference + ' - ' + ticket.subject" />

    <div class="min-h-screen bg-gray-100">
        <nav class="border-b border-gray-200 bg-white">
            <div class="mx-auto max-w-4xl px-4 py-4 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <Link :href="route('portal.tickets')" class="text-gray-400 hover:text-gray-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                    </Link>
                    <h1 class="text-lg font-semibold text-gray-900">{{ ticket.reference }}</h1>
                </div>
                <span class="text-sm text-gray-500">{{ customer.name }}</span>
            </div>
        </nav>

        <div class="mx-auto max-w-4xl px-4 py-8 space-y-6">
            <!-- Ticket header -->
            <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg p-6">
                <div class="flex items-start justify-between">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-900">{{ ticket.subject }}</h2>
                        <div class="mt-2 flex items-center gap-3 text-sm text-gray-500">
                            <span>{{ new Date(ticket.created_at).toLocaleString() }}</span>
                            <span :class="[statusColors[ticket.status], 'inline-flex rounded-full px-2 py-0.5 text-xs font-medium capitalize']">
                                {{ ticket.status }}
                            </span>
                        </div>
                    </div>
                </div>
                <div class="mt-4">
                    <SandboxedHtml :html="ticket.sanitized_body" />
                </div>
            </div>

            <!-- Comments -->
            <div v-for="comment in ticket.comments" :key="comment.id" class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <div class="border-b border-gray-200 px-6 py-3">
                    <div class="flex items-center justify-between">
                        <span class="font-medium text-gray-900">{{ comment.user?.name || customer.name }}</span>
                        <span class="text-sm text-gray-500">{{ new Date(comment.created_at).toLocaleString() }}</span>
                    </div>
                </div>
                <div class="p-6">
                    <SandboxedHtml :html="comment.sanitized_body" />
                </div>
            </div>

            <!-- Reply form -->
            <div v-if="ticket.status !== 'closed'" class="overflow-hidden bg-white shadow-sm sm:rounded-lg p-6">
                <form @submit.prevent="submitReply">
                    <textarea
                        v-model="replyForm.body"
                        rows="4"
                        placeholder="Write a reply..."
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                    <div class="mt-3 flex justify-end">
                        <button type="submit" :disabled="replyForm.processing || !replyForm.body" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                            Send Reply
                        </button>
                    </div>
                </form>
            </div>
            <div v-else class="text-center text-sm text-gray-500 py-4">
                This ticket is closed.
            </div>
        </div>
    </div>
</template>
