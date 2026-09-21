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
		refreshBuyPreview();
	}

	/**
	 * Перерисовывает кнопку «Купить» в превью по настройке режима и
	 * «наследовать стили темы» (режим «Мои шорткоды» показываем плейсхолдером).
	 */
	function refreshBuyPreview() {
		var root = previewRoot();
		if ( ! root ) {
			return;
		}
		var buys = root.querySelectorAll( '[data-rvn-buy-preview]' );
		var headerSel = document.querySelector( '.rvn-compare-design__form select[name="design[behavior][buy_header]"]' );
		var inherit = document.querySelector( '.rvn-compare-design__form input[name="design[behavior][inherit_theme_styles]"]' );
		var mode = headerSel ? headerSel.value : 'buy';
		var theme = !!( inherit && inherit.checked );

		for ( var i = 0; i < buys.length; i++ ) {
			var el = buys[ i ];
			el.classList.toggle( 'rvn-compare-buy--theme', theme );
			if ( mode === 'hidden' ) {
				el.style.display = 'none';
			} else {
				el.style.display = '';
				if ( mode === 'shortcode' ) {
					el.setAttribute( 'data-rvn-buy-preview-text', el.textContent );
					el.textContent = '[shortcode]';
				} else if ( el.hasAttribute( 'data-rvn-buy-preview-text' ) ) {
					el.textContent = el.getAttribute( 'data-rvn-buy-preview-text' );
					el.removeAttribute( 'data-rvn-buy-preview-text' );
				}
			}
		}
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

	/**
	 * Живой предпросмотр вкладки «Дизайн элементов и кнопок».
	 *
	 * Рисует внутренности кнопок/тостов-preview из значений формы es[...],
	 * в том числе SVG-иконку, режим, тексты, позицию бейджа и hover.
	 *
	 * @param {Element} root Контейнер .rvn-compare-elems.
	 */
	function refreshElementsPreview( root ) {
		var stage = root.querySelector( '[data-rvn-compare-stage]' );
		if ( ! stage ) {
			return;
		}

		function val( selector, fallback ) {
			var el = root.closest( 'form' ) ? root.closest( 'form' ).querySelector( selector ) : null;
			el = el || document.querySelector( selector );
			return el && el.value !== undefined ? el.value : fallback;
		}

		// Кнопка «Сравнить» и её режим.
		var btn = stage.querySelector( '.rvn-compare-button' );
		var compareMode = val( 'select[name="es[compare][mode]"]', 'icon_text' );
		var compareSvg = val( 'textarea[name="es[compare][svg]"]', '' );
		var btnText = val( 'input[name="button_text"]', 'Сравнить' );
		if ( btn ) {
			var icon1 = '';
			if ( compareMode === 'icon_text' || compareMode === 'text_icon' || compareMode === 'icon' ) {
				icon1 = '<span class="rvn-compare-button__icon">' + compareSvg + '</span>';
			}
			var label1 = ( compareMode === 'icon' || compareMode === 'hidden' ) ? '' : '<span class="rvn-compare-button__label">' + escapeHtml( btnText ) + '</span>';
			btn.className = 'rvn-compare-button rvn-compare-button--' + compareMode;
			if ( compareMode === 'text_icon' ) {
				btn.innerHTML = label1 + icon1;
			} else {
				btn.innerHTML = icon1 + label1;
			}
			if ( compareMode === 'hidden' ) {
				btn.style.display = 'none';
			} else {
				btn.style.display = '';
			}
		}

		// Кнопка-счётчик.
		var cnt = stage.querySelector( '.rvn-compare-counter-button' );
		var cntMode = val( 'select[name="es[counter][mode]"]', 'icon_text' );
		var cntSvg = val( 'textarea[name="es[counter][svg]"]', '' );
		var cntText = val( 'input[name="counter_button_text"]', 'Сравнение' );
		var badgePos = val( 'select[name="es[counter][badge_position]"]', 'right' );
		if ( cnt ) {
			var icon2 = ( cntMode === 'icon_text' || cntMode === 'text_icon' || cntMode === 'icon' )
				? '<span class="rvn-compare-button__icon">' + cntSvg + '</span>'
				: '';
			var label2 = ( cntMode === 'icon' || cntMode === 'hidden' ) ? '' : '<span class="rvn-compare-counter-button__label">' + escapeHtml( cntText ) + '</span>';
			var badge2 = '<span class="rvn-compare-counter-button__badge rvn-compare-counter-button__badge--' + badgePos + '">2</span>';
			cnt.className = 'rvn-compare-counter-button rvn-compare-button--' + cntMode;
			if ( cntMode === 'hidden' ) {
				cnt.style.display = 'none';
			} else {
				cnt.style.display = '';
				cnt.innerHTML = ( badgePos !== 'left' )
					? ( cntMode === 'text_icon' ? label2 + icon2 : icon2 + label2 ) + badge2
					: badge2 + ( cntMode === 'text_icon' ? label2 + icon2 : icon2 + label2 );
			}
		}
	}

	function escapeHtml( str ) {
		return String( str ).replace( /[&<>"']/g, function ( ch ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ ch ];
		} );
	}

	/**
	 * Кнопка «Обновить предпросмотр» — ручной вызов перерисовки.
	 */
	function bindElementsPreview( root ) {
		var btn = root.querySelector( '[data-rvn-compare-preview-refresh]' );
		if ( btn ) {
			btn.addEventListener( 'click', function () {
				refreshElementsPreview( root );
			} );
		}
		// Первичная отрисовка.
		refreshElementsPreview( root );
	}

	/**
	 * Перетаскивание элементов списков-конструкторов (группы / core-поля)
	 * без jQuery: drag handle ⠿ двигает элемент выше/ниже.
	 *
	 * @param {Element[]|NodeList} lists Контейнеры [data-rvn-compare-sort].
	 */
	function initSortableLists( lists ) {
		var dragging = null;

		Array.prototype.forEach.call( lists, function ( list ) {
			var items = list.querySelectorAll( ':scope > .rvn-compare-sort__item' );

			Array.prototype.forEach.call( items, function ( item ) {
				var handle = item.querySelector( '.rvn-compare-sort__handle' );
				if ( ! handle ) {
					return;
				}

				// Перетаскивание разрешаем только за ручку.
				handle.addEventListener( 'mousedown', function () {
					item.setAttribute( 'draggable', 'true' );
				} );
				handle.addEventListener( 'mouseup', function () {
					item.removeAttribute( 'draggable' );
				} );

				item.addEventListener( 'dragstart', function ( e ) {
					dragging = item;
					item.classList.add( 'is-dragging' );
					e.dataTransfer.effectAllowed = 'move';
					try {
						e.dataTransfer.setData( 'text/plain', '' );
					} catch ( err ) { /* пусто */ }
				} );

				item.addEventListener( 'dragend', function () {
					item.classList.remove( 'is-dragging' );
					item.removeAttribute( 'draggable' );
					dragging = null;
				} );
			} );

			list.addEventListener( 'dragover', function ( e ) {
				e.preventDefault();
				e.dataTransfer.dropEffect = 'move';

				if ( ! dragging ) {
					return;
				}
				var after = document.elementFromPoint( e.clientX, e.clientY );
				var target = after && after.closest ? after.closest( '.rvn-compare-sort__item' ) : null;
				if ( target && target !== dragging && target.parentNode === list ) {
					var rect = target.getBoundingClientRect();
					var before = ( e.clientY - rect.top ) < ( rect.height / 2 );
					list.insertBefore( dragging, before ? target : target.nextSibling );
				}
			} );

			list.addEventListener( 'drop', function ( e ) {
				e.preventDefault();
			} );
		} );
	}

	/**
	 * UI групп характеристик и групп категорий: удаление (hidden-маркеры),
	 * добавление группы характеристик.
	 */
	function bindFieldGroupsUI() {
		document.addEventListener( 'click', function ( e ) {
			var target = e.target;

			// Удаление группы характеристик.
			var delGroup = target.closest ? target.closest( '[data-del-group]' ) : null;
			if ( delGroup ) {
				e.preventDefault();
				var item = delGroup.closest( '.rvn-compare-sort__item' );
				if ( item ) {
					item.parentNode.removeChild( item );
				}
				return;
			}

			// Добавление группы характеристик.
			var addGroup = target.closest ? target.closest( '[data-add-group]' ) : null;
			if ( addGroup ) {
				e.preventDefault();
				var input = document.getElementById( 'field_groups_new' );
				var list = document.querySelector( '[data-rvn-compare-sort="groups"]' );
				if ( input && input.value && list ) {
					var slug = 'group-' + Date.now().toString( 36 );
					var li = document.createElement( 'li' );
					li.className = 'rvn-compare-sort__item';
					var handle = document.createElement( 'span' );
					handle.className = 'rvn-compare-sort__handle';
					handle.setAttribute( 'aria-hidden', 'true' );
					handle.textContent = '⠿';
					var field = document.createElement( 'input' );
					field.type = 'text';
					field.name = 'field_groups[' + slug + ']';
					field.value = input.value;
					field.className = 'regular-text';
					var del = document.createElement( 'button' );
					del.type = 'button';
					del.className = 'button-link rvn-compare-del-group';
					del.setAttribute( 'data-del-group', slug );
					del.textContent = 'Удалить';
					li.appendChild( handle );
					li.appendChild( field );
					li.appendChild( del );
					list.appendChild( li );
					input.value = '';
				}
				return;
			}

			// Удаление группы категорий: прячем строку и оставляем маркер
			// category_groups_delete[idx]=1 в форме (сабмит сохранит данные).
			var delCat = target.closest ? target.closest( '[data-del-catgroup]' ) : null;
			if ( delCat ) {
				e.preventDefault();
				var idx = delCat.getAttribute( 'data-del-catgroup' );
				var li = delCat.closest( '.rvn-compare-catgroups__item' );
				if ( li ) {
					li.classList.add( 'is-hidden' );
					var flag = li.querySelector( 'input[name="category_groups_delete[' + idx + ']"]' );
					if ( ! flag ) {
						flag = document.createElement( 'input' );
						flag.type = 'hidden';
						flag.name = 'category_groups_delete[' + idx + ']';
						flag.value = '1';
						li.appendChild( flag );
					}
				}
				return;
			}
		} );
	}

	/**
	 * Кнопки «Копировать» на вкладке «Справка» — копирование шорткода.
	 */
	function bindCopyShortcodes() {
		document.addEventListener( 'click', function ( e ) {
			var btn = e.target && e.target.closest ? e.target.closest( '[data-rvn-compare-copy]' ) : null;
			if ( ! btn || btn.tagName === 'CODE' ) {
				return;
			}
			e.preventDefault();
			var text = btn.getAttribute( 'data-rvn-compare-copy' ) || '';
			try {
				navigator.clipboard.writeText( text ).then( function () {
					var original = btn.textContent;
					btn.textContent = 'Скопировано ✓';
					btn.disabled = true;
					setTimeout( function () {
						btn.textContent = original;
						btn.disabled = false;
					}, 1200 );
				} ).catch( function () { /* игнорируем — тихий фолбэк ниже */ } );
			} catch ( err ) { /* старые браузеры */ }
		} );
	}

	/**
	 * Панель «Поля таблицы»: поиск/фильтр + вкл/выкл все (по видимым).
	 */
	function bindFieldsToolbar() {
		var list = document.querySelector( '[data-rvn-fields-list]' );
		if ( ! list ) {
			return;
		}
		var search = list.querySelector( '[data-rvn-fields-search]' );
		var btnAll = list.querySelector( '[data-rvn-fields-all]' );
		var btnNone = list.querySelector( '[data-rvn-fields-none]' );
		var container = list.parentNode;
		var items = container ? container.querySelectorAll( '.rvn-compare-sort--fields .rvn-compare-sort__item' ) : [];

		function applyFilter( query ) {
			var q = ( query || '' ).trim().toLocaleLowerCase();
			for ( var i = 0; i < items.length; i++ ) {
				var label = ( items[ i ].getAttribute( 'data-field-label' ) || '' ).toLocaleLowerCase();
				items[ i ].classList.toggle( 'is-hidden', q !== '' && label.indexOf( q ) === -1 );
			}
		}

		if ( search ) {
			search.addEventListener( 'input', function () {
				applyFilter( search.value );
			} );
		}

		function setVisible( checked ) {
			for ( var i = 0; i < items.length; i++ ) {
				if ( items[ i ].classList.contains( 'is-hidden' ) ) {
					continue;
				}
				var input = items[ i ].querySelector( '.rvn-compare-sort__toggle input[type="checkbox"]' );
				if ( input ) {
					input.checked = checked;
				}
			}
		}

		if ( btnAll ) {
			btnAll.addEventListener( 'click', function () {
				setVisible( true );
			} );
		}
		if ( btnNone ) {
			btnNone.addEventListener( 'click', function () {
				setVisible( false );
			} );
		}
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

		var elems = document.querySelector( '.rvn-compare-elems' );
		if ( elems ) {
			bindElementsPreview( elems );
		}

		var sorts = document.querySelectorAll( '[data-rvn-compare-sort]' );
		if ( sorts.length ) {
			initSortableLists( sorts );
		}

		bindFieldGroupsUI();
		bindCopyShortcodes();
		bindFieldsToolbar();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
