<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { useAuthStore } from '../../stores/auth'
import { useSettingsStore } from '../../stores/settings'

const authStore = useAuthStore()
const settingsStore = useSettingsStore()
const canManage = computed(() => authStore.can('manage settings'))
const form = reactive({
    shop_name: '',
    shop_address: '',
    shop_phone: '',
    shop_logo: null,
    shop_logo_url: null,
    vat_percentage: '0.00',
    currency_symbol: '৳',
    weight_unit: 'gram',
    default_pawn_interest_rate: '0.00',
    invoice_footer: '',
})
const logoFile = ref(null)
const logoPreviewUrl = ref('')
const removeLogo = ref(false)
const errorMessage = ref('')
const displayLogoUrl = computed(() => logoPreviewUrl.value || form.shop_logo_url)

function updateForm(settings) {
    Object.assign(form, settings)
}

async function load() {
    errorMessage.value = ''

    try {
        updateForm(await settingsStore.fetchSettings())
    } catch {
        errorMessage.value = settingsStore.error ?? 'Unable to load settings.'
    }
}

async function save() {
    errorMessage.value = ''

    const payload = new FormData()
    payload.append('shop_name', form.shop_name)
    payload.append('shop_address', form.shop_address)
    payload.append('shop_phone', form.shop_phone)
    payload.append('vat_percentage', String(form.vat_percentage))
    payload.append('currency_symbol', form.currency_symbol)
    payload.append('weight_unit', form.weight_unit)
    payload.append('default_pawn_interest_rate', String(form.default_pawn_interest_rate))
    payload.append('invoice_footer', form.invoice_footer)

    if (logoFile.value) {
        payload.append('shop_logo', logoFile.value)
    }

    if (removeLogo.value) {
        payload.append('remove_shop_logo', '1')
    }

    try {
        updateForm(await settingsStore.update(payload))
        logoFile.value = null
        removeLogo.value = false
        revokePreview()
    } catch {
        errorMessage.value = settingsStore.error ?? 'Unable to save settings.'
    }
}

function revokePreview() {
    if (logoPreviewUrl.value) {
        URL.revokeObjectURL(logoPreviewUrl.value)
        logoPreviewUrl.value = ''
    }
}

function selectLogo(file) {
    revokePreview()

    if (file) {
        logoPreviewUrl.value = URL.createObjectURL(file)
    }
}

onMounted(load)
onBeforeUnmount(revokePreview)
</script>

<template>
    <v-container class="py-8" fluid>
        <v-row justify="center">
            <v-col cols="12" lg="9">
                <v-card elevation="2">
                    <v-card-item>
                        <v-card-subtitle>Shop configuration</v-card-subtitle>
                        <v-card-title class="text-h4 font-weight-bold">Settings</v-card-title>
                        <v-card-text class="text-medium-emphasis">
                            These values are used throughout the shop, printed documents, and future modules.
                        </v-card-text>
                    </v-card-item>

                    <v-card-text>
                        <v-alert
                            v-if="errorMessage"
                            class="mb-6"
                            color="error"
                            density="comfortable"
                            variant="tonal"
                            type="error"
                        >
                            {{ errorMessage }}
                        </v-alert>

                        <v-form @submit.prevent="save">
                            <v-row>
                                <v-col cols="12" md="7">
                                    <v-text-field
                                        v-model="form.shop_name"
                                        :disabled="!canManage"
                                        label="Shop name"
                                        prepend-inner-icon="mdi-store-outline"
                                        required
                                    />
                                </v-col>
                                <v-col cols="12" md="5">
                                    <v-text-field
                                        v-model="form.shop_phone"
                                        :disabled="!canManage"
                                        label="Phone"
                                        prepend-inner-icon="mdi-phone-outline"
                                    />
                                </v-col>
                                <v-col cols="12">
                                    <v-textarea
                                        v-model="form.shop_address"
                                        :disabled="!canManage"
                                        label="Address"
                                        prepend-inner-icon="mdi-map-marker-outline"
                                        rows="2"
                                        auto-grow
                                    />
                                </v-col>
                                <v-col cols="12" md="5">
                                    <v-text-field
                                        v-model="form.vat_percentage"
                                        :disabled="!canManage"
                                        label="VAT percentage"
                                        min="0"
                                        max="100"
                                        step="0.01"
                                        suffix="%"
                                        type="number"
                                    />
                                </v-col>
                                <v-col cols="12" md="3">
                                    <v-text-field
                                        v-model="form.currency_symbol"
                                        :disabled="!canManage"
                                        label="Currency symbol"
                                        maxlength="10"
                                    />
                                </v-col>
                                <v-col cols="12" md="4">
                                    <v-select
                                        v-model="form.weight_unit"
                                        :disabled="!canManage"
                                        :items="[
                                            { title: 'Gram', value: 'gram' },
                                            { title: 'Vori', value: 'vori' },
                                        ]"
                                        label="Default weight unit"
                                    />
                                </v-col>
                                <v-col cols="12" md="8">
                                    <v-text-field
                                        v-model="form.default_pawn_interest_rate"
                                        :disabled="!canManage"
                                        label="Default pawn interest rate"
                                        min="0"
                                        max="100"
                                        step="0.01"
                                        suffix="% per month"
                                        type="number"
                                    />
                                </v-col>
                                <v-col cols="12">
                                    <v-textarea
                                        v-model="form.invoice_footer"
                                        :disabled="!canManage"
                                        label="Invoice footer"
                                        prepend-inner-icon="mdi-format-align-left"
                                        rows="2"
                                        auto-grow
                                    />
                                </v-col>
                            </v-row>

                            <v-divider class="my-6" />

                            <div class="text-subtitle-1 font-weight-bold mb-3">Shop logo</div>
                            <v-row align="center">
                                <v-col cols="12" sm="4" md="3">
                                    <v-img
                                        v-if="displayLogoUrl"
                                        :src="displayLogoUrl"
                                        class="logo-preview"
                                        cover
                                        rounded="lg"
                                    />
                                    <div v-else class="logo-placeholder d-flex align-center justify-center">
                                        <v-icon icon="mdi-image-off-outline" size="32" />
                                    </div>
                                </v-col>
                                <v-col cols="12" sm="8" md="9">
                                    <v-file-input
                                        v-model="logoFile"
                                        :disabled="!canManage"
                                        accept="image/png,image/jpeg,image/webp"
                                        label="Choose a logo"
                                        prepend-icon="mdi-upload-outline"
                                        show-size
                                        @update:model-value="selectLogo"
                                    />
                                    <v-checkbox
                                        v-if="form.shop_logo"
                                        v-model="removeLogo"
                                        :disabled="!canManage"
                                        color="error"
                                        density="compact"
                                        label="Remove current logo on save"
                                    />
                                </v-col>
                            </v-row>

                            <v-alert
                                v-if="!canManage"
                                class="mt-5"
                                color="info"
                                density="comfortable"
                                variant="tonal"
                                type="info"
                            >
                                You have read-only access. Ask an administrator or manager to change settings.
                            </v-alert>

                            <v-btn
                                v-if="canManage"
                                class="mt-6"
                                color="primary"
                                :loading="settingsStore.saving"
                                size="large"
                                type="submit"
                            >
                                Save settings
                            </v-btn>
                        </v-form>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>
    </v-container>
</template>

<style scoped>
.logo-preview,
.logo-placeholder {
    width: 160px;
    height: 160px;
    border: 1px solid rgb(138 106 50 / 20%);
    border-radius: 12px;
}

.logo-placeholder {
    color: rgb(79 93 117 / 60%);
    background: rgb(79 93 117 / 6%);
}
</style>
