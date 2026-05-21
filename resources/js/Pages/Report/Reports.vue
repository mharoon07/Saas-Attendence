<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {Head, router} from '@inertiajs/vue3';
import Card from "@/Components/Card.vue";
import {__} from "@/Composables/useTranslations.js";
import {ref, computed} from "vue";
import Swal from "sweetalert2";

const props = defineProps({
    stats: Object,       // dynamic calculated statistics
    employees: Array,   // all active employee records for dropdown filter
    filters: Object,     // active query parameter values
});

const selectedMonth = ref(props.filters?.month ?? new Date().getMonth() + 1);
const selectedYear = ref(props.filters?.year ?? new Date().getFullYear());
const selectedEmployee = ref(props.filters?.employee_id ?? 'all');

const months = [
    { value: 1, label: __('January') },
    { value: 2, label: __('February') },
    { value: 3, label: __('March') },
    { value: 4, label: __('April') },
    { value: 5, label: __('May') },
    { value: 6, label: __('June') },
    { value: 7, label: __('July') },
    { value: 8, label: __('August') },
    { value: 9, label: __('September') },
    { value: 10, label: __('October') },
    { value: 11, label: __('November') },
    { value: 12, label: __('December') },
];

const years = [2025, 2026, 2027, 2028];

const applyFilters = () => {
    router.get(route('reports.index'), {
        month: selectedMonth.value,
        year: selectedYear.value,
        employee_id: selectedEmployee.value
    }, {
        preserveState: true,
        preserveScroll: true
    });
};

const resetFilters = () => {
    selectedMonth.value = new Date().getMonth() + 1;
    selectedYear.value = new Date().getFullYear();
    selectedEmployee.value = 'all';
    applyFilters();
};

const mockReportData = computed(() => {
    const monthLabel = months.find(m => m.value === selectedMonth.value)?.label ?? __('Selected Month');
    const empLabel = props.stats?.employeeName ?? __('All Employees');
    
    return [
        { 
            id: 1, 
            name: `${__('Attendance Summary')} - ${empLabel} (${monthLabel} ${selectedYear.value})`, 
            type: __('PDF/CSV'), 
            date: '2026-05-21', 
            status: __('Generated') 
        },
        { 
            id: 2, 
            name: `${__('Payroll & Hours Breakdown')} - ${empLabel}`, 
            type: __('PDF/CSV'), 
            date: '2026-05-20', 
            status: __('Generated') 
        },
        { 
            id: 3, 
            name: `${__('Late Entry / Absence Limit Check')}`, 
            type: __('CSV'), 
            date: '2026-05-18', 
            status: __('Archived') 
        },
    ];
});

const handleDownload = (reportName) => {
    Swal.fire({
        title: __('Generating Report...'),
        html: __('Preparing data for :report...', {report: reportName}),
        timer: 1500,
        timerProgressBar: true,
        didOpen: () => {
            Swal.showLoading()
        }
    }).then((result) => {
        Swal.fire({
            icon: 'success',
            title: __('Report Downloaded!'),
            text: __('The :report has been saved successfully.', {report: reportName}),
            confirmButtonColor: '#7c3aed',
        });
    });
};
</script>

<template>
    <Head :title="__('Reports')"/>
    <AuthenticatedLayout>
        <template #tabs>
            <span class="text-gray-900 dark:text-gray-100 font-semibold text-lg flex items-center h-full">
                {{ __('Reports Dashboard') }}
            </span>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                
                <!-- Filter Selection Panel (Month & Employee) -->
                <Card class="p-6">
                    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
                        <div class="flex flex-col sm:flex-row sm:items-center gap-4 flex-grow">
                            <!-- Employee Filter (Admin only) -->
                            <div v-if="$page.props.auth.user.roles.includes('admin')" class="flex-grow min-w-[220px]">
                                <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">
                                    {{ __('Filter by Employee') }}
                                </label>
                                <select 
                                    v-model="selectedEmployee" 
                                    @change="applyFilters" 
                                    class="w-full rounded border-gray-300 dark:bg-gray-800 dark:border-gray-700 text-gray-950 dark:text-white focus:ring-purple-500 focus:border-purple-500 text-sm"
                                >
                                    <option value="all">{{ __('All Employees') }}</option>
                                    <option v-for="emp in employees" :key="emp.id" :value="emp.id">{{ emp.name }}</option>
                                </select>
                            </div>
                            
                            <!-- Month Filter -->
                            <div class="min-w-[150px]">
                                <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">
                                    {{ __('Month') }}
                                </label>
                                <select 
                                    v-model="selectedMonth" 
                                    @change="applyFilters" 
                                    class="w-full rounded border-gray-300 dark:bg-gray-800 dark:border-gray-700 text-gray-950 dark:text-white focus:ring-purple-500 focus:border-purple-500 text-sm"
                                >
                                    <option v-for="m in months" :key="m.value" :value="m.value">{{ m.label }}</option>
                                </select>
                            </div>

                            <!-- Year Filter -->
                            <div class="min-w-[100px]">
                                <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">
                                    {{ __('Year') }}
                                </label>
                                <select 
                                    v-model="selectedYear" 
                                    @change="applyFilters" 
                                    class="w-full rounded border-gray-300 dark:bg-gray-800 dark:border-gray-700 text-gray-950 dark:text-white focus:ring-purple-500 focus:border-purple-500 text-sm"
                                >
                                    <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                                </select>
                            </div>
                        </div>

                        <!-- Reset / Actions -->
                        <div class="flex items-end gap-2 shrink-0">
                            <button 
                                @click="resetFilters" 
                                class="py-2 px-4 border border-gray-300 dark:border-gray-700 rounded text-sm hover:bg-gray-50 dark:hover:bg-gray-800 transition font-semibold text-gray-600 dark:text-gray-300 flex items-center gap-1"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                                </svg>
                                {{ __('Reset Filters') }}
                            </button>
                        </div>
                    </div>
                </Card>

                <!-- Dynamic Overview Stats Grid -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <Card class="bg-gradient-to-br from-purple-600 to-indigo-700 text-white shadow-lg transform hover:scale-[1.02] transition-transform duration-200">
                        <div class="p-2">
                            <p class="text-sm uppercase tracking-wider text-purple-200">{{ stats.employeeName === 'All Employees' ? __('Total Employees') : __('Selected Employee') }}</p>
                            <h3 class="text-2xl font-extrabold mt-1 truncate">{{ stats.employeeName === 'All Employees' ? stats.empCount : stats.employeeName }}</h3>
                        </div>
                    </Card>
                    <Card class="bg-gradient-to-br from-blue-500 to-cyan-600 text-white shadow-lg transform hover:scale-[1.02] transition-transform duration-200">
                        <div class="p-2">
                            <p class="text-sm uppercase tracking-wider text-blue-100">{{ stats.employeeName === 'All Employees' ? __('Departments') : __('Expected Hours') }}</p>
                            <h3 class="text-3xl font-extrabold mt-1">{{ stats.employeeName === 'All Employees' ? stats.deptCount : stats.expectedHours + 'h' }}</h3>
                        </div>
                    </Card>
                    <Card class="bg-gradient-to-br from-emerald-500 to-teal-600 text-white shadow-lg transform hover:scale-[1.02] transition-transform duration-200">
                        <div class="p-2">
                            <p class="text-sm uppercase tracking-wider text-emerald-100">{{ stats.employeeName === 'All Employees' ? __('Attendance Rate') : __('Actual Hours') }}</p>
                            <h3 class="text-3xl font-extrabold mt-1">{{ stats.employeeName === 'All Employees' ? stats.attendanceRate + '%' : stats.actualHours + 'h' }}</h3>
                        </div>
                    </Card>
                    <Card class="bg-gradient-to-br from-amber-500 to-orange-600 text-white shadow-lg transform hover:scale-[1.02] transition-transform duration-200">
                        <div class="p-2">
                            <p class="text-sm uppercase tracking-wider text-amber-100">{{ stats.employeeName === 'All Employees' ? __('Late Check-Ins') : __('Monthly Accuracy') }}</p>
                            <h3 class="text-3xl font-extrabold mt-1">{{ stats.employeeName === 'All Employees' ? stats.lateCount : stats.attendanceRate + '%' }}</h3>
                        </div>
                    </Card>
                </div>

                <!-- Report Generators section -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Left: Custom Report Card -->
                    <Card class="col-span-1 p-6 space-y-4">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white border-b pb-2 border-purple-500">
                            {{ __('Generate Custom Report') }}
                        </h2>
                        
                        <div class="space-y-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    {{ __('Report Type') }}
                                </label>
                                <select class="w-full rounded border-gray-300 dark:bg-gray-800 dark:border-gray-700 text-gray-950 dark:text-white focus:ring-purple-500 focus:border-purple-500">
                                    <option>{{ __('Monthly Attendance Summary') }}</option>
                                    <option>{{ __('Payroll Breakdown') }}</option>
                                    <option>{{ __('Late Entry / Early Exit Details') }}</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase mb-1">
                                    {{ __('Target Parameter Info') }}
                                </label>
                                <div class="p-3 bg-gray-50 dark:bg-gray-800 rounded border border-gray-200 dark:border-gray-700 text-xs text-gray-600 dark:text-gray-300 space-y-1">
                                    <p><strong>{{ __('Employee:') }}</strong> {{ stats.employeeName }}</p>
                                    <p><strong>{{ __('Period:') }}</strong> {{ months.find(m => m.value === selectedMonth)?.label }} {{ selectedYear }}</p>
                                </div>
                            </div>

                            <button 
                                @click="handleDownload(`${__('Custom Summary Report')} - ${stats.employeeName}`)"
                                class="w-full py-2.5 px-4 bg-purple-600 hover:bg-purple-700 text-white font-semibold rounded shadow transition duration-150 flex items-center justify-center gap-2"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                </svg>
                                {{ __('Generate & Download') }}
                            </button>
                        </div>
                    </Card>

                    <!-- Right: Available System Reports -->
                    <Card class="col-span-1 lg:col-span-2 p-6 space-y-4">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white border-b pb-2 border-purple-500">
                            {{ __('Recently Generated Reports') }}
                        </h2>

                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="border-b border-gray-200 dark:border-gray-700 text-gray-400 text-xs font-semibold uppercase">
                                        <th class="py-3 px-2">{{ __('Report Name') }}</th>
                                        <th class="py-3 px-2 text-center">{{ __('Format') }}</th>
                                        <th class="py-3 px-2 text-center">{{ __('Created Date') }}</th>
                                        <th class="py-3 px-2 text-center">{{ __('Action') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-800 text-sm text-gray-800 dark:text-gray-300">
                                    <tr v-for="report in mockReportData" :key="report.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition">
                                        <td class="py-4 px-2 font-medium text-gray-900 dark:text-white">{{ report.name }}</td>
                                        <td class="py-4 px-2 text-center">
                                            <span class="px-2 py-1 text-xs font-semibold rounded bg-purple-100 dark:bg-purple-950 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-900">
                                                {{ report.type }}
                                            </span>
                                        </td>
                                        <td class="py-4 px-2 text-center">{{ report.date }}</td>
                                        <td class="py-4 px-2 text-center">
                                            <button 
                                                @click="handleDownload(report.name)"
                                                class="text-purple-600 hover:text-purple-700 dark:text-purple-400 font-semibold inline-flex items-center gap-1"
                                            >
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                                </svg>
                                                {{ __('Download') }}
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </Card>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
