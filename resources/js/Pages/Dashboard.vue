<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {Head, useForm} from '@inertiajs/vue3';
import NavLink from "@/Components/NavLink.vue";
import GoBackNavLink from "@/Components/GoBackNavLink.vue";
import Card from "@/Components/Card.vue";
import IconCard from "@/Components/IconCard.vue";
import MoneyIcon from "@/Components/Icons/MoneyIcon.vue";
import CalendarIcon from "@/Components/Icons/CalendarIcon.vue";
import TableIcon from "@/Components/Icons/TableIcon.vue";
import MessageIcon from "@/Components/Icons/MessageIcon.vue";
import {__} from "@/Composables/useTranslations.js";

// Calendar imports
import {CalendarView, CalendarViewHeader} from "vue-simple-calendar";
import "@/../../node_modules/vue-simple-calendar/dist/style.css";
import "@/../../node_modules/vue-simple-calendar/dist/css/default.css";
import "@/../../node_modules/vue-simple-calendar/dist/css/holidays-us.css";
import {computed, inject, onMounted, ref} from "vue";
import {useToast} from "vue-toastification";
import InputLabel from "@/Components/InputLabel.vue";
import InputError from "@/Components/InputError.vue";
import VueDatePicker from "@vuepic/vue-datepicker";
import '@vuepic/vue-datepicker/dist/main.css';
import TextInput from "@/Components/TextInput.vue";
import PlusIcon from "@/Components/Icons/PlusIcon.vue";
import {calendar_types} from "@/Composables/useCalendarItemTypes.js";
import dayjs from "dayjs";

const props = defineProps({
    calendarItems: Object,
    leaveRequests: Object,
    leaveStats: Object,
});

// Admin Public Holiday quick creator form
const form = useForm({
    date: '',
    title: '',
    type: 'holiday', // default to public holiday
});

const submitForm = () => {
    Object.keys(form.date).forEach(function (key) {
        if (form.date[key] && !/^\d{4}-\d{2}-\d{2}$/.test(form.date[key])){
            form.date[key] = dayjs(form.date[key]).format('YYYY-MM-DD');
        }
    });
    form.post(route('calendars.store'), {
        preserveScroll: true,
        onError: () => {
            useToast().error(__('Error Creating Holiday Item'));
        },
        onSuccess: () => {
            useToast().success(__('Holiday Stored Successfully'));
            form.reset();
        }
    });
};

const items = computed(() => {
    const obj1 = props.calendarItems.map(item => {
        const {start_date, end_date, id, title, type} = item;
        return {
            id: id,
            title: "[" + calendar_types[type].toUpperCase() + "] " + title,
            startDate: start_date,
            endDate: end_date
        };
    });
    const obj2 = props.leaveRequests.map(item => {
        const {start_date, end_date, id} = item;
        return {
            id: id,
            title: __('[ :type ] :name', {type: __('Approved Leave'), name: item.employee.name}),
            startDate: start_date,
            endDate: end_date
        };
    });
    return obj1.concat(obj2);
});

const themeClasses = computed(() => {
    return {
        "theme-default": true,
        "holiday-us-traditional": false,
        "holiday-us-official": false,
    }
});

const showDate = ref(new Date());
function setShowDate(d) {
    showDate.value = d;
}

onMounted(() => {
    setShowDate(new Date());

    // Highlight leave requests and public holidays in styling
    const element = document.querySelectorAll('.cv-item');
    for (let i = 0; i < element.length; i++) {
        const content = element[i].textContent;
        if (content.includes("[Approved Leave]")) {
            element[i].style.setProperty('background-color', '#ffc5c5', 'important');
        }
        else if (content.includes("["+calendar_types['holiday'].toUpperCase()+"]")) {
            element[i].style.setProperty('background-color', '#c7f6ca', 'important');
            element[i].style.setProperty('font-weight', 'bold', 'important');
        }
        else if (content.includes("["+calendar_types['meeting'].toUpperCase()+"]")) {
            element[i].style.setProperty('background-color', '#c5d4ff', 'important');
        }
        else if (content.includes("["+calendar_types['event'].toUpperCase()+"]")) {
            element[i].style.setProperty('background-color', '#bce6ef', 'important');
        }
        else if (content.includes("["+calendar_types['other'].toUpperCase()+"]")) {
            element[i].style.setProperty('background-color', '#ffe1c5', 'important');
        }
    }
});
</script>

<template>
    <Head :title="__('Dashboard')"/>
    <AuthenticatedLayout>
        <template #tabs>
            <GoBackNavLink/>
            <NavLink :href="route('dashboard.index')" :active="route().current('dashboard.index')">
                {{ __('Dashboard') }}
            </NavLink>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <!-- Welcome Card -->
                <div class="flex justify-between gap-4">
                    <Card class="w-full !mt-0">
                        <h1 class="!card-header !mb-0">
                            {{ __('Welcome, :name', {name: $page.props.auth.user.name}) }}!</h1>
                    </Card>
                </div>

                <!-- LEAVE SUMMARY CARDS -->
                <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                    <Card class="p-4 bg-white dark:bg-gray-800 text-center shadow-sm">
                        <p class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400 font-bold">{{ __('Total Leaves') }}</p>
                        <h3 class="text-3xl font-extrabold mt-1 text-purple-600 dark:text-purple-400">{{ leaveStats.total }}</h3>
                    </Card>
                    <Card class="p-4 bg-white dark:bg-gray-800 text-center shadow-sm">
                        <p class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400 font-bold">{{ __('Pending Leaves') }}</p>
                        <h3 class="text-3xl font-extrabold mt-1 text-amber-500">{{ leaveStats.pending }}</h3>
                    </Card>
                    <Card class="p-4 bg-white dark:bg-gray-800 text-center shadow-sm">
                        <p class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400 font-bold">{{ __('Approved Leaves') }}</p>
                        <h3 class="text-3xl font-extrabold mt-1 text-green-500">{{ leaveStats.approved }}</h3>
                    </Card>
                    <Card class="p-4 bg-white dark:bg-gray-800 text-center shadow-sm">
                        <p class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400 font-bold">{{ __('Rejected Leaves') }}</p>
                        <h3 class="text-3xl font-extrabold mt-1 text-red-500">{{ leaveStats.rejected }}</h3>
                    </Card>
                    <Card class="p-4 bg-white dark:bg-gray-800 text-center shadow-sm col-span-2 md:col-span-1">
                        <p class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400 font-bold">{{ __('Currently On Leave') }}</p>
                        <h3 class="text-3xl font-extrabold mt-1 text-blue-500">{{ leaveStats.currently_on_leave }}</h3>
                    </Card>
                </div>

                <!-- QUICK ACTIONS -->
                <div class="flex flex-col md:flex-row justify-between md:gap-4 mt-6">
                    <Card class="!p-2 w-full">
                        <h1 class="text-2xl">{{ __('Quick Actions') }}</h1>
                        <div class="flex flex-wrap justify-center gap-4">

                            <Card class="w-full lg:w-1/4 !shadow-none !overflow-visible flex-1 " :fancy-p="false">
                                <IconCard :heading="__('Payrolls')" :cta-text="__('Go To Payments')"
                                          :href="route('payrolls.index')">
                                    <MoneyIcon class="!mb-4 !h-12 !w-12 text-purple-500"/>
                                </IconCard>
                            </Card>

                            <Card class="w-full lg:w-1/4 !shadow-none !overflow-visible flex-1 " :fancy-p="false">
                                <IconCard :heading="__('Attendance')" :cta-text="__('Go to Attendance')"
                                          :href="route('attendance.dashboard')">
                                    <TableIcon class="!mb-4 !h-12 !w-12 text-purple-500"/>
                                </IconCard>
                            </Card>

                            <Card class="w-full lg:w-1/4 !shadow-none !overflow-visible flex-1 " :fancy-p="false">
                                <IconCard :heading="__('Calendar')" :cta-text="__('Go to Calendar')"
                                          :href="route('calendar.index')">
                                    <CalendarIcon class="!mb-4 !h-12 !w-12 text-purple-500"/>
                                </IconCard>
                            </Card>

                            <Card class="w-full lg:w-1/4 !shadow-none !overflow-visible flex-1 " :fancy-p="false">
                                <IconCard :heading="__('Reports')" :cta-text="__('Go To Reports')"
                                          :href="route('reports.index')">
                                    <TableIcon class="!mb-4 !h-12 !w-12 text-purple-500"/>
                                </IconCard>
                            </Card>
                        </div>
                    </Card>
                </div>

                <!-- CALENDAR SECTION -->
                <Card :fancy-p="false" class="p-6">
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 pb-4 border-b border-gray-200 dark:border-gray-800">
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Company Calendar') }}</h2>
                        
                        <!-- Quick Holiday creator for admin only -->
                        <div v-if="$page.props.auth.user.roles.includes('admin')" class="w-full md:w-auto">
                            <form @submit.prevent="submitForm" class="flex flex-col sm:flex-row items-stretch sm:items-end gap-3">
                                <div class="w-full sm:w-64">
                                    <InputLabel for="date" :value="__('Select Date / Date Range')"/>
                                    <VueDatePicker
                                        id="date"
                                        v-model="form.date"
                                        class="block w-full text-sm"
                                        :class="{'border border-red-500': form.errors.date}"
                                        :placeholder="__('Select Date...')"
                                        :enable-time-picker="false"
                                        range
                                        :dark="inject('isDark').value"
                                        required
                                    ></VueDatePicker>
                                    <InputError class="mt-1" :message="form.errors.date"/>
                                </div>
                                <div class="w-full sm:w-60">
                                    <InputLabel for="title" :value="__('Holiday Title')"/>
                                    <TextInput
                                        id="title"
                                        type="text"
                                        class="block w-full p-2 text-sm border-gray-300 rounded focus:border-purple-500 focus:ring-purple-500 dark:bg-gray-800 dark:border-gray-700 dark:text-white"
                                        :class="{'border border-red-500': form.errors.title}"
                                        v-model="form.title"
                                        autocomplete="off"
                                        :placeholder="__('Eid Public Holiday')"
                                        required
                                    />
                                    <InputError class="mt-1" :message="form.errors.title"/>
                                </div>
                                <button
                                    class="h-10 text-white bg-purple-600 hover:bg-purple-700 border border-purple-600 focus:ring-2 focus:outline-none focus:ring-purple-500 rounded px-5 text-sm font-semibold flex items-center justify-center gap-1 dark:focus:ring-purple-600 dark:bg-purple-800 dark:border-purple-800 dark:hover:bg-purple-700 w-full sm:w-auto"
                                >
                                    <PlusIcon class="w-4 h-4"/>
                                    {{__('Add Public Holiday')}}
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Calendar view -->
                    <div class="fancy-p mt-4 overflow-hidden rounded-lg">
                        <calendar-view
                            dir="ltr"
                            class="!dark:text-black text-gray-900"
                            :items="items"
                            :show-date="showDate"
                            :startingDayOfWeek="6"
                            :class="themeClasses"
                            style="min-height: 28em;"
                        >
                            <template #header="{ headerProps }">
                                <calendar-view-header
                                    :header-props="headerProps"
                                    @input="setShowDate" />
                            </template>
                        </calendar-view>
                    </div>
                </Card>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style>
.theme-default .cv-day.today {
    background-color: #d9c161 !important;
}
.currentPeriod:after {
    content: ' (Click to return to current month)' !important;
}
.theme-default .cv-day.past {
    background-color: rgba(10, 192, 109, 0.05) !important;
}
.theme-default .cv-header button {
    background-color: white !important;
    border: 1px solid #e5e7eb !important;
    color: #374151 !important;
}
.theme-default .cv-day.past:after {
    content: '✅️' !important;
    padding-top: 0.25em !important;
    opacity: 60% !important;
}
.theme-default .cv-day.outsideOfMonth {
    background-color: rgba(135, 135, 135, 0.1) !important;
}
.theme-default .cv-header, .theme-default .cv-header-day {
    background-color: rgba(175, 175, 175, 0.15) !important;
    color: inherit !important;
    font-weight: bold !important;
}
.cv-item {
    color: black !important;
}
</style>
