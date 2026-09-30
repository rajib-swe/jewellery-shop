<script setup>
import PwaStatus from './components/PwaStatus.vue'
</script>

<template>
    <v-app>
        <router-view />

        <template #overlay>
            <PwaStatus />
        </template>
    </v-app>
</template>
