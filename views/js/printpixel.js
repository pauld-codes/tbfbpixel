/*
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
 *  @author PrestaShop SA <contact@prestashop.com>
 *  @copyright  2007-2017 PrestaShop SA
 *  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */

$(document).ready(function(){

  function ajaxGetProduct(id, attribute) {
   $.ajax({
       type: 'POST',
       url: pixel_fc,
       dataType: 'json',
       data: {
          action: 'GetProduct',
          ajax: true,
          id_product: id,
          id_attribute: attribute,
       },
       success: function(data) {
          var iso_code = currency.iso_code,
          amount = data.price_amount;

          fbq('track', 'AddToCart', {value: amount, currency: iso_code, content_ids: data.id_product, content_type: "product"});
       },
       error: function(err) {
       }
   });
  }
  
	$(document).on('click', '.ajax_add_to_cart_button', function(e){
		//e.preventDefault();
		var idProduct =  parseInt($(this).data('id-product'));
		var idProductAttribute =  parseInt($(this).data('id-product-attribute'));
		var minimalQuantity =  parseInt($(this).data('minimal_quantity'));
		if (!minimalQuantity)
		minimalQuantity = 1;
		if ($(this).prop('disabled') != 'disabled')
		{
		ajaxGetProduct(idProduct, idProductAttribute);
		
		fbq('track', 'AddToCart', { value: productPrice, currency: currency.iso_code, content_ids: id_product, content_type: "product" });
		}
	});
		
		//for product page 'add' button...
	$(document).on('click', '#add_to_cart', function(e){
		//e.preventDefault();
		fbq('track', 'AddToCart', { value: productPrice, currency: currency.iso_code, content_ids: id_product, content_type: "product" });
	});
});
