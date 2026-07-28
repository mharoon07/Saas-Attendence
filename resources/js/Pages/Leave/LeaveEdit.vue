<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {Head, Link, useForm} from '@inertiajs/vue3';
import Card from "@/Components/Card.vue";
import InputLabel from "@/Components/InputLabel.vue";
import InputError from "@/Components/InputError.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import SearchableSelect from "@/Components/SearchableSelect.vue";
import {ref, watch, computed} from "vue";
import {__} from "@/Composables/useTranslations.js";

const props = defineProps({
    leave: Object,
    employees: Array,
    leave_types: Array,
});

const form = useForm({
    _method: 'PUT',
    employee_id: props.leave.employee_id,
    leave_type: props.leave.leave_type,
    start_date: props.leave.start_date,
    end_date: props.leave.end_date,
    half_day: !!props.leave.half_day,
    reason: props.leave.reason,
    attachment: null,
    status: props.leave.status,
    notes: props.leave.notes ?? '',
});

const selectedEmpObj = ref((props.employees ?? []).find(emp => emp.id === form.employee_id) || null);
const employeeOptions = computed(() => {
    return (props.employees ?? []).map(emp => ({
        id: emp.id,
        name: `${emp.name} (${emp.employee_code || ('EM-' + (emp.device_employee_id || emp.id))})`
    }));
});

watch(() => form.employee_id, (newVal) => {
    selectedEmpObj.value = (props.employees ?? []).find(emp => emp.id === newVal) || null;
});

const totalDays = computed(() => {
    if (form.half_day) return 0.5;
    if (form.start_date && form.end_date) {
        const start = new Date(form.start_date);
        const end = new Date(form.end_date);
        if (end >= start) {
            const diffTime = Math.abs(end - start);
            return Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
        }
    }
    return 0;
});

watch(() => form.half_day, (val) => {
    if (val && form.start_date) {
        form.end_date = form.start_date;
    }
});

watch(() => form.start_date, (val) => {
    if (form.half_day && val) {
        form.end_date = val;
    }
});

const handleFileChange = (e) => {
    form.attachment = e.target.files[0];
};

const submit = () => {
    form.post(route('leaves.update', props.leave.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="__('Edit Leave Record')"/>
    <AuthenticatedLayout>
        <div class="py-8">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
                <Card class="p-6">
                    <div class="flex justify-between items-center mb-6 border-b border-gray-200 dark:border-gray-700 pb-4">
                        <h1 class="card-header !mb-0">{{ __('Edit Leave Record') }}</h1>
                        <Link :href="route('leaves.show', leave.id)" class="text-purple-600 dark:text-purple-400 hover:underline font-semibold text-sm">
                            &larr; {{ __('Back to Details') }}
                        </Link>
                    </div>

                    <form @submit.prevent="submit" class="space-y-6">
                        <!-- Select Employee -->
                        <div>
                            <InputLabel for="employee_id" :value="__('Select Employee')" />
                            <SearchableSelect
                                v-model="form.employee_id"
                                :options="employeeOptions"
                                :placeholder="__('Select employee...')"
                                class="mt-1"
                            />
                            <InputError class="mt-2" :message="form.errors.employee_id" />
                        </div>

                        <!-- Auto-filled Employee ID Info -->
                        <div v-if="selectedEmpObj" class="p-3 bg-gray-50 dark:bg-gray-800 rounded border border-gray-200 dark:border-gray-700 text-sm">
                            <p class="text-gray-600 dark:text-gray-300">
                                <strong>{{ __('Selected Employee Code') }}:</strong> 
                                {{ selectedEmpObj.employee_code || ('EM-' + (selectedEmpObj.device_employee_id || selectedEmpObj.id)) }}
                            </p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Leave Type -->
                            <div>
                                <InputLabel for="leave_type" :value="__('Leave Type')" />
                                <select 
                                    id="leave_type"
                                    v-model="form.leave_type"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white text-sm"
                                >
                                    <option v-for="type in leave_types" :key="type" :value="type">{{ __(type) }}</option>
                                </select>
                                <InputError class="mt-2" :message="form.errors.leave_type" />
                            </div>

                            <!-- Status -->
                            <div>
                                <InputLabel for="status" :value="__('Leave Status')" />
                                <select 
                                    id="status"
                                    v-model="form.status"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white text-sm"
                                >
                                    <option value="Pending">{{ __('Pending') }}</option>
                                    <option value="Approved">{{ __('Approved') }}</option>
                                    <option value="Rejected">{{ __('Rejected') }}</option>
                                </select>
                                <InputError class="mt-2" :message="form.errors.status" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Start Date -->
                            <div>
                                <InputLabel for="start_date" :value="__('Start Date')" />
                                <input 
                                    id="start_date"
                                    type="date"
                                    v-model="form.start_date"
                                    class="mt-1 block w-full rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600 text-gray-950 dark:text-white focus:ring-purple-500 focus:border-purple-500 text-sm"
                                />
                                <InputError class="mt-2" :message="form.errors.start_date" />
                            </div>

                            <!-- End Date -->
                            <div>
                                <InputLabel for="end_date" :value="__('End Date')" />
                                <input 
                                    id="end_date"
                                    type="date"
                                    v-model="form.end_date"
                                    :disabled="form.half_day"
                                    class="mt-1 block w-full rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600 text-gray-950 dark:text-white focus:ring-purple-500 focus:border-purple-500 text-sm"
                                    :class="{'opacity-50 cursor-not-allowed': form.half_day}"
                                />
                                <InputError class="mt-2" :message="form.errors.end_date" />
                                <InputError class="mt-1" :message="form.errors.dates" />
                            </div>
                        </div>

                        <div class="flex items-center gap-4">
                            <!-- Half Day Checkbox -->
                            <div class="flex items-center">
                                <input 
                                    id="half_day"
                                    type="checkbox"
                                    v-model="form.half_day"
                                    class="rounded border-gray-300 text-purple-600 focus:ring-purple-500 h-4 h-4"
                                />
                                <label for="half_day" class="ml-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ __('Half Day Leave') }}
                                </label>
                            </div>

                            <!-- Days Display -->
                            <div class="text-sm font-semibold text-gray-600 dark:text-gray-300">
                                {{ __('Total Leave Days') }}: <span class="text-purple-600 dark:text-purple-400 font-extrabold text-base">{{ totalDays }}</span>
                            </div>
                        </div>

                        <!-- Reason -->
                        <div>
                            <InputLabel for="reason" :value="__('Reason / Purpose of Leave')" />
                            <textarea 
                                id="reason"
                                v-model="form.reason"
                                rows="3"
                                class="mt-1 block w-full rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600 text-gray-950 dark:text-white focus:ring-purple-500 focus:border-purple-500 text-sm"
                            ></textarea>
                            <InputError class="mt-2" :message="form.errors.reason" />
                        </div>

                        <!-- Notes -->
                        <div>
                            <InputLabel for="notes" :value="__('Admin Approval Remarks / Notes')" />
                            <textarea 
                                id="notes"
                                v-model="form.notes"
                                rows="2"
                                class="mt-1 block w-full rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600 text-gray-950 dark:text-white focus:ring-purple-500 focus:border-purple-500 text-sm"
                            ></textarea>
                            <InputError class="mt-2" :message="form.errors.notes" />
                        </div>

                        <!-- Attachment -->
                        <div>
                            <InputLabel for="attachment" :value="__('Attach New Document (Optional, replaces old document)')" />
                            <input 
                                id="attachment"
                                type="file"
                                @change="handleFileChange"
                                class="mt-1 block w-full text-sm text-gray-900 dark:text-gray-300 border border-gray-300 dark:border-gray-700 rounded-md cursor-pointer bg-gray-50 dark:bg-gray-800"
                            />
                            <div v-if="leave.attachment_path" class="text-xs text-purple-600 dark:text-purple-400 mt-2">
                                {{ __('Current attachment') }}: <a :href="'/storage/' + leave.attachment_path" target="_blank" class="hover:underline font-semibold">{{ leave.attachment_path.split('/').pop() }}</a>
                            </div>
                            <InputError class="mt-2" :message="form.errors.attachment" />
                        </div>

                        <!-- Submit Buttons -->
                        <div class="flex justify-end gap-2 border-t border-gray-200 dark:border-gray-700 pt-4">
                            <SecondaryButton @click="$inertia.visit(route('leaves.show', leave.id))">
                                {{ __('Cancel') }}
                            </SecondaryButton>
                            <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                                {{ __('Update Leave Record') }}
                            </PrimaryButton>
                        </div>
                    </form>
                </Card>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
