<script setup>
import { onMounted, ref } from 'vue'

const price = ref(0)

const bn = (n) => Math.round(n).toLocaleString('bn-BD')

const products = [
    { icon: '🥇', name: 'সোনার বার (১ ভরি)', tag: '২২ ক্যারেট · হলমার্ক', price: bn(215000) },
    { icon: '🪙', name: 'স্বর্ণমুদ্রা', tag: '২৪ ক্যারেট · ১০ গ্রাম', price: bn(158000) },
    { icon: '📿', name: 'বউ-সাজ নেকলেস সেট', tag: '২২ ক্যারেট · হাতের কাজ', price: bn(645000) },
    { icon: '✨', name: 'ঝুমকা ও টিকলি', tag: 'ঐতিহ্যবাহী নকশা', price: bn(98000) },
]

const features = [
    ['খাঁটিত্বের নিশ্চয়তা', 'প্রতিটি গয়নায় হলমার্ক ও ক্যারেট সনদ।'],
    ['স্বচ্ছ দাম', 'ভরি-আনা-রতি হিসাবে পরিষ্কার দর, লুকানো খরচ নেই।'],
    ['নিরাপদ ডেলিভারি', 'বিমাসহ সারা বাংলাদেশে নিরাপদ ডেলিভারি।'],
]

onMounted(() => {
    const t0 = performance.now()
    const tick = (t) => {
        const p = Math.min((t - t0) / 2200, 1)
        price.value = 215000 * (1 - Math.pow(1 - p, 3))
        if (p < 1) {
            requestAnimationFrame(tick)
        }
    }
    requestAnimationFrame(tick)

    const io = new IntersectionObserver(
        (entries) => entries.forEach((e) => e.isIntersecting && e.target.classList.add('in')),
        { threshold: 0.15 },
    )
    document.querySelectorAll('.reveal').forEach((el) => io.observe(el))
})
</script>

<template>
    <div class="relative overflow-hidden bg-ink font-bn text-white">
        <header
            class="fixed inset-x-0 top-0 z-30 flex items-center justify-between px-6 pb-4 md:px-14"
            style="padding-top:calc(1.25rem + env(safe-area-inset-top,0px));background:linear-gradient(#0b0a06cc,transparent)"
        >
            <a href="#" class="flex items-center gap-2 font-display text-2xl font-extrabold gold-text">
                <span class="inline-block h-4 w-6 rounded-sm bg-bd"><span class="mx-auto mt-[3px] block h-2.5 w-2.5 rounded-full bg-bdr"></span></span>স্বর্ণকুঠি
            </a>
            <nav class="glass hidden gap-8 rounded-full px-8 py-2 text-sm text-white/75 md:flex">
                <a href="#shop" class="hover:text-g3">সংগ্রহ</a><a href="#why" class="hover:text-g3">কেন আমরা</a><a href="#contact" class="hover:text-g3">যোগাযোগ</a>
            </nav>
            <a href="#shop" class="rounded-full bg-g4 px-5 py-2 text-sm font-semibold text-ink transition hover:scale-105">সোনা কিনুন</a>
        </header>

        <section class="alpona relative flex min-h-screen items-center" style="background-color:#0b0a06">
            <div class="absolute inset-0" style="background:radial-gradient(ellipse at 72% 42%,rgba(90,62,10,.75) 0%,rgba(11,10,6,.92) 62%)"></div>
            <div class="pointer-events-none absolute inset-0 hidden place-items-center md:grid" aria-hidden="true">
                <div class="relative translate-x-[38vw]">
                    <div class="hero-coin relative"></div>
                    <div class="hero-bar absolute -left-28 top-6" style="animation:bar-drift 5s ease-in-out infinite"></div>
                    <div class="hero-bar absolute -right-24 bottom-2" style="animation:bar-drift-2 6s ease-in-out infinite"></div>
                </div>
            </div>
            <div class="relative z-10 max-w-2xl px-6 pt-16 md:px-14">
                <p class="reveal in mb-4 text-sm tracking-[.25em] text-g4">খাঁটি · হলমার্ক যাচাইকৃত · চিরন্তন</p>
                <h1 class="reveal in font-display text-5xl font-extrabold leading-[1.25] md:text-7xl">বাঙালির <span class="gold-text">ঐতিহ্য</span>,<br>খাঁটি সোনায়</h1>
                <p class="reveal in mt-6 max-w-md text-lg text-white/65">বিয়ে, উৎসব বা বিনিয়োগ — সোনার বার, স্বর্ণমুদ্রা ও বউ-সাজের গয়না, ঢাকা থেকে সারা বাংলাদেশে।</p>
                <div class="reveal in mt-10 flex flex-wrap items-center gap-5">
                    <a href="#shop" class="rounded-full bg-gradient-to-r from-g5 to-g3 px-8 py-3 font-semibold text-ink transition hover:scale-105" style="box-shadow:0 0 40px -5px #d4a73c">সংগ্রহ দেখুন</a>
                    <div class="glass rounded-2xl px-5 py-3">
                        <div class="text-xs text-white/55">২২ ক্যারেট · প্রতি ভরি · ডেমো দর</div>
                        <div class="font-display text-2xl font-bold text-g3">৳ {{ bn(price) }}</div>
                    </div>
                </div>
            </div>
            <div class="kantha absolute inset-x-0 bottom-0"></div>
        </section>

        <section id="shop" class="alpona px-6 py-28 md:px-14">
            <h2 class="reveal text-center font-display text-4xl font-bold gold-text md:text-5xl">আমাদের সংগ্রহ</h2>
            <p class="reveal mt-3 text-center text-white/55">ভরি, আনা, রতি — আপনার হিসাবেই</p>
            <div class="mx-auto mt-14 grid max-w-6xl gap-8 md:grid-cols-2 lg:grid-cols-4">
                <article
                    v-for="(p, i) in products"
                    :key="p.name"
                    class="reveal glass group rounded-3xl p-6 transition duration-500 hover:-translate-y-3"
                    :style="{ transitionDelay: i * 110 + 'ms' }"
                >
                    <div class="mb-6 grid h-40 place-items-center rounded-2xl text-5xl transition duration-700 group-hover:rotate-3 group-hover:scale-105" style="background:conic-gradient(from 200deg,#8a6a1f,#f3d98b,#d4a73c,#fff1b8,#8a6a1f)">{{ p.icon }}</div>
                    <p class="text-xs text-g4">{{ p.tag }}</p>
                    <h3 class="mt-1 font-display text-xl font-bold">{{ p.name }}</h3>
                    <div class="mt-4 flex items-center justify-between">
                        <span class="text-lg font-semibold text-g3">৳ {{ p.price }}</span>
                        <button class="rounded-full border border-g4/50 px-4 py-1.5 text-sm transition hover:bg-g4 hover:text-ink">কিনুন</button>
                    </div>
                </article>
            </div>
        </section>

        <section id="why" class="px-6 pb-28 md:px-14">
            <div class="mx-auto grid max-w-6xl gap-10 md:grid-cols-3">
                <div v-for="f in features" :key="f[0]" class="reveal border-t-2 border-g4/40 pt-6">
                    <h4 class="font-display text-2xl font-bold text-g3">{{ f[0] }}</h4><p class="mt-2 text-white/60">{{ f[1] }}</p>
                </div>
            </div>
        </section>

        <section id="contact" class="reveal alpona px-6 pb-20 pt-10 text-center">
            <h2 class="font-display text-4xl font-extrabold leading-snug md:text-6xl">শুভ কাজে <span class="gold-text">সোনার ছোঁয়া</span></h2>
            <div class="mt-10 flex flex-wrap justify-center gap-4">
                <a href="#" class="rounded-full bg-bd px-8 py-3 font-semibold transition hover:scale-105">WhatsApp এ কথা বলুন</a>
                <a href="#" class="rounded-full bg-g4 px-8 py-3 font-semibold text-ink transition hover:scale-105">দোকানে আসুন · ঢাকা</a>
            </div>
            <p class="mt-14 text-xs text-white/35">© ২০২৬ স্বর্ণকুঠি। সর্বস্বত্ব সংরক্ষিত। (ডেমো দর ও পণ্য)</p>
        </section>
        <div class="kantha"></div>
    </div>
</template>
