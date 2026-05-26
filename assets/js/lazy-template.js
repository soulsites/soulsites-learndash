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
    // CSS injection
    // -------------------------------------------------------------------------

    /**
     * Inject CSS assets that Elementor enqueued server-side during rendering but
     * that never reached the browser because there was no wp_head() in AJAX context.
     *
     * Each asset is keyed by its WordPress style handle so we never inject it twice.
     *
     * @param {Object} cssAssets  { handle: { url?, inline? }, … }
     */
    function injectCssAssets(cssAssets) {
        if (!cssAssets) return;

        Object.keys(cssAssets).forEach(function (handle) {
            var asset = cssAssets[handle];
            var linkId  = 'ss-lt-link-'   + handle;
            var inlineId = 'ss-lt-inline-' + handle;

            // External stylesheet – only inject once, identified by handle
            if (asset.url && !document.getElementById(linkId)) {
                var link = document.createElement('link');
                link.id   = linkId;
                link.rel  = 'stylesheet';
                link.href = asset.url;
                document.head.appendChild(link);
            }

            // Inline CSS (wp_add_inline_style) – only inject once
            if (asset.inline && !document.getElementById(inlineId)) {
                var style = document.createElement('style');
                style.id          = inlineId;
                style.textContent = asset.inline;
                document.head.appendChild(style);
            }
        });
    }

    // -------------------------------------------------------------------------
    // elements_data population
    // -------------------------------------------------------------------------

    /**
     * Elementor's widget handlers (loop-carousel, loop-grid, Swiper, …) read
     * their settings from elementorFrontendConfig.elements_data[elementId].
     *
     * That object is populated during the initial page render via wp_head() –
     * but NOT for templates loaded via AJAX.  The same data is however available
     * as a JSON string in the data-settings attribute that Elementor outputs on
     * every .elementor-element during server-side rendering.
     *
     * We read those attributes and backfill elements_data before runReadyTrigger
     * is called, so Swiper and other handlers get the settings they need.
     */
    function populateElementsData(container) {
        if (!window.elementorFrontendConfig) return;

        if (!elementorFrontendConfig.elements_data) {
            elementorFrontendConfig.elements_data = {};
        }

        container.querySelectorAll('[data-id]').forEach(function (el) {
            var id = el.dataset.id;
            if (!id) return;

            // Never overwrite data that was already present in the global config
            // (e.g. the same template embedded twice on one page).
            if (elementorFrontendConfig.elements_data[id]) return;

            // data-settings contains the frontend-relevant widget settings as JSON.
            var raw = el.dataset.settings;
            try {
                elementorFrontendConfig.elements_data[id] = raw ? JSON.parse(raw) : {};
            } catch (e) {
                elementorFrontendConfig.elements_data[id] = {};
            }
        });
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
        // Pass the page context so the server-side WP_Query sees the correct
        // global post (e.g. for context-aware filters / dynamic tags).
        fd.append('context_id',  el.dataset.contextId || '0');

        fetch(cfg.ajaxUrl, { method: 'POST', body: fd })
            .then(function (r) {
                if (!r.ok) throw new Error('network');
                return r.json();
            })
            .then(function (resp) {
                if (!resp.success) { el.remove(); return; }
                inject(el, resp.data);
            })
            .catch(function () {
                el.remove();
            });
    }

    function inject(placeholder, data) {
        var html      = data.html       || '';
        var cssAssets = data.css_assets || {};

        // 1. Inject CSS that Elementor enqueued during server-side rendering.
        //    This must happen before the HTML enters the DOM so the browser can
        //    start fetching the stylesheets as early as possible.
        injectCssAssets(cssAssets);

        // 2. Parse the HTML, extracting <script> tags so they can be re-executed
        //    after the content is in the DOM (innerHTML does not execute scripts).
        var tmp = document.createElement('div');
        tmp.innerHTML = html;

        var scripts = Array.from(tmp.querySelectorAll('script'));
        scripts.forEach(function (s) { s.parentNode.removeChild(s); });

        var wrapper = document.createElement('div');
        wrapper.className = 'ss-lt-content';
        wrapper.innerHTML = tmp.innerHTML;

        // 3. Backfill elementorFrontendConfig.elements_data from data-settings
        //    attributes *before* the wrapper is in the live DOM (and before
        //    runReadyTrigger fires) so widget handlers find their settings.
        populateElementsData(wrapper);

        // 4. Swap placeholder for the real content.
        placeholder.parentNode.replaceChild(wrapper, placeholder);

        // 5. Re-execute scripts in document order so inline scripts that depend
        //    on the DOM being present (e.g. custom JS in the template) work.
        scripts.forEach(function (old) {
            var s = document.createElement('script');
            if (old.src) {
                s.src   = old.src;
                s.async = false; // preserve execution order
            } else {
                s.textContent = old.textContent;
            }
            if (old.type) s.type = old.type;
            document.head.appendChild(s);
        });

        // 6. Trigger Elementor widget initialisation (Swiper, loop-grid, etc.).
        //
        // We defer to the NEXT animation frame so the browser completes the
        // layout pass for the newly-injected content first.  Swiper reads the
        // container's pixel width to calculate how many slides fit; if we call
        // runReadyTrigger synchronously (width still 0 / unresolved), Swiper
        // falls back to slidesPerView = 1 regardless of the configured value.
        requestAnimationFrame(function () {
            initElementor(wrapper);
        });
    }

    // -------------------------------------------------------------------------
    // Elementor re-initialisation
    // -------------------------------------------------------------------------

    function initElementor(container) {
        if (!window.jQuery) return;

        function run() {
            if (!window.elementorFrontend || !elementorFrontend.elementsHandler) return;

            // Process elements in DOM order (outer → inner) so sections and
            // columns are ready before the widgets inside them initialise.
            jQuery(container).find('.elementor-element').each(function () {
                // Clear any deferred-flag set by the lazy-slider feature so
                // carousels inside this template initialise immediately.
                delete this.dataset.ssLazyDone;
                elementorFrontend.elementsHandler.runReadyTrigger(jQuery(this));
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

        // Hard fallback for browsers without IntersectionObserver.
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
