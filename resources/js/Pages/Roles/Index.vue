<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';

const props = defineProps({
    roles: Array,
    permissions: Object,
});

const showForm = ref(false);
const editingRole = ref(null);

const form = useForm({
    display_name: '',
    name: '',
    description: '',
    permissions: [],
});

function slugify(text) {
    return text.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');
}

watch(() => form.display_name, (val) => {
    if (!editingRole.value || !editingRole.value.is_system) {
        form.name = slugify(val);
    }
});

function openCreate() {
    editingRole.value = null;
    form.reset();
    form.permissions = [];
    showForm.value = true;
}

function openEdit(role) {
    editingRole.value = role;
    form.display_name = role.display_name;
    form.name = role.name;
    form.description = role.description || '';
    form.permissions = (role.permissions || []).map(p => p.id);
    showForm.value = true;
}

function submit() {
    if (editingRole.value) {
        form.put(route('roles.update', editingRole.value.id), {
            onSuccess: () => { showForm.value = false; },
        });
    } else {
        form.post(route('roles.store'), {
            onSuccess: () => { showForm.value = false; form.reset(); },
        });
    }
}

function deleteRole(role) {
    if (confirm(`Delete role "${role.display_name}"? Users with this role will need to be reassigned.`)) {
        router.delete(route('roles.destroy', role.id));
    }
}

function togglePermission(permissionId) {
    const index = form.permissions.indexOf(permissionId);
    if (index === -1) {
        form.permissions.push(permissionId);
    } else {
        form.permissions.splice(index, 1);
    }
}

function groupPermissionIds(group) {
    return (props.permissions[group] || []).map(p => p.id);
}

function isGroupAllSelected(group) {
    const ids = groupPermissionIds(group);
    return ids.length > 0 && ids.every(id => form.permissions.includes(id));
}

function isGroupPartial(group) {
    const ids = groupPermissionIds(group);
    const selected = ids.filter(id => form.permissions.includes(id));
    return selected.length > 0 && selected.length < ids.length;
}

function toggleGroup(group) {
    const ids = groupPermissionIds(group);
    if (isGroupAllSelected(group)) {
        form.permissions = form.permissions.filter(id => !ids.includes(id));
    } else {
        const toAdd = ids.filter(id => !form.permissions.includes(id));
        form.permissions.push(...toAdd);
    }
}

const permissionGroups = computed(() => Object.keys(props.permissions || {}));
</script>

<template>
    <Head title="Roles" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Roles</h2>
                <button @click="openCreate" class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    Create Role
                </button>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
                <!-- Flash -->
                <div v-if="$page.props.flash?.success" class="mb-4 rounded-md bg-green-50 border border-green-200 p-4">
                    <p class="text-sm text-green-800">{{ $page.props.flash.success }}</p>
                </div>
                <div v-if="$page.props.flash?.error" class="mb-4 rounded-md bg-red-50 border border-red-200 p-4">
                    <p class="text-sm text-red-800">{{ $page.props.flash.error }}</p>
                </div>

                <!-- Form -->
                <div v-if="showForm" class="mb-6 overflow-hidden bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium mb-6">{{ editingRole ? 'Edit' : 'Create' }} Role</h3>
                    <form @submit.prevent="submit" class="space-y-6">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Display Name</label>
                                <input v-model="form.display_name" type="text" required class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                <p v-if="form.errors.display_name" class="mt-1 text-sm text-red-600">{{ form.errors.display_name }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Slug</label>
                                <input v-model="form.name" type="text" required :readonly="editingRole?.is_system" :class="editingRole?.is_system ? 'bg-gray-100 cursor-not-allowed' : ''" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Description</label>
                            <textarea v-model="form.description" rows="2" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            <p v-if="form.errors.description" class="mt-1 text-sm text-red-600">{{ form.errors.description }}</p>
                        </div>

                        <!-- Permissions Grid -->
                        <div>
                            <h4 class="text-sm font-medium text-gray-700 mb-3">Permissions</h4>
                            <div class="space-y-4">
                                <div v-for="group in permissionGroups" :key="group" class="rounded-lg border border-gray-200 p-4">
                                    <div class="flex items-center gap-3 mb-3">
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                :checked="isGroupAllSelected(group)"
                                                :indeterminate="isGroupPartial(group)"
                                                @change="toggleGroup(group)"
                                                class="rounded text-indigo-600"
                                            />
                                            <span class="text-sm font-semibold text-gray-800 capitalize">{{ group }}</span>
                                        </label>
                                        <span class="text-xs text-gray-400">Select All</span>
                                    </div>
                                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3 ml-6">
                                        <label v-for="permission in permissions[group]" :key="permission.id" class="flex items-center gap-2 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                :checked="form.permissions.includes(permission.id)"
                                                @change="togglePermission(permission.id)"
                                                class="rounded text-indigo-600"
                                            />
                                            <span class="text-sm text-gray-700">{{ permission.display_name }}</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex gap-3 pt-2">
                            <button type="submit" :disabled="form.processing" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                                {{ editingRole ? 'Update' : 'Create' }}
                            </button>
                            <button type="button" @click="showForm = false" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Roles List -->
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="divide-y divide-gray-200">
                        <div v-for="role in roles" :key="role.id" class="flex items-center justify-between px-6 py-4">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="font-medium text-gray-900">{{ role.display_name }}</span>
                                    <span v-if="role.is_system" class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">System</span>
                                </div>
                                <div class="text-sm text-gray-500">
                                    <span v-if="role.description">{{ role.description }} &middot; </span>
                                    {{ role.users_count }} {{ role.users_count === 1 ? 'user' : 'users' }}
                                    &middot; {{ (role.permissions || []).length }} permissions
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <button @click="openEdit(role)" class="text-sm text-indigo-600 hover:text-indigo-800">Edit</button>
                                <button
                                    @click="deleteRole(role)"
                                    :disabled="role.is_system"
                                    class="text-sm"
                                    :class="role.is_system ? 'text-gray-300 cursor-not-allowed' : 'text-red-600 hover:text-red-800'"
                                >Delete</button>
                            </div>
                        </div>
                        <div v-if="!roles.length" class="px-6 py-8 text-center text-gray-500">No roles yet. Click "Create Role" to create one.</div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
