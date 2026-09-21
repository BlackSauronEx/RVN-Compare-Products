/**
 * RVN Compare — фронтенд (ванильный JS, без jQuery).
 *
 * Единый неймспейс window.rvnCompare. Событийная модель: ядро хранилища
 * шлёт события (list_updated и т.д.), а кнопки/счётчик/таблица слушают
 * их и обновляются независимо (R4-09, §10 живого ТЗ).
 *
 * Хранение:
 *  - гость: localStorage['rvn_compare'] = { items: [], meta: {} };
 *  - авторизованный: сервер (REST) + зеркало в localStorage.
 */
( function () {
	'use strict';

	var LS_KEY = 'rvn_compare';
	var CFG = window.rvnCompare || {};
	var STATE = window.rvnCompareState || { items: [] };

	var items = [];
	var meta = {};

	/**
	 * Прочитать список из localStorage с tolerance к legacy-формату (обычный массив).
	 */
	function readLS() {
		var raw = null;
		try {
			raw = window.localStorage.getItem( LS_KEY );
		} catch ( e ) {
			raw = null;
		}
		if ( ! raw ) {
			return { items: [], meta: {} };
		}
		try {
			var parsed = JSON.parse( raw );
			if ( Array.isArray( parsed ) ) {
				// legacy: был просто массив ID
				return { items: parsed, meta: {} };
			}
			return {
				items: Array.isArray( parsed.items ) ? parsed.items : [],
				meta: parsed.meta && typeof parsed.meta === 'object' ? parsed.meta : {}
			};
		} catch ( e ) {
			return { items: [], meta: {} };
		}
	}

	/**
	 * Записать список в localStorage (гость) или зеркало (авторизованный).
	 */
	function writeLS( list, newMeta ) {
		var data = { items: list, meta: newMeta || meta };
		try {
			window.localStorage.setItem( LS_KEY, JSON.stringify( data ) );
		} catch ( e ) {
			/* localStorage может быть недоступен (приватный режим) — молча игнорируем */
		}
		items = list;
		meta = data.meta;
	}

	/**
	 * Отправить событие изменения списка; все слушатели обновляются сами.
	 */
	function emit( name, detail ) {
		if ( typeof window.CustomEvent === 'function' ) {
			window.dispatchEvent( new CustomEvent( name, { detail: detail || {} } ) );
		}
	}

	/**
	 * Обновить все элементы интерфейса по текущему состоянию.
	 */
	function refreshUI() {
		var count = items.length;

		// Живые счётчики.
		var counters = document.querySelectorAll( '[data-rvn-compare-count]' );
		for ( var c = 0; c < counters.length; c++ ) {
			counters[ c ].textContent = String( count );
		}

		// Кнопки «Сравнить»/«Уже в сравнении».
		var buttons = document.querySelectorAll( '[data-rvn-compare-add]' );
		for ( var b = 0; b < buttons.length; b++ ) {
			var btn = buttons[ b ];
			var id = parseInt( btn.getAttribute( 'data-rvn-compare-add' ), 10 );
			var added = items.indexOf( id ) !== -1;
			btn.setAttribute( 'data-rvn-compare-state', added ? 'added' : '' );
			btn.classList.toggle( 'is-added', added );

			var label = btn.querySelector( '.rvn-compare-button__label' );
			if ( label ) {
				label.textContent = added ? ( CFG.i18n && CFG.i18n.addedLabel ? CFG.i18n.addedLabel : 'Уже в сравнении' )
					: ( CFG.i18n && CFG.i18n.buttonLabel ? CFG.i18n.buttonLabel : 'Сравнить' );
			}
		}

		emit( 'rvn_compare:list_updated', { count: count, items: items.slice() } );
	}

	/**
	 * Возвращает настройки тоста (по типу), слитые с базовым дефолтом.
	 */
	function toastCfg( kind ) {
		var base = {
			positionDesktop: 'bottom-center',
			positionMobile: 'bottom-center',
			progress: true,
			icon: '',
			iconPosition: 'left'
		};
		var got = ( CFG.toasts && CFG.toasts[ kind ] ) ? CFG.toasts[ kind ] : {};
		for ( var k in base ) {
			if ( ! base.hasOwnProperty( k ) ) {
				continue;
			}
			if ( typeof got[ k ] === 'undefined' ) {
				got[ k ] = base[ k ];
			}
		}
		return got;
	}

	/**
	 * Показать всплывающее уведомление (toast).
	 *
	 * @param {string} message Текст.
	 * @param {string} kind    added | removed | cleared | limit.
	 */
	function toast( message, kind ) {
		if ( ! message ) {
			// Пустое сообщение = не показывать (R4-04).
			return;
		}
		kind = kind || 'added';
		var cfg = toastCfg( kind );

		var node = document.createElement( 'div' );
		node.className = 'rvn-compare-toast';
		node.setAttribute( 'data-kind', kind );
		node.setAttribute( 'role', 'status' );

		// Позиция (десктоп vs мобайл) через data-атрибуты, CSS применяет.
		node.setAttribute( 'data-position', cfg.positionDesktop );
		node.setAttribute( 'data-position-mobile', cfg.positionMobile );

		if ( cfg.icon ) {
			var icon = document.createElement( 'span' );
			icon.className = 'rvn-compare-toast__icon rvn-compare-toast__icon--' + cfg.iconPosition;
			icon.innerHTML = cfg.icon; // SVG из санитайзера на сервере.
			node.appendChild( icon );
		}

		var text = document.createElement( 'span' );
		text.className = 'rvn-compare-toast__text';
		text.textContent = message;
		node.appendChild( text );

		var bar = null;
		if ( cfg.progress ) {
			bar = document.createElement( 'span' );
			bar.className = 'rvn-compare-toast__bar';
			node.appendChild( bar );
		}

		document.body.appendChild( node );

		// Принудительный reflow для проигрывания анимации появления.
		void node.offsetWidth;
		node.classList.add( 'is-visible' );

		var hideMs = parseInt( CFG.toastMs, 10 ) || 3200;
		if ( bar ) {
			bar.style.animationDuration = hideMs + 'ms';
		}
		window.setTimeout( function () {
			node.classList.remove( 'is-visible' );
			window.setTimeout( function () {
				if ( node.parentNode ) {
					node.parentNode.removeChild( node );
				}
			}, 320 );
		}, hideMs );
	}

	/**
	 * REST-запрос к серверу (для авторизованных пользователей).
	 */
	function api( route, data ) {
		return window.fetch( CFG.restUrl + route, {
			method: data ? 'POST' : 'GET',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': CFG.nonce || ''
			},
			body: data ? JSON.stringify( data ) : undefined
		} ).then( function ( r ) {
			return r.json().then( function ( json ) {
				if ( ! r.ok ) {
					throw new Error( json && json.message ? json.message : 'Request failed' );
				}
				return json;
			} );
		} );
	}

	/**
	 * Добавить или убрать товар (toggle).
	 */
	function toggle( id ) {
		id = parseInt( id, 10 );
		if ( ! id ) {
			return;
		}

		var added = items.indexOf( id ) !== -1;

		// Убираем из локального состояния сразу (оптимистично).
		var next = items.slice();
		if ( added ) {
			next = next.filter( function ( x ) { return x !== id; } );
		} else {
			if ( CFG.limits && CFG.limits.total && next.length >= CFG.limits.total ) {
				toast( CFG.i18n && CFG.i18n.limit ? CFG.i18n.limit : 'Достигнут максимум товаров в сравнении', 'limit' );
				return;
			}
			next.push( id );
		}

		if ( CFG.loggedIn ) {
			api( 'toggle', { product_id: id, action: added ? 'remove' : 'add' } )
				.then( function ( res ) {
					var data = res && res.data ? res.data : {};
					writeLS( data.items || next, meta );
					refreshUI();
					toast( added ? ( CFG.i18n.removed || '' ) : ( data.reason === 'limit' ? ( CFG.i18n.limit || '' ) : ( CFG.i18n.added || '' ) ), data.reason === 'limit' ? 'limit' : ( added ? 'removed' : 'added' ) );
				} )
				.catch( function () {
					// Откат при ошибке сети.
					writeLS( items, meta );
					refreshUI();
				} );
		} else {
			writeLS( next, meta );
			refreshUI();
			toast( added ? ( CFG.i18n.removed || '' ) : ( CFG.i18n.added || '' ), added ? 'removed' : 'added' );
		}
	}

	/**
	 * Очистить список целиком (с подтверждением, если требуется).
	 */
	function clearAll( el ) {
		var noConfirm = el && el.hasAttribute( 'data-rvn-compare-no-confirm' );
		if ( ! noConfirm && ! window.confirm( CFG.i18n && CFG.i18n.clearConfirm ? CFG.i18n.clearConfirm : 'Очистить список сравнения?' ) ) {
			return;
		}

		if ( CFG.loggedIn ) {
			api( 'clear', {} ).then( function ( res ) {
				var data = res && res.data ? res.data : {};
				writeLS( data.items || [], meta );
				refreshUI();
				toast( CFG.i18n.cleared || '', 'cleared' );
			} ).catch( function () {
				refreshUI();
			} );
		} else {
			writeLS( [], meta );
			refreshUI();
			toast( CFG.i18n.cleared || '', 'cleared' );
		}
	}

	/**
	 * Слить гостевой список после логина (если ещё не сделано).
	 */
	function maybeMerge() {
		if ( ! CFG.loggedIn ) {
			return;
		}
		var local = readLS();
		if ( local.items.length && local.meta && local.meta.merged === '1' ) {
			return;
		}
		if ( ! local.items.length ) {
			writeLS( STATE.items, { merged: '1' } );
			return;
		}
		api( 'merge', { items: local.items } ).then( function ( res ) {
			var data = res && res.data ? res.data : {};
			writeLS( data.items || local.items, { merged: '1' } );
			refreshUI();
		} ).catch( function () {
			refreshUI();
		} );
	}

	/**
	 * Подгружает HTML вкладки таблицы по REST (/table).
	 */
	function fetchTabHTML( tab ) {
		var ids = items.map( function ( id ) { return Number( id ); } ).join( ',' );
		var url = CFG.restUrl + 'table?tab=' + encodeURIComponent( tab || '' ) + '&ids=' + encodeURIComponent( ids );

		return window.fetch( url, {
			credentials: 'same-origin',
			headers: { 'X-WP-Nonce': CFG.nonce || '' }
		} ).then( function ( r ) { return r.json(); } ).then( function ( json ) {
			if ( ! json || ! json.success ) {
				throw new Error( 'table request failed' );
			}
			return json.data || {};
		} );
	}

	/**
	 * Вычисляет число видимых колонок по текущей ширине окна.
	 */
	function visibleColumns( scroller ) {
		var w = window.innerWidth || document.documentElement.clientWidth;
		var bp = CFG.breakpoints || { tablet: 1024, mobile: 768 };
		var cols = CFG.columns || { desktop: 5, tablet: 3, mobile: 2 };

		if ( w <= bp.mobile ) {
			return parseInt( cols.mobile, 10 ) || 2;
		}
		if ( w <= bp.tablet ) {
			return parseInt( cols.tablet, 10 ) || 3;
		}
		return parseInt( cols.desktop, 10 ) || 5;
	}

	/**
	 * Синхронизирует ширину колонок и позицию плавающей панели со слайдером.
	 */
	function syncScroller( scroller ) {
		var n = visibleColumns( scroller );
		var clip = scroller.querySelector( '[data-rvn-compare-clip]' );
		var colEls = scroller.querySelectorAll( '[data-rvn-compare-col]' );

		if ( ! clip ) {
			return;
		}

		// Ширина колонки = клиентская ширина клипа / N (фиксированно, R2-02).
		var width = Math.floor( clip.clientWidth / n );

		for ( var i = 0; i < colEls.length; i++ ) {
			colEls[ i ].style.width = width + 'px';
			colEls[ i ].style.minWidth = width + 'px';
			colEls[ i ].style.flex = '0 0 ' + width + 'px';
		}

		// Ячейки значений в рядах — той же ширины, что и колонки шапки.
		var valueEls = scroller.querySelectorAll( '.rvn-compare-row__value' );
		for ( var v = 0; v < valueEls.length; v++ ) {
			valueEls[ v ].style.width = width + 'px';
			valueEls[ v ].style.minWidth = width + 'px';
			valueEls[ v ].style.flex = '0 0 ' + width + 'px';
		}

		// Подписи рядов — по ширине уголка шапки (сохраняем выравнивание).
		var corner = scroller.querySelector( '.rvn-compare-corner' );
		if ( corner ) {
			var labelW = corner.getBoundingClientRect().width;
			var labelEls = scroller.querySelectorAll( '.rvn-compare-row__label' );
			for ( var l = 0; l < labelEls.length; l++ ) {
				labelEls[ l ].style.width = labelW + 'px';
				labelEls[ l ].style.minWidth = labelW + 'px';
				labelEls[ l ].style.flex = '0 0 ' + labelW + 'px';
			}
		}

		// Навешиваем ширину колонкам плавающей панели, если она есть.
		var fpCols = document.querySelectorAll( '.rvn-compare-floating__col' );
		for ( var f = 0; f < fpCols.length; f++ ) {
			fpCols[ f ].style.width = width + 'px';
			fpCols[ f ].style.minWidth = width + 'px';
			fpCols[ f ].style.flex = '0 0 ' + width + 'px';
		}
	}

	/**
	 * Хранит состояние плавающей панели (создаётся лениво при первом скролле).
	 */
	var floating = null;

	/**
	 * Создаёт плавающую панель — компактный клон шапки таблицы.
	 */
	function initFloating( scroller ) {
		var header = scroller.querySelector( '[data-rvn-compare-header]' );
		var wrapper = document.createElement( 'div' );
		wrapper.className = 'rvn-compare-floating';
		wrapper.setAttribute( 'data-rvn-compare-floating', '1' );
		wrapper.setAttribute( 'aria-hidden', 'true' );

		var cols = header.querySelectorAll( '[data-rvn-compare-col]' );
		var row = document.createElement( 'div' );
		row.className = 'rvn-compare-floating__row';

		for ( var i = 0; i < cols.length; i++ ) {
			var clone = cols[ i ].cloneNode( true );
			clone.classList.add( 'rvn-compare-floating__col' );
			clone.removeAttribute( 'data-rvn-compare-col' );

			// «Купить» в плавающей панели: кнопка кладётся на сервере в
			// скрытом слоте data-buy-slot="floating" (настройка buy_floating);
			// при empty (режим hidden) показываем фото товара.
			var dst = clone.querySelector( '[data-buy-slot="floating"]' );
			if ( dst && ! dst.querySelector( '.rvn-compare-buy' ) ) {
				dst.classList.add( 'is-empty' );
				var thumb = clone.querySelector( '.rvn-compare-col__thumb' );
				if ( thumb ) {
					thumb.classList.add( 'is-shown' );
				}
			}

			row.appendChild( clone );
		}

		wrapper.appendChild( row );
		document.body.appendChild( wrapper );

		floating = {
			el: wrapper,
			row: row,
			headerTop: 0,
			active: false,
			left: 0
		};
	}

	/**
	 * Обновляет состояние плавающей панели при скролле (throttle через rAF).
	 */
	function updateFloating( scroller ) {
		if ( ! floating ) {
			initFloating( scroller );
		}

		var header = scroller.querySelector( '[data-rvn-compare-header]' );
		if ( ! header ) {
			return;
		}

		var rect = header.getBoundingClientRect();
		var offset = parseInt( CFG.floatingOffset, 10 ) || 0;
		var show = rect.bottom < offset;

		floating.active = show;
		if ( show ) {
			floating.el.style.top = offset + 'px';
			floating.el.classList.add( 'is-visible' );
			floating.el.style.transform = 'translateX(' + ( -currentIndex( scroller ) * colWidth( scroller ) ) + 'px)';
		} else {
			floating.el.classList.remove( 'is-visible' );
		}
	}

	/**
	 * Текущий индекс (позиция) слайдера.
	 */
	function currentIndex( scroller ) {
		var clip = scroller.querySelector( '[data-rvn-compare-clip]' );
		if ( ! clip ) {
			return 0;
		}
		return Math.round( clip.scrollLeft / colWidth( scroller ) );
	}

	/**
	 * Ширина одной колонки слайдера (эмпирически из DOM).
	 */
	function colWidth( scroller ) {
		var col = scroller.querySelector( '[data-rvn-compare-col]' );
		return col ? col.getBoundingClientRect().width : 0;
	}

	/**
	 * Прокрутка слайдера на один шаг (вперёд/назад).
	 */
	function stepScroller( scroller, dir ) {
		var clip = scroller.querySelector( '[data-rvn-compare-clip]' );
		if ( ! clip ) {
			return;
		}

		var w = colWidth( scroller );
		var index = currentIndex( scroller );
		var maxIndex = Math.max( 0, clip.scrollWidth - clip.clientWidth );

		var target = dir === 'next' ? index + 1 : index - 1;
		target = Math.max( 0, Math.min( target, Math.floor( maxIndex / w ) ) );
		var px = target * w;

		if ( CFG.animationMs && parseInt( CFG.animationMs, 10 ) > 0 ) {
			clip.scrollTo( { left: px, behavior: 'smooth' } );
		} else {
			clip.scrollLeft = px;
		}
		updateArrows( scroller );
	}

	/**
	 * Блокирует стрелки по краям прокрутки.
	 */
	function updateArrows( scroller ) {
		var clip = scroller.querySelector( '[data-rvn-compare-clip]' );
		if ( ! clip ) {
			return;
		}

		var prev = scroller.querySelector( '[data-rvn-compare-arrow="prev"]' );
		var next = scroller.querySelector( '[data-rvn-compare-arrow="next"]' );
		var max = clip.scrollWidth - clip.clientWidth;

		if ( prev ) {
			prev.disabled = clip.scrollLeft <= 1;
		}
		if ( next ) {
			next.disabled = clip.scrollLeft >= max - 1;
		}
	}

	/**
	 * Привязывает интерактив таблицы: вкладки, «Только различия», группы, слайдер.
	 */
	function initTableParts( root ) {
		if ( ! root ) {
			return;
		}

		// «Только различия».
		var diff = root.querySelector( '[data-rvn-compare-only-diff]' );
		if ( diff ) {
			var applyDiff = function () {
				var rows = root.querySelectorAll( '[data-rvn-compare-row]' );
				for ( var r = 0; r < rows.length; r++ ) {
					if ( diff.checked ) {
						rows[ r ].style.display = rows[ r ].classList.contains( 'has-diff' ) ? '' : 'none';
					} else {
						rows[ r ].style.display = '';
					}
				}
			};
			diff.addEventListener( 'change', applyDiff );
			applyDiff();
		}

		// Сворачивание групп.
		arrayForEach( root.querySelectorAll( '[data-rvn-compare-group]' ), function ( group ) {
			var head = group.querySelector( '[data-rvn-compare-group-toggle]' );
			if ( head ) {
				head.addEventListener( 'click', function () {
					group.classList.toggle( 'is-collapsed' );
				} );
			}
		} );

		// Слайдер.
		var scroller = root.querySelector( '[data-rvn-compare-scroller]' );
		if ( scroller ) {
			syncScroller( scroller );
			updateArrows( scroller );

			var prev = scroller.querySelector( '[data-rvn-compare-arrow="prev"]' );
			var next = scroller.querySelector( '[data-rvn-compare-arrow="next"]' );
			var clip = scroller.querySelector( '[data-rvn-compare-clip]' );

			if ( prev ) {
				prev.addEventListener( 'click', function () {
					stepScroller( scroller, 'prev' );
				} );
			}
			if ( next ) {
				next.addEventListener( 'click', function () {
					stepScroller( scroller, 'next' );
				} );
			}

			if ( clip ) {
				clip.addEventListener( 'scroll', function () {
					updateArrows( scroller );
					if ( floating && floating.active ) {
						floating.el.style.transform = 'translateX(' + ( -clip.scrollLeft ) + 'px)';
					}
				}, { passive: true } );
			}

			if ( 'ResizeObserver' in window ) {
				var ro = new ResizeObserver( function () {
					syncScroller( scroller );
					updateArrows( scroller );
				} );
				ro.observe( clip );
			} else {
				window.addEventListener( 'resize', function () {
					syncScroller( scroller );
					updateArrows( scroller );
				} );
			}

			// Плавающая панель — скролл страницы.
			window.addEventListener( 'scroll', function () {
				if ( scroller && scroller.offsetParent !== null ) {
					updateFloating( scroller );
				}
			}, { passive: true } );
		}
	}

	function initTable( scope ) {
		var table = scope && scope.querySelector ? scope.querySelector( '.rvn-compare-table' ) : null;
		if ( ! table ) {
			return;
		}

		var tabsWrap = table.querySelector( '[data-rvn-compare-tabs]' );
		var viewport = table.querySelector( '[data-rvn-compare-viewport]' );

		if ( tabsWrap ) {
			tabsWrap.addEventListener( 'click', function ( e ) {
				var btn = e.target && e.target.closest ? e.target.closest( '[data-rvn-compare-tab]' ) : null;
				if ( ! btn ) {
					return;
				}

				var tab = btn.getAttribute( 'data-rvn-compare-tab' );
				var allBtns = tabsWrap.querySelectorAll( '[data-rvn-compare-tab]' );
				for ( var i = 0; i < allBtns.length; i++ ) {
					allBtns[ i ].classList.toggle( 'is-active', allBtns[ i ] === btn );
				}

				if ( ! viewport ) {
					return;
				}
				viewport.classList.add( 'is-loading' );
				fetchTabHTML( tab ).then( function ( data ) {
					viewport.innerHTML = data.html || '';
					viewport.classList.remove( 'is-loading' );
					initTableParts( viewport );
				} ).catch( function () {
					viewport.classList.remove( 'is-loading' );
				} );
			} );
		}

		initTableParts( table );
	}

	/**
	 * Мини-хелпер forEach для NodeList.
	 */
	function arrayForEach( list, cb ) {
		for ( var i = 0; i < list.length; i++ ) {
			cb( list[ i ], i );
		}
	}

	/**
	 * Ре-рендер таблицы после изменения списка (удаление/добавление).
	 */
	function onListUpdated() {
		var table = document.querySelector( '.rvn-compare-table' );
		if ( ! table ) {
			return;
		}

		var tabsWrap = table.querySelector( '[data-rvn-compare-tabs]' );
		var activeBtn = tabsWrap ? tabsWrap.querySelector( '[data-rvn-compare-tab].is-active' ) : null;
		var tab = activeBtn ? activeBtn.getAttribute( 'data-rvn-compare-tab' ) : ( table.getAttribute( 'data-active' ) || '' );
		var viewport = table.querySelector( '[data-rvn-compare-viewport]' );

		if ( viewport ) {
			viewport.classList.add( 'is-loading' );
		}
		fetchTabHTML( tab ).then( function ( data ) {
			if ( ! viewport ) { return; }
			viewport.innerHTML = data.html || '';
			viewport.classList.remove( 'is-loading' );
			initTableParts( viewport );
		} ).catch( function () {
			if ( viewport ) {
				viewport.classList.remove( 'is-loading' );
			}
		} );
	}

	/**
	 * Инициализация фронтенда.
	 */
	function init() {
		if ( CFG.loggedIn ) {
			// Авторизованный: сервер — источник правды, LS — зеркало.
			items = ( STATE.items && Array.isArray( STATE.items ) ) ? STATE.items.slice() : [];
			meta = { merged: '1' };
			maybeMerge();
		} else {
			var local = readLS();
			items = local.items.slice();
			meta = local.meta;
		}

		refreshUI();

		// Событийное делегирование для кнопок и очистки.
		document.addEventListener( 'click', function ( e ) {
			var target = e.target;

			var addBtn = target.closest ? target.closest( '[data-rvn-compare-add]' ) : null;
			if ( addBtn ) {
				e.preventDefault();
				toggle( addBtn.getAttribute( 'data-rvn-compare-add' ) );
				return;
			}

			var clearBtn = target.closest ? target.closest( '[data-rvn-compare-clear]' ) : null;
			if ( clearBtn ) {
				e.preventDefault();
				clearAll( clearBtn );
			}
		} );

		// Кросс-вкладочная синхронизация: событие storage.
		window.addEventListener( 'storage', function ( e ) {
			if ( e.key === LS_KEY ) {
				var fresh = readLS();
				items = fresh.items.slice();
				meta = fresh.meta;
				refreshUI();
			}
		} );

		// Досинка состояния при возврате фокуса во вкладку (R4-05).
		document.addEventListener( 'visibilitychange', function () {
			if ( document.visibilityState === 'visible' ) {
				var fresh = readLS();
				// Для гостя LS и есть источник; для залогиненного — зеркало.
				items = fresh.items.slice();
				meta = fresh.meta;
				refreshUI();
			}
		} );

		// Таблица сравнения: вкладки, «Только различия», группы, ре-рендер.
		initTable( document );
		window.addEventListener( 'rvn_compare:list_updated', onListUpdated );
	}

	// Публичный неймспейс.
	window.rvnCompare = {
		items: function () { return items.slice(); },
		toast: toast,
		refresh: refreshUI
	};

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
