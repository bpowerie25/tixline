<script setup>
defineProps({
    value: [String, Number],
    label: String,
});
</script>

<template>
    <div class="flex flex-col items-center justify-center h-full py-4">
        <div class="text-4xl font-bold text-gray-900">{{ value ?? '-' }}</div>
        <div v-if="label" class="mt-2 text-sm font-medium text-gray-500">{{ label }}</div>
    </div>
</template>
