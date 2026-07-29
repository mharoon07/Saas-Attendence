<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {Head, Link, router, useForm} from '@inertiajs/vue3';
import Table from "@/Components/Table/Table.vue";
import TableHead from "@/Components/Table/TableHead.vue";
import TableBody from "@/Components/Table/TableBody.vue";
import TableBodyHeader from "@/Components/Table/TableBodyHeader.vue";
import TableBodyAction from "@/Components/Table/TableBodyAction.vue";
import TableRow from "@/Components/Table/TableRow.vue";
import PayrollTabs from "@/Components/Tabs/PayrollTabs.vue";
import VueDatePicker from "@vuepic/vue-datepicker";
import '@vuepic/vue-datepicker/dist/main.css'
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import {computed, inject, ref, watch} from "vue";
import Card from "@/Components/Card.vue";
import Modal from "@/Components/Modal.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import {__} from "@/Composables/useTranslations.js";
import Swal from "sweetalert2";

const props = defineProps({
    payrolls: Object,
    dateParam: String,
    statusParam: String,
    departmentParam: String,
    shiftParam: String,
    employeeParam: String,
    tabParam: String,
    employees: Array,
    shifts: Array,
    departments: Array,
});

const date = ref(props.dateParam ? new Date(props.dateParam) : '');
const status = ref(props.statusParam || 'all');
const departmentId = ref(props.departmentParam || '');
const shiftId = ref(props.shiftParam || '');
const employeeId = ref(props.employeeParam || '');
const currentTab = ref(props.tabParam || 'current');

const filteredFilterEmployees = computed(() => {
    if (!props.employees) return [];
    return props.employees.filter((emp) => {
        if (shiftId.value && emp.shift_id != shiftId.value) return false;
        if (departmentId.value && emp.department_id != departmentId.value) return false;
        return true;
    });
});

const switchTab = (tab) => {
    currentTab.value = tab;
    filter();
};

const filter = () => {
    const routeParameters = {
        date: date.value === null ? null : date.value,
        status: status.value === null ? null : status.value,
        department_id: departmentId.value || null,
        shift_id: shiftId.value || null,
        employee_id: employeeId.value || null,
        tab: currentTab.value,
    };
    router.visit(route('payrolls.index', routeParameters), {
        preserveState: true,
        preserveScroll: true,
    });
};

watch([date, status, departmentId, shiftId, employeeId], filter);

const showGenerateModal = ref(false);
const generateForm = useForm({
    month_year: null,
    employee_id: '', 
    shift_id: '',
    department_id: '',
});

const filteredTargetEmployees = computed(() => {
    if (!props.employees) return [];
    return props.employees.filter((emp) => {
        if (generateForm.shift_id && emp.shift_id != generateForm.shift_id) {
            return false;
        }
        if (generateForm.department_id && emp.department_id != generateForm.department_id) {
            return false;
        }
        return true;
    });
});

watch([() => generateForm.shift_id, () => generateForm.department_id], () => {
    if (generateForm.employee_id) {
        const stillValid = filteredTargetEmployees.value.some(e => e.id == generateForm.employee_id);
        if (!stillValid) {
            generateForm.employee_id = '';
        }
    }
});

const generatePayroll = () => {
    generateForm.post(route('payrolls.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showGenerateModal.value = false;
            generateForm.reset();
        },
    });
};

const destroy = (id) => {
    Swal.fire({
        title: __('Are you sure you want to delete this Payroll?'),
        text: __('This action cannot be undone.'),
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: __('Yes, delete it!')
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('payrolls.destroy', {payroll: id}), {
                preserveScroll: true,
                onSuccess: () => {
                    Swal.fire(__('Deleted!'), __('The Payroll has been deleted.'), 'success')
                }
            })
        }
    })
};

</script>

<template>
    <Head :title="__('Payrolls')"/>
    <AuthenticatedLayout>
        <template #tabs>
            <PayrollTabs />
        </template>
        <div class="py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <Card class="!mt-0">
                    <div class="flex justify-between items-center !mb-4">
                        <h1 class="card-header !mb-0">{{__('Payrolls')}}</h1>
                        <PrimaryButton v-if="$page.props.auth.user.roles.includes('admin')" @click="showGenerateModal = true">
                            {{__('Generate Payroll')}}
                        </PrimaryButton>
                    </div>

                    <!-- Current vs Previous Payroll Tabs -->
                    <div class="border-b border-gray-200 dark:border-gray-700 mb-6">
                        <ul class="flex flex-wrap -mb-px text-sm font-medium text-center">
                            <li class="me-2">
                                <button
                                    @click="switchTab('current')"
                                    :class="[
                                        'inline-block p-4 border-b-2 rounded-t-lg transition-colors font-semibold',
                                        currentTab === 'current'
                                            ? 'text-purple-600 border-purple-600 dark:text-purple-400 dark:border-purple-400'
                                            : 'border-transparent text-gray-500 hover:text-gray-600 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'
                                    ]"
                                >
                                    📄 {{ __('Current Payroll') }}
                                </button>
                            </li>
                            <li class="me-2">
                                <button
                                    @click="switchTab('previous')"
                                    :class="[
                                        'inline-block p-4 border-b-2 rounded-t-lg transition-colors font-semibold',
                                        currentTab === 'previous'
                                            ? 'text-purple-600 border-purple-600 dark:text-purple-400 dark:border-purple-400'
                                            : 'border-transparent text-gray-500 hover:text-gray-600 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'
                                    ]"
                                >
                                    📁 {{ __('Previous Payroll') }}
                                </button>
                            </li>
                        </ul>
                    </div>

                    <!-- Filter Controls Grid -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-6 items-end">
                        <!-- Department Filter -->
                        <div>
                            <InputLabel for="filter_department" :value="__('Filter by Department') + ':'" />
                            <select
                                id="filter_department"
                                v-model="departmentId"
                                class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-purple-500 dark:focus:border-purple-600 focus:ring-purple-500 dark:focus:ring-purple-600 rounded-md shadow-sm h-[34px] py-1 px-3 block w-full text-sm mt-1"
                            >
                                <option value="">{{ __('All Departments') }}</option>
                                <option v-for="dept in departments" :key="dept.id" :value="dept.id">
                                    {{ dept.name }}
                                </option>
                            </select>
                        </div>

                        <!-- Shift Filter -->
                        <div>
                            <InputLabel for="filter_shift" :value="__('Filter by Shift') + ':'" />
                            <select
                                id="filter_shift"
                                v-model="shiftId"
                                class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-purple-500 dark:focus:border-purple-600 focus:ring-purple-500 dark:focus:ring-purple-600 rounded-md shadow-sm h-[34px] py-1 px-3 block w-full text-sm mt-1"
                            >
                                <option value="">{{ __('All Shifts') }}</option>
                                <option v-for="sh in shifts" :key="sh.id" :value="sh.id">
                                    {{ sh.name }}
                                </option>
                            </select>
                        </div>

                        <!-- Employee Filter -->
                        <div>
                            <InputLabel for="filter_employee" :value="__('Filter by Employee') + ':'" />
                            <select
                                id="filter_employee"
                                v-model="employeeId"
                                class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-purple-500 dark:focus:border-purple-600 focus:ring-purple-500 dark:focus:ring-purple-600 rounded-md shadow-sm h-[34px] py-1 px-3 block w-full text-sm mt-1"
                            >
                                <option value="">{{ __('All Employees') }}</option>
                                <option v-for="emp in filteredFilterEmployees" :key="emp.id" :value="emp.id">
                                    {{ emp.name }}
                                </option>
                            </select>
                        </div>

                        <!-- Month Filter -->
                        <div>
                            <InputLabel for="date" :value="__('Filter by Month') + ':'" />
                            <VueDatePicker
                                id="date"
                                v-model="date"
                                class="w-full payroll-date-picker"
                                :placeholder="__('Select a Date...')"
                                :enable-time-picker="false"
                                :max-date="new Date()"
                                month-picker
                                :dark="inject('isDark').value"
                            ></VueDatePicker>
                        </div>

                        <!-- Status Filter -->
                        <div>
                            <InputLabel for="status" :value="__('Filter by Status') + ':'" />
                            <select
                                id="status"
                                v-model="status"
                                class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-purple-500 dark:focus:border-purple-600 focus:ring-purple-500 dark:focus:ring-purple-600 rounded-md shadow-sm h-[34px] py-1 px-3 block w-full text-sm mt-1"
                            >
                                <option value="all">{{ __('All') }}</option>
                                <option value="pending">{{ __('Pending') }}</option>
                                <option value="reviewed">{{ __('Reviewed') }}</option>
                                <option value="paid">{{ __('Paid') }}</option>
                            </select>
                        </div>
                    </div>
                    <Table :links="payrolls.links" :showingNumber="payrolls.data.length" :totalNumber="payrolls.total">
                        <template #Head>
                            <TableHead>{{__('Payroll ID')}}</TableHead>
                            <TableHead>{{__('Month/Year')}}</TableHead>
                            <TableHead>{{__('Employee')}}</TableHead>
                            <TableHead>{{__('Monthly Salary')}}</TableHead>
                            <TableHead>{{__('Daily Salary')}}</TableHead>
                            <TableHead>{{__('Absent')}}</TableHead>
                            <TableHead>{{__('Leave')}}</TableHead>
                            <TableHead>{{__('Overtime (Hrs)')}}</TableHead>
                            <TableHead>{{__('Overtime Amt')}}</TableHead>
                            <TableHead>{{__('Additions')}}</TableHead>
                            <TableHead>{{__('Deductions')}}</TableHead>
                            <TableHead>{{__('Gross Salary')}}</TableHead>
                            <TableHead>{{__('Net Salary')}}</TableHead>
                            <TableHead>{{__('Status')}}</TableHead>
                            <TableHead v-if="$page.props.auth.user.roles.includes('admin')">{{__('Action')}}</TableHead>
                        </template>
                         <template #Body>
                            <TableRow v-for="payroll in payrolls.data" :key="payroll.id">
                                <TableBodyHeader :href="route('payrolls.show', {id: payroll.id})">{{payroll.id}}</TableBodyHeader>
                                <TableBodyHeader :href="route('payrolls.show', {id: payroll.id})">{{payroll.payroll_month}}/{{payroll.payroll_year}}</TableBodyHeader>
                                <TableBodyHeader :href="route('payrolls.show', {id: payroll.id})">{{payroll.employee_name}} <span class="text-xs text-purple-600 dark:text-purple-400 font-semibold">(EM-{{payroll.device_employee_id || payroll.emp_id}})</span></TableBodyHeader>
                                <TableBody :href="route('payrolls.show', {id: payroll.id})">{{payroll.currency}} {{payroll.monthly_salary}}</TableBody>
                                <TableBody :href="route('payrolls.show', {id: payroll.id})">{{payroll.currency}} {{payroll.daily_salary}}</TableBody>
                                <TableBody :href="route('payrolls.show', {id: payroll.id})">{{payroll.absent_days}}</TableBody>
                                <TableBody :href="route('payrolls.show', {id: payroll.id})">{{payroll.leave_days}}</TableBody>
                                <TableBody :href="route('payrolls.show', {id: payroll.id})">{{payroll.overtime_hours}}</TableBody>
                                <TableBody :href="route('payrolls.show', {id: payroll.id})">{{payroll.currency}} {{payroll.overtime_amount}}</TableBody>
                                <TableBody :href="route('payrolls.show', {id: payroll.id})">{{payroll.currency}} {{payroll.total_additions}}</TableBody>
                                <TableBody :href="route('payrolls.show', {id: payroll.id})">{{payroll.currency}} {{payroll.total_deductions}}</TableBody>
                                <TableBody :href="route('payrolls.show', {id: payroll.id})">{{payroll.currency}} {{payroll.gross_salary}}</TableBody>
                                <TableBody :href="route('payrolls.show', {id: payroll.id})">{{payroll.currency}} {{payroll.net_salary}}</TableBody>
                                <TableBody :href="route('payrolls.show', {id: payroll.id})">{{payroll.status  ? "Paid" : (payroll.is_reviewed ? "Reviewed" : "Pending Review")}}</TableBody>
                                <td class="px-6 py-4" v-if="$page.props.auth.user.roles.includes('admin')">
                                     <a :href="route('payrolls.edit', {id: payroll.id})" class="text-purple-600 hover:underline mr-2">{{__('Edit')}}</a>
                                     <a :href="route('payrolls.pdf', {id: payroll.id, mode: 'download'})" class="text-blue-600 hover:underline font-semibold mr-2">{{__('Download PDF')}}</a>
                                     <button @click.prevent="destroy(payroll.id)" class="text-red-600 hover:underline">{{__('Delete')}}</button>
                                </td>
                            </TableRow>
                        </template>
                    </Table>
                </Card>
            </div>
        </div>

        <Modal :show="showGenerateModal" @close="showGenerateModal = false">
            <div class="p-6 dark:bg-gray-800">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    {{ __('Generate Payroll') }}
                </h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    {{ __('Select a month and year to generate monthly payroll for active employees.') }}
                </p>
                <div class="mt-4 space-y-4">
                    <div>
                        <InputLabel for="month_year" :value="__('Select Month & Year')" />
                        <VueDatePicker
                            v-model="generateForm.month_year"
                            month-picker
                            auto-apply
                            class="mt-1 block w-full"
                            :dark="inject('isDark').value"
                            teleport="body"
                        />
                        <InputError class="mt-2" :message="generateForm.errors.month_year" />
                    </div>
                    <div>
                        <InputLabel for="shift_id" :value="__('Shift Wise (Optional)')" />
                        <select
                            id="shift_id"
                            v-model="generateForm.shift_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm"
                        >
                            <option value="">{{ __('All Shifts') }}</option>
                            <option v-for="s in shifts" :key="s.id" :value="s.id">
                                {{ s.name }}
                            </option>
                        </select>
                        <InputError class="mt-2" :message="generateForm.errors.shift_id" />
                    </div>
                    <div>
                        <InputLabel for="department_id" :value="__('Department Wise (Optional)')" />
                        <select
                            id="department_id"
                            v-model="generateForm.department_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm"
                        >
                            <option value="">{{ __('All Departments') }}</option>
                            <option v-for="d in departments" :key="d.id" :value="d.id">
                                {{ d.name }}
                            </option>
                        </select>
                        <InputError class="mt-2" :message="generateForm.errors.department_id" />
                    </div>
                    <div>
                        <InputLabel for="employee_id" :value="__('Target Employee (Optional)')" />
                        <select
                            id="employee_id"
                            v-model="generateForm.employee_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm"
                        >
                            <option value="">{{ __('All Active Employees') }}</option>
                            <option v-for="emp in filteredTargetEmployees" :key="emp.id" :value="emp.id">
                                {{ emp.name }} ({{ emp.employee_code || ('EM-' + (emp.device_employee_id || emp.id)) }})
                            </option>
                        </select>
                        <InputError class="mt-2" :message="generateForm.errors.employee_id" />
                    </div>
                </div>
                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="showGenerateModal = false">
                        {{ __('Cancel') }}
                    </SecondaryButton>

                    <PrimaryButton
                        class="ms-3"
                        :class="{ 'opacity-25': generateForm.processing }"
                        :disabled="generateForm.processing"
                        @click="generatePayroll"
                    >
                        {{ __('Generate') }}
                    </PrimaryButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>

<style>
.payroll-date-picker {
    margin-top: 0.25rem;
}

.payroll-date-picker .dp__main {
    height: 34px !important;
}

.payroll-date-picker .dp__input {
    height: 34px !important;
    min-height: 34px !important;
    max-height: 34px !important;
    font-size: 0.875rem !important;
    line-height: 1.25rem !important;
    border-radius: 0.375rem !important;
    padding-top: 0.25rem !important;
    padding-bottom: 0.25rem !important;
    padding-left: 35px !important;
    box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05) !important;
}

.dark .payroll-date-picker .dp__input {
    background-color: #111827 !important;
    color: #d1d5db !important;
    border-color: #374151 !important;
}

.payroll-date-picker .dp__input:focus {
    border-color: #9333ea !important;
    box-shadow: 0 0 0 1px #9333ea !important;
}
</style>
