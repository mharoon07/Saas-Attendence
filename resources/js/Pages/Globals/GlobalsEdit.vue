<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {Head, useForm} from '@inertiajs/vue3';
import OrgTabs from "@/Components/Tabs/OrgTabs.vue";
import {useToast} from "vue-toastification";
import InputLabel from "@/Components/InputLabel.vue";
import TextInput from "@/Components/TextInput.vue";
import InputError from "@/Components/InputError.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import Swal from "sweetalert2";
import '@vuepic/vue-datepicker/dist/main.css'
import {onMounted} from "vue";
import {initTooltips} from "flowbite";
import ToolTip from "@/Components/ToolTip.vue";
import {Switch} from "@headlessui/vue";
import Card from "@/Components/Card.vue";
import Notice from "@/Components/Notice.vue";
import {__} from "@/Composables/useTranslations.js";


const props = defineProps({
    globals: Object,
})

const form = useForm({
    organization_name: props.globals.organization_name,
    timezone: props.globals.timezone,
    is_ip_based: props.globals.is_ip_based,
    ip: props.globals.is_ip_based ? JSON.parse(props.globals.ip).join(', ') : '',
    email: props.globals.email,
    organization_address: props.globals.organization_address,
    absence_limit: props.globals.absence_limit,
    late_threshold_minutes: props.globals.late_threshold_minutes,
    payroll_day: props.globals.payroll_day,
    weekend_off_days: JSON.parse(props.globals.weekend_off_days),
    income_tax: props.globals.income_tax,
});

const submitConfirm = () => {
    const swalWithBootstrapButtons = Swal.mixin({
        customClass: {
            confirmButton: 'mx-4 text-white bg-purple-700 hover:bg-purple-800 focus:outline-none focus:ring-4 focus:ring-purple-300 font-medium rounded-full text-sm px-5 py-2.5 text-center mr-2 mb-2 dark:bg-purple-600 dark:hover:bg-purple-700 dark:focus:ring-purple-900',
            cancelButton: 'text-white bg-gray-500 hover:bg-gray-600 focus:outline-none focus:ring-4 focus:ring-gray-300 font-medium rounded-full text-sm px-5 py-2.5 text-center mb-2 dark:bg-gray-600 dark:hover:bg-gray-700 dark:focus:ring-gray-900'
        },
        buttonsStyling: false
    })
    swalWithBootstrapButtons.fire({
        title: __('Are you sure?'),
        text: __('Globals data is central to this application data integrity!'),
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: __('Yes, Confirm!'),
        cancelButtonText:  __('No, Cancel!'),
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            if (form.ip !== null && !Array.isArray(form.ip)) {
                form.ip = form.ip.split(',')
                    .map((val) => val.trim())
                    .filter((val) => val !== null && val !== '');
            }
            form.put(route('globals.update'), {
                preserveScroll: true,
                onError: () => {
                    useToast().error(__('Error Editing Globals'));
                },
                onSuccess: () => {
                    Swal.fire(__('Global Data Changed Successfully'), '', 'success')
                }
            });
        }
    })
};

async function fetchIP() {
    form.ip = "Fetching IP, Please Wait...";
    fetch("https://api.ipify.org/?format=json")
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            // Handle the data
            form.ip = data['ip'];
        })
        .catch(error => {
            // Handle errors
            console.error('Error fetching IP:', error.message);
        });
}

onMounted(() => {
    initTooltips();
});

</script>

<template>
    <Head :title="__('Edit Organization Data')"/>
    <AuthenticatedLayout>
        <template #tabs>
            <OrgTabs/>
        </template>
        <div class="py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <Card class="!mt-0">
                    <h1 class="card-header !mb-6">{{__('Edit Organization Data')}}</h1>
                    <Notice type="warning" :bold="__('Warning!')" br
                            :text="__('Changing timezone in production can cause unexepected behavior in the attendance system') + '. ' +
                             __('Please revise the attendance list of the current week after changing the timezone') +'.'"/>
                    <form @submit.prevent="submitConfirm">
                        <div>
                            <InputLabel for="organization_name" :value="__('Organization Name')"/>
                            <TextInput
                                id="organization_name"
                                type="text"
                                class="mt-1 block w-full"
                                :class="{'border border-red-500': form.errors.organization_name}"
                                v-model="form.organization_name"
                                required
                                autocomplete="off"
                                :placeholder="__('Organization Name')"
                            />
                            <InputError class="mt-2" :message="form.errors.organization_name"/>
                        </div>
                        <div class="mt-2">
                            <InputLabel for="timezone" :value="__('Time Zone') " class="inline"/>
                            <a class="underline text-purple-500 text-sm" target="_blank"
                               href="https://en.wikipedia.org/wiki/List_of_tz_database_time_zones">{{' ' + __('(list)')}}</a>
                            <ToolTip>
                                {{__('This is the timezone that will be used to calculate the time related data in the application.')}}
                                <br/>
                                {{__('Please choose a timezone from the list.')}}
                            </ToolTip>
                            <TextInput
                                id="timezone"
                                type="text"
                                class="mt-1 block w-full"
                                :class="{'border border-red-500': form.errors.timezone}"
                                v-model="form.timezone"
                                required
                                autocomplete="off"
                                placeholder="Africa/Cairo"
                            />
                            <InputError class="mt-2" :message="form.errors.timezone"/>
                        </div>


                        <div class="mt-2">
                            <InputLabel for="email" :value="__('Organization Email')"/>
                            <TextInput
                                id="email"
                                type="text"
                                class="mt-1 block w-full"
                                :class="{'border border-red-500': form.errors.email}"
                                v-model="form.email"
                                required
                                autocomplete="off"
                                placeholder="ceo@oraby.com"
                            />
                            <InputError class="mt-2" :message="form.errors.email"/>
                        </div>
                        <div class="mt-2">
                            <InputLabel for="organization_address" :value="__('Organization Address')"/>
                            <TextInput
                                id="organization_address"
                                type="text"
                                class="mt-1 block w-full"
                                :class="{'border border-red-500': form.errors.organization_address}"
                                v-model="form.organization_address"
                                required
                                autocomplete="off"
                                :placeholder="__('114 Juhayna Sq. 6th of October City, Giza, Egypt')"
                            />
                            <InputError class="mt-2" :message="form.errors.organization_address"/>
                        </div>
                        <div class="mt-2">
                            <InputLabel for="absence_limit" :value="__('Absence Limit')"/>
                            <TextInput
                                id="absence_limit"
                                type="number"
                                min="1"
                                max="365"
                                step="1"
                                class="mt-1 block w-full"
                                :class="{'border border-red-500': form.errors.absence_limit}"
                                v-model="form.absence_limit"
                                required
                                autocomplete="off"
                                placeholder="8"
                            />
                            <InputError class="mt-2" :message="form.errors.absence_limit"/>
                        </div>
                        <div class="mt-2">
                            <InputLabel for="payroll_day" :value="__('Payroll Day')" class="inline"/>
                            <ToolTip>
                                {{__('Which day should the salaries be paid on. Payrolls will be generated automatically that day on 00:00.')}}
                                 <br />
                                {{__('The generated payrolls account for the previous month\'s work, from day 1 until the end of the previous month.')}}
                            </ToolTip>
                            <TextInput
                                id="payroll_day"
                                type="number"
                                min="1"
                                max="31"
                                step="1"
                                class="mt-1 block w-full"
                                :class="{'border border-red-500': form.errors.payroll_day}"
                                v-model="form.payroll_day"
                                required
                                autocomplete="off"
                                placeholder="8"
                            />
                            <InputError class="mt-2" :message="form.errors.payroll_day"/>
                        </div>
                        <div class="mt-2">
                            <InputLabel for="income_tax" :value="__('Income Tax Percentage')"/>
                            <TextInput
                                id="income_tax"
                                type="number"
                                min="0"
                                max="100"
                                step="0.1"
                                class="mt-1 block w-full"
                                :class="{'border border-red-500': form.errors.income_tax}"
                                v-model="form.income_tax"
                                required
                                autocomplete="off"
                                placeholder="14"
                            />
                            <InputError class="mt-2" :message="form.errors.income_tax"/>
                        </div>
                        <div class="mt-2">
                            <InputLabel for="late_threshold_minutes" :value="__('Attendance Late Threshold (Minutes)')" class="inline"/>
                            <ToolTip>
                                {{__('The number of minutes after the shift start time that an employee can sign in and still be considered on time.')}}
                            </ToolTip>
                            <TextInput
                                id="late_threshold_minutes"
                                type="number"
                                min="0"
                                max="1440"
                                step="1"
                                class="mt-1 block w-full"
                                :class="{'border border-red-500': form.errors.late_threshold_minutes}"
                                v-model="form.late_threshold_minutes"
                                required
                                autocomplete="off"
                                placeholder="15"
                            />
                            <InputError class="mt-2" :message="form.errors.late_threshold_minutes"/>
                        </div>

                        <div class="flex items-center justify-end mt-4">
                            <PrimaryButton class="ltr:ml-4 rtl:mr-4" :class="{ 'opacity-25': form.processing }"
                                           :disabled="form.processing">
                                {{__('Edit Data')}}
                            </PrimaryButton>
                        </div>
                    </form>
                </Card>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
