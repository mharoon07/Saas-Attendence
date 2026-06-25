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
import Card from "@/Components/Card.vue";
import {Switch} from "@headlessui/vue";
import {__} from "@/Composables/useTranslations.js";

const props = defineProps({
    device: Object,
});

const form = useForm({
    name: props.device.name,
    serial_number: props.device.serial_number,
    ip_address: props.device.ip_address ?? '',
    description: props.device.description ?? '',
    is_active: props.device.is_active ? true : false,
});

const submit = () => {
    form.put(route('devices.update', {id: props.device.id}), {
        preserveScroll: true,
        onError: () => {
            useToast().error(__('Error Editing Device'));
        },
        onSuccess: () => {
            useToast().success(__('Device Edited Successfully'));
        }
    });
};

const destroy = () => {
    const swalWithBootstrapButtons = Swal.mixin({
        customClass: {
            confirmButton: 'mx-4 text-white bg-red-700 hover:bg-red-800 focus:outline-none focus:ring-4 focus:ring-red-300 font-medium rounded-full text-sm px-5 py-2.5 text-center mr-2 mb-2 dark:bg-red-600 dark:hover:bg-red-700 dark:focus:ring-red-900',
            cancelButton: 'text-white bg-purple-700 hover:bg-purple-800 focus:outline-none focus:ring-4 focus:ring-purple-300 font-medium rounded-full text-sm px-5 py-2.5 text-center mb-2 dark:bg-purple-600 dark:hover:bg-purple-700 dark:focus:ring-purple-900'
        },
        buttonsStyling: false
    });
    
    swalWithBootstrapButtons.fire({
        title: __('Are you sure you want to delete this device?'),
        text: __('You won\'t be able to revert this!'),
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: __('Yes, Delete!'),
        cancelButtonText:  __('No, Cancel!'),
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            form.delete(route('devices.destroy', {id: props.device.id}), {
                preserveScroll: true,
                onSuccess: () => {
                    Swal.fire(__('Device Removed!'), '', 'success');
                },
            });
        }
    });
};
</script>

<template>
    <Head :title="__('Device Edit')"/>
    <AuthenticatedLayout>
        <template #tabs>
            <OrgTabs/>
        </template>
        <div class="py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <Card class="!mt-0">
                    <h1 class="card-header !mb-4">{{__('Edit Device Details')}}</h1>
                    <form @submit.prevent="submit">
                        <div>
                            <InputLabel for="name" :value="__('Device Name')"/>
                            <TextInput
                                id="name"
                                type="text"
                                class="mt-1 block w-full"
                                :class="{'border border-red-500': form.errors.name}"
                                v-model="form.name"
                                required
                                autocomplete="off"
                                :placeholder="__('Main Gate Face Reader')"
                            />
                            <InputError class="mt-2" :message="form.errors.name"/>
                        </div>
                        <div class="mt-4">
                            <InputLabel for="serial_number" :value="__('Serial Number (SN)')"/>
                            <TextInput
                                id="serial_number"
                                type="text"
                                class="mt-1 block w-full"
                                :class="{'border border-red-500': form.errors.serial_number}"
                                v-model="form.serial_number"
                                required
                                autocomplete="off"
                                :placeholder="__('e.g. 651016140026')"
                            />
                            <InputError class="mt-2" :message="form.errors.serial_number"/>
                        </div>
                        <div class="mt-4">
                            <InputLabel for="ip_address" :value="__('IP Address')"/>
                            <TextInput
                                id="ip_address"
                                type="text"
                                class="mt-1 block w-full"
                                :class="{'border border-red-500': form.errors.ip_address}"
                                v-model="form.ip_address"
                                autocomplete="off"
                                :placeholder="__('e.g. 192.168.1.150')"
                            />
                            <InputError class="mt-2" :message="form.errors.ip_address"/>
                        </div>
                        <div class="mt-4">
                            <InputLabel for="description" :value="__('Device Description')"/>
                            <TextInput
                                id="description"
                                type="text"
                                class="mt-1 block w-full"
                                :class="{'border border-red-500': form.errors.description}"
                                v-model="form.description"
                                autocomplete="off"
                                :placeholder="__('Located at primary office building entrance.')"
                            />
                            <InputError class="mt-2" :message="form.errors.description"/>
                        </div>
                        
                        <div class="mt-4">
                            <InputLabel class="inline" for="is_active" :value="__('Is Active / Enabled?')"/>
                            <div class="block">
                                <Switch dir="ltr"
                                    v-model="form.is_active"
                                    :class="form.is_active ? 'bg-purple-600' : 'bg-gray-400'"
                                    class="col-span-4 mt-2 relative inline-flex h-6 w-11 items-center rounded-full"
                                >
                                    <span
                                        :class="form.is_active ? 'translate-x-6' : 'translate-x-1'"
                                        class="inline-block h-4 w-4 transform rounded-full bg-white transition"
                                    />
                                </Switch>
                            </div>
                            <InputError class="mt-2" :message="form.errors.is_active"/>
                        </div>

                        <div class="flex items-center justify-end mt-4">
                            <PrimaryButton type="button" @click="destroy" class="bg-red-600 hover:bg-red-700 ml-4">
                                {{__('Delete Device')}}
                            </PrimaryButton>
                            <PrimaryButton class="ltr:ml-4 rtl:mr-4" :class="{ 'opacity-25': form.processing }"
                                           :disabled="form.processing">
                                {{__('Edit Device')}}
                            </PrimaryButton>
                        </div>
                    </form>
                </Card>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
