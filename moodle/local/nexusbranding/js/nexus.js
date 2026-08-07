(() => {
    'use strict';

    const ORIGIN = window.location.origin;
    const BASE = `${ORIGIN}/local/nexusbranding`;

    const HOME_URL = `${ORIGIN}/`;
    const LOGIN_URL = `${ORIGIN}/login/index.php`;

    const HERO_IMAGES = [
        `${BASE}/pix/hero-04.png`,
        `${BASE}/pix/hero-01.png`,
        `${BASE}/pix/hero-02.png`,
        `${BASE}/pix/hero-03.png`,
    ];

    function currentPath() {
        const value =
            (window.location.pathname || '/')
                .replace(/\/+$/, '');

        return value || '/';
    }

    function isHome() {
        const value = currentPath();

        return (
            value === '/' ||
            value === '/index.php'
        );
    }

    function isLogin() {
        const value = currentPath();

        return (
            value === '/login' ||
            value === '/login/index.php'
        );
    }


    /* ==================================================
       HOMEPAGE NAVIGATION
       ================================================== */

    function installNavTitle() {
        if (!isHome()) {
            return;
        }

        if (
            document.querySelector(
                '.nexus-nav-title'
            )
        ) {
            return;
        }

        const navbar =
            document.querySelector('.navbar');

        if (!navbar) {
            return;
        }

        const title =
            document.createElement('div');

        title.className =
            'nexus-nav-title';

        title.textContent =
            'Nexus Education Private School';

        navbar.appendChild(title);
    }


    function fixLoginLinks() {
        if (!isHome()) {
            return;
        }

        document
            .querySelectorAll('a')
            .forEach(link => {

                const text =
                    (link.textContent || '')
                        .trim()
                        .toLowerCase();

                if (
                    text === 'login' ||
                    text === 'log in'
                ) {
                    link.href = LOGIN_URL;
                }
            });
    }


    /* ==================================================
       NATIVE MOOVE CAROUSEL
       ================================================== */

    function preloadHeroes() {
        HERO_IMAGES.forEach(
            (src, index) => {

                const image =
                    new Image();

                image.src = src;

                if (index === 0) {
                    image.fetchPriority =
                        'high';
                }
            }
        );
    }


    function configureMooveCarousel() {
        if (!isHome()) {
            return false;
        }

        const carousel =
            document.querySelector(
                '#moove-carousel'
            ) ||
            document.querySelector(
                '#mooveslideshow .carousel'
            );

        if (!carousel) {
            return false;
        }

        const slides =
            Array.from(
                carousel.querySelectorAll(
                    '.carousel-item'
                )
            );

        if (!slides.length) {
            return false;
        }

        slides
            .slice(0, HERO_IMAGES.length)
            .forEach(
                (slide, index) => {

                    const src =
                        HERO_IMAGES[index];

                    /*
                     * Set direct static URL as the slide
                     * background. This bypasses the broken
                     * theme stored-file URL completely.
                     */
                    slide.style.setProperty(
                        'background-image',
                        `url("${src}")`,
                        'important'
                    );

                    slide.style.setProperty(
                        'background-size',
                        'cover',
                        'important'
                    );

                    slide.style.setProperty(
                        'background-position',
                        'center center',
                        'important'
                    );

                    slide.style.setProperty(
                        'background-repeat',
                        'no-repeat',
                        'important'
                    );

                    /*
                     * Moove versions may also include an img.
                     * If one exists, force the same URL there.
                     */
                    const images =
                        slide.querySelectorAll('img');

                    images.forEach(img => {
                        img.src = src;
                        img.removeAttribute('srcset');

                        img.style.setProperty(
                            'width',
                            '100%',
                            'important'
                        );

                        img.style.setProperty(
                            'height',
                            '100%',
                            'important'
                        );

                        img.style.setProperty(
                            'object-fit',
                            'cover',
                            'important'
                        );

                        img.style.setProperty(
                            'object-position',
                            'center',
                            'important'
                        );
                    });
                }
            );

        /*
         * Guarantee slide 1 = Hero 04.
         */
        slides.forEach(
            (slide, index) => {
                slide.classList.toggle(
                    'active',
                    index === 0
                );
            }
        );

        carousel.classList.add(
            'nexus-carousel-ready'
        );

        return true;
    }


    function waitForMooveCarousel() {
        if (
            configureMooveCarousel()
        ) {
            return;
        }

        /*
         * Moodle/Moove can finish Mustache/AMD work after
         * DOMContentLoaded. Observe until carousel exists.
         */
        const observer =
            new MutationObserver(() => {

                if (
                    configureMooveCarousel()
                ) {
                    observer.disconnect();
                }
            });

        observer.observe(
            document.documentElement,
            {
                childList: true,
                subtree: true,
            }
        );

        /*
         * Safety: do not leave observer running forever.
         */
        window.setTimeout(
            () => {
                observer.disconnect();
                configureMooveCarousel();
            },
            5000
        );
    }


    /* ==================================================
       LOGIN PAGE
       ================================================== */

    function installLoginBackButton() {
        if (!isLogin()) {
            return;
        }

        if (
            document.querySelector(
                '.nexus-login-back'
            )
        ) {
            return;
        }

        const link =
            document.createElement('a');

        link.className =
            'nexus-login-back';

        link.href =
            HOME_URL;

        link.innerHTML = `
            <span aria-hidden="true">‹</span>
            <span>Back to home</span>
        `;

        document.body.prepend(link);
    }


    function installLoginBrand() {
        if (!isLogin()) {
            return;
        }

        if (
            document.querySelector(
                '.nexus-login-brand'
            )
        ) {
            return;
        }

        const container =
            document.querySelector(
                '.login-container'
            );

        if (!container) {
            return;
        }

        const brand =
            document.createElement('div');

        brand.className =
            'nexus-login-brand';

        brand.innerHTML = `
            <img
                src="${BASE}/pix/logo.png"
                alt="Nexus Education Private School"
            >

            <span class="nexus-login-eyebrow">
                LEARNING PORTAL
            </span>

            <h1>
                Welcome back
            </h1>

            <p>
                Sign in to access your courses,
                assignments and learning dashboard.
            </p>
        `;

        container.prepend(brand);
    }


    /* ==================================================
       BOOT
       ================================================== */

    function boot() {

        if (isLogin()) {
            document.body.classList.add(
                'nexus-login-page'
            );

            installLoginBackButton();
            installLoginBrand();

            return;
        }

        if (isHome()) {
            document.body.classList.add(
                'nexus-landing-page'
            );

            preloadHeroes();
            installNavTitle();
            fixLoginLinks();
            waitForMooveCarousel();
        installNexusFooter();
        }
    }


    if (
        document.readyState === 'loading'
    ) {
        document.addEventListener(
            'DOMContentLoaded',
            boot
        );
    } else {
        boot();
    }

})();

/* ==========================================================
   NEXUS PREMIUM FOOTER
   ========================================================== */

function installNexusFooter() {
    if (!isHome()) {
        return;
    }

    const footerRoot =
        document.querySelector('#page-footer');

    if (!footerRoot) {
        return;
    }

    if (
        footerRoot.querySelector(
            '.nexus-footer-rich'
        )
    ) {
        return;
    }

    const footer =
        document.createElement('div');

    footer.className =
        'nexus-footer-rich';

    footer.innerHTML = `
        <div class="nexus-footer-rich__inner">

            <div class="nexus-footer-rich__top">

                <div class="nexus-footer-rich__brand">

                    <img
                        src="${BASE}/pix/logo.png"
                        alt="Nexus Education Private School"
                        class="nexus-footer-rich__logo"
                    >

                    <p>
                        A modern learning environment supporting
                        student achievement, confidence and future success.
                    </p>

                    <a
                        href="https://nexuseps.com"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="nexus-footer-rich__website"
                    >
                        Visit nexuseps.com
                    </a>

                </div>


                <div class="nexus-footer-rich__nav">

                    <div class="nexus-footer-rich__column">

                        <h3>Learning Portal</h3>

                        <a href="${HOME_URL}">
                            Home
                        </a>

                        <a href="${LOGIN_URL}">
                            Student Login
                        </a>

                    </div>


                    <div class="nexus-footer-rich__column">

                        <h3>Nexus EPS</h3>

                        <a
                            href="https://nexuseps.com"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            School Website
                        </a>

                        <a
                            href="https://nexuseps.com"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            Programs
                        </a>

                        <a
                            href="https://nexuseps.com"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            Contact Us
                        </a>

                    </div>

                </div>

            </div>


            <div class="nexus-footer-rich__divider"></div>


            <div class="nexus-footer-rich__bottom">

                <p>
                    © ${new Date().getFullYear()}
                    Nexus Education Private School.
                    All rights reserved.
                </p>

                <div class="nexus-footer-rich__social">

                    <a
                        href="https://nexuseps.com"
                        target="_blank"
                        rel="noopener noreferrer"
                        aria-label="Nexus EPS website"
                    >
                        <svg
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <circle cx="12" cy="12" r="9"></circle>
                            <path d="M3 12h18"></path>
                            <path d="M12 3a15 15 0 0 1 0 18"></path>
                            <path d="M12 3a15 15 0 0 0 0 18"></path>
                        </svg>
                    </a>

                </div>

            </div>

        </div>
    `;

    footerRoot.innerHTML = '';

    footerRoot.appendChild(
        footer
    );
}

