import { computed, ref, watch } from 'vue'
import { defineStore } from 'pinia'
import bn from '../lang/bn'
import en from '../lang/en'

export const SUPPORTED_LOCALES = [
    { code: 'bn', title: 'বাংলা', flag: '🇧🇩' },
    { code: 'en', title: 'English', flag: '🇬🇧' },
]

export const DEFAULT_LOCALE = 'bn'

const STORAGE_KEY = 'jewellery-shop.locale'

const messages = { bn, en }

function readStoredLocale() {
    try {
        return window.localStorage.getItem(STORAGE_KEY)
    } catch {
        return null
    }
}

function writeStoredLocale(locale) {
    try {
        window.localStorage.setItem(STORAGE_KEY, locale)
    } catch {
        // Storage is unavailable in private browsing; the in-memory locale still applies.
    }
}

function resolve(locale, key) {
    return key.split('.').reduce(
        (value, part) => (value === null || value === undefined ? undefined : value[part]),
        messages[locale],
    )
}

function interpolate(template, params) {
    if (typeof template !== 'string' || !params) {
        return template
    }

    return template.replace(/\{(\w+)\}/g, (match, name) => (
        Object.hasOwn(params, name) ? String(params[name]) : match
    ))
}

export const useLocaleStore = defineStore('locale', () => {
    const locale = ref(readStoredLocale() ?? DEFAULT_LOCALE)

    if (!messages[locale.value]) {
        locale.value = DEFAULT_LOCALE
    }

    const htmlLang = computed(() => (locale.value === 'bn' ? 'bn' : 'en'))

    function t(key, params) {
        const value = resolve(locale.value, key)

        if (typeof value === 'string') {
            return interpolate(value, params)
        }

        if (locale.value !== DEFAULT_LOCALE) {
            const fallback = resolve(DEFAULT_LOCALE, key)

            if (typeof fallback === 'string') {
                return interpolate(fallback, params)
            }
        }

        return key
    }

    function setLocale(nextLocale) {
        if (!messages[nextLocale]) {
            return
        }

        locale.value = nextLocale
        writeStoredLocale(nextLocale)
    }

    watch(locale, (value) => {
        writeStoredLocale(value)

        if (typeof document !== 'undefined') {
            document.documentElement.lang = htmlLang.value
        }
    }, { immediate: true })

    return {
        locale,
        htmlLang,
        supportedLocales: SUPPORTED_LOCALES,
        t,
        setLocale,
    }
})
