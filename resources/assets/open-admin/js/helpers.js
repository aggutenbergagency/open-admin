/*--------------------------------------------------*/
/* visual */
/*--------------------------------------------------*/

var show = function (list, display) {
	if (typeof (display) === 'undefined') {
		display = "block";
	}
	if (!isNodeList(list)) {
		var list = [list];
	}
	list.forEach(elm => {
		showElm(elm, display);
	});
};
function showElm(elm, display) {
	if (elm.tagName == "TR") {
		elm.style.display = "table-row";
	} else {
		elm.style.display = display;
	}
}

var hide = function (list) {
	if (!isNodeList(list)) {
		var list = [list];
		isNodeList(list)
	}
	list.forEach(elm => {
		elm.style.display = 'none';
	});
};

var toggle = function (list) {
	if (!isNodeList(list)) {
		var list = [list];
	}
	list.forEach(elm => {
		let calculatedStyle = window.getComputedStyle(elm).display;
		if (calculatedStyle === 'block' || calculatedStyle === 'flex' || calculatedStyle === 'table-row') {
			elm.style.display = 'none';
			return;
		}
		showElm(elm);
	});
};

/*--------------------------------------------------*/
/* lang function */
/*--------------------------------------------------*/

var __ = function (trans_string) {
	return admin_lang_arr[trans_string];
}

var trans = __;

/*--------------------------------------------------*/
/* array / object helpers */
/*--------------------------------------------------*/

var merge_default = function (defaults, object, ...rest) {
	return Object.assign({}, defaults, object, ...rest);
}

var arr_remove = function (arr, elem) {
	var indexElement = arr.findIndex(el => el == elem);
	if (indexElement != -1)
		arr.splice(indexElement, 1);
	return arr;
};

var arr_includes = function (arr, elem) {
	var indexElement = arr.findIndex(el => el == elem);
	return (indexElement != -1)
};

/*--------------------------------------------------*/
/* event Handlers & timing */
/*--------------------------------------------------*/

function delegate(selector, handler) {

	return function (event) {
		var targ = event.target;
		do {
			if (targ.matches(selector)) {
				handler.call(targ, event);
			}
		} while ((targ = targ.parentNode) && targ != event.currentTarget);
	}
}


function debounce(func, timeout = 300) {
	let timer;
	return (...args) => {
		clearTimeout(timer);
		timer = setTimeout(() => { func.apply(this, args); }, timeout);
	};
}

/*--------------------------------------------------*/
/* html elements */
/*--------------------------------------------------*/

function getOuterHeigt(el) {
	// Get the DOM Node if you pass in a string
	el = (typeof el === 'string') ? document.querySelector(el) : el;

	var styles = window.getComputedStyle(el);
	var margin = parseFloat(styles['marginTop']) +
		parseFloat(styles['marginBottom']);

	return Math.ceil(el.offsetHeight + margin);
}

function isNodeList(nodes) {
	var stringRepr = Object.prototype.toString.call(nodes);

	return typeof nodes === 'object' &&
		/^\[object (HTMLCollection|NodeList|Object)\]$/.test(stringRepr) &&
		(typeof nodes.length === 'number') &&
		(nodes.length === 0 || (typeof nodes[0] === "object" && nodes[0].nodeType > 0));
}

/**
 * @param {String} HTML representing a single element
 * @return {Element}
 */
function htmlToElement(html) {
	var template = document.createElement('template');
	html = html.trim(); // Never return a text node of whitespace as the result
	template.innerHTML = html;
	return template.content.firstChild;
}

/**
 * @param {String} HTML representing any number of sibling elements
 * @return {NodeList}
 */
function htmlToElements(html) {
	var template = document.createElement('template');
	template.innerHTML = html;
	return template.content.childNodes;
}


/*--------------------------------------------------*/
/* cookie helpers*/
/*--------------------------------------------------*/

function setCookie(name, value, days) {
	var expires = "";
	if (days) {
		var date = new Date();
		date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
		expires = "; expires=" + date.toUTCString();
	}
	document.cookie = name + "=" + (value || "") + expires + "; path=/";
}
function getCookie(name) {
	var nameEQ = name + "=";
	var ca = document.cookie.split(';');
	for (var i = 0; i < ca.length; i++) {
		var c = ca[i];
		while (c.charAt(0) == ' ') c = c.substring(1, c.length);
		if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length, c.length);
	}
	return null;
}
function eraseCookie(name) {
	document.cookie = name + '=; Path=/; Expires=Thu, 01 Jan 1970 00:00:01 GMT;';
}
