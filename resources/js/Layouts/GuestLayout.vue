<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div
        class="flex min-h-screen flex-col items-center justify-center bg-gradient-to-br from-[#e6f9f3] via-[#f6f8fa] to-[#dbeafe] dark:from-[#181c23] dark:via-[#23272f] dark:to-[#181c23] transition-colors duration-theme"
    >
        <div class="mb-6">
            <Link href="/">
                <ApplicationLogo class="h-20 w-20 fill-current text-[#10b981] dark:text-[#6ee7b7] drop-shadow-lg" />
            </Link>
        </div>

        <div
            class="w-full bg-white/90 dark:bg-[#23272f]/90 px-8 py-8 shadow-2xl sm:max-w-md rounded-2xl border border-[#e5e7eb] dark:border-[#23272f] backdrop-blur-md"
        >
            <slot />
        </div>
    </div>
</template>
