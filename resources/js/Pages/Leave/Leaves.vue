<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {Head, Link, router} from '@inertiajs/vue3';
import Table from "@/Components/Table/Table.vue";
import TableHead from "@/Components/Table/TableHead.vue";
import TableBody from "@/Components/Table/TableBody.vue";
import TableBodyHeader from "@/Components/Table/TableBodyHeader.vue";
import TableRow from "@/Components/Table/TableRow.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import Card from "@/Components/Card.vue";
import SearchableSelect from "@/Components/SearchableSelect.vue";
import {ref, watch, computed, inject} from "vue";
import {__} from "@/Composables/useTranslations.js";
import Swal from "sweetalert2";

const props = defineProps({
    leaves: Object,
    filters: Object,
    employees: Array,
});

const search = ref(props.filters?.search ?? '');
const selectedEmployee = ref(props.filters?.employee_id ?? 'all');
const selectedType = ref(props.filters?.leave_type ?? 'all');
const selectedStatus = ref(props.filters?.status ?? 'all');
const startDate = ref(props.filters?.start_date ?? '');
const endDate = ref(props.filters?.end_date ?? '');

const employeeOptions = computed(() => [
    { id: 'all', name: __('All Employees') },
    ...(props.employees ?? [])
]);

const applyFilters = () => {
    router.get(route('leaves.index'), {
        search: search.value,
        employee_id: selectedEmployee.value,
        leave_type: selectedType.value,
        status: selectedStatus.value,
        start_date: startDate.value,
        end_date: endDate.value,
    }, {
        preserveState: true,
        preserveScroll: true
    });
};

const resetFilters = () => {
    search.value = '';
    selectedEmployee.value = 'all';
    selectedType.value = 'all';
    selectedStatus.value = 'all';
    startDate.value = '';
    endDate.value = '';
    applyFilters();
};

const deleteLeave = (id) => {
    Swal.fire({
        title: __('Are you sure?'),
        text: __('You will not be able to revert this!'),
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: __('Yes, delete it!')
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('leaves.destroy', id), {
                preserveScroll: true,
                onSuccess: () => {
                    Swal.fire(__('Deleted!'), __('Leave record has been deleted.'), 'success');
                }
            });
        }
    });
};

const isEndDatePassed = (endDate) => {
    if (!endDate) return false;
    const [year, month, day] = endDate.split('-').map(Number);
    const end = new Date(year, month - 1, day);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    return end < today;
};

const approveLeave = (id) => {
    const leaveItem = props.leaves.data.find(l => l.id === id);
    if (leaveItem && isEndDatePassed(leaveItem.end_date)) {
        Swal.fire(__('Error'), __('Cannot approve a leave request after its end date has passed.'), 'error');
        return;
    }

    Swal.fire({
        title: __('Approve Leave'),
        text: __('Are you sure you want to approve this leave request?'),
        icon: 'question',
        input: 'text',
        inputPlaceholder: __('Add approval remarks/notes (optional)'),
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        cancelButtonColor: '#3085d6',
        confirmButtonText: __('Approve')
    }).then((result) => {
        if (result.isConfirmed) {
            router.put(route('leaves.approve', id), {
                notes: result.value
            }, {
                preserveScroll: true,
                onSuccess: () => {
                    Swal.fire(__('Approved!'), __('Leave request approved successfully.'), 'success');
                }
            });
        }
    });
};

const rejectLeave = (id) => {
    const leaveItem = props.leaves.data.find(l => l.id === id);
    if (leaveItem && isEndDatePassed(leaveItem.end_date)) {
        Swal.fire(__('Error'), __('Cannot reject a leave request after its end date has passed.'), 'error');
        return;
    }

    Swal.fire({
        title: __('Reject Leave'),
        text: __('Are you sure you want to reject this leave request?'),
        icon: 'warning',
        input: 'text',
        inputPlaceholder: __('Add rejection reason/notes (optional)'),
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#3085d6',
        confirmButtonText: __('Reject')
    }).then((result) => {
        if (result.isConfirmed) {
            router.put(route('leaves.reject', id), {
                notes: result.value
            }, {
                preserveScroll: true,
                onSuccess: () => {
                    Swal.fire(__('Rejected!'), __('Leave request rejected successfully.'), 'success');
                }
            });
        }
    });
};

// Helper for status classes
const statusClass = (status) => {
    if (status === 'Approved') return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400';
    if (status === 'Rejected') return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400';
    return 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400';
};
</script>

<template>
    <Head :title="__('Leave Management')"/>
    <AuthenticatedLayout>
        <div class="py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Filters Card -->
                <Card class="mb-6 p-6">
                    <div class="flex flex-col gap-4">
                        <div class="flex flex-col md:flex-row items-center justify-between gap-4 border-b border-gray-200 dark:border-gray-700 pb-4">
                            <h1 class="card-header !mb-0">{{ __('Leave Management') }}</h1>
                            <Link 
                                v-if="$page.props.auth.user.roles.includes('admin')"
                                :href="route('leaves.create')" 
                                class="inline-flex items-center px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white font-semibold text-sm rounded shadow transition duration-150"
                            >
                                {{ __('Add Leave (Manual)') }}
                            </Link>
                        </div>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                            <!-- Search -->
                            <div>
                                <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">
                                    {{ __('Search Employee') }}
                                </label>
                                <input 
                                    v-model="search" 
                                    type="text" 
                                    :placeholder="__('Name or ID...')" 
                                    class="w-full rounded border-gray-300 dark:bg-gray-800 dark:border-gray-700 text-gray-950 dark:text-white focus:ring-purple-500 focus:border-purple-500 text-sm"
                                    @keyup.enter="applyFilters"
                                />
                            </div>

                            <!-- Employee Searchable Select (Admin only) -->
                            <div v-if="$page.props.auth.user.roles.includes('admin')">
                                <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">
                                    {{ __('Filter by Employee') }}
                                </label>
                                <SearchableSelect
                                    v-model="selectedEmployee"
                                    :options="employeeOptions"
                                    :placeholder="__('Select employee...')"
                                    @update:modelValue="applyFilters"
                                />
                            </div>

                            <!-- Leave Type -->
                            <div>
                                <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">
                                    {{ __('Leave Type') }}
                                </label>
                                <select 
                                    v-model="selectedType" 
                                    @change="applyFilters" 
                                    class="w-full rounded border-gray-300 dark:bg-gray-800 dark:border-gray-700 text-gray-950 dark:text-white focus:ring-purple-500 focus:border-purple-500 text-sm"
                                >
                                    <option value="all">{{ __('All Types') }}</option>
                                    <option value="Annual">{{ __('Annual') }}</option>
                                    <option value="Sick">{{ __('Sick') }}</option>
                                    <option value="Casual">{{ __('Casual') }}</option>
                                    <option value="Unpaid">{{ __('Unpaid') }}</option>
                                    <option value="Maternity">{{ __('Maternity') }}</option>
                                    <option value="Paternity">{{ __('Paternity') }}</option>
                                    <option value="Other">{{ __('Other') }}</option>
                                </select>
                            </div>

                            <!-- Status -->
                            <div>
                                <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">
                                    {{ __('Status') }}
                                </label>
                                <select 
                                    v-model="selectedStatus" 
                                    @change="applyFilters" 
                                    class="w-full rounded border-gray-300 dark:bg-gray-800 dark:border-gray-700 text-gray-950 dark:text-white focus:ring-purple-500 focus:border-purple-500 text-sm"
                                >
                                    <option value="all">{{ __('All Statuses') }}</option>
                                    <option value="Pending">{{ __('Pending') }}</option>
                                    <option value="Approved">{{ __('Approved') }}</option>
                                    <option value="Rejected">{{ __('Rejected') }}</option>
                                </select>
                            </div>
                        </div>

                        <div class="flex flex-col sm:flex-row items-end justify-between gap-4">
                            <div class="flex flex-wrap gap-4 flex-grow">
                                <!-- Start Date -->
                                <div class="w-full sm:w-auto min-w-[150px]">
                                    <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">
                                        {{ __('From Date') }}
                                    </label>
                                    <input 
                                        v-model="startDate" 
                                        type="date" 
                                        class="w-full rounded border-gray-300 dark:bg-gray-800 dark:border-gray-700 text-gray-950 dark:text-white focus:ring-purple-500 focus:border-purple-500 text-sm"
                                        @change="applyFilters"
                                    />
                                </div>
                                <!-- End Date -->
                                <div class="w-full sm:w-auto min-w-[150px]">
                                    <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">
                                        {{ __('To Date') }}
                                    </label>
                                    <input 
                                        v-model="endDate" 
                                        type="date" 
                                        class="w-full rounded border-gray-300 dark:bg-gray-800 dark:border-gray-700 text-gray-950 dark:text-white focus:ring-purple-500 focus:border-purple-500 text-sm"
                                        @change="applyFilters"
                                    />
                                </div>
                            </div>
                            <!-- Buttons -->
                            <div class="flex gap-2 w-full sm:w-auto shrink-0 justify-end">
                                <button 
                                    @click="applyFilters" 
                                    class="py-2 px-4 bg-purple-600 hover:bg-purple-700 text-white font-semibold text-sm rounded shadow transition duration-150 flex items-center gap-1"
                                >
                                    {{ __('Search') }}
                                </button>
                                <button 
                                    @click="resetFilters" 
                                    class="py-2 px-4 border border-gray-300 dark:border-gray-700 rounded text-sm hover:bg-gray-50 dark:hover:bg-gray-800 text-gray-700 dark:text-gray-300 transition font-semibold"
                                >
                                    {{ __('Reset') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </Card>

                <!-- Data Table -->
                <Card class="!mt-0">
                    <Table :links="leaves.links" :showingNumber="leaves.data.length" :totalNumber="leaves.total">
                        <template #Head>
                            <TableHead>{{ __('Emp Code') }}</TableHead>
                            <TableHead>{{ __('Employee Name') }}</TableHead>
                            <TableHead>{{ __('Leave Type') }}</TableHead>
                            <TableHead>{{ __('Start Date') }}</TableHead>
                            <TableHead>{{ __('End Date') }}</TableHead>
                            <TableHead class="text-center">{{ __('Days') }}</TableHead>
                            <TableHead>{{ __('Status') }}</TableHead>
                            <TableHead>{{ __('Applied By') }}</TableHead>
                            <TableHead>{{ __('Actions') }}</TableHead>
                        </template>
                        <template #Body>
                            <TableRow v-for="leave in leaves.data" :key="leave.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                <TableBodyHeader>{{ leave.employee.employee_code || ('EM-' + (leave.employee.device_employee_id || leave.employee.id)) }}</TableBodyHeader>
                                <TableBodyHeader>{{ leave.employee.name }}</TableBodyHeader>
                                <TableBody>{{ leave.leave_type }}</TableBody>
                                <TableBody>{{ leave.start_date }}</TableBody>
                                <TableBody>{{ leave.end_date }}</TableBody>
                                <TableBody class="text-center font-bold">{{ leave.total_days }}</TableBody>
                                <TableBody>
                                    <span class="px-2.5 py-1 text-xs font-semibold rounded-full border" :class="statusClass(leave.status)">
                                        {{ leave.status }}
                                    </span>
                                </TableBody>
                                <TableBody>{{ leave.applied_by }}</TableBody>
                                <TableBody>
                                    <div class="flex items-center gap-2">
                                        <Link :href="route('leaves.show', leave.id)" class="text-purple-600 hover:text-purple-900 font-semibold text-xs">{{ __('View') }}</Link>
                                        <template v-if="$page.props.auth.user.roles.includes('admin')">
                                            <Link v-if="!isEndDatePassed(leave.end_date)" :href="route('leaves.edit', leave.id)" class="text-indigo-600 hover:text-indigo-900 font-semibold text-xs">{{ __('Edit') }}</Link>
                                            <span v-else class="text-gray-400 font-semibold text-xs cursor-not-allowed opacity-60" :title="__('Cannot edit after end date has passed')">{{ __('Edit') }}</span>
                                            
                                            <!-- Approve/Reject inline actions for Pending leaves -->
                                            <template v-if="leave.status === 'Pending'">
                                                <button v-if="!isEndDatePassed(leave.end_date)" @click="approveLeave(leave.id)" class="text-emerald-600 hover:text-emerald-950 font-bold text-xs">{{ __('Approve') }}</button>
                                                <span v-else class="text-gray-400 font-bold text-xs cursor-not-allowed opacity-60" :title="__('Cannot approve after end date has passed')">{{ __('Approve') }}</span>
                                                <button v-if="!isEndDatePassed(leave.end_date)" @click="rejectLeave(leave.id)" class="text-rose-600 hover:text-rose-950 font-bold text-xs">{{ __('Reject') }}</button>
                                                <span v-else class="text-gray-400 font-bold text-xs cursor-not-allowed opacity-60" :title="__('Cannot reject after end date has passed')">{{ __('Reject') }}</span>
                                            </template>

                                            <button @click="deleteLeave(leave.id)" class="text-red-600 hover:text-red-950 font-semibold text-xs">{{ __('Delete') }}</button>
                                        </template>
                                    </div>
                                </TableBody>
                            </TableRow>
                            <tr v-if="leaves.data.length === 0">
                                <td colspan="9" class="px-6 py-10 text-center text-gray-500 dark:text-gray-400">
                                    {{ __('No leave records found.') }}
                                </td>
                            </tr>
                        </template>
                    </Table>
                </Card>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
