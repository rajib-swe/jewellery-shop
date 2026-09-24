import client from './client'

export async function listCategories() {
    const { data } = await client.get('/categories')

    return data.data
}

export async function createCategory(payload) {
    const { data } = await client.post('/categories', payload)

    return data.data
}

export async function updateCategory(categoryId, payload) {
    const { data } = await client.put(`/categories/${categoryId}`, payload)

    return data.data
}

export async function deleteCategory(categoryId) {
    await client.delete(`/categories/${categoryId}`)
}
