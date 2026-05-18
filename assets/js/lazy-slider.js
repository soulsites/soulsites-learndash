(function () {
    'use strict';

    // Both known widget type names for carousel-style loop widgets
    var LAZY_TYPES = ['loop-carousel', 'loop-slider'];

    function isLazyWidgetType(widgetType) {
        if (!widgetType) return false;
        var base = widgetType.split('.')[0];
        return LAZY_TYPES.indexOf(base) !== -1;
    }

    function isNearViewport(el) {
        var rect = el.getBoundingClientRect();
        return rect.top < window.innerHeight + 300;
    }

    function getCardCount(el) {
        var container = el.querySelector('.elementor-loop-container');
        if (!container) return 3;

        // Elementor stores Swiper options as JSON on the container
        var raw = container.dataset.swiperOptions
            || container.dataset.elementorSwiperOptions
            || '';

        if (raw) {
            try {
                var opts = JSON.parse(raw);
                var perView = opts.slidesPerView || opts.slides_per_view;
                if (perView && perView !== 'auto' && parseFloat(perView) > 0) {
                    return Math.min(Math.ceil(parseFloat(perView)) + 1, 6);
                }
            } catch (e) {}
        }

        return 3;
    }

    function injectSkeleton(el) {
        var cardCount = getCardCount(el);
        var skeleton = document.createElement('div');
        skeleton.className = 'ss-lazy-skeleton';

        for (var i = 0; i < cardCount; i++) {
            var card = document.createElement('div');
            card.className = 'ss-lazy-skeleton__card';

            var thumb = document.createElement('div');
            thumb.className = 'ss-lazy-skeleton__thumb';
            card.appendChild(thumb);

            var body = document.createElement('div');
            body.className = 'ss-lazy-skeleton__body';

            for (var j = 0; j < 3; j++) {
                var line = document.createElement('div');
                line.className = 'ss-lazy-skeleton__line';
                body.appendChild(line);
            }
            card.appendChild(body);
            skeleton.appendChild(card);
        }

        el.classList.add('ss-lazy-slider');
        el.appendChild(skeleton);
    }

    function removeSkeleton(el) {
        var skeleton = el.querySelector('.ss-lazy-skeleton');
        if (skeleton) skeleton.remove();
        el.classList.remove('ss-lazy-slider');
    }

    jQuery(window).on('elementor/frontend/init', function () {
        if (!window.elementorFrontend || !elementorFrontend.elementsHandler) return;

        var handler = elementorFrontend.elementsHandler;

        // Guard: only patch once
        if (handler._ssLazyPatched) return;
        handler._ssLazyPatched = true;

        var originalRunReadyTrigger = handler.runReadyTrigger.bind(handler);
        var pending = new Map();

        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;

                var el = entry.target;
                observer.unobserve(el);

                var data = pending.get(el);
                if (!data) return;
                pending.delete(el);

                removeSkeleton(el);

                // Now let Elementor initialize the widget normally
                originalRunReadyTrigger(data.$scope);
            });
        }, { rootMargin: '300px 0px' });

        handler.runReadyTrigger = function ($scope) {
            var el = $scope[0];

            if (!isLazyWidgetType($scope.data('widget_type'))) {
                return originalRunReadyTrigger($scope);
            }

            // Already handled (re-entry from observer callback) or close enough
            if (el.dataset.ssLazyDone || isNearViewport(el)) {
                el.dataset.ssLazyDone = '1';
                return originalRunReadyTrigger($scope);
            }

            // Defer: skip Swiper init entirely until in viewport
            injectSkeleton(el);
            pending.set(el, { $scope: $scope });
            observer.observe(el);
        };
    });
})();
