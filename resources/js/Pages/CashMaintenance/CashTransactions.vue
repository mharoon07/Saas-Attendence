<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {Head, useForm, router} from '@inertiajs/vue3';
import Table from "@/Components/Table/Table.vue";
import TableHead from "@/Components/Table/TableHead.vue";
import TableBody from "@/Components/Table/TableBody.vue";
import TableBodyHeader from "@/Components/Table/TableBodyHeader.vue";
import TableRow from "@/Components/Table/TableRow.vue";
import VueDatePicker from "@vuepic/vue-datepicker";
import '@vuepic/vue-datepicker/dist/main.css'
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import TextInput from "@/Components/TextInput.vue";
import {inject, ref, watch} from "vue";
import Card from "@/Components/Card.vue";
import Modal from "@/Components/Modal.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import SearchableSelect from "@/Components/SearchableSelect.vue";
import debounce from 'lodash/debounce';

const props = defineProps({
    cashTransactions: Object,
    employees: Array,
    filters: Object,
    totals: Object,
});

const showAddModal = ref(false);

const addForm = useForm({
    employee_id: '',
    transaction_type: 'cash_in',
    amount: '',
    date: '',
    description: '',
    reference: '',
    status: 'pending',
});

const addCashTransaction = () => {
    addForm.post(route('cash-transactions.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showAddModal.value = false;
            addForm.reset();
        },
    });
};

const filterForm = ref({
    employee_id: props.filters.employee_id || '',
    transaction_type: props.filters.transaction_type || '',
    status: props.filters.status || '',
    start_date: props.filters.start_date || '',
    end_date: props.filters.end_date || '',
});

watch(filterForm, debounce((value) => {
    router.get(route('cash-transactions.index'), value, {
        preserveState: true,
        replace: true,
    });
}, 300), {deep: true});

</script>

<template>
    <Head :title="__('Cash Maintenance')"/>
    <AuthenticatedLayout>
        <div class="py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Summary Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                    <Card class="!mt-0 !mb-0 !bg-green-100 dark:!bg-green-900 border-l-4 border-green-500">
                        <div class="flex flex-col">
                            <span class="text-gray-500 dark:text-gray-400 font-medium">{{__('Total Cash In')}}</span>
                            <span class="text-2xl font-bold text-gray-800 dark:text-white">{{ totals.total_cash_in }}</span>
                        </div>
                    </Card>
                    <Card class="!mt-0 !mb-0 !bg-red-100 dark:!bg-red-900 border-l-4 border-red-500">
                        <div class="flex flex-col">
                            <span class="text-gray-500 dark:text-gray-400 font-medium">{{__('Total Cash Out')}}</span>
                            <span class="text-2xl font-bold text-gray-800 dark:text-white">{{ totals.total_cash_out }}</span>
                        </div>
                    </Card>
                    <Card class="!mt-0 !mb-0 !bg-purple-100 dark:!bg-purple-900 border-l-4 border-purple-500">
                        <div class="flex flex-col">
                            <span class="text-gray-500 dark:text-gray-400 font-medium">{{__('Current Balance')}}</span>
                            <span class="text-2xl font-bold" :class="totals.current_balance < 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400'">
                                {{ totals.current_balance }}
                            </span>
                        </div>
                    </Card>
                </div>

                <Card class="!mt-0">
                    <div class="flex justify-between items-center !mb-4">
                        <h1 class="card-header !mb-0">{{__('Employee Cash Transactions')}}</h1>
                        <PrimaryButton @click="showAddModal = true">
                            {{__('Add Transaction')}}
                        </PrimaryButton>
                    </div>

                    <!-- Filters -->
                    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
                        <div v-if="$page.props.auth.user.roles.includes('admin')">
                            <select v-model="filterForm.employee_id" class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-purple-500 focus:ring-purple-500 rounded-md shadow-sm w-full">
                                <option value="">{{__('All Employees')}}</option>
                                <option v-for="emp in employees" :key="emp.id" :value="emp.id">{{ emp.name }}</option>
                            </select>
                        </div>
                        <div>
                            <select v-model="filterForm.transaction_type" class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-purple-500 focus:ring-purple-500 rounded-md shadow-sm w-full">
                                <option value="">{{__('All Types')}}</option>
                                <option value="cash_in">{{__('Cash In')}}</option>
                                <option value="cash_out">{{__('Cash Out')}}</option>
                            </select>
                        </div>
                        <div>
                            <select v-model="filterForm.status" class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-purple-500 focus:ring-purple-500 rounded-md shadow-sm w-full">
                                <option value="">{{__('All Statuses')}}</option>
                                <option value="pending">{{__('Pending')}}</option>
                                <option value="approved">{{__('Approved')}}</option>
                                <option value="rejected">{{__('Rejected')}}</option>
                                <option value="completed">{{__('Completed')}}</option>
                            </select>
                        </div>
                        <div>
                            <VueDatePicker v-model="filterForm.start_date" :enable-time-picker="false" :dark="inject('isDark').value" placeholder="Start Date" format="yyyy-MM-dd" value-format="yyyy-MM-dd" teleport="body" />
                        </div>
                        <div>
                            <VueDatePicker v-model="filterForm.end_date" :enable-time-picker="false" :dark="inject('isDark').value" placeholder="End Date" format="yyyy-MM-dd" value-format="yyyy-MM-dd" teleport="body" />
                        </div>
                    </div>

                    <Table :links="cashTransactions.links" :showingNumber="cashTransactions.data.length" :totalNumber="cashTransactions.total">
                        <template #Head>
                            <TableHead>{{__('ID')}}</TableHead>
                            <TableHead>{{__('Employee')}}</TableHead>
                            <TableHead>{{__('Type')}}</TableHead>
                            <TableHead>{{__('Amount')}}</TableHead>
                            <TableHead>{{__('Date')}}</TableHead>
                            <TableHead>{{__('Description')}}</TableHead>
                            <TableHead>{{__('Reference')}}</TableHead>
                            <TableHead>{{__('Status')}}</TableHead>
                        </template>
                         <template #Body>
                            <TableRow v-for="transaction in cashTransactions.data" :key="transaction.id">
                                <TableBodyHeader>{{transaction.id}}</TableBodyHeader>
                                <TableBodyHeader>{{transaction.employee.name}}</TableBodyHeader>
                                <TableBody>
                                    <span :class="transaction.transaction_type === 'cash_in' ? 'text-green-600 font-semibold' : 'text-red-600 font-semibold'">
                                        {{ transaction.transaction_type === 'cash_in' ? __('Cash In') : __('Cash Out') }}
                                    </span>
                                </TableBody>
                                <TableBody>{{transaction.amount}}</TableBody>
                                <TableBody>{{transaction.date}}</TableBody>
                                <TableBody>{{transaction.description || '-'}}</TableBody>
                                <TableBody>{{transaction.reference || '-'}}</TableBody>
                                <TableBody>
                                    <span class="px-2 py-1 text-xs rounded-full" 
                                        :class="{
                                            'bg-yellow-100 text-yellow-800': transaction.status === 'pending',
                                            'bg-blue-100 text-blue-800': transaction.status === 'approved',
                                            'bg-red-100 text-red-800': transaction.status === 'rejected',
                                            'bg-green-100 text-green-800': transaction.status === 'completed'
                                        }">
                                        {{ transaction.status }}
                                    </span>
                                </TableBody>
                            </TableRow>
                        </template>
                    </Table>
                </Card>
            </div>
        </div>

        <Modal :show="showAddModal" @close="showAddModal = false">
            <div class="p-6 dark:bg-gray-800">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    {{ __('Add Cash Transaction') }}
                </h2>     
                <div class="mt-6 space-y-4">
                    <div>
                        <InputLabel for="employee_id" :value="__('Employee')" />
                        <SearchableSelect
                            v-model="addForm.employee_id"
                            :options="employees"
                            :placeholder="__('Search or select employee...')"
                            class="mt-1"
                        />
                        <InputError class="mt-2" :message="addForm.errors.employee_id" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <InputLabel for="transaction_type" :value="__('Transaction Type')" />
                            <select
                                id="transaction_type"
                                v-model="addForm.transaction_type"
                                class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-purple-500 dark:focus:border-purple-600 focus:ring-purple-500 dark:focus:ring-purple-600 rounded-md shadow-sm py-1 block w-full mt-1"
                            >
                                <option value="cash_in">{{ __('Cash In') }}</option>
                                <option value="cash_out">{{ __('Cash Out') }}</option>
                            </select>
                            <InputError class="mt-2" :message="addForm.errors.transaction_type" />
                        </div>
                        <div>
                            <InputLabel for="amount" :value="__('Amount')" />
                            <TextInput
                                id="amount"
                                type="number"
                                step="0.01"
                                v-model="addForm.amount"
                                class="mt-1 block w-full"
                                required
                            />
                            <InputError class="mt-2" :message="addForm.errors.amount" />
                        </div>
                    </div>

                    <div>
                        <InputLabel for="date" :value="__('Date')" />
                        <VueDatePicker
                            id="date"
                            v-model="addForm.date"
                            class="py-1 block w-full"
                            :enable-time-picker="false"
                            model-type="yyyy-MM-dd"
                            :dark="inject('isDark').value"
                            format="yyyy-MM-dd"
                            value-format="yyyy-MM-dd"
                            teleport="body"
                            required
                        ></VueDatePicker>
                        <InputError class="mt-2" :message="addForm.errors.date" />
                    </div>

                    <div>
                        <InputLabel for="description" :value="__('Description / Note')" />
                        <TextInput
                            id="description"
                            type="text"
                            v-model="addForm.description"
                            class="mt-1 block w-full"
                        />
                        <InputError class="mt-2" :message="addForm.errors.description" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <InputLabel for="reference" :value="__('Reference')" />
                            <TextInput
                                id="reference"
                                type="text"
                                v-model="addForm.reference"
                                class="mt-1 block w-full"
                            />
                            <InputError class="mt-2" :message="addForm.errors.reference" />
                        </div>
                        <div>
                            <InputLabel for="status" :value="__('Status')" />
                            <select
                                id="status"
                                v-model="addForm.status"
                                class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-purple-500 dark:focus:border-purple-600 focus:ring-purple-500 dark:focus:ring-purple-600 rounded-md shadow-sm py-1 block w-full mt-1"
                            >
                                <option value="pending">{{ __('Pending') }}</option>
                                <option value="approved">{{ __('Approved') }}</option>
                                <option value="rejected">{{ __('Rejected') }}</option>
                                <option value="completed">{{ __('Completed') }}</option>
                            </select>
                            <InputError class="mt-2" :message="addForm.errors.status" />
                        </div>
                    </div>
                </div>
                
                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="showAddModal = false">
                        {{ __('Cancel') }}
                    </SecondaryButton>

                    <PrimaryButton
                        class="ms-3"
                        :class="{ 'opacity-25': addForm.processing }"
                        :disabled="addForm.processing"
                        @click="addCashTransaction"
                    >
                        {{ __('Save') }}
                    </PrimaryButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
