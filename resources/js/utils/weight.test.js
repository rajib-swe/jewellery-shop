import assert from 'node:assert/strict'
import test from 'node:test'
import {
    anaToGrams,
    gramsToAna,
    gramsToPoint,
    gramsToRoti,
    gramsToTraditional,
    gramsToVori,
    pointToGrams,
    rotiToGrams,
    traditionalToGrams,
    voriToGrams,
} from './weight.js'

const EPSILON = 0.0000000001

test('traditional unit ratios are exact', () => {
    assert.ok(Math.abs(voriToGrams(1) - 11.664) <= EPSILON)
    assert.ok(Math.abs(anaToGrams(1) - 0.729) <= EPSILON)
    assert.ok(Math.abs(rotiToGrams(1) - 0.1215) <= EPSILON)
    assert.ok(Math.abs(pointToGrams(1) - 0.01215) <= EPSILON)
    assert.ok(Math.abs(voriToGrams('2') - 23.328) <= EPSILON)
})

test('grams round trip through each unit without drift', () => {
    const grams = 10.137

    assert.ok(Math.abs(voriToGrams(gramsToVori(grams)) - grams) <= EPSILON)
    assert.ok(Math.abs(anaToGrams(gramsToAna(grams)) - grams) <= EPSILON)
    assert.ok(Math.abs(rotiToGrams(gramsToRoti(grams)) - grams) <= EPSILON)
    assert.ok(Math.abs(pointToGrams(gramsToPoint(grams)) - grams) <= EPSILON)
})

test('traditional units can be combined', () => {
    assert.ok(Math.abs(traditionalToGrams({ vori: 1, ana: 2, roti: 3, point: 4 }) - 13.5351) <= EPSILON)
})

test('grams can be expressed in each traditional unit', () => {
    const units = gramsToTraditional(11.664)

    assert.equal(units.vori, 1)
    assert.equal(units.ana, 16)
    assert.equal(units.roti, 96)
    assert.equal(units.point, 960)
})

test('negative weights are rejected', () => {
    assert.throws(() => gramsToVori(-0.001), RangeError)
})
