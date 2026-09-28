import { reactive, ref } from 'vue'
import { defineStore } from 'pinia'
import {
    addPawnPayment,
    createPawn,
    forfeitPawn,
    getPawn,
    listPawns,
    redeemPawn,
    renewPawn,
} from '../api/pawns'

function errorMessage(error, fallback) {
    return error.response?.data?.message ?? error.message ?? fallback
}

export const usePawnsStore = defineStore('pawns', () => {
    const pawns = ref([])
    const currentPawn = ref(null)
    const meta = reactive({
        current_page: 1,
        per_page: 15,
        total: 0,
        last_page: 1,
    })
    const loadingPawns = ref(false)
    const loadingCurrent = ref(false)
    const saving = ref(false)
    const error = ref(null)
    let listRequest = null
    let listRequestKey = null
    let currentRequest = null

    async function fetchPawns(params = {}, force = false) {
        const requestKey = JSON.stringify(params)

        if (!force && listRequest && listRequestKey === requestKey) {
            return listRequest
        }

        loadingPawns.value = true
        const request = listPawns(params)
        listRequest = request
        listRequestKey = requestKey

        try {
            const response = await request

            if (listRequest === request) {
                pawns.value = response.data
                Object.assign(meta, response.meta ?? {})
            }

            return response
        } catch (requestError) {
            if (listRequest === request) {
                error.value = errorMessage(requestError, 'Unable to load pawns.')
            }

            throw requestError
        } finally {
            if (listRequest === request) {
                listRequest = null
                listRequestKey = null
                loadingPawns.value = false
            }
        }
    }

    async function fetchPawn(pawnId, force = false) {
        if (!force && currentRequest) {
            return currentRequest
        }

        loadingCurrent.value = true
        const request = getPawn(pawnId)
        currentRequest = request

        try {
            const pawn = await request

            if (currentRequest === request) {
                currentPawn.value = pawn
            }

            return pawn
        } catch (requestError) {
            if (currentRequest === request) {
                error.value = errorMessage(requestError, 'Unable to load the pawn account.')
            }

            throw requestError
        } finally {
            if (currentRequest === request) {
                currentRequest = null
                loadingCurrent.value = false
            }
        }
    }

    async function savePawn(payload) {
        saving.value = true
        error.value = null

        try {
            const pawn = await createPawn(payload)

            currentPawn.value = pawn

            return pawn
        } catch (requestError) {
            error.value = errorMessage(requestError, 'Unable to record the pawn.')
            throw requestError
        } finally {
            saving.value = false
        }
    }

    async function recordPayment(pawnId, payload) {
        saving.value = true
        error.value = null

        try {
            const pawn = await addPawnPayment(pawnId, payload)

            currentPawn.value = pawn

            return pawn
        } catch (requestError) {
            error.value = errorMessage(requestError, 'Unable to record the payment.')
            throw requestError
        } finally {
            saving.value = false
        }
    }

    async function redeemCurrentPawn(pawnId, payload = {}) {
        saving.value = true
        error.value = null

        try {
            const pawn = await redeemPawn(pawnId, payload)

            currentPawn.value = pawn

            return pawn
        } catch (requestError) {
            error.value = errorMessage(requestError, 'Unable to redeem the pawn.')
            throw requestError
        } finally {
            saving.value = false
        }
    }

    async function renewCurrentPawn(pawnId, payload = {}) {
        saving.value = true
        error.value = null

        try {
            const pawn = await renewPawn(pawnId, payload)

            currentPawn.value = pawn

            return pawn
        } catch (requestError) {
            error.value = errorMessage(requestError, 'Unable to renew the pawn.')
            throw requestError
        } finally {
            saving.value = false
        }
    }

    async function forfeitCurrentPawn(pawnId, payload = {}) {
        saving.value = true
        error.value = null

        try {
            const pawn = await forfeitPawn(pawnId, payload)

            currentPawn.value = pawn

            return pawn
        } catch (requestError) {
            error.value = errorMessage(requestError, 'Unable to forfeit the pawn.')
            throw requestError
        } finally {
            saving.value = false
        }
    }

    function clearCurrent() {
        currentPawn.value = null
        error.value = null
    }

    function clearError() {
        error.value = null
    }

    return {
        pawns,
        currentPawn,
        meta,
        loadingPawns,
        loadingCurrent,
        saving,
        error,
        fetchPawns,
        fetchPawn,
        savePawn,
        recordPayment,
        redeemCurrentPawn,
        renewCurrentPawn,
        forfeitCurrentPawn,
        clearCurrent,
        clearError,
    }
})
