<?php

	final class TriggMine_ApiClient
	{
		const VERSION = '1.0.7';

		/**
		 * Keys to be used in code.
		 */
		const KEY_AGENT = 'Agent';
		const KEY_BUYER_ID = 'BuyerId';
		const KEY_BUYER_EMAIL = 'BuyerEmail';
		const KEY_BUYER_BIRTHDAY = 'BuyerBirthday';
		const KEY_BUYER_REG_START = 'BuyerRegStart';
		const KEY_BUYER_REG_END = 'BuyerRegEnd';
		const KEY_CART_ID = 'CartId';
		const KEY_CART_URL = 'CartUrl';
		const KEY_REDIRECT_ID = 'RedirectId';
		const KEY_TOKEN = 'Token';
		const KEY_VISITOR_ID = 'VisitorId';
		const KEY_CART_PING_TIME = 'CartPingTime';
		const KEY_CONVERSION_ID = 'ConversionId';

		const KEY_MAIN_API_SUFFIX = 'api/Cart';

		private $_actionMapping = array(
			'log'              => array(
				'method' => 'Log'
			),
			'activate'         => array(
				'method' => 'Activate'
			),
			'deactivate'       => array(
				'method' => 'Deactivate'
			),
			'sendExport'       => array(
				'method' => 'Import',
			),
			'getVisitor'       => array(
				'method'    => 'GetVisitorId',
				'paramsOut' => array(self::KEY_AGENT, self::KEY_VISITOR_ID),
				'paramsIn'  => array(self::KEY_VISITOR_ID)
			),
			'getBuyerEmail'    => array(
				'method'    => 'GetBuyerEmail',
				'paramsOut' => array(self::KEY_BUYER_ID)
			),
			'getBuyerByEmail'  => array(
				'method'   => 'GetBuyerId',
				'paramsOut'=> array(self::KEY_BUYER_ID),
				'paramsIn' => array(self::KEY_BUYER_ID)
			),
			'getNewBuyer'      => array(
				'method'   => 'GetBuyerId',
				'paramsIn' => array(self::KEY_BUYER_ID)
			),
			'updateBuyer'      => array(
				'method'    => 'CreateReplaceBuyerInfo',
				'paramsOut' => array(self::KEY_BUYER_ID)
			),
			'setBuyerBirthday' => array(
				'method'    => 'SetBuyerBirthday',
				'paramsOut' => array(self::KEY_BUYER_ID, self::KEY_BUYER_EMAIL, self::KEY_BUYER_BIRTHDAY)
			),
			'setBuyerRegStart' => array(
				'method'    => 'SetBuyerRegStart',
				'paramsOut' => array(self::KEY_BUYER_ID, self::KEY_BUYER_EMAIL, self::KEY_BUYER_REG_START)
			),
			'setBuyerRegEnd'   => array(
				'method'    => 'SetBuyerRegEnd',
				'paramsOut' => array(self::KEY_BUYER_ID, self::KEY_BUYER_EMAIL, self::KEY_BUYER_REG_END)
			),
			'getNewCart'       => array(
				'method'    => 'GetCartId',
				'paramsOut' => array(self::KEY_BUYER_ID),
				'paramsIn'  => array(self::KEY_BUYER_ID, self::KEY_CART_ID)
			),
			'addCartItem'      => array(
				'method'    => 'CreateReplaceCartItem',
				'paramsOut' => array(self::KEY_BUYER_ID, self::KEY_CART_ID, self::KEY_CART_URL),
				'paramsIn'  => array(self::KEY_BUYER_ID, self::KEY_CART_ID)
			),
			'updateCartItem'   => array(
				'method'    => 'CreateReplaceCartItem',
				'paramsOut' => array(self::KEY_BUYER_ID, self::KEY_CART_ID, self::KEY_CART_URL),
				'paramsIn'  => array(self::KEY_BUYER_ID, self::KEY_CART_ID)
			),
			'updateCartFull'   => array(
				'method'    => 'CreateReplaceCart',
				'paramsOut' => array(self::KEY_BUYER_ID, self::KEY_CART_ID, self::KEY_CART_URL),
				'paramsIn'  => array(self::KEY_BUYER_ID, self::KEY_CART_ID)
			),
			'deleteCartItem'   => array(
				'method'    => 'DeleteCartItem',
				'paramsOut' => array(self::KEY_BUYER_ID, self::KEY_CART_ID)
			),
			'cleanupCart'      => array(
				'method'    => 'CleanupCart',
				'paramsOut' => array(self::KEY_BUYER_ID, self::KEY_CART_ID)
			),
			'purchaseCart'     => array(
				'method'    => 'PurchaseCart',
				'paramsOut' => array(self::KEY_BUYER_ID, self::KEY_CART_ID, self::KEY_REDIRECT_ID, self::KEY_CONVERSION_ID)
			),
			'getCartContent'   => array(
				'method'    => 'GetCartContent',
				'paramsOut' => array(self::KEY_BUYER_ID, self::KEY_CART_ID)
			),
			'pingCart'         => array(
				'method'    => 'PingCart',
				'paramsOut' => array(self::KEY_AGENT, self::KEY_BUYER_ID, self::KEY_CART_ID),
				'paramsIn'  => array(self::KEY_CART_PING_TIME)
			),

		);

		/**
		 * @var TriggMine_Core
		 */
		private $_integrator = null;

		/**
		 * URL of API.
		 * @var string
		 */
		private $_apiUrl = null;

		/**
		 * API access token.
		 * @var string
		 */
		private $_token = null;

		public function __construct(TriggMine_Core $integrator)
		{
			$this->_integrator = $integrator;
			$this->_apiUrl = $this->_integrator->_restApi;
			$this->_token = $this->_integrator->_token;
		}

		/**
		 * Makes request to API server.
		 *
		 * @param array $request Data to send.
		 * @param array $response [optional]<br />Please provide if you need actual response.
		 *
		 * @return bool Is request successful.
		 */
		public function request($request, &$response = null)
		{
			if (!($this->_apiUrl && $this->_token)) {
				return false;
			}

			try {
				$this->_processOutgoingData($request);

				TriggMine_Helper::encodeArray($request, $this->_integrator->getCharset());

				if ($this->_integrator->supportsJavaScript()
					&& !$this->_integrator->isAjaxRequest()
					&& !$this->_integrator->isAsyncRequest()
					&& in_array($request['_action'], array('getVisitor'))
				) {
					// Can't handle any other actions.
					// In case of redirect other method calls can be lost in case of redirect,
					// which often happens in CMS after the cart purchase, for example.
					$this->_registerAsyncRequest($request);
				} elseif (in_array($request['_action'], array('onPingCart'))) {
					$this->_integrator->pingCart();
				} elseif (in_array($request['_action'], array('onUpdateBuyerEmail'))) {
					$this->_integrator->updateBuyerEmail($request['Data']);
				} else {
					$url = strstr($this->_apiUrl, 'http://') ? $this->_apiUrl : 'http://' . $this->_apiUrl;
					$url .= substr($url, -1) === '/' ? '' : '/';
					$url .= self::KEY_MAIN_API_SUFFIX;
					$result = false;

					if (TriggMine_Helper::isCurlEnabled()) {
						$result = $this->_curlRequest($url, $request);
					} else if (TriggMine_Helper::isUrlFopenEnabled()) {
						$result = $this->_fopenRequest($url, $request);
					}

					$response = json_decode($result, true);

					$this->_processIncomingData($request, $response);
					TriggMine_Helper::encodeArray($response, $this->_integrator->getCharset(), true);

				}
			} catch (Exception $e) {
				if ($request['_action'] != 'Log') {
					// It's not 'Log' method failed, so no recursion will occure
					$this->reportError($e, $response ? $response['LogId'] : null);
				}
			}

			return !TriggMine_Error::isError($response['ErrorCode']);
		}

		/**
		 * Checks outgoing data whether it contains all needed information.
		 *
		 * @param array $request
		 */
		private function _processOutgoingData(&$request)
		{
			// Always send token
			$request[self::KEY_TOKEN] = $this->_token;

			// Always send Data array having at least something inside
			isset($request['Data']) || $request['Data'] = array('dummy' => 1);
			if (isset($this->_actionMapping[$request['_action']])) {
				$actionMapping = $this->_actionMapping[$request['_action']];
			} else {
				$actionMapping = array('method' => ucfirst($request['_action']));
			}

			$request['Method'] = $actionMapping['method'];
			$paramsOut = isset($actionMapping['paramsOut']) ? $actionMapping['paramsOut'] : array();

			foreach ($paramsOut as $paramName) {
				if (empty($request['Data'][$paramName])) {
					switch ($paramName) {
						case self::KEY_AGENT:
							$value = $this->_integrator->getAgent();
							break;

						case self::KEY_VISITOR_ID:
							$value = $this->_integrator->getVisitorId();
							break;

						case self::KEY_BUYER_ID:
							$value = $this->_integrator->getBuyerId();
							break;

						case self::KEY_CART_ID:
							$value = $this->_integrator->getCartId();
							break;

						case self::KEY_REDIRECT_ID:
							$value = $this->_integrator->getRedirectId();
							break;

						case self::KEY_CART_URL:
							$value = $this->_integrator->getCartUrl();
							break;

						case self::KEY_CONVERSION_ID:
							$value = $this->_integrator->getСonversionId();
							break;

						default:
							$value = null;
					}

					if ($value) {
						$request['Data'][$paramName] = $value;
					}
				}
			}
		}

		/**
		 * Registers fake JavaScript file to be loaded on the client-side.
		 * &lt;script&gt; tag will invoke an asynchronous API request.
		 *
		 * @param array $request
		 */
		private function _registerAsyncRequest($request)
		{
			$url = $this->_integrator->getSiteUrl() . '/?triggmine_async=1&';
			$url .= http_build_query(array(
				'_action' => $request['_action'],
				'Data'    => json_encode($request['Data'])
			));

			// Adding salt to avoid caching
			$url .= '&salt=' . substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 8);

			$this->_integrator->registerJavaScriptFile($url, false);
		}

		/**
		 * Performs CURL request.
		 *
		 * @param string $url
		 * @param array $data
		 *
		 * @return bool
		 */
		private function _curlRequest($url, $data)
		{
			$curl = curl_init();
			curl_setopt($curl, CURLOPT_URL, $url);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($curl, CURLOPT_POST, true);
			curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Accept: application/json'));
			curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
			$result = curl_exec($curl);
			curl_close($curl);

			return $result;
		}

		/**
		 * Performs file open request.
		 *
		 * @param $url
		 * @param $data
		 *
		 * @return string
		 * @throws Exception
		 *
		 */
		private function _fopenRequest($url, $data)
		{
			$options = array(
				'http' => array(
					'method'  => 'POST',
					'content' => json_encode($data),
					'header'  => "Content-Type: application/json\r\n" . "Accept: application/json\r\n"
				)
			);

			if (!$context = stream_context_create($options)) {
				throw new Exception("Request failed. Can't create stream context.");
			}

			if (!$result = @file_get_contents($url, false, $context)) {
				throw new Exception('Error occured.');
			}

			return $result;
		}

		/**
		 * Persists all needed information from the incoming data set.
		 *
		 * @param array $request
		 * @param array $response
		 */
		private function _processIncomingData($request, $response)
		{
			$actionMapping = $this->_actionMapping[$request['_action']];
			$paramsIn = isset($actionMapping['paramsIn']) ? $actionMapping['paramsIn'] : array();
			foreach ($paramsIn as $paramName) {
				if (!empty($response['Data'][$paramName]) || $paramName === self::KEY_CART_PING_TIME) {
					switch ($paramName) {
						case self::KEY_BUYER_ID:
							$this->_integrator->setBuyerId($response['Data'][$paramName]);
							break;

						case self::KEY_CART_ID:
							$this->_integrator->setCartId($response['Data'][$paramName]);
							break;

						case self::KEY_VISITOR_ID:
							$this->_integrator->setVisitorId($response['Data'][$paramName]);
							$this->_integrator->setVisitorCalled();
							break;

						case self::KEY_REDIRECT_ID:
							$this->_integrator->setRedirectId($response['Data'][$paramName]);
							break;

						case self::KEY_CART_PING_TIME:
							$this->_integrator->setCartPingTime(time());
							break;
					}
				}
			}
		}

		/**
		 * Reports an error to the API server.
		 *
		 * @param mixed $error Exception or string.
		 * @param string $logId [optional] LogId, if present after another API method request.
		 */
		public function reportError($error, $logId = null)
		{
			if ($error instanceof Exception) {
				$error = $error->getMessage() . "\n" . $error->getTraceAsString();
			}

			$data = array(
				'_action' => 'log',
				'Data'    => array(
					'Description' => $error
				)
			);

			if ($logId) {
				$data['Data']['LogId'] = $logId;
			}

			$this->request($data);
		}
	}