<script setup>
import { computed, onMounted, ref } from 'vue'
import { backupDownloadUrl } from '../../api/backups'
import { useBackupsStore } from '../../stores/backups'
import { useLocaleStore } from '../../stores/locale'

const localeStore = useLocaleStore()
const backupsStore = useBackupsStore()
const deleteDialog = ref(false)
const deleteTarget = ref(null)
const errorMessage = ref('')
const notice = ref('')

const headers = computed(() => [
    { title: localeStore.t('backup.file'), key: 'name' },
    { title: localeStore.t('backup.takenOn'), key: 'created_on' },
    { title: localeStore.t('backup.size'), key: 'size', align: 'end' },
    { title: '', key: 'actions', sortable: false, align: 'end' },
])

function formatSize(bytes) {
    if (!bytes) {
        return '0 kB'
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} kB`
    }

    return `${(bytes / (1024 * 1024)).toFixed(2)} MB`
}

function latestName() {
    return backupsStore.items[0]?.name ?? null
}

async function load() {
    backupsStore.clearError()

    try {
        await backupsStore.fetchList(true)
    } catch {
        errorMessage.value = backupsStore.error ?? localeStore.t('errors.connection')
    }
}

async function run() {
    errorMessage.value = ''
    notice.value = ''

    try {
        const response = await backupsStore.run()
        notice.value = localeStore.t('backup.completed').replace('{rows}', response.meta.rows)
        await load()
    } catch {
        errorMessage.value = backupsStore.error ?? localeStore.t('errors.connection')
    }
}

function askDelete(backup) {
    errorMessage.value = ''
    deleteTarget.value = backup
    deleteDialog.value = true
}

async function confirmDelete() {
    try {
        await backupsStore.remove(deleteTarget.value.name)
        deleteDialog.value = false
        deleteTarget.value = null
    } catch {
        errorMessage.value = backupsStore.error ?? localeStore.t('errors.connection')
        deleteDialog.value = false
        deleteTarget.value = null
    }
}

onMounted(load)
</script>

<template>
    <v-container class="py-8" fluid>
        <v-row>
            <v-col cols="12">
                <div class="d-flex flex-wrap align-center justify-space-between ga-4 mb-6">
                    <div>
                        <v-card-subtitle>{{ $t('backup.listSubtitle') }}</v-card-subtitle>
                        <v-card-title class="text-h4 font-weight-bold">
                            {{ $t('backup.title') }}
                        </v-card-title>
                        <v-card-text class="text-medium-emphasis pa-0 mt-1">
                            {{ $t('backup.intro') }}
                        </v-card-text>
                    </div>
                    <v-btn
                        color="primary"
                        :loading="backupsStore.running"
                        prepend-icon="mdi-database-outline"
                        @click="run"
                    >
                        {{ $t('backup.backupNow') }}
                    </v-btn>
                </div>

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

                <v-alert class="mb-6" color="info" density="comfortable" variant="tonal">
                    {{ $t('backup.scheduleHint') }}
                    <template v-if="backupsStore.keepDays">
                        {{ $t('backup.retentionHint').replace('{days}', backupsStore.keepDays) }}
                    </template>
                </v-alert>

                <v-card elevation="2">
                    <v-data-table-server
                        :headers="headers"
                        :items="backupsStore.items"
                        :items-length="backupsStore.items.length"
                        :loading="backupsStore.loading"
                        density="comfortable"
                        item-value="name"
                    >
                        <template #item.name="{ item }">
                            <div class="d-flex align-center ga-2">
                                <span class="font-weight-medium">{{ item.name }}</span>
                                <v-chip
                                    v-if="item.name === latestName()"
                                    color="primary"
                                    size="x-small"
                                    variant="tonal"
                                >
                                    {{ $t('backup.latest') }}
                                </v-chip>
                            </div>
                        </template>

                        <template #item.created_on="{ item }">
                            <span class="text-body-2">{{ item.created_on }}</span>
                        </template>

                        <template #item.size="{ item }">
                            <span class="text-body-2">{{ formatSize(item.size) }}</span>
                        </template>

                        <template #item.actions="{ item }">
                            <div class="d-flex justify-end ga-1">
                                <v-btn
                                    :aria-label="$t('backup.download')"
                                    :href="backupDownloadUrl(item.name)"
                                    density="compact"
                                    icon="mdi-download-outline"
                                    size="small"
                                    variant="text"
                                />
                                <v-btn
                                    :aria-label="$t('common.delete')"
                                    color="error"
                                    density="compact"
                                    icon="mdi-delete-outline"
                                    size="small"
                                    variant="text"
                                    @click="askDelete(item)"
                                />
                            </div>
                        </template>

                        <template #no-data>
                            <div class="py-6 text-medium-emphasis">{{ $t('backup.noBackups') }}</div>
                        </template>
                    </v-data-table-server>
                </v-card>
            </v-col>
        </v-row>

        <v-dialog v-model="deleteDialog" max-width="480">
            <v-card>
                <v-card-title>{{ $t('backup.deleteTitle') }}</v-card-title>
                <v-card-text>
                    <v-alert class="mb-4" color="warning" density="comfortable" variant="tonal">
                        {{ $t('backup.deleteBody') }}
                    </v-alert>
                    <div v-if="deleteTarget" class="text-body-1">{{ deleteTarget.name }}</div>
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="deleteDialog = false">{{ $t('common.cancel') }}</v-btn>
                    <v-btn color="error" @click="confirmDelete">{{ $t('common.delete') }}</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </v-container>
</template>
