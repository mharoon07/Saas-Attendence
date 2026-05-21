<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {Head, useForm} from '@inertiajs/vue3';
import {useToast} from "vue-toastification";
import InputLabel from "@/Components/InputLabel.vue";
import TextInput from "@/Components/TextInput.vue";
import InputError from "@/Components/InputError.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import Card from "@/Components/Card.vue";
import {__} from "@/Composables/useTranslations.js";

const props = defineProps({
    nextCode: {
        type: String,
        default: '',
    },
});

const form = useForm({
    department: '',
    code: props.nextCode,
});

const submit = () => {
    form.post(route('stocky-departments.store'), {
        preserveScroll: true,
        onError: () => {
            useToast().error(__('Error Creating Department'));
        },
        onSuccess: () => {
            useToast().success(__('Department Created Successfully'));
            form.reset({
                department: '',
                code: props.nextCode,
            });
        }
    });
};
</script>

<template>
    <Head :title="__('Create Department')"/>
    <AuthenticatedLayout>
        <div class="py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <Card class="!mt-0">
                    <h1 class="card-header !mb-4">{{__('Add A Department')}}</h1>
                    <form @submit.prevent="submit">
                        <div class="grid grid-cols-2 gap-4 mt-4">
                            <div>
                                <InputLabel for="department" :value="__('Department Name')"/>
                                <TextInput
                                    id="department"
                                    type="text"
                                    class="mt-1 block w-full"
                                    :class="{'border border-red-500': form.errors.department}"
                                    v-model="form.department"
                                    required
                                    autocomplete="off"
                                    :placeholder="__('Sales')"
                                />
                                <InputError class="mt-2" :message="form.errors.department"/>
                            </div>
                            <div>
                                <InputLabel for="code" :value="__('Department Code (Auto-generated)')"/>
                                <TextInput
                                    id="code"
                                    type="text"
                                    class="mt-1 block w-full bg-gray-100 dark:bg-gray-800 cursor-not-allowed"
                                    :class="{'border border-red-500': form.errors.code}"
                                    v-model="form.code"
                                    required
                                    disabled
                                    autocomplete="off"
                                    :placeholder="__('DP01')"
                                />
                                <InputError class="mt-2" :message="form.errors.code"/>
                            </div>
                        </div>
                        <div class="flex items-center justify-end mt-4">
                            <PrimaryButton class="ltr:ml-4 rtl:mr-4" :class="{ 'opacity-25': form.processing }"
                                           :disabled="form.processing">
                                {{__('Add Department')}}
                            </PrimaryButton>
                        </div>
                    </form>
                </Card>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
