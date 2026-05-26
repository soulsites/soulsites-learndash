(function () {
    'use strict';

    var cfg = window.SsLazyTemplate;
    if (!cfg || !cfg.ajaxUrl) return;

    // -------------------------------------------------------------------------
    // Skeleton builder
    // -------------------------------------------------------------------------

    function makeSkeleton() {
        var sk = document.createElement('div');
        sk.className = 'ss-lt-skeleton';

        // Title + text lines
        var lines = document.createElement('div');
        lines.className = 'ss-lt-skeleton__lines';
        [70, 90, 55].forEach(function (w) {
            var ln = document.createElement('div');
            ln.className = 'ss-lt-skeleton__line';
            ln.style.width = w + '%';
            lines.appendChild(ln);
        });
        sk.appendChild(lines);

        // Card row (mimics carousel / grid)
        var cards = document.createElement('div');
        cards.className = 'ss-lt-skeleton__cards';
        for (var i = 0; i < 3; i++) {
            var card = document.createElement('div');
            card.className = 'ss-lt-skeleton__card';

            var thumb = document.createElement('div');
            thumb.className = 'ss-lt-skeleton__thumb';
            card.appendChild(thumb);

            var body = document.createElement('div');
            body.className = 'ss-lt-skeleton__body';
            [80, 60].forEach(function (w) {
                var ln = document.createElement('div');
                ln.className = 'ss-lt-skeleton__line';
                ln.style.width = w + '%';
                body.appendChild(ln);
            });
            card.appendChild(body);
            cards.appendChild(card);
        }
        sk.appendChild(cards);

        return sk;
    }

    function showSkeleton(el) {
        el.classList.add('ss-lt-loading');
        el.appendChild(makeSkeleton());
    }

    // -------------------------------------------------------------------------
    // Template loading
    // -------------------------------------------------------------------------

    var inFlight = new WeakSet();

    function load(el) {
        if (inFlight.has(el)) return;
        inFlight.add(el);

        showSkeleton(el);

        var fd = new FormData();
        fd.append('action',      'ss_load_lazy_template');
        fd.append('template_id', el.dataset.templateId);
        fd.append('nonce',       cfg.nonce);

        fetch(cfg.ajaxUrl, { method: 'POST', body: fd })
            .then(function (r) {
                if (!r.ok) throw new Error('network');
                return r.json();
            })
            .then(function (resp) {
                if (!resp.success) { el.remove(); return; }
                inject(el, resp.data.html);
            })
            .catch(function () {
                // On error remove the placeholder silently
                el.remove();
            });
    }

    function inject(placeholder, html) {
        // Parse HTML and extract <script> tags so they can be re-executed.
        var tmp = document.createElement('div');
        tmp.innerHTML = html;

        var scripts = Array.from(tmp.querySelectorAll('script'));
        scripts.forEach(function (s) { s.parentNode.removeChild(s); });

        var wrapper = document.createElement('div');
        wrapper.className = 'ss-lt-content';
        wrapper.innerHTML = tmp.innerHTML;

        placeholder.parentNode.replaceChild(wrapper, placeholder);

        // Re-execute scripts in document order so inline scripts run correctly.
        scripts.forEach(function (old) {
            var s = document.createElement('script');
            if (old.src) {
                s.src   = old.src;
                s.async = false;
            } else {
                s.textContent = old.textContent;
            }
            if (old.type) s.type = old.type;
            document.head.appendChild(s);
        });

        initElementor(wrapper);
    }

    // -------------------------------------------------------------------------
    // Elementor re-initialisation
    // -------------------------------------------------------------------------

    function initElementor(container) {
        if (!window.jQuery) return;

        function run() {
            if (!window.elementorFrontend || !elementorFrontend.elementsHandler) return;

            // Process outer → inner so sections are ready before widgets.
            jQuery(container).find('.elementor-element').each(function () {
                var $el = jQuery(this);
                // Clear any lazy-slider deferred flag so the widget initialises now.
                delete this.dataset.ssLazyDone;
                elementorFrontend.elementsHandler.runReadyTrigger($el);
            });
        }

        if (window.elementorFrontend && elementorFrontend.isInit) {
            run();
        } else {
            jQuery(window).one('elementor/frontend/init', run);
        }
    }

    // -------------------------------------------------------------------------
    // Observer setup
    // -------------------------------------------------------------------------

    function setup() {
        var placeholders = document.querySelectorAll('.ss-lazy-template[data-template-id]');
        if (!placeholders.length) return;

        // Hard fallback for very old browsers: load everything immediately.
        if (!('IntersectionObserver' in window)) {
            placeholders.forEach(load);
            return;
        }

        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                observer.unobserve(entry.target);
                load(entry.target);
            });
        }, { rootMargin: '300px 0px' });

        placeholders.forEach(function (el) { observer.observe(el); });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setup);
    } else {
        setup();
    }
})();
