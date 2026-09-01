<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import SandboxedHtml from '@/Components/SandboxedHtml.vue';
import { ref } from 'vue';

const props = defineProps({
    ticket: Object,
    customer: Object,
});

const replyForm = useForm({
    body: '',
    attachments: [],
});

const fileInput = ref(null);
const maxFileSize = 10 * 1024 * 1024;
const attachmentError = ref('');

function handleFiles(e) {
    attachmentError.value = '';
    const files = Array.from(e.target.files);
    const oversized = files.filter(f => f.size > maxFileSize);

    if (oversized.length) {
        attachmentError.value = `These files exceed the 10 MB limit: ${oversized.map(f => f.name).join(', ')}`;
        replyForm.attachments = files.filter(f => f.size <= maxFileSize);
    } else {
        replyForm.attachments = files;
    }
}

function removeFile(index) {
    replyForm.attachments.splice(index, 1);
    if (fileInput.value) fileInput.value.value = '';
}

function submitReply() {
    replyForm.post(route('portal.ticket.reply', props.ticket.id), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            replyForm.reset();
            if (fileInput.value) fileInput.value.value = '';
        },
    });
}

const previewImage = ref(null);

function openPreview(attachment) {
    previewImage.value = attachment;
}

function closePreview() {
    previewImage.value = null;
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
            <div v-if="$page.props.flash?.success" class="rounded-md bg-green-50 border border-green-200 p-4">
                <p class="text-sm text-green-800">{{ $page.props.flash.success }}</p>
            </div>
            <div v-if="$page.props.flash?.warning" class="rounded-md bg-yellow-50 border border-yellow-200 p-4">
                <p class="text-sm text-yellow-800">{{ $page.props.flash.warning }}</p>
            </div>
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
                <div v-if="ticket.attachments?.length" class="mt-4 flex flex-wrap gap-2">
                    <template v-for="att in ticket.attachments" :key="att.id">
                        <button
                            v-if="att.is_image"
                            @click="openPreview(att)"
                            class="inline-flex items-center gap-1.5 rounded border border-gray-200 bg-gray-50 px-2.5 py-1.5 text-xs text-gray-700 hover:bg-gray-100"
                        >
                            <svg class="h-3.5 w-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                            {{ att.original_filename }}
                        </button>
                        <a
                            v-else
                            :href="route('attachments.download', att.id)"
                            target="_blank"
                            class="inline-flex items-center gap-1.5 rounded border border-gray-200 bg-gray-50 px-2.5 py-1.5 text-xs text-gray-700 hover:bg-gray-100"
                        >
                            <svg class="h-3.5 w-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" /></svg>
                            {{ att.original_filename }}
                        </a>
                    </template>
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
                <div v-if="comment.attachments?.length" class="px-6 pb-4 flex flex-wrap gap-2">
                    <template v-for="att in comment.attachments" :key="att.id">
                        <button
                            v-if="att.is_image"
                            @click="openPreview(att)"
                            class="inline-flex items-center gap-1.5 rounded border border-gray-200 bg-gray-50 px-2.5 py-1.5 text-xs text-gray-700 hover:bg-gray-100"
                        >
                            <svg class="h-3.5 w-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                            {{ att.original_filename }}
                        </button>
                        <a
                            v-else
                            :href="route('attachments.download', att.id)"
                            target="_blank"
                            class="inline-flex items-center gap-1.5 rounded border border-gray-200 bg-gray-50 px-2.5 py-1.5 text-xs text-gray-700 hover:bg-gray-100"
                        >
                            <svg class="h-3.5 w-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" /></svg>
                            {{ att.original_filename }}
                        </a>
                    </template>
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
                    <div class="mt-3">
                        <input ref="fileInput" type="file" multiple @change="handleFiles" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.txt,.csv,.doc,.docx,.xls,.xlsx,.zip,.eml" class="text-sm text-gray-500 file:mr-3 file:rounded file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-sm file:text-gray-700 hover:file:bg-gray-200" />
                        <p class="mt-1 text-xs text-gray-400">Max 10 MB per file. Images, PDF, Office docs, CSV, TXT, ZIP.</p>
                        <p v-if="attachmentError" class="mt-1 text-xs text-red-600">{{ attachmentError }}</p>
                        <div v-if="replyForm.attachments.length" class="mt-2 flex flex-wrap gap-2">
                            <span v-for="(file, i) in replyForm.attachments" :key="i" class="inline-flex items-center gap-1 rounded bg-gray-100 px-2 py-1 text-xs text-gray-700">
                                {{ file.name }}
                                <button type="button" @click="removeFile(i)" class="text-gray-400 hover:text-red-500">&times;</button>
                            </span>
                        </div>
                    </div>
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

        <!-- Image Preview Modal -->
        <div v-if="previewImage" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60" @click.self="closePreview">
            <div class="relative max-w-4xl max-h-[90vh] mx-4">
                <button @click="closePreview" class="absolute -top-10 right-0 text-white hover:text-gray-300 text-sm flex items-center gap-1">
                    Close
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
                <img :src="route('attachments.preview', previewImage.id)" :alt="previewImage.original_filename" class="max-w-full max-h-[85vh] rounded-lg shadow-2xl" />
                <div class="mt-2 flex items-center justify-between text-sm text-white/80">
                    <span>{{ previewImage.original_filename }}</span>
                    <a :href="route('attachments.download', previewImage.id)" class="hover:text-white underline">Download</a>
                </div>
            </div>
        </div>
    </div>
</template>
