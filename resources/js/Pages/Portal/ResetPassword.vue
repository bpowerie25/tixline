<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { computed } from 'vue';

const props = defineProps({
    token: String,
    email: String,
});

const tenant = computed(() => usePage().props.tenant);
const primaryColor = computed(() => tenant.value?.primary_color || '#be123c');

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

function submit() {
    form.post(route('portal.reset-password.submit'));
}
</script>

<template>
    <Head title="Reset Password" />

    <PublicLayout>
        <div class="flex items-center justify-center py-16">
            <div class="w-full max-w-md px-4">
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg p-8">
                    <h2 class="text-2xl font-bold text-gray-900 text-center mb-6">Reset Password</h2>

                    <div v-if="$page.props.flash?.error" class="mb-4 rounded-md bg-red-50 border border-red-200 p-3">
                        <p class="text-sm text-red-800">{{ $page.props.flash.error }}</p>
                    </div>
                    <div v-if="$page.props.flash?.success" class="mb-4 rounded-md bg-green-50 border border-green-200 p-3">
                        <p class="text-sm text-green-800">{{ $page.props.flash.success }}</p>
                    </div>

                    <form @submit.prevent="submit" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Email</label>
                            <input v-model="form.email" type="email" required readonly class="mt-1 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">New Password</label>
                            <input v-model="form.password" type="password" required autofocus class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            <p v-if="form.errors.password" class="mt-1 text-sm text-red-600">{{ form.errors.password }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Confirm Password</label>
                            <input v-model="form.password_confirmation" type="password" required class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        </div>
                        <button type="submit" :disabled="form.processing" :style="{ backgroundColor: primaryColor }" class="w-full rounded-md px-4 py-2.5 text-sm font-medium text-white opacity-90 hover:opacity-100 disabled:opacity-50">
                            Reset Password
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </PublicLayout>
</template>
