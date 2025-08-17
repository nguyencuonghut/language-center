<script setup>
defineProps({
    value: {
        type: String,
        required: true
    },
    required: {
        type: Boolean,
        default: false
    }
});
</script>

<template>
    <label class="block text-sm mb-1">
        {{ value }}
        <span v-if="required" class="text-red-500 ml-1">*</span>
    </label>
</template>
