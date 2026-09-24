export const VORI_IN_GRAMS = 11.664
export const ANA_PER_VORI = 16
export const ROTI_PER_ANA = 6
export const POINT_PER_ROTI = 10
export const ANA_IN_GRAMS = VORI_IN_GRAMS / ANA_PER_VORI
export const ROTI_IN_GRAMS = ANA_IN_GRAMS / ROTI_PER_ANA
export const POINT_IN_GRAMS = ROTI_IN_GRAMS / POINT_PER_ROTI

const PRECISION = 12

function normalize(value) {
    return Number(value.toFixed(PRECISION))
}

function parseWeight(value, unit) {
    const numericValue = Number(value)

    if (!Number.isFinite(numericValue) || numericValue < 0) {
        throw new RangeError(`Weight in ${unit} cannot be negative or non-finite.`)
    }

    return numericValue
}

function fromGrams(grams, gramsPerUnit) {
    const weight = parseWeight(grams, 'grams')

    return normalize(weight / gramsPerUnit)
}

function toGrams(value, gramsPerUnit, unit) {
    const weight = parseWeight(value, unit)

    return normalize(weight * gramsPerUnit)
}

export function gramsToVori(grams) {
    return fromGrams(grams, VORI_IN_GRAMS)
}

export function voriToGrams(vori) {
    return toGrams(vori, VORI_IN_GRAMS, 'vori')
}

export function gramsToAna(grams) {
    return fromGrams(grams, ANA_IN_GRAMS)
}

export function anaToGrams(ana) {
    return toGrams(ana, ANA_IN_GRAMS, 'ana')
}

export function gramsToRoti(grams) {
    return fromGrams(grams, ROTI_IN_GRAMS)
}

export function rotiToGrams(roti) {
    return toGrams(roti, ROTI_IN_GRAMS, 'roti')
}

export function gramsToPoint(grams) {
    return fromGrams(grams, POINT_IN_GRAMS)
}

export function pointToGrams(point) {
    return toGrams(point, POINT_IN_GRAMS, 'point')
}

export function gramsToTraditional(grams) {
    return {
        vori: gramsToVori(grams),
        ana: gramsToAna(grams),
        roti: gramsToRoti(grams),
        point: gramsToPoint(grams),
    }
}

export function traditionalToGrams({ vori = 0, ana = 0, roti = 0, point = 0 } = {}) {
    return normalize(
        voriToGrams(vori)
        + anaToGrams(ana)
        + rotiToGrams(roti)
        + pointToGrams(point),
    )
}
