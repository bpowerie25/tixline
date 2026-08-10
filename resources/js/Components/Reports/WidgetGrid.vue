<script setup>
import { ref, onMounted, watch } from 'vue';

const props = defineProps({
    layout: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['layout-updated']);

const mounted = ref(false);
const GridLayout = ref(null);
const GridItem = ref(null);

onMounted(async () => {
    try {
        const mod = await import('vue3-grid-layout-next');
        GridLayout.value = mod.GridLayout;
        GridItem.value = mod.GridItem;
    } catch (e) {
        console.warn('vue3-grid-layout-next not available:', e);
    }
    mounted.value = true;
});

const internalLayout = ref([...props.layout]);

watch(() => props.layout, (val) => {
    internalLayout.value = [...val];
}, { deep: true });

function onLayoutUpdated(newLayout) {
    emit('layout-updated', newLayout);
}
</script>

<template>
    <div>
        <template v-if="mounted && GridLayout && GridItem">
            <component
                :is="GridLayout"
                :layout="internalLayout"
                :col-num="12"
                :row-height="30"
                :is-draggable="true"
                :is-resizable="true"
                :margin="[16, 16]"
                :use-css-transforms="true"
                @layout-updated="onLayoutUpdated"
            >
                <component
                    :is="GridItem"
                    v-for="item in internalLayout"
                    :key="item.i"
                    :i="item.i"
                    :x="item.x"
                    :y="item.y"
                    :w="item.w"
                    :h="item.h"
                    :min-w="3"
                    :min-h="4"
                >
                    <slot :item="item" />
                </component>
            </component>
        </template>
        <div v-else-if="mounted" class="text-center py-12 text-gray-400">
            <p>Grid layout could not be loaded.</p>
        </div>
        <div v-else class="text-center py-12 text-gray-400">
            Loading...
        </div>
    </div>
</template>
