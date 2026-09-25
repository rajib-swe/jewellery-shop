<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { useAuthStore } from '../stores/auth'
import { useCustomersStore } from '../stores/customers'
import { useLocaleStore } from '../stores/locale'

const props = defineProps({
    modelValue: {
        type: [Object, Number, String],
        default: null,
    },
    label: {
        type: String,
        default: '',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
})

const emit = defineEmits(['update:modelValue', 'created'])
const authStore = useAuthStore()
const customerStore = useCustomersStore()
const localeStore = useLocaleStore()
const pickerLabel = computed(() => props.label || localeStore.t('customers.pickerLabel'))
const selectedCustomer = ref(null)
const search = ref('')
const results = ref([])
const loading = ref(false)
const quickDialog = ref(false)
const quickSaving = ref(false)
const quickError = ref('')
const quickForm = reactive({
    name: '',
    phone: '',
})
const canQuickAdd = computed(() => authStore.can('manage customers'))

const items = computed(() => results.value.map((customer) => ({
    ...customer,
    title: `${customer.name} · ${customer.phone}`,
    subtitle: customer.code,
})))

function normalizeCustomer(customer) {
    if (!customer) {
        return null
    }

    return {
        ...customer,
        title: `${customer.name} · ${customer.phone}`,
        subtitle: customer.code,
    }
}

function selectCustomer(customer) {
    const normalized = normalizeCustomer(customer)
    selectedCustomer.value = normalized
    emit('update:modelValue', normalized)
}

function clearCustomer() {
    selectedCustomer.value = null
    emit('update:modelValue', null)
}

async function searchCustomers(value) {
    search.value = value
    const term = value.trim()

    if (term.length < 2) {
        results.value = []
        return
    }

    loading.value = true

    try {
        const response = await customerStore.fetchList({
            search: term,
            per_page: 10,
        }, true)
        results.value = response.data
    } catch {
        results.value = []
    } finally {
        loading.value = false
    }
}

function openQuickAdd() {
    quickError.value = ''
    quickForm.name = ''
    quickForm.phone = ''
    quickDialog.value = true
}

async function quickAdd() {
    quickError.value = ''

    if (!quickForm.name.trim() || !quickForm.phone.trim()) {
        quickError.value = localeStore.t('customers.nameRequired')
        return
    }

    const payload = new FormData()
    payload.append('name', quickForm.name.trim())
    payload.append('phone', quickForm.phone.trim())
    payload.append('opening_balance', '0.00')
    quickSaving.value = true

    try {
        const customer = await customerStore.saveCustomer(payload)
        selectCustomer(customer)
        emit('created', customer)
        quickDialog.value = false
    } catch (error) {
        quickError.value = error.response?.data?.message ?? localeStore.t('customers.quickAddFailed')
    } finally {
        quickSaving.value = false
    }
}

watch(
    () => props.modelValue,
    async (value) => {
        if (value && typeof value === 'object') {
            selectedCustomer.value = normalizeCustomer(value)
            return
        }

        if (value === null || value === '') {
            selectedCustomer.value = null
            return
        }

        try {
            selectCustomer(await customerStore.fetchCustomer(value, true))
        } catch {
            clearCustomer()
        }
    },
    { immediate: true },
)
</script>

<template>
    <div class="customer-picker">
        <div class="d-flex align-start ga-2">
            <v-autocomplete
                v-model="selectedCustomer"
                :disabled="disabled"
                :items="items"
                :label="pickerLabel"
                :loading="loading"
                clearable
                density="comfortable"
                hide-no-data
                item-title="title"
                item-subtitle="subtitle"
                item-value="id"
                :no-data-text="$t('customers.pickerNoData')"
                return-object
                variant="outlined"
                @update:search="searchCustomers"
                @update:model-value="selectCustomer"
            >
                <template #prepend-inner>
                    <v-icon icon="mdi-account-search-outline" size="20" />
                </template>
                <template #append-inner>
                    <v-btn
                        v-if="canQuickAdd"
                        :aria-label="$t('customers.addNewCustomer')"
                        icon="mdi-account-plus-outline"
                        size="small"
                        variant="text"
                        @click.stop="openQuickAdd"
                    />
                </template>
            </v-autocomplete>
        </div>

        <v-dialog v-model="quickDialog" max-width="480">
            <v-card>
                <v-card-title>{{ $t('customers.quickAdd') }}</v-card-title>
                <v-card-text>
                    <v-alert
                        v-if="quickError"
                        class="mb-4"
                        color="error"
                        density="comfortable"
                        type="error"
                        variant="tonal"
                    >
                        {{ quickError }}
                    </v-alert>
                    <v-text-field
                        v-model="quickForm.name"
                        autofocus
                        :label="$t('common.name')"
                        prepend-inner-icon="mdi-account-outline"
                        required
                    />
                    <v-text-field
                        v-model="quickForm.phone"
                        :label="$t('common.phone')"
                        placeholder="01712345678"
                        prepend-inner-icon="mdi-phone-outline"
                        required
                    />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="quickDialog = false">{{ $t('common.cancel') }}</v-btn>
                    <v-btn color="primary" :loading="quickSaving" @click="quickAdd">
                        {{ $t('customers.addCustomer') }}
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </div>
</template>

<style scoped>
.customer-picker {
    min-width: 0;
    width: 100%;
}
</style>
