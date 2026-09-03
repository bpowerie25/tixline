<script setup>
import { reactive, watch, computed } from 'vue';
import Modal from '@/Components/Modal.vue';
import WidgetFilterPanel from './WidgetFilterPanel.vue';

const props = defineProps({
    show: Boolean,
    widget: Object,
    widgetData: Object,
    widgetTypes: { type: Array, default: () => [] },
    teams: { type: Array, default: () => [] },
    agents: { type: Array, default: () => [] },
    labels: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'save']);

const form = reactive({
    title: '',
    widget_type: '',
    chart_type: 'bar',
    filters: {},
});

watch(() => props.widget, (w) => {
    if (w) {
        form.title = w.title || '';
        form.widget_type = w.widget_type || '';
        form.chart_type = w.chart_type || 'bar';
        form.filters = w.filters ? JSON.parse(JSON.stringify(w.filters)) : {};
    }
}, { immediate: true });

const defaultColors = ['#6366f1', '#ec4899', '#f59e0b', '#10b981', '#06b6d4', '#8b5cf6', '#ef4444', '#f97316', '#22c55e', '#0ea5e9'];

const colorLabels = computed(() => {
    if (!props.widgetData?.labels) return [];
    return props.widgetData.labels.map((label, i) => ({
        label,
        color: form.filters.color_overrides?.[label]
            || props.widgetData.colorMap?.[label]
            || defaultColors[i % defaultColors.length],
    }));
});

const supportsColors = computed(() => {
    return ['bar', 'pie'].includes(form.chart_type) && colorLabels.value.length > 0;
});

function setColorOverride(label, color) {
    if (!form.filters.color_overrides) {
        form.filters.color_overrides = {};
    }
    form.filters.color_overrides[label] = color;
}

function resetColors() {
    delete form.filters.color_overrides;
}

const validChartTypes = computed(() => {
    const meta = props.widgetTypes.find(t => t.type === form.widget_type);
    if (meta?.chart_types) return meta.chart_types;
    // Default chart types based on widget type
    if (['avg_response_time', 'avg_resolution_time', 'avg_resolution_time_business', 'sla_compliance'].includes(form.widget_type)) {
        return ['number', 'bar', 'line'];
    }
    if (form.widget_type === 'agent_performance') {
        return ['table', 'bar'];
    }
    if (form.widget_type === 'ticket_volume') {
        return ['line', 'bar'];
    }
    return ['bar', 'pie', 'line', 'table', 'number'];
});

const chartTypeLabels = {
    bar: 'Bar Chart',
    pie: 'Pie Chart',
    line: 'Line Chart',
    table: 'Table',
    number: 'Number',
};

function save() {
    emit('save', { ...form });
    emit('close');
}
</script>

<template>
    <Modal :show="show" max-width="lg" @close="$emit('close')">
        <div class="p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Configure Widget</h3>

            <div class="space-y-4">
                <!-- Title -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Title</label>
                    <input
                        v-model="form.title"
                        type="text"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        placeholder="Widget title"
                    />
                </div>

                <!-- Widget Type -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Widget Type</label>
                    <select
                        v-model="form.widget_type"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        <option value="" disabled>Select a type...</option>
                        <option v-for="wt in widgetTypes" :key="wt.type" :value="wt.type">
                            {{ wt.name || wt.type }}
                        </option>
                    </select>
                </div>

                <!-- Chart Type -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Chart Type</label>
                    <select
                        v-model="form.chart_type"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        <option v-for="ct in validChartTypes" :key="ct" :value="ct">
                            {{ chartTypeLabels[ct] || ct }}
                        </option>
                    </select>
                </div>

                <!-- Filters -->
                <div class="border-t border-gray-200 pt-4">
                    <h4 class="text-sm font-medium text-gray-700 mb-3">Filters</h4>
                    <WidgetFilterPanel
                        :filters="form.filters"
                        :teams="teams"
                        :agents="agents"
                        :labels="labels"
                        @update:filters="form.filters = $event"
                    />
                </div>

                <!-- Colors -->
                <div v-if="supportsColors" class="border-t border-gray-200 pt-4">
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="text-sm font-medium text-gray-700">Colors</h4>
                        <button
                            v-if="form.filters.color_overrides"
                            type="button"
                            @click="resetColors"
                            class="text-xs text-gray-500 hover:text-gray-700"
                        >
                            Reset to defaults
                        </button>
                    </div>
                    <div class="space-y-2">
                        <div v-for="item in colorLabels" :key="item.label" class="flex items-center gap-3">
                            <label :for="'color-' + item.label" class="relative shrink-0 cursor-pointer">
                                <span
                                    class="block w-8 h-8 rounded-md border border-gray-200 shadow-sm"
                                    :style="{ backgroundColor: item.color }"
                                />
                                <input
                                    :id="'color-' + item.label"
                                    type="color"
                                    :value="item.color"
                                    @input="setColorOverride(item.label, $event.target.value)"
                                    class="absolute inset-0 opacity-0 w-full h-full cursor-pointer"
                                />
                            </label>
                            <span class="text-sm text-gray-700 capitalize">{{ item.label }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex gap-3 pt-6">
                <button
                    @click="save"
                    class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                >
                    Save
                </button>
                <button
                    @click="$emit('close')"
                    class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                >
                    Cancel
                </button>
            </div>
        </div>
    </Modal>
</template>
