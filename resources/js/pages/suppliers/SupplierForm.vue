<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useOptionLabels } from '../../constants/options'
import { useLocaleStore } from '../../stores/locale'
import { useSuppliersStore } from '../../stores/suppliers'

const route = useRoute()
const router = useRouter()
const localeStore = useLocaleStore()
const suppliersStore = useSuppliersStore()
const { supplierTypeOptions: typeItems } = useOptionLabels()
const supplierId = computed(() => route.params.id)
const isEdit = computed(() => supplierId.value !== undefined)
const errorMessage = ref('')
const form = reactive({
    name: '',
    type: 'supplier',
    phone: '',
    address: '',
    nid: '',
    notes: '',
})

function applySupplier(supplier) {
    Object.assign(form, {
        name: supplier.name ?? '',
        type: supplier.type ?? 'supplier',
        phone: supplier.phone ?? '',
        address: supplier.address ?? '',
        nid: supplier.nid ?? '',
        notes: supplier.notes ?? '',
    })
}

async function load() {
    if (!isEdit.value) {
        return
    }

    errorMessage.value = ''

    try {
        applySupplier(await suppliersStore.fetchSupplier(supplierId.value, true))
    } catch {
        errorMessage.value = suppliersStore.error ?? localeStore.t('suppliers.loadFailed')
    }
}

async function submit() {
    errorMessage.value = ''

    if (!form.name.trim()) {
        errorMessage.value = localeStore.t('suppliers.nameRequired')
        return
    }

    const payload = {
        name: form.name.trim(),
        type: form.type,
        phone: form.phone.trim() || null,
        address: form.address.trim() || null,
        nid: form.nid.trim() || null,
        notes: form.notes.trim() || null,
    }

    try {
        const supplier = await suppliersStore.saveSupplier(payload, supplierId.value ?? null)

        router.push({ name: 'supplier-profile', params: { id: supplier.id } })
    } catch (error) {
        errorMessage.value = error.response?.data?.message
            ?? suppliersStore.error
            ?? localeStore.t('suppliers.saveFailed')
    }
}

watch(supplierId, load)

onMounted(load)
</script>

<template>
    <v-container class="py-8" fluid>
        <v-row justify="center">
            <v-col cols="12" lg="7">
                <div class="d-flex align-center ga-3 mb-6">
                    <v-btn
                        :aria-label="$t('common.back')"
                        icon="mdi-arrow-left"
                        variant="text"
                        @click="router.push({ name: 'suppliers' })"
                    />
                    <div>
                        <v-card-subtitle>{{ $t('suppliers.listTitle') }}</v-card-subtitle>
                        <v-card-title class="text-h4 font-weight-bold pa-0">
                            {{ isEdit ? $t('suppliers.editSupplier') : $t('suppliers.newSupplier') }}
                        </v-card-title>
                    </div>
                </div>

                <v-alert
                    v-if="errorMessage"
                    class="mb-6"
                    closable
                    color="error"
                    density="comfortable"
                    type="error"
                    variant="tonal"
                    @click:close="errorMessage = ''"
                >
                    {{ errorMessage }}
                </v-alert>

                <v-card elevation="2">
                    <v-form @submit.prevent="submit">
                        <v-card-text>
                            <v-row>
                                <v-col cols="12" md="7">
                                    <v-text-field
                                        v-model="form.name"
                                        :label="$t('common.name')"
                                        prepend-inner-icon="mdi-store-outline"
                                        required
                                    />
                                </v-col>
                                <v-col cols="12" md="5">
                                    <v-select
                                        v-model="form.type"
                                        :items="typeItems"
                                        :label="$t('suppliers.selectType')"
                                        prepend-inner-icon="mdi-hammer-wrench"
                                    />
                                </v-col>
                                <v-col cols="12" sm="6" md="4">
                                    <v-text-field
                                        v-model="form.phone"
                                        :label="$t('common.phone')"
                                        prepend-inner-icon="mdi-phone-outline"
                                    />
                                </v-col>
                                <v-col cols="12" sm="6" md="4">
                                    <v-text-field
                                        v-model="form.nid"
                                        :label="$t('customers.nid')"
                                        prepend-inner-icon="mdi-card-account-details-outline"
                                    />
                                </v-col>
                                <v-col cols="12">
                                    <v-textarea
                                        v-model="form.address"
                                        :label="$t('common.address')"
                                        prepend-inner-icon="mdi-map-marker-outline"
                                        rows="2"
                                        auto-grow
                                    />
                                </v-col>
                                <v-col cols="12">
                                    <v-textarea
                                        v-model="form.notes"
                                        :label="$t('common.notes')"
                                        rows="2"
                                        variant="outlined"
                                    />
                                </v-col>
                            </v-row>
                        </v-card-text>

                        <v-card-actions class="pa-4 pt-0">
                            <v-spacer />
                            <v-btn color="primary" :loading="suppliersStore.saving" type="submit">
                                {{ isEdit ? $t('common.saveChanges') : $t('suppliers.addSupplier') }}
                            </v-btn>
                        </v-card-actions>
                    </v-form>
                </v-card>
            </v-col>
        </v-row>
    </v-container>
</template>
