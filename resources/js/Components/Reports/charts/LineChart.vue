<script setup>
import { computed } from 'vue';

const props = defineProps({
    labels: Array,
    values: Array,
});

const padding = 20;
const width = 400;
const height = 200;

const maxVal = computed(() => Math.max(...(props.values || []), 1));

const points = computed(() => {
    if (!props.values?.length) return [];
    const count = props.values.length;
    return props.values.map((val, i) => ({
        x: padding + (i / Math.max(count - 1, 1)) * (width - padding * 2),
        y: padding + (1 - val / maxVal.value) * (height - padding * 2),
        value: val,
        label: props.labels?.[i] || '',
    }));
});

const polyline = computed(() => points.value.map(p => `${p.x},${p.y}`).join(' '));

const areaPath = computed(() => {
    if (!points.value.length) return '';
    const first = points.value[0];
    const last = points.value[points.value.length - 1];
    const linePoints = points.value.map(p => `L${p.x},${p.y}`).join(' ');
    return `M${first.x},${height - padding} ${linePoints} L${last.x},${height - padding} Z`;
});
</script>

<template>
    <div>
        <svg :viewBox="`0 0 ${width} ${height}`" class="w-full h-auto" preserveAspectRatio="xMidYMid meet">
            <!-- Grid lines -->
            <line
                v-for="i in 4" :key="'grid-' + i"
                :x1="padding" :x2="width - padding"
                :y1="padding + ((i - 1) / 3) * (height - padding * 2)"
                :y2="padding + ((i - 1) / 3) * (height - padding * 2)"
                stroke="#f3f4f6" stroke-width="1"
            />
            <!-- Area fill -->
            <path :d="areaPath" fill="#6366f1" fill-opacity="0.1" />
            <!-- Line -->
            <polyline
                :points="polyline"
                fill="none" stroke="#6366f1" stroke-width="2" stroke-linejoin="round"
            />
            <!-- Dots -->
            <circle
                v-for="(pt, i) in points" :key="i"
                :cx="pt.x" :cy="pt.y" r="3"
                fill="#6366f1"
            >
                <title>{{ pt.label }}: {{ pt.value }}</title>
            </circle>
        </svg>
        <div v-if="labels?.length" class="flex justify-between mt-1 text-xs text-gray-400 px-1">
            <span>{{ labels[0] }}</span>
            <span>{{ labels[labels.length - 1] }}</span>
        </div>
        <div v-if="!values?.length" class="text-sm text-gray-400 text-center py-4">No data</div>
    </div>
</template>
