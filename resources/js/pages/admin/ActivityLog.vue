<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useActivityLogStore } from '../../stores/activityLog'
import { useLocaleStore } from '../../stores/locale'

const localeStore = useLocaleStore()
const activityLogStore = useActivityLogStore()
const search = ref('')
const userFilter = ref(null)
const moduleFilter = ref(null)
const eventFilter = ref(null)
const dateFrom = ref('')
const dateTo = ref('')

const options = computed(() => ({
    page: activityLogStore.meta.current_page,
    per_page: activityLogStore.meta.per_page,
    search: search.value || undefined,
    user_id: userFilter.value || undefined,
    log_name: moduleFilter.value || undefined,
    event: eventFilter.value || undefined,
    date_from: dateFrom.value || undefined,
    date_to: dateTo.value || undefined,
}))

// Every dropdown is built from the trail itself, so an option is only ever
// offered when it actually returns rows.
const userItems = computed(() => [
    { title: localeStore.t('activityLog.allUsers'), value: null },
    ...activityLogStore.filterOptions.users.map((user) => ({ title: user.name, value: user.id })),
])

const moduleItems = computed(() => [
    { title: localeStore.t('activityLog.allModules'), value: null },
    ...activityLogStore.filterOptions.log_names.map((name) => ({
        title: moduleTitle(name),
        value: name,
    })),
])

const eventItems = computed(() => [
    { title: localeStore.t('activityLog.allEvents'), value: null },
    ...activityLogStore.filterOptions.events.map((name) => ({
        title: eventTitle(name),
        value: name,
    })),
])

const headers = computed(() => [
    { title: localeStore.t('activityLog.when'), key: 'created_at' },
    { title: localeStore.t('activityLog.module'), key: 'log_name' },
    { title: localeStore.t('activityLog.action'), key: 'description' },
    { title: localeStore.t('activityLog.user'), key: 'causer' },
    { title: localeStore.t('activityLog.record'), key: 'subject' },
])

function moduleTitle(logName) {
    const key = `activityLog.modules.${logName}`

    return localeStore.t(key) === key ? logName : localeStore.t(key)
}

function eventTitle(event) {
    const key = `activityLog.events.${event}`

    return localeStore.t(key) === key ? event : localeStore.t(key)
}

function modelTitle(subjectType) {
    if (!subjectType) {
        return '—'
    }

    const short = subjectType.split('\\').pop()

    return moduleTitle(characterToLowerCase(short))
}

function characterToLowerCase(value) {
    return value.charAt(0).toLowerCase() + value.slice(1)
}

function subjectId(activity) {
    if (!activity.subject_type) {
        return '—'
    }

    return `#${activity.subject_id}`
}

function formatDateTime(value) {
    if (!value) {
        return '—'
    }

    return new Date(value).toLocaleString(
        localeStore.locale === 'bn' ? 'bn-BD' : 'en-GB',
        { dateStyle: 'medium', timeStyle: 'short' },
    )
}

async function load() {
    activityLogStore.clearError()

    try {
        await activityLogStore.fetchList(options.value, true)
    } catch {
        // The store exposes the message, the table simply stays empty.
    }
}

function changePage(page) {
    if (page < 1) {
        return
    }

    options.value.page = page
    load()
}

function clearFilters() {
    search.value = ''
    userFilter.value = null
    moduleFilter.value = null
    eventFilter.value = null
    dateFrom.value = ''
    dateTo.value = ''
}

let debounce = null

watch([search, userFilter, moduleFilter, eventFilter, dateFrom, dateTo], () => {
    options.value.page = 1
    clearTimeout(debounce)
    debounce = setTimeout(load, 300)
})

onMounted(async () => {
    try {
        await activityLogStore.fetchFilters(true)
    } catch {
        // The filters fall back to "everything", which is still usable.
    }

    await load()
})
</script>

<template>
    <v-container class="py-8" fluid>
        <v-row>
            <v-col cols="12">
                <div class="d-flex flex-wrap align-center justify-space-between ga-4 mb-6">
                    <div>
                        <v-card-subtitle>{{ $t('activityLog.listSubtitle') }}</v-card-subtitle>
                        <v-card-title class="text-h4 font-weight-bold">
                            {{ $t('activityLog.title') }}
                        </v-card-title>
                        <v-card-text class="text-medium-emphasis pa-0 mt-1">
                            {{ $t('activityLog.intro') }}
                        </v-card-text>
                    </div>
                    <v-btn
                        prepend-icon="mdi-filter-variant"
                        variant="text"
                        @click="clearFilters"
                    >
                        {{ $t('common.clear') }}
                    </v-btn>
                </div>

                <v-card elevation="2">
                    <v-card-text>
                        <v-row align="center">
                            <v-col cols="12" md="4">
                                <v-text-field
                                    v-model="search"
                                    clearable
                                    density="comfortable"
                                    :label="$t('common.search')"
                                    prepend-inner-icon="mdi-magnify"
                                    variant="outlined"
                                />
                            </v-col>
                            <v-col cols="12" sm="6" md="3">
                                <v-select
                                    v-model="userFilter"
                                    :items="userItems"
                                    :label="$t('activityLog.user')"
                                    :loading="activityLogStore.loadingFilters"
                                    variant="outlined"
                                />
                            </v-col>
                            <v-col cols="12" sm="6" md="3">
                                <v-select
                                    v-model="moduleFilter"
                                    :items="moduleItems"
                                    :label="$t('activityLog.module')"
                                    variant="outlined"
                                />
                            </v-col>
                            <v-col cols="12" sm="6" md="2">
                                <v-select
                                    v-model="eventFilter"
                                    :items="eventItems"
                                    :label="$t('activityLog.action')"
                                    variant="outlined"
                                />
                            </v-col>
                            <v-col cols="6" md="4">
                                <v-text-field
                                    v-model="dateFrom"
                                    :label="$t('common.from')"
                                    type="date"
                                    variant="outlined"
                                />
                            </v-col>
                            <v-col cols="6" md="4">
                                <v-text-field
                                    v-model="dateTo"
                                    :label="$t('common.to')"
                                    type="date"
                                    variant="outlined"
                                />
                            </v-col>
                        </v-row>
                    </v-card-text>

                    <v-divider />

                    <v-alert
                        v-if="activityLogStore.error"
                        class="ma-4"
                        closable
                        color="error"
                        density="comfortable"
                        type="error"
                        variant="tonal"
                        @click:close="activityLogStore.clearError()"
                    >
                        {{ activityLogStore.error }}
                    </v-alert>

                    <v-data-table-server
                        :headers="headers"
                        :items="activityLogStore.items"
                        :items-length="activityLogStore.meta.total"
                        :loading="activityLogStore.loading"
                        :page="activityLogStore.meta.current_page"
                        :page-size="activityLogStore.meta.per_page"
                        density="comfortable"
                        item-value="id"
                    >
                        <template #item.created_at="{ item }">
                            <span class="text-caption text-medium-emphasis">
                                {{ formatDateTime(item.created_at) }}
                            </span>
                        </template>

                        <template #item.log_name="{ item }">
                            <v-chip size="small" variant="tonal">
                                {{ moduleTitle(item.log_name) }}
                            </v-chip>
                        </template>

                        <template #item.description="{ item }">
                            <div>
                                <span class="text-body-2">{{ item.description }}</span>
                                <v-chip
                                    v-if="item.event"
                                    class="ms-2"
                                    size="x-small"
                                    variant="text"
                                >
                                    {{ eventTitle(item.event) }}
                                </v-chip>
                            </div>
                        </template>

                        <template #item.causer="{ item }">
                            <span class="text-body-2">{{ item.causer?.name ?? '—' }}</span>
                        </template>

                        <template #item.subject="{ item }">
                            <span class="text-caption text-medium-emphasis">
                                {{ modelTitle(item.subject_type) }} {{ subjectId(item) }}
                            </span>
                        </template>

                        <template #no-data>
                            <div class="py-6 text-medium-emphasis">
                                {{ $t('activityLog.noEntries') }}
                            </div>
                        </template>
                    </v-data-table-server>

                    <v-divider />

                    <div class="d-flex align-center justify-end pa-3">
                        <v-pagination
                            :length="Math.ceil(activityLogStore.meta.total / activityLogStore.meta.per_page) || 1"
                            :model-value="activityLogStore.meta.current_page"
                            density="comfortable"
                            rounded
                            :total-visible="5"
                            @update:model-value="changePage"
                        />
                    </div>
                </v-card>
            </v-col>
        </v-row>
    </v-container>
</template>
