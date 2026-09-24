<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const authStore = useAuthStore()
const router = useRouter()
const errorMessage = ref('')
const submitting = ref(false)

async function continueToLogin() {
    errorMessage.value = ''

    if (!authStore.isAuthenticated) {
        await router.push({ name: 'login' })

        return
    }

    submitting.value = true

    try {
        await authStore.logout()
        await router.push({ name: 'login' })
    } catch {
        errorMessage.value = 'Unable to end the current session. Please try again.'
    } finally {
        submitting.value = false
    }
}
</script>

<template>
    <v-main class="access-denied-background">
        <v-container class="fill-height" fluid>
            <v-row align="center" justify="center">
                <v-col cols="12" sm="8" md="5">
                    <v-card class="pa-3" elevation="8">
                        <v-card-text class="text-center py-10">
                            <v-avatar color="error" variant="tonal" size="72" class="mb-5">
                                <v-icon icon="mdi-shield-alert-outline" size="38" />
                            </v-avatar>
                            <v-card-title class="text-h4 font-weight-bold">Access denied</v-card-title>
                            <v-card-text class="mt-3 text-body-1 text-medium-emphasis">
                                Your account does not have permission to open this area.
                            </v-card-text>

                            <v-alert
                                v-if="errorMessage"
                                class="mt-5 text-left"
                                color="error"
                                density="comfortable"
                                variant="tonal"
                                type="error"
                            >
                                {{ errorMessage }}
                            </v-alert>

                            <v-btn
                                class="mt-6"
                                color="primary"
                                :loading="submitting"
                                size="large"
                                @click="continueToLogin"
                            >
                                {{ authStore.isAuthenticated ? 'Sign out' : 'Go to sign in' }}
                            </v-btn>
                        </v-card-text>
                    </v-card>
                </v-col>
            </v-row>
        </v-container>
    </v-main>
</template>

<style scoped>
.access-denied-background {
    background: #f8f5ef;
}
</style>
