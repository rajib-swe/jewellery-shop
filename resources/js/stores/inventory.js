import { reactive, ref } from 'vue'
import { defineStore } from 'pinia'
import {
    createCategory,
    deleteCategory,
    listCategories,
    updateCategory,
} from '../api/categories'
import {
    adjustStock,
    createItem,
    deleteItem,
    getItem,
    getStockSummary,
    listItems,
    updateItem,
} from '../api/items'

function errorMessage(error, fallback) {
    return error.response?.data?.message ?? error.message ?? fallback
}

export const useInventoryStore = defineStore('inventory', () => {
    const categories = ref([])
    const items = ref([])
    const currentItem = ref(null)
    const summary = ref({
        total_items: 0,
        total_net_weight: '0.000',
        by_karat: [],
    })
    const meta = reactive({
        current_page: 1,
        per_page: 15,
        total: 0,
        last_page: 1,
    })
    const loadingCategories = ref(false)
    const loadingItems = ref(false)
    const loadingCurrent = ref(false)
    const loadingSummary = ref(false)
    const saving = ref(false)
    const error = ref(null)
    let itemsRequest = null
    let itemsRequestKey = null

    async function fetchCategories(force = false) {
        if (loadingCategories.value && !force) {
            return categories.value
        }

        loadingCategories.value = true

        try {
            categories.value = await listCategories()

            return categories.value
        } catch (requestError) {
            error.value = errorMessage(requestError, 'Unable to load categories.')
            throw requestError
        } finally {
            loadingCategories.value = false
        }
    }

    async function fetchItems(params = {}, force = false) {
        const requestKey = JSON.stringify(params)

        if (!force && itemsRequest && itemsRequestKey === requestKey) {
            return itemsRequest
        }

        loadingItems.value = true
        const request = listItems(params)
        itemsRequest = request
        itemsRequestKey = requestKey

        try {
            const response = await request

            if (itemsRequest === request) {
                items.value = response.data
                Object.assign(meta, response.meta ?? {})
            }

            return response
        } catch (requestError) {
            if (itemsRequest === request) {
                error.value = errorMessage(requestError, 'Unable to load inventory items.')
            }

            throw requestError
        } finally {
            if (itemsRequest === request) {
                itemsRequest = null
                itemsRequestKey = null
                loadingItems.value = false
            }
        }
    }

    async function fetchItem(itemId, force = false) {
        if (!force && loadingCurrent.value) {
            return currentItem.value
        }

        loadingCurrent.value = true

        try {
            currentItem.value = await getItem(itemId)

            return currentItem.value
        } catch (requestError) {
            error.value = errorMessage(requestError, 'Unable to load the inventory item.')
            throw requestError
        } finally {
            loadingCurrent.value = false
        }
    }

    async function fetchSummary(force = false) {
        if (!force && loadingSummary.value) {
            return summary.value
        }

        loadingSummary.value = true

        try {
            summary.value = await getStockSummary()

            return summary.value
        } catch (requestError) {
            error.value = errorMessage(requestError, 'Unable to load the stock summary.')
            throw requestError
        } finally {
            loadingSummary.value = false
        }
    }

    async function saveCategory(payload, categoryId = null) {
        saving.value = true
        error.value = null

        try {
            const category = categoryId === null
                ? await createCategory(payload)
                : await updateCategory(categoryId, payload)

            await fetchCategories(true)

            return category
        } catch (requestError) {
            error.value = errorMessage(requestError, 'Unable to save the category.')
            throw requestError
        } finally {
            saving.value = false
        }
    }

    async function removeCategory(categoryId) {
        saving.value = true
        error.value = null

        try {
            await deleteCategory(categoryId)
            await fetchCategories(true)
        } catch (requestError) {
            error.value = errorMessage(requestError, 'Unable to delete the category.')
            throw requestError
        } finally {
            saving.value = false
        }
    }

    async function saveItem(payload, itemId = null) {
        saving.value = true
        error.value = null

        try {
            const item = itemId === null
                ? await createItem(payload)
                : await updateItem(itemId, payload)

            if (itemId !== null) {
                currentItem.value = item
            }

            return item
        } catch (requestError) {
            error.value = errorMessage(requestError, 'Unable to save the inventory item.')
            throw requestError
        } finally {
            saving.value = false
        }
    }

    async function removeItem(itemId) {
        saving.value = true
        error.value = null

        try {
            await deleteItem(itemId)
        } catch (requestError) {
            error.value = errorMessage(requestError, 'Unable to delete the inventory item.')
            throw requestError
        } finally {
            saving.value = false
        }
    }

    async function recordAdjustment(itemId, payload) {
        saving.value = true
        error.value = null

        try {
            const item = await adjustStock(itemId, payload)
            currentItem.value = item

            return item
        } catch (requestError) {
            error.value = errorMessage(requestError, 'Unable to record the stock adjustment.')
            throw requestError
        } finally {
            saving.value = false
        }
    }

    function clearCurrent() {
        currentItem.value = null
    }

    function clearError() {
        error.value = null
    }

    return {
        categories,
        items,
        currentItem,
        summary,
        meta,
        loadingCategories,
        loadingItems,
        loadingCurrent,
        loadingSummary,
        saving,
        error,
        fetchCategories,
        fetchItems,
        fetchItem,
        fetchSummary,
        saveCategory,
        removeCategory,
        saveItem,
        removeItem,
        recordAdjustment,
        clearCurrent,
        clearError,
    }
})
