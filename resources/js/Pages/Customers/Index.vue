<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    customers: Object,
    filters: Object,
});

const search = ref(props.filters.search || '');

let debounceTimer;
function applyFilters() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        router.get(route('customers.index'), {
            search: search.value || undefined,
        }, { preserveState: true, replace: true });
    }, 300);
}

watch(search, applyFilters);

function timeAgo(dateStr) {
    if (!dateStr) return 'Never';
    const date = new Date(dateStr);
    const now = new Date();
    const diff = Math.floor((now - date) / 1000);
    if (diff < 60) return 'Just now';
    if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
    if (diff < 2592000) return Math.floor(diff / 86400) + 'd ago';
    return date.toLocaleDateString();
}
</script>

<template>
    <Head title="Requesters" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Requesters</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-4">
                <!-- Search -->
                <div>
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Search by name, email, or organization..."
                        class="w-full max-w-md rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                    />
                </div>

                <div class="text-sm text-gray-500">
                    {{ customers.total }} requester{{ customers.total === 1 ? '' : 's' }}
                </div>

                <!-- Table -->
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Email</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Organization</th>
                                <th class="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500">Tickets</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Last Login</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Created</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <tr
                                v-for="customer in customers.data"
                                :key="customer.id"
                                class="hover:bg-gray-50"
                            >
                                <td class="whitespace-nowrap px-6 py-4">
                                    <Link :href="route('customers.show', customer.id)" class="font-medium text-indigo-600 hover:text-indigo-800">
                                        {{ customer.name }}
                                    </Link>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">{{ customer.email }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">{{ customer.organization || '—' }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-center text-sm text-gray-900">{{ customer.tickets_count }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                    <span :class="{ 'text-red-500': !customer.last_login_at }">
                                        {{ timeAgo(customer.last_login_at) }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">{{ new Date(customer.created_at).toLocaleDateString() }}</td>
                            </tr>
                            <tr v-if="!customers.data.length">
                                <td colspan="6" class="px-6 py-8 text-center text-gray-500">No requesters found.</td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Pagination -->
                    <div v-if="customers.last_page > 1" class="flex items-center justify-between border-t border-gray-200 px-6 py-3">
                        <div class="text-sm text-gray-500">
                            Showing {{ customers.from }} to {{ customers.to }} of {{ customers.total }}
                        </div>
                        <div class="flex gap-1">
                            <Link
                                v-for="link in customers.links"
                                :key="link.label"
                                :href="link.url || '#'"
                                :class="[
                                    'rounded px-3 py-1 text-sm',
                                    link.active ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-gray-100',
                                    !link.url ? 'cursor-default opacity-50' : '',
                                ]"
                                v-html="link.label"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
