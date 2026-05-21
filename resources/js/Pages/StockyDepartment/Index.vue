<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {Head, router} from '@inertiajs/vue3';
import DataTable from "@/Components/DataTable.vue";
import SearchBar from "@/Components/SearchBar.vue";
import {ref, watch} from "vue";
import debounce from "lodash.debounce";
import Card from "@/Components/Card.vue";
import FlexButton from "@/Components/FlexButton.vue";
import PlusIcon from "@/Components/Icons/PlusIcon.vue";

const props = defineProps({
    departments: Object,
    filters: Object,
});

const term = ref(props.filters.term || '');
const search = debounce(() => {
    router.visit(route('stocky-departments.index', {term: term.value}),
        {preserveState: true, preserveScroll: true})
}, 500);
watch(term, search);
</script>

<template>
    <Head :title="__('Departments')"/>
    <AuthenticatedLayout>
        <div class="py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <Card class="!mt-0">
                    <h1 class="card-header !mb-4">{{ __(' Departments') }}</h1>
                    <div class="flex justify-between items-center mb-4 gap-4">
                        <FlexButton :href="route('stocky-departments.create')" :text="__('Add A Department')">
                            <PlusIcon/>
                        </FlexButton>
                        <SearchBar>
                            <input type="text" id="table-search-departments" v-model="term"
                                   class="input-class"
                                   :placeholder="__('Search departments...')">
                        </SearchBar>
                    </div>

                    <DataTable
                        :controller="'stocky-departments'"
                        :head='[__("ID"), __("Name"), __("Code")]'
                        :data="departments"
                        :hasActions="false"
                        :hasLink="false"
                    ></DataTable>
                </Card>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
