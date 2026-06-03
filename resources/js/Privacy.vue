<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import Navbar from './components/Navbar.vue';
import Footer from './components/Footer.vue';
import BottomNav from './components/BottomNav.vue';

const initial = window.__INITIAL_PRIVACY__;

const showScrollTop = ref(false);

function onScroll() {
    showScrollTop.value = window.scrollY > 400;
}

function scrollToTop() {
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

onMounted(() => {
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
});

onBeforeUnmount(() => {
    window.removeEventListener('scroll', onScroll);
});
</script>

<template>
    <div class="min-h-screen flex flex-col bg-white dark:bg-gray-950 text-gray-900 dark:text-gray-100 pb-20 md:pb-0">
        <Navbar />

        <!-- Hero band -->
        <section class="relative isolate overflow-hidden text-white">
            <div class="absolute inset-0 bg-[linear-gradient(135deg,#ff8c42_0%,#e5722b_55%,#7a2d0a_100%)] dark:bg-[linear-gradient(135deg,#3a1d0e_0%,#2a1408_55%,#160901_100%)]"></div>
            <div class="absolute inset-0 mix-blend-multiply dark:opacity-30 bg-[radial-gradient(circle_at_80%_20%,rgba(255,200,140,0.35)_0%,rgba(255,140,66,0)_55%)]"></div>

            <div class="relative max-w-4xl mx-auto w-full px-4 md:px-6 lg:px-8 py-10 md:py-14">
                <div class="text-xs font-semibold uppercase tracking-wider text-orange-100/90">
                    Legal
                </div>
                <h1 class="mt-3 text-3xl md:text-5xl font-bold tracking-tight leading-tight">
                    Privacy Policy
                </h1>
                <p class="mt-4 text-sm md:text-base text-orange-50/90">
                    Effective date: {{ initial.effectiveDate }}
                </p>
            </div>
        </section>

        <!-- Body -->
        <main class="flex-1">
            <article class="max-w-4xl mx-auto w-full px-4 md:px-6 lg:px-8 py-10 md:py-16 space-y-10 md:space-y-14 text-gray-700 dark:text-gray-300 leading-relaxed">
                <!-- Intro -->
                <section class="space-y-4">
                    <p>
                        This Privacy Policy explains how Leoncio Cesar Polestico, operating RentConnectPH ("RentConnectPH", "we", "us"), collects, uses, shares, and protects your personal information when you use our website, mobile applications, and related services (collectively, the "Platform").
                    </p>
                    <p>
                        By using the Platform, you agree to the practices described below. RentConnectPH complies with the Republic Act No. 10173, the Data Privacy Act of 2012 ("DPA"), and its Implementing Rules and Regulations.
                    </p>
                </section>

                <!-- 1. Who we are -->
                <section class="space-y-4">
                    <h2 class="text-xl md:text-2xl font-bold text-gray-900 dark:text-white">
                        1. Who we are
                    </h2>
                    <p>
                        The Platform is operated by Leoncio Cesar Polestico, a sole proprietor based in Cagayan de Oro, Philippines. For purposes of the DPA, Leoncio Cesar Polestico is the Personal Information Controller for any data processed through the Platform.
                    </p>
                </section>

                <!-- 2. Information we collect -->
                <section class="space-y-5">
                    <h2 class="text-xl md:text-2xl font-bold text-gray-900 dark:text-white">
                        2. Information we collect
                    </h2>
                    <p>
                        We collect information directly from you when you use the Platform, and automatically through your interactions with it.
                    </p>

                    <div class="space-y-2">
                        <h3 class="text-base md:text-lg font-semibold text-gray-900 dark:text-white">
                            From prospective renters
                        </h3>
                        <ul class="list-disc pl-6 space-y-1.5">
                            <li><span class="font-semibold text-gray-900 dark:text-white">Inquiry form:</span> your name and mobile number when you submit an inquiry on a listing.</li>
                            <li><span class="font-semibold text-gray-900 dark:text-white">Contact form:</span> your name, email address, and message content when you reach out through our contact page.</li>
                        </ul>
                    </div>

                    <div class="space-y-2">
                        <h3 class="text-base md:text-lg font-semibold text-gray-900 dark:text-white">
                            From property owners
                        </h3>
                        <ul class="list-disc pl-6 space-y-1.5">
                            <li>Property information you provide (title, type, monthly rent, bedrooms, bathrooms, floor area, address, barangay, description, photographs, and amenities).</li>
                            <li>Your contact details (name, mobile number, and any notes you provide) which are displayed to renters who submit an inquiry on your listing.</li>
                        </ul>
                    </div>

                    <div class="space-y-2">
                        <h3 class="text-base md:text-lg font-semibold text-gray-900 dark:text-white">
                            From platform users (admins, field officers)
                        </h3>
                        <ul class="list-disc pl-6 space-y-1.5">
                            <li>Account credentials (name, email, hashed password).</li>
                            <li>Activity records tied to your account (listings created, edited, verified, or visited; inquiries handled; photos uploaded).</li>
                        </ul>
                    </div>

                    <div class="space-y-2">
                        <h3 class="text-base md:text-lg font-semibold text-gray-900 dark:text-white">
                            From mobile applications
                        </h3>
                        <ul class="list-disc pl-6 space-y-1.5">
                            <li><span class="font-semibold text-gray-900 dark:text-white">Device location:</span> when you grant permission within our iOS or Android applications, we may access your device's geolocation to support features such as setting a property's location pin on the map or finding listings near you. Location is collected only while you actively use these features, and you can revoke permission at any time through your device settings.</li>
                            <li><span class="font-semibold text-gray-900 dark:text-white">Device and app information:</span> standard identifiers such as device model, operating system, and app version sent with requests for compatibility, diagnostics, and security.</li>
                        </ul>
                    </div>

                    <div class="space-y-2">
                        <h3 class="text-base md:text-lg font-semibold text-gray-900 dark:text-white">
                            Automatically collected
                        </h3>
                        <ul class="list-disc pl-6 space-y-1.5">
                            <li>Standard server and infrastructure logs (IP address, browser type, request URL, response status, timestamps) generated when you use the Platform.</li>
                            <li>Session cookies and a CSRF token required for the Platform to function securely.</li>
                            <li>A small preference cookie or local storage entry recording your light/dark theme choice.</li>
                            <li>
                                <span class="font-semibold text-gray-900 dark:text-white">Google Analytics (GA4)</span>, provided by Google LLC, which sets cookies to collect information such as your IP address (truncated where supported), device and browser type, pages viewed, time spent, and referring URLs. This helps us measure traffic, improve usability, and identify popular listings. You can opt out by installing the
                                <a href="https://tools.google.com/dlpage/gaoptout" target="_blank" rel="noopener" class="text-orange-600 dark:text-orange-400 hover:underline">Google Analytics opt-out browser add-on</a>.
                            </li>
                        </ul>
                    </div>
                </section>

                <!-- 3. How we use your information -->
                <section class="space-y-4">
                    <h2 class="text-xl md:text-2xl font-bold text-gray-900 dark:text-white">
                        3. How we use your information
                    </h2>
                    <p>
                        We process personal information for the following purposes:
                    </p>
                    <ul class="list-disc pl-6 space-y-1.5">
                        <li>To display verified rental listings to the public.</li>
                        <li>To enable renters to contact property owners directly by displaying owner contact details on the inquiry success page after an inquiry is submitted.</li>
                        <li>To manage user accounts, authenticate platform users, and maintain records of activity for accountability and audit.</li>
                        <li>To monitor performance, detect errors, and analyze traffic patterns across both the website and the mobile applications.</li>
                        <li>To respond to support, contact, and data-subject requests.</li>
                        <li>To improve the Platform, prevent fraud, and enforce our Terms of Service.</li>
                        <li>To comply with applicable laws and lawful requests by public authorities.</li>
                    </ul>
                </section>

                <!-- 4. How we share -->
                <section class="space-y-4">
                    <h2 class="text-xl md:text-2xl font-bold text-gray-900 dark:text-white">
                        4. How we share your information
                    </h2>
                    <p>
                        We share personal information only as described below. We do not sell your personal information.
                    </p>
                    <ul class="list-disc pl-6 space-y-1.5">
                        <li><span class="font-semibold text-gray-900 dark:text-white">Renters:</span> when you submit an inquiry on a listing, we display the property owner's contact details (name, mobile number, and any notes the owner provided) to you. We do not push inquiring renters' details to property owners — renters reach out to owners directly.</li>
                        <li><span class="font-semibold text-gray-900 dark:text-white">Service providers:</span> we rely on trusted third parties to host and operate the Platform, including Amazon Web Services (cloud hosting, storage, and infrastructure logging via AWS CloudWatch), Algolia (search indexing), Google LLC (Google Analytics for usage measurement), and email-delivery services. These providers process data on our instructions and under confidentiality obligations.</li>
                        <li><span class="font-semibold text-gray-900 dark:text-white">Legal and regulatory:</span> we may disclose information when required by law, court order, or government authority, or when necessary to protect our rights or the safety of any person.</li>
                    </ul>
                </section>

                <!-- 5. Where data is processed -->
                <section class="space-y-4">
                    <h2 class="text-xl md:text-2xl font-bold text-gray-900 dark:text-white">
                        5. Where your data is processed
                    </h2>
                    <p>
                        The Platform is hosted on Amazon Web Services. Some of our service providers (e.g. Algolia, Google Analytics, AWS regions) may process data outside the Philippines, including in the United States and Singapore. Where transfers occur, we take reasonable steps to ensure your information remains protected to a standard comparable to what the DPA requires.
                    </p>
                </section>

                <!-- 6. How long we keep -->
                <section class="space-y-4">
                    <h2 class="text-xl md:text-2xl font-bold text-gray-900 dark:text-white">
                        6. How long we keep your information
                    </h2>
                    <p>
                        We retain personal information only as long as necessary to fulfill the purposes set out in this Policy, including any legal, accounting, or reporting requirements.
                    </p>
                    <ul class="list-disc pl-6 space-y-1.5">
                        <li><span class="font-semibold text-gray-900 dark:text-white">Inquiry records:</span> retained for audit and analytics purposes for a reasonable period after submission.</li>
                        <li><span class="font-semibold text-gray-900 dark:text-white">Account data:</span> retained while your account is active and for a reasonable period after deactivation.</li>
                        <li><span class="font-semibold text-gray-900 dark:text-white">Listings:</span> deactivated and rejected listings are retained in our records for audit purposes.</li>
                        <li><span class="font-semibold text-gray-900 dark:text-white">Server and analytics logs:</span> retained for a reasonable period consistent with operational, security, and performance-monitoring needs.</li>
                    </ul>
                    <p>
                        You may request deletion of your personal information at any time (see Section 8).
                    </p>
                </section>

                <!-- 7. How we protect -->
                <section class="space-y-4">
                    <h2 class="text-xl md:text-2xl font-bold text-gray-900 dark:text-white">
                        7. How we protect your information
                    </h2>
                    <p>
                        We apply reasonable organizational, physical, and technical safeguards, including:
                    </p>
                    <ul class="list-disc pl-6 space-y-1.5">
                        <li>HTTPS encryption for all data in transit.</li>
                        <li>Industry-standard password hashing for credentials.</li>
                        <li>Role-based access controls limiting who within our team can view or modify data.</li>
                        <li>Server-side validation of all submissions.</li>
                        <li>Routine review of access permissions for staff and field officers.</li>
                    </ul>
                    <p>
                        No method of transmission or storage is completely secure, so we cannot guarantee absolute security.
                    </p>
                </section>

                <!-- 8. Your rights -->
                <section class="space-y-4">
                    <h2 class="text-xl md:text-2xl font-bold text-gray-900 dark:text-white">
                        8. Your rights under the Data Privacy Act
                    </h2>
                    <p>
                        As a data subject under the DPA, you have the right to:
                    </p>
                    <ul class="list-disc pl-6 space-y-1.5">
                        <li>Be informed of how your personal data is processed.</li>
                        <li>Access the personal data we hold about you.</li>
                        <li>Correct any inaccurate or incomplete data.</li>
                        <li>Object to the processing of your data.</li>
                        <li>Request the suspension, withdrawal, blocking, removal, or destruction of your data.</li>
                        <li>Be notified of any data breach affecting your personal information.</li>
                        <li>Lodge a complaint with the <a href="https://privacy.gov.ph" target="_blank" rel="noopener" class="text-orange-600 dark:text-orange-400 hover:underline">National Privacy Commission</a>.</li>
                    </ul>
                    <p>
                        To exercise any of these rights, please reach us through
                        <a href="/contact" class="text-orange-600 dark:text-orange-400 hover:underline">our contact form</a>.
                        We will respond to verified requests within a reasonable period.
                    </p>
                </section>

                <!-- 9. Children -->
                <section class="space-y-4">
                    <h2 class="text-xl md:text-2xl font-bold text-gray-900 dark:text-white">
                        9. Children's privacy
                    </h2>
                    <p>
                        The Platform is not intended for individuals under 18 years of age. We do not knowingly collect personal information from minors. If you believe a minor has provided us with personal information, please contact us so we can remove it.
                    </p>
                </section>

                <!-- 10. Changes -->
                <section class="space-y-4">
                    <h2 class="text-xl md:text-2xl font-bold text-gray-900 dark:text-white">
                        10. Changes to this Policy
                    </h2>
                    <p>
                        We may update this Privacy Policy from time to time to reflect changes to our practices, technology, legal requirements, or other factors. The "Effective date" at the top of this Policy indicates when it was last revised. Continued use of the Platform after changes are posted constitutes your acceptance of the updated Policy.
                    </p>
                </section>

                <!-- 11. Contact -->
                <section class="space-y-4">
                    <h2 class="text-xl md:text-2xl font-bold text-gray-900 dark:text-white">
                        11. Contact us
                    </h2>
                    <p>
                        For privacy concerns, requests, or to exercise your rights under the Data Privacy Act, please reach us through
                        <a href="/contact" class="text-orange-600 dark:text-orange-400 hover:underline">our contact form</a>.
                    </p>
                </section>
            </article>
        </main>

        <Footer />
        <BottomNav />

        <!-- Scroll-to-top FAB -->
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0 translate-y-2"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 translate-y-2"
        >
            <button
                v-if="showScrollTop"
                @click="scrollToTop"
                aria-label="Scroll to top"
                class="md:hidden fixed bottom-24 right-4 w-12 h-12 rounded-full bg-orange-500 hover:bg-orange-600 text-white shadow-lg flex items-center justify-center z-40"
            >
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 15l-6-6-6 6" />
                </svg>
            </button>
        </Transition>
    </div>
</template>
