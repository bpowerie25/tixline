<script setup>
import { computed } from 'vue';

const props = defineProps({
    labels: Array,
    values: Array,
    colorMap: Object,
});

const defaultColors = ['#6366f1', '#ec4899', '#f59e0b', '#10b981', '#06b6d4', '#8b5cf6', '#ef4444', '#f97316', '#22c55e', '#0ea5e9'];

function colorFor(label, index) {
    return props.colorMap?.[label] || defaultColors[index % defaultColors.length];
}

const total = computed(() => (props.values || []).reduce((a, b) => a + b, 0) || 1);

const gradient = computed(() => {
    if (!props.values?.length) return 'conic-gradient(#e5e7eb 0% 100%)';
    let cumulative = 0;
    const stops = [];
    props.values.forEach((val, i) => {
        const start = cumulative;
        cumulative += (val / total.value) * 100;
        stops.push(`${colorFor(props.labels[i], i)} ${start}% ${cumulative}%`);
    });
    return `conic-gradient(${stops.join(', ')})`;
});

const legend = computed(() => {
    if (!props.labels || !props.values) return [];
    return props.labels.map((label, i) => ({
        label,
        value: props.values[i] || 0,
        color: colorFor(label, i),
        pct: Math.round(((props.values[i] || 0) / total.value) * 100),
    }));
});
</script>

<template>
    <div class="flex items-center gap-6">
        <div
            class="w-32 h-32 rounded-full shrink-0"
            :style="{
                background: gradient,
                mask: 'radial-gradient(circle at center, transparent 40%, black 41%)',
                WebkitMask: 'radial-gradient(circle at center, transparent 40%, black 41%)',
            }"
        />
        <div class="space-y-1.5 min-w-0">
            <div v-for="item in legend" :key="item.label" class="flex items-center gap-2 text-sm">
                <span class="w-3 h-3 rounded-full shrink-0" :style="{ backgroundColor: item.color }" />
                <span class="text-gray-700 truncate">{{ item.label }}</span>
                <span class="text-gray-400 ml-auto shrink-0">{{ item.value }} ({{ item.pct }}%)</span>
            </div>
            <div v-if="!legend.length" class="text-sm text-gray-400">No data</div>
        </div>
    </div>
</template>
