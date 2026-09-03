<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import WidgetGrid from '@/Components/Reports/WidgetGrid.vue';
import WidgetCard from '@/Components/Reports/WidgetCard.vue';
import WidgetConfigurator from '@/Components/Reports/WidgetConfigurator.vue';
import AddWidgetPanel from '@/Components/Reports/AddWidgetPanel.vue';
import ExportButton from '@/Components/Reports/ExportButton.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    report: Object,
    widgetData: Object,
    teams: Array,
    agents: Array,
    labels: Array,
    widgetTypes: Array,
});

const showAddWidget = ref(false);
const configuringWidget = ref(null);
const editingTitle = ref(false);
const titleInput = ref('');

let layoutDebounce = null;

// Build grid layout from widgets
const layout = computed(() => {
    return (props.report.widgets || []).map((w, i) => ({
        i: String(w.id),
        x: w.grid_x ?? (i % 2) * 6,
        y: w.grid_y ?? Math.floor(i / 2) * 6,
        w: w.grid_w ?? 6,
        h: w.grid_h ?? 6,
    }));
});

function widgetById(id) {
    return props.report.widgets.find(w => String(w.id) === String(id));
}

function dataForWidget(id) {
    return props.widgetData?.[id] || null;
}

// --- Layout persistence (plain fetch, no page reload) ---
function onLayoutUpdated(newLayout) {
    clearTimeout(layoutDebounce);
    layoutDebounce = setTimeout(() => {
        const layoutPayload = newLayout.map(item => ({
            id: item.i,
            grid_x: item.x,
            grid_y: item.y,
            grid_w: item.w,
            grid_h: item.h,
        }));
        fetch(route('custom-reports.layout', props.report.id), {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ widgets: layoutPayload }),
        });
    }, 500);
}

// --- Add widget ---
function addWidget(widgetType) {
    const addForm = useForm({
        widget_type: widgetType,
        chart_type: getDefaultChartType(widgetType),
        title: getDefaultTitle(widgetType),
        filters: {},
    });
    addForm.post(route('custom-reports.widgets.store', props.report.id), {
        preserveScroll: true,
    });
}

function getDefaultChartType(widgetType) {
    if (['avg_response_time', 'avg_resolution_time', 'avg_resolution_time_business'].includes(widgetType)) return 'number';
    if (['agent_performance', 'ticket_list'].includes(widgetType)) return 'table';
    if (widgetType === 'ticket_volume') return 'line';
    if (widgetType === 'sla_compliance') return 'pie';
    return 'bar';
}

function getDefaultTitle(widgetType) {
    const map = {
        tickets_by_status: 'Tickets by Status',
        tickets_by_priority: 'Tickets by Priority',
        tickets_by_team: 'Tickets by Team',
        tickets_by_agent: 'Tickets by Agent',
        tickets_by_source: 'Tickets by Source',
        tickets_by_label: 'Tickets by Label',
        ticket_volume: 'Ticket Volume Over Time',
        avg_response_time: 'Avg Response Time',
        avg_resolution_time: 'Avg Resolution Time',
        avg_resolution_time_business: 'Avg Resolution Time (Business Hours)',
        sla_compliance: 'SLA Compliance',
        agent_performance: 'Agent Performance',
        ticket_list: 'Ticket List',
    };
    return map[widgetType] || widgetType;
}

// --- Configure widget ---
function openConfigure(widget) {
    configuringWidget.value = widget;
}

async function saveWidgetConfig(config) {
    const updateForm = useForm({
        widget_type: config.widget_type,
        chart_type: config.chart_type,
        title: config.title,
        filters: config.filters,
    });

    updateForm.put(route('custom-reports.widgets.update', [props.report.id, configuringWidget.value.id]), {
        preserveScroll: true,
        onSuccess: () => {
            configuringWidget.value = null;
        },
    });
}

// --- Delete widget ---
function deleteWidget(widget) {
    if (confirm(`Delete widget "${widget.title}"?`)) {
        router.delete(route('custom-reports.widgets.destroy', [props.report.id, widget.id]), {
            preserveScroll: true,
        });
    }
}

// --- Inline title editing ---
function startEditTitle() {
    titleInput.value = props.report.name;
    editingTitle.value = true;
}

function saveTitle() {
    if (titleInput.value.trim() && titleInput.value !== props.report.name) {
        router.put(route('custom-reports.update', props.report.id), {
            name: titleInput.value.trim(),
            description: props.report.description,
        }, { preserveScroll: true });
    }
    editingTitle.value = false;
}
</script>

<template>
    <Head :title="report.name" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <template v-if="editingTitle">
                        <input
                            v-model="titleInput"
                            type="text"
                            class="text-xl font-semibold rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            @keyup.enter="saveTitle"
                            @blur="saveTitle"
                            ref="titleInputEl"
                        />
                    </template>
                    <template v-else>
                        <h2
                            class="text-xl font-semibold leading-tight text-gray-800 cursor-pointer hover:text-indigo-600"
                            @click="startEditTitle"
                            title="Click to edit"
                        >
                            {{ report.name }}
                        </h2>
                    </template>
                    <span v-if="report.description" class="text-sm text-gray-500">{{ report.description }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <button
                        @click="showAddWidget = true"
                        class="inline-flex items-center gap-1.5 rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                        Add Widget
                    </button>
                    <ExportButton :report-id="report.id" />
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div v-if="!report.widgets?.length" class="text-center py-16">
                    <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No widgets yet</h3>
                    <p class="mt-1 text-sm text-gray-500">Get started by adding a widget to your report.</p>
                    <button
                        @click="showAddWidget = true"
                        class="mt-4 inline-flex items-center gap-1.5 rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                    >
                        Add Widget
                    </button>
                </div>

                <WidgetGrid
                    v-else
                    :layout="layout"
                    @layout-updated="onLayoutUpdated"
                >
                    <template #default="{ item }">
                        <WidgetCard
                            :widget="widgetById(item.i)"
                            :data="dataForWidget(item.i)"
                            @configure="openConfigure"
                            @delete="deleteWidget"
                        />
                    </template>
                </WidgetGrid>
            </div>
        </div>

        <!-- Add Widget Panel -->
        <AddWidgetPanel
            :show="showAddWidget"
            @close="showAddWidget = false"
            @add="addWidget"
        />

        <!-- Widget Configurator -->
        <WidgetConfigurator
            :show="!!configuringWidget"
            :widget="configuringWidget"
            :widget-data="configuringWidget ? dataForWidget(configuringWidget.id) : null"
            :widget-types="widgetTypes"
            :teams="teams"
            :agents="agents"
            :labels="labels"
            @close="configuringWidget = null"
            @save="saveWidgetConfig"
        />
    </AuthenticatedLayout>
</template>
