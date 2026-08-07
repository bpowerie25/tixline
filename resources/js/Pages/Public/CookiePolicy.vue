<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { computed } from 'vue';

const tenant = computed(() => usePage().props.tenant);
</script>

<template>
    <Head title="Cookie Policy" />

    <PublicLayout>
        <div class="mx-auto max-w-3xl px-6 py-12">
            <div class="bg-white shadow-sm sm:rounded-lg p-8">
                <h1 class="text-2xl font-bold text-gray-900 mb-6">Cookie Policy</h1>
                <p class="text-sm text-gray-500 mb-6">Last updated: August 2026</p>

                <div class="prose prose-gray max-w-none text-gray-600 space-y-6">
                    <section>
                        <h2 class="text-lg font-semibold text-gray-800">What are cookies?</h2>
                        <p>
                            Cookies are small text files stored on your device when you visit a website.
                            They are widely used to make websites work efficiently and to provide information to the site owners.
                        </p>
                    </section>

                    <section>
                        <h2 class="text-lg font-semibold text-gray-800">Cookies we use</h2>
                        <p>We only use <strong>strictly necessary cookies</strong> that are essential for the website to function. We do not use any tracking, analytics, or advertising cookies.</p>

                        <div class="overflow-x-auto mt-4">
                            <table class="w-full text-sm border border-gray-200 rounded">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-2 text-left font-medium text-gray-700 border-b">Cookie</th>
                                        <th class="px-4 py-2 text-left font-medium text-gray-700 border-b">Purpose</th>
                                        <th class="px-4 py-2 text-left font-medium text-gray-700 border-b">Duration</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="border-b border-gray-100">
                                        <td class="px-4 py-2 font-mono text-xs">XSRF-TOKEN</td>
                                        <td class="px-4 py-2">Protects against cross-site request forgery attacks</td>
                                        <td class="px-4 py-2">Session</td>
                                    </tr>
                                    <tr class="border-b border-gray-100">
                                        <td class="px-4 py-2 font-mono text-xs">laravel_session</td>
                                        <td class="px-4 py-2">Maintains your authenticated session so you stay logged in</td>
                                        <td class="px-4 py-2">2 hours</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-2 font-mono text-xs">cookie_consent</td>
                                        <td class="px-4 py-2">Remembers that you have acknowledged this cookie notice</td>
                                        <td class="px-4 py-2">Persistent (localStorage)</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section>
                        <h2 class="text-lg font-semibold text-gray-800">Third-party cookies</h2>
                        <p>
                            We do not set any third-party cookies. No data is shared with external advertising or analytics services.
                        </p>
                    </section>

                    <section>
                        <h2 class="text-lg font-semibold text-gray-800">Managing cookies</h2>
                        <p>
                            You can control cookies through your browser settings. However, disabling cookies may prevent you from logging in or using certain features of this site.
                        </p>
                    </section>

                    <section>
                        <h2 class="text-lg font-semibold text-gray-800">Contact</h2>
                        <p>
                            If you have questions about our use of cookies, please contact us at
                            <a v-if="tenant?.support_email" :href="'mailto:' + tenant.support_email" class="text-indigo-600 hover:underline">{{ tenant.support_email }}</a>
                            <span v-else>our support team</span>.
                        </p>
                    </section>
                </div>
            </div>
        </div>
    </PublicLayout>
</template>
