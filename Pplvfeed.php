<?php

class Pplvfeed
{
    private static $attributes = [];
    private static $exportedIds = [];
    private static $languages = [];
    private static $polylangInstalled = false;

    public static function initSettings()
    {
        add_filter(
            'plugin_action_links_' . PPFEED__PLUGIN_BASENAME,
            [__CLASS__, 'plugin_action_links']
        );
        add_submenu_page(
            'woocommerce',
            __( 'WooCommerce settings', 'woocommerce' ),
            __( 'PP.lv feed', 'woocommerce' ),
            'manage_woocommerce',
            'ppfeed',
            [ __CLASS__, 'settings' ]
        );

        register_setting('ppfeed_options', 'ppfeed_options', [__CLASS__, 'sanitizeCallback']);
        add_settings_field( 'ppfeed_protected', 'Password protected', [__CLASS__, 'protectedFeed'], 'ppfeed', 'ppfeed_settings' );
        add_settings_field( 'ppfeed_code', null, [__CLASS__, 'codeFeed'], 'ppfeed', 'ppfeed_settings' );
    }

    public static function protectedFeed()
    {
        $options = get_option( 'ppfeed_options' );

        echo sprintf(
            "<input id='ppfeed_protected' name='ppfeed_options[ppfeed_protected]' type='checkbox'%s/>",
            $options['ppfeed_protected'] ? ' checked' : null
        );
    }

    public static function codeFeed()
    {
        $options = get_option( 'ppfeed_options' );

        echo sprintf(
            "<input id='ppfeed_code' name='ppfeed_options[ppfeed_code]' type='hidden' value='%s'/>",
            $options['ppfeed_code'] ?? null
        );
    }

    public static function sanitizeCallback($input)
    {
        return [
            'ppfeed_protected' => isset($input['ppfeed_protected']) && $input['ppfeed_protected'],
            'ppfeed_code' => $input['ppfeed_code'] ?: substr(md5(time()), 0, 4),
        ];
    }

    public static function plugin_action_links( $links ) {
        return array_merge(
            [
                'settings' => sprintf(
                    '<a href="%s" aria-label="%s">%s</a>',
                    admin_url( 'admin.php?page=ppfeed' ),
                    'View PP.lv feed settings',
                    'Settings'
                )
            ],
            $links
        );
    }

    public static function settings()
    {
        $options = get_option('ppfeed_options');
        $exportUrl = sprintf(
            '%s%s',
            rest_url('ppfeed/export'),
            isset($options['ppfeed_protected']) && $options['ppfeed_protected']
                ? '?code=' . $options['ppfeed_code']
                : null
        );
        include 'settings.php';
    }

    public static function init()
    {
        if (!function_exists('register_rest_route')) {
            return;
        }

        add_action('rest_api_init', function () {
            register_rest_route('ppfeed', '/export', [
                'methods' => 'GET',
                'callback' => [__CLASS__, 'export'],
            ]);
        });
    }

    private static function getBatchProducts()
    {
        $i = 0;
        while ($data = wc_get_products([
            'limit' => 100,
            'offset' => $i,
            'status' => 'publish',
            'stock_status' => ['instock', 'onbackorder'],
            'order' => 'ASC',
        ])) {
            function_exists('wp_cache_flush_runtime') ? wp_cache_flush_runtime() : wp_cache_flush();
            $i += 100;
            yield $data;
        }
    }

    private static function validateCode($request)
    {
        $options = get_option( 'ppfeed_options' );
        if (!isset($options['ppfeed_protected']) || !$options['ppfeed_protected']) {
            return true;
        }

        $inputCode = $request->get_param( 'code' );

        return $inputCode === $options['ppfeed_code'];
    }

    private static function initTranslationBundles()
    {
        self::$polylangInstalled = (bool) get_option( 'polylang-wc' ) && function_exists('pll_languages_list') && (bool) pll_languages_list();
        if (self::$polylangInstalled) {
            self::$languages = array_keys(pll_the_languages(['raw' => 1]));
        }
    }

    public static function export($request)
    {
        if (!self::validateCode($request)) {
            return new WP_Error('invalid_key', 'Route is password protected.', ['status' => 401]);
        }
        self::initTranslationBundles();

        $categories = self::getCategories();

        header('Content-Type: application/json');
        header('PPlv-Version: ' . self::getVersion());
        echo '{"type":"lot_list","lot_list":[';
        $counter = 0;
        foreach (self::getBatchProducts() as $products) {
            /** @var WC_Product_Simple $product */
            foreach ( $products as $product ) {
                if (in_array($product->get_id(), self::$exportedIds, true)) {
                    continue;
                }
                if ($counter++) {
                    echo ',';
                }
                self::$exportedIds[] = $product->get_id();
                echo json_encode(self::getProductData( $product, $categories ));
            }
        }
        echo ']}';
        die;
    }

    /** @param WC_Product_Simple $product */
    private static function getProductData($product, $categories)
    {
        $productCategories = array_map(
            static function ($categoryId) use ($categories) {
                return $categories[$categoryId];
            },
            $product->get_category_ids()
        );
        $mainCategory = $productCategories ? array_shift($productCategories) : 'Uncategorized';

        $price = $product->get_price();
        $regularPrice = $product->get_regular_price();

        $mainImage = (int) $product->get_image_id();
        $gallery = $product->get_gallery_image_ids();
        if ($mainImage) {
            array_unshift($gallery, $mainImage);
        }

        $currentLot = [
            'type' => 'single_lot',
            'id' => $product->get_id(),
            'link' => $product->get_permalink(),
            'category' => $mainCategory,
            'action' => 'sell',
            'price' => $price,
            'images' => array_map(
                static function ($image) {
                    return wp_get_attachment_image_url( $image, 'full');
                },
                array_values(array_unique($gallery))
            ),
            'region' => 'Riga',
            'text' => self::sanitizeDescription($product),
            'features' => self::combineAttributes($product->get_attributes()),
        ];
        if ($productCategories) {
            $currentLot['alternative_categories'] = $productCategories;
        }
        if ($price !== $regularPrice) {
            $currentLot['original_price'] = $regularPrice;
        }
        self::addPolylangProTranslations($product, $currentLot);

        return $currentLot;
    }

    private static function addPolylangProTranslations($product, &$productData)
    {
        if (!self::$polylangInstalled) {
            return;
        }
        foreach (self::$languages as $language) {
            $translation = PLL()->model->post->get_translation($product->get_id(), $language);
            if ($translation) {
                $translatedProduct = wc_get_product($translation);
                self::$exportedIds[] = $translatedProduct->get_id();
                $productData['text' . ($language !== 'lv' ? '_' . $language : '')] = self::sanitizeDescription($translatedProduct);
            }
        }
    }

    private static function getVersion()
    {
        $pluginData = get_file_data(__DIR__ . '/ppfeed.php', array('Version' => 'Version'), false);

        return sprintf(
            'wp-%s-wc-%s-pp-%s',
            get_bloginfo( 'version' ),
            defined('WC_VERSION') ? WC_VERSION : 'NaN',
            $pluginData['Version']
        );
    }

    private static function sanitizeDescription($product)
    {
        return trim(
            $product->get_name() . PHP_EOL .
            str_replace(["\t", "\r"], '', trim(strip_tags($product->get_description())))
            . PHP_EOL
        );
    }

    /** @param WC_Product_Attribute[] $attributes */
    private static function combineAttributes($attributes)
    {
        $out = [];
        foreach ($attributes as $attribute) {
            if (!array_key_exists($attribute->get_id(), self::$attributes)) {
                self::$attributes[$attribute->get_id()] = [];
                foreach (get_terms($attribute->get_name()) as $term) {
                    self::$attributes[$attribute->get_id()][$term->term_id] = $term->name;
                }
            }

            $out[$attribute->get_name()] = array_map(
                static function ($option) use ($attribute) {
                    return self::$attributes[$attribute->get_id()][$option];
                },
                $attribute->get_options()
            );
        }

        return $out;
    }

    private static function getCategories()
    {
        $categories = [];
        /** @var WP_Term $category */
        foreach (get_terms('product_cat') as $category) {
            $categories[$category->term_id] = $category;
        }

        $output = [];
        foreach ($categories as $cid => $category) {
            $output[$cid] = self::getCategoryPathRecursive($cid, $categories);
        }

        return $output;
    }

    private static function getCategoryPathRecursive($categoryId, $categories, $breadcrumbs = [])
    {
        if (!isset($categories[$categoryId])) {
            return implode(' >> ', array_reverse($breadcrumbs));
        }

        $breadcrumbs[] = $categories[$categoryId]->name;

        return self::getCategoryPathRecursive($categories[$categoryId]->parent, $categories, $breadcrumbs);
    }
}
