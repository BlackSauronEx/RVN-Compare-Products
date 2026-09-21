/**
 * RVN Compare — скрипты админки (ванильный JS).
 */
( function () {
	'use strict';

	/**
	 * Словарь поле ⇒ CSS-переменная + формат (вкладка «Дизайн таблицы»).
	 * Живой превью пересчитывает переменные из полей design[секция][поле].
	 */
	var DES_VARS = {
		'table_bg':      { css: '--rvn-compare-table-bg', type: 'color' },
		'header_bg':     { css: '--rvn-compare-header-bg', type: 'color' },
		'label_bg':      { css: '--rvn-compare-label-bg', type: 'color' },
		'group_bg':      { css: '--rvn-compare-group-bg', type: 'color' },
		'group_soft_bg': { css: '--rvn-compare-group-soft', type: 'color' },
		'text':          { css: '--rvn-compare-text', type: 'color' },
		'value_text':    { css: '--rvn-compare-value-text', type: 'color' },
		'label_text':    { css: '--rvn-compare-label-text', type: 'color' },
		'accent':        { css: '--rvn-compare-accent', type: 'color' },
		'diff_bg':       { css: '--rvn-compare-diff', type: 'color' },
		'arrow_bg':      { css: '--rvn-compare-arrow-bg', type: 'color' },
		'floating_bg':   { css: '--rvn-compare-floating-bg', type: 'color' },
		'border':        { css: '--rvn-compare-border', type: 'color' },
		'value_size':    { css: '--rvn-compare-font-size', type: 'px' },
		'label_size':    { css: '--rvn-compare-label-size', type: 'px' },
		'group_size':    { css: '--rvn-compare-group-size', type: 'px' },
		'radius':        { css: '--rvn-compare-radius', type: 'px' },
		'cell_padding':  { css: '--rvn-compare-cell-padding', type: 'px' },
		'photo_height':  { css: '--rvn-compare-photo-height', type: 'px' },
		'label_weight':  { css: '--rvn-compare-label-weight', type: 'raw' },
		'value_weight':  { css: '--rvn-compare-value-weight', type: 'raw' }
	};

	/**
	 * Находит демо-таблицу превью на экране.
	 */
	function previewRoot() {
		return document.querySelector( '.rvn-compare-design__preview .rvn-compare-preview' );
	}

	/**
	 * Применяет object-fit у фейковых фото превью.
	 */
	function applyPhotoFit( root ) {
		var sel = document.querySelector( '.rvn-compare-design__form select[name="design[geometry][photo_fit]"]' );
		var fit = sel ? sel.value : 'contain';
		var imgs = root.querySelectorAll( '.rvn-compare-col__thumb' );
		for ( var i = 0; i < imgs.length; i++ ) {
			imgs[ i ].style.objectFit = fit === 'cover' ? 'cover' : 'contain';
		}
	}

	/**
	 * Показ «мягкого фона групп» в превью.
	 */
	function applySoftBg( root, checkbox ) {
		var enabled = !!( checkbox && checkbox.checked );
		var heads = root.querySelectorAll( '.rvn-compare-group__head' );
		for ( var i = 0; i < heads.length; i++ ) {
			heads[ i ].classList.toggle( 'is-soft', enabled );
		}
	}

	/**
	 * Пересчитывает CSS-переменные превью из текущих значений формы.
	 */
	function refreshPreview() {
		var root = previewRoot();
		if ( ! root ) {
			return;
		}

		for ( var field in DES_VARS ) {
			if ( ! DES_VARS.hasOwnProperty( field ) ) {
				continue;
			}
			var spec = DES_VARS[ field ];
			var input = document.querySelector( '.rvn-compare-design__form [name$="][' + field + ']"]' );
			if ( ! input ) {
				continue;
			}
			var raw = input.value;

			if ( spec.type === 'color' ) {
				if ( ! /^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test( raw ) ) {
					continue; // невалидный цвет — оставляем предыдущий.
				}
				root.style.setProperty( spec.css, raw );
			} else if ( spec.type === 'px' ) {
				var num = parseInt( raw, 10 );
				if ( isNaN( num ) || num < 0 ) {
					continue;
				}
				root.style.setProperty( spec.css, num + 'px' );
			} else {
				root.style.setProperty( spec.css, raw );
			}
		}

		applyPhotoFit( root );
		applySoftBg( root, document.querySelector( '.rvn-compare-design__form input[name="design[behavior][soft_bg_enabled]"]' ) );
	}

	/**
	 * Инициализация вкладки «Дизайн таблицы»: live-превью + sticky-превью.
	 *
	 * @param {Element} root Контейнер .rvn-compare-design.
	 */
	function initDesign( root ) {
		var form = root.querySelector( '.rvn-compare-design__form' );
		var prev = root.querySelector( '.rvn-compare-design__preview' );

		if ( form ) {
			form.addEventListener( 'input', refreshPreview );
			form.addEventListener( 'change', refreshPreview );

			// Запоминаем исходные значения полей для сброса по Esc.
			var fields = form.querySelectorAll( 'input, select' );
			for ( var i = 0; i < fields.length; i++ ) {
				if ( ! fields[ i ].hasAttribute( 'data-default' ) ) {
					fields[ i ].setAttribute( 'data-default', fields[ i ].value );
				}
			}

			form.addEventListener( 'keydown', function ( e ) {
				if ( e.key !== 'Escape' ) {
					return;
				}
				var el = e.target;
				if ( el && ( el.tagName === 'INPUT' || el.tagName === 'SELECT' ) && el.hasAttribute( 'data-default' ) ) {
					el.value = el.getAttribute( 'data-default' );
					refreshPreview();
				}
			} );
		}

		// Sticky-превью: фиксируем колонку превью при прокрутке формы.
		function positionPreview() {
			if ( ! prev || ! form ) {
				return;
			}
			var adminBar = document.getElementById( 'wpadminbar' );
			var top = adminBar ? adminBar.offsetHeight + 20 : 64;
			var prevRect = prev.getBoundingClientRect();
			var formRect = form.getBoundingClientRect();

			if ( prevRect.top < top && prevRect.height < formRect.height && prevRect.bottom > top ) {
				prev.classList.add( 'is-sticky' );
			} else {
				prev.classList.remove( 'is-sticky' );
			}
		}
		window.addEventListener( 'scroll', positionPreview, { passive: true } );
		window.addEventListener( 'resize', positionPreview );
		positionPreview();

		refreshPreview();
	}

	function init() {
		// Color picker на полях с классом .rvn-compare-color.
		var fields = document.querySelectorAll( '.rvn-compare-color' );
		if ( fields.length && window.wp && window.wp.colorPicker ) {
			for ( var i = 0; i < fields.length; i++ ) {
				window.wp.colorPicker( fields[ i ] );
			}
		}

		var design = document.querySelector( '.rvn-compare-design' );
		if ( design ) {
			initDesign( design );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
