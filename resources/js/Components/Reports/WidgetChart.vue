<script setup>
import BarChart from './charts/BarChart.vue';
import PieChart from './charts/PieChart.vue';
import LineChart from './charts/LineChart.vue';
import DataTable from './charts/DataTable.vue';
import NumberCard from './charts/NumberCard.vue';

defineProps({
    chartType: String,
    data: Object,
});

const chartComponents = {
    bar: BarChart,
    pie: PieChart,
    line: LineChart,
    table: DataTable,
    number: NumberCard,
};
</script>

<template>
    <component
        v-if="chartType && data && chartComponents[chartType]"
        :is="chartComponents[chartType]"
        v-bind="data"
    />
    <div v-else class="flex items-center justify-center h-full text-sm text-gray-400">
        No data available
    </div>
</template>
