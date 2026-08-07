<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { computed } from 'vue';

const tenant = computed(() => usePage().props.tenant);
const primaryColor = computed(() => tenant.value?.primary_color || '#be123c');

const form = useForm({
    email: '',
});

function submit() {
    form.post(route('portal.forgot-password.submit'));
}
</script>

<template>
    <Head title="Forgot Password" />

    <PublicLayout>
        <div class="flex items-center justify-center py-16">
            <div class="w-full max-w-md px-4">
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg p-8">
                    <h2 class="text-2xl font-bold text-gray-900 text-center mb-2">Forgot Password</h2>
                    <p class="text-sm text-gray-500 text-center mb-6">Enter your email and we'll send you a reset link.</p>

                    <div v-if="$page.props.flash?.success" class="mb-4 rounded-md bg-green-50 border border-green-200 p-3">
                        <p class="text-sm text-green-800">{{ $page.props.flash.success }}</p>
                    </div>

                    <form @submit.prevent="submit" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Email</label>
                            <input v-model="form.email" type="email" required autofocus class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
                        </div>
                        <button type="submit" :disabled="form.processing" :style="{ backgroundColor: primaryColor }" class="w-full rounded-md px-4 py-2.5 text-sm font-medium text-white opacity-90 hover:opacity-100 disabled:opacity-50">
                            Send Reset Link
                        </button>
                    </form>

                    <p class="mt-4 text-center text-sm text-gray-500">
                        <Link :href="route('portal.login')" :style="{ color: primaryColor }" class="hover:underline">Back to sign in</Link>
                    </p>
                </div>
            </div>
        </div>
    </PublicLayout>
</template>
