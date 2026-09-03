<script setup>
import { ref, computed } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import ThemeProvider from '@/Components/ThemeProvider.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useCan } from '@/Composables/useCan';

const { can, canAny } = useCan();

const showingNavigationDropdown = ref(false);

const tenant = computed(() => usePage().props.tenant);

const hasAnySettingsPermission = computed(() => canAny([
    'teams.manage', 'labels.manage', 'workflows.manage', 'forms.manage',
    'agents.manage', 'canned-responses.manage', 'sla-policies.manage',
    'tenants.manage', 'mail.manage', 'inbound-emails.view', 'spam-filters.manage',
    'departments.manage', 'roles.manage', 'activity-logs.view', 'api-keys.manage',
]));
</script>

<template>
    <ThemeProvider>
    <div>
        <div class="min-h-screen bg-gray-100">
            <nav
                class="border-b border-gray-100 bg-white"
            >
                <!-- Primary Navigation Menu -->
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="flex h-16 justify-between">
                        <div class="flex">
                            <!-- Logo -->
                            <div class="flex shrink-0 items-center">
                                <Link :href="route('dashboard')">
                                    <img v-if="tenant?.logo_url" :src="tenant.logo_url" class="block h-9 w-auto" :alt="tenant.name" />
                                    <ApplicationLogo v-else
                                        class="block h-9 w-auto fill-current text-gray-800"
                                    />
                                </Link>
                            </div>

                            <!-- Navigation Links -->
                            <div
                                class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex"
                            >
                                <NavLink :href="route('dashboard')" :active="route().current('dashboard')">
                                    Dashboard
                                </NavLink>
                                <NavLink :href="route('tickets.index')" :active="route().current('tickets.*')">
                                    Tickets
                                </NavLink>
                                <NavLink v-if="can('customers.view')" :href="route('customers.index')" :active="route().current('customers.*')">
                                    Requesters
                                </NavLink>
                                <NavLink v-if="can('reports.view')" :href="route('reports.index')" :active="route().current('reports.*')">
                                    Reports
                                </NavLink>
                                <NavLink v-if="can('reports.custom.manage')" :href="route('custom-reports.index')" :active="route().current('custom-reports.*')">
                                    Custom Reports
                                </NavLink>
                                <NavLink v-if="can('kb.admin.view')" :href="route('kb.admin.index')" :active="route().current('kb.admin.*')">
                                    KB
                                </NavLink>
                                <NavLink :href="route('help.index')" :active="route().current('help.*')">
                                    Help
                                </NavLink>

                                <!-- Settings Dropdown -->
                                <div v-if="hasAnySettingsPermission" class="hidden sm:flex sm:items-center">
                                    <Dropdown align="left" width="48">
                                        <template #trigger>
                                            <button class="inline-flex items-center border-b-2 px-1 pt-1 text-sm font-medium leading-5 transition duration-150 ease-in-out focus:outline-none"
                                                :class="[
                                                    route().current('teams.*') || route().current('labels.*') || route().current('workflows.*') || route().current('forms.*') || route().current('canned-responses.*') || route().current('sla-policies.*') || route().current('business-hours.*') || route().current('api-keys.*') || route().current('tenants.*') || route().current('departments.*') || route().current('agents.*') || route().current('mail-config.*') || route().current('roles.*') || route().current('activity-logs.*')
                                                        ? 'border-indigo-400 text-gray-900 focus:border-indigo-700'
                                                        : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'
                                                ]"
                                            >
                                                Settings
                                                <svg class="-me-0.5 ms-1 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                                </svg>
                                            </button>
                                        </template>
                                        <template #content>
                                            <DropdownLink v-if="can('agents.manage')" :href="route('agents.index')">Agents</DropdownLink>
                                            <DropdownLink v-if="can('teams.manage')" :href="route('teams.index')">Teams</DropdownLink>
                                            <DropdownLink v-if="can('labels.manage')" :href="route('labels.index')">Labels</DropdownLink>
                                            <DropdownLink v-if="can('workflows.manage')" :href="route('workflows.index')">Workflows</DropdownLink>
                                            <DropdownLink v-if="can('forms.manage')" :href="route('forms.index')">Forms</DropdownLink>
                                            <DropdownLink v-if="can('canned-responses.manage')" :href="route('canned-responses.index')">Canned Responses</DropdownLink>
                                            <DropdownLink v-if="can('sla-policies.manage')" :href="route('sla-policies.index')">SLA Policies</DropdownLink>
                                            <DropdownLink v-if="can('sla-policies.manage')" :href="route('business-hours.index')">Business Hours</DropdownLink>
                                            <DropdownLink v-if="can('api-keys.manage')" :href="route('api-keys.index')">API Keys</DropdownLink>
                                            <DropdownLink v-if="can('tenants.manage')" :href="route('tenants.index')">Tenants</DropdownLink>
                                            <DropdownLink v-if="can('departments.manage')" :href="route('departments.index')">Departments</DropdownLink>
                                            <DropdownLink v-if="can('roles.manage')" :href="route('roles.index')">Roles</DropdownLink>
                                            <DropdownLink v-if="can('mail.manage') && $page.props.features?.byo_mail" :href="route('mail-config.index')">Mail</DropdownLink>
                                            <DropdownLink v-if="can('inbound-emails.view')" :href="route('inbound-emails.index')">Inbound Emails</DropdownLink>
                                            <DropdownLink v-if="can('spam-filters.manage')" :href="route('spam-filters.index')">Spam Filters</DropdownLink>
                                            <DropdownLink v-if="can('activity-logs.view')" :href="route('activity-logs.index')">Activity Logs</DropdownLink>
                                        </template>
                                    </Dropdown>
                                </div>
                            </div>
                        </div>

                        <div class="hidden sm:ms-6 sm:flex sm:items-center">
                            <!-- Settings Dropdown -->
                            <div class="relative ms-3">
                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <span class="inline-flex rounded-md">
                                            <button
                                                type="button"
                                                class="inline-flex items-center rounded-md border border-transparent bg-white px-3 py-2 text-sm font-medium leading-4 text-gray-500 transition duration-150 ease-in-out hover:text-gray-700 focus:outline-none"
                                            >
                                                {{ $page.props.auth.user.name }}

                                                <svg
                                                    class="-me-0.5 ms-2 h-4 w-4"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    viewBox="0 0 20 20"
                                                    fill="currentColor"
                                                >
                                                    <path
                                                        fill-rule="evenodd"
                                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                        clip-rule="evenodd"
                                                    />
                                                </svg>
                                            </button>
                                        </span>
                                    </template>

                                    <template #content>
                                        <DropdownLink
                                            :href="route('profile.edit')"
                                        >
                                            Profile
                                        </DropdownLink>
                                        <DropdownLink
                                            :href="route('logout')"
                                            method="post"
                                            as="button"
                                        >
                                            Log Out
                                        </DropdownLink>
                                    </template>
                                </Dropdown>
                            </div>
                        </div>

                        <!-- Hamburger -->
                        <div class="-me-2 flex items-center sm:hidden">
                            <button
                                @click="
                                    showingNavigationDropdown =
                                        !showingNavigationDropdown
                                "
                                class="inline-flex items-center justify-center rounded-md p-2 text-gray-400 transition duration-150 ease-in-out hover:bg-gray-100 hover:text-gray-500 focus:bg-gray-100 focus:text-gray-500 focus:outline-none"
                            >
                                <svg
                                    class="h-6 w-6"
                                    stroke="currentColor"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        :class="{
                                            hidden: showingNavigationDropdown,
                                            'inline-flex':
                                                !showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        :class="{
                                            hidden: !showingNavigationDropdown,
                                            'inline-flex':
                                                showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Responsive Navigation Menu -->
                <div
                    :class="{
                        block: showingNavigationDropdown,
                        hidden: !showingNavigationDropdown,
                    }"
                    class="sm:hidden"
                >
                    <div class="space-y-1 pb-3 pt-2">
                        <ResponsiveNavLink :href="route('dashboard')" :active="route().current('dashboard')">Dashboard</ResponsiveNavLink>
                        <ResponsiveNavLink :href="route('tickets.index')" :active="route().current('tickets.*')">Tickets</ResponsiveNavLink>
                        <ResponsiveNavLink v-if="can('customers.view')" :href="route('customers.index')" :active="route().current('customers.*')">Requesters</ResponsiveNavLink>
                        <ResponsiveNavLink v-if="can('reports.view')" :href="route('reports.index')" :active="route().current('reports.*')">Reports</ResponsiveNavLink>
                        <ResponsiveNavLink v-if="can('reports.custom.manage')" :href="route('custom-reports.index')" :active="route().current('custom-reports.*')">Custom Reports</ResponsiveNavLink>
                        <ResponsiveNavLink v-if="can('kb.admin.view')" :href="route('kb.admin.index')" :active="route().current('kb.admin.*')">Knowledge Base</ResponsiveNavLink>
                        <ResponsiveNavLink :href="route('help.index')" :active="route().current('help.*')">Help</ResponsiveNavLink>

                        <div v-if="hasAnySettingsPermission" class="border-t border-gray-200 mt-2 pt-2">
                            <div class="px-4 py-1 text-xs font-semibold uppercase text-gray-400">Settings</div>
                            <ResponsiveNavLink v-if="can('agents.manage')" :href="route('agents.index')" :active="route().current('agents.*')">Agents</ResponsiveNavLink>
                            <ResponsiveNavLink v-if="can('teams.manage')" :href="route('teams.index')" :active="route().current('teams.*')">Teams</ResponsiveNavLink>
                            <ResponsiveNavLink v-if="can('labels.manage')" :href="route('labels.index')" :active="route().current('labels.*')">Labels</ResponsiveNavLink>
                            <ResponsiveNavLink v-if="can('workflows.manage')" :href="route('workflows.index')" :active="route().current('workflows.*')">Workflows</ResponsiveNavLink>
                            <ResponsiveNavLink v-if="can('forms.manage')" :href="route('forms.index')" :active="route().current('forms.*')">Forms</ResponsiveNavLink>
                            <ResponsiveNavLink v-if="can('canned-responses.manage')" :href="route('canned-responses.index')" :active="route().current('canned-responses.*')">Canned Responses</ResponsiveNavLink>
                            <ResponsiveNavLink v-if="can('sla-policies.manage')" :href="route('sla-policies.index')" :active="route().current('sla-policies.*')">SLA Policies</ResponsiveNavLink>
                            <ResponsiveNavLink v-if="can('sla-policies.manage')" :href="route('business-hours.index')" :active="route().current('business-hours.*')">Business Hours</ResponsiveNavLink>
                            <ResponsiveNavLink v-if="can('api-keys.manage')" :href="route('api-keys.index')" :active="route().current('api-keys.*')">API Keys</ResponsiveNavLink>
                            <ResponsiveNavLink v-if="can('tenants.manage')" :href="route('tenants.index')" :active="route().current('tenants.*')">Tenants</ResponsiveNavLink>
                            <ResponsiveNavLink v-if="can('departments.manage')" :href="route('departments.index')" :active="route().current('departments.*')">Departments</ResponsiveNavLink>
                            <ResponsiveNavLink v-if="can('roles.manage')" :href="route('roles.index')" :active="route().current('roles.*')">Roles</ResponsiveNavLink>
                            <ResponsiveNavLink v-if="can('mail.manage') && $page.props.features?.byo_mail" :href="route('mail-config.index')" :active="route().current('mail-config.*')">Mail</ResponsiveNavLink>
                            <ResponsiveNavLink v-if="can('inbound-emails.view')" :href="route('inbound-emails.index')" :active="route().current('inbound-emails.*')">Inbound Emails</ResponsiveNavLink>
                            <ResponsiveNavLink v-if="can('spam-filters.manage')" :href="route('spam-filters.index')" :active="route().current('spam-filters.*')">Spam Filters</ResponsiveNavLink>
                            <ResponsiveNavLink v-if="can('activity-logs.view')" :href="route('activity-logs.index')" :active="route().current('activity-logs.*')">Activity Logs</ResponsiveNavLink>
                        </div>
                    </div>

                    <!-- Responsive Settings Options -->
                    <div
                        class="border-t border-gray-200 pb-1 pt-4"
                    >
                        <div class="px-4">
                            <div
                                class="text-base font-medium text-gray-800"
                            >
                                {{ $page.props.auth.user.name }}
                            </div>
                            <div class="text-sm font-medium text-gray-500">
                                {{ $page.props.auth.user.email }}
                            </div>
                        </div>

                        <div class="mt-3 space-y-1">
                            <ResponsiveNavLink :href="route('profile.edit')">
                                Profile
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                :href="route('logout')"
                                method="post"
                                as="button"
                            >
                                Log Out
                            </ResponsiveNavLink>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Page Heading -->
            <header
                class="bg-white shadow"
                v-if="$slots.header"
            >
                <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <!-- Page Content -->
            <main>
                <slot />
            </main>
        </div>
    </div>
    </ThemeProvider>
</template>
