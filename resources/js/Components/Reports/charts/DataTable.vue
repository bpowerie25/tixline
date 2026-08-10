<script setup>
defineProps({
    columns: Array,
    rows: Array,
});
</script>

<template>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead>
                <tr>
                    <th
                        v-for="col in columns" :key="col"
                        class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500"
                    >
                        {{ col }}
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <tr v-for="(row, i) in rows" :key="i">
                    <td
                        v-for="col in columns" :key="col"
                        class="px-4 py-3 text-sm text-gray-600"
                    >
                        {{ row[col] ?? '-' }}
                    </td>
                </tr>
                <tr v-if="!rows?.length">
                    <td :colspan="columns?.length || 1" class="px-4 py-6 text-center text-sm text-gray-400">
                        No data
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
