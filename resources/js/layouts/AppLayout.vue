<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useGoldRatesStore } from '../stores/gold-rates'
import { useSettingsStore } from '../stores/settings'

const authStore = useAuthStore()
const goldRateStore = useGoldRatesStore()
const settingsStore = useSettingsStore()
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
    {
        title: 'Gold Rates',
        icon: 'mdi-chart-line-variant',
        to: { name: 'gold-rates' },
        permission: 'view gold rates',
    },
    {
        title: 'Settings',
        icon: 'mdi-cog-outline',
        to: { name: 'settings' },
        permission: 'view settings',
    },
]

const visibleNavigation = computed(() => navigation.filter(
    (item) => !item.permission || authStore.can(item.permission),
))

const shopName = computed(() => settingsStore.settings.shop_name || 'Jewellery Shop')
const latestRate = computed(() => goldRateStore.latest.find((rate) => rate.karat === 22)
    ?? goldRateStore.latest[0]
    ?? null)
const currencySymbol = computed(() => settingsStore.settings.currency_symbol || '৳')

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

function formatRate(value) {
    return Number(value || 0).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })
}

async function loadHeaderData() {
    await Promise.allSettled([
        settingsStore.fetchSettings(),
        goldRateStore.fetchLatest(),
    ])
}

async function handleLogout() {
    logoutError.value = false

    try {
        await authStore.logout()
        await router.push({ name: 'login' })
    } catch {
        logoutError.value = true
    }
}

onMounted(loadHeaderData)
</script>

<template>
    <v-navigation-drawer v-model="drawer" color="surface">
        <div class="pa-5">
            <div class="d-flex align-center ga-3">
                <v-avatar color="primary" size="42">
                    <v-icon icon="mdi-diamond-stone" />
                </v-avatar>
                <div>
                    <div class="text-subtitle-1 font-weight-bold">{{ shopName }}</div>
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
            <div class="pa-4 text-caption text-medium-emphasis">Step 2 · Settings &amp; rates</div>
        </template>
    </v-navigation-drawer>

    <v-app-bar color="surface" flat border>
        <v-app-bar-nav-icon aria-label="Toggle navigation" @click="drawer = !drawer" />
        <v-app-bar-title>{{ shopName }}</v-app-bar-title>

        <v-spacer />

        <v-chip
            v-if="latestRate"
            class="mr-3 d-none d-sm-flex"
            color="secondary"
            prepend-icon="mdi-chart-line-variant"
            variant="tonal"
        >
            {{ latestRate.karat }}K · {{ currencySymbol }}{{ formatRate(latestRate.rate_per_gram) }}/g
        </v-chip>
        <v-chip v-else class="mr-3 d-none d-sm-flex" color="warning" variant="tonal">
            No gold rate
        </v-chip>

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
