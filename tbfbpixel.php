<?php
/**
* 2018 PaulD.codes
* 2007-2017 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2016 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

if (!defined('_TB_VERSION_')) {
    exit;
}

class Tbfbpixel extends Module
{
    protected $js_path = null;
    protected $front_controller = null;
	private $pixel_id = null;
	private $cached = false;
	private $pixelLocation = 'https://connect.facebook.net/en_US/fbevents.js';

    public function __construct()
    {
        $this->name = 'tbfbpixel';
        $this->author = 'PaulD';
        $this->tab = 'analytics_stats';
        $this->version = '0.9';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Facebook Pixel integration for thirty bees');
        $this->description = $this->l('This module allows you to implement an FB analysis tool into your website pages and track some events');

        $this->js_path = 'modules/'.$this->name.'/views/js/';
        $this->front_controller = Context::getContext()->link->getModuleLink(
            $this->name,
            'FrontAjaxPixel',
            array(),
            true
        );
    }

    public function install()
    {
        //$this->_clearCache('*');
        Configuration::updateValue('TB_FB_PIXEL', '');

        return parent::install()
          && $this->registerHook('header')
          && $this->registerHook('displayPayment')
          && $this->registerHook('displayOrderConfirmation')
          && $this->registerHook('actionFrontControllerSetMedia')
          && $this->registerHook('actionAjaxDieProductControllerdisplayAjaxQuickviewBefore')
        ;
    }

    public function uninstall()
    {
        return parent::uninstall();
    }
	
	private function GetPixelId()
	{
		if ($this->cached === false && empty($this->pixel_id))
		{
			$this->pixel_id = Configuration::get('TB_FB_PIXEL');
			$this->cached = true;
		}
		
		return $this->pixel_id;
	}

    private function postProcess()
    {
        if (((bool)Tools::isSubmit('submitTbFbPixel')) === true) {
            $id_pixel = pSQL(trim(Tools::getValue('TB_FB_PIXEL')));
            if (empty($id_pixel)) {
                return  $this->displayError(
                    $this->l('Your ID Pixel can not be empty')
                );
            } elseif (Tools::strlen($id_pixel) < 15 || Tools::strlen($id_pixel) > 16) {
                return  $this->displayError(
                    $this->l('Your ID Pixel must be 16 characters long')
                );
            } else {
                Configuration::updateValue('TB_FB_PIXEL', $id_pixel);
                return $this->displayConfirmation(
                    $this->l('Your ID Pixel have been updated.')
                );
            }
        }
    }

    public function getContent()
    {
        // Set JS
        $this->context->controller->addJs(array(
            $this->_path.'views/js/conf.js',
        ));

        $is_submit = $this->postProcess();

        $this->context->smarty->assign(array(
            'is_submit'          => $is_submit,
            'module_name'        => $this->name,
            'module_version'     => $this->version,
            'debug_mode'         => (int) _PS_MODE_DEV_,
            'module_display'     => $this->displayName,
            'multishop'          => (int) Shop::isFeatureActive(),
            'id_pixel'           => pSQL(Configuration::get('TB_FB_PIXEL')),
        ));

        return $is_submit.$this->display(__FILE__, 'views/templates/admin/configuration.tpl');
    }

    /*
    ** Hook's Managment
    */
    public function hookActionFrontControllerSetMedia()
    {
        if (empty($this->GetPixelId())) {
            return;
        }

        // Asset Manager
        $this->context->controller->addJS($this->js_path.'printpixel.js');
    }

    // Handle Payment module (AddPaymentInfo)
    public function hookDisplayPayment($params)
    {
      if (empty($this->GetPixelId())) {
        return;
      }

      $items_id = array();
      $items = $params['cart']->getProducts();
      foreach ($items as &$item) {
          $items_id[] = $item['reference'];
      }
      unset($items, $item);

      $iso_code = pSQL($this->context->currency->iso_code);
      $content = array(
        'value' => Tools::ps_round($params['cart']->getOrderTotal(), 2),
        'currency' => $iso_code,
        'content_type' => 'product',
        'content_ids' => $items_id,
        'num_items' => $params['cart']->nbProducts(),
      );

      $content = $this->formatPixel($content);

      $this->context->smarty->assign(array(
        'type' => 'AddPaymentInfo',
        'content' => $content,
      ));

      return $this->display(__FILE__, 'views/templates/hook/displaypixel.tpl');
    }

    // Set Pixel (ViewContent / ViewCategory / ViewCMS / Search / InitiateCheckout)
    public function hookHeader($params)
    {
        if (empty($this->GetPixelId())) {
            return;
        }

        // Asset Manager to be sure the JS is loaded
        $this->context->controller->addJS(
            $this->js_path.'printpixel.js'
        );

        $type = '';
        $content = array();

        $page = $this->context->controller->php_self;
        if (empty($page)) {
            $page = Tools::getValue('controller');
        }

        // front || modulefront 
        $controller_type = $this->context->controller->controller_type;

        $id_lang = (int)$this->context->language->id;
        $locale = Tools::strtoupper($this->context->language->iso_code);
        $iso_code = $this->context->currency->iso_code;
        $content_type = 'product';

        $track = 'track';
        /**
        * Triggers ViewContent product pages
        */
        if ($page === 'product' /*&& isset($this->context->controller->product)*/) {
            $type = 'ViewContent';
            $prods = $this->context->controller->product;
			
            if (isset($prods->attributes) && count($prods->attributes) > 0) {
                $content_type = 'product_group';
            }

            $content = array(
              'content_name' => Tools::replaceAccentedChars($prods->name) .' ('.$locale.')',
              'content_ids' => $prods->reference,
              'content_type' => $content_type,
              'value' => (float)$prods->price,
              'currency' => $iso_code,
            );
        }
        /**
        * Triggers ViewContent for category pages
        */
        elseif ($page === 'category' && $controller_type === 'front') {
            $type = 'ViewCategory';
            //$category = $this->context->controller->getCategory();

			//die(var_dump($this->context->controller));
			
            //$breadcrumbs = $this->context->controller->getBreadcrumbLinks();
            //$breadcrumb = implode(' > ', array_column($breadcrumbs['links'], 'title'));

            $track = 'trackCustom';

            $content = array(
              'content_name' => Tools::replaceAccentedChars($this->context->controller->category->name) .' ('.$locale.')',
              //'content_category' => Tools::replaceAccentedChars($breadcrumb),
              'content_ids' => array_column($this->context->controller->cat_products, 'reference'),
              'content_type' => $content_type,
            );
        }
        /**
        * Triggers ViewContent for custom module
        */
        elseif ($controller_type === 'modulefront' && isset($this->context->controller->module->name)) {
            $name = Tools::ucfirst($this->context->controller->module->name);
            $type = 'View'.$name.Tools::ucfirst($page);

            $track = 'trackCustom';
            $content = array();
        }
        /**
        * Triggers ViewContent for cms pages
        */
        elseif ($page === 'cms' && $page->cms != null) {
            $type = 'ViewCMS';
            $cms = new Cms((int)Tools::getValue('id_cms'), $id_lang);

            //$breadcrumbs = $this->context->controller->getBreadcrumbLinks();
            //$breadcrumb = implode(' > ', array_column($breadcrumbs['links'], 'title'));
            $track = 'trackCustom';

            $content = array(
              //'content_category' => Tools::replaceAccentedChars($breadcrumb),
              'content_name' => Tools::replaceAccentedChars($cms->meta_title) .' ('.$locale.')',
            );
        }
        /**
        * Triggers Search for result pages
        */
        elseif ($page === 'search') {
            $type = Tools::ucfirst($page);
			
            $content = array(
              'search_string' => pSQL(Tools::getValue('search_query')),
            );
        }
        /**
        * Triggers InitiateCheckout for checkout page
        */
        elseif ($page === 'cart' || $page === 'order-opc' || $page === 'order') {
            $type = 'InitiateCheckout';

            $content = array(
              'num_items' => $this->context->cart->nbProducts(),
              'content_ids' => array_column($this->context->cart->getProducts(), 'reference'),
              'content_type' => $content_type,
              'value' => (float)$this->context->cart->getOrderTotal(),
              'currency' => $iso_code,
            );
        }

        // Format Pixel to display
        $content = $this->formatPixel($content);

        Media::addJsDef(array(
            'pixel_fc' => $this->front_controller
        ));
		
		if (file_exists(dirname(__FILE__) . '/views/js/tbf.js'))
			// multishop?
			$this->pixelLocation =  $this->context->link->getPageLink('index', true) . 'modules/' . $this->name . '/views/js/tbf.js';
		
        $this->context->smarty->assign(array(
          'id_pixel' => pSQL(Configuration::get('TB_FB_PIXEL')),
          'location' => $this->pixelLocation,
          'type' => $type,
          'content' => $content,
          'track' => $track,
        ));

        return $this->display(__FILE__, 'views/templates/hook/header.tpl');
    }

    // Handle QuickView (ViewContent)
    public function hookActionAjaxDieProductControllerdisplayAjaxQuickviewBefore($params)
    {
        if (empty($this->GetPixelId())) {
            return;
        }

        // Decode Product Object
        $value = Tools::jsonDecode($params['value']);
        $locale = pSQL(Tools::strtoupper($this->context->language->iso_code));
        $iso_code = pSQL($this->context->currency->iso_code);

        $content = array(
          'content_name' => Tools::replaceAccentedChars($value->product->name) .' ('.$locale.')',
          'content_ids' => array($value->product->reference),
          'content_type' => 'product',
          'value' => (float)$value->product->price_amount,
          'currency' => $iso_code,
        );
        $content = $this->formatPixel($content);

        $this->context->smarty->assign(array(
          'type' => 'ViewContent',
          'content' => $content,
        ));

        $value->quickview_html .= $this->context->smarty->fetch(
            $this->local_path.'views/templates/hook/displaypixel.tpl'
        );

        // Recode Product Object
        $params['value'] = Tools::jsonEncode($value);

        die($params['value']);
    }

    // Handle Display confirmation (Purchase)
    public function hookDisplayOrderConfirmation($params)
    {
        if (empty($this->GetPixelId())) {
            return;
        }

        $order = $params['objOrder'];

        $num_items = 0;
        $items_id = array();
        $items = $order->getProductsDetail();
        foreach ($items as $item) {
            $num_items += (int)$item['product_quantity'];
            $items_id[] = $item['product_reference'];
        }
        unset($items, $item);

        $iso_code = pSQL($this->context->currency->iso_code);

        $content = array(
          'value' => Tools::ps_round($order->total_paid, 2),
          'currency' => $iso_code,
          'content_type' => 'product',
          'content_ids' => $items_id,
          'order_id' => $order->id,
          'num_items' => $num_items,
        );

        $content = $this->formatPixel($content);

        $this->context->smarty->assign(array(
          'type' => 'Purchase',
          'content' => $content,
        ));

        return $this->display(__FILE__, 'views/templates/hook/displaypixel.tpl');
    }

    // Format you pixel
    private function formatPixel($params)
    {
        if (!empty($params)) {
            $format = '{';
            foreach ($params as $key => &$val) {
                if (gettype($val) === 'string') {
                    $format .= $key.': \''.addslashes($val).'\', ';
                } elseif (gettype($val) === 'array') {
                    $format .= $key.': [\'';
                    foreach ($val as &$id) {
                        $format .= /*(int)*/$id."', '";
                    }
                    unset($id);
                    $format = Tools::substr($format, 0, -4);
                    $format .= '\'], ';
                } else {
                    $format .= $key.': '.addslashes($val).', ';
                }
            }
            unset($params, $key, $val);

            $format = Tools::substr($format, 0, -2);
            $format .= '}';

            return $format;
        }
        return false;
    }
}
