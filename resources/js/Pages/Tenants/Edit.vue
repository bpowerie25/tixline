<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

const props = defineProps({
    tenant: Object,
});

const isNew = computed(() => !props.tenant?.id);

const form = useForm({
    name: props.tenant?.name || '',
    domain: props.tenant?.domain || '',
    logo_url: props.tenant?.logo_url || '',
    favicon_url: props.tenant?.favicon_url || '',
    logo_file: null,
    favicon_file: null,
    header_height: props.tenant?.header_height || 'medium',
    primary_color: props.tenant?.primary_color || '#6366f1',
    secondary_color: props.tenant?.secondary_color || '#4f46e5',
    accent_color: props.tenant?.accent_color || '#818cf8',
    header_bg_color: props.tenant?.header_bg_color || '#ffffff',
    header_text_color: props.tenant?.header_text_color || '#111827',
    sidebar_bg_color: props.tenant?.sidebar_bg_color || '#f9fafb',
    custom_css: props.tenant?.custom_css || '',
    font_family: props.tenant?.font_family || '',
    portal_title: props.tenant?.portal_title || '',
    portal_welcome_text: props.tenant?.portal_welcome_text || '',
    support_email: props.tenant?.support_email || '',
    is_active: props.tenant?.is_active ?? true,
});

const logoPreview = computed(() => {
    if (form.logo_file) return URL.createObjectURL(form.logo_file);
    return form.logo_url || null;
});

const faviconPreview = computed(() => {
    if (form.favicon_file) return URL.createObjectURL(form.favicon_file);
    return form.favicon_url || null;
});

function handleLogoUpload(e) {
    const file = e.target.files[0];
    if (file) {
        form.logo_file = file;
        form.logo_url = '';
    }
}

function handleFaviconUpload(e) {
    const file = e.target.files[0];
    if (file) {
        form.favicon_file = file;
        form.favicon_url = '';
    }
}

function submit() {
    if (isNew.value) {
        form.post(route('tenants.store'), { forceFormData: true });
    } else {
        form.post(route('tenants.update', props.tenant.id), {
            forceFormData: true,
            headers: { 'X-HTTP-Method-Override': 'PUT' },
        });
    }
}

// Live preview style
const previewStyle = computed(() => ({
    '--color-primary': form.primary_color,
    '--color-secondary': form.secondary_color,
    '--color-accent': form.accent_color,
    '--header-bg': form.header_bg_color,
    '--header-text': form.header_text_color,
    '--sidebar-bg': form.sidebar_bg_color,
    fontFamily: form.font_family || 'inherit',
}));
</script>

<template>
    <Head :title="isNew ? 'New Tenant' : 'Edit Tenant'" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('tenants.index')" class="text-gray-400 hover:text-gray-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                </Link>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ isNew ? 'New Tenant' : 'Edit Tenant' }}</h2>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
                <form @submit.prevent="submit" class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <!-- Settings -->
                    <div class="lg:col-span-2 space-y-6">
                        <!-- General -->
                        <div class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4">
                            <h3 class="text-lg font-medium text-gray-900">General</h3>
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Tenant Name</label>
                                    <input v-model="form.name" type="text" required class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Custom Domain</label>
                                    <input v-model="form.domain" type="text" placeholder="support.client.com" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                </div>
                            </div>
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Support Email</label>
                                    <input v-model="form.support_email" type="email" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                </div>
                                <div class="flex items-end">
                                    <label class="flex items-center gap-2">
                                        <input v-model="form.is_active" type="checkbox" class="rounded text-indigo-600" />
                                        <span class="text-sm font-medium text-gray-700">Active</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Brand Colors -->
                        <div class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4">
                            <h3 class="text-lg font-medium text-gray-900">Colors</h3>
                            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                                <div v-for="[key, label] in [
                                    ['primary_color', 'Primary'],
                                    ['secondary_color', 'Secondary'],
                                    ['accent_color', 'Accent'],
                                    ['header_bg_color', 'Header BG'],
                                    ['header_text_color', 'Header Text'],
                                    ['sidebar_bg_color', 'Sidebar BG'],
                                ]" :key="key">
                                    <label class="block text-sm font-medium text-gray-700">{{ label }}</label>
                                    <div class="mt-1 relative">
                                        <input v-model="form[key]" type="color" class="absolute left-2 top-1/2 -translate-y-1/2 h-6 w-6 shrink-0 cursor-pointer rounded border-0 p-0" style="appearance: auto; -webkit-appearance: auto;" />
                                        <input v-model="form[key]" type="text" class="w-full rounded-md border-gray-300 text-sm shadow-sm font-mono pl-10" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Branding Assets -->
                        <div class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4">
                            <h3 class="text-lg font-medium text-gray-900">Branding</h3>
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Logo</label>
                                    <div v-if="logoPreview" class="mb-2 flex items-center gap-3">
                                        <img :src="logoPreview" class="h-10 rounded border border-gray-200 bg-white p-1" alt="Logo preview" />
                                        <button type="button" @click="form.logo_file = null; form.logo_url = ''" class="text-xs text-red-500 hover:text-red-700">Remove</button>
                                    </div>
                                    <input type="file" accept="image/*" @change="handleLogoUpload" class="text-sm text-gray-500 file:mr-3 file:rounded file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-sm file:text-gray-700 hover:file:bg-gray-200" />
                                    <p class="mt-1 text-xs text-gray-400">Or paste a URL:</p>
                                    <input v-model="form.logo_url" type="text" placeholder="https://..." class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                                    <p v-if="form.errors.logo_file" class="mt-1 text-sm text-red-600">{{ form.errors.logo_file }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Favicon</label>
                                    <div v-if="faviconPreview" class="mb-2 flex items-center gap-3">
                                        <img :src="faviconPreview" class="h-8 rounded border border-gray-200 bg-white p-1" alt="Favicon preview" />
                                        <button type="button" @click="form.favicon_file = null; form.favicon_url = ''" class="text-xs text-red-500 hover:text-red-700">Remove</button>
                                    </div>
                                    <input type="file" accept="image/*" @change="handleFaviconUpload" class="text-sm text-gray-500 file:mr-3 file:rounded file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-sm file:text-gray-700 hover:file:bg-gray-200" />
                                    <p class="mt-1 text-xs text-gray-400">Or paste a URL:</p>
                                    <input v-model="form.favicon_url" type="text" placeholder="https://..." class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                                    <p v-if="form.errors.favicon_file" class="mt-1 text-sm text-red-600">{{ form.errors.favicon_file }}</p>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Font Family</label>
                                    <input v-model="form.font_family" type="text" placeholder="Inter, system-ui, sans-serif" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Portal Header Height</label>
                                    <select v-model="form.header_height" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="small">Small</option>
                                        <option value="medium">Medium (default)</option>
                                        <option value="large">Large</option>
                                        <option value="xlarge">Extra Large</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Portal -->
                        <div class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4">
                            <h3 class="text-lg font-medium text-gray-900">Customer Portal</h3>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Portal Title</label>
                                <input v-model="form.portal_title" type="text" placeholder="Help Center" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Welcome Text</label>
                                <textarea v-model="form.portal_welcome_text" rows="2" placeholder="How can we help you today?" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            </div>
                        </div>

                        <!-- Custom CSS -->
                        <div class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4">
                            <h3 class="text-lg font-medium text-gray-900">Custom CSS</h3>
                            <textarea v-model="form.custom_css" rows="8" placeholder="/* Custom CSS overrides */" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono text-sm" />
                        </div>

                        <div class="flex justify-end gap-3">
                            <Link :href="route('tenants.index')" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</Link>
                            <button type="submit" :disabled="form.processing" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                                {{ isNew ? 'Create Tenant' : 'Update Tenant' }}
                            </button>
                        </div>
                    </div>

                    <!-- Live Preview -->
                    <div>
                        <div class="sticky top-8">
                            <h3 class="text-sm font-medium text-gray-500 mb-3">Live Preview</h3>
                            <div :style="previewStyle" class="rounded-lg border border-gray-200 overflow-hidden shadow-sm text-sm">
                                <!-- Preview header -->
                                <div class="px-4 py-3 flex items-center justify-between" :style="{ backgroundColor: form.header_bg_color, color: form.header_text_color }">
                                    <div class="flex items-center gap-2">
                                        <img v-if="logoPreview" :src="logoPreview" class="h-6" alt="" />
                                        <span class="font-semibold">{{ form.name || 'Tenant' }}</span>
                                    </div>
                                    <span class="text-xs opacity-60">Agent</span>
                                </div>
                                <!-- Preview nav -->
                                <div class="px-4 py-2 flex gap-3 text-xs" :style="{ backgroundColor: form.sidebar_bg_color }">
                                    <span :style="{ color: form.primary_color }" class="font-medium">Dashboard</span>
                                    <span class="text-gray-400">Tickets</span>
                                    <span class="text-gray-400">Reports</span>
                                </div>
                                <!-- Preview content -->
                                <div class="p-4 bg-gray-50">
                                    <div class="rounded px-3 py-2 text-xs" :style="{ backgroundColor: form.primary_color, color: '#fff' }">
                                        Primary Button
                                    </div>
                                    <div class="mt-2 rounded px-3 py-2 text-xs border" :style="{ borderColor: form.accent_color, color: form.accent_color }">
                                        Accent Outline
                                    </div>
                                    <div class="mt-2 rounded px-3 py-1.5 text-xs" :style="{ backgroundColor: form.secondary_color + '22', color: form.secondary_color }">
                                        Secondary Badge
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
