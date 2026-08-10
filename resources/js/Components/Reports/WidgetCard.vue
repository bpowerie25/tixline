<script setup>
import WidgetChart from './WidgetChart.vue';

defineProps({
    widget: Object,
    data: Object,
});

defineEmits(['configure', 'delete']);

const chartIcons = {
    bar: 'M4 6h4v14H4zm6-4h4v18h-4zm6 8h4v10h-4z',
    pie: 'M11 2v9H2a9 9 0 0 0 9 9 9 9 0 0 0 9-9A9 9 0 0 0 11 2zm2 0a9 9 0 0 1 7 9h-7V2z',
    line: 'M3 17l6-6 4 4 8-8',
    table: 'M3 3h18v18H3zM3 9h18M3 15h18M9 3v18M15 3v18',
    number: 'M7 20V4h4v16H7zm6-10V4h4v6h-4z',
};
</script>

<template>
    <div class="flex flex-col h-full bg-white rounded-lg shadow overflow-hidden">
        <!-- Header -->
        <div class="flex items-center justify-between px-4 py-2 border-b border-gray-100 bg-gray-50 shrink-0">
            <div class="flex items-center gap-2 min-w-0">
                <svg v-if="chartIcons[widget.chart_type]" class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" :d="chartIcons[widget.chart_type]" />
                </svg>
                <span class="text-sm font-medium text-gray-700 truncate">{{ widget.title }}</span>
            </div>
            <div class="flex items-center gap-1 shrink-0">
                <button @click="$emit('configure', widget)" class="p-1 text-gray-400 hover:text-gray-600 rounded" title="Configure">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.573-1.066z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </button>
                <button @click="$emit('delete', widget)" class="p-1 text-gray-400 hover:text-red-500 rounded" title="Delete">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- Body -->
        <div class="flex-1 p-4 overflow-auto">
            <WidgetChart :chart-type="widget.chart_type" :data="data" />
        </div>
    </div>
</template>
