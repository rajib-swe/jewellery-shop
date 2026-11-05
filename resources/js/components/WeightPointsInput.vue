<script setup>
import { computed } from 'vue'
import { pointsFromParts, pointsToGrams, pointsToParts } from '../utils/weight'

const props = defineProps({
    modelValue: {
        type: Object,
        default: () => ({ vori: '', ana: '', roti: '', point: '' }),
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    errorMessages: {
        type: [String, Array],
        default: '',
    },
})

const emit = defineEmits(['update:modelValue'])

function update(key, value) {
    emit('update:modelValue', { ...props.modelValue, [key]: value })
}

const totalPoints = computed(() => pointsFromParts(props.modelValue))
const totalGrams = computed(() => pointsToGrams(totalPoints.value).toFixed(5))

defineExpose({ totalPoints, totalGrams })
</script>

<template>
    <div class="weight-points-input">
        <div class="d-flex ga-1">
            <v-text-field
                :model-value="modelValue.vori"
                :disabled="disabled"
                :label="$t('units.vori')"
                min="0"
                type="number"
                variant="outlined"
                density="compact"
                hide-details
                @update:model-value="update('vori', $event)"
            />
            <v-text-field
                :model-value="modelValue.ana"
                :disabled="disabled"
                :label="$t('units.ana')"
                min="0"
                type="number"
                variant="outlined"
                density="compact"
                hide-details
                @update:model-value="update('ana', $event)"
            />
            <v-text-field
                :model-value="modelValue.roti"
                :disabled="disabled"
                :label="$t('units.roti')"
                min="0"
                type="number"
                variant="outlined"
                density="compact"
                hide-details
                @update:model-value="update('roti', $event)"
            />
            <v-text-field
                :model-value="modelValue.point"
                :disabled="disabled"
                :label="$t('units.point')"
                min="0"
                type="number"
                variant="outlined"
                density="compact"
                hide-details
                @update:model-value="update('point', $event)"
            />
        </div>
        <div class="text-caption text-medium-emphasis mt-1">
            = {{ totalPoints }} পয়েন্ট · {{ totalGrams }} g
        </div>
        <div v-if="errorMessages" class="text-error text-caption">
            {{ Array.isArray(errorMessages) ? errorMessages[0] : errorMessages }}
        </div>
    </div>
</template>
