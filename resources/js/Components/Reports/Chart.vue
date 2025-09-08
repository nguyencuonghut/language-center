<template>
    <canvas ref="chartCanvas"></canvas>
</template>

<script setup>
import { ref, onMounted, nextTick, watch, onBeforeUnmount } from 'vue'
import { Chart } from 'chart.js'

const props = defineProps({
    type: {
        type: String,
        required: true
    },
    data: {
        type: Object,
        required: true
    },
    options: {
        type: Object,
        default: () => ({})
    }
})

const chartCanvas = ref(null)
let chartInstance = null

const createChart = () => {
    if (!chartCanvas.value || !props.data) return

    if (chartInstance) {
        chartInstance.destroy()
    }

    chartInstance = new Chart(chartCanvas.value, {
        type: props.type,
        data: props.data,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            ...props.options
        }
    })
}

onMounted(async () => {
    await nextTick()
    createChart()
})

watch(() => [props.data, props.type, props.options], async () => {
    await nextTick()
    createChart()
}, { deep: true })

onBeforeUnmount(() => {
    if (chartInstance) {
        chartInstance.destroy()
    }
})
</script>
