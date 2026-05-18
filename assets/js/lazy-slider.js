(function () {
    'use strict';

    var LazySliderManager = function () {
        this._pending = new Map();
        this._observer = new IntersectionObserver(
            this._onIntersect.bind(this),
            { rootMargin: '300px 0px' }
        );
    };

    LazySliderManager.prototype.init = function () {
        var self = this;
        window.elementorFrontend.hooks.addAction(
            'frontend/element_ready/loop-slider.default',
            function ($scope) {
                self._handleSlider($scope);
            }
        );
    };

    LazySliderManager.prototype._handleSlider = function ($scope) {
        var el = $scope[0];

        // Guard: skip if already processed by us
        if (typeof el.dataset.ssLazy !== 'undefined') {
            return;
        }

        var rect = el.getBoundingClientRect();
        var isNearViewport = rect.top < window.innerHeight + 300;

        if (isNearViewport) {
            el.dataset.ssLazy = 'ready';
            return;
        }

        // Destroy the Swiper instance Elementor just created
        var swiperEl = el.querySelector('.swiper');
        if (swiperEl && swiperEl.swiper) {
            swiperEl.swiper.destroy(true, true);
        }

        el.dataset.ssLazy = 'pending';
        el.classList.add('ss-lazy-slider');
        this._injectSkeleton(el);

        this._pending.set(el, $scope);
        this._observer.observe(el);
    };

    LazySliderManager.prototype._injectSkeleton = function (el) {
        // Derive card count from visible slides setting stored on the swiper container
        var swiperEl  = el.querySelector('.swiper');
        var cardCount = 3;
        if (swiperEl) {
            var perView = parseInt(swiperEl.dataset.ssSlidesPerView, 10);
            if (!isNaN(perView) && perView > 0) {
                cardCount = perView;
            }
        }

        var skeleton = document.createElement('div');
        skeleton.className = 'ss-lazy-skeleton';

        for (var i = 0; i < cardCount; i++) {
            var card  = document.createElement('div');
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

        el.appendChild(skeleton);
    };

    LazySliderManager.prototype._removeSkeleton = function (el) {
        var skeleton = el.querySelector('.ss-lazy-skeleton');
        if (skeleton) {
            skeleton.remove();
        }
        el.classList.remove('ss-lazy-slider');
    };

    LazySliderManager.prototype._onIntersect = function (entries) {
        var self = this;
        entries.forEach(function (entry) {
            if (!entry.isIntersecting) {
                return;
            }

            var el     = entry.target;
            var $scope = self._pending.get(el);

            self._observer.unobserve(el);
            self._pending.delete(el);

            if (!$scope) {
                return;
            }

            // Mark as ready BEFORE runReadyTrigger so our hook guard fires
            el.dataset.ssLazy = 'ready';
            self._removeSkeleton(el);

            if (
                window.elementorFrontend.elementsHandler &&
                typeof window.elementorFrontend.elementsHandler.runReadyTrigger === 'function'
            ) {
                window.elementorFrontend.elementsHandler.runReadyTrigger($scope);
            }
        });
    };

    jQuery(window).on('elementor/frontend/init', function () {
        if (typeof window.elementorFrontend === 'undefined') {
            return;
        }
        var manager = new LazySliderManager();
        manager.init();
    });
})();
