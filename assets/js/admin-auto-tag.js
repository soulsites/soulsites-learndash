/* global acf, SoulsitesAutoTag, jQuery */
(function ($) {
	'use strict';

	if (typeof acf === 'undefined' || typeof SoulsitesAutoTag === 'undefined') {
		return;
	}

	// fieldConfig: [{ field: 'name', key: 'field_xxx', taxonomy: 'ld_course_tag' }, ...]
	var config  = SoulsitesAutoTag.fieldConfig;
	var timers  = {};

	// Quick lookup by field name and by field key.
	var byName = {};
	var byKey  = {};
	config.forEach(function (c) {
		byName[c.field] = c;
		byKey[c.key]    = c;
	});

	// -------------------------------------------------------------------------
	// Value extraction
	// -------------------------------------------------------------------------

	/**
	 * Extract selected/entered values from an ACF field wrapper element.
	 * Supports: select (single + multiple), checkbox, radio, text, textarea.
	 *
	 * @param  {jQuery} $wrapper  [data-name] or [data-key] wrapper div.
	 * @returns {string[]}
	 */
	function extractValues($wrapper) {
		var values = [];

		// Select (single & multiple)
		$wrapper.find('select').each(function () {
			var selected = $(this).val();
			if (!selected) return;
			[].concat(selected).forEach(function (v) {
				if (v) values.push(v);
			});
		});

		// Checkbox / radio
		$wrapper.find('input[type="checkbox"]:checked, input[type="radio"]:checked').each(function () {
			var v = $(this).val();
			if (v && v !== 'false') values.push(v);
		});

		// Text / url / email inputs
		$wrapper.find('input[type="text"], input[type="email"], input[type="url"]').each(function () {
			$(this).val().split(',').forEach(function (part) {
				part = $.trim(part);
				if (part) values.push(part);
			});
		});

		// Textarea
		$wrapper.find('textarea').each(function () {
			$(this).val().split(',').forEach(function (part) {
				part = $.trim(part);
				if (part) values.push(part);
			});
		});

		// Deduplicate.
		return values.filter(function (v, i, arr) {
			return v !== '' && arr.indexOf(v) === i;
		});
	}

	// -------------------------------------------------------------------------
	// AJAX sync
	// -------------------------------------------------------------------------

	/**
	 * Debounced AJAX call to create/update tags for a single field.
	 *
	 * @param {Object}   cfg     Entry from fieldConfig ({ field, key, taxonomy }).
	 * @param {string[]} values  Extracted values to sync as tags.
	 */
	function syncField(cfg, values) {
		clearTimeout(timers[cfg.key]);

		timers[cfg.key] = setTimeout(function () {
			delete timers[cfg.key];

			$.ajax({
				url:  SoulsitesAutoTag.ajaxUrl,
				type: 'POST',
				data: {
					action:    'soulsites_auto_tag_course',
					nonce:     SoulsitesAutoTag.nonce,
					post_id:   SoulsitesAutoTag.postId,
					field_key: cfg.key,
					taxonomy:  cfg.taxonomy,
					value:     values,
				},
				success: function (response) {
					if (response.success && response.data.terms.length) {
						showFeedback(cfg.field, response.data.terms);
					}
				},
			});
		}, 600);
	}

	// -------------------------------------------------------------------------
	// Visual feedback
	// -------------------------------------------------------------------------

	/**
	 * Briefly shows the auto-assigned tag names below the field wrapper.
	 *
	 * @param {string}   fieldName  ACF field name (used to find the wrapper).
	 * @param {string[]} terms      Term names that were assigned.
	 */
	function showFeedback(fieldName, terms) {
		var $wrapper = $('[data-name="' + fieldName + '"]').first();
		if (!$wrapper.length) return;

		var $feedback = $wrapper.find('.soulsites-tag-feedback');
		if (!$feedback.length) {
			$feedback = $(
				'<div class="soulsites-tag-feedback"' +
				' style="margin-top:4px;font-size:11px;color:#46b450;line-height:1.4;"></div>'
			);
			$wrapper.append($feedback);
		}

		var label = (SoulsitesAutoTag.i18n && SoulsitesAutoTag.i18n.tagged)
			? SoulsitesAutoTag.i18n.tagged : 'Tags:';

		$feedback
			.text(label + ' ' + terms.map(function (t) { return '#' + t; }).join('  '))
			.stop(true, true)
			.fadeIn(150);

		clearTimeout($feedback.data('soulsites-hide'));
		$feedback.data(
			'soulsites-hide',
			setTimeout(function () { $feedback.fadeOut(400); }, 3500)
		);
	}

	// -------------------------------------------------------------------------
	// ACF integration
	// -------------------------------------------------------------------------

	acf.addAction('ready', function () {
		// Event delegation on .acf-fields so repeater rows added later are covered.
		$('.acf-fields, #poststuff').on('change', '[data-name], [data-key]', function () {
			var $el      = $(this);
			var cfg      = byName[$el.data('name')] || byKey[$el.data('key')];
			if (!cfg) return;

			var values = extractValues($el);
			syncField(cfg, values);
		});

		// ACF's own change action (covers relationship, taxonomy picker, date, etc.).
		acf.addAction('change', function (field) {
			// Try matching by field key first (most reliable), then by name.
			var cfg = byKey[field.get('key')] || byName[field.get('name')];
			if (!cfg) return;

			var $el    = field.$el;
			var values = $el && $el.length ? extractValues($el) : [];
			syncField(cfg, values);
		});
	});

}(jQuery));
