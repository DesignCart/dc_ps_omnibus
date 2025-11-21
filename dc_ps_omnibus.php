<?php
/**
 * DC PS Omnibus - Golden Master Edition
 * @author Design Cart
 * @version 3.3.0
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class Dc_Ps_Omnibus extends Module
{
    private $storage_dir;
    private $retention_days = 30;

    public function __construct()
    {
        $this->name = 'dc_ps_omnibus';
        $this->tab = 'pricing_promotion';
        $this->version = '3.3.0';
        $this->author = 'Design Cart';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('DC PS Omnibus');
        $this->description = $this->l('Zgodna z dyrektywą Omnibus historia cen (Flat File). Obsługuje PS 1.7, 8 i 9.');

        $this->storage_dir = _PS_MODULE_DIR_ . $this->name . '/history/';
    }

    public function install()
    {
        if (!file_exists($this->storage_dir)) {
            $old_umask = umask(0);
            @mkdir($this->storage_dir, 0777, true);
            umask($old_umask);
            @file_put_contents($this->storage_dir . '.htaccess', 'Deny from all');
            @file_put_contents($this->storage_dir . 'index.php', '');
        }

        return parent::install() &&
            $this->registerHook('actionObjectProductUpdateAfter') && 
            $this->registerHook('actionObjectProductAddAfter') &&
            $this->registerHook('actionObjectSpecificPriceAddAfter') &&
            $this->registerHook('actionObjectSpecificPriceUpdateAfter') &&
            $this->registerHook('actionObjectSpecificPriceDeleteAfter') &&
            $this->registerHook('displayProductPriceBlock');
    }

    public function hookActionObjectProductUpdateAfter($params) { $this->processProductObject($params); }
    public function hookActionObjectProductAddAfter($params) { $this->processProductObject($params); }
    public function hookActionObjectSpecificPriceAddAfter($params) { $this->processSpecificPriceObject($params); }
    public function hookActionObjectSpecificPriceUpdateAfter($params) { $this->processSpecificPriceObject($params); }
    public function hookActionObjectSpecificPriceDeleteAfter($params) { $this->processSpecificPriceObject($params); }

    private function processProductObject($params) {
        if (!isset($params['object']) || !$params['object'] instanceof Product) return;
        $this->saveProductHistory($params['object']);
    }

    private function processSpecificPriceObject($params) {
        if (!isset($params['object']) || !$params['object'] instanceof SpecificPrice) return;
        $id_product = (int)$params['object']->id_product;
        $product = new Product($id_product);
        if (Validate::isLoadedObject($product)) {
            $this->saveProductHistory($product);
        }
    }

    private function saveProductHistory($product) {
        static $processed_ids = [];
        if (isset($processed_ids[$product->id])) return;
        $processed_ids[$product->id] = true;

        $combinations = $product->getAttributesResume(Context::getContext()->language->id);

        if (empty($combinations)) {
            $price = $product->getPrice(true, null, 2);
            $this->logPrice($product->id, 0, $price);
        } else {
            foreach ($combinations as $combination) {
                $ipa = (int)$combination['id_product_attribute'];
                $price = Product::getPriceStatic($product->id, true, $ipa, 2);
                $this->logPrice($product->id, $ipa, $price);
            }
        }
    }

    private function logPrice($id_product, $id_product_attribute, $price){
        $file = $this->storage_dir . 'history-' . (int)$id_product . '-' . (int)$id_product_attribute . '.txt';
        
        if (!is_dir($this->storage_dir)) {
            $old_umask = umask(0);
            @mkdir($this->storage_dir, 0777, true);
            umask($old_umask);
        }

        $current_data = [];
        $now = time();
        $cutoff = $now - ($this->retention_days * 24 * 60 * 60);

        if (file_exists($file)) {
            $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines && count($lines) < 1000) {
                foreach ($lines as $line) {
                    $parts = explode('|', $line);
                    if (count($parts) === 2 && is_numeric($parts[0]) && is_numeric($parts[1])) {
                        if ((int)$parts[0] >= $cutoff) {
                            $current_data[] = $line;
                        }
                    }
                }
            }
        }

        if (!empty($current_data)) {
            $last_entry = end($current_data);
            list($last_time, $last_price) = explode('|', $last_entry);
            if (abs((float)$last_price - (float)$price) < 0.001) {
                return;
            }
        }

        $current_data[] = $now . '|' . $price;
        @file_put_contents($file, implode("\n", $current_data), LOCK_EX);
    }


    public function hookDisplayProductPriceBlock($params){
        try {
            if (!isset($params['type']) || $params['type'] !== 'after_price') return;
            if ($this->context->controller->php_self !== 'product') return;

            $product = $params['product'];
            $id_product = 0;
            $id_product_attribute = 0;

            if (is_array($product)) {
                $id_product = (int)$product['id_product'];
                $id_product_attribute = isset($product['id_product_attribute']) ? (int)$product['id_product_attribute'] : 0;
            } elseif (is_object($product)) {
                $id_product = (int)$product->id;
                $id_product_attribute = isset($product->id_product_attribute) ? (int)$product->id_product_attribute : 0;
            }

            if (!$id_product) return;

            $lowest_price = $this->getLowestPrice($id_product, $id_product_attribute);

            if ($lowest_price !== false && is_numeric($lowest_price) && $lowest_price > 0) {
                $currency_code = $this->context->currency->iso_code;
                $formatted_price = $this->context->currentLocale->formatPrice($lowest_price, $currency_code);
                
                return sprintf(
                    '<div class="dc-omnibus-info" style="font-size: 0.8rem; color: #666; margin-top: 10px; line-height: 1.2; clear:both;">
                        <i class="material-icons" style="font-size: 14px; vertical-align: middle;">info_outline</i> 
                        Najniższa cena z 30 dni: <strong>%s</strong>
                    </div>',
                    $formatted_price
                );
            }
        } catch (Exception $e) {
            return '';
        }
    }

    private function getLowestPrice($id_product, $id_product_attribute){
        $file = $this->storage_dir . 'history-' . (int)$id_product . '-' . (int)$id_product_attribute . '.txt';
        if (!file_exists($file)) return false;

        $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$lines) return false;

        $prices = [];
        $cutoff = time() - ($this->retention_days * 24 * 60 * 60);

        foreach ($lines as $line) {
            $parts = explode('|', $line);
            if (count($parts) === 2) {
                $timestamp = (int)$parts[0];
                $price = (float)$parts[1];
                if ($timestamp >= $cutoff && $price > 0) {
                    $prices[] = $price;
                }
            }
        }

        return empty($prices) ? false : min($prices);
    }
}