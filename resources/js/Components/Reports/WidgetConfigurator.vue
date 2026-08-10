<script setup>
import { reactive, watch, computed } from 'vue';
import Modal from '@/Components/Modal.vue';
import WidgetFilterPanel from './WidgetFilterPanel.vue';

const props = defineProps({
    show: Boolean,
    widget: Object,
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
