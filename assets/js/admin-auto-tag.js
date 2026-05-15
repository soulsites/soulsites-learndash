/* global acf, SoulsitesAutoTag, jQuery */
(function ($) {
	'use strict';

	if (typeof acf === 'undefined' || typeof SoulsitesAutoTag === 'undefined') {
		return;
	}

	var config   = SoulsitesAutoTag.fieldConfig; // [{ field, taxonomy }, ...]
	var timers   = {};

	/** Map of fieldName → { field, taxonomy } for quick lookup. */
	var fieldMap = {};
	config.forEach(function (c) {
		fieldMap[c.field] = c;
	});

	// -------------------------------------------------------------------------
	// Value extraction
	// -------------------------------------------------------------------------

	/**
	 * Extract an array of selected/entered values from an ACF field wrapper.
	 * Works with: select (single + multi), checkbox, radio, text, textarea.
	 *
	 * @param {jQuery} $wrapper  Element with [data-name="fieldname"].
	 * @returns {string[]}
	 */
	function extractValues($wrapper) {
		var values = [];

		// Select (single & multiple)
		$wrapper.find('select').each(function () {
			var selected = $(this).val();
			if (!selected) return;
			if (Array.isArray(selected)) {
				values = values.concat(selected);
			} else {
				values.push(selected);
			}
		});

		// Checkbox / radio inputs
		$wrapper.find('input[type="checkbox"]:checked, input[type="radio"]:checked').each(function () {
			var v = $(this).val();
			if (v && v !== 'false') values.push(v);
		});

		// Text / email / url inputs (skip hidden, submit, etc.)
		$wrapper.find('input[type="text"], input[type="email"], input[type="url"]').each(function () {
			var v = $.trim($(this).val());
			if (v) {
				// Support comma-separated entries
				v.split(',').forEach(function (part) {
					part = $.trim(part);
					if (part) values.push(part);
				});
			}
		});

		// Textarea
		$wrapper.find('textarea').each(function () {
			var v = $.trim($(this).val());
			if (v) {
				v.split(',').forEach(function (part) {
					part = $.trim(part);
					if (part) values.push(part);
				});
			}
		});

		// Deduplicate and remove empty strings.
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
	 * @param {string}   fieldName
	 * @param {string}   taxonomy
	 * @param {string[]} values
	 */
	function syncField(fieldName, taxonomy, values) {
		clearTimeout(timers[fieldName]);

		timers[fieldName] = setTimeout(function () {
			delete timers[fieldName];

			$.ajax({
				url:  SoulsitesAutoTag.ajaxUrl,
				type: 'POST',
				data: {
					action:   'soulsites_auto_tag_course',
					nonce:    SoulsitesAutoTag.nonce,
					post_id:  SoulsitesAutoTag.postId,
					field:    fieldName,
					taxonomy: taxonomy,
					value:    values,
				},
				success: function (response) {
					if (response.success && response.data.terms.length) {
						showFeedback(fieldName, response.data.terms);
					}
				},
			});
		}, 600);
	}

	// -------------------------------------------------------------------------
	// Visual feedback
	// -------------------------------------------------------------------------

	/**
	 * Briefly shows the auto-assigned tag names below the field.
	 *
	 * @param {string}   fieldName
	 * @param {string[]} terms
	 */
	function showFeedback(fieldName, terms) {
		var $wrapper  = $('[data-name="' + fieldName + '"]').first();
		if (!$wrapper.length) return;

		var $feedback = $wrapper.find('.soulsites-tag-feedback');
		if (!$feedback.length) {
			$feedback = $(
				'<div class="soulsites-tag-feedback" ' +
				'style="margin-top:4px;font-size:11px;color:#46b450;line-height:1.4;"></div>'
			);
			$wrapper.append($feedback);
		}

		var label = (SoulsitesAutoTag.i18n && SoulsitesAutoTag.i18n.tagged)
			? SoulsitesAutoTag.i18n.tagged
			: 'Tags:';

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
	// ACF hooks
	// -------------------------------------------------------------------------

	/**
	 * Attach change listeners once ACF has rendered fields on the page.
	 * Uses event delegation so dynamic fields (repeater rows added later)
	 * are also covered.
	 */
	acf.addAction('ready', function () {
		// Delegate to .acf-fields container so it survives repeater re-renders.
		$('.acf-fields, #poststuff').on(
			'change',
			'[data-name]',
			function () {
				var fieldName = $(this).data('name');
				if (!fieldName || !fieldMap[fieldName]) return;

				var cfg    = fieldMap[fieldName];
				var values = extractValues($(this));
				if (values.length) {
					syncField(cfg.field, cfg.taxonomy, values);
				}
			}
		);

		// Also listen via ACF's own change action for completeness
		// (fires for ACF-managed field types like relationship, taxonomy, etc.).
		acf.addAction('change', function (field) {
			var name = field.get('name');
			if (!name || !fieldMap[name]) return;

			var cfg = fieldMap[name];
			// Use the field's $el wrapper to extract values uniformly.
			var $el = field.$el;
			if (!$el || !$el.length) return;

			var values = extractValues($el);
			if (values.length) {
				syncField(cfg.field, cfg.taxonomy, values);
			}
		});
	});

}(jQuery));
