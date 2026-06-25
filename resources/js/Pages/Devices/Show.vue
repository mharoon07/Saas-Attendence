<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {Head} from '@inertiajs/vue3';
import FlexButton from "@/Components/FlexButton.vue";
import OrgTabs from "@/Components/Tabs/OrgTabs.vue";
import Card from "@/Components/Card.vue";
import ModifyIcon from "@/Components/Icons/ModifyIcon.vue";
import DescriptionList from "@/Components/DescriptionList/DescriptionList.vue";
import DT from "@/Components/DescriptionList/DT.vue";
import DD from "@/Components/DescriptionList/DD.vue";
import DescriptionListItem from "@/Components/DescriptionList/DescriptionListItem.vue";
import {__} from "@/Composables/useTranslations.js";

const props = defineProps({
    device: Object,
});
</script>

<template>
    <Head :title="__('Device Details')"/>
    <AuthenticatedLayout>
        <template #tabs>
            <OrgTabs/>
        </template>
        <div class="py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <Card class="!mt-0">
                    <div>
                        <div class="flex justify-between items-center">
                            <h1 class="card-header !mb-4">{{__('Device Details')}}</h1>
                            <div class="flex gap-4">
                                <FlexButton
                                    :text="__('Modify Device Data')"
                                    :href="route('devices.edit', {id: props.device.id})"
                                >
                                    <ModifyIcon/>
                                </FlexButton>
                            </div>
                        </div>

                        <h2 class="mb-2 ml-1 font-semibold">{{__('Basic Info')}}</h2>
                        <DescriptionList>
                            <DescriptionListItem colored>
                                <DT>{{__('Name')}}</DT>
                                <DD>{{ device.name }}</DD>
                            </DescriptionListItem>

                            <DescriptionListItem colored>
                                <DT>{{__('ID')}}</DT>
                                <DD>{{ device.id }}</DD>
                            </DescriptionListItem>

                            <DescriptionListItem>
                                <DT>{{__('Serial Number (SN)')}}</DT>
                                <DD>{{ device.serial_number }}</DD>
                            </DescriptionListItem>

                            <DescriptionListItem>
                                <DT>{{__('IP Address')}}</DT>
                                <DD>{{ device.ip_address ?? __('N/A') }}</DD>
                            </DescriptionListItem>

                            <DescriptionListItem colored>
                                <DT>{{__('Description')}}</DT>
                                <DD>{{ device.description ?? __('N/A') }}</DD>
                            </DescriptionListItem>

                            <DescriptionListItem colored>
                                <DT>{{__('Status')}}</DT>
                                <DD>
                                    <span v-if="device.is_active" class="text-green-600 font-semibold">{{ __('Active') }}</span>
                                    <span v-else class="text-red-600 font-semibold">{{ __('Disabled') }}</span>
                                </DD>
                            </DescriptionListItem>
                        </DescriptionList>
                    </div>
                </Card>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
