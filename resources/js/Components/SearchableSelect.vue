<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { __ } from '@/Composables/useTranslations.js';

const props = defineProps({
    modelValue: [String, Number, Array],
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
    multiple: {
        type: Boolean,
        default: false,
    }
});

const emit = defineEmits(['update:modelValue']);

const isOpen = ref(false);
const search = ref('');
const containerRef = ref(null);
const searchInputRef = ref(null);

// For Single Select
const selectedOption = computed(() => {
    if (props.multiple) return null;
    return props.options.find(opt => opt[props.valueKey] == props.modelValue);
});

// For Multi Select
const selectedOptions = computed(() => {
    if (!props.multiple) return [];
    const vals = Array.isArray(props.modelValue) ? props.modelValue : (props.modelValue ? [props.modelValue] : []);
    if (vals.includes('all')) {
        return props.options.filter(opt => opt[props.valueKey] !== 'all');
    }
    return props.options.filter(opt => vals.some(v => v == opt[props.valueKey]));
});

const isSelected = (opt) => {
    if (!props.multiple) {
        return props.modelValue == opt[props.valueKey];
    }
    const vals = Array.isArray(props.modelValue) ? props.modelValue : (props.modelValue ? [props.modelValue] : []);
    if (opt[props.valueKey] === 'all') {
        return vals.includes('all');
    }
    if (vals.includes('all')) return true;
    return vals.some(v => v == opt[props.valueKey]);
};

watch(() => props.modelValue, () => {
    if (!props.multiple) {
        if (selectedOption.value) {
            search.value = selectedOption.value[props.labelKey];
        } else {
            search.value = '';
        }
    }
}, { immediate: true });

const filteredOptions = computed(() => {
    if (!search.value || (!props.multiple && selectedOption.value && search.value === selectedOption.value[props.labelKey])) {
        return props.options;
    }
    const q = search.value.toLowerCase();
    return props.options.filter(opt => {
        const name = String(opt[props.labelKey] || '').toLowerCase();
        const id = String(opt[props.valueKey] || '').toLowerCase();
        const pin = String(opt.device_employee_id || '').toLowerCase();
        const code = String(opt.employee_code || '').toLowerCase();
        return name.includes(q) || id.includes(q) || pin.includes(q) || code.includes(q);
    });
});

const selectOption = (opt) => {
    if (!props.multiple) {
        emit('update:modelValue', opt[props.valueKey]);
        search.value = opt[props.labelKey];
        isOpen.value = false;
    } else {
        let current = Array.isArray(props.modelValue) ? [...props.modelValue] : (props.modelValue ? [props.modelValue] : []);
        const val = opt[props.valueKey];

        if (val === 'all') {
            if (current.includes('all')) {
                current = [];
            } else {
                current = ['all'];
            }
        } else {
            if (current.includes('all')) {
                current = props.options.map(o => o[props.valueKey]).filter(oVal => oVal !== 'all' && oVal !== val);
            } else {
                const idx = current.findIndex(v => v == val);
                if (idx > -1) {
                    current.splice(idx, 1);
                } else {
                    current.push(val);
                }
            }
        }
        emit('update:modelValue', current);
        search.value = '';
    }
};

const removeTag = (opt) => {
    if (!props.multiple) return;
    let current = Array.isArray(props.modelValue) ? [...props.modelValue] : [];
    const val = opt[props.valueKey];
    if (val === 'all' || current.includes('all')) {
        current = props.options
            .map(o => o[props.valueKey])
            .filter(oVal => oVal !== 'all' && oVal !== val);
    } else {
        current = current.filter(v => v != val);
    }
    emit('update:modelValue', current);
};

const selectAll = () => {
    if (!props.multiple) return;
    const current = Array.isArray(props.modelValue) ? props.modelValue : [];
    if (current.includes('all') || current.length === props.options.filter(o => o.id !== 'all').length) {
        emit('update:modelValue', []);
    } else {
        emit('update:modelValue', ['all']);
    }
};

const handleInput = () => {
    isOpen.value = true;
    if (!props.multiple && !search.value) {
        emit('update:modelValue', '');
    }
};

const handleFocus = () => {
    isOpen.value = true;
};

const handleClickOutside = (e) => {
    if (containerRef.value && !containerRef.value.contains(e.target)) {
        isOpen.value = false;
        if (!props.multiple) {
            if (selectedOption.value) {
                search.value = selectedOption.value[props.labelKey];
            } else if (!props.modelValue) {
                search.value = '';
            }
        } else {
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
        <!-- Single Select Input -->
        <div v-if="!multiple" class="relative">
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

        <!-- Multi Select Input Container with Chips -->
        <div 
            v-else 
            @click="handleFocus"
            class="min-h-[42px] border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus-within:border-purple-500 dark:focus-within:border-purple-600 focus-within:ring-1 focus-within:ring-purple-500 rounded-md shadow-sm p-1.5 flex flex-wrap items-center gap-1.5 cursor-text relative pr-8"
        >
            <!-- Chips -->
            <template v-if="Array.isArray(modelValue) && modelValue.includes('all')">
                <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-purple-600 text-white rounded-md text-xs font-semibold">
                    {{ __('All Employees') }}
                    <button type="button" @click.stop="emit('update:modelValue', [])" class="hover:text-purple-200">
                        &times;
                    </button>
                </span>
            </template>
            <template v-else>
                <span 
                    v-for="opt in selectedOptions" 
                    :key="opt[valueKey]"
                    class="inline-flex items-center gap-1 px-2 py-0.5 bg-purple-100 dark:bg-purple-900/60 text-purple-800 dark:text-purple-300 rounded-md text-xs font-medium border border-purple-300 dark:border-purple-700"
                >
                    <span>{{ opt[labelKey] }}</span>
                    <button type="button" @click.stop="removeTag(opt)" class="hover:text-purple-900 dark:hover:text-white font-bold ml-0.5">
                        &times;
                    </button>
                </span>
            </template>

            <!-- Search input inside multi select -->
            <input
                ref="searchInputRef"
                type="text"
                v-model="search"
                @input="handleInput"
                @focus="handleFocus"
                :placeholder="selectedOptions.length === 0 && (!Array.isArray(modelValue) || !modelValue.includes('all')) ? placeholder : ''"
                class="bg-transparent border-0 outline-none focus:ring-0 p-0 text-sm flex-grow min-w-[120px] text-gray-900 dark:text-gray-100 placeholder-gray-400"
            />

            <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none text-gray-400">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </div>
        </div>

        <!-- Dropdown Options List -->
        <div
            v-if="isOpen"
            class="absolute z-50 mt-1 w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-md shadow-lg max-h-60 overflow-y-auto"
        >
            <!-- Select All Header for Multi-Select -->
            <div v-if="multiple && options.length > 0" class="px-4 py-2 border-b border-gray-100 dark:border-gray-700 flex justify-between items-center bg-gray-50 dark:bg-gray-900/50">
                <button type="button" @click="selectAll" class="text-xs font-bold text-purple-600 dark:text-purple-400 hover:underline">
                    {{ (Array.isArray(modelValue) && modelValue.includes('all')) ? __('Deselect All') : __('Select All Employees') }}
                </button>
                <span class="text-xs text-gray-400 font-medium">
                    {{ (Array.isArray(modelValue) && modelValue.includes('all')) ? options.filter(o => o[valueKey] !== 'all').length : selectedOptions.length }} {{ __('Selected') }}
                </span>
            </div>

            <div
                v-if="filteredOptions.length === 0"
                class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400 text-center"
            >
                {{ __('No matching results found') }}
            </div>
            <ul v-else class="py-1">
                <li
                    v-for="opt in filteredOptions"
                    :key="opt[valueKey]"
                    @click="selectOption(opt)"
                    class="px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-purple-100 dark:hover:bg-purple-900/50 cursor-pointer flex justify-between items-center"
                    :class="{'bg-purple-50 dark:bg-purple-900/30 font-medium': isSelected(opt)}"
                >
                    <div class="flex items-center gap-2">
                        <input
                            v-if="multiple"
                            type="checkbox"
                            :checked="isSelected(opt)"
                            class="rounded border-gray-300 text-purple-600 focus:ring-purple-500 h-4 w-4 pointer-events-none"
                        />
                        <span>{{ opt[labelKey] }}</span>
                    </div>
                    <span v-if="opt[valueKey] !== 'all' && (opt.device_employee_id || opt.employee_code || opt.id)" class="text-xs text-purple-600 dark:text-purple-400 font-semibold ml-2">
                        ({{ opt.employee_code || ('EM-' + (opt.device_employee_id || opt.id)) }})
                    </span>
                </li>
            </ul>
        </div>
    </div>
</template>
