<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';

const props = defineProps({
    forms: Array,
});

function deleteForm(form) {
    if (confirm(`Delete form "${form.name}"?`)) {
        router.delete(route('forms.destroy', form.id));
    }
}
</script>

<template>
    <Head title="Forms" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Forms</h2>
                <Link :href="route('forms.create')" class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    New Form
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-4xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="divide-y divide-gray-200">
                        <div v-for="form in forms" :key="form.id" class="flex items-center justify-between px-6 py-4">
                            <div>
                                <div class="font-medium text-gray-900">{{ form.name }}</div>
                                <div class="text-sm text-gray-500">
                                    {{ form.fields_count }} fields &middot; {{ form.tickets_count }} tickets
                                    <span :class="form.is_active ? 'text-green-600' : 'text-gray-400'" class="ml-2">
                                        {{ form.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <Link :href="route('forms.show', form.id)" class="text-sm text-indigo-600 hover:text-indigo-800">Edit</Link>
                                <button @click="deleteForm(form)" class="text-sm text-red-600 hover:text-red-800">Delete</button>
                            </div>
                        </div>
                        <div v-if="!forms.length" class="px-6 py-8 text-center text-gray-500">No forms yet.</div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
