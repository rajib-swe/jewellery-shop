<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import { useCustomersStore } from '../../stores/customers'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const customerStore = useCustomersStore()
const customerId = computed(() => route.params.id ?? null)
const isEditing = computed(() => customerId.value !== null)
const canManage = computed(() => authStore.can('manage customers'))
const form = reactive({
    name: '',
    phone: '',
    nid: '',
    address: '',
    opening_balance: '0.00',
    notes: '',
    photo_url: null,
})
const photoFile = ref(null)
const photoPreviewUrl = ref('')
const removePhoto = ref(false)
const loading = ref(false)
const errorMessage = ref('')
const fieldErrors = reactive({})

const displayPhotoUrl = computed(() => photoPreviewUrl.value || form.photo_url)

function revokePreview() {
    if (photoPreviewUrl.value) {
        URL.revokeObjectURL(photoPreviewUrl.value)
        photoPreviewUrl.value = ''
    }
}

function populateForm(customer) {
    Object.assign(form, {
        name: customer.name ?? '',
        phone: customer.phone ?? '',
        nid: customer.nid ?? '',
        address: customer.address ?? '',
        opening_balance: customer.opening_balance ?? '0.00',
        notes: customer.notes ?? '',
        photo_url: customer.photo_url ?? null,
    })
    removePhoto.value = false
}

async function load() {
    errorMessage.value = ''
    clearFieldErrors()

    if (!isEditing.value) {
        return
    }

    loading.value = true

    try {
        populateForm(await customerStore.fetchCustomer(customerId.value, true))
    } catch {
        errorMessage.value = customerStore.error ?? 'Unable to load the customer.'
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

function selectPhoto(file) {
    revokePreview()
    removePhoto.value = false

    if (file) {
        photoPreviewUrl.value = URL.createObjectURL(file)
    }
}

function buildPayload() {
    const payload = new FormData()
    payload.append('name', form.name.trim())
    payload.append('phone', form.phone.trim())
    payload.append('nid', form.nid.trim())
    payload.append('address', form.address.trim())
    payload.append('opening_balance', String(form.opening_balance))
    payload.append('notes', form.notes.trim())

    if (photoFile.value) {
        payload.append('photo', photoFile.value)
    }

    if (removePhoto.value) {
        payload.append('remove_photo', '1')
    }

    return payload
}

function validateForm() {
    clearFieldErrors()

    if (!form.name.trim()) {
        fieldErrors.name = 'Name is required.'
    }

    if (!form.phone.trim()) {
        fieldErrors.phone = 'Phone is required.'
    }

    if (form.opening_balance === '' || Number(form.opening_balance) < 0) {
        fieldErrors.opening_balance = 'Enter a nonnegative opening balance.'
    }

    return Object.keys(fieldErrors).length === 0
}

async function save() {
    errorMessage.value = ''

    if (!validateForm()) {
        return
    }

    try {
        const customer = await customerStore.saveCustomer(
            buildPayload(),
            isEditing.value ? customerId.value : null,
        )
        await router.push({ name: 'customer-profile', params: { id: customer.id } })
    } catch (error) {
        setValidationErrors(error.response?.data?.errors)
        errorMessage.value = customerStore.error ?? 'Unable to save the customer.'
    }
}

function goBack() {
    if (isEditing.value && customerStore.current?.id === Number(customerId.value)) {
        router.push({ name: 'customer-profile', params: { id: customerId.value } })
        return
    }

    router.push({ name: 'customers' })
}

onMounted(load)
onBeforeUnmount(revokePreview)
</script>

<template>
    <v-container class="py-8" fluid>
        <v-row justify="center">
            <v-col cols="12" lg="9">
                <div class="d-flex align-center ga-3 mb-6">
                    <v-btn
                        aria-label="Go back"
                        icon="mdi-arrow-left"
                        variant="text"
                        @click="goBack"
                    />
                    <div>
                        <v-card-subtitle>Customer directory</v-card-subtitle>
                        <v-card-title class="text-h4 font-weight-bold pa-0">
                            {{ isEditing ? 'Edit customer' : 'Add customer' }}
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
                                    <v-text-field
                                        v-model="form.name"
                                        :disabled="!canManage"
                                        :error-messages="fieldErrors.name ? [fieldErrors.name] : []"
                                        label="Name"
                                        prepend-inner-icon="mdi-account-outline"
                                        required
                                    />
                                </v-col>
                                <v-col cols="12" sm="6">
                                    <v-text-field
                                        v-model="form.phone"
                                        :disabled="!canManage"
                                        :error-messages="fieldErrors.phone ? [fieldErrors.phone] : []"
                                        label="Phone"
                                        placeholder="01712345678"
                                        prepend-inner-icon="mdi-phone-outline"
                                        required
                                    />
                                </v-col>
                                <v-col cols="12" sm="6">
                                    <v-text-field
                                        v-model="form.nid"
                                        :disabled="!canManage"
                                        :error-messages="fieldErrors.nid ? [fieldErrors.nid] : []"
                                        label="NID"
                                        prepend-inner-icon="mdi-card-account-details-outline"
                                    />
                                </v-col>
                                <v-col cols="12" sm="6">
                                    <v-text-field
                                        v-model="form.opening_balance"
                                        :disabled="!canManage"
                                        :error-messages="fieldErrors.opening_balance ? [fieldErrors.opening_balance] : []"
                                        label="Opening balance"
                                        min="0"
                                        step="0.01"
                                        type="number"
                                        required
                                    />
                                </v-col>
                                <v-col cols="12">
                                    <v-textarea
                                        v-model="form.address"
                                        :disabled="!canManage"
                                        :error-messages="fieldErrors.address ? [fieldErrors.address] : []"
                                        auto-grow
                                        label="Address"
                                        prepend-inner-icon="mdi-map-marker-outline"
                                        rows="2"
                                    />
                                </v-col>
                                <v-col cols="12">
                                    <v-textarea
                                        v-model="form.notes"
                                        :disabled="!canManage"
                                        :error-messages="fieldErrors.notes ? [fieldErrors.notes] : []"
                                        auto-grow
                                        label="Notes"
                                        prepend-inner-icon="mdi-note-text-outline"
                                        rows="3"
                                    />
                                </v-col>
                            </v-row>

                            <v-divider class="my-6" />

                            <div class="text-subtitle-1 font-weight-bold mb-3">Customer photo</div>
                            <v-row align="center">
                                <v-col cols="12" sm="4" md="3">
                                    <v-img
                                        v-if="displayPhotoUrl"
                                        :src="displayPhotoUrl"
                                        class="customer-photo-preview"
                                        cover
                                        rounded="lg"
                                    />
                                    <div v-else class="customer-photo-placeholder d-flex align-center justify-center">
                                        <v-icon icon="mdi-account-outline" size="36" />
                                    </div>
                                </v-col>
                                <v-col cols="12" sm="8" md="9">
                                    <v-file-input
                                        v-model="photoFile"
                                        :disabled="!canManage"
                                        :error-messages="fieldErrors.photo ? [fieldErrors.photo] : []"
                                        accept="image/png,image/jpeg,image/webp"
                                        label="Choose a photo"
                                        prepend-icon="mdi-upload-outline"
                                        show-size
                                        @update:model-value="selectPhoto"
                                    />
                                    <v-checkbox
                                        v-if="form.photo_url && !photoFile"
                                        v-model="removePhoto"
                                        :disabled="!canManage"
                                        color="error"
                                        density="compact"
                                        label="Remove current photo on save"
                                    />
                                </v-col>
                            </v-row>

                            <div class="d-flex flex-wrap justify-end ga-3 mt-6">
                                <v-btn variant="text" @click="goBack">Cancel</v-btn>
                                <v-btn
                                    v-if="canManage"
                                    color="primary"
                                    :loading="customerStore.saving"
                                    size="large"
                                    type="submit"
                                >
                                    {{ isEditing ? 'Save changes' : 'Create customer' }}
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
.customer-photo-preview,
.customer-photo-placeholder {
    width: 160px;
    height: 160px;
    border: 1px solid rgb(138 106 50 / 20%);
    border-radius: 12px;
}

.customer-photo-placeholder {
    color: rgb(79 93 117 / 60%);
    background: rgb(79 93 117 / 6%);
}
</style>
