<script setup>
import { computed, onMounted, ref } from 'vue'
import { PERMISSION_GROUPS } from '../../constants/admin'
import { useAuthStore } from '../../stores/auth'
import { useLocaleStore } from '../../stores/locale'
import { useRolesStore } from '../../stores/roles'

const authStore = useAuthStore()
const localeStore = useLocaleStore()
const rolesStore = useRolesStore()
const canManage = computed(() => authStore.can('manage roles'))
const errorMessage = ref('')
const notice = ref('')

// Only the permissions the API actually knows about are drawn, so a
// permission added to the seeder but missing here shows up as an absent row
// rather than a checkbox that saves nothing.
const groups = computed(() => PERMISSION_GROUPS
    .map((group) => ({
        ...group,
        permissions: group.permissions.filter((permission) => rolesStore.permissions.some(
            (item) => item.name === permission,
        )),
    }))
    .filter((group) => group.permissions.length > 0))

function roleTitle(roleName) {
    const key = `common.roles.${roleName}`

    return localeStore.t(key) === key ? roleName : localeStore.t(key)
}

function permissionTitle(permission) {
    const key = `roles.permissions.${camelCase(permission)}`

    return localeStore.t(key) === key ? sentenceCase(permission) : localeStore.t(key)
}

async function load() {
    errorMessage.value = ''

    try {
        await rolesStore.fetchAll(true)
    } catch {
        errorMessage.value = rolesStore.error ?? localeStore.t('errors.connection')
    }
}

async function save(roleName) {
    errorMessage.value = ''
    notice.value = ''

    try {
        await rolesStore.saveRole(roleName)
        notice.value = localeStore.t('roles.saved').replace('{role}', roleTitle(roleName))

        // The signed-in user's own permissions may have just changed, so the
        // cached session is dropped and the router guard refetches it. Without
        // this the drawer would keep showing menus the server now refuses.
        if (authStore.user?.roles.includes(roleName)) {
            authStore.initialized = false
        }
    } catch {
        errorMessage.value = rolesStore.error ?? localeStore.t('errors.connection')
    }
}

function revert() {
    rolesStore.resetDraft()
    notice.value = ''
}

function camelCase(permission) {
    return permission.replace(/[- ]/g, '_')
}

function sentenceCase(permission) {
    const words = permission.split(' ')

    return words
        .map((word, index) => (index === 0 ? word.charAt(0).toUpperCase() + word.slice(1) : word))
        .join(' ')
}

onMounted(load)
</script>

<template>
    <v-container class="py-8" fluid>
        <v-row>
            <v-col cols="12">
                <v-card-subtitle>{{ $t('roles.listSubtitle') }}</v-card-subtitle>
                <v-card-title class="text-h4 font-weight-bold">{{ $t('roles.title') }}</v-card-title>
                <v-card-text class="text-medium-emphasis pa-0 mt-1 mb-6">
                    {{ $t('roles.intro') }}
                </v-card-text>

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

                <v-alert
                    v-if="notice"
                    class="mb-6"
                    closable
                    color="success"
                    density="comfortable"
                    type="success"
                    variant="tonal"
                    @click:close="notice = ''"
                >
                    {{ notice }}
                </v-alert>

                <v-alert
                    v-if="!canManage"
                    class="mb-6"
                    color="info"
                    density="comfortable"
                    variant="tonal"
                >
                    {{ $t('roles.readOnlyHint') }}
                </v-alert>

                <v-card elevation="2" :loading="rolesStore.loading">
                    <v-table density="comfortable">
                        <thead>
                            <tr>
                                <th class="text-left">{{ $t('roles.permission') }}</th>
                                <th
                                    v-for="role in rolesStore.roles"
                                    :key="role.name"
                                    class="text-center"
                                >
                                    <div>{{ roleTitle(role.name) }}</div>
                                    <div class="text-caption text-medium-emphasis">
                                        {{ $t('roles.usersCount') }}: {{ role.users_count }}
                                    </div>
                                </th>
                                <th class="pa-2" />
                            </tr>
                        </thead>
                        <tbody>
                            <template
                                v-for="group in groups"
                                :key="group.name"
                            >
                                <tr>
                                    <td class="bg-grey-lighten-4 pa-2">
                                        <span class="text-overline font-weight-bold">
                                            {{ $t(`roles.groups.${group.name}`) }}
                                        </span>
                                    </td>
                                    <td
                                        v-for="role in rolesStore.roles"
                                        :key="`${role.name}-${group.name}`"
                                        class="bg-grey-lighten-4"
                                    />
                                    <td class="bg-grey-lighten-4 pa-2" />
                                </tr>
                                <tr
                                    v-for="permission in group.permissions"
                                    :key="permission"
                                >
                                    <td>
                                        <span class="text-body-2">
                                            {{ permissionTitle(permission) }}
                                        </span>
                                    </td>
                                    <td
                                        v-for="role in rolesStore.roles"
                                        :key="`${role.name}-${permission}`"
                                        class="text-center"
                                    >
                                        <v-checkbox-btn
                                            :disabled="!canManage"
                                            :model-value="rolesStore.isSelected(role.name, permission)"
                                            color="primary"
                                            density="compact"
                                            hide-details
                                            @update:model-value="rolesStore.togglePermission(role.name, permission)"
                                        />
                                    </td>
                                    <td class="pa-2" />
                                </tr>
                            </template>
                            <tr>
                                <td class="pa-2" />
                                <td
                                    v-for="role in rolesStore.roles"
                                    :key="`${role.name}-actions`"
                                    class="text-center pa-2"
                                >
                                    <div class="d-flex justify-center ga-1">
                                        <v-btn
                                            :disabled="!canManage || !rolesStore.isDirty(role.name)"
                                            :loading="rolesStore.saving"
                                            color="primary"
                                            density="compact"
                                            size="small"
                                            variant="tonal"
                                            @click="save(role.name)"
                                        >
                                            {{ $t('common.save') }}
                                        </v-btn>
                                        <v-btn
                                            v-if="canManage && rolesStore.isDirty(role.name)"
                                            density="compact"
                                            size="small"
                                            variant="text"
                                            @click="revert"
                                        >
                                            {{ $t('common.cancel') }}
                                        </v-btn>
                                    </div>
                                </td>
                                <td class="pa-2" />
                            </tr>
                        </tbody>
                    </v-table>
                </v-card>
            </v-col>
        </v-row>
    </v-container>
</template>

<style scoped>
:deep(table) {
    min-width: 720px;
}
</style>
