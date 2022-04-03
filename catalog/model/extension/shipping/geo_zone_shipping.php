<?php
class ModelExtensionShippingGeoZoneShipping extends Model {

	function getQuote($address) {

		$this->load->language('extension/shipping/geo_zone_shipping');

		$shipping_methods = $this->config->get('shipping_geo_zone_shipping_methods');

		$cart_total = $this->cart->getSubTotal();
		$cart_weight = $this->cart->getWeight();

		$quote_data = array();

		foreach($shipping_methods as $key => $method){

			// if method is enable
			if($method['status']){

				if(($method['cost_type'] == 'flat' && $cart_total >= (float)$method['total'])
					|| ($method['cost_type'] == 'price' && $cart_total >= (float)$method['price'])
					|| ($method['cost_type'] == 'weight' && $cart_weight >= (float)$method['weight'])) {

					$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . (int)$method['geo_zone_id'] . "' AND country_id = '" . (int)$address['country_id'] . "' AND (zone_id = '" . (int)$address['zone_id'] . "' OR zone_id = '0')");

					if (!$method['geo_zone_id']) {
						$status = true;
					} elseif ($query->num_rows) {
						$status = true;
					} else {
						$status = false;
					}

					if($status){
						$method_name = $method['name'][$this->config->get('config_language_id')];
						$method_code = $key; // strtolower(preg_replace('/[^\da-z]/i', '', $method_name));
						$method_cost = 0;

						if($method['cost_type'] == 'flat'){
							$method_cost = $method['flat_cost'];
						}


						else if($method['cost_type'] == 'price'){
							$price_cost = explode(",", $method['price_cost']);
							foreach($price_cost as $pcost){
								$pc = explode(":", $pcost);

								if(!isset($pc[1])) continue;
								
								$price = $pc[0];
								$rate = $pc[1];
								$method_cost = $rate;
								
								if($price >= $cart_total){
									break;
								}
							}

							// just for debugging purpose
							// $method_name .= '<!-- (Cart Total: ' . $cart_total .') -->';
						}


						else if($method['cost_type'] == 'weight'){
							$weight_cost = explode(",", $method['weight_cost']);
							foreach($weight_cost as $wcost){
								$wc = explode(":", $wcost);

								if(!isset($wc[1])) continue;
								
								$weight = $wc[0];
								$rate = $wc[1];
								$method_cost = $rate;
								
								if($weight >= $cart_weight){
									break;
								}
							}

							// just for debugging purpose
							// $method_name .= '<!-- (Weight: ' . $cart_weight .') -->';
						}

						else {
							// no method found yet? seems something wrong, continue for other methods
							continue;
						}


						$title = $this->currency->format($this->tax->calculate($method_cost, $method['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);

						if($method['instruction'][$this->config->get('config_language_id')]){
							 $title .= ' (' . $method['instruction'][$this->config->get('config_language_id')].')';
						}

						$quote_data[$method_code] = array(
							'code'         => 'geo_zone_shipping.'.$method_code,
							'title'        => $method_name,
							'cost'         => $method_cost,
							'tax_class_id' => $method['tax_class_id'],
							'text'         => $title
						);
					}
				}
			}
		}

		$method_data = array();

		if ($quote_data) {
			$method_data = array(
				'code'       => 'geo_zone_shipping',
				'title'      => '',
				'quote'      => $quote_data,
				'sort_order' => $this->config->get('shipping_geo_zone_shipping_sort_order'),
				'error'      => false
			);
		}

		return $method_data;
	}
}