var TriggMineApi = {
	version: '1.0.5',
	pingInterval: 30000, // 30 seconds
	keyCartPingTime: 'triggmine[CartPingTime]',
	keyCartId: 'triggmine[CartId]',
	xhr: null,
	lastEmail: null,
	emailRegExp: "[a-z0-9!#$%&'*+/=?^_`{|}~-]+(?:\\.[a-z0-9!#$%&'*+/=?^_`{|}~-]+)*@(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9]))+?\\.[a-zA-Z]{2,6}$",

	init: function () {
		this.createXhr();
		this.pingCart();
		this.waitingEmail();
	},
	createXhr: function () {
		var f = window.ActiveXObject !== undefined ?
			function () {
				return TriggMineApi.createStandardXHR() || TriggMineApi.createActiveXHR();
			} :
			this.createStandardXHR;

		this.xhr = f();
	},
	createStandardXHR: function () {
		try {
			return new window.XMLHttpRequest();
		} catch (e) {
		}
	},
	createActiveXHR: function () {
		try {
			return new window.ActiveXObject("Microsoft.XMLHTTP");
		} catch (e) {
		}
	},
	getCookie: function (cname) {
		var name = cname + "=";
		var ca = document.cookie.split(';');
		for (var i = 0; i < ca.length; i++) {
			var c = ca[i];
			while (c.charAt(0) == ' ') c = c.substring(1);
			if (c.indexOf(name) != -1) return c.substring(name.length, c.length);
		}
		return "";
	},
	time: function () {
		return Math.floor(new Date().getTime() / 1000);
	},
	pingCart: function () {
		var timePassed = this.time() - this.getCookie(this.keyCartPingTime);
		var timeout = timePassed > this.pingInterval / 1000;
		var cartId = this.getCookie(this.keyCartId);
		if (timeout && cartId) {
			this.xhr.open('GET', '/?triggmine_async=1&_action=onPingCart' + this.getSalt(), true);
			this.xhr.send();
		}
		setTimeout('TriggMineApi.pingCart()', this.pingInterval);
	},
	waitingEmail: function () {
		var inputs, index;

		if (document.querySelectorAll) {
			inputs = document.querySelectorAll('input[type=text], input[type=email]');
		}
		else {
			inputs = [];
			var unfiltered = document.getElementsByTagName("input"),
				i = unfiltered.length,
				input;
			while (i--) {
				input = unfiltered[i];
				if (!input.type || input.type === 'text' || input.type === 'email') {
					inputs.push(input);
				}
			}
		}

		for (index = 0; index < inputs.length; ++index) {
			inputs[index].oninput = function () {
				var value = this.value;
				var pattern = new RegExp(TriggMineApi.emailRegExp);

				if (value != TriggMineApi.lastEmail && pattern.test(value)) {
					TriggMineApi.updateBuyerEmail(value);
					TriggMineApi.lastEmail = value;
				}
			}
		}
	},
	updateBuyerEmail: function (email) {
		var mail = '{"BuyerEmail":"' + email + '"}';
		this.xhr.open('GET', '/?triggmine_async=1&_action=onUpdateBuyerEmail&Data=' + encodeURIComponent(mail) + this.getSalt(), true);
		this.xhr.send();
	},
	getSalt: function () {
		return '&salt=' + this.str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ').substr(0, 8);
	},
	str_shuffle: function (str) {
		if (arguments.length === 0) {
			throw 'Wrong parameter count for str_shuffle()';
		}

		if (str == null) {
			return '';
		}

		str += '';

		var newStr = '',
			rand, i = str.length;

		while (i) {
			rand = Math.floor(Math.random() * i);
			newStr += str.charAt(rand);
			str = str.substring(0, rand) + str.substr(rand + 1);
			i--;
		}

		return newStr;
	}
};
// init TriggMineApi javascript
TriggMineApi.init();