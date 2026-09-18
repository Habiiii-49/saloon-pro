/**
 * Elegance Salon - Main JavaScript
 */
(function () {
    'use strict';

    // Flag that JS is active so CSS can safely hide animate-on-scroll elements
    document.documentElement.classList.add('js');

    /* ===========================
       NAVBAR SCROLL EFFECT
    =========================== */
    const navbar = document.getElementById('mainNavbar');
    function handleNavbarScroll() {
        if (!navbar) return;
        if (window.scrollY > 80) {
            navbar.classList.add('navbar-scrolled');
        } else {
            navbar.classList.remove('navbar-scrolled');
        }
    }
    window.addEventListener('scroll', handleNavbarScroll);
    handleNavbarScroll();

    /* ===========================
       SMOOTH SCROLLING
    =========================== */
    document.querySelectorAll('a[href^="#"]').forEach(function (anchor) {
        anchor.addEventListener('click', function (e) {
            var targetId = this.getAttribute('href');
            if (targetId === '#' || targetId === '#!') return;
            var target = document.querySelector(targetId);
            if (target) {
                e.preventDefault();
                var navHeight = navbar ? navbar.offsetHeight : 0;
                var targetPosition = target.getBoundingClientRect().top + window.pageYOffset - navHeight;
                window.scrollTo({ top: targetPosition, behavior: 'smooth' });
            }
        });
    });

    /* ===========================
       BACK TO TOP
    =========================== */
    var backToTopBtn = document.getElementById('backToTop');
    if (backToTopBtn) {
        window.addEventListener('scroll', function () {
            if (window.scrollY > 400) {
                backToTopBtn.classList.add('visible');
            } else {
                backToTopBtn.classList.remove('visible');
            }
        });
        backToTopBtn.addEventListener('click', function () {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }

    /* ===========================
       ANIMATED COUNTERS
    =========================== */
    function animateCounters() {
        var counters = document.querySelectorAll('.stat-number[data-target]');
        counters.forEach(function (counter) {
            if (counter.dataset.animated === 'true') return;
            var target = parseInt(counter.dataset.target, 10);
            var suffix = counter.dataset.suffix || '';
            var duration = 2000;
            var startTime = null;

            function step(timestamp) {
                if (!startTime) startTime = timestamp;
                var progress = Math.min((timestamp - startTime) / duration, 1);
                var eased = 1 - Math.pow(1 - progress, 3);
                var current = Math.floor(eased * target);
                counter.textContent = current.toLocaleString() + suffix;
                if (progress < 1) {
                    requestAnimationFrame(step);
                } else {
                    counter.textContent = target.toLocaleString() + suffix;
                }
            }
            counter.dataset.animated = 'true';
            requestAnimationFrame(step);
            // Guarantee the final value displays even if rAF is throttled
            setTimeout(function () {
                counter.textContent = target.toLocaleString() + suffix;
            }, duration + 500);
        });
    }

    /* ===========================
       SCROLL-REVEAL (anima ssections)
       Content is revealed when it enters the viewport.
       Multiple mechanisms ensure content is NEVER stuck hidden:
       1) IntersectionObserver 2) scroll/resize check 3) final safety timer
    =========================== */
    function isElementInViewport(el) {
        var rect = el.getBoundingClientRect();
        var vh = window.innerHeight || document.documentElement.clientHeight;
        return rect.top < vh * 0.98 && rect.bottom > 20;
    }

    function revealOnScroll() {
        var anyRevealed = false;
        document.querySelectorAll('.animate-on-scroll, .stats-section').forEach(function (el) {
            if (!el.classList.contains('section-visible') && isElementInViewport(el)) {
                el.classList.add('section-visible');
                if (el.classList.contains('stats-section')) {
                    animateCounters();
                }
                anyRevealed = true;
            }
        });
        return anyRevealed;
    }

    var observerOptions = { threshold: 0.1 };

    var sectionObserver = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add('section-visible');
                if (entry.target.classList.contains('stats-section')) {
                    animateCounters();
                }
                sectionObserver.unobserve(entry.target);
            }
        });
    }, observerOptions);

    document.querySelectorAll('.animate-on-scroll, .stats-section').forEach(function (el) {
        sectionObserver.observe(el);
    });

    // Immediate reveal of anything already in the viewport
    window.addEventListener('load', revealOnScroll);
    revealOnScroll();

    // Reveal as the user scrolls or resizes (throttled)
    var revealThrottle;
    window.addEventListener('scroll', function () {
        clearTimeout(revealThrottle);
        revealThrottle = setTimeout(revealOnScroll, 60);
    });
    window.addEventListener('resize', function () {
        clearTimeout(revealThrottle);
        revealThrottle = setTimeout(revealOnScroll, 120);
    });

    // Absolute safety net: after 3s force-reveal everything still hidden
    setTimeout(function () {
        document.querySelectorAll('.animate-on-scroll, .stats-section').forEach(function (el) {
            if (!el.classList.contains('section-visible')) {
                el.classList.add('section-visible');
                if (el.classList.contains('stats-section')) {
                    animateCounters();
                }
            }
        });
    }, 3000);

    /* ===========================
       GALLERY FILTERING
    =========================== */
    var filterButtons = document.querySelectorAll('.gallery-filter-btn');
    var galleryItems = document.querySelectorAll('.gallery-item');

    filterButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var filter = this.dataset.filter;

            filterButtons.forEach(function (b) { b.classList.remove('active'); });
            this.classList.add('active');

            galleryItems.forEach(function (item) {
                var category = item.dataset.category;
                if (filter === 'all' || category === filter) {
                    item.style.display = '';
                    setTimeout(function () {
                        item.classList.add('gallery-item-visible');
                        item.classList.remove('gallery-item-hidden');
                    }, 50);
                } else {
                    item.classList.remove('gallery-item-visible');
                    item.classList.add('gallery-item-hidden');
                    setTimeout(function () {
                        item.style.display = 'none';
                    }, 400);
                }
            });
        });
    });

    /* ===========================
       LIGHTBOX
    =========================== */
    var lightboxOverlay = null;
    var lightboxImg = null;
    var lightboxCaption = null;
    var lightboxCounter = null;
    var currentIndex = 0;
    var visibleImages = [];

    function getVisibleImages() {
        return Array.from(document.querySelectorAll('.gallery-item')).filter(function (item) {
            return item.style.display !== 'none';
        });
    }

    function createLightbox() {
        if (document.getElementById('galleryLightbox')) {
            return;
        }
        var overlay = document.createElement('div');
        overlay.id = 'galleryLightbox';
        overlay.className = 'lightbox-overlay';
        overlay.innerHTML = '<button class="lightbox-close" aria-label="Close">&times;</button>' +
            '<button class="lightbox-prev" aria-label="Previous"><i class="fas fa-chevron-left"></i></button>' +
            '<button class="lightbox-next" aria-label="Next"><i class="fas fa-chevron-right"></i></button>' +
            '<div class="lightbox-content">' +
            '<img class="lightbox-image" src="" alt="">' +
            '<div class="lightbox-caption"></div>' +
            '<div class="lightbox-counter"></div>' +
            '</div>';
        document.body.appendChild(overlay);

        lightboxOverlay = overlay;
        lightboxImg = overlay.querySelector('.lightbox-image');
        lightboxCaption = overlay.querySelector('.lightbox-caption');
        lightboxCounter = overlay.querySelector('.lightbox-counter');

        overlay.querySelector('.lightbox-close').addEventListener('click', closeLightbox);
        overlay.querySelector('.lightbox-prev').addEventListener('click', function () { navigateLightbox(-1); });
        overlay.querySelector('.lightbox-next').addEventListener('click', function () { navigateLightbox(1); });
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) closeLightbox();
        });
    }

    function openLightbox(index) {
        visibleImages = getVisibleImages();
        currentIndex = index;
        createLightbox();
        updateLightboxContent();
        lightboxOverlay.classList.add('lightbox-active');
        document.body.style.overflow = 'hidden';
    }

    function closeLightbox() {
        if (lightboxOverlay) {
            lightboxOverlay.classList.remove('lightbox-active');
            document.body.style.overflow = '';
        }
    }

    function navigateLightbox(direction) {
        currentIndex += direction;
        if (currentIndex < 0) currentIndex = visibleImages.length - 1;
        if (currentIndex >= visibleImages.length) currentIndex = 0;
        updateLightboxContent();
    }

    function updateLightboxContent() {
        var item = visibleImages[currentIndex];
        if (!item) return;
        var img = item.querySelector('img');
        var title = item.dataset.title || '';
        var category = item.dataset.category || '';
        lightboxImg.src = img ? img.src : '';
        lightboxImg.alt = title;
        lightboxCaption.textContent = title ? title + ' — ' + category : category;
        lightboxCounter.textContent = (currentIndex + 1) + ' / ' + visibleImages.length;
    }

    document.querySelectorAll('.gallery-item').forEach(function (item, index) {
        item.addEventListener('click', function () {
            visibleImages = getVisibleImages();
            var visibleIndex = visibleImages.indexOf(item);
            openLightbox(visibleIndex >= 0 ? visibleIndex : 0);
        });
    });

    document.addEventListener('keydown', function (e) {
        if (!lightboxOverlay || !lightboxOverlay.classList.contains('lightbox-active')) return;
        if (e.key === 'Escape') closeLightbox();
        if (e.key === 'ArrowLeft') navigateLightbox(-1);
        if (e.key === 'ArrowRight') navigateLightbox(1);
    });

    /* ===========================
       SERVICE CARD HOVER GLOW
    =========================== */
    document.querySelectorAll('.service-card').forEach(function (card) {
        card.addEventListener('mousemove', function (e) {
            var rect = card.getBoundingClientRect();
            var x = e.clientX - rect.left;
            var y = e.clientY - rect.top;
            card.style.setProperty('--mouse-x', x + 'px');
            card.style.setProperty('--mouse-y', y + 'px');
        });
    });

    /* ===========================
       LOADING ANIMATION
    =========================== */
    window.addEventListener('load', function () {
        var loader = document.getElementById('pageLoader');
        if (loader) {
            setTimeout(function () {
                loader.classList.add('loader-hidden');
                setTimeout(function () { loader.remove(); }, 500);
            }, 500);
        }
    });

})();
