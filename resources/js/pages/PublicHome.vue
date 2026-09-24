<script setup>
import { useRouter } from 'vue-router'
import GoldPriceTable from '../components/GoldPriceTable.vue'

const router = useRouter()

const features = [
    {
        icon: 'mdi-chart-line-variant',
        title: 'Live market rates',
        text: 'Keep a clear view of common gold karats and their latest effective prices.',
    },
    {
        icon: 'mdi-storefront-outline',
        title: 'Built for your shop',
        text: 'Manage customers, stock, sales, and pawn accounts from one focused workspace.',
    },
    {
        icon: 'mdi-shield-check-outline',
        title: 'Reliable records',
        text: 'Keep rates, transactions, and important changes organized and auditable.',
    },
]

function scrollToPrices() {
    document.getElementById('live-prices')?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}
</script>

<template>
    <v-main class="public-home">
        <div class="public-shell">
            <header class="public-header">
                <router-link class="brand" :to="{ name: 'home' }">
                    <span class="brand-mark">
                        <v-icon icon="mdi-diamond-stone" size="22" />
                    </span>
                    <span>
                        <strong>Jewellery Shop</strong>
                        <small>Price & operations</small>
                    </span>
                </router-link>

                <nav class="public-nav" aria-label="Primary navigation">
                    <a href="#live-prices">Live prices</a>
                    <a href="#why-us">Why us</a>
                    <v-btn
                        :to="{ name: 'login' }"
                        color="primary"
                        size="small"
                        variant="outlined"
                    >
                        Admin sign in
                    </v-btn>
                </nav>
            </header>

            <section class="public-hero" aria-labelledby="public-title">
                <div class="hero-copy">
                    <p class="eyebrow">GOLD PRICE BANGLADESH</p>
                    <h1 id="public-title">আজকের স্বর্ণের দাম, এক নজরে</h1>
                    <p class="hero-description">
                        প্রতিটি ক্যারেটের বাজার দাম দেখুন এবং আপনার জুয়েলারি দোকের কাজগুলো একটি সহজ ওয়ার্কস্পেসে গুছিয়ে নিন।
                    </p>
                    <div class="hero-actions">
                        <v-btn color="primary" size="large" @click="scrollToPrices">
                            View live prices
                        </v-btn>
                        <v-btn
                            color="secondary"
                            size="large"
                            variant="text"
                            @click="router.push({ name: 'login' })"
                        >
                            Open dashboard
                        </v-btn>
                    </div>
                </div>
                <div class="hero-art" aria-hidden="true">
                    <div class="hero-orbit orbit-one" />
                    <div class="hero-orbit orbit-two" />
                    <div class="hero-gem">
                        <v-icon icon="mdi-diamond-stone" size="64" />
                    </div>
                    <span class="hero-spark spark-one">✦</span>
                    <span class="hero-spark spark-two">✦</span>
                </div>
            </section>

            <section id="live-prices" class="public-section prices-section" aria-labelledby="prices-title">
                <div class="section-heading">
                    <div>
                        <p class="eyebrow">LIVE MARKET PRICES</p>
                        <h2 id="prices-title">আজকের দাম</h2>
                    </div>
                    <span class="section-note">Updated from the live market feed</span>
                </div>
                <div class="price-card">
                    <GoldPriceTable mode="table" />
                </div>
            </section>

            <section id="why-us" class="public-section" aria-labelledby="why-title">
                <div class="section-heading">
                    <div>
                        <p class="eyebrow">A CLEARER WORKSPACE</p>
                        <h2 id="why-title">শপ চালানোর জন্য প্রস্তুত</h2>
                    </div>
                </div>
                <div class="feature-grid">
                    <v-card v-for="feature in features" :key="feature.title" class="feature-card" variant="outlined">
                        <v-card-text>
                            <v-avatar color="primary" variant="tonal" size="46">
                                <v-icon :icon="feature.icon" />
                            </v-avatar>
                            <h3>{{ feature.title }}</h3>
                            <p>{{ feature.text }}</p>
                        </v-card-text>
                    </v-card>
                </div>
            </section>

            <section class="public-cta" aria-labelledby="cta-title">
                <div>
                    <p class="eyebrow">READY WHEN YOU ARE</p>
                    <h2 id="cta-title">আপনার দোকের আজকের ডিজিটাল শুরু করুন</h2>
                </div>
                <v-btn color="primary" size="large" :to="{ name: 'login' }">
                    Sign in to your shop
                </v-btn>
            </section>

            <footer class="public-footer">
                <span>Jewellery Shop</span>
                <span>Simple tools for a modern jewellery business.</span>
            </footer>
        </div>
    </v-main>
</template>

<style scoped>
.public-home {
    min-height: 100vh;
    background:
        radial-gradient(circle at 85% 8%, rgb(196 154 84 / 18%), transparent 28%),
        linear-gradient(180deg, #fbfaf7 0%, #f5f0e7 100%);
    color: #2e2a24;
}

.public-shell {
    width: min(1180px, calc(100% - 40px));
    margin: 0 auto;
    padding: 24px 0 32px;
}

.public-header,
.public-nav,
.brand,
.hero-actions,
.section-heading,
.public-cta,
.public-footer {
    display: flex;
    align-items: center;
}

.public-header {
    justify-content: space-between;
    gap: 24px;
}

.brand {
    gap: 10px;
    color: inherit;
    text-decoration: none;
}

.brand-mark {
    display: grid;
    width: 42px;
    height: 42px;
    place-items: center;
    border-radius: 13px;
    background: #8a6a32;
    color: #fff;
}

.brand strong,
.brand small {
    display: block;
}

.brand strong {
    font-size: 0.98rem;
}

.brand small {
    color: #80786d;
    font-size: 0.72rem;
}

.public-nav {
    gap: 24px;
}

.public-nav a {
    color: #6e665b;
    font-size: 0.88rem;
    font-weight: 600;
    text-decoration: none;
}

.public-nav a:hover {
    color: #8a6a32;
}

.public-hero {
    display: grid;
    min-height: 470px;
    grid-template-columns: minmax(0, 1.1fr) minmax(280px, 0.9fr);
    align-items: center;
    gap: 60px;
    padding: 76px 0 60px;
}

.eyebrow {
    margin: 0 0 12px;
    color: #9a7436;
    font-size: 0.72rem;
    font-weight: 800;
    letter-spacing: 0.16em;
}

.hero-copy h1 {
    max-width: 650px;
    margin: 0;
    color: #29251f;
    font-size: clamp(2.5rem, 6vw, 5rem);
    font-weight: 800;
    letter-spacing: -0.045em;
    line-height: 1.05;
}

.hero-description {
    max-width: 560px;
    margin: 22px 0 0;
    color: #6e665b;
    font-size: 1.08rem;
    line-height: 1.8;
}

.hero-actions {
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 30px;
}

.hero-art {
    position: relative;
    display: grid;
    min-height: 330px;
    place-items: center;
}

.hero-orbit {
    position: absolute;
    border: 1px solid rgb(138 106 50 / 24%);
    border-radius: 50%;
    transform: rotate(-24deg);
}

.orbit-one {
    width: 290px;
    height: 190px;
}

.orbit-two {
    width: 380px;
    height: 240px;
    transform: rotate(28deg);
}

.hero-gem {
    z-index: 1;
    display: grid;
    width: 156px;
    height: 156px;
    place-items: center;
    border: 1px solid rgb(138 106 50 / 30%);
    border-radius: 50%;
    background: linear-gradient(145deg, #fffdf8, #ead7ae);
    color: #8a6a32;
    box-shadow: 0 24px 60px rgb(95 71 30 / 18%);
}

.hero-spark {
    position: absolute;
    color: #c49a54;
    font-size: 1.8rem;
}

.spark-one {
    top: 34px;
    right: 16%;
}

.spark-two {
    bottom: 26px;
    left: 13%;
    font-size: 1.1rem;
}

.public-section {
    padding: 54px 0;
}

.section-heading {
    justify-content: space-between;
    gap: 20px;
    margin-bottom: 22px;
}

.section-heading h2,
.public-cta h2 {
    margin: 0;
    color: #2e2a24;
    font-size: clamp(1.65rem, 3vw, 2.3rem);
    font-weight: 800;
    letter-spacing: -0.03em;
}

.section-note {
    color: #80786d;
    font-size: 0.82rem;
}

.price-card,
.feature-card {
    border: 1px solid rgb(138 106 50 / 16%);
    background: rgb(255 255 255 / 82%);
}

.price-card {
    overflow: hidden;
    border-radius: 20px;
    box-shadow: 0 16px 40px rgb(95 71 30 / 7%);
}

.feature-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 16px;
}

.feature-card {
    border-radius: 18px;
}

.feature-card h3 {
    margin: 20px 0 8px;
    font-size: 1.1rem;
}

.feature-card p {
    margin: 0;
    color: #766d61;
    line-height: 1.7;
}

.public-cta {
    justify-content: space-between;
    gap: 28px;
    margin: 28px 0 68px;
    padding: 34px 38px;
    border-radius: 22px;
    background: #2f3b4b;
    color: #fff;
}

.public-cta .eyebrow {
    color: #d9b978;
}

.public-cta h2 {
    max-width: 620px;
    color: #fff;
}

.public-footer {
    justify-content: space-between;
    gap: 20px;
    padding-top: 22px;
    border-top: 1px solid rgb(138 106 50 / 16%);
    color: #80786d;
    font-size: 0.8rem;
}

@media (max-width: 800px) {
    .public-header,
    .public-cta,
    .public-footer {
        align-items: flex-start;
        flex-direction: column;
    }

    .public-nav {
        width: 100%;
        flex-wrap: wrap;
        gap: 14px;
    }

    .public-hero {
        grid-template-columns: 1fr;
        gap: 18px;
        padding-top: 54px;
    }

    .hero-art {
        min-height: 220px;
    }

    .feature-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 520px) {
    .public-shell {
        width: min(100% - 28px, 1180px);
        padding-top: 16px;
    }

    .public-nav a {
        display: none;
    }

    .section-heading {
        align-items: flex-start;
        flex-direction: column;
    }

    .public-cta {
        padding: 26px 22px;
    }
}
</style>
