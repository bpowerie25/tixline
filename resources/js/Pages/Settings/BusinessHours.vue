<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    schedule: Object,
    defaults: Object,
    timezones: Array,
});

const DAY_NAMES = {
    1: 'Monday',
    2: 'Tuesday',
    3: 'Wednesday',
    4: 'Thursday',
    5: 'Friday',
    6: 'Saturday',
    7: 'Sunday',
};

const configured = computed(() => props.schedule !== null);

const form = useForm({
    timezone: props.schedule?.timezone ?? props.defaults.timezone,
    days: structuredClone(props.schedule?.days ?? props.defaults.days),
    holidays: structuredClone(props.schedule?.holidays ?? []),
});

const newHoliday = ref({ date: '', name: '' });

function isOpen(day) {
    return (form.days[day] ?? []).length > 0;
}

function toggleDay(day) {
    form.days[day] = isOpen(day) ? [] : [{ start: '09:00', end: '17:00' }];
}

function addWindow(day) {
    form.days[day].push({ start: '13:00', end: '17:00' });
}

function removeWindow(day, index) {
    form.days[day].splice(index, 1);
}

function addHoliday() {
    if (!newHoliday.value.date) return;
    form.holidays.push({ ...newHoliday.value });
    form.holidays.sort((a, b) => a.date.localeCompare(b.date));
    newHoliday.value = { date: '', name: '' };
}

function removeHoliday(index) {
    form.holidays.splice(index, 1);
}

function submit() {
    form.post(route('business-hours.store'), { preserveScroll: true });
}

function clearSchedule() {
    if (confirm('Remove business hours? SLA targets will go back to running around the clock, including nights and weekends.')) {
        router.delete(route('business-hours.destroy'), { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Business Hours" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Business Hours</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-4xl sm:px-6 lg:px-8 space-y-6">
                <div v-if="$page.props.flash?.success" class="rounded-md bg-green-50 border border-green-200 p-4">
                    <p class="text-sm text-green-800">{{ $page.props.flash.success }}</p>
                </div>

                <div v-if="!configured" class="rounded-md bg-amber-50 border border-amber-200 p-4">
                    <p class="text-sm text-amber-800">
                        No business hours are configured, so SLA clocks run continuously — a 4 hour
                        first-response target set at 6pm on Friday is breached by Saturday morning.
                        Set your opening hours below to have SLA targets count only working time.
                    </p>
                </div>

                <form @submit.prevent="submit" class="space-y-6">
                    <div class="bg-white shadow-sm sm:rounded-lg p-6">
                        <label class="block text-sm font-medium text-gray-700">Timezone</label>
                        <select v-model="form.timezone" class="mt-1 w-full max-w-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option v-for="tz in timezones" :key="tz" :value="tz">{{ tz }}</option>
                        </select>
                        <p class="mt-1 text-sm text-gray-500">Opening times below are interpreted in this timezone.</p>
                        <p v-if="form.errors.timezone" class="mt-1 text-sm text-red-600">{{ form.errors.timezone }}</p>
                    </div>

                    <div class="bg-white shadow-sm sm:rounded-lg p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-1">Opening Hours</h3>
                        <p class="text-sm text-gray-500 mb-4">Add a second window for a day that closes over lunch.</p>

                        <p v-if="form.errors.days" class="mb-4 rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-700">{{ form.errors.days }}</p>

                        <div class="divide-y divide-gray-200">
                            <div v-for="(name, day) in DAY_NAMES" :key="day" class="py-4">
                                <div class="flex items-start gap-4">
                                    <label class="flex w-40 shrink-0 items-center gap-2 pt-2">
                                        <input type="checkbox" :checked="isOpen(day)" @change="toggleDay(day)" class="rounded text-indigo-600" />
                                        <span class="text-sm font-medium text-gray-700">{{ name }}</span>
                                    </label>

                                    <div v-if="isOpen(day)" class="flex-1 space-y-2">
                                        <div v-for="(window, index) in form.days[day]" :key="index" class="flex items-center gap-2">
                                            <input v-model="window.start" type="time" required class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                            <span class="text-sm text-gray-500">to</span>
                                            <input v-model="window.end" type="time" required class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                            <button v-if="form.days[day].length > 1" type="button" @click="removeWindow(day, index)" class="text-sm text-red-600 hover:text-red-800">Remove</button>
                                        </div>
                                        <button type="button" @click="addWindow(day)" class="text-sm text-indigo-600 hover:text-indigo-800">+ Add window</button>
                                    </div>
                                    <div v-else class="flex-1 pt-2 text-sm text-gray-400">Closed</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white shadow-sm sm:rounded-lg p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-1">Holidays</h3>
                        <p class="text-sm text-gray-500 mb-4">SLA clocks are paused for the whole of these days.</p>

                        <div class="flex flex-wrap items-end gap-3 mb-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Date</label>
                                <input v-model="newHoliday.date" type="date" class="mt-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            </div>
                            <div class="flex-1">
                                <label class="block text-sm font-medium text-gray-700">Name (optional)</label>
                                <input v-model="newHoliday.name" type="text" placeholder="Christmas Day" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            </div>
                            <button type="button" @click="addHoliday" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Add</button>
                        </div>

                        <div class="divide-y divide-gray-200">
                            <div v-for="(holiday, index) in form.holidays" :key="index" class="flex items-center justify-between py-2">
                                <div class="text-sm">
                                    <span class="font-medium text-gray-900">{{ holiday.date }}</span>
                                    <span v-if="holiday.name" class="ml-2 text-gray-500">{{ holiday.name }}</span>
                                </div>
                                <button type="button" @click="removeHoliday(index)" class="text-sm text-red-600 hover:text-red-800">Remove</button>
                            </div>
                            <p v-if="!form.holidays.length" class="py-2 text-sm text-gray-500">No holidays added.</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <button type="submit" :disabled="form.processing" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                            Save Business Hours
                        </button>
                        <button v-if="configured" type="button" @click="clearSchedule" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            Remove
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
