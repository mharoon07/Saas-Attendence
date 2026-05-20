<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { onMounted, ref } from "vue";

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

onMounted(() => {
    document.documentElement.classList.remove('dark');
});

const form = useForm({
    email: 'super@root.com',
    password: 'password',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};

const autofill = (email, password) => {
    form.email = email;
    form.password = password;
};
</script>

<template>
    <Head title="Log in" />

    <div class="min-h-screen flex items-center justify-center bg-slate-950 relative overflow-hidden font-sans">
        <!-- Abstract glowing background blobs -->
        <div class="absolute top-1/4 left-1/4 w-96 h-96 bg-violet-600 rounded-full mix-blend-multiply filter blur-[128px] opacity-20 animate-pulse"></div>
        <div class="absolute bottom-1/4 right-1/4 w-96 h-96 bg-indigo-600 rounded-full mix-blend-multiply filter blur-[128px] opacity-20 animate-pulse" style="animation-delay: 2s;"></div>

        <div class="w-full max-w-md mx-4 z-10">
            <!-- Glassmorphism Card -->
            <div class="bg-slate-900/60 backdrop-blur-xl border border-slate-800/80 p-8 rounded-2xl shadow-2xl relative overflow-hidden transition-all duration-300 hover:border-slate-700/80">
                
                <!-- Logo & Title -->
                <div class="text-center mb-8">
                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-tr from-violet-600 to-indigo-500 shadow-lg shadow-violet-500/25 mb-4">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 009 11.5c0-.704-.088-1.39-.254-2.05m-2.44 3.377a13.921 13.921 0 011.02-3.224m3.44 2.04l.054-.09a13.916 13.916 0 002-7.3c0-.705-.088-1.39-.253-2.05m-2.44 3.377a13.921 13.921 0 001.02-3.224M15 11.5a13.916 13.916 0 00-2-7.3m2 7.3a13.916 13.916 0 01-2 7.3m3.44-2.04l.054-.09a13.912 13.912 0 001.996-5.93m-2.44 3.377a13.921 13.921 0 001.02-3.224M21 11.813A13.937 13.937 0 0118 16m2.44-3.377a13.921 13.921 0 001.02-3.224M18 10a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </div>
                    <h1 class="text-3xl font-extrabold tracking-tight text-white mb-2">
                        HRM <span class="bg-clip-text text-transparent bg-gradient-to-r from-violet-400 to-indigo-400">Attendance</span>
                    </h1>
                    <p class="text-slate-400 text-sm">Please log in to manage your attendance and records</p>
                </div>

                <!-- Status alert -->
                <div v-if="status" class="mb-6 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-sm font-medium">
                    {{ status }}
                </div>

                <!-- Form -->
                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Email -->
                    <div>
                        <label class="block text-slate-300 text-xs font-semibold uppercase tracking-wider mb-2" for="email">Email Address</label>
                        <div class="relative">
                            <input
                                id="email"
                                type="email"
                                class="w-full px-4 py-3 bg-slate-950/80 border border-slate-800 rounded-xl text-slate-100 placeholder-slate-500 focus:outline-none focus:border-violet-500 focus:ring-1 focus:ring-violet-500 transition-all"
                                placeholder="name@company.com"
                                v-model="form.email"
                                required
                                autofocus
                                autocomplete="username"
                            />
                        </div>
                        <InputError class="mt-2" :message="form.errors.email"/>
                    </div>

                    <!-- Password -->
                    <div>
                        <div class="flex justify-between items-center mb-2">
                            <label class="block text-slate-300 text-xs font-semibold uppercase tracking-wider" for="password">Password</label>
                            <Link
                                v-if="canResetPassword"
                                :href="route('password.request')"
                                class="text-xs text-violet-400 hover:text-violet-300 transition-colors"
                            >
                                Forgot password?
                            </Link>
                        </div>
                        <input
                            id="password"
                            type="password"
                            class="w-full px-4 py-3 bg-slate-950/80 border border-slate-800 rounded-xl text-slate-100 placeholder-slate-500 focus:outline-none focus:border-violet-500 focus:ring-1 focus:ring-violet-500 transition-all"
                            placeholder="••••••••"
                            v-model="form.password"
                            required
                            autocomplete="current-password"
                        />
                        <InputError class="mt-2" :message="form.errors.password"/>
                    </div>

                    <!-- Remember me -->
                    <div class="flex items-center">
                        <label class="flex items-center cursor-pointer select-none">
                            <Checkbox name="remember" v-model:checked="form.remember" class="border-slate-800 bg-slate-950 text-violet-600 focus:ring-violet-500" />
                            <span class="ml-2.5 text-slate-400 text-sm">Keep me logged in</span>
                        </label>
                    </div>

                    <!-- Submit -->
                    <button
                        type="submit"
                        class="w-full py-3.5 bg-gradient-to-r from-violet-600 to-indigo-600 hover:from-violet-500 hover:to-indigo-500 active:from-violet-700 active:to-indigo-700 text-white font-bold rounded-xl shadow-lg shadow-violet-600/20 hover:shadow-violet-600/30 transition-all duration-150 transform hover:-translate-y-0.5 active:translate-y-0 flex items-center justify-center space-x-2"
                        :class="{ 'opacity-50 pointer-events-none': form.processing }"
                        :disabled="form.processing"
                    >
                        <span v-if="form.processing" class="w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                        <span v-else>Log In</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>
