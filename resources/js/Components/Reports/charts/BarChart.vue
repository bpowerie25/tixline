<script setup>
import { computed } from 'vue';

const props = defineProps({
    labels: Array,
    values: Array,
    colorMap: Object,
});

const defaultColors = ['#6366f1', '#ec4899', '#f59e0b', '#10b981', '#06b6d4', '#8b5cf6', '#ef4444', '#f97316', '#22c55e', '#0ea5e9'];

const maxValue = computed(() => Math.max(...(props.values || []), 1));

const bars = computed(() => {
    if (!props.labels || !props.values) return [];
    return props.labels.map((label, i) => ({
        label,
        value: props.values[i] || 0,
        pct: ((props.values[i] || 0) / maxValue.value) * 100,
        color: props.colorMap?.[label] || defaultColors[i % defaultColors.length],
    }));
});
</script>

<template>
    <div class="space-y-3">
        <div v-for="bar in bars" :key="bar.label" class="flex items-center gap-3">
            <span class="w-36 text-sm text-gray-700 truncate" :title="bar.label">{{ bar.label }}</span>
            <div class="flex-1 h-6 bg-gray-100 rounded-full overflow-hidden">
                <div
                    class="h-full rounded-full transition-all duration-300"
                    :style="{ width: bar.pct + '%', backgroundColor: bar.color, minWidth: bar.value > 0 ? '4px' : '0' }"
                />
            </div>
            <span class="w-10 text-sm text-gray-600 text-right">{{ bar.value }}</span>
        </div>
        <div v-if="!bars.length" class="text-sm text-gray-400 text-center py-4">No data</div>
    </div>
</template>
