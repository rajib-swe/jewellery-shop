<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useAuthStore } from '../../stores/auth'
import { useLocaleStore } from '../../stores/locale'
import { useUsersStore } from '../../stores/users'

const authStore = useAuthStore()
const localeStore = useLocaleStore()
const usersStore = useUsersStore()
const canManage = computed(() => authStore.can('manage users'))
const isAdmin = computed(() => authStore.primaryRole === 'admin')
const search = ref('')
const roleFilter = ref('')
const dialog = ref(false)
const deleteDialog = ref(false)
const deleteTarget = ref(null)
const errorMessage = ref('')
const currentUserId = computed(() => authStore.user?.id ?? null)
const form = reactive({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: '',
})

// Only admins can assign a role, so the picker only offers the three seeded
// roles. The matrix screen is where the finer permissions are tuned.
const roleItems = computed(() => ['admin', 'manager', 'cashier'].map((value) => ({
    title: localeStore.t(`common.roles.${value}`),
    value,
})))

const headers = computed(() => [
    { title: localeStore.t('common.name'), key: 'name' },
    { title: localeStore.t('users.email'), key: 'email' },
    { title: localeStore.t('common.role'), key: 'roles' },
    { title: localeStore.t('common.date'), key: 'created_at' },
    { title: '', key: 'actions', sortable: false, align: 'end' },
])

const options = computed(() => ({
    page: usersStore.meta.current_page,
    per_page: usersStore.meta.per_page,
    search: search.value || undefined,
    role: roleFilter.value || undefined,
}))

const roleFilterItems = computed(() => [
    { title: localeStore.t('users.allRoles'), value: '' },
    ...roleItems.value,
])

function roleTitle(role) {
    return localeStore.t(`common.roles.${role}`)
}

function formatDate(value) {
    if (!value) {
        return '—'
    }

    return new Date(value).toLocaleDateString(localeStore.locale === 'bn' ? 'bn-BD' : 'en-GB')
}

async function load() {
    usersStore.clearError()

    try {
        await usersStore.fetchList(options.value, true)
    } catch {
        // The store exposes the message, the table simply stays empty.
    }
}

function changePage(page) {
    if (page < 1) {
        return
    }

    options.value.page = page
    load()
}

function openCreate() {
    errorMessage.value = ''
    deleteTarget.value = null

    Object.assign(form, {
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        role: 'cashier',
    })

    dialog.value = true
}

function openEdit(user) {
    errorMessage.value = ''
    deleteTarget.value = user

    Object.assign(form, {
        name: user.name ?? '',
        email: user.email ?? '',
        password: '',
        password_confirmation: '',
        role: user.roles[0] ?? '',
    })

    dialog.value = true
}

async function submit() {
    errorMessage.value = ''

    const payload = {
        name: form.name.trim(),
        email: form.email.trim(),
    }

    if (form.password) {
        payload.password = form.password
        payload.password_confirmation = form.password_confirmation
    }

    if (form.role) {
        payload.roles = [form.role]
    }

    try {
        await usersStore.saveUser(payload, deleteTarget.value?.id ?? null)
        dialog.value = false
        deleteTarget.value = null
        await load()
    } catch (error) {
        errorMessage.value = usersStore.error
            ?? error.response?.data?.message
            ?? localeStore.t('users.saveFailed')
    }
}

function askDelete(user) {
    errorMessage.value = ''
    deleteTarget.value = user
    deleteDialog.value = true
}

async function confirmDelete() {
    try {
        await usersStore.removeUser(deleteTarget.value.id)
        deleteDialog.value = false
        deleteTarget.value = null
        await load()
    } catch {
        // The error is shown by the alert above the table.
    }
}

let debounce = null

watch([search, roleFilter], () => {
    options.value.page = 1
    clearTimeout(debounce)
    debounce = setTimeout(load, 300)
})

onMounted(load)
</script>

<template>
    <v-container class="py-8" fluid>
        <v-row>
            <v-col cols="12">
                <div class="d-flex flex-wrap align-center justify-space-between ga-4 mb-6">
                    <div>
                        <v-card-subtitle>{{ $t('users.listSubtitle') }}</v-card-subtitle>
                        <v-card-title class="text-h4 font-weight-bold">
                            {{ $t('users.title') }}
                        </v-card-title>
                        <v-card-text class="text-medium-emphasis pa-0 mt-1">
                            {{ $t('users.intro') }}
                        </v-card-text>
                    </div>
                    <v-btn
                        v-if="canManage"
                        color="primary"
                        prepend-icon="mdi-account-plus-outline"
                        @click="openCreate"
                    >
                        {{ $t('users.addUser') }}
                    </v-btn>
                </div>

                <v-card elevation="2">
                    <v-card-text>
                        <v-row align="center">
                            <v-col cols="12" md="4">
                                <v-text-field
                                    v-model="search"
                                    clearable
                                    density="comfortable"
                                    :label="$t('common.search')"
                                    :placeholder="$t('users.searchPlaceholder')"
                                    prepend-inner-icon="mdi-magnify"
                                    variant="outlined"
                                />
                            </v-col>
                            <v-col cols="12" sm="6" md="3">
                                <v-select
                                    v-model="roleFilter"
                                    :items="roleFilterItems"
                                    :label="$t('common.role')"
                                    variant="outlined"
                                />
                            </v-col>
                        </v-row>
                    </v-card-text>

                    <v-divider />

                    <v-alert
                        v-if="usersStore.error"
                        class="ma-4"
                        closable
                        color="error"
                        density="comfortable"
                        type="error"
                        variant="tonal"
                        @click:close="usersStore.clearError()"
                    >
                        {{ usersStore.error }}
                    </v-alert>

                    <v-data-table-server
                        :headers="headers"
                        :items="usersStore.items"
                        :items-length="usersStore.meta.total"
                        :loading="usersStore.loading"
                        :page="usersStore.meta.current_page"
                        :page-size="usersStore.meta.per_page"
                        density="comfortable"
                        item-value="id"
                    >
                        <template #item.name="{ item }">
                            <div class="d-flex align-center ga-2">
                                <span class="font-weight-medium">{{ item.name }}</span>
                                <v-chip
                                    v-if="item.id === currentUserId"
                                    color="primary"
                                    size="x-small"
                                    variant="tonal"
                                >
                                    {{ $t('users.you') }}
                                </v-chip>
                            </div>
                        </template>

                        <template #item.email="{ item }">
                            <span class="text-body-2">{{ item.email }}</span>
                        </template>

                        <template #item.roles="{ item }">
                            <div class="d-flex flex-wrap ga-1">
                                <v-chip
                                    v-for="role in item.roles"
                                    :key="role"
                                    size="small"
                                    variant="tonal"
                                >
                                    {{ roleTitle(role) }}
                                </v-chip>
                                <span v-if="!item.roles.length" class="text-caption">
                                    {{ $t('common.noRole') }}
                                </span>
                            </div>
                        </template>

                        <template #item.created_at="{ item }">
                            <span class="text-caption text-medium-emphasis">
                                {{ formatDate(item.created_at) }}
                            </span>
                        </template>

                        <template #item.actions="{ item }">
                            <div v-if="canManage" class="d-flex justify-end ga-1">
                                <v-btn
                                    :aria-label="$t('common.edit')"
                                    density="compact"
                                    icon="mdi-pencil-outline"
                                    size="small"
                                    variant="text"
                                    @click="openEdit(item)"
                                />
                                <v-btn
                                    :aria-label="$t('common.delete')"
                                    color="error"
                                    density="compact"
                                    icon="mdi-delete-outline"
                                    size="small"
                                    variant="text"
                                    @click="askDelete(item)"
                                />
                            </div>
                        </template>

                        <template #no-data>
                            <div class="py-6 text-medium-emphasis">{{ $t('users.noUsers') }}</div>
                        </template>
                    </v-data-table-server>

                    <v-divider />

                    <div class="d-flex align-center justify-end pa-3">
                        <v-pagination
                            :length="Math.ceil(usersStore.meta.total / usersStore.meta.per_page) || 1"
                            :model-value="usersStore.meta.current_page"
                            density="comfortable"
                            rounded
                            :total-visible="5"
                            @update:model-value="changePage"
                        />
                    </div>
                </v-card>
            </v-col>
        </v-row>

        <v-dialog v-model="dialog" max-width="560">
            <v-card>
                <v-card-title>
                    {{ deleteTarget ? $t('common.edit') : $t('users.addUser') }}
                </v-card-title>
                <v-card-text>
                    <v-alert
                        v-if="errorMessage"
                        class="mb-4"
                        color="error"
                        density="comfortable"
                        type="error"
                        variant="tonal"
                    >
                        {{ errorMessage }}
                    </v-alert>

                    <v-text-field
                        v-model="form.name"
                        :label="$t('common.name')"
                        prepend-inner-icon="mdi-account-outline"
                        variant="outlined"
                    />
                    <v-text-field
                        v-model="form.email"
                        :label="$t('users.email')"
                        prepend-inner-icon="mdi-email-outline"
                        type="email"
                        variant="outlined"
                    />
                    <v-select
                        v-if="isAdmin"
                        v-model="form.role"
                        :items="roleItems"
                        :label="$t('common.role')"
                        prepend-inner-icon="mdi-shield-account-outline"
                        variant="outlined"
                    />
                    <v-text-field
                        v-model="form.password"
                        autocomplete="new-password"
                        :hint="deleteTarget ? $t('users.passwordUnchanged') : undefined"
                        :label="$t('users.password')"
                        prepend-inner-icon="mdi-key-outline"
                        persistent-hint
                        type="password"
                        variant="outlined"
                    />
                    <v-text-field
                        v-model="form.password_confirmation"
                        autocomplete="new-password"
                        :label="$t('users.passwordConfirmation')"
                        prepend-inner-icon="mdi-key-outline"
                        type="password"
                        variant="outlined"
                    />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="dialog = false">{{ $t('common.cancel') }}</v-btn>
                    <v-btn color="primary" :loading="usersStore.saving" @click="submit">
                        {{ $t('common.save') }}
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="deleteDialog" max-width="480">
            <v-card>
                <v-card-title>{{ $t('users.deleteTitle') }}</v-card-title>
                <v-card-text>
                    <v-alert class="mb-4" color="warning" density="comfortable" variant="tonal">
                        {{ $t('users.deleteBody') }}
                    </v-alert>
                    <div v-if="deleteTarget" class="text-body-1">
                        {{ deleteTarget.name }} · {{ deleteTarget.email }}
                    </div>
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="deleteDialog = false">{{ $t('common.cancel') }}</v-btn>
                    <v-btn color="error" :loading="usersStore.saving" @click="confirmDelete">
                        {{ $t('common.delete') }}
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </v-container>
</template>
