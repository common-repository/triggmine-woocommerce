<?php
	include dirname(__FILE__) . '/ApiClient.php';
	include dirname(__FILE__) . '/Helper.php';
	include dirname(__FILE__) . '/Error.php';

	abstract class TriggMine_Core
	{
		const VERSION = '1.0.8';

		/**
		 * Keys to be used in code.
		 */
		const KEY_CART_PING_TIME = 'CartPingTime';
		const KEY_TRIGGMINE = 'triggmine';
		const KEY_TRIGGMINE_ASYNC = 'triggmine_async';
		const KEY_TRIGGMINE_VERSION = 'triggmine_version';
		const KEY_TRIGGMINE_EXPORT = 'triggmine_export';

		const SETTING_IS_ON = 'triggmine_is_on';
		const SETTING_REST_API = 'triggmine_rest_api';
		const SETTING_TOKEN = 'triggmine_token';

		const CART_PING_INTERVAL = 60; // seconds

		const MSG_IMPLEMENT_IN_INTEGRATOR = 'Method has to be implemented in the Integrator class.';

		/**
		 * State flag: handling events at the moment.
		 * @var bool
		 */
		protected $_handleEvents = true;

		/**
		 * Plugin setting: is plugin active.
		 * @var bool
		 */
		protected $_isOn = null;

		/**
		 * Is it an asynchronous request.
		 * @var bool
		 */
		protected $_isAsync = false;
		/**
		 * @var string
		 */
		protected $_buyerId = null;
		/**
		 * @var string
		 */
		protected $_cartId = null;
		/**
		 * @var string
		 */
		protected $_redirectId = null;
		/**
		 * @var string
		 */
		protected $_visitorId = null;
		/**
		 * API client instance.
		 * @var TriggMine_ApiClient
		 */
		private $_api = null;
		/**
		 * Remembers whether API was called in current request already.
		 * @var bool
		 */
		private $_apiCalled = false;
		/**
		 * Remembers whether Api was called visitor request
		 * @var bool
		 */
		private $_visitorCalled = false;
		/**
		 * @var string
		 */
		private $_conversionId;
		/**
		 * Log in file flag
		 * @var bool
		 */
		protected $_logInFile = false;


		public function __construct()
		{
			if ($this->_getGetValue(self::KEY_TRIGGMINE_VERSION) !== null) {
				echo $this->getVersion();
				exit;
			}

			$this->_isOn = $this->_getSettingValue(self::SETTING_IS_ON);
			$this->_token = $this->_getSettingValue(self::SETTING_TOKEN);
			$this->_restApi = $this->_getSettingValue(self::SETTING_REST_API);
			$this->_isAsync = ($this->_getGetValue(self::KEY_TRIGGMINE_ASYNC) !== null);



			// Initializing API client
			$this->_api = new TriggMine_ApiClient($this);

			if ($this->_isFictiveRequest()) {
				$this->_isOn = false;
			}

			$this->_processAsyncRequest();

			$this->registerJavaScriptFile('/core/js/api.js');
		}

		/**
		 * Encapsulates the work with $_GET array.
		 *
		 * @param string $key Key from $_GET array.
		 * @param string $default Default value if not found.
		 *
		 * @return string False if failed.
		 */
		protected function _getGetValue($key, $default = null)
		{
			$value = filter_input(INPUT_GET, $key, FILTER_SANITIZE_STRING);

			return $value !== null ? $value : $default;
		}

		/**
		 * Returns HTML string with versions info.
		 * @return string
		 */
		final public function getVersion()
		{
			return 'Agent: ' . $this->getAgent() . ' ' . $this->getAgentVersion() . '<br/>'
			. 'Core version: ' . self::VERSION . '<br/>'
			. 'Integrator version: ' . constant(get_class($this) . '::VERSION') . '<br/>'
			. 'ApiClient version: ' . TriggMine_ApiClient::VERSION;
		}

		/**
		 * Returns a name of your CMS / eCommerce platform.
		 * @return string Agent name.
		 */
		abstract public function getAgent();

		/**
		 * Returns a version of your CMS / eCommerce platform.
		 * @return string Version.
		 */
		abstract public function getAgentVersion();

		/**
		 * Returns a value of the setting having given name.
		 *
		 * @param string $key Setting name.
		 *
		 * @return string Setting value.
		 */
		abstract protected function _getSettingValue($key);

		final function getOrders()
		{
			$input = file_get_contents('php://input');
			$data = json_decode($input);
			if ($data->Method === 'GetHistory' && $data->Token === $this->_token) {
				header('Content-Type: application/json');
				$response = json_encode($this->exportOrders($data->Data));

				return $response;
			}
		}

		/**
		 * Returns a json string of orders
		 *
		 * @param $data
		 *
		 * @return string Json
		 */
		abstract public function exportOrders($data);

		/**
		 * Tells whether current request is fictive, i.e. a bot request etc.
		 * @return bool
		 */
		protected function _isFictiveRequest()
		{
			return TriggMine_Helper::isRobotRequest();
		}

		/**
		 * Processes asynchronous requests.
		 */
		private function _processAsyncRequest()
		{
			if ($this->_getGetValue(self::KEY_TRIGGMINE_ASYNC) === null) {
				return;
			}

			$action = $this->_getGetValue('_action');
			if ($action) {
				$data = filter_input(INPUT_GET, 'Data');
				$this->_request(array(
					'_action' => $action,
					'Data'    => $data ? json_decode($data, true) : array()
				));
			}

			// Request processed, nothing more to do
			exit;
		}

		/**
		 * Makes request to API server.
		 *
		 * @param array $data Data to send.
		 * @param array $response [optional]<br />Please provide if you need actual response.
		 *
		 * @return bool Is request successful.
		 */
		private function _request($data, &$response = null)
		{
			$result = false;
			if ($this->_isOn || in_array($data['_action'], array('activate', 'test'))) {
				$result = $this->_api->request($data, $response);
				$this->_apiCalled = true;
			}

			return $result;
		}

		/**
		 * Adds &lt;script&gt; tag into the HTML.
		 * Modifies the URL depending on whether it is a plugin file or not.
		 *
		 * @param string $url Relative or absolute URL of the JS file.
		 * @param bool $isPluginFile Is it a part of plugin?
		 */
		abstract public function registerJavaScriptFile($url, $isPluginFile = true);

		abstract public function install();

		abstract public function uninstall();

		/**
		 * Tells whether current request is AJAX one.
		 * AJAX doesn't equal to async.
		 * @return bool
		 */
		abstract public function isAjaxRequest();

		/**
		 * Tells about JS support in the integrator.
		 * @return bool
		 */
		abstract public function supportsJavaScript();

		/**
		 * Adds JS into the HTML.
		 *
		 * @param string $script JS code.
		 */
		abstract public function registerJavaScript($script);

		/**
		 * Returns URL of the website.
		 */
		abstract public function getSiteUrl();

		/**
		 * Charset used in the system.
		 * @return string
		 */
		public function getCharset()
		{
			return 'UTF8';
		}

		/**
		 * @return string
		 */
		public function getVisitorId()
		{
			if (!$this->_visitorId) {
				$this->_visitorId = $this->_getCookieValue(TriggMine_ApiClient::KEY_VISITOR_ID);
			}

			return $this->_visitorId;
		}

		/**
		 * Remembers VisitorId locally and in cookies.
		 *
		 * @param string $id
		 */
		public function setVisitorId($id)
		{
			$this->_visitorId = $id;
			$this->_setCookieValue(TriggMine_ApiClient::KEY_VISITOR_ID, $id, strtotime('+20 years'));
		}

		/**
		 * Encapsulates the work with cookies.
		 *
		 * @param string $name Key from $_COOKIES['triggmine'] array.
		 *
		 * @return string Null if not found.
		 */
		protected function _getCookieValue($name)
		{
			return isset($_COOKIE[self::KEY_TRIGGMINE][$name]) ? $_COOKIE[self::KEY_TRIGGMINE][$name] : null;
		}

		/**
		 * Encapsulates the work with cookies.
		 *
		 * @param string $name Cookie name.
		 * @param string $value Cookie value. Pass <b>null</b> to delete cookie.
		 * @param int $expire [optional]<br />Expiration timestamp.
		 * @param string $path [optional]<br />The path on the server in which the cookie will be available on.
		 */
		protected function _setCookieValue($name, $value, $expire = 0, $path = '/')
		{
			$key = self::KEY_TRIGGMINE . '[' . $name . ']';

			if ($value === null) {
				// Delete this cookie
				$expire = time() - 999999;
				unset($_COOKIE[self::KEY_TRIGGMINE][$name]);
			}

			setcookie($key, $value, $expire, $path);
		}

		/**
		 * @return string
		 */
		public function getRedirectId()
		{
			if (!$this->_redirectId) {
				$this->_redirectId = $this->_getCookieValue(TriggMine_ApiClient::KEY_REDIRECT_ID);
			}

			return $this->_redirectId;
		}

		/**
		 * Remembers RedirectId locally and in session.
		 *
		 * @param string $id
		 */
		public function setRedirectId($id)
		{
			$this->_redirectId = $id;
			$this->_setCookieValue(TriggMine_ApiClient::KEY_REDIRECT_ID, $id);
		}

		/**
		 * Return ConversionId
		 *
		 * @return mixed|string
		 */
		public function getСonversionId()
		{
			if (!$this->_conversionId) {
				$this->_conversionId = $this->_getCookieValue(TriggMine_ApiClient::KEY_CONVERSION_ID);
			}

			return $this->_conversionId;
		}

		public function setConversionId($id)
		{
			$this->_conversionId = $id;
			$this->_setCookieValue(TriggMine_ApiClient::KEY_CONVERSION_ID, $id);
		}

		/**
		 * Encapsulates the work with $_SESSION array.
		 *
		 * @param string $key Key from $_SESSION['triggmine'] array.
		 *
		 * @return mixed Null if not found.
		 */
		protected function _getSessionValue($key)
		{
			return isset($_SESSION[self::KEY_TRIGGMINE][$key]) ? $_SESSION[self::KEY_TRIGGMINE][$key] : null;
		}

		/**
		 * Remember CartPingTime in cookies
		 *
		 * @param string $time
		 */
		public function setCartPingTime($time)
		{
			$this->_setCookieValue(TriggMine_ApiClient::KEY_CART_PING_TIME, $time);
		}


		public function setLogInFile($bool)
		{
			$this->_logInFile = $bool;
		}

		public function getLogInFile()
		{
			return $this->_logInFile;
		}

		/**
		 * Common entry point for any call of event handlers and API actions.
		 * Catches all possible exceptions.
		 *
		 * @param string $name
		 * @param array $arguments
		 */
		public function __call($name, $arguments)
		{
			$method = '';

			if (strpos($name, 'on') === 0) {
				if ($this->_handleEvents) {
					$method = '_' . $name;
				} else {
					// Event can't be handled now
					return;
				}
			} else {
				if (method_exists($this, '_action_' . $name)) {
					$method = '_action_' . $name;
				}
			}

			if (!$method) {
				// TODO: log
				return;
			}

			try {
				return call_user_func_array(array($this, $method), $arguments);
			} catch (Exception $e) {
				$this->_api->reportError($e);
				// TODO: log locally
			}
		}

		public function localResponseLog($request, $responce, $fileName = '/logs/log.txt')
		{
			$f = fopen(dirname(__FILE__) . $fileName, 'a+');
			fputs($f, '-----Start log at ' . date('d-m-Y H:i:s') . '------' . "\n");

			fputs($f, 'INFO: Request:' . "\n");
			fputs($f, json_encode($request) . "\n");
			fputs($f, 'INFO: Response:' . "\n");
			fputs($f, json_encode($responce) . "\n");

			fputs($f, '-----End log at ' . date('d-m-Y H:i:s') . '--------' . "\n");
			fclose($f);
		}

		/**
		 * Builds unique CartItemId to be sent to the server. This is a default implementation.
		 * Method has to be overriden according to the data structures specific for the system.
		 *
		 * @param mixed $item Object or array containing cart item data.
		 *
		 * @return string
		 */
		protected function _buildCartItemId($item)
		{
			return is_object($item) ? $item->id : $item['id'];
		}

		/**
		 * Handler for 'buyer logged in' event.
		 */
		protected function _onBuyerLoggedIn($email)
		{
			throw new RuntimeException(self::MSG_IMPLEMENT_IN_INTEGRATOR);
		}

		/**
		 * Handler for 'buyer logged out' event.
		 */
		protected function _onBuyerLoggedOut()
		{
			throw new RuntimeException(self::MSG_IMPLEMENT_IN_INTEGRATOR);
		}

		/**
		 * Handler for 'item added to the cart' event.
		 */
		protected function _onCartItemAddedonCartItemAdded($item)
		{
			throw new RuntimeException(self::MSG_IMPLEMENT_IN_INTEGRATOR);
		}

		/**
		 * Handler for 'item updated in the cart' event.
		 */
		protected function _onCartItemUpdated($item)
		{
			throw new RuntimeException(self::MSG_IMPLEMENT_IN_INTEGRATOR);
		}

		/**
		 * Handler for 'item deleted from the cart' event.
		 */
		protected function _onCartItemDeleted($item)
		{
			throw new RuntimeException(self::MSG_IMPLEMENT_IN_INTEGRATOR);
		}

		/**
		 * Handler for 'shopping cart cleaned up' event.
		 */
		protected function _onCartCleanedUp($data)
		{
			throw new RuntimeException(self::MSG_IMPLEMENT_IN_INTEGRATOR);
		}

		/**
		 * Handler for 'shopping cart is purchased' event.
		 */
		protected function _onCartPurchased($data)
		{
			throw new RuntimeException(self::MSG_IMPLEMENT_IN_INTEGRATOR);
		}

		/**
		 * Handler for 'shopping cart is updated' event.
		 */
		protected function _onCartUpdated($data)
		{
			throw new RuntimeException(self::MSG_IMPLEMENT_IN_INTEGRATOR);
		}

		/**
		 * Encapsulates the work with $_POST array.
		 *
		 * @param string $key Key from $_POST array.
		 * @param string $default Default value if not found.
		 *
		 * @return string False if failed.
		 */
		protected function _getPostValue($key, $default = null)
		{
			$value = filter_input(INPUT_POST, $key, FILTER_SANITIZE_STRING);

			return $value !== null ? $value : $default;
		}

		/**
		 * Encapsulates the work with $_SESSION array.
		 *
		 * @param string $key Key from $_SESSION['triggmine'] array.
		 * @param string $value Value to store in $_SESSION. Pass <b>null</b> to unset.
		 */
		protected function _setSessionValue($key, $value)
		{
			if ($value === null) {
				unset($_SESSION[self::KEY_TRIGGMINE][$key]);
			} else {
				$_SESSION[self::KEY_TRIGGMINE] || $_SESSION[self::KEY_TRIGGMINE] = array();
				$_SESSION[self::KEY_TRIGGMINE][$key] = $value;
			}
		}

		/**
		 * Handler for 'page loaded' event.
		 */
		private function _onPageLoaded()
		{
			if ($this->_getGetValue(self::KEY_TRIGGMINE_EXPORT) !== null) {
				echo $this->getOrders();
				exit;
			}

			if (!$this->_isOn || $this->isAsyncRequest() || $this->isAjaxRequest()) {
				return;
			}

			// Trying to get and set Conversion Id

			$this->registerConversion();

			// Trying to retrieve a cart if needed
			$this->retrieveCart();

			// Registering new unique visitor in the system
			if (!$this->_getVisitorCalled()) {
				$this->registerVisitor();
			}
		}

		/**
		 * Tells whether current request is async one.
		 * Async doesn't equal to AJAX, it means a special GET param has been passed.
		 * @return bool
		 */
		public function isAsyncRequest()
		{
			return $this->_isAsync;
		}

		/**
		 * Sends an API request to receive a unique Id for current visitor.
		 * @return boolean Result.
		 */
		private function _action_registerVisitor()
		{

			$data = array(
				'_action' => 'getVisitor'
			);

			return $this->_request($data);
		}

		private function _action_registerConversion()
		{
			$conversionId = $this->_getGetValue(TriggMine_ApiClient::KEY_CONVERSION_ID);

			if (!($conversionId)) {
				return false;
			}

			$this->setConversionId($conversionId);
		}

		/**
		 * Sends an API request to receive a unique Id for current buyer.
		 * @return boolean Result.
		 */
		private function _action_registerBuyer()
		{
			if ($this->getBuyerId()) {
				return false;
			}

			$data = array('_action' => 'getNewBuyer');

			$buyerInfo = $this->getBuyerInfo();
			if (!empty($buyerInfo['BuyerEmail'])) {
				$data['Data'] = array('BuyerEmail' => $buyerInfo['BuyerEmail']);
			}

			if ($this->_request($data)) {
				// Got new BuyerId

				if (count($buyerInfo) > 1) {
					// Have some info except email, updating buyer
					$this->_request(array(
						'_action' => 'updateBuyer',
						'Data'    => $buyerInfo
					));
				}
			}
		}

		/**
		 * @return string
		 */
		public function getBuyerId()
		{
			if (!$this->_buyerId) {
				$this->_buyerId = $this->_getCookieValue(TriggMine_ApiClient::KEY_BUYER_ID);
			}

			return $this->_buyerId;
		}

		/**
		 * Remembers BuyerId locally and in cookies.
		 *
		 * @param string $id
		 */
		public function setBuyerId($id)
		{
			$this->_buyerId = $id;
			$this->_setCookieValue(TriggMine_ApiClient::KEY_BUYER_ID, $id, strtotime('+20 years'));
		}

		/**
		 * Returns array with buyer info [BuyerEmail, FirstName, LastName].
		 */
		abstract public function getBuyerInfo();

		/**
		 * Activates plugin.
		 * @return bool
		 */
		private function _action_activate()
		{
			$data = array('_action' => 'activate');

			return $this->_request($data);
		}

		/**
		 * Deactivates plugin.
		 * @return bool
		 */
		private function _action_deactivate()
		{
			$data = array('_action' => 'deactivate');

			return $this->_request($data);
		}

		/**
		 * Sends an API request to update buyer info.
		 *
		 * @param mixed $data Either array [BuyerEmail, FirstName, LastName] or string with email.
		 *
		 * @return bool Result.
		 */
		private function _action_logInBuyer($data)
		{
			$this->updateBuyerEmail($data);
		}

		/**
		 * Tells whether current user is admin.
		 * @return bool Is user an administrator.
		 */
		abstract protected function _isUserAdmin();

		private function _action_logOutBuyer()
		{
			// Clear Buyer Id
			$this->setBuyerId(null);

			// Purchased cart doesn't exist anymore
			$this->setCartId(null);

			// Clear Redirect Id
			$this->setRedirectId(null);

			// Clear Redirect Id
			$this->setCartPingTime(null);
		}

		/**
		 * Sends an API request to update buyer's personal data.
		 *
		 * @param array $data Array containing: BuyerEmail, FirstName, LastName.
		 *
		 * @return bool Result.
		 */
		private function _action_updateBuyerInfo($data)
		{
			$data = array(
				'_action' => 'updateBuyer',
				'Data'    => $data
			);

			return $this->_request($data);
		}

		/**
		 * Sends an API request to add cart item.
		 *
		 * @param array $item Cart item data array: CartItemId, ImageUrl, ThumbnailUrl, Price, Count, Title, Description.
		 *
		 * @return bool Result.
		 */
		private function _action_addCartItem($item)
		{
			$data = array(
				'_action' => 'addCartItem',
				'Data'    => $item
			);

			return $this->_request($data);
		}

		private function _action_updateCartFull($items)
		{
			$data = array(
				'_action' => 'updateCartFull',
				'Data'    => $items
			);

			return $this->_request($data);
		}

		private function _action_sendExport($item)
		{
			$data = array(
				'_action' => 'sendExport',
				'Data'    => $item
			);

			return $this->_request($data);
		}

		/**
		 * Sends an API request to add cart item.
		 *
		 * @param array $item Cart item data array: CartItemId, ImageUrl, ThumbnailUrl, Price, Count, Title, Description.
		 *
		 * @return bool Result.
		 */
		private function _action_updateCartItem($item)
		{
			$data = array(
				'_action' => 'updateCartItem',
				'Data'    => $item
			);

			return $this->_request($data);
		}

		/**
		 * Sends an API request to mark a cart item deleted.
		 *
		 * @param string $itemId Cart item ID.
		 *
		 * @return boolean Result.
		 */
		private function _action_deleteCartItem($itemId)
		{
			$data = array(
				'_action' => 'deleteCartItem',
				'Data'    => array(
					'CartItemId' => $itemId
				)
			);

			return $this->_request($data);
		}

		/**
		 * Sends an API request to mark the shopping cart cleaned up.
		 * @return boolean Result.
		 */
		private function _action_cleanupCart()
		{
			if (!$this->getCartId()) {
				// Nothing to clean up
				return false;
			}

			$data = array('_action' => 'cleanupCart');
			$result = $this->_request($data);

			// Forgetting this cart as it was manually cleaned up
			$this->setCartId(null);

			return $result;
		}

		/**
		 * @return string
		 */
		public function getCartId()
		{
			if (!$this->_cartId) {
				$this->_cartId = $this->_getCookieValue(TriggMine_ApiClient::KEY_CART_ID);
			}

			return $this->_cartId;
		}

		/**
		 * Remembers CartId locally and in session.
		 *
		 * @param string $id
		 */
		public function setCartId($id)
		{
			$this->_cartId = $id;
			$this->_setCookieValue(TriggMine_ApiClient::KEY_CART_ID, $id);
		}

		/**
		 * Sends an API request to mark the shopping cart purchased.
		 *
		 * @param mixed $buyerInfo Either array [BuyerEmail, FirstName, LastName] or string with email.
		 *
		 * @return bool
		 */
		private function _action_purchaseCart($buyerInfo)
		{
			// Log user in, for case when unauthorized visitor purchased items
			$this->logInBuyer($buyerInfo);

			$data = array('_action' => 'purchaseCart');
			$result = $this->_request($data);

			// Purchased cart doesn't exist anymore
			$this->setCartId(null);

			// Clear Redirect Id
			$this->setRedirectId(null);

			// Clear CartPingTime
			$this->setCartPingTime(null);

			// Clearing data stored in cookie variable
			$this->_clearSession();

			return $result;
		}

		/**
		 * Unsets whole set of TriggMine's session values.
		 */
		protected function _clearSession()
		{
			unset($_SESSION[self::KEY_TRIGGMINE]);
		}

		/**
		 * Sends a ping request to the server in order to show that visitor still works with the website.
		 *
		 * @param bool $force Force ping request.
		 *
		 * @return boolean
		 */
		private function _action_pingCart($force = false)
		{
			if (!$this->getCartId()) {
				return false;
			}

			$timePassed = time() - (int)$this->_getCookieValue(self::KEY_CART_PING_TIME);
			$timeout = $timePassed > self::CART_PING_INTERVAL;

			if ($force || $timeout) {
				$this->_setCookieValue(self::KEY_CART_PING_TIME, time());

				$data = array('_action' => 'pingCart');

				return $this->_request($data);
			}

			return false;
		}

		private function _action_updateBuyerEmail($data)
		{

			if ($this->_isUserAdmin()) {
				// TODO: do we need to send any requests from admin?
				return false;
			}

			if (!is_array($data)) {
				$data = array('BuyerEmail' => $data);
			}

			if ($data['BuyerEmail'] != '') {
				$this->_request(array(
						'_action' => 'getBuyerByEmail',
						'Data'    => array('BuyerEmail' => $data['BuyerEmail'])
					)
				);
			}

			if (!$this->getBuyerId()) {
				$this->_request(array('_action' => 'getNewBuyer'));
			}


			if ($userData = $this->_getUserDataFromDatabase(trim(strip_tags(stripslashes($data['BuyerEmail']))))) {
				$data = array_merge($data, $userData);
			}

			$data = array(
				'_action' => 'updateBuyer',
				'Data'    => $data
			);

			return $this->_request($data);

		}

		abstract protected function _getUserDataFromDatabase($email);

		/**
		 * Handles a request of the URL from trigger email, retrieves shopping cart content.
		 * @return bool Result.
		 */
		private function _action_retrieveCart()
		{
			$buyerId = $this->_getGetValue(TriggMine_ApiClient::KEY_BUYER_ID);
			$cartId = $this->_getGetValue(TriggMine_ApiClient::KEY_CART_ID);
			$redirectId = $this->_getGetValue(TriggMine_ApiClient::KEY_REDIRECT_ID);

			if (!($buyerId && $cartId && $redirectId)) {
				// There are no some of the required values
				return false;
			}

			$this->setBuyerId($buyerId);
			$this->setCartId($cartId);
			$this->setRedirectId($redirectId);

			$data = array('_action' => 'getCartContent');

			$response = null;
			$this->_request($data, $response);

			if (!empty($response['Data']['Items'])) {
				// disabling event handling during cart re-fill
				$this->_handleEvents = false;
				$this->_fillShoppingCart($response['Data']);
				$this->_handleEvents = true;
			}

			// Redirect to the cart
			header('Location: ' . $this->getCartUrl());
			exit;
		}

		public function setVisitorCalled()
		{
			$this->_visitorCalled = true;
		}

		protected function _getVisitorCalled()
		{
			return $this->_visitorCalled;
		}

		/**
		 * Re-fills shopping cart with items.
		 *
		 * @param array $cartContent Content of the shopping cart. Structure:
		 * <pre><code>
		 * array(
		 *  'CartUrl'    => 'http://...',
		 *  'TotalPrice' => 1000,
		 *  'Items'      => array(
		 *      0 => array(
		 *          CartItemId  : '123',
		 *          Price       : 750.00,
		 *          Count       : 1,
		 *          Title       : 'Lumia 920',
		 *          ImageUrl    : 'http://...',
		 *          ThumbnailUrl: 'http://...',
		 *          Description : '...',
		 *      ),
		 *      ...
		 *  )
		 * )</code></pre>
		 */
		abstract protected function _fillShoppingCart($cartContent);

		/**
		 * Returns absolute URL to the shopping cart page.
		 * @return string Shopping cart URL.
		 */
		abstract public function getCartUrl();
	}