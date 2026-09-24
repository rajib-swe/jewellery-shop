<script setup>
import { computed } from 'vue'
import { useAuthStore } from '../stores/auth'

const authStore = useAuthStore()

const roleLabel = computed(() => {
    if (!authStore.primaryRole) {
        return 'No role'
    }

    return authStore.primaryRole.charAt(0).toUpperCase() + authStore.primaryRole.slice(1)
})

const capabilities = [
    'Sanctum session authentication',
    'Role and permission middleware',
    'Server-backed user activity logging',
    'Precision-safe weight conversions',
]
</script>

<template>
    <v-container class="py-8" fluid>
        <v-row>
            <v-col cols="12" lg="8">
                <v-card class="h-100" elevation="2">
                    <v-card-item>
                        <v-card-subtitle>Workspace overview</v-card-subtitle>
                        <v-card-title class="text-h4 font-weight-bold">
                            Welcome, {{ authStore.user?.name }}
                        </v-card-title>
                    </v-card-item>

                    <v-card-text>
                        <p class="text-body-1 text-medium-emphasis mb-6">
                            The application shell is ready. Modules will be added one build-plan step at a time.
                        </p>

                        <v-list lines="two">
                            <v-list-item
                                v-for="capability in capabilities"
                                :key="capability"
                                prepend-icon="mdi-check-circle-outline"
                                :title="capability"
                            />
                        </v-list>
                    </v-card-text>
                </v-card>
            </v-col>

            <v-col cols="12" lg="4">
                <v-card class="h-100" color="primary" theme="light">
                    <v-card-text class="d-flex flex-column align-center text-center py-10">
                        <v-avatar color="primary" size="64" class="mb-5">
                            <v-icon icon="mdi-account-circle" size="38" />
                        </v-avatar>
                        <div class="text-h5 font-weight-bold">{{ authStore.user?.name }}</div>
                        <div class="text-body-2 text-medium-emphasis mt-1">
                            {{ authStore.user?.email }}
                        </div>
                        <v-chip class="mt-5" color="primary" variant="flat">
                            {{ roleLabel }}
                        </v-chip>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>
    </v-container>
</template>
