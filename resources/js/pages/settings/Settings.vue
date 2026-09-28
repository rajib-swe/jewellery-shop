<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { useOptionLabels } from '../../constants/options'
import { useAuthStore } from '../../stores/auth'
import { useLocaleStore } from '../../stores/locale'
import { useSettingsStore } from '../../stores/settings'

const authStore = useAuthStore()
const localeStore = useLocaleStore()
const settingsStore = useSettingsStore()
const { pawnPartialMonthRuleOptions: partialMonthItems } = useOptionLabels()
const canManage = computed(() => authStore.can('manage settings'))
const weightUnitItems = computed(() => [
    { title: localeStore.t('settings.weightUnitGram'), value: 'gram' },
    { title: localeStore.t('settings.weightUnitVori'), value: 'vori' },
])
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
    invoice_template: 'demo2',
    pawn_max_ltv_percentage: '75.00',
    pawn_term_days: '30',
    pawn_grace_days: '30',
    pawn_partial_month_rule: 'daily_proration',
    pawn_terms: '',
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
        errorMessage.value = settingsStore.error ?? localeStore.t('errors.connection')
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
    payload.append('invoice_template', form.invoice_template || 'demo2')
    payload.append('pawn_max_ltv_percentage', String(form.pawn_max_ltv_percentage))
    payload.append('pawn_term_days', String(form.pawn_term_days))
    payload.append('pawn_grace_days', String(form.pawn_grace_days))
    payload.append('pawn_partial_month_rule', form.pawn_partial_month_rule)
    payload.append('pawn_terms', form.pawn_terms)

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
        errorMessage.value = settingsStore.error ?? localeStore.t('errors.connection')
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
                        <v-card-subtitle>{{ $t('settings.subtitle') }}</v-card-subtitle>
                        <v-card-title class="text-h4 font-weight-bold">{{ $t('settings.title') }}</v-card-title>
                        <v-card-text class="text-medium-emphasis">
                            {{ $t('settings.subtitleNote') }}
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
                                        :label="$t('settings.shopName')"
                                        prepend-inner-icon="mdi-store-outline"
                                        required
                                    />
                                </v-col>
                                <v-col cols="12" md="5">
                                    <v-text-field
                                        v-model="form.shop_phone"
                                        :disabled="!canManage"
                                        :label="$t('common.phone')"
                                        prepend-inner-icon="mdi-phone-outline"
                                    />
                                </v-col>
                                <v-col cols="12">
                                    <v-textarea
                                        v-model="form.shop_address"
                                        :disabled="!canManage"
                                        :label="$t('common.address')"
                                        prepend-inner-icon="mdi-map-marker-outline"
                                        rows="2"
                                        auto-grow
                                    />
                                </v-col>
                                <v-col cols="12" md="5">
                                    <v-text-field
                                        v-model="form.vat_percentage"
                                        :disabled="!canManage"
                                        :label="$t('settings.vatPercentage')"
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
                                        :label="$t('settings.currencySymbol')"
                                        maxlength="10"
                                    />
                                </v-col>
                                <v-col cols="12" md="4">
                                    <v-select
                                        v-model="form.weight_unit"
                                        :disabled="!canManage"
                                        :items="weightUnitItems"
                                        :label="$t('settings.weightUnit')"
                                    />
                                </v-col>
                                <v-col cols="12" md="8">
                                    <v-text-field
                                        v-model="form.default_pawn_interest_rate"
                                        :disabled="!canManage"
                                        :label="$t('settings.defaultPawnInterest')"
                                        min="0"
                                        max="100"
                                        step="0.01"
                                        :suffix="$t('settings.perMonth')"
                                        type="number"
                                    />
                                </v-col>
                                <v-col cols="12">
                                    <v-textarea
                                        v-model="form.invoice_footer"
                                        :disabled="!canManage"
                                        :label="$t('settings.invoiceFooter')"
                                        prepend-inner-icon="mdi-format-align-left"
                                        rows="2"
                                        auto-grow
                                    />
                                </v-col>
                                <v-col cols="12">
                                    <v-divider class="my-6" />

                                    <div class="text-subtitle-1 font-weight-bold mb-3">
                                        {{ $t('settings.pawnSection') }}
                                    </div>
                                    <v-row>
                                        <v-col cols="12" md="4">
                                            <v-text-field
                                                v-model="form.pawn_max_ltv_percentage"
                                                :disabled="!canManage"
                                                :hint="$t('settings.pawnMaxLtvHint')"
                                                :label="$t('settings.pawnMaxLtv')"
                                                min="1"
                                                max="100"
                                                persistent-hint
                                                step="0.01"
                                                suffix="%"
                                                type="number"
                                            />
                                        </v-col>
                                        <v-col cols="12" sm="6" md="3">
                                            <v-text-field
                                                v-model="form.pawn_term_days"
                                                :disabled="!canManage"
                                                :label="$t('settings.pawnTermDays')"
                                                min="1"
                                                step="1"
                                                type="number"
                                            />
                                        </v-col>
                                        <v-col cols="12" sm="6" md="3">
                                            <v-text-field
                                                v-model="form.pawn_grace_days"
                                                :disabled="!canManage"
                                                :label="$t('settings.pawnGraceDays')"
                                                min="0"
                                                step="1"
                                                type="number"
                                            />
                                        </v-col>
                                        <v-col cols="12" md="2">
                                            <v-select
                                                v-model="form.pawn_partial_month_rule"
                                                :disabled="!canManage"
                                                :items="partialMonthItems"
                                                :label="$t('settings.pawnPartialMonthRule')"
                                            />
                                        </v-col>
                                        <v-col cols="12">
                                            <v-textarea
                                                v-model="form.pawn_terms"
                                                :disabled="!canManage"
                                                :hint="$t('pawns.termsHint')"
                                                :label="$t('settings.pawnTerms')"
                                                prepend-inner-icon="mdi-format-align-left"
                                                rows="7"
                                                auto-grow
                                            />
                                        </v-col>
                                    </v-row>
                                </v-col>
                                <v-col cols="12">
                                    <div class="text-subtitle-1 font-weight-bold mb-2">{{ $t('settings.invoiceTemplate') }}</div>
                                    <v-radio-group v-model="form.invoice_template" :disabled="!canManage" class="mt-1">
                                        <v-card variant="outlined" class="mb-3 pa-3" :color="form.invoice_template === 'demo2' ? 'primary' : undefined">
                                            <v-radio value="demo2" color="primary">
                                                <template #label>
                                                    <div>
                                                        <div class="d-flex align-center">
                                                            <span class="font-weight-bold">{{ $t('settings.templateDemo2') }}</span>
                                                            <v-chip size="x-small" color="success" class="ms-2" variant="tonal">রেকমেন্ডেড</v-chip>
                                                        </div>
                                                        <div class="text-caption text-medium-emphasis mt-1">
                                                            আধুনিক ক্লিন প্যাড লেআউট, দৈনিক স্বর্ণের রেট-স্ট্রিপ এবং সহজে প্রিন্টযোগ্য ফ্রেমওয়ার্ক।
                                                        </div>
                                                    </div>
                                                </template>
                                            </v-radio>
                                        </v-card>
                                        <v-card variant="outlined" class="pa-3" :color="form.invoice_template === 'demo1' ? 'primary' : undefined">
                                            <v-radio value="demo1" color="primary">
                                                <template #label>
                                                    <div>
                                                        <div class="d-flex align-center">
                                                            <span class="font-weight-bold">{{ $t('settings.templateDemo1') }}</span>
                                                        </div>
                                                        <div class="text-caption text-medium-emphasis mt-1">
                                                            ঐতিহ্যবাহী লাক্সারি গোল্ডেন ও মেরুন ক্যাশ মেমো ফরম্যাট এবং ক্লাসিক অলঙ্করণ।
                                                        </div>
                                                    </div>
                                                </template>
                                            </v-radio>
                                        </v-card>
                                    </v-radio-group>
                                </v-col>
                            </v-row>

                            <v-divider class="my-6" />

                            <div class="text-subtitle-1 font-weight-bold mb-3">{{ $t('settings.shopLogo') }}</div>
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
                                        :label="$t('settings.chooseLogo')"
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
                                        :label="$t('settings.removeLogo')"
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
                                {{ $t('settings.readOnly') }}
                            </v-alert>

                            <v-btn
                                v-if="canManage"
                                class="mt-6"
                                color="primary"
                                :loading="settingsStore.saving"
                                size="large"
                                type="submit"
                            >
                                {{ $t('common.save') }}
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
