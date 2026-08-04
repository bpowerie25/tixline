<script setup>
const props = defineProps({
    group: Object,
    fields: Array,
    operators: Array,
    depth: { type: Number, default: 0 },
});

const emit = defineEmits(['update:group', 'remove']);

function updateMatch(value) {
    emit('update:group', { ...props.group, match: value });
}

function updateRule(index, updated) {
    const rules = [...props.group.rules];
    rules[index] = updated;
    emit('update:group', { ...props.group, rules });
}

function removeRule(index) {
    const rules = props.group.rules.filter((_, i) => i !== index);
    emit('update:group', { ...props.group, rules });
}

function addRule() {
    const rules = [...props.group.rules, { field: 'subject', operator: 'contains', value: '' }];
    emit('update:group', { ...props.group, rules });
}

function addGroup(match) {
    const rules = [...props.group.rules, { match, rules: [{ field: 'subject', operator: 'contains', value: '' }] }];
    emit('update:group', { ...props.group, rules });
}

function isGroup(rule) {
    return rule.match !== undefined;
}

const borderColors = ['border-indigo-300', 'border-amber-300', 'border-emerald-300', 'border-rose-300'];
const bgColors = ['bg-indigo-50/50', 'bg-amber-50/50', 'bg-emerald-50/50', 'bg-rose-50/50'];
</script>

<template>
    <div
        :class="[
            'rounded-lg border-2 p-4',
            borderColors[depth % borderColors.length],
            bgColors[depth % bgColors.length],
        ]"
    >
        <!-- Group header -->
        <div class="mb-3 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="text-xs font-semibold uppercase text-gray-500">Match</span>
                <button
                    type="button"
                    @click="updateMatch('all')"
                    :class="[
                        'rounded px-2.5 py-1 text-xs font-semibold transition',
                        group.match === 'all' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-600 border border-gray-300 hover:bg-gray-50'
                    ]"
                >AND</button>
                <button
                    type="button"
                    @click="updateMatch('any')"
                    :class="[
                        'rounded px-2.5 py-1 text-xs font-semibold transition',
                        group.match === 'any' ? 'bg-amber-500 text-white' : 'bg-white text-gray-600 border border-gray-300 hover:bg-gray-50'
                    ]"
                >OR</button>
            </div>
            <button v-if="depth > 0" type="button" @click="$emit('remove')" class="text-xs text-red-500 hover:text-red-700">Remove group</button>
        </div>

        <!-- Rules -->
        <div class="space-y-2">
            <template v-for="(rule, i) in group.rules" :key="i">
                <!-- Connector label between rules -->
                <div v-if="i > 0" class="flex justify-center">
                    <span :class="[
                        'rounded-full px-3 py-0.5 text-xs font-semibold',
                        group.match === 'all' ? 'bg-indigo-100 text-indigo-700' : 'bg-amber-100 text-amber-700'
                    ]">
                        {{ group.match === 'all' ? 'AND' : 'OR' }}
                    </span>
                </div>

                <!-- Nested group -->
                <ConditionGroup
                    v-if="isGroup(rule)"
                    :group="rule"
                    :fields="fields"
                    :operators="operators"
                    :depth="depth + 1"
                    @update:group="updateRule(i, $event)"
                    @remove="removeRule(i)"
                />

                <!-- Single rule -->
                <div v-else class="flex items-center gap-2 rounded-md bg-white p-2 shadow-sm">
                    <select
                        :value="rule.field"
                        @change="updateRule(i, { ...rule, field: $event.target.value })"
                        class="rounded-md border-gray-300 text-sm shadow-sm"
                    >
                        <option v-for="f in fields" :key="f.value" :value="f.value">{{ f.label }}</option>
                    </select>
                    <select
                        :value="rule.operator"
                        @change="updateRule(i, { ...rule, operator: $event.target.value })"
                        class="rounded-md border-gray-300 text-sm shadow-sm"
                    >
                        <option v-for="op in operators" :key="op.value" :value="op.value">{{ op.label }}</option>
                    </select>
                    <input
                        v-if="!['is_empty', 'is_not_empty'].includes(rule.operator)"
                        :value="rule.value"
                        @input="updateRule(i, { ...rule, value: $event.target.value })"
                        type="text"
                        placeholder="Value"
                        class="flex-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                    <button type="button" @click="removeRule(i)" class="text-red-400 hover:text-red-600">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
            </template>
        </div>

        <!-- Add buttons -->
        <div class="mt-3 flex gap-2">
            <button type="button" @click="addRule" class="rounded border border-gray-300 bg-white px-3 py-1 text-xs font-medium text-gray-600 hover:bg-gray-50">
                + Condition
            </button>
            <button type="button" @click="addGroup('all')" class="rounded border border-indigo-300 bg-white px-3 py-1 text-xs font-medium text-indigo-600 hover:bg-indigo-50">
                + AND Group
            </button>
            <button type="button" @click="addGroup('any')" class="rounded border border-amber-300 bg-white px-3 py-1 text-xs font-medium text-amber-600 hover:bg-amber-50">
                + OR Group
            </button>
        </div>
    </div>
</template>
