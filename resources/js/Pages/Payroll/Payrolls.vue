<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {Head, router} from '@inertiajs/vue3';
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
import {inject, ref, watch} from "vue";
import Card from "@/Components/Card.vue";
import Modal from "@/Components/Modal.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    payrolls: Object,
    dateParam: String,
    statusParam: String,
    employees: Array,
});
const date = ref(new Date(props.dateParam));
if (props.dateParam === '') {
    date.value = '';
}
const status = ref(props.statusParam);
if (props.statusParam === '') {
    status.value = 'all';
}
const filter = (() => {
    const routeParameters = {date: date.value === null ? null : date.value, status: status.value === null ? null : status.value};
    router.visit(route('payrolls.index', routeParameters),
        {preserveState: true, preserveScroll: true})
});
watch(date, filter);
watch(status, filter);
const showGenerateModal = ref(false);
const generateForm = useForm({
    period_start: '',
    period_end: '',
    employee_id: '', 
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
                    <div class="flex flex-col lg:flex-row lg:justify-between lg:items-center gap-4">
                        <div>
                            <InputLabel for="date" :value="__('Filter by Month') +':'"/>
                            <VueDatePicker
                                id="date"
                                v-model="date"
                                class="py-1 block w-full"
                                :placeholder="__('Select a Date...')"
                                :enable-time-picker="false"
                                :max-date="new Date()"
                                month-picker
                                :dark="inject('isDark').value"
                                required
                            ></VueDatePicker>
                            <InputError v-if="Object.keys($page.props.errors).length" class="mt-2" :message="$page.props.errors"/>
                        </div>
                        <div class="w-1/2" dir="ltr">
                            <InputLabel for="date" :value="__('Filter by Status')+':'"/>
                            <ul class="ul-checkbox mb-1">
                                <li class="li-checkbox">
                                    <div class="ul-li-div-radio">
                                        <input id="horizontal-list-radio-all" type="radio" value="all" v-model="status" name="list-radio" class="li-radio-input">
                                        <label for="horizontal-list-radio-all" class="li-radio-label">{{__('All')}}</label>
                                    </div>
                                </li>
                                <li class="li-checkbox">
                                    <div class="ul-li-div-radio">
                                        <input id="horizontal-list-radio-pending" type="radio" value="pending" v-model="status" name="list-radio" class="li-radio-input">
                                        <label for="horizontal-list-radio-pending" class="li-radio-label">{{__('Pending')}}</label>
                                    </div>
                                </li>
                                <li class="li-checkbox">
                                    <div class="ul-li-div-radio">
                                        <input id="horizontal-list-radio-reviewed" type="radio" value="reviewed" v-model="status" name="list-radio" class="li-radio-input">
                                        <label for="horizontal-list-radio-reviewed" class="li-radio-label">{{__('Reviewed')}}</label>
                                    </div>
                                </li>
                                <li class="li-checkbox">
                                    <div class="ul-li-div-radio">
                                        <input id="horizontal-list-radio-paid" type="radio" value="paid" v-model="status" name="list-radio" class="li-radio-input">
                                        <label for="horizontal-list-radio-paid" class="li-radio-label">{{__('Paid')}}</label>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <Table :links="payrolls.links" :showingNumber="payrolls.data.length" :totalNumber="payrolls.total">
                        <template #Head>
                            <TableHead>{{__('Payroll ID')}}</TableHead>
                            <TableHead>{{__('Employee Name')}}</TableHead>
                            <TableHead>{{__('Total Amount')}}</TableHead>
                            <TableHead>{{__('Due Date')}}</TableHead>
                            <TableHead>{{__('Status')}}</TableHead>
                            <TableHead v-if="$page.props.auth.user.roles.includes('admin')">{{__('Action')}}</TableHead>
                        </template>
                         <template #Body>
                            <TableRow v-for="payroll in payrolls.data" :key="payroll.id">
                                <TableBodyHeader :href="route('payrolls.show', {id: payroll.id})">{{payroll.id}}</TableBodyHeader>
                                <TableBodyHeader :href="route('payrolls.show', {id: payroll.id})" >{{payroll.employee_name}}</TableBodyHeader>
                                <TableBody :href="route('payrolls.show', {id: payroll.id})">{{payroll.currency}} {{payroll.total_payable}}</TableBody>
                                <TableBody :href="route('payrolls.show', {id: payroll.id})">{{payroll.due_date}}</TableBody>
                                <TableBody :href="route('payrolls.show', {id: payroll.id})">{{payroll.status  ? "Paid" : (payroll.is_reviewed ? "Reviewed" : "Pending Review")}}</TableBody>
                                <td class="px-6 py-4" v-if="$page.props.auth.user.roles.includes('admin')">
                                    <a :href="route('payrolls.edit', {id: payroll.id})" class="text-purple-600 hover:underline mr-2">{{__('Edit')}}</a>
                                    <a :href="route('payrolls.export', {id: payroll.id})" class="text-green-600 hover:underline">{{__('Download')}}</a>
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
                    {{ __('Generate Payroll manually') }}
                </h2>     
                <div class="mt-6 space-y-4">
                    <div>
                        <InputLabel for="period_start" :value="__('Period Start')" />
                        <VueDatePicker
                            id="period_start"
                            v-model="generateForm.period_start"
                            class="py-1 block w-full"
                            :enable-time-picker="false"
                            :dark="inject('isDark').value"
                            teleport="body"
                            required
                        ></VueDatePicker>
                        <InputError class="mt-2" :message="generateForm.errors.period_start" />
                    </div>
                    <div>
                        <InputLabel for="period_end" :value="__('Period End')" />
                        <VueDatePicker
                            id="period_end"
                            v-model="generateForm.period_end"
                            class="py-1 block w-full"
                            :enable-time-picker="false"
                            :dark="inject('isDark').value"
                            teleport="body"
                            required
                        ></VueDatePicker>
                        <InputError class="mt-2" :message="generateForm.errors.period_end" />
                    </div>
                    <div>
                        <InputLabel for="employee_id" :value="__('Employee (Optional)')" />
                        <select
                            id="employee_id"
                            v-model="generateForm.employee_id"
                            class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-purple-500 dark:focus:border-purple-600 focus:ring-purple-500 dark:focus:ring-purple-600 rounded-md shadow-sm py-1 block w-full mt-1"
                        >
                            <option value="">{{ __('All Employees') }}</option>
                            <option v-for="emp in employees" :key="emp.id" :value="emp.id">
                                {{ emp.name }}
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
