<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    modelValue: [String, Number],
    options: {
        type: Array,
        default: () => [],
    },
    placeholder: {
        type: String,
        default: 'Select option...',
    },
    labelKey: {
        type: String,
        default: 'name',
    },
    valueKey: {
        type: String,
        default: 'id',
    },
});

const emit = defineEmits(['update:modelValue']);

const isOpen = ref(false);
const search = ref('');
const containerRef = ref(null);

const selectedOption = computed(() => {
    return props.options.find(opt => opt[props.valueKey] == props.modelValue);
});

watch(() => props.modelValue, () => {
    if (selectedOption.value) {
        search.value = selectedOption.value[props.labelKey];
    } else {
        search.value = '';
    }
}, { immediate: true });

const filteredOptions = computed(() => {
    if (!search.value || (selectedOption.value && search.value === selectedOption.value[props.labelKey])) {
        return props.options;
    }
    const q = search.value.toLowerCase();
    return props.options.filter(opt => {
        const name = String(opt[props.labelKey] || '').toLowerCase();
        const id = String(opt[props.valueKey] || '').toLowerCase();
        const pin = String(opt.device_employee_id || '').toLowerCase();
        return name.includes(q) || id.includes(q) || pin.includes(q);
    });
});

const selectOption = (opt) => {
    emit('update:modelValue', opt[props.valueKey]);
    search.value = opt[props.labelKey];
    isOpen.value = false;
};

const handleInput = () => {
    isOpen.value = true;
    if (!search.value) {
        emit('update:modelValue', '');
    }
};

const handleFocus = () => {
    isOpen.value = true;
};

const handleClickOutside = (e) => {
    if (containerRef.value && !containerRef.value.contains(e.target)) {
        isOpen.value = false;
        if (selectedOption.value) {
            search.value = selectedOption.value[props.labelKey];
        } else if (!props.modelValue) {
            search.value = '';
        }
    }
};

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});
</script>

<template>
    <div ref="containerRef" class="relative w-full">
        <div class="relative">
            <input
                type="text"
                v-model="search"
                @input="handleInput"
                @focus="handleFocus"
                :placeholder="placeholder"
                class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-purple-500 dark:focus:border-purple-600 focus:ring-purple-500 dark:focus:ring-purple-600 rounded-md shadow-sm py-2 px-3 block w-full text-sm pr-10"
            />
            <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none text-gray-400">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </div>
        </div>

        <div
            v-if="isOpen"
            class="absolute z-50 mt-1 w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-md shadow-lg max-h-60 overflow-y-auto"
        >
            <div
                v-if="filteredOptions.length === 0"
                class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400 text-center"
            >
                No matching results found
            </div>
            <ul v-else class="py-1">
                <li
                    v-for="opt in filteredOptions"
                    :key="opt[valueKey]"
                    @click="selectOption(opt)"
                    class="px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-purple-100 dark:hover:bg-purple-900/50 cursor-pointer flex justify-between items-center"
                    :class="{'bg-purple-50 dark:bg-purple-900/30 font-medium': modelValue == opt[valueKey]}"
                >
                    <span>{{ opt[labelKey] }}</span>
                    <span v-if="opt.device_employee_id" class="text-xs text-gray-400 ml-2">
                        (PIN: {{ opt.device_employee_id }})
                    </span>
                </li>
            </ul>
        </div>
    </div>
</template>
