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
	 * Показать всплывающее уведомление (toast).
	 */
	function toast( message ) {
		if ( ! message ) {
			return;
		}
		var node = document.createElement( 'div' );
		node.className = 'rvn-compare-toast';
		node.setAttribute( 'role', 'status' );
		node.textContent = message;
		if ( CFG.accent ) {
			node.style.borderLeftColor = CFG.accent;
		}
		document.body.appendChild( node );

		// Принудительный reflow для проигрывания анимации появления.
		void node.offsetWidth;
		node.classList.add( 'is-visible' );

		var hideMs = parseInt( CFG.toastMs, 10 ) || 3200;
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
				toast( CFG.i18n && CFG.i18n.limit ? CFG.i18n.limit : 'Достигнут максимум товаров в сравнении' );
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
					toast( added ? ( CFG.i18n.removed || '' ) : ( data.reason === 'limit' ? ( CFG.i18n.limit || '' ) : ( CFG.i18n.added || '' ) ) );
				} )
				.catch( function () {
					// Откат при ошибке сети.
					writeLS( items, meta );
					refreshUI();
				} );
		} else {
			writeLS( next, meta );
			refreshUI();
			toast( added ? ( CFG.i18n.removed || '' ) : ( CFG.i18n.added || '' ) );
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
				toast( CFG.i18n.cleared || '' );
			} ).catch( function () {
				refreshUI();
			} );
		} else {
			writeLS( [], meta );
			refreshUI();
			toast( CFG.i18n.cleared || '' );
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
