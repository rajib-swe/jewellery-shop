<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useAuthStore } from '../../stores/auth'
import { useInventoryStore } from '../../stores/inventory'

const authStore = useAuthStore()
const inventoryStore = useInventoryStore()
const canManage = computed(() => authStore.can('manage inventory'))
const dialog = ref(false)
const deleteDialog = ref(false)
const editingId = ref(null)
const deleteTarget = ref(null)
const errorMessage = ref('')
const form = reactive({
    name: '',
    description: '',
})

function openCreate() {
    editingId.value = null
    form.name = ''
    form.description = ''
    errorMessage.value = ''
    dialog.value = true
}

function openEdit(category) {
    editingId.value = category.id
    form.name = category.name
    form.description = category.description ?? ''
    errorMessage.value = ''
    dialog.value = true
}

function openDelete(category) {
    deleteTarget.value = category
    deleteDialog.value = true
}

async function load() {
    errorMessage.value = ''
    inventoryStore.clearError()

    try {
        await inventoryStore.fetchCategories()
    } catch {
        errorMessage.value = inventoryStore.error ?? 'Unable to load categories.'
    }
}

async function save() {
    errorMessage.value = ''

    if (!form.name.trim()) {
        errorMessage.value = 'Category name is required.'
        return
    }

    try {
        await inventoryStore.saveCategory({
            name: form.name.trim(),
            description: form.description.trim() || null,
        }, editingId.value)
        dialog.value = false
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? inventoryStore.error ?? 'Unable to save the category.'
    }
}

async function confirmDelete() {
    if (!deleteTarget.value) {
        return
    }

    errorMessage.value = ''

    try {
        await inventoryStore.removeCategory(deleteTarget.value.id)
        deleteDialog.value = false
        deleteTarget.value = null
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? inventoryStore.error ?? 'Unable to delete the category.'
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
                        <v-card-subtitle>Item catalog</v-card-subtitle>
                        <v-card-title class="text-h4 font-weight-bold">Categories</v-card-title>
                        <v-card-text class="text-medium-emphasis pa-0 mt-1">
                            Organize rings, necklaces, bangles, and other jewellery.
                        </v-card-text>
                    </div>
                    <v-btn
                        v-if="canManage"
                        color="primary"
                        prepend-icon="mdi-shape-outline"
                        size="large"
                        @click="openCreate"
                    >
                        Add category
                    </v-btn>
                </div>

                <v-alert
                    v-if="errorMessage"
                    class="mb-6"
                    color="error"
                    density="comfortable"
                    type="error"
                    variant="tonal"
                    closable
                    @click:close="errorMessage = ''"
                >
                    {{ errorMessage }}
                </v-alert>
            </v-col>

            <v-col cols="12">
                <v-progress-linear v-if="inventoryStore.loadingCategories" indeterminate />

                <v-row v-if="inventoryStore.categories.length" dense>
                    <v-col
                        v-for="category in inventoryStore.categories"
                        :key="category.id"
                        cols="12"
                        sm="6"
                        lg="4"
                    >
                        <v-card class="h-100" variant="outlined">
                            <v-card-text>
                                <div class="d-flex align-start justify-space-between ga-3">
                                    <div class="category-copy">
                                        <v-card-title class="text-h6 pa-0">{{ category.name }}</v-card-title>
                                        <v-card-subtitle class="pa-0 mt-2">
                                            {{ category.description || 'No description' }}
                                        </v-card-subtitle>
                                    </div>
                                    <v-icon color="primary" icon="mdi-shape-outline" />
                                </div>
                            </v-card-text>
                            <v-card-actions v-if="canManage">
                                <v-btn
                                    aria-label="Edit category"
                                    icon="mdi-pencil-outline"
                                    size="small"
                                    variant="text"
                                    @click="openEdit(category)"
                                />
                                <v-btn
                                    aria-label="Delete category"
                                    color="error"
                                    icon="mdi-delete-outline"
                                    size="small"
                                    variant="text"
                                    @click="openDelete(category)"
                                />
                            </v-card-actions>
                        </v-card>
                    </v-col>
                </v-row>

                <v-empty-state
                    v-else-if="!inventoryStore.loadingCategories"
                    icon="mdi-shape-outline"
                    title="No categories yet"
                    text="Add a category before creating inventory items."
                />
            </v-col>
        </v-row>

        <v-dialog v-model="dialog" max-width="520">
            <v-card>
                <v-card-title>{{ editingId ? 'Edit category' : 'Add category' }}</v-card-title>
                <v-card-text>
                    <v-text-field
                        v-model="form.name"
                        autofocus
                        label="Name"
                        prepend-inner-icon="mdi-shape-outline"
                        required
                    />
                    <v-textarea
                        v-model="form.description"
                        auto-grow
                        label="Description"
                        prepend-inner-icon="mdi-text-box-outline"
                        rows="3"
                    />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="dialog = false">Cancel</v-btn>
                    <v-btn color="primary" :loading="inventoryStore.saving" @click="save">Save category</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="deleteDialog" max-width="420">
            <v-card>
                <v-card-title>Delete this category?</v-card-title>
                <v-card-text>
                    {{ deleteTarget?.name }} can only be deleted when it has no inventory items.
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="deleteDialog = false">Cancel</v-btn>
                    <v-btn color="error" :loading="inventoryStore.saving" @click="confirmDelete">Delete</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </v-container>
</template>

<style scoped>
.category-copy {
    min-width: 0;
}
</style>
