<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {Head} from '@inertiajs/vue3';
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
    advancePayments: Object,
    employees: Array,
});

const showAddModal = ref(false);

const addForm = useForm({
    employee_id: '',
    advance_amount: '',
    date: '',
    status: 'pending',
});

const addAdvancePayment = () => {
    addForm.post(route('advance-payments.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showAddModal.value = false;
            addForm.reset();
        },
    });
};
</script>

<template>
    <Head :title="__('Advance Payments')"/>
    <AuthenticatedLayout>
        <div class="py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <Card class="!mt-0">
                    <div class="flex justify-between items-center !mb-4">
                        <h1 class="card-header !mb-0">{{__('Advance Payments')}}</h1>
                        <PrimaryButton @click="showAddModal = true">
                            {{__('Add Advance Payment')}}
                        </PrimaryButton>
                    </div>

                    <Table :links="advancePayments.links" :showingNumber="advancePayments.data.length" :totalNumber="advancePayments.total">
                        <template #Head>
                            <TableHead>{{__('ID')}}</TableHead>
                            <TableHead>{{__('Employee Name')}}</TableHead>
                            <TableHead>{{__('Advance Amount')}}</TableHead>
                            <TableHead>{{__('Deducted Amount')}}</TableHead>
                            <TableHead>{{__('Remaining Amount')}}</TableHead>
                            <TableHead>{{__('Date')}}</TableHead>
                            <TableHead>{{__('Status')}}</TableHead>
                            <TableHead>{{__('Action')}}</TableHead>
                        </template>
                         <template #Body>
                            <TableRow v-for="payment in advancePayments.data" :key="payment.id">
                                <TableBodyHeader>{{payment.id}}</TableBodyHeader>
                                <TableBodyHeader>{{payment.employee.name}}</TableBodyHeader>
                                <TableBody>{{payment.advance_amount}}</TableBody>
                                <TableBody>{{payment.deducted_amount}}</TableBody>
                                <TableBody>{{payment.remaining_amount}}</TableBody>
                                <TableBody>{{payment.date}}</TableBody>
                                <TableBody>{{payment.status}}</TableBody>
                                <td class="px-6 py-4">
                                    <a href="#" class="text-purple-600 hover:underline mr-2">{{__('Edit')}}</a>
                                </td>
                            </TableRow>
                        </template>
                    </Table>
                </Card>
            </div>
        </div>

        <Modal :show="showAddModal" @close="showAddModal = false">
            <div class="p-6 dark:bg-gray-800">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    {{ __('Add Advance Payment') }}
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
                        <InputLabel for="advance_amount" :value="__('Advance Amount')" />
                        <TextInput
                            id="advance_amount"
                            type="number"
                            v-model="addForm.advance_amount"
                            class="mt-1 block w-full"
                            required
                        />
                        <InputError class="mt-2" :message="addForm.errors.advance_amount" />
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
                            <option value="pending">{{ __('Pending') }}</option>
                            <option value="approved">{{ __('Approved') }}</option>
                            <option value="rejected">{{ __('Rejected') }}</option>
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
                        @click="addAdvancePayment"
                    >
                        {{ __('Save') }}
                    </PrimaryButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
