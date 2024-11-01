<?php
require_once dirname(__FILE__) . '/core/Core.php';

class TriggMine_Integrator_Wordpress_Woocoommerce extends TriggMine_Core
{
	const VERSION = '1.0.1';

	private $_scriptFiles = array();
	private $_scripts = array();

	protected $_logInFile = false;

	public function __construct($outputJavaScript = true)
	{
		parent::__construct();

		if ($outputJavaScript) {
			add_action('wp_print_footer_scripts', array($this, 'outputJavaScript'), 10);
		}
	}

	/**
	 * Returns a name of your CMS / eCommerce platform.
	 *
	 * @return string Agent name.
	 */
	public function getAgent()
	{
		return 'Wordpress';
	}

	/**
	 * Returns a version of your CMS / eCommerce platform.
	 *
	 * @return string Version.
	 */
	public function getAgentVersion()
	{
		return get_bloginfo('version', 'raw');
	}

	/**
	 * Returns a value of the setting having given name.
	 *
	 * @param string $key Setting name.
	 *
	 * @return string Setting value.
	 */
	protected function _getSettingValue($key)
	{
		$options = (array)get_option('triggmine');
		$key = str_replace('triggmine_', '', $key);

		return isset($options[ $key ]) ? $options[ $key ] : '';
	}

	/**
	 * Returns a json string of orders
	 *
	 * @param $data
	 *
	 * @return string Json
	 */
	public function exportOrders($data)
	{
        $spanStart = (int) $data->SpanStart;
        $spanEnd = (int) $data->SpanEnd;
        $offset = (int) $data->Offset;
        $next = (int) $data->Next;

        $mainOutput = array();

        global $wpdb;

        if (!empty($wpdb->prefix)) {
            $wp_table_prefix = $wpdb->prefix;
        }

        $tableOrderItems= "{$wp_table_prefix}woocommerce_order_items";

        $sql = 'SELECT DISTINCT o.`order_id`
					FROM `'.$tableOrderItems.'` as o
					WHERE o.`order_id` BETWEEN '.$spanStart.' AND '.$spanEnd.'
  					ORDER BY o.`order_id` ASC
					LIMIT '.$next.' OFFSET '.$offset.';
			';
        $result = $wpdb->get_results($sql);
        foreach ($result as $res) {

			$order = new WC_Order($res->order_id);
			$user_id = $order->user_id;
			$user = get_user_by('id', $user_id);
			$localOutput = array();

			$localOutput['CartId'] = (string)$order->id; //Order ID
			$localOutput['Email'] = $user->data->user_email;
			$localOutput['Amount'] = round($order->get_total(), 2); //Order Total
			$localOutput['DateTime'] = gmdate("Y-m-d H:i:s\Z", strtotime($order->order_date));
			$localOutput['State'] = ($order->post_status == 'wc-completed') ? 3 : 0;


            foreach ($order->get_items() as $key => $lineItem) {
				$data = $lineItem;
				$product_id = $data['variation_id'] ? $data['variation_id'] : $data['product_id'];

				$product_data = wc_get_product($product_id);

				if (isset($product_data->variation_id)) {
					$post = $product_data->parent->post;
				} else {
					$post = $product_data->post;
				}

				//$product = new Product((int)$product_id);

				$data = array(
					'CartItemId'       => strval($this->buildCartItemId($product_data)),
					'Title'            => strval($lineItem['name']),
					'ShortDescription' => strval($post->post_excerpt),
					'Description'      => strval($post->post_content),
					'Price'            => floatval($product_data->price),
					'Count'            => intval($lineItem['qty'])
				);

				$attachment = wp_get_attachment_image_src($product_data->get_image_id());
				$image = array_shift($attachment);
				if (!empty($image)) {
					$data['ImageUrl'] = $image;
				}

				$localOutput['Content'][] = $data;
			}
			$mainOutput[] = $localOutput;
        }

		return $mainOutput;
    }

	/**
	 * Adds &lt;script&gt; tag into the HTML.
	 * Modifies the URL depending on whether it is a plugin file or not.
	 *
	 * @param string $url          Relative or absolute URL of the JS file.
	 * @param bool   $isPluginFile Is it a part of plugin?
	 */
	public function registerJavaScriptFile($url, $isPluginFile = true)
	{
		$this->_scriptFiles[] = $isPluginFile ? plugins_url($url, __FILE__) : $url;
	}

	public function outputJavaScript()
	{
		foreach ($this->_scriptFiles as $scriptFile) {
			echo "<script type='text/javascript' src='$scriptFile'></script>" . PHP_EOL;
		}

		foreach ($this->_scripts as $script) {
			echo "<script type='text/javascript'>/* <![CDATA[ */ $script /* ]]> */</script>" . PHP_EOL;
		}
	}

	public function install()
	{
		add_option(self::SETTING_IS_ON, 0);
		add_option(self::SETTING_REST_API, 'http://api.triggmine.com/');
		add_option(self::SETTING_TOKEN, '');
	}

	public function uninstall()
	{
		delete_option(self::SETTING_IS_ON);
		delete_option(self::SETTING_REST_API);
		delete_option(self::SETTING_TOKEN);
	}

	/**
	 * Tells whether current request is AJAX one.
	 * AJAX doesn't equal to async.
	 *
	 * @return bool
	 */
	public function isAjaxRequest()
	{
		return defined('DOING_AJAX') && DOING_AJAX;
	}

	/**
	 * Tells about JS support in the integrator.
	 *
	 * @return bool
	 */
	public function supportsJavaScript()
	{
		return true;
	}

	/**
	 * Adds JS into the HTML.
	 *
	 * @param string $script JS code.
	 */
	public function registerJavaScript($script)
	{
		$this->_scripts[] = $script;
	}

	/**
	 * Returns URL of the website.
	 */
	public function getSiteUrl()
	{
		return site_url();
	}

	/**
	 * Returns array with buyer info [BuyerEmail, FirstName, LastName].
	 */
	public function getBuyerInfo()
	{
		global $current_user;

		return array(
			'BuyerEmail' => $current_user->user_email,
			'FirstName'  => $current_user->first_name,
			'LastName'   => $current_user->last_name
		);
	}

	/**
	 * Tells whether current user is admin.
	 *
	 * @return bool Is user an administrator.
	 */
	protected function _isUserAdmin()
	{
		return is_user_admin();
	}

	protected function _getUserDataFromDatabase($email)
	{
		$user = false;

		if (function_exists('get_user_by'))
			$user = get_user_by('email', $email);
		else {
			$userData = WP_User::get_data_by('email', $email);

			if ($userData) {
				$user = new WP_User;
				$user->init($userData);
			}
		}

		if ($user) {
			$data = array(
				'BuyerRegEnd' => gmdate("Y-m-d H:i:s", strtotime($user->data->user_registered))
			);

			return $data;
		}

		$data = array(
			'BuyerRegStart' => gmdate("Y-m-d H:i:s", time())
		);

		return $data;
	}

	/**
	 * Re-fills shopping cart with items.
	 *
	 * @param array $cartContent Content of the shopping cart. Structure:
	 *                           <pre><code>
	 *                           array(
	 *                           'CartUrl'    => 'http://...',
	 *                           'TotalPrice' => 1000,
	 *                           'Items'      => array(
	 *                           0 => array(
	 *                           CartItemId  : '123',
	 *                           Price       : 750.00,
	 *                           Count       : 1,
	 *                           Title       : 'Lumia 920',
	 *                           ImageUrl    : 'http://...',
	 *                           ThumbnailUrl: 'http://...',
	 *                           Description : '...',
	 *                           ),
	 *                           ...
	 *                           )
	 *                           )</code></pre>
	 */
	protected function _fillShoppingCart($cartContent)
	{
		if (empty($cartContent['Items'])) {
			return false;
		}

		global $woocommerce;
		$woocommerce->cart->empty_cart();

		$cartItems = $cartContent['Items'];

		foreach ($cartItems as $cartItem) {

			$ids = explode('|', $cartItem['CartItemId']);
			$product_id = array_shift($ids);
			$variation_id = end($ids);
			$quantity = $cartItem['Count'];

			if ($variation_id) {
				$woocommerce->cart->add_to_cart($product_id, $quantity, $variation_id);
			} else {
				$woocommerce->cart->add_to_cart($product_id, $quantity);
			}

		}
	}

	/**
	 * Handler for 'buyer logged in' event.
	 */
	protected function _onBuyerLoggedIn($login, $user = null)
	{
		$this->logInBuyer(array(
			'BuyerEmail' => $user->user_email,
			'FirstName'  => $user->first_name,
			'LastName'   => $user->last_name
		));
	}


	/**
	 * Returns absolute URL to the shopping cart page.
	 *
	 * @return string Shopping cart URL.
	 */
	public function getCartUrl()
	{
		global $woocommerce;
		return $woocommerce->cart->get_cart_url();
	}

	protected function _onUpdateCartFull() {
		$data = $this->getCartItems();
		if (!empty($data))
			$this->updateCartFull($data);
	}

	public function getCartItems()
	{
		global $woocommerce;
		$items = $woocommerce->cart->get_cart();
		$products = array();

		foreach ($items as $item) {
			$data = $item['data'];
			$product_id = $data->variation_id ? $data->variation_id : $data->id;

			$product_data = wc_get_product($product_id);

			if (isset($data->variation_id)) {
				$post = $data->parent->post;
			} else {
				$post = $data->post;
			}

			$data = array(
				'CartItemId'       => strval($this->buildCartItemId($product_data)),
				'Title'            => strval($post->post_title),
				'ShortDescription' => strval($post->post_excerpt),
				'Description'      => strval($post->post_content),
				'Price'            => floatval($product_data->price),
				'Count'            => intval($item['quantity'])
			);

			$attachment = wp_get_attachment_image_src($product_data->get_image_id());
			$image = array_shift($attachment);
			if (!empty($image)) {
				$data['ImageUrl'] = $image;
			}

			$products['Items'][] = $data;
		}

		return $products;
	}

	public function buildCartItemId($product_data)
	{
		if (isset($product_data->variation_id)) {
			$cartItemId = (string) $product_data->parent->id . '|' . (string) $product_data->variation_id;
		} else {
			$cartItemId = (string) $product_data->id;
		}

		return $cartItemId;
	}

	/**
	 * Handler for 'shopping cart is purchased' event.
	 *
	 * @param $order_id
	 */
	public function _onCartPurchased($order_id)
	{
		$order = new WC_Order( $order_id );

		$buyerInfo = array(
			'BuyerEmail' => $order->billing_email,
			'FirstName'  => $order->billing_first_name,
			'LastName'   => $order->billing_last_name,
		);

		$this->purchaseCart($buyerInfo);
	}

	public function _onBuyerLoggedOut()
	{
		$this->logOutBuyer();
	}

	public function _onSendExport($input)
	{
		$data = $this->_prepareExportData($input);
		$this->sendExport($data);
	}

	public function _prepareExportData($input)
	{
		global $wpdb;

		if (!empty($wpdb->prefix)) {
			$wp_table_prefix = $wpdb->prefix;
		}

		$tablePosts = "{$wp_table_prefix}posts";

		$days = $input['time_export_option'];
		if ($days != 'all') {
			$dateStamp = '-'.$days.' days';
			$sql = '
					SELECT p.`ID`
					FROM `'.$tablePosts.'` p
					WHERE p.post_date >= \''.date('Y-m-d H:i:s', strtotime($dateStamp)).'\'
					AND p.post_type = "shop_order"
					Order by p.`ID` ASC
				';
		} else {
			$sql = '
					SELECT p.`ID`
					FROM `'.$tablePosts.'` p
					WHERE p.post_type = "shop_order"
					Order by p.`ID` ASC
				';
		}

		$result = $wpdb->get_results($sql);
		if (!is_array($result)) {
			return false;
		} else {
			$start = current($result);
			$end = end($result);

			$message = array(
				'Url' => $this->getSiteUrl() . '?' . self::KEY_TRIGGMINE_EXPORT,
				'SpanStart' => $start->ID,
				'SpanEnd'   => $end->ID,
				'SpanCount' => count($result)
			);
		}

		return $message;
	}

}