(() => {
    'use strict';

    const BASE =
        `${window.location.origin}/local/nexusbranding`;

    const HERO_INTERVAL = 2000;

    const HERO_IMAGES = [
        `${BASE}/pix/hero-01.png`,
        `${BASE}/pix/hero-02.png`,
        `${BASE}/pix/hero-03.png`,
        `${BASE}/pix/hero-04.png`,
    ];


    function isFrontPage() {
        const path =
            window.location.pathname.replace(
                /\/+$/,
                ''
            );

        return (
            path === '' ||
            path === '/' ||
            path.endsWith('/index.php')
        );
    }


    function installNavbarLogo() {
        if (
            document.querySelector(
                '.nexus-navbar-brand'
            )
        ) {
            return;
        }

        const oldBrand =
            document.querySelector(
                '.navbar-brand'
            );

        if (!oldBrand) {
            return;
        }

        const link =
            document.createElement('a');

        link.className =
            'nexus-navbar-brand';

        link.href =
            `${window.location.origin}/`;

        link.setAttribute(
            'aria-label',
            'Nexus Education Private School'
        );

        const image =
            document.createElement('img');

        image.src =
            `${BASE}/pix/logo.png`;

        image.alt =
            'Nexus Education Private School';

        link.appendChild(image);

        oldBrand.replaceWith(link);
    }


    function createHero() {
        if (!isFrontPage()) {
            return;
        }

        if (
            document.querySelector(
                '.nexus-home-hero'
            )
        ) {
            return;
        }

        const main =
            document.querySelector(
                '#page-content'
            ) ||
            document.querySelector(
                '#page'
            );

        if (!main) {
            return;
        }

        const hero =
            document.createElement('section');

        hero.className =
            'nexus-home-hero';

        hero.setAttribute(
            'aria-label',
            'Nexus Education Private School'
        );


        HERO_IMAGES.forEach(
            (source, index) => {

                const image =
                    document.createElement('img');

                image.className =
                    'nexus-home-hero__image';

                if (index === 0) {
                    image.classList.add(
                        'is-active'
                    );
                }

                image.src = source;
                image.alt = '';

                image.decoding =
                    'async';

                hero.appendChild(image);
            }
        );


        const dots =
            document.createElement('div');

        dots.className =
            'nexus-home-hero__dots';


        HERO_IMAGES.forEach(
            (_, index) => {

                const dot =
                    document.createElement(
                        'button'
                    );

                dot.type =
                    'button';

                dot.className =
                    'nexus-home-hero__dot';

                if (index === 0) {
                    dot.classList.add(
                        'is-active'
                    );
                }

                dot.dataset.index =
                    String(index);

                dot.setAttribute(
                    'aria-label',
                    `Show image ${index + 1}`
                );

                dots.appendChild(dot);
            }
        );


        hero.appendChild(dots);

        main.prepend(hero);

        activateHero(hero);
    }


    function activateHero(hero) {
        const images = [
            ...hero.querySelectorAll(
                '.nexus-home-hero__image'
            )
        ];

        const dots = [
            ...hero.querySelectorAll(
                '.nexus-home-hero__dot'
            )
        ];

        if (images.length < 2) {
            return;
        }

        let current = 0;
        let timer = null;

        const reducedMotion =
            window.matchMedia(
                '(prefers-reduced-motion: reduce)'
            ).matches;


        function show(index) {
            current =
                (
                    index +
                    images.length
                ) % images.length;

            images.forEach(
                (image, i) => {
                    image.classList.toggle(
                        'is-active',
                        i === current
                    );
                }
            );

            dots.forEach(
                (dot, i) => {
                    dot.classList.toggle(
                        'is-active',
                        i === current
                    );
                }
            );
        }


        function start() {
            if (
                reducedMotion ||
                document.hidden
            ) {
                return;
            }

            clearInterval(timer);

            timer =
                setInterval(
                    () => {
                        show(
                            current + 1
                        );
                    },
                    HERO_INTERVAL
                );
        }


        function stop() {
            clearInterval(timer);
            timer = null;
        }


        dots.forEach(dot => {
            dot.addEventListener(
                'click',
                () => {
                    show(
                        Number(
                            dot.dataset.index
                        )
                    );

                    start();
                }
            );
        });


        document.addEventListener(
            'visibilitychange',
            () => {
                if (document.hidden) {
                    stop();
                } else {
                    start();
                }
            }
        );


        start();
    }


    function installFooter() {
        if (
            document.querySelector(
                '.nexus-footer'
            )
        ) {
            return;
        }

        const footerRoot =
            document.querySelector(
                '#page-footer'
            );

        if (!footerRoot) {
            return;
        }

        const footer =
            document.createElement(
                'div'
            );

        footer.className =
            'nexus-footer';

        footer.innerHTML = `
            <div class="nexus-footer__inner">

                <img
                    class="nexus-footer__logo"
                    src="${BASE}/pix/logo.png"
                    alt="Nexus Education Private School"
                >

                <p class="nexus-footer__description">
                    Nexus Education Private School
                    provides a modern digital learning
                    environment designed to support
                    student achievement and future success.
                </p>

                <div class="nexus-footer__bottom">
                    © ${new Date().getFullYear()}
                    Nexus Education Private School.
                    All rights reserved.
                </div>

            </div>
        `;

        footerRoot.prepend(
            footer
        );
    }


    function boot() {
        installNavbarLogo();
        createHero();
        installFooter();
    }


    if (
        document.readyState ===
        'loading'
    ) {
        document.addEventListener(
            'DOMContentLoaded',
            boot
        );
    } else {
        boot();
    }

})();
