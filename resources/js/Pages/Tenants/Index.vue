<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    tenants: Array,
});

function deleteTenant(tenant) {
    if (confirm(`Delete tenant "${tenant.name}"? This cannot be undone.`)) {
        router.delete(route('tenants.destroy', tenant.id));
    }
}
</script>

<template>
    <Head title="Tenants" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Tenants / Branding</h2>
                <Link :href="route('tenants.create')" class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    New Tenant
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="divide-y divide-gray-200">
                        <Link
                            v-for="tenant in tenants"
                            :key="tenant.id"
                            :href="route('tenants.show', tenant.id)"
                            class="flex items-center justify-between px-6 py-4 hover:bg-gray-50"
                        >
                            <div class="flex items-center gap-4">
                                <div class="flex gap-1">
                                    <span class="h-6 w-6 rounded" :style="{ backgroundColor: tenant.primary_color }" />
                                    <span class="h-6 w-6 rounded" :style="{ backgroundColor: tenant.secondary_color }" />
                                    <span class="h-6 w-6 rounded" :style="{ backgroundColor: tenant.accent_color }" />
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-medium text-gray-900">{{ tenant.name }}</span>
                                        <span v-if="!tenant.is_active" class="text-xs text-gray-400">(inactive)</span>
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        {{ tenant.domain || tenant.slug }}
                                        &middot; {{ tenant.users_count }} users
                                        &middot; {{ tenant.tickets_count }} tickets
                                    </div>
                                </div>
                            </div>
                            <button @click.prevent="deleteTenant(tenant)" class="text-sm text-red-600 hover:text-red-800">Delete</button>
                        </Link>
                        <div v-if="!tenants.length" class="px-6 py-8 text-center text-gray-500">No tenants configured yet.</div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
