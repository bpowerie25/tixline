<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';

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
    <Head title="Customer Login" />

    <div class="min-h-screen flex items-center justify-center bg-gray-100">
        <div class="w-full max-w-md px-4">
            <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg p-8">
                <h2 class="text-2xl font-bold text-gray-900 text-center mb-6">Customer Portal</h2>

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
            </div>
        </div>
    </div>
</template>
