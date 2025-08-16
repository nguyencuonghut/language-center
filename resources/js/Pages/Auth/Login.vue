<script setup>
import { ref, onMounted } from 'vue';
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});


const form = useForm({
        email: '',
        password: '',
        remember: false,
});

// Dark mode state for toggle button
const isDark = ref(false);
function applyTheme(next) {
    const html = document.documentElement;
    next ? html.classList.add('dark') : html.classList.remove('dark');
    isDark.value = next;
    localStorage.setItem('theme', next ? 'dark' : 'light');
}
onMounted(() => {
    const saved = localStorage.getItem('theme');
    const initial = saved ? saved === 'dark' : (window.matchMedia?.('(prefers-color-scheme: dark)').matches ?? false);
    applyTheme(initial);
});
function toggleDark(){ applyTheme(!isDark.value); }

const submit = () => {
        form.post(route('login'), {
                onFinish: () => form.reset('password'),
        });
};
</script>

<template>
    <div class="min-h-screen flex flex-col items-center justify-center bg-[#f6f8fa] dark:bg-[#181c23] transition-colors duration-theme">
        <Head title="Log in" />
        <div class="absolute top-6 right-8">
            <button
                @click="toggleDark"
                class="h-10 w-10 flex items-center justify-center rounded-full bg-[#f6f8fa] dark:bg-[#23272f] border border-[#e5e7eb] dark:border-[#23272f] shadow hover:bg-[#e6f9f3] dark:hover:bg-[#23272f]/80 transition-colors"
                aria-label="Toggle dark mode"
            >
                <i :class="[isDark ? 'pi pi-sun' : 'pi pi-moon', 'text-xl text-[#10b981] dark:text-[#6ee7b7]']" />
            </button>
        </div>
        <div class="w-full max-w-md">
            <div class="rounded-[48px] border-4 border-[#10b981] bg-white dark:bg-[#23272f] shadow-2xl px-10 py-12 flex flex-col items-center relative" style="box-shadow:0 8px 32px 0 rgba(16,185,129,0.10);">
                <div class="flex flex-col items-center mb-6">
                    <img src="/logo.png" alt="Logo" class="h-48 mb-4" />
                    <h1 class="text-2xl font-bold text-[#23272f] dark:text-[#f6f8fa] mb-1">Ngoại Ngữ Ms.Thơm!</h1>
                    <p class="text-[#64748b] dark:text-[#b0b7c3] text-base">Đăng nhập</p>
                </div>
                <form @submit.prevent="submit" class="w-full space-y-6">
                    <div>
                        <InputLabel for="email" value="Email" class="text-[#23272f] dark:text-[#f6f8fa] font-semibold" />
            <TextInput
                id="email"
                type="email"
                class="mt-2 block w-full rounded-lg font-semibold transition-colors duration-theme border border-gray-300 dark:border-gray-600 bg-white dark:bg-[#18191c] text-gray-700 dark:text-gray-200 placeholder-gray-400 dark:placeholder-gray-500 focus:border-emerald-400 focus:ring-emerald-400"
                v-model="form.email"
                required
                autofocus
                autocomplete="username"
                placeholder="Email address"
            />
                        <InputError class="mt-2" :message="form.errors.email" />
                    </div>
                    <div>
                        <InputLabel for="password" value="Password" class="text-[#23272f] dark:text-[#f6f8fa] font-semibold" />
            <TextInput
                id="password"
                type="password"
                class="mt-2 block w-full rounded-lg font-semibold transition-colors duration-theme border border-gray-300 dark:border-gray-600 bg-white dark:bg-[#18191c] text-gray-700 dark:text-gray-200 placeholder-gray-400 dark:placeholder-gray-500 focus:border-emerald-400 focus:ring-emerald-400"
                v-model="form.password"
                required
                autocomplete="current-password"
                placeholder="Password"
            />
                        <InputError class="mt-2" :message="form.errors.password" />
                    </div>
                    <div class="flex items-center justify-between">
                        <label class="flex items-center select-none">
                            <Checkbox name="remember" v-model:checked="form.remember" />
                            <span class="ms-2 text-sm text-[#6b7280] dark:text-[#b0b7c3]">Remember me</span>
                        </label>
                        <Link
                            v-if="canResetPassword"
                            :href="route('password.request')"
                            class="text-sm text-[#10b981] dark:text-[#6ee7b7] hover:underline focus:outline-none"
                        >
                            Forgot password?
                        </Link>
                    </div>
                    <PrimaryButton
                        class="w-full py-3 rounded-lg !bg-[#10b981] dark:!bg-[#6ee7b7] !text-white dark:!text-[#23272f] font-bold text-base !shadow-md hover:!bg-[#059669] dark:hover:!bg-[#34d399] transition-colors duration-theme !border-0 tracking-wide"
                        :class="{ 'opacity-50': form.processing }"
                        :disabled="form.processing"
                    >
                        SIGN IN
                    </PrimaryButton>
                </form>
            </div>
        </div>
    </div>
</template>
