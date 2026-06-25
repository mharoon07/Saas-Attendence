<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {Head, router} from '@inertiajs/vue3';
import DataTable from "@/Components/DataTable.vue";
import SearchBar from "@/Components/SearchBar.vue";
import FlexButton from "@/Components/FlexButton.vue";
import OrgTabs from "@/Components/Tabs/OrgTabs.vue";
import {ref, watch} from "vue";
import debounce from "lodash.debounce";
import Card from "@/Components/Card.vue";
import PlusIcon from "@/Components/Icons/PlusIcon.vue";

const term = ref('');
const search = debounce(() => {
    router.visit(route('devices.index', {term: term.value}),
        {preserveState: true, preserveScroll: true})
}, 500);
watch(term, search);

defineProps({
    devices: Object,
});
</script>
<template>
    <Head :title="__('Devices')"/>
    <AuthenticatedLayout>
        <template #tabs>
            <OrgTabs/>
        </template>
        <div class="py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <Card class="!mt-0">
                    <h1 class="card-header !mb-4">{{ __('Registered Devices') }}</h1>
                    <div class="flex justify-between items-center mb-4 gap-4">
                        <FlexButton :href="route('devices.create')" :text="__('Add Device')">
                            <PlusIcon/>
                        </FlexButton>
                        <SearchBar>
                            <input type="text" id="table-search-users" v-model="term"
                                   class="input-class"
                                   :placeholder="__('Search for a device')">
                        </SearchBar>
                    </div>

                    <DataTable
                        :controller="'devices'"
                        :head='[__("ID"), __("Name"), __("Serial Number"), __("IP Address")]'
                        :data="devices"
                        :hasActions="true"
                        :hasLink="true"
                    ></DataTable>
                </Card>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
