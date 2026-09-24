<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import { useInventoryStore } from '../../stores/inventory'
import { KARAT_OPTIONS, MAKING_TYPE_OPTIONS } from '../../constants/inventory'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const inventoryStore = useInventoryStore()
const itemId = computed(() => route.params.id ?? null)
const isEditing = computed(() => itemId.value !== null)
const canManage = computed(() => authStore.can('manage inventory'))
const form = reactive({
    category_id: null,
    name: '',
    karat: 22,
    gross_weight: '',
    stone_weight: '0.000',
    making_type: 'fixed',
    making_value: '0.00',
    stone_price: '0.00',
    barcode: '',
    image_url: null,
})
const imageFile = ref(null)
const imagePreviewUrl = ref('')
const removeImage = ref(false)
const loading = ref(false)
const errorMessage = ref('')
const fieldErrors = reactive({})

const categoryItems = computed(() => inventoryStore.categories.map((category) => ({
    title: category.name,
    value: category.id,
})))

const netWeightPreview = computed(() => {
    const gross = Number(form.gross_weight || 0)
    const stone = Number(form.stone_weight || 0)

    if (gross <= 0 || stone < 0 || stone > gross) {
        return '—'
    }

    return (gross - stone).toFixed(3)
})

function revokePreview() {
    if (imagePreviewUrl.value) {
        URL.revokeObjectURL(imagePreviewUrl.value)
        imagePreviewUrl.value = ''
    }
}

function populateForm(item) {
    Object.assign(form, {
        category_id: item.category.id,
        name: item.name,
        karat: item.karat,
        gross_weight: item.gross_weight,
        stone_weight: item.stone_weight,
        making_type: item.making_type,
        making_value: item.making_value,
        stone_price: item.stone_price,
        barcode: item.barcode ?? '',
        image_url: item.image_url,
    })
    removeImage.value = false
}

async function load() {
    errorMessage.value = ''
    clearFieldErrors()
    loading.value = true

    try {
        await inventoryStore.fetchCategories()
        if (isEditing.value) {
            populateForm(await inventoryStore.fetchItem(itemId.value, true))
        }
    } catch {
        errorMessage.value = inventoryStore.error ?? 'Unable to load the inventory item.'
    } finally {
        loading.value = false
    }
}

function clearFieldErrors() {
    Object.keys(fieldErrors).forEach((field) => delete fieldErrors[field])
}

function setValidationErrors(errors = {}) {
    Object.entries(errors).forEach(([field, messages]) => {
        if (Array.isArray(messages) && messages.length) {
            fieldErrors[field] = messages[0]
        }
    })
}

function selectImage(file) {
    revokePreview()
    removeImage.value = false

    if (file) {
        imagePreviewUrl.value = URL.createObjectURL(file)
    }
}

function validateForm() {
    clearFieldErrors()

    if (!form.category_id) {
        fieldErrors.category_id = 'Category is required.'
    }

    if (!form.name.trim()) {
        fieldErrors.name = 'Name is required.'
    }

    if (!form.gross_weight || Number(form.gross_weight) <= 0) {
        fieldErrors.gross_weight = 'Gross weight must be greater than zero.'
    }

    if (form.stone_weight !== '' && (Number(form.stone_weight) < 0 || Number(form.stone_weight) > Number(form.gross_weight || 0))) {
        fieldErrors.stone_weight = 'Stone weight cannot exceed gross weight.'
    }

    if (form.making_value === '' || Number(form.making_value) < 0) {
        fieldErrors.making_value = 'Making value cannot be negative.'
    }

    if (form.stone_price === '' || Number(form.stone_price) < 0) {
        fieldErrors.stone_price = 'Stone price cannot be negative.'
    }

    return Object.keys(fieldErrors).length === 0
}

function buildPayload() {
    const payload = new FormData()
    payload.append('category_id', String(form.category_id))
    payload.append('name', form.name.trim())
    payload.append('karat', String(form.karat))
    payload.append('gross_weight', String(form.gross_weight))
    payload.append('stone_weight', String(form.stone_weight || 0))
    payload.append('making_type', form.making_type)
    payload.append('making_value', String(form.making_value))
    payload.append('stone_price', String(form.stone_price || 0))
    payload.append('barcode', form.barcode.trim())

    if (imageFile.value) {
        payload.append('image', imageFile.value)
    }

    if (removeImage.value) {
        payload.append('remove_image', '1')
    }

    return payload
}

async function save() {
    errorMessage.value = ''

    if (!validateForm()) {
        return
    }

    try {
        await inventoryStore.saveItem(
            buildPayload(),
            isEditing.value ? itemId.value : null,
        )
        await router.push({ name: 'items' })
    } catch (error) {
        setValidationErrors(error.response?.data?.errors)
        errorMessage.value = error.response?.data?.message ?? inventoryStore.error ?? 'Unable to save the inventory item.'
    }
}

function goBack() {
    router.push({ name: 'items' })
}

onMounted(load)
onBeforeUnmount(revokePreview)
</script>

<template>
    <v-container class="py-8" fluid>
        <v-row justify="center">
            <v-col cols="12" lg="9">
                <div class="d-flex align-center ga-3 mb-6">
                    <v-btn aria-label="Back to items" icon="mdi-arrow-left" variant="text" @click="goBack" />
                    <div>
                        <v-card-subtitle>Gold inventory</v-card-subtitle>
                        <v-card-title class="text-h4 font-weight-bold pa-0">
                            {{ isEditing ? 'Edit item' : 'Add item' }}
                        </v-card-title>
                    </div>
                </div>

                <v-alert
                    v-if="errorMessage"
                    class="mb-6"
                    color="error"
                    density="comfortable"
                    type="error"
                    variant="tonal"
                    closable
                    @click:close="errorMessage = ''"
                >
                    {{ errorMessage }}
                </v-alert>

                <v-card elevation="2" :loading="loading">
                    <v-card-text>
                        <v-form @submit.prevent="save">
                            <v-row>
                                <v-col cols="12" sm="6">
                                    <v-select
                                        v-model="form.category_id"
                                        :disabled="!canManage"
                                        :error-messages="fieldErrors.category_id ? [fieldErrors.category_id] : []"
                                        :items="categoryItems"
                                        label="Category"
                                        prepend-inner-icon="mdi-shape-outline"
                                        required
                                    />
                                </v-col>
                                <v-col cols="12" sm="6">
                                    <v-text-field
                                        v-model="form.name"
                                        :disabled="!canManage"
                                        :error-messages="fieldErrors.name ? [fieldErrors.name] : []"
                                        label="Item name"
                                        prepend-inner-icon="mdi-tag-text-outline"
                                        required
                                    />
                                </v-col>
                                <v-col cols="12" sm="4">
                                    <v-select
                                        v-model="form.karat"
                                        :disabled="!canManage"
                                        :error-messages="fieldErrors.karat ? [fieldErrors.karat] : []"
                                        :items="KARAT_OPTIONS"
                                        label="Karat"
                                        required
                                    />
                                </v-col>
                                <v-col cols="12" sm="4">
                                    <v-text-field
                                        v-model="form.gross_weight"
                                        :disabled="!canManage"
                                        :error-messages="fieldErrors.gross_weight ? [fieldErrors.gross_weight] : []"
                                        label="Gross weight"
                                        min="0.001"
                                        required
                                        step="0.001"
                                        suffix="g"
                                        type="number"
                                    />
                                </v-col>
                                <v-col cols="12" sm="4">
                                    <v-text-field
                                        v-model="form.stone_weight"
                                        :disabled="!canManage"
                                        :error-messages="fieldErrors.stone_weight ? [fieldErrors.stone_weight] : []"
                                        label="Stone weight"
                                        min="0"
                                        step="0.001"
                                        suffix="g"
                                        type="number"
                                    />
                                </v-col>
                                <v-col cols="12" sm="4">
                                    <v-select
                                        v-model="form.making_type"
                                        :disabled="!canManage"
                                        :error-messages="fieldErrors.making_type ? [fieldErrors.making_type] : []"
                                        :items="MAKING_TYPE_OPTIONS"
                                        label="Making type"
                                        required
                                    />
                                </v-col>
                                <v-col cols="12" sm="4">
                                    <v-text-field
                                        v-model="form.making_value"
                                        :disabled="!canManage"
                                        :error-messages="fieldErrors.making_value ? [fieldErrors.making_value] : []"
                                        label="Making value"
                                        min="0"
                                        step="0.01"
                                        type="number"
                                        required
                                    />
                                </v-col>
                                <v-col cols="12" sm="4">
                                    <v-text-field
                                        v-model="form.stone_price"
                                        :disabled="!canManage"
                                        :error-messages="fieldErrors.stone_price ? [fieldErrors.stone_price] : []"
                                        label="Stone price"
                                        min="0"
                                        step="0.01"
                                        type="number"
                                    />
                                </v-col>
                                <v-col cols="12" sm="6">
                                    <v-text-field
                                        v-model="form.barcode"
                                        :disabled="!canManage"
                                        :error-messages="fieldErrors.barcode ? [fieldErrors.barcode] : []"
                                        label="Barcode"
                                        prepend-inner-icon="mdi-barcode"
                                    />
                                </v-col>
                                <v-col cols="12" sm="6">
                                    <v-text-field
                                        :model-value="netWeightPreview"
                                        disabled
                                        hint="Calculated on the server"
                                        label="Net weight"
                                        persistent-hint
                                        suffix="g"
                                    />
                                </v-col>
                            </v-row>

                            <v-divider class="my-6" />

                            <div class="text-subtitle-1 font-weight-bold mb-3">Item image</div>
                            <v-row align="center">
                                <v-col cols="12" sm="4" md="3">
                                    <v-img
                                        v-if="imagePreviewUrl || form.image_url"
                                        :src="imagePreviewUrl || form.image_url"
                                        class="item-image-preview"
                                        cover
                                        rounded="lg"
                                    />
                                    <div v-else class="item-image-placeholder d-flex align-center justify-center">
                                        <v-icon icon="mdi-image-off-outline" size="36" />
                                    </div>
                                </v-col>
                                <v-col cols="12" sm="8" md="9">
                                    <v-file-input
                                        v-model="imageFile"
                                        :disabled="!canManage"
                                        :error-messages="fieldErrors.image ? [fieldErrors.image] : []"
                                        accept="image/png,image/jpeg,image/webp"
                                        label="Choose an image"
                                        prepend-icon="mdi-upload-outline"
                                        show-size
                                        @update:model-value="selectImage"
                                    />
                                    <v-checkbox
                                        v-if="form.image_url && !imageFile"
                                        v-model="removeImage"
                                        :disabled="!canManage"
                                        color="error"
                                        density="compact"
                                        label="Remove current image on save"
                                    />
                                </v-col>
                            </v-row>

                            <div class="d-flex flex-wrap justify-end ga-3 mt-6">
                                <v-btn variant="text" @click="goBack">Cancel</v-btn>
                                <v-btn
                                    v-if="canManage"
                                    color="primary"
                                    :loading="inventoryStore.saving"
                                    size="large"
                                    type="submit"
                                >
                                    {{ isEditing ? 'Save changes' : 'Add item' }}
                                </v-btn>
                            </div>
                        </v-form>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>
    </v-container>
</template>

<style scoped>
.item-image-preview,
.item-image-placeholder {
    width: 160px;
    height: 160px;
    border: 1px solid rgb(138 106 50 / 20%);
    border-radius: 12px;
}

.item-image-placeholder {
    color: rgb(79 93 117 / 60%);
    background: rgb(79 93 117 / 6%);
}
</style>
