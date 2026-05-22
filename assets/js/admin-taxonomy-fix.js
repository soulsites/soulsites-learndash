/* global acf, jQuery */
(function ($) {
	'use strict';

	if (typeof acf === 'undefined') return;

	/*
	 * ACF taxonomy checkbox fields manage visual state via the `md-checked`
	 * CSS class on the parent <li>, but for server-rendered pre-checked inputs
	 * (checked="1") they sometimes fail to update input.checked when the user
	 * clicks to deselect. We watch for md-checked class changes and keep the
	 * actual checkbox in sync so the form submits the correct values.
	 */
	acf.addAction('ready', function () {
		if (!window.MutationObserver) return;

		$('.acf-taxonomy-field[data-ftype="checkbox"]').each(function () {
			new MutationObserver(function (mutations) {
				mutations.forEach(function (m) {
					if (m.attributeName !== 'class' || m.target.tagName !== 'LI') return;
					var li    = m.target;
					var input = li.querySelector('input[type="checkbox"]');
					if (!input) return;
					var checked = li.classList.contains('md-checked');
					if (input.checked !== checked) {
						input.checked = checked;
					}
				});
			}).observe(this, {
				subtree:         true,
				attributes:      true,
				attributeFilter: ['class'],
			});
		});
	});

}(jQuery));
