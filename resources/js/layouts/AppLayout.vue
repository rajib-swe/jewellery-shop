<script setup>
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const authStore = useAuthStore()
const router = useRouter()
const drawer = ref(true)
const logoutError = ref(false)

const navigation = [
    {
        title: 'Dashboard',
        icon: 'mdi-view-dashboard-outline',
        to: { name: 'dashboard' },
        permission: 'access api',
    },
]

const visibleNavigation = computed(() => navigation.filter(
    (item) => !item.permission || authStore.can(item.permission),
))

const initials = computed(() => {
    const name = authStore.user?.name?.trim() ?? ''

    if (!name) {
        return 'U'
    }

    return name
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('')
})

async function handleLogout() {
    logoutError.value = false

    try {
        await authStore.logout()
        await router.push({ name: 'login' })
    } catch {
        logoutError.value = true
    }
}
</script>

<template>
    <v-navigation-drawer v-model="drawer" color="surface">
        <div class="pa-5">
            <div class="d-flex align-center ga-3">
                <v-avatar color="primary" size="42">
                    <v-icon icon="mdi-diamond-stone" />
                </v-avatar>
                <div>
                    <div class="text-subtitle-1 font-weight-bold">Jewellery Shop</div>
                    <div class="text-caption text-medium-emphasis">Operations console</div>
                </div>
            </div>
        </div>

        <v-divider />

        <v-list nav density="comfortable">
            <v-list-item
                v-for="item in visibleNavigation"
                :key="item.title"
                :prepend-icon="item.icon"
                :title="item.title"
                :to="item.to"
            />
        </v-list>

        <template #append>
            <div class="pa-4 text-caption text-medium-emphasis">Step 1 bootstrap</div>
        </template>
    </v-navigation-drawer>

    <v-app-bar color="surface" flat border>
        <v-app-bar-nav-icon aria-label="Toggle navigation" @click="drawer = !drawer" />
        <v-app-bar-title>Jewellery Shop</v-app-bar-title>

        <v-spacer />

        <v-menu v-if="authStore.user" location="bottom end">
            <template #activator="{ props }">
                <v-btn v-bind="props" variant="text" class="px-2">
                    <v-avatar color="primary" size="36">
                        <span class="text-subtitle-2 font-weight-bold">{{ initials }}</span>
                    </v-avatar>
                    <v-icon icon="mdi-chevron-down" size="small" />
                </v-btn>
            </template>

            <v-list width="280">
                <v-list-item
                    :title="authStore.user.name"
                    :subtitle="authStore.user.email"
                    prepend-icon="mdi-account-circle-outline"
                />
                <v-divider />
                <v-list-item
                    prepend-icon="mdi-logout"
                    title="Sign out"
                    @click="handleLogout"
                />
            </v-list>
        </v-menu>
    </v-app-bar>

    <v-main>
        <router-view />
    </v-main>

    <v-snackbar v-model="logoutError" color="error" timeout="5000">
        Unable to sign out. Check your connection and try again.
    </v-snackbar>
</template>
