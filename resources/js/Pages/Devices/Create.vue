<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {Head, useForm} from '@inertiajs/vue3';
import OrgTabs from "@/Components/Tabs/OrgTabs.vue";
import {useToast} from "vue-toastification";
import InputLabel from "@/Components/InputLabel.vue";
import TextInput from "@/Components/TextInput.vue";
import InputError from "@/Components/InputError.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import Card from "@/Components/Card.vue";
import {__} from "@/Composables/useTranslations.js";

const form = useForm({
    name: '',
    serial_number: '',
    ip_address: '',
    description: '',
});

const submit = () => {
    form.post(route('devices.store'), {
        preserveScroll: true,
        onError: () => {
            useToast().error(__('Error Creating Device'));
        },
        onSuccess: () => {
            useToast().success(__('Device Created Successfully'));
            form.reset();
        }
    });
};
</script>
<template>
    <Head :title="__('Device Create')"/>
    <AuthenticatedLayout>
        <template #tabs>
            <OrgTabs/>
        </template>
        <div class="py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <Card class="!mt-0">
                    <h1 class="card-header !mb-4">{{__('Add A Device')}}</h1>
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
                        <div class="flex items-center justify-end mt-4">
                            <PrimaryButton class="ltr:ml-4 rtl:mr-4" :class="{ 'opacity-25': form.processing }"
                                           :disabled="form.processing">
                                {{__('Add Device')}}
                            </PrimaryButton>
                        </div>
                    </form>
                </Card>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
