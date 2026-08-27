<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SandboxedHtml from '@/Components/SandboxedHtml.vue';
import SlaBadge from '@/Components/SlaBadge.vue';
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

const pageUrl = usePage().url;
const params = pageUrl.includes('?') ? new URLSearchParams(pageUrl.split('?')[1]) : new URLSearchParams();
const fromTicket = params.get('from');
const backUrl = params.get('back');

const props = defineProps({
    ticket: Object,
    teams: Array,
    agents: Array,
    labels: Array,
    cannedResponses: Array,
    hasCustomerAccount: Boolean,
    requesterTickets: Array,
    duplicates: Array,
});

const showCannedPicker = ref(false);

function insertCannedResponse(response) {
    const interpolated = response.body
        .replace(/\{\{requester_name\}\}/g, props.ticket.requester_name)
        .replace(/\{\{requester_email\}\}/g, props.ticket.requester_email)
        .replace(/\{\{ticket_reference\}\}/g, props.ticket.reference)
        .replace(/\{\{ticket_subject\}\}/g, props.ticket.subject)
        .replace(/\{\{agent_name\}\}/g, props.ticket.assignee?.name || '');
    commentForm.body = interpolated;
    showCannedPicker.value = false;
}

const commentForm = useForm({
    body: '',
    is_internal: false,
    attachments: [],
});

const fileInput = ref(null);

const maxFileSize = 10 * 1024 * 1024; // 10MB
const attachmentError = ref('');

function handleFiles(e) {
    attachmentError.value = '';
    const files = Array.from(e.target.files);
    const oversized = files.filter(f => f.size > maxFileSize);

    if (oversized.length) {
        attachmentError.value = `These files exceed the 10 MB limit: ${oversized.map(f => f.name).join(', ')}`;
        commentForm.attachments = files.filter(f => f.size <= maxFileSize);
    } else {
        commentForm.attachments = files;
    }
}

function removeFile(index) {
    commentForm.attachments.splice(index, 1);
    if (fileInput.value) fileInput.value.value = '';
}

const updateForm = useForm({
    status: props.ticket.status,
    priority: props.ticket.priority,
    team_id: props.ticket.team_id,
    assigned_to: props.ticket.assigned_to,
    labels: props.ticket.labels.map(l => l.id),
});

function submitComment(andClose = false) {
    commentForm.transform((data) => ({
        ...data,
        close_ticket: andClose,
    })).post(route('tickets.comments.store', props.ticket.id), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            commentForm.reset();
            if (fileInput.value) fileInput.value.value = '';
        },
    });
}

function updateTicket() {
    updateForm.put(route('tickets.update', props.ticket.id), {
        preserveScroll: true,
    });
}

function resolveAndClose() {
    if (!confirm('Resolve and close this ticket?')) return;
    router.put(route('tickets.update', props.ticket.id), {
        status: 'closed',
    }, { preserveScroll: true });
}

// Duplicate marking
const showDuplicateModal = ref(false);
const duplicateSearch = ref('');
const duplicateResults = ref([]);
const duplicateSearching = ref(false);
let duplicateDebounce;

function searchForDuplicate() {
    clearTimeout(duplicateDebounce);
    if (duplicateSearch.value.length < 2) {
        duplicateResults.value = [];
        return;
    }
    duplicateDebounce = setTimeout(async () => {
        duplicateSearching.value = true;
        try {
            const response = await fetch(route('tickets.search') + '?q=' + encodeURIComponent(duplicateSearch.value));
            const data = await response.json();
            duplicateResults.value = data.filter(t => t.id !== props.ticket.id);
        } catch (e) {
            duplicateResults.value = [];
        }
        duplicateSearching.value = false;
    }, 300);
}

function markAsDuplicate(originalId) {
    if (!confirm('Mark this ticket as a duplicate? It will be closed.')) return;
    router.post(route('tickets.mark-duplicate', props.ticket.id), {
        duplicate_of: originalId,
    }, { preserveScroll: true, onSuccess: () => { showDuplicateModal.value = false; } });
}

const resetSending = ref(false);

function sendPasswordReset() {
    if (!confirm('Send a password reset email to ' + props.ticket.requester_email + '?')) return;
    resetSending.value = true;
    router.post(route('tickets.send-password-reset', props.ticket.id), {}, {
        preserveScroll: true,
        onFinish: () => resetSending.value = false,
    });
}

const priorityColors = {
    low: 'bg-gray-100 text-gray-700',
    normal: 'bg-blue-100 text-blue-700',
    high: 'bg-orange-100 text-orange-700',
    urgent: 'bg-red-100 text-red-700',
};

const statusColors = {
    open: 'bg-green-100 text-green-700',
    pending: 'bg-yellow-100 text-yellow-700',
    resolved: 'bg-blue-100 text-blue-700',
    closed: 'bg-gray-100 text-gray-700',
};
</script>

<template>
    <Head :title="ticket.reference + ' - ' + ticket.subject" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="fromTicket ? route('tickets.show', fromTicket) : (backUrl || route('tickets.index'))" class="text-gray-400 hover:text-gray-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                </Link>
                <span class="font-mono text-gray-500">{{ ticket.reference }}</span>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ ticket.subject }}</h2>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div v-if="$page.props.flash?.success" class="mb-4 rounded-md bg-green-50 border border-green-200 p-4">
                    <p class="text-sm text-green-800">{{ $page.props.flash.success }}</p>
                </div>
                <div v-if="$page.props.flash?.warning" class="mb-4 rounded-md bg-yellow-50 border border-yellow-200 p-4">
                    <p class="text-sm text-yellow-800">{{ $page.props.flash.warning }}</p>
                </div>
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <!-- Main Content -->
                    <div class="lg:col-span-2 space-y-6">
                        <!-- Duplicate Banner -->
                        <div v-if="ticket.duplicate_of" class="rounded-md bg-purple-50 border border-purple-200 p-4">
                            <p class="text-sm text-purple-800">
                                This ticket is a duplicate of
                                <Link :href="route('tickets.show', ticket.duplicate_of.id)" class="font-medium underline hover:text-purple-900">
                                    {{ ticket.duplicate_of.reference }} — {{ ticket.duplicate_of.subject }}
                                </Link>
                            </p>
                        </div>

                        <!-- Original Message -->
                        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                            <div class="border-b border-gray-200 px-6 py-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <span class="font-medium text-gray-900">{{ ticket.requester_name }}</span>
                                        <span class="text-sm text-gray-500 ml-2">&lt;{{ ticket.requester_email }}&gt;</span>
                                    </div>
                                    <span class="text-sm text-gray-500">{{ new Date(ticket.created_at).toLocaleString() }}</span>
                                </div>
                            </div>
                            <div class="p-6">
                                <SandboxedHtml :html="ticket.sanitized_body" />
                            </div>

                            <!-- Custom Fields -->
                            <div v-if="ticket.custom_fields && Object.keys(ticket.custom_fields).length" class="border-t border-gray-200 px-6 py-4">
                                <h4 class="text-sm font-medium text-gray-500 mb-2">Custom Fields</h4>
                                <dl class="grid grid-cols-2 gap-2">
                                    <template v-for="(value, key) in ticket.custom_fields" :key="key">
                                        <dt class="text-sm text-gray-500">{{ key }}</dt>
                                        <dd class="text-sm text-gray-900">{{ value }}</dd>
                                    </template>
                                </dl>
                            </div>
                        </div>

                        <!-- Comments -->
                        <div v-for="comment in ticket.comments" :key="comment.id" class="overflow-hidden shadow-sm sm:rounded-lg" :class="comment.is_internal ? 'bg-yellow-50 border border-yellow-200' : 'bg-white'">
                            <div class="border-b px-6 py-3" :class="comment.is_internal ? 'border-yellow-200' : 'border-gray-200'">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <span class="font-medium text-gray-900">{{ comment.user?.name || 'System' }}</span>
                                        <span v-if="comment.is_internal" class="inline-flex rounded-full bg-yellow-100 px-2 py-0.5 text-xs font-medium text-yellow-800">Internal Note</span>
                                        <span v-if="comment.type === 'system'" class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">System</span>
                                    </div>
                                    <span class="text-sm text-gray-500">{{ new Date(comment.created_at).toLocaleString() }}</span>
                                </div>
                            </div>
                            <div class="p-6">
                                <SandboxedHtml :html="comment.sanitized_body" />
                            </div>
                            <div v-if="comment.attachments?.length" class="px-6 pb-4 flex flex-wrap gap-2">
                                <a
                                    v-for="att in comment.attachments"
                                    :key="att.id"
                                    :href="route('attachments.download', att.id)"
                                    target="_blank"
                                    class="inline-flex items-center gap-1.5 rounded border border-gray-200 bg-gray-50 px-2.5 py-1.5 text-xs text-gray-700 hover:bg-gray-100"
                                >
                                    <svg class="h-3.5 w-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" /></svg>
                                    {{ att.original_filename }}
                                </a>
                            </div>
                        </div>

                        <!-- Reply Form -->
                        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg p-6">
                            <form @submit.prevent="submitComment()">
                                <div class="mb-3 flex items-center gap-4">
                                    <label class="flex items-center gap-2">
                                        <input type="radio" :value="false" v-model="commentForm.is_internal" class="text-indigo-600" />
                                        <span class="text-sm">Reply</span>
                                    </label>
                                    <label class="flex items-center gap-2">
                                        <input type="radio" :value="true" v-model="commentForm.is_internal" class="text-yellow-600" />
                                        <span class="text-sm">Internal Note</span>
                                    </label>
                                </div>
                                <div class="relative">
                                    <textarea
                                        v-model="commentForm.body"
                                        rows="4"
                                        :placeholder="commentForm.is_internal ? 'Write an internal note...' : 'Write a reply...'"
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        :class="commentForm.is_internal ? 'bg-yellow-50' : ''"
                                    />
                                    <!-- Canned Response Picker -->
                                    <div v-if="showCannedPicker" class="absolute bottom-full left-0 mb-1 w-80 max-h-64 overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-lg z-10">
                                        <div class="p-2 border-b border-gray-100 text-xs font-medium text-gray-500">Canned Responses</div>
                                        <button
                                            v-for="cr in cannedResponses"
                                            :key="cr.id"
                                            type="button"
                                            @click="insertCannedResponse(cr)"
                                            class="w-full px-3 py-2 text-left hover:bg-gray-50 border-b border-gray-50 last:border-0"
                                        >
                                            <div class="flex items-center gap-2">
                                                <span class="text-sm font-medium text-gray-900">{{ cr.name }}</span>
                                                <code class="text-xs text-gray-400">#{{ cr.shortcode }}</code>
                                            </div>
                                            <p class="text-xs text-gray-500 truncate mt-0.5">{{ cr.body }}</p>
                                        </button>
                                        <div v-if="!cannedResponses.length" class="px-3 py-4 text-center text-sm text-gray-500">No canned responses yet.</div>
                                    </div>
                                </div>
                                <!-- File attachments -->
                                <div class="mt-3">
                                    <input ref="fileInput" type="file" multiple @change="handleFiles" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.txt,.csv,.doc,.docx,.xls,.xlsx,.zip,.eml" class="text-sm text-gray-500 file:mr-3 file:rounded file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-sm file:text-gray-700 hover:file:bg-gray-200" />
                                    <p class="mt-1 text-xs text-gray-400">Max 10 MB per file. Images, PDF, Office docs, CSV, TXT, ZIP.</p>
                                    <p v-if="attachmentError" class="mt-1 text-xs text-red-600">{{ attachmentError }}</p>
                                    <div v-if="commentForm.attachments.length" class="mt-2 flex flex-wrap gap-2">
                                        <span v-for="(file, i) in commentForm.attachments" :key="i" class="inline-flex items-center gap-1 rounded bg-gray-100 px-2 py-1 text-xs text-gray-700">
                                            {{ file.name }}
                                            <button type="button" @click="removeFile(i)" class="text-gray-400 hover:text-red-500">&times;</button>
                                        </span>
                                    </div>
                                </div>

                                <div class="mt-3 flex items-center justify-between">
                                    <button
                                        type="button"
                                        @click="showCannedPicker = !showCannedPicker"
                                        class="text-sm text-gray-500 hover:text-gray-700"
                                    >
                                        <span class="inline-flex items-center gap-1">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7" /></svg>
                                            Canned Response
                                        </span>
                                    </button>
                                    <div class="flex items-center gap-2">
                                        <button
                                            type="submit"
                                            :disabled="commentForm.processing || !commentForm.body"
                                            class="inline-flex items-center rounded-md px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                                            :class="commentForm.is_internal ? 'bg-yellow-600 hover:bg-yellow-700' : 'bg-indigo-600 hover:bg-indigo-700'"
                                        >
                                            {{ commentForm.is_internal ? 'Add Note' : 'Reply' }}
                                        </button>
                                        <button
                                            v-if="!commentForm.is_internal && ticket.status !== 'closed'"
                                            type="button"
                                            @click="submitComment(true)"
                                            :disabled="commentForm.processing || !commentForm.body"
                                            class="inline-flex items-center rounded-md bg-gray-700 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800 disabled:opacity-50"
                                        >
                                            Reply &amp; Close
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <div class="space-y-6">
                        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg p-6">
                            <h3 class="text-sm font-medium text-gray-500 mb-4">Ticket Details</h3>
                            <form @submit.prevent="updateTicket" class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Status</label>
                                    <select v-model="updateForm.status" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                                        <option value="open">Open</option>
                                        <option value="pending">Pending</option>
                                        <option value="resolved">Resolved</option>
                                        <option value="closed">Closed</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Priority</label>
                                    <select v-model="updateForm.priority" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                                        <option value="low">Low</option>
                                        <option value="normal">Normal</option>
                                        <option value="high">High</option>
                                        <option value="urgent">Urgent</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Team</label>
                                    <select v-model="updateForm.team_id" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                                        <option :value="null">Unassigned</option>
                                        <option v-for="team in teams" :key="team.id" :value="team.id">{{ team.name }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Assigned To</label>
                                    <select v-model="updateForm.assigned_to" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                                        <option :value="null">Unassigned</option>
                                        <option v-for="agent in agents" :key="agent.id" :value="agent.id">{{ agent.name }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Labels</label>
                                    <div class="space-y-1 max-h-32 overflow-y-auto">
                                        <label v-for="label in labels" :key="label.id" class="flex items-center gap-2">
                                            <input type="checkbox" :value="label.id" v-model="updateForm.labels" class="rounded text-indigo-600" />
                                            <span class="inline-flex items-center gap-1 text-sm">
                                                <span class="h-2 w-2 rounded-full" :style="{ backgroundColor: label.color }" />
                                                {{ label.name }}
                                            </span>
                                        </label>
                                    </div>
                                </div>
                                <button
                                    type="submit"
                                    :disabled="updateForm.processing"
                                    class="w-full rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                                >
                                    Update
                                </button>
                            </form>
                            <button
                                v-if="ticket.status !== 'closed'"
                                @click="resolveAndClose"
                                class="mt-2 w-full rounded-md bg-gray-700 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800"
                            >
                                Resolve &amp; Close
                            </button>
                        </div>

                        <!-- SLA Status -->
                        <div v-if="ticket.sla_status" class="overflow-hidden bg-white shadow-sm sm:rounded-lg p-6">
                            <h3 class="text-sm font-medium text-gray-500 mb-3">SLA</h3>
                            <SlaBadge :ticket="ticket" />
                            <dl class="mt-3 space-y-2 text-sm">
                                <div v-if="ticket.sla_response_due_at" class="flex justify-between">
                                    <dt class="text-gray-500">Response Due</dt>
                                    <dd class="text-gray-900">{{ new Date(ticket.sla_response_due_at).toLocaleString() }}</dd>
                                </div>
                                <div v-if="ticket.sla_resolution_due_at" class="flex justify-between">
                                    <dt class="text-gray-500">Resolution Due</dt>
                                    <dd class="text-gray-900">{{ new Date(ticket.sla_resolution_due_at).toLocaleString() }}</dd>
                                </div>
                            </dl>
                        </div>

                        <!-- Custom Fields -->
                        <div v-if="ticket.custom_fields && Object.keys(ticket.custom_fields).length" class="overflow-hidden bg-white shadow-sm sm:rounded-lg p-6">
                            <h3 class="text-sm font-medium text-gray-500 mb-3">Custom Fields</h3>
                            <dl class="space-y-2 text-sm">
                                <div v-for="(value, key) in ticket.custom_fields" :key="key" class="flex justify-between">
                                    <dt class="text-gray-500 capitalize">{{ key.replace(/_/g, ' ') }}</dt>
                                    <dd class="text-gray-900 text-right">{{ value || '—' }}</dd>
                                </div>
                            </dl>
                        </div>

                        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg p-6">
                            <h3 class="text-sm font-medium text-gray-500 mb-3">Requester</h3>
                            <Link :href="route('tickets.requester', ticket.requester_email)" class="text-sm text-indigo-600 font-medium hover:text-indigo-800">{{ ticket.requester_name }}</Link>
                            <p class="text-sm text-gray-500">{{ ticket.requester_email }}</p>
                            <button
                                v-if="hasCustomerAccount"
                                @click="sendPasswordReset"
                                :disabled="resetSending"
                                class="mt-3 w-full rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                            >
                                {{ resetSending ? 'Sending...' : 'Send Password Reset' }}
                            </button>
                        </div>

                        <!-- Requester History -->
                        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg p-6">
                            <h3 class="text-sm font-medium text-gray-500 mb-3">Requester History</h3>
                            <p v-if="!requesterTickets.length" class="text-sm text-gray-400">No previous tickets</p>
                            <ul v-else class="space-y-2">
                                <li v-for="rt in requesterTickets" :key="rt.id">
                                    <Link :href="route('tickets.show', rt.id) + '?from=' + ticket.id" class="block text-sm hover:bg-gray-50 -mx-2 px-2 py-1 rounded">
                                        <div class="flex items-center justify-between gap-2">
                                            <span class="text-gray-500 shrink-0">{{ rt.reference }}</span>
                                            <span
                                                class="shrink-0 inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                                :class="{
                                                    'bg-green-100 text-green-800': rt.status === 'open',
                                                    'bg-yellow-100 text-yellow-800': rt.status === 'pending',
                                                    'bg-blue-100 text-blue-800': rt.status === 'resolved',
                                                    'bg-gray-100 text-gray-800': rt.status === 'closed',
                                                }"
                                            >
                                                {{ rt.status }}
                                            </span>
                                        </div>
                                        <p class="text-gray-700 truncate">{{ rt.subject }}</p>
                                        <p class="text-xs text-gray-400">{{ new Date(rt.created_at).toLocaleDateString() }}</p>
                                    </Link>
                                </li>
                            </ul>
                        </div>

                        <!-- Duplicates -->
                        <div v-if="duplicates?.length" class="overflow-hidden bg-white shadow-sm sm:rounded-lg p-6">
                            <h3 class="text-sm font-medium text-gray-500 mb-3">Duplicates</h3>
                            <ul class="space-y-2">
                                <li v-for="dup in duplicates" :key="dup.id">
                                    <Link :href="route('tickets.show', dup.id)" class="block text-sm hover:bg-gray-50 -mx-2 px-2 py-1 rounded">
                                        <div class="flex items-center justify-between gap-2">
                                            <span class="text-gray-500">{{ dup.reference }}</span>
                                            <span class="inline-flex rounded-full bg-purple-100 px-2 py-0.5 text-xs font-medium text-purple-700">duplicate</span>
                                        </div>
                                        <p class="text-gray-700 truncate">{{ dup.subject }}</p>
                                    </Link>
                                </li>
                            </ul>
                        </div>

                        <!-- Mark as Duplicate -->
                        <div v-if="!ticket.duplicate_of && ticket.status !== 'closed'" class="overflow-hidden bg-white shadow-sm sm:rounded-lg p-6">
                            <button
                                v-if="!showDuplicateModal"
                                @click="showDuplicateModal = true"
                                class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                            >
                                Mark as Duplicate
                            </button>
                            <div v-else>
                                <h3 class="text-sm font-medium text-gray-500 mb-3">Mark as Duplicate</h3>
                                <input
                                    v-model="duplicateSearch"
                                    @input="searchForDuplicate"
                                    type="text"
                                    placeholder="Search by reference or subject..."
                                    class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                />
                                <p v-if="duplicateSearching" class="mt-2 text-xs text-gray-400">Searching...</p>
                                <ul v-if="duplicateResults.length" class="mt-2 divide-y divide-gray-100 rounded border border-gray-200 max-h-48 overflow-y-auto">
                                    <li
                                        v-for="result in duplicateResults"
                                        :key="result.id"
                                        @click="markAsDuplicate(result.id)"
                                        class="px-3 py-2 cursor-pointer hover:bg-gray-50"
                                    >
                                        <div class="flex items-center justify-between gap-2">
                                            <span class="text-xs font-mono text-gray-500">{{ result.reference }}</span>
                                            <span
                                                class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                                :class="{
                                                    'bg-green-100 text-green-700': result.status === 'open',
                                                    'bg-yellow-100 text-yellow-700': result.status === 'pending',
                                                    'bg-blue-100 text-blue-700': result.status === 'resolved',
                                                    'bg-gray-100 text-gray-700': result.status === 'closed',
                                                }"
                                            >{{ result.status }}</span>
                                        </div>
                                        <p class="text-sm text-gray-700 truncate">{{ result.subject }}</p>
                                    </li>
                                </ul>
                                <p v-else-if="duplicateSearch.length >= 2 && !duplicateSearching" class="mt-2 text-xs text-gray-400">No matching tickets found.</p>
                                <button
                                    @click="showDuplicateModal = false; duplicateSearch = ''; duplicateResults = [];"
                                    class="mt-3 w-full rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50"
                                >
                                    Cancel
                                </button>
                            </div>
                        </div>

                        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg p-6">
                            <h3 class="text-sm font-medium text-gray-500 mb-3">Info</h3>
                            <dl class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <dt class="text-gray-500">Source</dt>
                                    <dd class="text-gray-900">{{ ticket.source }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-gray-500">Created</dt>
                                    <dd class="text-gray-900">{{ new Date(ticket.created_at).toLocaleDateString() }}</dd>
                                </div>
                                <div v-if="ticket.first_responded_at" class="flex justify-between">
                                    <dt class="text-gray-500">First Response</dt>
                                    <dd class="text-gray-900">{{ new Date(ticket.first_responded_at).toLocaleDateString() }}</dd>
                                </div>
                                <div v-if="ticket.resolved_at" class="flex justify-between">
                                    <dt class="text-gray-500">Resolved</dt>
                                    <dd class="text-gray-900">{{ new Date(ticket.resolved_at).toLocaleDateString() }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
