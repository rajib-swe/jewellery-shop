<script setup>
import { computed, ref } from 'vue'
import { useAuthStore } from '../stores/auth'
import { useLocaleStore } from '../stores/locale'
import { usePwaStore } from '../stores/pwa'

const authStore = useAuthStore()
const localeStore = useLocaleStore()
const pwaStore = usePwaStore()

const updating = ref(false)

const showInstall = computed(() => pwaStore.canInstall)
const showIosSteps = computed(() => pwaStore.canInstall && pwaStore.isIos)

async function handleInstall() {
    if (pwaStore.isIos) {
        return
    }

    await pwaStore.promptInstall()
}

async function handleUpdate() {
    updating.value = true

    try {
        await pwaStore.applyUpdate()
    } finally {
        updating.value = false
    }
}
</script>

<template>
    <v-snackbar
        v-if="showInstall"
        :model-value="showInstall"
        color="primary"
        location="bottom"
        :timeout="-1"
        :aria-label="$t('pwa.installTitle')"
        @update:model-value="pwaStore.dismissInstall()"
    >
        <v-icon icon="mdi-cellphone-arrow-down" class="mr-2" />
        <span class="font-weight-medium">
            {{ showIosSteps ? $t('pwa.iosInstallBody') : $t('pwa.installBody') }}
        </span>

        <template #actions>
            <v-btn v-if="!showIosSteps" color="white" variant="text" @click="handleInstall">
                {{ $t('pwa.installAction') }}
            </v-btn>
            <v-btn color="white" variant="text" @click="pwaStore.dismissInstall()">
                {{ $t('pwa.installDismiss') }}
            </v-btn>
        </template>
    </v-snackbar>

    <v-snackbar
        v-model="pwaStore.updateReady"
        color="info"
        location="bottom"
        :timeout="-1"
        :aria-label="$t('pwa.updateTitle')"
    >
        <v-icon icon="mdi-update" class="mr-2" />
        {{ updating ? $t('pwa.updating') : $t('pwa.updateTitle') }}

        <template #actions>
            <v-btn color="white" variant="text" :loading="updating" @click="handleUpdate">
                {{ $t('pwa.updateAction') }}
            </v-btn>
        </template>
    </v-snackbar>

    <v-snackbar
        v-model="authStore.sessionExpired"
        color="error"
        location="bottom"
        timeout="6000"
        :aria-label="$t('pwa.sessionExpired')"
    >
        <v-icon icon="mdi-lock-alert" class="mr-2" />
        {{ $t('pwa.sessionExpired') }}
    </v-snackbar>
</template>
