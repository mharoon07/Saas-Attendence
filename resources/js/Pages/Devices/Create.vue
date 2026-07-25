<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {Head, useForm} from '@inertiajs/vue3';
import {ref} from 'vue';
import OrgTabs from "@/Components/Tabs/OrgTabs.vue";
import {useToast} from "vue-toastification";
import InputLabel from "@/Components/InputLabel.vue";
import TextInput from "@/Components/TextInput.vue";
import InputError from "@/Components/InputError.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import Card from "@/Components/Card.vue";
import Swal from "sweetalert2";
import axios from "axios";
import {__} from "@/Composables/useTranslations.js";

const toast = useToast();
const waitingForDevice = ref(false);

const form = useForm({
    name: '',
    serial_number: '',
    ip_address: '',
    description: '',
});

const setValidationErrors = (errors) => {
    Object.entries(errors).forEach(([field, messages]) => {
        form.setError(field, Array.isArray(messages) ? messages[0] : messages);
    });
};

const waitForDeviceConnection = (deviceId, since) => new Promise((resolve, reject) => {
    const timeoutMs = 5 * 60 * 1000;
    const startedAt = Date.now();

    const interval = window.setInterval(async () => {
        try {
            const response = await axios.get(route('devices.connection-status', {device: deviceId}), {
                params: {since},
                headers: {Accept: 'application/json'},
            });

            if (response.data.connected) {
                window.clearInterval(interval);
                resolve(response.data);
                return;
            }

            if (Date.now() - startedAt > timeoutMs) {
                window.clearInterval(interval);
                reject(new Error('timeout'));
            }
        } catch (error) {
            window.clearInterval(interval);
            reject(error);
        }
    }, 2000);
});

const storeDeviceAndWait = async () => {
    waitingForDevice.value = true;
    form.clearErrors();
    const waitStartedAt = new Date().toISOString();

    Swal.fire({
        title: __('Waiting For Device Confirmation'),
        html: `
            <div class="text-left" style="color: #111827 !important;">
                <p style="color: #111827 !important;">Enter the server address on the physical device, then press <strong style="color: #000000 !important;">Confirm (OK)</strong>.</p>
                <p class="mt-3" style="color: #111827 !important;">The app will continue automatically when the device contacts the server.</p>
            </div>
        `,
        customClass: {
            title: '!text-gray-900',
            htmlContainer: '!text-gray-900',
        },
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: () => {
            Swal.showLoading();
        },
    });

    try {
        const response = await axios.post(route('devices.store'), {
            name: form.name,
            serial_number: form.serial_number,
            ip_address: form.ip_address,
            description: form.description,
        }, {
            headers: {Accept: 'application/json'},
        });

        await waitForDeviceConnection(response.data.device.id, waitStartedAt);

        await Swal.fire({
            title: __('Device Connected Successfully'),
            text: __('The physical device has contacted the server.'),
            icon: 'success',
            confirmButtonText: __('Go to Devices'),
            customClass: {
                title: '!text-gray-900',
                htmlContainer: '!text-gray-900',
                confirmButton: 'text-white bg-purple-700 hover:bg-purple-800 focus:outline-none focus:ring-4 focus:ring-purple-300 font-medium rounded-full text-sm px-5 py-2.5 text-center mb-2',
            },
            buttonsStyling: false,
        });

        window.location.href = route('devices.index');
    } catch (error) {
        Swal.close();

        if (error.response?.status === 422) {
            setValidationErrors(error.response.data.errors ?? {});
            toast.error(__('Error Creating Device'));
            return;
        }

        if (error.message === 'timeout') {
            await Swal.fire({
                title: __('Still Waiting For Device'),
                text: __('The device was saved, but it has not contacted the server yet. Check the server address on the physical device and press Confirm (OK).'),
                icon: 'warning',
                confirmButtonText: __('OK'),
                customClass: {
                    title: '!text-gray-900',
                    htmlContainer: '!text-gray-900',
                },
            });
            return;
        }

        toast.error(__('Error Creating Device'));
    } finally {
        waitingForDevice.value = false;
    }
};

const submit = () => {
    Swal.fire({
        title: __('Physical Device Setup'),
        html: `
            <div class="text-left" style="color: #111827 !important;">
                <p class="mb-3" style="color: #111827 !important;">Before adding this device, configure the physical device:</p>
                <ol class="list-decimal pl-5 space-y-2" style="color: #111827 !important;">
                    <li style="color: #111827 !important;">Go to <strong style="color: #000000 !important;">Main Menu</strong>.</li>
                    <li style="color: #111827 !important;">Open the <strong style="color: #000000 !important;">COMM.</strong> page.</li>
                    <li style="color: #111827 !important;">Open <strong style="color: #000000 !important;">Cloud Server Setting</strong>.</li>
                    <li style="color: #111827 !important;">Set <strong style="color: #000000 !important;">Server Mode</strong> to <strong style="color: #000000 !important;">ADMS</strong>.</li>
                    <li style="color: #111827 !important;">Set <strong style="color: #000000 !important;">Enable Domain Name</strong> to <strong style="color: #000000 !important;">Active</strong>.</li>
                    <li style="color: #111827 !important;">Set <strong style="color: #000000 !important;">Server Address</strong> to:<br><code class="select-all bg-gray-100 px-2 py-0.5 rounded" style="color: #111827 !important;">peru-spider-275736.hostingersite.com</code></li>
                </ol>
            </div>
        `,
        icon: 'info',
        showCancelButton: true,
        confirmButtonText: __('Add Device'),
        cancelButtonText: __('Cancel'),
        reverseButtons: true,
        customClass: {
            title: '!text-gray-900',
            htmlContainer: '!text-gray-900',
            popup: '!text-gray-900',
            confirmButton: 'mx-4 text-white bg-purple-700 hover:bg-purple-800 focus:outline-none focus:ring-4 focus:ring-purple-300 font-medium rounded-full text-sm px-5 py-2.5 text-center mb-2',
            cancelButton: 'text-white bg-gray-600 hover:bg-gray-700 focus:outline-none focus:ring-4 focus:ring-gray-300 font-medium rounded-full text-sm px-5 py-2.5 text-center mb-2'
        },
        buttonsStyling: false
    }).then((result) => {
        if (result.isConfirmed) {
            storeDeviceAndWait();
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
                            <PrimaryButton class="ltr:ml-4 rtl:mr-4" :class="{ 'opacity-25': waitingForDevice }"
                                           :disabled="waitingForDevice">
                                {{__('Add Device')}}
                            </PrimaryButton>
                        </div>
                    </form>
                </Card>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
