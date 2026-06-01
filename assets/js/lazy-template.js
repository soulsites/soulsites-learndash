(function () {
    'use strict';

    var cfg = window.SsLazyTemplate;

    // Debug: Check if config is loaded
    if (!cfg) {
        console.warn('[SsLazyTemplate] Config not found: window.SsLazyTemplate is undefined');
        return;
    }

    if (!cfg.ajaxUrl) {
        console.warn('[SsLazyTemplate] Config incomplete: ajaxUrl not set');
        return;
    }

    // Debug: Confirm config is ready
    console.log('[SsLazyTemplate] Config loaded:', { ajaxUrl: cfg.ajaxUrl, nonce: cfg.nonce ? 'set' : 'missing' });

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
     *
     * Critically: Elementor expects elements_data[id] to be an object with the
     * shape { id, elType, widgetType, settings: {...} } – NOT just the raw
     * settings dict.  Storing the bare settings caused loop-grid widgets with
     * a carousel display type to fall back to grid rendering (handlers read
     * `.settings.display_type` and got undefined).
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

            var settings = {};
            var raw = el.dataset.settings;
            if (raw) {
                try { settings = JSON.parse(raw); } catch (e) {}
            }

            var elType     = el.getAttribute('data-element_type') || '';
            var widgetType = el.getAttribute('data-widget_type')  || '';

            elementorFrontendConfig.elements_data[id] = {
                id:         id,
                elType:     elType || (widgetType ? 'widget' : ''),
                widgetType: widgetType,
                settings:   settings
            };
        });
    }

    // -------------------------------------------------------------------------
    // Template loading
    // -------------------------------------------------------------------------

    var inFlight = new WeakSet();

    function load(el) {
        if (inFlight.has(el)) {
            console.log('[SsLazyTemplate] Load already in flight for this element');
            return;
        }
        inFlight.add(el);

        var templateId = el.dataset.templateId;
        var contextId = el.dataset.contextId || '0';

        console.log('[SsLazyTemplate] Loading template:', { templateId: templateId, contextId: contextId });

        showSkeleton(el);

        var fd = new FormData();
        fd.append('action',      'ss_load_lazy_template');
        fd.append('template_id', templateId);
        fd.append('nonce',       cfg.nonce);
        fd.append('context_id',  contextId);

        console.log('[SsLazyTemplate] Sending AJAX request to:', cfg.ajaxUrl);

        fetch(cfg.ajaxUrl, { method: 'POST', body: fd })
            .then(function (r) {
                console.log('[SsLazyTemplate] AJAX response status:', r.status);
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            })
            .then(function (resp) {
                console.log('[SsLazyTemplate] AJAX response:', resp);
                if (!resp.success) {
                    console.error('[SsLazyTemplate] Server error:', resp.data);
                    el.remove();
                    return;
                }
                inject(el, resp.data);
            })
            .catch(function (err) {
                console.error('[SsLazyTemplate] AJAX error:', err);
                el.remove();
            });
    }

    function inject(placeholder, data) {
        var html      = data.html       || '';
        var cssAssets = data.css_assets || {};

        console.log('[SsLazyTemplate] Injecting content:', { htmlLength: html.length, cssAssetCount: Object.keys(cssAssets).length });

        // 1. Inject CSS that Elementor enqueued during server-side rendering.
        //    This must happen before the HTML enters the DOM so the browser can
        //    start fetching the stylesheets as early as possible.
        injectCssAssets(cssAssets);

        // 2. Parse the HTML into a detached wrapper.  Scripts placed via
        //    innerHTML do NOT auto-execute, so we'll walk them after insertion
        //    and re-execute only the ones that should run.
        var wrapper = document.createElement('div');
        wrapper.className = 'ss-lt-content';
        wrapper.innerHTML = html;

        // 3. Backfill elementorFrontendConfig.elements_data from data-settings
        //    attributes *before* the wrapper is in the live DOM (and before
        //    runReadyTrigger fires) so widget handlers find their settings.
        populateElementsData(wrapper);

        // 4. Swap placeholder for the real content.
        placeholder.parentNode.replaceChild(wrapper, placeholder);

        // 5. Re-execute scripts in-place.
        //
        //    Critically: do NOT move scripts to <head>.  Elementor's loop
        //    widgets (and other modules) embed non-executable template/data
        //    blocks (e.g. <script type="text/template">) that other JS reads
        //    by querying inside the widget element.  Moving them to <head>
        //    detaches them from that lookup and breaks slide hydration so
        //    only the one server-rendered slide remains.
        //
        //    For each <script> we replace the inert (innerHTML-parsed) node
        //    with a freshly-created equivalent in the same position:
        //      - Executable scripts (no type / text/javascript / module): run.
        //      - Non-executable types (template, json, x-loop-data, …):
        //        copy as-is so other JS can still find them by selector.
        Array.from(wrapper.querySelectorAll('script')).forEach(function (old) {
            var type = (old.type || '').toLowerCase();
            var isExecutable = type === ''
                || type === 'text/javascript'
                || type === 'application/javascript'
                || type === 'module';

            var s = document.createElement('script');
            if (old.src) {
                s.src   = old.src;
                if (isExecutable) s.async = false; // preserve execution order
            } else {
                s.textContent = old.textContent;
            }
            if (old.type) s.type = old.type;
            // Copy any other attributes that might matter for the script
            // (id, data-*, integrity, crossorigin, nonce, …).
            for (var i = 0; i < old.attributes.length; i++) {
                var attr = old.attributes[i];
                if (attr.name === 'src' || attr.name === 'type') continue;
                s.setAttribute(attr.name, attr.value);
            }

            // Replace at the original location – preserves widget context.
            old.parentNode.replaceChild(s, old);
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

            // 7. Fire custom event for other plugins/scripts to hook into.
            // Event fires on both the container and window for flexibility.
            var event = new CustomEvent('ssLazyTemplateLoaded', {
                detail: {
                    container: wrapper,
                    templateId: placeholder.dataset.templateId,
                    timestamp: new Date().getTime()
                },
                bubbles: true,
                cancelable: false
            });

            wrapper.dispatchEvent(event);
            window.dispatchEvent(event);

            console.log('[SsLazyTemplate] Content loaded, fired ssLazyTemplateLoaded event');
        });
    }

    // -------------------------------------------------------------------------
    // Elementor re-initialisation
    // -------------------------------------------------------------------------

    function initElementor(container) {
        if (!window.jQuery) return;

        function run() {
            if (!window.elementorFrontend || !elementorFrontend.elementsHandler) return;

            jQuery(container).find('.elementor-element').each(function () {
                // Set ssLazyDone = '1' BEFORE calling runReadyTrigger.
                //
                // If lazy-slider.js is also active it patches runReadyTrigger
                // globally: for loop-carousel/loop-slider widgets it normally
                // re-defers initialisation until the element enters the viewport.
                // That second deferral is wrong here – the template was just
                // loaded because the user scrolled near it, so we want Swiper
                // to fire right now, not wait for another IntersectionObserver.
                //
                // The lazy-slider patch short-circuits when ssLazyDone === '1':
                //   if (el.dataset.ssLazyDone || isNearViewport(el)) {
                //       return originalRunReadyTrigger($scope);   // ← direct
                //   }
                // Setting the flag therefore bypasses the re-deferral entirely.
                this.dataset.ssLazyDone = '1';
                elementorFrontend.elementsHandler.runReadyTrigger(jQuery(this));
            });

            // Force every Swiper inside the new content to recalculate its
            // slide dimensions after one more render pass.  Swiper reads
            // the container width on init; if the browser hadn't finished
            // layout when runReadyTrigger fired, update() corrects that.
            requestAnimationFrame(function () {
                container.querySelectorAll('.swiper, .swiper-container, .elementor-loop-container').forEach(function (c) {
                    if (c.swiper && typeof c.swiper.update === 'function') {
                        c.swiper.update();
                    }
                });
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
        console.log('[SsLazyTemplate] Found ' + placeholders.length + ' lazy template placeholder(s)');

        if (!placeholders.length) {
            console.warn('[SsLazyTemplate] No lazy templates found on page');
            return;
        }

        // Hard fallback for browsers without IntersectionObserver.
        if (!('IntersectionObserver' in window)) {
            console.log('[SsLazyTemplate] IntersectionObserver not available, loading immediately');
            placeholders.forEach(load);
            return;
        }

        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                console.log('[SsLazyTemplate] Template entering viewport, loading...');
                observer.unobserve(entry.target);
                load(entry.target);
            });
        }, { rootMargin: '300px 0px' });

        placeholders.forEach(function (el) {
            observer.observe(el);
        });

        console.log('[SsLazyTemplate] IntersectionObserver setup complete');
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setup);
    } else {
        setup();
    }
})();
