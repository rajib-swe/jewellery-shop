<script setup>
import { reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const authStore = useAuthStore()
const route = useRoute()
const router = useRouter()
const errorMessage = ref('')

const form = reactive({
    email: '',
    password: '',
    remember: false,
})

const emailRules = [
    (value) => Boolean(value) || 'Email address is required.',
    (value) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value) || 'Enter a valid email address.',
]

const passwordRules = [
    (value) => Boolean(value) || 'Password is required.',
]

async function submit() {
    errorMessage.value = ''

    try {
        await authStore.login(form)

        const requestedRedirect = route.query.redirect
        const isLocalRedirect = typeof requestedRedirect === 'string'
            && requestedRedirect.startsWith('/')
            && !requestedRedirect.startsWith('//')
            && !requestedRedirect.includes('\\')
        const redirect = isLocalRedirect ? requestedRedirect : { name: 'dashboard' }

        await router.push(redirect)
    } catch (error) {
        errorMessage.value = error.response?.data?.errors?.email?.[0]
            ?? error.response?.data?.message
            ?? error.message
            ?? 'Unable to sign in.'
    }
}
</script>

<template>
    <v-main class="login-background">
        <v-container class="fill-height" fluid>
            <v-row align="center" justify="center">
                <v-col cols="12" sm="9" md="6" lg="4">
                    <v-card class="login-card" elevation="10">
                        <v-card-item class="pb-0">
                            <v-avatar color="primary" size="52" class="mb-5">
                                <v-icon icon="mdi-diamond-stone" size="30" />
                            </v-avatar>
                            <v-card-title class="text-h4 font-weight-bold">Jewellery Shop</v-card-title>
                            <v-card-subtitle class="text-wrap mt-2">
                                Sign in to manage sales, inventory, and pawn accounts.
                            </v-card-subtitle>
                        </v-card-item>

                        <v-card-text>
                            <v-alert
                                v-if="errorMessage"
                                class="mb-5"
                                color="error"
                                density="comfortable"
                                variant="tonal"
                                type="error"
                            >
                                {{ errorMessage }}
                            </v-alert>

                            <v-form @submit.prevent="submit">
                                <v-text-field
                                    v-model="form.email"
                                    autocomplete="email"
                                    label="Email address"
                                    prepend-inner-icon="mdi-email-outline"
                                    :rules="emailRules"
                                    autofocus
                                />

                                <v-text-field
                                    v-model="form.password"
                                    autocomplete="current-password"
                                    label="Password"
                                    prepend-inner-icon="mdi-lock-outline"
                                    :rules="passwordRules"
                                    type="password"
                                />

                                <v-checkbox
                                    v-model="form.remember"
                                    color="primary"
                                    density="compact"
                                    hide-details
                                    label="Keep me signed in"
                                />

                                <v-btn
                                    class="mt-5"
                                    color="primary"
                                    :loading="authStore.submitting"
                                    size="large"
                                    type="submit"
                                    block
                                >
                                    Sign in
                                </v-btn>
                            </v-form>
                        </v-card-text>
                    </v-card>
                </v-col>
            </v-row>
        </v-container>
    </v-main>
</template>

<style scoped>
.login-background {
    background:
        radial-gradient(circle at 15% 20%, rgb(196 154 84 / 28%), transparent 32%),
        radial-gradient(circle at 85% 80%, rgb(79 93 117 / 20%), transparent 35%),
        #f8f5ef;
}

.login-card {
    border: 1px solid rgb(138 106 50 / 16%);
}
</style>
