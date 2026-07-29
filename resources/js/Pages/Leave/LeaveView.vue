<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {Head, Link, router} from '@inertiajs/vue3';
import Card from "@/Components/Card.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import {__} from "@/Composables/useTranslations.js";
import Swal from "sweetalert2";
import {computed} from "vue";

const props = defineProps({
    leave: Object,
});

const employeeCode = computed(() => {
    return props.leave.employee.employee_code || ('EM-' + (props.leave.employee.device_employee_id || props.leave.employee.id));
});

const designation = computed(() => {
    const ep = props.leave.employee.employee_positions?.[0];
    return ep?.position?.name ?? 'N/A';
});

const departmentName = computed(() => {
    return props.leave.employee.department?.name ?? 'N/A';
});

const attachmentName = computed(() => {
    if (!props.leave.attachment_path) return '';
    return props.leave.attachment_path.split('/').pop();
});

const deleteLeave = () => {
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
            router.delete(route('leaves.destroy', props.leave.id), {
                onSuccess: () => {
                    Swal.fire(__('Deleted!'), __('Leave record has been deleted.'), 'success');
                }
            });
        }
    });
};

const isEndDatePassed = computed(() => {
    if (!props.leave?.end_date) return false;
    const [year, month, day] = props.leave.end_date.split('-').map(Number);
    const end = new Date(year, month - 1, day);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    return end < today;
});

const approveLeave = () => {
    if (isEndDatePassed.value) {
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
            router.put(route('leaves.approve', props.leave.id), {
                notes: result.value
            }, {
                onSuccess: () => {
                    Swal.fire(__('Approved!'), __('Leave request approved successfully.'), 'success');
                }
            });
        }
    });
};

const rejectLeave = () => {
    if (isEndDatePassed.value) {
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
            router.put(route('leaves.reject', props.leave.id), {
                notes: result.value
            }, {
                onSuccess: () => {
                    Swal.fire(__('Rejected!'), __('Leave request rejected successfully.'), 'success');
                }
            });
        }
    });
};

const statusClass = computed(() => {
    if (props.leave.status === 'Approved') return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400 border-green-200 dark:border-green-850';
    if (props.leave.status === 'Rejected') return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400 border-red-200 dark:border-red-850';
    return 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400 border-amber-200 dark:border-amber-850';
});
</script>

<template>
    <Head :title="__('Leave Details')"/>
    <AuthenticatedLayout>
        <div class="py-8">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <Card class="p-6">
                    <!-- Title & Navigation -->
                    <div class="flex justify-between items-center mb-6 border-b border-gray-200 dark:border-gray-700 pb-4">
                        <div>
                            <h1 class="card-header !mb-1">{{ __('Leave Details') }}</h1>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ __('Applied on') }}: {{ new Date(leave.created_at).toLocaleDateString() }}
                            </p>
                        </div>
                        <Link :href="route('leaves.index')" class="text-purple-600 dark:text-purple-400 hover:underline font-semibold text-sm">
                            &larr; {{ __('Back to List') }}
                        </Link>
                    </div>

                    <!-- Info Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <!-- Employee Info Section -->
                        <div class="space-y-4">
                            <h2 class="text-sm font-bold uppercase tracking-wider text-gray-400 border-b dark:border-gray-700 pb-1">
                                {{ __('Employee Information') }}
                            </h2>
                            <div class="grid grid-cols-2 gap-3 text-sm text-gray-700 dark:text-gray-300">
                                <span class="font-medium text-gray-500">{{ __('Employee Code') }}:</span>
                                <span class="font-bold text-gray-900 dark:text-white">{{ employeeCode }}</span>

                                <span class="font-medium text-gray-500">{{ __('Full Name') }}:</span>
                                <span class="font-bold text-gray-900 dark:text-white">{{ leave.employee.name }}</span>

                                <span class="font-medium text-gray-500">{{ __('Department') }}:</span>
                                <span>{{ departmentName }}</span>

                                <span class="font-medium text-gray-500">{{ __('Designation') }}:</span>
                                <span>{{ designation }}</span>
                            </div>
                        </div>

                        <!-- Leave Details Section -->
                        <div class="space-y-4">
                            <h2 class="text-sm font-bold uppercase tracking-wider text-gray-400 border-b dark:border-gray-700 pb-1">
                                {{ __('Leave Request Info') }}
                            </h2>
                            <div class="grid grid-cols-2 gap-3 text-sm text-gray-700 dark:text-gray-300">
                                <span class="font-medium text-gray-500">{{ __('Leave Type') }}:</span>
                                <span class="font-bold text-purple-600 dark:text-purple-400">{{ leave.leave_type }}</span>

                                <span class="font-medium text-gray-500">{{ __('Leave Duration') }}:</span>
                                <span class="font-medium">
                                    {{ leave.start_date }} {{ __('to') }} {{ leave.end_date }}
                                </span>

                                <span class="font-medium text-gray-500">{{ __('Total Days') }}:</span>
                                <span class="font-extrabold text-base text-gray-950 dark:text-white">
                                    {{ leave.total_days }} {{ __('Days') }}
                                    <span v-if="leave.half_day" class="text-xs text-amber-600 font-normal">({{ __('Half Day') }})</span>
                                </span>

                                <span class="font-medium text-gray-500">{{ __('Status') }}:</span>
                                <div>
                                    <span class="px-2.5 py-0.5 text-xs font-semibold rounded-full border" :class="statusClass">
                                        {{ leave.status }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Reason & Remarks Section -->
                    <div class="mt-8 space-y-6 border-t border-gray-200 dark:border-gray-700 pt-6">
                        <!-- Reason -->
                        <div>
                            <h3 class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-2">
                                {{ __('Reason for Leave') }}
                            </h3>
                            <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded border border-gray-200 dark:border-gray-700 text-sm text-gray-800 dark:text-gray-200 whitespace-pre-wrap">
                                {{ leave.reason }}
                            </div>
                        </div>

                        <!-- Notes / Approval Remarks -->
                        <div v-if="leave.notes || leave.status !== 'Pending'">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-2">
                                {{ __('Remarks / Approval Notes') }}
                            </h3>
                            <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded border border-gray-200 dark:border-gray-700 text-sm text-gray-800 dark:text-gray-200 whitespace-pre-wrap">
                                <p v-if="leave.notes">{{ leave.notes }}</p>
                                <p v-else class="text-gray-400 italic">{{ __('No remarks provided.') }}</p>
                            </div>
                        </div>

                        <!-- Attachment Section -->
                        <div v-if="leave.attachment_path">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-2">
                                {{ __('Supporting Document / Attachment') }}
                            </h3>
                            <div class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-gray-800 rounded border border-gray-200 dark:border-gray-700 text-sm text-gray-800 dark:text-gray-200">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 text-gray-400 shrink-0">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m18.375 12.739-7.693 7.693a4.5 4.5 0 0 1-6.364-6.364l10.94-10.94A3 3 0 1 1 19.5 7.372L8.552 18.32m.009-.01-.01.01m5.699-9.941-7.81 7.81a1.5 1.5 0 0 0 2.112 2.13" />
                                </svg>
                                <span class="font-medium truncate flex-grow">{{ attachmentName }}</span>
                                <a 
                                    :href="'/storage/' + leave.attachment_path" 
                                    target="_blank"
                                    download
                                    class="py-1 px-3 bg-purple-600 hover:bg-purple-700 text-white font-semibold text-xs rounded transition shrink-0"
                                >
                                    {{ __('Download') }}
                                </a>
                            </div>
                        </div>

                        <!-- Audit Info -->
                        <div v-if="leave.status !== 'Pending'" class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs text-gray-500 border-t dark:border-gray-700 pt-4">
                            <p><strong>{{ __('Approved/Rejected By') }}:</strong> {{ leave.approver ? leave.approver.name : __('System') }}</p>
                            <p v-if="leave.approved_at"><strong>{{ __('Actioned Date') }}:</strong> {{ new Date(leave.approved_at).toLocaleString() }}</p>
                        </div>
                    </div>

                    <!-- Actions Panel -->
                    <div v-if="$page.props.auth.user.roles.includes('admin')" class="flex flex-wrap justify-between items-center gap-3 border-t border-gray-200 dark:border-gray-700 pt-6 mt-6">
                        <div class="flex gap-2">
                            <!-- Approve/Reject buttons when status is Pending -->
                            <template v-if="leave.status === 'Pending'">
                                <button 
                                    v-if="!isEndDatePassed"
                                    @click="approveLeave" 
                                    class="py-2 px-5 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-sm rounded shadow transition duration-150"
                                >
                                    {{ __('Approve Request') }}
                                </button>
                                <button 
                                    v-else
                                    disabled
                                    class="py-2 px-5 bg-gray-400 text-white font-semibold text-sm rounded cursor-not-allowed opacity-60"
                                    :title="__('Cannot approve after end date has passed')"
                                >
                                    {{ __('Approve Request') }}
                                </button>
                                <button 
                                    v-if="!isEndDatePassed"
                                    @click="rejectLeave" 
                                    class="py-2 px-5 bg-rose-600 hover:bg-rose-700 text-white font-semibold text-sm rounded shadow transition duration-150"
                                >
                                    {{ __('Reject Request') }}
                                </button>
                                <button 
                                    v-else
                                    disabled
                                    class="py-2 px-5 bg-gray-400 text-white font-semibold text-sm rounded cursor-not-allowed opacity-60"
                                    :title="__('Cannot reject after end date has passed')"
                                >
                                    {{ __('Reject Request') }}
                                </button>
                            </template>
                        </div>

                        <div class="flex gap-2">
                            <Link 
                                v-if="!isEndDatePassed"
                                :href="route('leaves.edit', leave.id)" 
                                class="py-2 px-4 border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300 rounded font-semibold text-sm hover:bg-gray-50 dark:hover:bg-gray-800 transition"
                            >
                                {{ __('Edit Record') }}
                            </Link>
                            <span 
                                v-else
                                class="py-2 px-4 border border-gray-300 dark:border-gray-700 text-gray-400 rounded font-semibold text-sm cursor-not-allowed opacity-60"
                                :title="__('Cannot edit after end date has passed')"
                            >
                                {{ __('Edit Record') }}
                            </span>
                            <button 
                                @click="deleteLeave" 
                                class="py-2 px-4 bg-red-600 hover:bg-red-700 text-white font-semibold text-sm rounded shadow transition duration-150"
                            >
                                {{ __('Delete Record') }}
                            </button>
                        </div>
                    </div>
                </Card>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
