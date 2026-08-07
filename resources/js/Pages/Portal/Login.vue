<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const tenant = computed(() => usePage().props.tenant);

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

function submit() {
    form.post(route('portal.login.submit'));
}
</script>

<template>
    <Head :title="(tenant?.portal_title || 'Portal') + ' - Sign In'" />

    <div class="min-h-screen flex items-center justify-center bg-gray-100">
        <div class="w-full max-w-md px-4">
            <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg p-8">
                <div class="text-center mb-6">
                    <img v-if="tenant?.logo_url" :src="tenant.logo_url" class="mx-auto h-12 mb-4" :alt="tenant.name" />
                    <h2 class="text-2xl font-bold text-gray-900">{{ tenant?.portal_title || 'Portal' }}</h2>
                </div>

                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Email</label>
                        <input v-model="form.email" type="email" required autofocus class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Password</label>
                        <input v-model="form.password" type="password" required class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                    </div>
                    <label class="flex items-center gap-2">
                        <input v-model="form.remember" type="checkbox" class="rounded text-indigo-600" />
                        <span class="text-sm text-gray-600">Remember me</span>
                    </label>
                    <button type="submit" :disabled="form.processing" class="w-full rounded-md bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                        Sign In
                    </button>
                </form>

                <p class="mt-4 text-center text-sm text-gray-500">
                    Don't have an account?
                    <Link :href="route('portal.register')" class="text-indigo-600 hover:text-indigo-800">Register</Link>
                </p>
                <p class="mt-2 text-center text-xs text-gray-400">
                    <Link :href="route('login')" class="hover:text-gray-600">Staff login</Link>
                </p>
            </div>
        </div>
    </div>
</template>
