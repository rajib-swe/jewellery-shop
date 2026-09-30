<script setup>
import { computed } from 'vue'
import { useAuthStore } from '../../stores/auth'
import { useLocaleStore } from '../../stores/locale'

const authStore = useAuthStore()
const localeStore = useLocaleStore()

/**
 * The report index.
 *
 * Each entry is a plain link with its own permission, so the list can offer the
 * ledgers and the profit statement without a second navigation tree hiding
 * them behind tabs.
 */
const entries = computed(() => [
    {
        title: localeStore.t('reports.salesTitle'),
        description: localeStore.t('reports.salesSubtitle'),
        icon: 'mdi-chart-bar',
        to: { name: 'report-sales' },
        permission: 'view reports',
    },
    {
        title: localeStore.t('reports.stockTitle'),
        description: localeStore.t('reports.stockSubtitle'),
        icon: 'mdi-scale-balance',
        to: { name: 'report-stock' },
        permission: 'view reports',
    },
    {
        title: localeStore.t('reports.pawnTitle'),
        description: localeStore.t('reports.pawnSubtitle'),
        icon: 'mdi-handshake-outline',
        to: { name: 'report-pawns' },
        permission: 'view reports',
    },
    {
        title: localeStore.t('reports.overdueTitle'),
        description: localeStore.t('reports.overdueSubtitle'),
        icon: 'mdi-calendar-alert',
        to: { name: 'report-overdue-pawns' },
        permission: 'view reports',
    },
    {
        title: localeStore.t('reports.interestTitle'),
        description: localeStore.t('reports.interestSubtitle'),
        icon: 'mdi-percent-outline',
        to: { name: 'report-interest' },
        permission: 'view reports',
    },
    {
        title: localeStore.t('reports.profitTitle'),
        description: localeStore.t('reports.profitSubtitle'),
        icon: 'mdi-chart-line-variant',
        to: { name: 'report-profit' },
        permission: 'view reports',
    },
    {
        title: localeStore.t('reports.customerTitle'),
        description: localeStore.t('reports.customerSubtitle'),
        icon: 'mdi-bank-outline',
        to: { name: 'report-customer-ledger' },
        permission: 'view reports',
    },
    {
        title: localeStore.t('reports.supplierTitle'),
        description: localeStore.t('reports.supplierSubtitle'),
        icon: 'mdi-truck-delivery-outline',
        to: { name: 'report-supplier-ledger' },
        permission: 'view reports',
    },
])

const visible = computed(() => entries.value.filter(
    (entry) => !entry.permission || authStore.can(entry.permission),
))
</script>

<template>
    <v-container class="py-8" fluid>
        <v-row>
            <v-col cols="12">
                <div class="mb-6">
                    <v-card-subtitle>{{ $t('reports.subtitle') }}</v-card-subtitle>
                    <v-card-title class="text-h4 font-weight-bold">
                        {{ $t('reports.title') }}
                    </v-card-title>
                    <v-card-text class="text-medium-emphasis pa-0 mt-1">
                        {{ $t('reports.intro') }}
                    </v-card-text>
                </div>

                <v-row>
                    <v-col
                        v-for="entry in visible"
                        :key="entry.title"
                        cols="12"
                        sm="6"
                        md="4"
                        lg="3"
                    >
                        <v-card class="h-100" :to="entry.to" elevation="2">
                            <v-card-text>
                                <v-icon :icon="entry.icon" size="30" />
                                <div class="text-subtitle-1 font-weight-medium mt-3">
                                    {{ entry.title }}
                                </div>
                                <div class="text-caption text-medium-emphasis mt-1">
                                    {{ entry.description }}
                                </div>
                            </v-card-text>
                        </v-card>
                    </v-col>
                </v-row>
            </v-col>
        </v-row>
    </v-container>
</template>
