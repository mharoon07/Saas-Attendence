<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {Head, router} from '@inertiajs/vue3';
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
import {inject, ref} from "vue";
import Card from "@/Components/Card.vue";
import Modal from "@/Components/Modal.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import SearchableSelect from "@/Components/SearchableSelect.vue";
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    loans: Object,
    employees: Array,
});

const showAddModal = ref(false);
const showEditModal = ref(false);

const addForm = useForm({
    employee_id: '',
    loan_amount: '',
    deduction_percentage: '',
    date: '',
    status: 'active',
});

const editForm = useForm({
    id: null,
    employee_id: '',
    loan_amount: '',
    deduction_percentage: '',
    date: '',
    status: 'active',
});

const addLoan = () => {
    addForm.post(route('loans.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showAddModal.value = false;
            addForm.reset();
        },
    });
};

const openEditModal = (loan) => {
    editForm.id = loan.id;
    editForm.employee_id = loan.employee_id;
    editForm.loan_amount = loan.total_amount;
    editForm.deduction_percentage = loan.deduction_percentage;
    editForm.date = loan.date;
    editForm.status = loan.status;
    showEditModal.value = true;
};

const updateLoan = () => {
    editForm.put(route('loans.update', editForm.id), {
        preserveScroll: true,
        onSuccess: () => {
            showEditModal.value = false;
            editForm.reset();
        },
    });
};

const deleteLoan = (loanId) => {
    if (confirm('Are you sure you want to delete this loan record?')) {
        router.delete(route('loans.destroy', loanId), {
            preserveScroll: true,
        });
    }
};
</script>

<template>
    <Head :title="__('Employee Loans')"/>
    <AuthenticatedLayout>
        <div class="py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <Card class="!mt-0">
                    <div class="flex justify-between items-center !mb-4">
                        <h1 class="card-header !mb-0">{{__('Employee Loans')}}</h1>
                        <PrimaryButton @click="showAddModal = true">
                            {{__('Add Loan')}}
                        </PrimaryButton>
                    </div>

                    <Table :links="loans.links" :showingNumber="loans.data.length" :totalNumber="loans.total">
                        <template #Head>
                            <TableHead>{{__('ID')}}</TableHead>
                            <TableHead>{{__('Employee Name')}}</TableHead>
                            <TableHead>{{__('Total Amount')}}</TableHead>
                            <TableHead>{{__('Deduction %')}}</TableHead>
                            <TableHead>{{__('Paid Amount')}}</TableHead>
                            <TableHead>{{__('Remaining Balance')}}</TableHead>
                            <TableHead>{{__('Status')}}</TableHead>
                            <TableHead>{{__('Action')}}</TableHead>
                        </template>
                         <template #Body>
                            <TableRow v-for="loan in loans.data" :key="loan.id">
                                <TableBodyHeader>{{loan.id}}</TableBodyHeader>
                                <TableBodyHeader>{{loan.employee.name}}</TableBodyHeader>
                                <TableBody>{{loan.total_amount}}</TableBody>
                                <TableBody>{{loan.deduction_percentage}}%</TableBody>
                                <TableBody>{{loan.paid_amount}}</TableBody>
                                <TableBody>{{loan.remaining_balance}}</TableBody>
                                <TableBody>{{loan.status}}</TableBody>
                                <td class="px-6 py-4 flex items-center gap-2">
                                    <button @click="openEditModal(loan)" class="text-purple-600 hover:underline font-semibold mr-2">{{__('Edit')}}</button>
                                    <button @click="deleteLoan(loan.id)" class="text-red-600 hover:underline font-semibold">{{__('Delete')}}</button>
                                </td>
                            </TableRow>
                        </template>
                    </Table>
                </Card>
            </div>
        </div>

        <!-- Add Loan Modal -->
        <Modal :show="showAddModal" @close="showAddModal = false">
            <div class="p-6 dark:bg-gray-800">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    {{ __('Add Employee Loan') }}
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

                    <div>
                        <InputLabel for="loan_amount" :value="__('Loan Amount')" />
                        <TextInput
                            id="loan_amount"
                            type="number"
                            v-model="addForm.loan_amount"
                            class="mt-1 block w-full"
                            required
                        />
                        <InputError class="mt-2" :message="addForm.errors.loan_amount" />
                    </div>

                    <div>
                        <InputLabel for="deduction_percentage" :value="__('Loan Deduction Percentage (%)')" />
                        <TextInput
                            id="deduction_percentage"
                            type="number"
                            v-model="addForm.deduction_percentage"
                            class="mt-1 block w-full"
                            required
                        />
                        <InputError class="mt-2" :message="addForm.errors.deduction_percentage" />
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
                            teleport="body"
                            required
                        ></VueDatePicker>
                        <InputError class="mt-2" :message="addForm.errors.date" />
                    </div>

                    <div>
                        <InputLabel for="status" :value="__('Status')" />
                        <select
                            id="status"
                            v-model="addForm.status"
                            class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-purple-500 dark:focus:border-purple-600 focus:ring-purple-500 dark:focus:ring-purple-600 rounded-md shadow-sm py-1 block w-full mt-1"
                        >
                            <option value="active">{{ __('Active') }}</option>
                            <option value="completed">{{ __('Completed') }}</option>
                        </select>
                        <InputError class="mt-2" :message="addForm.errors.status" />
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
                        @click="addLoan"
                    >
                        {{ __('Save') }}
                    </PrimaryButton>
                </div>
            </div>
        </Modal>

        <!-- Edit Loan Modal -->
        <Modal :show="showEditModal" @close="showEditModal = false">
            <div class="p-6 dark:bg-gray-800">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    {{ __('Edit Employee Loan') }}
                </h2>     
                <div class="mt-6 space-y-4">
                    <div>
                        <InputLabel for="edit_employee_id" :value="__('Employee')" />
                        <SearchableSelect
                            v-model="editForm.employee_id"
                            :options="employees"
                            :placeholder="__('Search or select employee...')"
                            class="mt-1"
                        />
                        <InputError class="mt-2" :message="editForm.errors.employee_id" />
                    </div>

                    <div>
                        <InputLabel for="edit_loan_amount" :value="__('Loan Amount')" />
                        <TextInput
                            id="edit_loan_amount"
                            type="number"
                            v-model="editForm.loan_amount"
                            class="mt-1 block w-full"
                            required
                        />
                        <InputError class="mt-2" :message="editForm.errors.loan_amount" />
                    </div>

                    <div>
                        <InputLabel for="edit_deduction_percentage" :value="__('Loan Deduction Percentage (%)')" />
                        <TextInput
                            id="edit_deduction_percentage"
                            type="number"
                            v-model="editForm.deduction_percentage"
                            class="mt-1 block w-full"
                            required
                        />
                        <InputError class="mt-2" :message="editForm.errors.deduction_percentage" />
                    </div>

                    <div>
                        <InputLabel for="edit_date" :value="__('Date')" />
                        <VueDatePicker
                            id="edit_date"
                            v-model="editForm.date"
                            class="py-1 block w-full"
                            :enable-time-picker="false"
                            model-type="yyyy-MM-dd"
                            :dark="inject('isDark').value"
                            teleport="body"
                            required
                        ></VueDatePicker>
                        <InputError class="mt-2" :message="editForm.errors.date" />
                    </div>

                    <div>
                        <InputLabel for="edit_status" :value="__('Status')" />
                        <select
                            id="edit_status"
                            v-model="editForm.status"
                            class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-purple-500 dark:focus:border-purple-600 focus:ring-purple-500 dark:focus:ring-purple-600 rounded-md shadow-sm py-1 block w-full mt-1"
                        >
                            <option value="active">{{ __('Active') }}</option>
                            <option value="completed">{{ __('Completed') }}</option>
                        </select>
                        <InputError class="mt-2" :message="editForm.errors.status" />
                    </div>
                </div>
                
                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="showEditModal = false">
                        {{ __('Cancel') }}
                    </SecondaryButton>

                    <PrimaryButton
                        class="ms-3"
                        :class="{ 'opacity-25': editForm.processing }"
                        :disabled="editForm.processing"
                        @click="updateLoan"
                    >
                        {{ __('Update') }}
                    </PrimaryButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
