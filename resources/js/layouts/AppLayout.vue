<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useDisplay } from 'vuetify'
import { useAuthStore } from '../stores/auth'
import { useGoldRatesStore } from '../stores/gold-rates'
import { useLocaleStore } from '../stores/locale'
import { usePwaStore } from '../stores/pwa'
import { useSettingsStore } from '../stores/settings'

const authStore = useAuthStore()
const goldRateStore = useGoldRatesStore()
const localeStore = useLocaleStore()
const pwaStore = usePwaStore()
const settingsStore = useSettingsStore()
const route = useRoute()
const router = useRouter()
const { mdAndUp } = useDisplay()

const drawer = ref(mdAndUp.value)
const logoutError = ref(false)

const APP_BAR_HEIGHT = 'calc(64px + env(safe-area-inset-top, 0px))'
const BOTTOM_NAV_HEIGHT = 'calc(56px + env(safe-area-inset-bottom, 0px))'

watch(mdAndUp, (value) => {
    drawer.value = value
})

const navigation = computed(() => [
    {
        id: 'dashboard',
        title: localeStore.t('nav.dashboard'),
        icon: 'mdi-view-dashboard-outline',
        to: { name: 'dashboard' },
        permission: 'access api',
    },
    {
        id: 'gold-rates',
        title: localeStore.t('nav.goldRates'),
        icon: 'mdi-chart-line-variant',
        to: { name: 'gold-rates' },
        permission: 'view gold rates',
    },
    {
        id: 'customers',
        title: localeStore.t('nav.customers'),
        icon: 'mdi-account-group-outline',
        to: { name: 'customers' },
        permission: 'view customers',
    },
    {
        id: 'sales',
        title: localeStore.t('nav.sales'),
        icon: 'mdi-receipt-text-outline',
        to: { name: 'sales' },
        permission: 'view sales',
    },
    {
        id: 'sale-create',
        title: localeStore.t('nav.newSale'),
        icon: 'mdi-cart-arrow-right',
        to: { name: 'sale-create' },
        permission: 'manage sales',
    },
    {
        id: 'pawns',
        title: localeStore.t('nav.pawns'),
        icon: 'mdi-handshake-outline',
        to: { name: 'pawns' },
        permission: 'view pawns',
    },
    {
        id: 'pawn-create',
        title: localeStore.t('nav.newPawn'),
        icon: 'mdi-plus-circle-outline',
        to: { name: 'pawn-create' },
        permission: 'manage pawns',
    },
    {
        id: 'purchases',
        title: localeStore.t('nav.purchases'),
        icon: 'mdi-truck-delivery-outline',
        to: { name: 'purchases' },
        permission: 'view purchases',
    },
    {
        id: 'purchase-create',
        title: localeStore.t('nav.newPurchase'),
        icon: 'mdi-cart-arrow-down',
        to: { name: 'purchase-create' },
        permission: 'manage purchases',
    },
    {
        id: 'suppliers',
        title: localeStore.t('nav.suppliers'),
        icon: 'mdi-store-outline',
        to: { name: 'suppliers' },
        permission: 'view suppliers',
    },
    {
        id: 'items',
        title: localeStore.t('nav.items'),
        icon: 'mdi-package-variant-closed',
        to: { name: 'items' },
        permission: 'view inventory',
    },
    {
        id: 'stock-summary',
        title: localeStore.t('nav.stockSummary'),
        icon: 'mdi-scale-balance',
        to: { name: 'stock-summary' },
        permission: 'view inventory',
    },
    {
        id: 'categories',
        title: localeStore.t('nav.categories'),
        icon: 'mdi-shape-outline',
        to: { name: 'categories' },
        permission: 'view inventory',
    },
    {
        id: 'item-labels',
        title: localeStore.t('nav.itemLabels'),
        icon: 'mdi-printer-outline',
        to: { name: 'item-labels' },
        permission: 'view inventory',
    },
    {
        id: 'cash-book',
        title: localeStore.t('nav.cashBook'),
        icon: 'mdi-book-open-page-variant-outline',
        to: { name: 'cash-book' },
        permission: 'view accounts',
    },
    {
        id: 'expenses',
        title: localeStore.t('nav.expenses'),
        icon: 'mdi-cash-minus',
        to: { name: 'expenses' },
        permission: 'view accounts',
    },
    {
        id: 'daily-closing',
        title: localeStore.t('nav.dailyClosing'),
        icon: 'mdi-lock-check-outline',
        to: { name: 'daily-closing' },
        permission: 'view accounts',
    },
    {
        id: 'reports',
        title: localeStore.t('nav.reports'),
        icon: 'mdi-chart-box-outline',
        to: { name: 'reports' },
        permission: 'view reports',
    },
    {
        id: 'settings',
        title: localeStore.t('nav.settings'),
        icon: 'mdi-cog-outline',
        to: { name: 'settings' },
        permission: 'view settings',
    },
    {
        id: 'admin-users',
        title: localeStore.t('nav.users'),
        icon: 'mdi-account-cog-outline',
        to: { name: 'admin-users' },
        permission: 'view users',
    },
    {
        id: 'admin-roles',
        title: localeStore.t('nav.roles'),
        icon: 'mdi-shield-account-outline',
        to: { name: 'admin-roles' },
        permission: 'view roles',
    },
    {
        id: 'admin-activity-log',
        title: localeStore.t('nav.activityLog'),
        icon: 'mdi-history',
        to: { name: 'admin-activity-log' },
        permission: 'view activity log',
    },
    {
        id: 'admin-backups',
        title: localeStore.t('nav.backups'),
        icon: 'mdi-database-outline',
        to: { name: 'admin-backups' },
        permission: 'manage backups',
    },
])

const visibleNavigation = computed(() => navigation.value.filter(
    (item) => !item.permission || authStore.can(item.permission),
))

function pickByPermission(permission) {
    return visibleNavigation.value.find((item) => item.permission === permission) ?? null
}

// Two screens either side of the sale shortcut, so the thumb lands on it.
const bottomLeading = computed(() => [
    pickByPermission('access api'),
    pickByPermission('view sales'),
].filter(Boolean))

const bottomTrailing = computed(() => [
    pickByPermission('view customers'),
    pickByPermission('view pawns'),
].filter(Boolean))

const bottomShortcut = computed(() => pickByPermission('manage sales'))

const activeBottomItem = computed(() => [...bottomLeading.value, ...bottomTrailing.value]
    .find((item) => item.id === route.name)?.id ?? null)

const shopName = computed(() => settingsStore.settings.shop_name || localeStore.t('common.appName'))
const latestRate = computed(() => goldRateStore.latest.find((rate) => rate.karat === 22)
    ?? goldRateStore.latest[0]
    ?? null)
const currencySymbol = computed(() => settingsStore.settings.currency_symbol || '৳')
const buildStepLabel = computed(() => localeStore.t('nav.buildStep'))
const noRateLabel = computed(() => localeStore.t('goldRates.title'))
const latestRatePerGram = computed(() => `/${localeStore.t('units.gram')}`)

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
                    <div class="text-caption text-medium-emphasis">{{ $t('nav.operations') }}</div>
                </div>
            </div>
        </div>

        <v-divider />

        <v-list nav density="comfortable">
            <v-list-item
                v-for="item in visibleNavigation"
                :key="item.id"
                :prepend-icon="item.icon"
                :title="item.title"
                :to="item.to"
            />
        </v-list>

        <template #append>
            <div class="pa-4 text-caption text-medium-emphasis">{{ buildStepLabel }}</div>
        </template>
    </v-navigation-drawer>

    <v-app-bar :height="APP_BAR_HEIGHT" color="surface" flat border>
        <v-app-bar-nav-icon :aria-label="$t('nav.operations')" @click="drawer = !drawer" />
        <v-app-bar-title>{{ shopName }}</v-app-bar-title>

        <v-spacer />

        <v-chip
            v-if="!pwaStore.online"
            class="mr-3"
            color="warning"
            prepend-icon="mdi-cloud-off-outline"
            variant="tonal"
        >
            {{ $t('pwa.offlineShort') }}
        </v-chip>
        <v-chip
            v-else-if="latestRate"
            class="mr-3 d-none d-sm-flex"
            color="secondary"
            prepend-icon="mdi-chart-line-variant"
            variant="tonal"
        >
            {{ latestRate.karat }}K · {{ currencySymbol }}{{ formatRate(latestRate.rate_per_gram) }}{{ latestRatePerGram }}
        </v-chip>
        <v-chip v-else class="mr-3 d-none d-sm-flex" color="warning" variant="tonal">
            {{ noRateLabel }}
        </v-chip>

        <v-btn-toggle
            :aria-label="$t('common.language')"
            class="mr-2 d-none d-sm-flex"
            color="primary"
            density="compact"
            mandatory
            :model-value="localeStore.locale"
            variant="outlined"
        >
            <v-btn
                v-for="option in localeStore.supportedLocales"
                :key="option.code"
                :aria-label="option.title"
                :title="option.title"
                :value="option.code"
            >
                {{ option.title }}
            </v-btn>
        </v-btn-toggle>

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
                <v-divider class="d-sm-none" />
                <v-list-item
                    v-for="option in localeStore.supportedLocales"
                    :key="`menu-${option.code}`"
                    class="d-sm-none"
                    :prepend-icon="localeStore.locale === option.code ? 'mdi-check-bold' : 'mdi-translate'"
                    :title="option.title"
                    @click="localeStore.setLocale(option.code)"
                />
                <v-divider />
                <v-list-item
                    prepend-icon="mdi-logout"
                    :title="$t('auth.signOut')"
                    @click="handleLogout"
                />
            </v-list>
        </v-menu>
    </v-app-bar>

    <v-main>
        <div v-if="!pwaStore.online" class="sticky-top">
            <v-alert
                color="warning"
                density="compact"
                icon="mdi-cloud-off-outline"
                variant="tonal"
                class="rounded-0"
            >
                {{ $t('pwa.offlineBody') }}
            </v-alert>
        </div>

        <router-view />
    </v-main>

    <v-bottom-navigation
        :height="BOTTOM_NAV_HEIGHT"
        :model-value="activeBottomItem"
        bg-color="surface"
        grow
        mode="shift"
        class="d-md-none"
    >
        <v-btn
            v-for="item in bottomLeading"
            :key="`lead-${item.id}`"
            :to="item.to"
            :value="item.id"
        >
            <v-icon :icon="item.icon" />
            <span>{{ item.title }}</span>
        </v-btn>

        <template #activator>
            <v-btn
                v-if="bottomShortcut"
                :to="bottomShortcut.to"
                color="primary"
                icon="mdi-cart-arrow-right"
                size="large"
                variant="flat"
            />
        </template>

        <v-btn
            v-for="item in bottomTrailing"
            :key="`trail-${item.id}`"
            :to="item.to"
            :value="item.id"
        >
            <v-icon :icon="item.icon" />
            <span>{{ item.title }}</span>
        </v-btn>
    </v-bottom-navigation>

    <v-snackbar v-model="logoutError" color="error" timeout="5000">
        {{ $t('auth.signOutFailed') }}
    </v-snackbar>
</template>
