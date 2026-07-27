<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {Head, Link, router} from '@inertiajs/vue3';
import EmployeeTabs from "@/Components/Tabs/EmployeeTabs.vue";
import SearchBar from "@/Components/SearchBar.vue";
import FlexButton from "@/Components/FlexButton.vue";
import {ref, watch} from "vue";
import debounce from "lodash.debounce";
import Table from "@/Components/Table/Table.vue";
import TableHead from "@/Components/Table/TableHead.vue";
import TableBody from "@/Components/Table/TableBody.vue";
import TableBodyHeader from "@/Components/Table/TableBodyHeader.vue";
import TableRow from "@/Components/Table/TableRow.vue";
import Card from "@/Components/Card.vue";
import AddUserIcon from "@/Components/Icons/AddUserIcon.vue";
import Swal from "sweetalert2";
import {useToast} from "vue-toastification";
import {__} from "@/Composables/useTranslations.js";

const term = ref('');
const sort = ref('id');
const sort_dir = ref(true);
const search = debounce(() => {
    router.visit(route('employees.index', {term: term.value, sort: sort.value, sort_dir: sort_dir.value}),
        {preserveState: true, preserveScroll: true})
}, 400);
watch(term, search);
watch(sort, search);
watch(sort_dir, search);

const props = defineProps({
    employees: Object,
});

const destroy = (id) => {
    const swalWithBootstrapButtons = Swal.mixin({
        customClass: {
            confirmButton: 'mx-4 text-white bg-red-700 hover:bg-red-800 focus:outline-none focus:ring-4 focus:ring-red-300 font-medium rounded-full text-sm px-5 py-2.5 text-center mr-2 mb-2 dark:bg-red-600 dark:hover:bg-red-700 dark:focus:ring-red-900',
            cancelButton: 'text-white bg-purple-700 hover:bg-purple-800 focus:outline-none focus:ring-4 focus:ring-purple-300 font-medium rounded-full text-sm px-5 py-2.5 text-center mb-2 dark:bg-purple-600 dark:hover:bg-purple-700 dark:focus:ring-purple-900'
        },
        buttonsStyling: false
    });
    swalWithBootstrapButtons.fire({
        title: __('Are you sure?'),
        text: __('You won\'t be able to revert this!'),
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: __('Yes, Delete!'),
        cancelButtonText: __('No, Cancel!'),
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('employees.destroy', { id: id }), {
                preserveScroll: true,
                onError: (errors) => {
                    if (errors && errors.Error) {
                        useToast().error(errors.Error);
                    } else {
                        useToast().error(__('Error Removing Employee'));
                    }
                },
                onSuccess: () => {
                    Swal.fire(__('Employee Removed!'), '', 'success');
                },
            });
        }
    });
};

</script>

<template>
    <Head :title="__('Employees')"/>
    <AuthenticatedLayout>
        <template #tabs>
            <EmployeeTabs />
        </template>
        <div class="py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <Card class="!mt-0">
                    <h1 class="card-header !mb-4">{{__('Current Employees')}}</h1>
                    <div class="flex justify-between items-center gap-4 pb-4">
                        <FlexButton :href="route('employees.create')" :text="__('Add Employee')">
                            <AddUserIcon/>
                        </FlexButton>
                        <SearchBar>
                            <input type="text" id="table-search-users" v-model="term"
                                   class="input-class"
                                   :placeholder="__('Search for a user')">
                        </SearchBar>
                    </div>

                    <Table :links="employees.links" :showingNumber="employees.data.length" :totalNumber="employees.total">
                        <template #Head>
                            <TableHead @click="sort='id'; sort_dir = !sort_dir;" sortable>{{__('ID')}} ↕</TableHead>
                            <TableHead @click="sort='name'; sort_dir = !sort_dir;" sortable>{{__('Name')}} ↕</TableHead>
                            <TableHead>{{__('Email')}}</TableHead>
                            <TableHead>{{__('Phone')}}</TableHead>
                            <TableHead>{{__('National ID')}}</TableHead>
                            <TableHead>{{__('Action')}}</TableHead>
                        </template>

                        <!--Iterate Here-->
                        <template #Body>
                            <TableRow v-for="employee in employees.data" :key="employee.id">
                                <TableBodyHeader :href="route('employees.show', {id: employee.id})">{{employee.employee_code || ('EM-' + (employee.device_employee_id || employee.id))}}</TableBodyHeader>
                                <TableBodyHeader :href="route('employees.show', {id: employee.id})" >{{employee.name}}</TableBodyHeader>
                                <TableBody :href="route('employees.show', {id: employee.id})">{{employee.email}}</TableBody>
                                <TableBody :href="route('employees.show', {id: employee.id})">{{employee.phone}}</TableBody>
                                <TableBody :href="route('employees.show', {id: employee.id})">{{employee.national_id}}</TableBody>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <Link :href="route('employees.edit', {id: employee.id})" class="font-medium text-purple-600 dark:text-purple-500 hover:underline ltr:mr-3 rtl:ml-3">
                                        {{__('Edit')}}
                                    </Link>
                                    <button @click.prevent="destroy(employee.id)" type="button" class="font-medium text-red-600 dark:text-red-500 hover:underline">
                                        {{__('Delete')}}
                                    </button>
                                </td>
                            </TableRow>
                        </template>
                    </Table>
                </Card>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
