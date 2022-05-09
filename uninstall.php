<?php

require_once(plugin_dir_path(__FILE__) . '/lib/autoload.php');
// if uninstall.php is not called by WordPress, die

use Cryptum\NFT\NFTViewPage;
use Cryptum\NFT\Utils\Log;

if (!defined('WP_UNINSTALL_PLUGIN')) {
	die;
}
Log::info('Deleting Cryptum NFT information');
$option_name = 'cryptum_nft';

delete_option($option_name);

// for site options in Multisite
delete_site_option($option_name);

NFTViewPage::instance()->delete_page();

// drop a custom database table
global $wpdb;
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}_cryptum_nft_item_meta");