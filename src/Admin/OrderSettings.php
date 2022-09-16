<?php

namespace Cryptum\NFT\Admin;

use Cryptum\NFT\Utils\Api;
use Cryptum\NFT\Utils\Blockchain;
use Cryptum\NFT\Utils\Log;
use Cryptum\NFT\Utils\Misc;

class OrderSettings
{
	private static $instance = null;
	public static function instance()
	{
		if (self::$instance == null) {
			self::$instance = new OrderSettings();
		}
		return self::$instance;
	}
	private function __construct()
	{
		$postType = Misc::get_post_type_from_querystring($_SERVER['QUERY_STRING']);
		if (is_admin() && strcmp($postType, 'shop_order') == 0) {
			// Log::info($postType);
			add_action('wp_enqueue_scripts', function () {
				wp_enqueue_style('admin', CRYPTUM_NFT_PLUGIN_DIR . 'public/css/admin.css');
			});
			$title = get_transient('order_settings_error.title');
			$message = get_transient('order_settings_error.message');
			if (!empty($title) or !empty($message)) {
				add_action('admin_notices', function () use ($title, $message) { ?>
					<div class="error notice notice-error">
						<p class="cryptum_nft_title"><?php htmlspecialchars($title) ?></p>
						<p><?php htmlspecialchars($message) ?></p>
					</div>
		<?php
				});
				delete_transient('order_settings_error.title');
				delete_transient('order_settings_error.message');
			}
		}
	}

	function set_admin_notices_error($title = '', $message = '')
	{
		set_transient('order_settings_error.title', $title);
		set_transient('order_settings_error.message', $message);
	}

	public function on_order_status_changed($order_id, $old_status, $new_status)
	{
		// Log::info($old_status . ' -> ' . $new_status);
		if ($new_status == 'processing') {
			$order = wc_get_order($order_id);

			$user = $order->get_user();
			$options = get_option('cryptum_nft');
			$store_id = $options['storeId'];

			$items = $order->get_items();
			$products = [];
			foreach ($items as $orderItem) {
				$product = wc_get_product($orderItem->get_product_id());
				$cryptum_nft_enabled = $product->get_meta('_cryptum_nft_options_nft_enable');
				if (isset($cryptum_nft_enabled) and $cryptum_nft_enabled == 'yes') {
					$products[] = [
						'id' => trim($product->get_meta('_cryptum_nft_options_product_id')),
						'name' => $product->get_name(),
						'value' => $product->get_price(),
						'quantity' => $orderItem->get_quantity()
					];
				}
			}

			if (count($products) == 0) {
				return;
			}

			$email_address = !empty($order->get_billing_email()) ? $order->get_billing_email() : $user->get('email');

			$response = Api::create_nft_order($order, $store_id, $products, $email_address);
			if (isset($response['error'])) {
				$message = $response['message'];
				$this->set_admin_notices_error(
					__("Error in configuring Cryptum NFT Plugin", 'cryptum-nft-domain'),
					$message
				);
				return;
			}
		}
	}

	public function show_transactions_info_panel()
	{
		global $pagenow, $post;
		$post_type = get_post_type($post);
		if (is_admin() and $post_type == 'shop_order' and $pagenow == 'post.php') {
			$order = wc_get_order($post);

			if (!empty($order->get_meta('user_wallet_address'))) {
				add_meta_box(
					'cryptum_nft_transactions_info',
					__('Cryptum NFT Transactions Info', 'cryptum-nft-domain'),
					[$this, 'show_transactions_info'],
					'shop_order',
					'normal'
				);
			}
		}
	}

	public function show_transactions_info()
	{ ?>
		<div class="cryptum_nft_transactions_infro_panel_data">
			<?php
			global $post;
			$order = wc_get_order($post);

			$message = $order->get_meta('_cryptum_nft_order_transactions_message');
			if (!empty($message)) {
				echo '<p style="font-size:12px;">' . esc_html($message)  . '</p>';
			}
			$transactions = json_decode($order->get_meta('_cryptum_nft_order_transactions'));
			if (isset($transactions) and count($transactions) > 0) {
				echo '<h4>' . __('NFT transactions hashes', 'cryptum-nft-domain') . '</h4>';
				foreach ($transactions as $transaction) {
					echo '<p><strong>' . $transaction->protocol . ': </strong> '
						. '<a href="' . Blockchain::get_tx_explorer_url($transaction->protocol, $transaction->hash) . '" target="_blank">'
						. $transaction->hash
						. '</a></p>';
				}
			} else {
				echo '<p>' . __('No NFTs have been transferred yet.', 'cryptum-nft-domain') . '</p>';
			} ?>
		</div>
<?php
	}

	public function nft_order_status_changed_callback()
	{
		if ('OPTIONS' == $_SERVER['REQUEST_METHOD']) {
			status_header(200);
			exit();
		} elseif ('POST' == $_SERVER['REQUEST_METHOD']) {
			$apikey = trim($_SERVER['HTTP_X_API_KEY']);
			$options = get_option('cryptum_nft');
			if ($apikey != $options['apikey']) {
				wp_send_json_error(array('message' => 'Unauthorized'), 401);
			}

			$raw_post = file_get_contents('php://input');
			$decoded  = json_decode($raw_post);
			$ecommerceOrderId = $decoded->ecommerceOrderId;
			$storeId = $decoded->storeId;
			$message = $decoded->message;
			$transactions = $decoded->transactions;
			$updatedProducts = $decoded->updatedProducts;
			Log::info($updatedProducts);

			if (!isset($storeId) or $options['storeId'] != $storeId) {
				wp_send_json_error(array('message' => 'Incorrect store id'), 400);
			}
			$order = wc_get_order($ecommerceOrderId);
			if (!isset($order)) {
				wp_send_json_error(array('message' => 'Incorrect order id'), 400);
			}

			foreach ($order->get_items() as $item) {
				$cryptum_productId = get_post_meta($item->get_product_id(), '_cryptum_nft_options_product_id', true);
				$products_columns = array_column($updatedProducts, '_id');
				$found_product = array_search($cryptum_productId, $products_columns);
				if ($found_product) {
					$product = wc_get_product($item->get_product_id());
					$product->set_manage_stock(true);
					$product->set_stock_quantity($found_product['nft']['amount']);
					$product->save();
				}
			}

			$order->update_meta_data('_cryptum_nft_order_transactions', json_encode($transactions));
			$order->update_meta_data('_cryptum_nft_order_transactions_message', $message);
			$order->save();
		}
	}
}
