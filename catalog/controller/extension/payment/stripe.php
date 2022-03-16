<?php
//==============================================================================
// Stripe Payment Gateway v303.2
// 
// Author: Clear Thinking, LLC
// E-mail: johnathan@getclearthinking.com
// Website: http://www.getclearthinking.com
// 
// All code within this file is copyright Clear Thinking, LLC.
// You may not copy or reuse code within this file without written permission.
//==============================================================================

class ControllerExtensionPaymentStripe extends Controller {
	private $type = 'payment';
	private $name = 'stripe';
	
	public function logFatalErrors() {
		$error = error_get_last();
		if ($error['type'] === E_ERROR) { 
			$this->log->write('STRIPE PAYMENT GATEWAY: Order could not be completed due to the following fatal error:');
			$this->log->write('PHP Fatal Error:  ' . $error['message'] . ' in ' . $error['file'] . ' on line ' . $error['line']);
		}
	}
	
	//==============================================================================
	// index()
	//==============================================================================
	public function index() {
		register_shutdown_function(array($this, 'logFatalErrors'));
		
		$data['type'] = $this->type;
		$data['name'] = $this->name;
		
		$data['settings'] = $settings = $this->getSettings();
		$data['language'] = $this->session->data['language'];
		$data['currency'] = $this->session->data['currency'];
		
		$main_currency = $this->db->query("SELECT * FROM " . DB_PREFIX . "setting WHERE `key` = 'config_currency' AND store_id = 0")->row['value'];
		$decimal_factor = (in_array($data['currency'], array('BIF','CLP','DJF','GNF','JPY','KMF','KRW','MGA','PYG','RWF','VND','VUV','XAF','XOF','XPF'))) ? 1 : 100;
		
		$data['error'] = '';
		$data['checkout_success_url'] = $this->url->link('checkout/success', '', 'SSL');
		
		$data['stripe_errors'] = array(
			'card_declined',
			'expired_card',
			'incorrect_cvc',
			'incorrect_number',
			'incorrect_zip',
			'invalid_cvc',
			'invalid_expiry_month',
			'invalid_expiry_year',
			'invalid_number',
			'missing',
			'processing_error',
		);
		
		// Get order info
		$order_id = $this->session->data['order_id'];
		
		$this->load->model('checkout/order');
		$order_info = $data['order_info'] = $this->model_checkout_order->getOrder($order_id);
		
		// Find stripe_customer_id
		$data['customer'] = array();
		$data['logged_in'] = $this->customer->isLogged();
		$stripe_customer_id = '';
		
		if ($data['logged_in']) {
			$customer_id_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "stripe_customer WHERE customer_id = " . (int)$this->customer->getId() . " AND transaction_mode = '" . $this->db->escape($settings['transaction_mode']) . "'");
			
			if ($customer_id_query->num_rows) {
				$stripe_customer_id = $customer_id_query->row['stripe_customer_id'];
				
				if ($settings['allow_stored_cards']) {
					$payment_methods = $this->curlRequest('GET', 'payment_methods', array('customer' => $stripe_customer_id, 'type' => 'card'));
					
					if (!empty($payment_methods['error'])) {
						$this->log->write('STRIPE PAYMENT GATEWAY: ' . $payment_methods['error']['message']);
					} elseif ($data['settings']['allow_stored_cards']) {
						$data['customer_cards'] = $payment_methods['data'];
					}
					
					$customer_response = $this->curlRequest('GET', 'customers/' . $stripe_customer_id);
					
					$data['default_card'] = (!empty($customer_response['invoice_settings']['default_payment_method'])) ? $customer_response['invoice_settings']['default_payment_method'] : '';
				}
			}
		}
		
		// Render
		$theme = (version_compare(VERSION, '2.2', '<')) ? $this->config->get('config_template') : str_replace('theme_', '', $this->config->get('config_theme'));
		$template = (file_exists(DIR_TEMPLATE . $theme . '/template/extension/' . $this->type . '/' . $this->name . '.twig')) ? $theme : 'default';
		$template_file = DIR_TEMPLATE . $template . '/template/extension/' . $this->type . '/' . $this->name . '.twig';
		
		if (is_file($template_file)) {
			extract($data);
			
			ob_start();
			require(class_exists('VQMod') ? VQMod::modCheck(modification($template_file)) : modification($template_file));
			$output = ob_get_clean();
			
			return $output;
		} else {
			return 'Error loading template file';
		}
	}
	
	//==============================================================================
	// getSubscriptionPlans()
	//==============================================================================
	private function getSubscriptionPlans($settings, $order_info) {
		if (!empty($this->session->data[$this->name . '_plans'])) {
			return $this->session->data[$this->name . '_plans'];
		}
		
		$plans = array();
		
		if (empty($settings['subscriptions'])) {
			return $plans;
		}
		
		$cart_products = $this->cart->getProducts();
		$currency = $this->session->data['currency'];
		$decimal_factor = (in_array($settings['currencies_' . $currency], array('BIF','CLP','DJF','GNF','JPY','KMF','KRW','MGA','PYG','RWF','VND','VUV','XAF','XOF','XPF'))) ? 1 : 100;
		
		foreach ($cart_products as $product) {
			$plan_id = '';
			$start_date = '';
			$product_name = $product['name'];
			
			$product_info = $this->db->query("SELECT * FROM " . DB_PREFIX . "product WHERE product_id = " . (int)$product['product_id'])->row;
			if (!empty($product_info['location'])) {
				$plan_id = trim($product_info['location']);
			}
			
			if (empty($plan_id)) continue;
			
			// Get plan info
			$plan_response = $this->curlRequest('GET', 'plans/' . $plan_id);
			
			if (!empty($plan_response['error'])) continue;
			
			$plan_tax_rate = $this->tax->getTax($product['total'], $product['tax_class_id']) / $product['total'];
			
			// Add plan to array
			$plans[] = array(
				'cost'					=> $plan_response['amount'] / $decimal_factor,
				'id'					=> $plan_response['id'],
				'name'					=> (!empty($plan_response['nickname'])) ? $plan_response['nickname'] : $plan_response['id'],
				'product_id'			=> $product['product_id'],
				'product_key'			=> $product[version_compare(VERSION, '2.1', '<') ? 'key' : 'cart_id'],
				'product_name'			=> $product_name,
				'quantity'				=> $product['quantity'],
				'start_date'			=> $start_date,
				'taxed_cost'			=> $plan_response['amount'] / $decimal_factor * (1 + $plan_tax_rate),
				'tax_percent'			=> $plan_tax_rate * $decimal_factor,
				'trial'					=> $plan_response['trial_period_days'],
				'shipping_cost'			=> 0,
				'taxed_shipping_cost'	=> 0,
			);
		}
		
		$this->session->data[$this->name . '_plans'] = $plans;
		return $plans;
	}
	
	//==============================================================================
	// displayError()
	//==============================================================================
	public function displayError($message) {
		if (!empty($this->request->get['source'])) {
			$settings = $this->getSettings();
			$language = (isset($this->session->data['language'])) ? $this->session->data['language'] : $this->config->get('config_language');
			
			$header = $this->load->controller('common/header');
			$footer = $this->load->controller('common/footer');
			
			$error_page = html_entity_decode($settings['error_page_' . $language], ENT_QUOTES, 'UTF-8');
			$error_page = str_replace(array('[header]', '[error]', '[footer]'), array($header, $message, $footer), $error_page);
			
			echo $error_page;
		} elseif (empty($this->request->post['payment_intent'])) {
			echo json_encode(array('error' => $message));
		} else {
			echo $message;
		}
	}
	
	//==============================================================================
	// createPaymentIntent()
	//==============================================================================
	public function createPaymentIntent() {
		$settings = $this->getSettings();
		
		// Get order data
		$this->load->model('checkout/order');
		
		$order_id = $this->session->data['order_id'];
		$order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
		
		// Get subscription plan data
		$customer_id = $this->customer->getId();
		
		$plans = $this->getSubscriptionPlans($settings, $order_info);
		
		// Check if payment is a non-card method
		if (empty($this->request->get['source'])) {
			$payment_type = 'card';
			$store_card = (!empty($plans) || (isset($this->request->post['store_card']) && $this->request->post['store_card'] == 'true') || $settings['send_customer_data'] == 'always');
		} else {
			$payment_type = $this->request->get['payment_type'];
			$store_card = false;
		}
		
		if (!empty($plans) && $settings['prevent_guests'] && !$customer_id) {
			$this->displayError($settings['text_customer_required_' . $language]);
			return;
		}
		
		// Set up billing address and shipping info
		$billing_address = array(
			'line1'			=> trim(html_entity_decode($order_info['payment_address_1'], ENT_QUOTES, 'UTF-8')),
			'line2'			=> trim(html_entity_decode($order_info['payment_address_2'], ENT_QUOTES, 'UTF-8')),
			'city'			=> trim(html_entity_decode($order_info['payment_city'], ENT_QUOTES, 'UTF-8')),
			'state'			=> trim(html_entity_decode($order_info['payment_zone'], ENT_QUOTES, 'UTF-8')),
			'postal_code'	=> trim(html_entity_decode($order_info['payment_postcode'], ENT_QUOTES, 'UTF-8')),
			'country'		=> trim(html_entity_decode($order_info['payment_iso_code_2'], ENT_QUOTES, 'UTF-8')),
		);
		
		if (empty($order_info['shipping_firstname'])) {
			$shipping_info = array();
		} else {
			$shipping_info = array(
				'name'		=> trim(html_entity_decode($order_info['shipping_firstname'] . ' ' . $order_info['shipping_lastname'], ENT_QUOTES, 'UTF-8')),
				'phone'		=> trim(html_entity_decode($order_info['telephone'], ENT_QUOTES, 'UTF-8')),
				'address'	=> array(
					'line1'			=> trim(html_entity_decode($order_info['shipping_address_1'], ENT_QUOTES, 'UTF-8')),
					'line2'			=> trim(html_entity_decode($order_info['shipping_address_2'], ENT_QUOTES, 'UTF-8')),
					'city'			=> trim(html_entity_decode($order_info['shipping_city'], ENT_QUOTES, 'UTF-8')),
					'state'			=> trim(html_entity_decode($order_info['shipping_zone'], ENT_QUOTES, 'UTF-8')),
					'postal_code'	=> trim(html_entity_decode($order_info['shipping_postcode'], ENT_QUOTES, 'UTF-8')),
					'country'		=> trim(html_entity_decode($order_info['shipping_iso_code_2'], ENT_QUOTES, 'UTF-8')),
				),
			);
		}
		
		// Create or update customer
		$customer_id_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "stripe_customer WHERE customer_id = " . (int)$customer_id . " AND transaction_mode = '" . $this->db->escape($settings['transaction_mode']) . "'");
		$stripe_customer_id = (!empty($customer_id_query->row['stripe_customer_id'])) ? $customer_id_query->row['stripe_customer_id'] : '';
		
		if ($store_card) {
			$customer_data = array(
				'address'		=> $billing_address,
				'description'	=> $order_info['firstname'] . ' ' . $order_info['lastname'] . ' (' . 'customer_id: ' . $order_info['customer_id'] . ')',
				'email'			=> $order_info['email'],
				'name'			=> $order_info['firstname'] . ' ' . $order_info['lastname'],
				'phone'			=> $order_info['telephone'],
				'shipping'		=> $shipping_info,
			);
			
			$customer_response = $this->curlRequest('POST', 'customers' . ($stripe_customer_id ? '/' . $stripe_customer_id : ''), $customer_data);
			
			if (!empty($customer_response['error'])) {
				$this->displayError($customer_response['error']['message']);
				return;
			} else {
				if (!$stripe_customer_id && $this->customer->isLogged()) {
					$this->db->query("INSERT INTO " . DB_PREFIX . "stripe_customer SET customer_id = " . (int)$customer_id . ", stripe_customer_id = '" . $this->db->escape($customer_response['id']) . "', transaction_mode = '" . $this->db->escape($settings['transaction_mode']) . "'");
				}
				$stripe_customer_id = $customer_response['id'];
			}
		}
		
		// Calculate amount
		$currency = $this->session->data['currency'];
		$main_currency = $this->db->query("SELECT * FROM " . DB_PREFIX . "setting WHERE `key` = 'config_currency' AND store_id = 0")->row['value'];
		$decimal_factor = (in_array($settings['currencies_' . $currency], array('BIF','CLP','DJF','GNF','JPY','KMF','KRW','MGA','PYG','RWF','VND','VUV','XAF','XOF','XPF'))) ? 1 : 100;
		
		$amount = $order_info['total'];
		
		foreach ($plans as $plan) {
			$amount -= $plan['taxed_cost'] * $plan['quantity'];
			$amount -= $plan['taxed_shipping_cost'];
		}
		
		// Set up payment intent data
		$json = array(
			'status'			=> '',
			'payment_intent_id'	=> '',
		);
		
		if ($amount >= 0.5) {
			$metadata['Store'] = $this->config->get('config_name');
			$metadata['Order ID'] = $order_info['order_id'];
			$metadata['Customer Info'] = $order_info['firstname'] . ' ' . $order_info['lastname'] . ', ' . $order_info['email'] . ', ' . $order_info['telephone'] . ', customer_id: ' . $order_info['customer_id'];
			$metadata['Products'] = $this->replaceShortcodes('[products]', $order_info);
			$metadata['Order Comment'] = $order_info['comment'];
			$metadata['IP Address'] = $order_info['ip'];
			foreach ($metadata as &$md) {
				if (strlen($md) > 197) {
					$md = mb_substr($md, 0, 197, 'UTF-8') . '...';
				}
			}
			
			$curl_data = array(
				'amount'				=> round($decimal_factor * $this->currency->convert($amount, $main_currency, $currency)),
				'currency'				=> strtolower($currency),
				'capture_method'		=> 'manual',
				'confirm'				=> 'true',
				'confirmation_method'	=> 'manual',
				'description'			=> $this->replaceShortcodes($settings['transaction_description'], $order_info),
				'metadata'				=> $metadata,
				'payment_method_types'	=> array($payment_type),
				'save_payment_method'	=> ($store_card) ? 'true' : 'false',
				'shipping'				=> $shipping_info,
			);
			
			if ($stripe_customer_id) {
				$curl_data['customer'] = $stripe_customer_id;
			}
			
			if ($payment_type == 'card') {
				$curl_data['payment_method'] = $this->request->post['payment_method'];
			} else {
				$curl_data['source'] = $this->request->get['source'];
			}
			
			if ($settings['always_send_receipts']) {
				$curl_data['receipt_email'] = $order_info['email'];
			}
			
			// Create payment intent
			$payment_intent = $this->curlRequest('POST', 'payment_intents', $curl_data);
			
			if (!empty($payment_intent['error'])) {
				$this->displayError($payment_intent['error']['message']);
				return;
			} elseif ($payment_intent['status'] == 'requires_payment_method') {
				$this->displayError('Missing payment method');
				return;
			} else {
				$json = array(
					'client_secret'		=> $payment_intent['client_secret'],
					'payment_intent_id'	=> $payment_intent['id'],
					'status'			=> $payment_intent['status'],
				);
			}
		} elseif ($store_card) {
			// Add payment method to customer
			$attach_response = $this->curlRequest('POST', 'payment_methods/' . $this->request->post['payment_method'] . '/attach', array('customer' => $stripe_customer_id));
			
			if (!empty($attach_response['error'])) {
				// ignore any errors
			}
		}
		
		// Set new payment method to default
		if ($store_card && empty($json['error'])) {
			$customer_data = array(
				'invoice_settings'	=> array(
					'default_payment_method'	=> $this->request->post['payment_method'],
				),
			);
			
			$make_default_response = $this->curlRequest('POST', 'customers/' . $stripe_customer_id, $customer_data);
			
			if (!empty($make_default_response['error'])) {
				$this->displayError($make_default_response['error']['message']);
				return;
			}
		}
		
		// Return data
		if ($payment_type == 'card') {
			echo json_encode($json);
		} else {
			$this->request->post['payment_intent'] = $payment_intent['id'];
			$this->chargePayment();
		}
	}
	
	//==============================================================================
	// chargePayment()
	//==============================================================================
	public function chargePayment() {
		register_shutdown_function(array($this, 'logFatalErrors'));
		unset($this->session->data[$this->name . '_order_error']);
		
		$settings = $this->getSettings();
		
		$language_data = $this->load->language(version_compare(VERSION, '2.3', '<') ? 'total/total' : 'extension/total/total');
		$language = (isset($this->session->data['language'])) ? $this->session->data['language'] : $this->config->get('config_language');
		$currency = $this->session->data['currency'];
		$main_currency = $this->db->query("SELECT * FROM " . DB_PREFIX . "setting WHERE `key` = 'config_currency' AND store_id = 0")->row['value'];
		$decimal_factor = (in_array($settings['currencies_' . $currency], array('BIF','CLP','DJF','GNF','JPY','KMF','KRW','MGA','PYG','RWF','VND','VUV','XAF','XOF','XPF'))) ? 1 : 100;
		
		// Get order data
		$this->load->model('checkout/order');
		
		$order_id = $this->session->data['order_id'];
		$order_info = $this->model_checkout_order->getOrder($order_id);
		
		// Get customer data
		$customer_id = $this->customer->getId();
		$customer_id_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "stripe_customer WHERE customer_id = " . (int)$customer_id . " AND transaction_mode = '" . $this->db->escape($settings['transaction_mode']) . "'");
		$stripe_customer_id = (!empty($customer_id_query->row['stripe_customer_id'])) ? $customer_id_query->row['stripe_customer_id'] : '';
		
		$customer_response = $this->curlRequest('GET', 'customers' . ($stripe_customer_id ? '/' . $stripe_customer_id : ''));
		
		// Get payment intent data
		$payment_intent_id = (isset($this->request->post['payment_intent'])) ? $this->request->post['payment_intent'] : '';
		
		if ($payment_intent_id) {
			$payment_intent = $this->curlRequest('GET', 'payment_intents/' . $payment_intent_id);
			
			if (!empty($payment_intent['error'])) {
				$this->displayError($payment_intent['error']['message']);
				return;
			} else {
				// Re-confirm payment intent if necessary
				if ($payment_intent['status'] == 'requires_confirmation') {
					$confirm_response = $this->curlRequest('POST', 'payment_intents/' . $payment_intent_id . '/confirm');
					
					if (!empty($confirm_response['error'])) {
						$this->displayError($confirm_response['error']['message']);
						return;
					} else {
						$payment_intent = $confirm_response;
					}
				}
			}
		}
		
		// Subscribe customer to plans
		$plans = $this->getSubscriptionPlans($settings, $order_info);
		unset($this->session->data[$this->name . '_plans']);
		
		foreach ($plans as &$plan) {
			$subscription_data = array(
				'customer'		=> $stripe_customer_id,
				'items'			=> array(array('plan' => $plan['id'], 'quantity' => $plan['quantity'])),
				'tax_percent'	=> $plan['tax_percent'],
				'metadata'		=> array(
					'order_id'		=> $order_id,
					'product_id'	=> $plan['product_id'],
					'product_name'	=> $plan['product_name'],
				),
			);
			
			if (isset($this->session->data['coupon'])) {
				$coupon_response = $this->curlRequest('GET', 'coupons/' . $this->session->data['coupon']);
				if (empty($coupon_response['error'])) {
					$subscription_data['coupon'] = $coupon_response['id'];
				}
			}
			
			$subscription_response = $this->curlRequest('POST', 'subscriptions', $subscription_data);
			
			if (!empty($subscription_response['error'])) {
				$this->displayError($subscription_response['error']['message']);
				return;
			}
			
			// Subtract out subscription costs
			$total_plan_cost = $plan['quantity'] * $plan['taxed_cost'] + $plan['taxed_shipping_cost'];
			$order_info['total'] -= $total_plan_cost;
			
			// Add extra plan data for later use
			$plan['total_plan_cost'] = $total_plan_cost;
			$plan['subscription_response'] = $subscription_response;
		}
		
		// Set base order_status_id and capture status
		$order_status_id = $settings['success_status_id'];
		$capture = ($settings['charge_mode'] == 'capture');
		
		// Check fraud data
		if ($settings['charge_mode'] == 'fraud') {
			if (version_compare(VERSION, '2.0.3', '<')) {
				if ($this->config->get('config_fraud_detection')) {
					$this->load->model('checkout/fraud');
					if ($this->model_checkout_fraud->getFraudScore($order_info) > $this->config->get('config_fraud_score')) {
						$capture = false;
					}
				}
			} else {
				$this->load->model('account/customer');
				$customer_info = $this->model_account_customer->getCustomer($order_info['customer_id']);
				
				if (empty($customer_info['safe'])) {
					$fraud_extensions = $this->db->query("SELECT * FROM " . DB_PREFIX . "extension WHERE `type` = 'fraud' ORDER BY `code` ASC")->rows;
					
					foreach ($fraud_extensions as $extension) {
						$prefix = (version_compare(VERSION, '3.0', '<')) ? '' : 'fraud_';
						if (!$this->config->get($prefix . $extension['code'] . '_status')) continue;
						
						if (version_compare(VERSION, '2.3', '<')) {
							$this->load->model('fraud/' . $extension['code']);
							$fraud_status_id = $this->{'model_fraud_' . $extension['code']}->check($order_info);
						} else {
							$this->load->model('extension/fraud/' . $extension['code']);
							$fraud_status_id = $this->{'model_extension_fraud_' . $extension['code']}->check($order_info);
						}
						
						if ($fraud_status_id) {
							$capture = false;
						}
					}
				}
			}
		}
		
		if (!$capture) {
			$order_status_id = $settings['authorize_status_id'];
		}
		
		// Capture payment intent if necessary
		$charge = (!empty($payment_intent['charges']['data'][0])) ? $payment_intent['charges']['data'][0] : array();
		
		if ($order_info['total'] >= 0.5 && $capture && empty($charge['captured'])) {
			$curl_data = array(
				'amount_to_capture'	=> round($decimal_factor * $this->currency->convert($order_info['total'], $main_currency, $currency)),
			);
			
			$capture_response = $this->curlRequest('POST', 'payment_intents/' . $payment_intent_id . '/capture', $curl_data);
			
			if (!empty($capture_response['error'])) {
				$this->displayError($capture_response['error']['message']);
				return;
			} else {
				$charge['captured'] = true;
			}
		}
		
		// Check verifications
		if (isset($charge['payment_method_details']['card']['checks'])) {
			$checks = $charge['payment_method_details']['card']['checks'];
			if ($settings['street_status_id'] && $checks['address_line1_check'] == 'fail')		$order_status_id = $settings['street_status_id'];
			if ($settings['zip_status_id'] && $checks['address_postal_code_check'] == 'fail')	$order_status_id = $settings['zip_status_id'];
			if ($settings['cvc_status_id'] && $checks['cvc_check'] == 'fail')					$order_status_id = $settings['cvc_status_id'];
		}
		
		// Create comment data
		$strong = '<strong style="display: inline-block; width: 180px; padding: 2px 5px">';
		$hr = '<hr style="margin: 5px">';
		$comment = '';
		
		foreach ($plans as $plan) {
			$subscription_response = $plan['subscription_response'];
			
			$comment .= $strong . 'Subscribed to Plan:</strong>' . $plan['name'] . '<br>';
			$comment .= $strong . 'Subscription Charge:</strong>' . $this->currency->format($plan['cost'], strtoupper($subscription_response['plan']['currency']), 1);
			
			if ($plan['taxed_cost'] != $plan['cost']) {
				$comment .= ' (Including Tax: ' . $this->currency->format($plan['taxed_cost'], strtoupper($subscription_response['plan']['currency']), 1) . ')';
			}
			
			if (!empty($plan['shipping_cost'])) {
				$comment .= '<br>' . $strong . 'Shipping Cost:</strong>' . $this->currency->format($plan['shipping_cost'], strtoupper($subscription_response['plan']['currency']), 1);
			}
			
			if (!empty($plan['start_date']) && strtotime($plan['start_date']) > time()) {
				$comment .= '<br>' . $strong . 'Start Date:</strong>' . $plan['start_date'];
			} elseif (!empty($plan['trial'])) {
				$comment .= '<br>' . $strong . 'Trial Days:</strong>' . $plan['trial'];
			}
			
			if (!empty($charge)) {
				$comment .= $hr;
			}
		}
		
		if (!empty($charge)) {
			$charge_amount = $charge['amount'] / $decimal_factor;
			$comment .= '<script type="text/javascript" src="view/javascript/stripe.js"></script>';
			
			// Universal fields
			$comment .= $strong . 'Stripe Payment ID:</strong>' . $payment_intent['id'] . '<br>';
			$comment .= $strong . 'Charge Amount:</strong>' . $this->currency->format($charge_amount, strtoupper($charge['currency']), 1) . '<br>';
			$comment .= $strong . 'Captured:</strong>' . (!empty($charge['captured']) ? 'Yes' : '<span>No &nbsp;</span> <a onclick="stripeCapture($(this), ' . number_format($charge_amount, 2, '.', '') . ', \'' . $payment_intent['id'] . '\')">(Capture)</a>') . '<br>';
			
			// Billing details
			if (!empty($charge['billing_details'])) {
				$comment .= $strong . 'Billing Details:</strong>' . $charge['billing_details']['name'] . '<br>';
				if (!empty($charge['billing_details']['address'])) {
					$comment .= $strong . '&nbsp;</strong>' . $charge['billing_details']['address']['line1'] . '<br>';
					if (!empty($card_address['line2'])) $comment .= $strong . '&nbsp;</strong>' . $charge['billing_details']['address']['line2'] . '<br>';
					$comment .= $strong . '&nbsp;</strong>' . $charge['billing_details']['address']['city']. ', ' .$charge['billing_details']['address']['state'] . ' ' . $charge['billing_details']['address']['postal_code'] . '<br>';
					if (!empty($card_address['country'])) $comment .= $strong . '&nbsp;</strong>' . $charge['billing_details']['address']['country'] . '<br>';
				}
				$comment .= $hr;
			}
			
			// Card fields
			if ($charge['payment_method_details']['type'] == 'card') {
				$card = $charge['payment_method_details']['card'];
				
				$comment .= $strong . 'Card Type:</strong>' . (!empty($card['description']) ? $card['description'] : ucwords($card['brand'])) . '<br>';
				$comment .= $strong . 'Card Number:</strong>**** **** **** ' . $card['last4'] . '<br>';
				$comment .= $strong . 'Card Expiry:</strong>' . $card['exp_month'] . ' / ' . $card['exp_year'] . '<br>';
				$comment .= $strong . 'Card Origin:</strong>' . $card['country'] . '<br>';
				$comment .= $hr;
				$comment .= $strong . 'CVC Check:</strong>' . $card['checks']['cvc_check'] . '<br>';
				$comment .= $strong . 'Street Check:</strong>' . $card['checks']['address_line1_check'] . '<br>';
				$comment .= $strong . 'Zip Check:</strong>' . $card['checks']['address_postal_code_check'] . '<br>';
				$comment .= $strong . '3D Secure:</strong>' . (!empty($card['three_d_secure']['succeeded']) ? 'success via ' . $card['three_d_secure']['version'] : 'not checked') . '<br>';
			}
			
			// Refund link
			$comment .= $hr;
			$comment .= $strong . 'Refund:</strong><a onclick="stripeRefund($(this), ' . number_format($charge_amount, 2, '.', '') . ', \'' . $charge['id'] . '\')">(Refund)</a>';
		}
		
		// Add order history
		$this->db->query("INSERT INTO " . DB_PREFIX . "order_history SET order_id = " . (int)$order_id . ", order_status_id = " . (int)$order_status_id . ", notify = 0, comment = '" . $this->db->escape($comment) . "', date_added = NOW()");
		
		// Subtract trialing subscriptions from order
		$prefix = (version_compare(VERSION, '3.0', '<')) ? '' : 'total_';
		
		foreach ($plans as $plan) {
			if ($plan['subscription_response']['status'] == 'trialing') {
				$this->db->query("UPDATE `" . DB_PREFIX . "order` SET total = " . (float)$order_info['total'] . " WHERE order_id = " . (int)$order_info['order_id']);
				$this->db->query("UPDATE " . DB_PREFIX . "order_total SET value = " . (float)$order_info['total'] . " WHERE order_id = " . (int)$order_info['order_id'] . " AND title = '" . $this->db->escape($language_data['text_total']) . "'");
				$this->db->query("INSERT INTO " . DB_PREFIX . "order_total SET order_id = " . (int)$order_info['order_id'] . ", code = 'total', title = '" . $this->db->escape($settings['text_to_be_charged_' . $language] . ' (' . $plan['name'] . ')') . "', value = " . (float)-$plan['total_plan_cost'] . ", sort_order = " . ((int)$this->config->get($prefix . 'total_sort_order')-1));
			}
		}
		
		// Payment is complete
		$this->session->data[$this->name . '_order_id'] = $order_id;
		$this->session->data[$this->name . '_order_status_id'] = $order_status_id;
		
		// Check 3D Secure for subscriptions
		if (!empty($subscription_response['latest_invoice'])) {
			$invoice_response = $this->curlRequest('GET', 'invoices/' . $subscription_response['latest_invoice']);
			
			if (!empty($invoice_response['payment_intent'])) {
				$payment_intent_response = $this->curlRequest('GET', 'payment_intents/' . $invoice_response['payment_intent']);
				
				if (empty($payment_intent_response['error'])) {
					echo $payment_intent_response['client_secret'];
				}
			}
		}
	}
	
	//==============================================================================
	// completeOrder()
	//==============================================================================
	public function completeOrder() {
		if (empty($this->session->data[$this->name . '_order_id'])) {
			echo 'No order data';
			return;
		}
		
		$order_id = $this->session->data[$this->name . '_order_id'];
		$order_status_id = $this->session->data[$this->name . '_order_status_id'];
		
		unset($this->session->data[$this->name . '_order_id']);
		unset($this->session->data[$this->name . '_order_status_id']);
		
		$this->session->data[$this->name . '_order_error'] = $order_id;
		
		$this->load->model('checkout/order');
		$this->model_checkout_order->addOrderHistory($order_id, $order_status_id);
	}
	
	//==============================================================================
	// completeWithError()
	//==============================================================================
	public function completeWithError() {
		if (empty($this->session->data[$this->name . '_order_error'])) {
			echo 'Payment was not processed';
			return;
		}
		
		$settings = $this->getSettings();
		
		$this->db->query("UPDATE `" . DB_PREFIX . "order` SET order_status_id = " . (int)$settings['error_status_id'] . ", date_modified = NOW() WHERE order_id = " . (int)$this->session->data[$this->name . '_order_error']);
		$this->db->query("INSERT INTO " . DB_PREFIX . "order_history SET order_id = " . (int)$this->session->data[$this->name . '_order_error'] . ", order_status_id = " . (int)$settings['error_status_id'] . ", notify = 0, comment = 'The order could not be completed normally due to the following error:<br><br><em>" . $this->db->escape($this->request->post['error_message']) . "</em><br><br>Double-check your SMTP settings in System > Settings > Mail, and then try disabling or uninstalling any modifications that affect customer orders (i.e. the /catalog/model/checkout/order.php file). One of those is usually the cause of errors like this.', date_added = NOW()");
		
		unset($this->session->data[$this->name . '_order_error']);
	}
	
	//==============================================================================
	// Webhook functions
	//==============================================================================
	public function webhook() {
		register_shutdown_function(array($this, 'logFatalErrors'));
		$settings = $this->getSettings();

		$event = @json_decode(file_get_contents('php://input'), true);
		
		if (empty($event['type'])) {
			echo 'Stripe Payment Gateway webhook is working.';
			return;
		}
		
		if (!isset($this->request->get['key']) || $this->request->get['key'] != md5($this->config->get('config_encryption'))) {
			echo 'Wrong key';
			$this->log->write('STRIPE WEBHOOK ERROR: webhook URL key ' . $this->request->get['key'] . ' does not match the encryption key hash ' . md5($this->config->get('config_encryption')));
			return;
		}
		
		$webhook = $event['data']['object'];
		$this->load->model('checkout/order');
		
		if ($event['type'] == 'customer.deleted') {
			
			$mode = ($webhook['livemode']) ? 'live' : 'test';
			$this->db->query("DELETE FROM " . DB_PREFIX . "stripe_customer WHERE stripe_customer_id = '" . $this->db->escape($webhook['id']) . "'");
			
		} elseif ($event['type'] == 'charge.captured') {
			
			$order_history_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "order_history WHERE `comment` LIKE '%" . $this->db->escape($webhook['id']) . "%' ORDER BY order_history_id DESC");
			if (!$order_history_query->num_rows) return;
			
			$strong = '<strong style="display: inline-block; width: 140px; padding: 3px">';
			$comment = $strong . 'Stripe Event:</strong>' . $event['type'] . '<br>';
			
			$order_id = $order_history_query->row['order_id'];
			$order_status_id = ($settings['success_status_id']) ? $settings['success_status_id'] : $order_history_query->row['order_status_id'];
			
			$this->model_checkout_order->addOrderHistory($order_id, $order_status_id, $comment, false);
			
		} elseif ($event['type'] == 'charge.refunded') {
			
			$order_history_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "order_history WHERE `comment` LIKE '%" . $this->db->escape($webhook['payment_intent']) . "%' ORDER BY order_history_id DESC");
			if (!$order_history_query->num_rows) return;
			
			$refund = array_pop($webhook['refunds']['data']);
			$refund_currency = strtoupper($refund['currency']);
			$decimal_factor = (in_array($refund_currency, array('BIF','CLP','DJF','GNF','JPY','KMF','KRW','MGA','PYG','RWF','VND','VUV','XAF','XOF','XPF'))) ? 1 : 100;
			
			$strong = '<strong style="display: inline-block; width: 140px; padding: 3px">';
			$comment = $strong . 'Stripe Event:</strong>' . $event['type'] . '<br>';
			$comment .= $strong . 'Refund Amount:</strong>' . $this->currency->format($refund['amount'] / $decimal_factor, $refund_currency, 1) . '<br>';
			$comment .= $strong . 'Total Amount Refunded:</strong>' . $this->currency->format($webhook['amount_refunded'] / $decimal_factor, $refund_currency, 1);
			
			$order_id = $order_history_query->row['order_id'];
			$order_info = $this->model_checkout_order->getOrder($order_id);
			$refund_type = ($webhook['amount_refunded'] == $webhook['amount']) ? 'refund' : 'partial';
			$order_status_id = ($settings[$refund_type . '_status_id']) ? $settings[$refund_type . '_status_id'] : $order_history_query->row['order_status_id'];
			
			$this->model_checkout_order->addOrderHistory($order_id, $order_status_id, $comment, false);
		
		} elseif ($event['type'] == 'customer.subscription.deleted') {
			
			/*
			$order_id = $webhook['metadata']['order_id'];
			$this->db->query("UPDATE `" . DB_PREFIX . "order` SET order_status_id = 7 WHERE order_id = " . (int)$order_id);
			$this->db->query("INSERT INTO " . DB_PREFIX . "order_history SET order_id = " . (int)$order_id . ", order_status_id = 7, notify = 0, comment = 'customer.subscription.deleted', date_added = NOW()");
			*/
			
		} elseif ($event['type'] == 'invoice.payment_succeeded' && !empty($settings['subscriptions'])) {
			
			// Check for immediate subscriptions
			$now_query = $this->db->query("SELECT NOW()");
			$last_order_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order` WHERE email = '" . $this->db->escape($webhook['customer_email']) . "' ORDER BY date_added DESC");
			if ($last_order_query->num_rows && (strtotime($now_query->row['NOW()']) - strtotime($last_order_query->row['date_added'])) < 600) {
				// Customer's last order is within 10 minutes, so it most likely was an immediate subscription and is already shown on their last order
				return;
			}
			
			// Set customer data
			$data = array();
			$data['email'] = $webhook['customer_email'];
			
			$opencart_customer = $this->db->query("SELECT * FROM " . DB_PREFIX . "customer WHERE email = '" . $this->db->escape($data['email']) . "'")->row;
			$opencart_address = $this->db->query("SELECT * FROM " . DB_PREFIX . "address WHERE address_id = " . (int)$opencart_customer['address_id'])->row;
			
			$data['customer_id'] = (isset($opencart_customer['customer_id'])) ? $opencart_customer['customer_id'] : 0;
			
			// Use OpenCart address for billing and/or shipping
			if (($settings['order_address'] == 'opencart' || $settings['order_address'] == 'both') && !empty($opencart_customer)) {
				$data['firstname'] = $opencart_customer['firstname'];
				$data['lastname'] = $opencart_customer['lastname'];
				$data['telephone'] = $opencart_customer['telephone'];
				
				$zone_id = (isset($opencart_address['zone_id'])) ? $opencart_address['zone_id'] : 0;
				$zone_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone WHERE zone_id = " . (int)$opencart_address['zone_id']);
				$opencart_address['zone'] = (isset($zone_query->row['name'])) ? $zone_query->row['name'] : '';
				
				$country_id = (isset($opencart_address['country_id'])) ? $opencart_address['country_id'] : 0;
				$country_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "country WHERE country_id = " . (int)$opencart_address['country_id']);
				$opencart_address['country'] = (isset($country_query->row['name'])) ? $country_query->row['name'] : '';
				
				foreach (array('firstname', 'lastname', 'company', 'company_id', 'tax_id', 'address_1', 'address_2', 'city', 'postcode', 'zone_id', 'zone', 'country_id', 'country') as $field) {
					$data['payment_' . $field] = (isset($opencart_address[$field])) ? $opencart_address[$field] : '';
					$data['shipping_' . $field] = (isset($opencart_address[$field])) ? $opencart_address[$field] : '';
				}
			}
			
			// Use Stripe address for billing and/or shipping
			if ($settings['order_address'] == 'stripe' || $settings['order_address'] == 'both') {
				$customer_response = $this->curlRequest('GET', 'customers/' . $webhook['customer'], array('expand' => array('' => 'default_source')));
				$stripe_customer = (!empty($customer_response['error'])) ? $customer_response['default_source']['owner'] : array();
				
				// Customer name and telephone
				if (!empty($webhook['customer_name'])) {
					$customer_name = explode(' ', $webhook['customer_name'], 2);
				} elseif (!empty($stripe_customer['name'])) {
					$customer_name = explode(' ', $stripe_customer['name'], 2);
				} elseif (!empty($opencart_customer['firstname'])) {
					$customer_name = array($opencart_customer['firstname'], $opencart_customer['lastname']);
				}
				
				$data['firstname'] = (isset($customer_name[0])) ? $customer_name[0] : '';
				$data['lastname'] = (isset($customer_name[1])) ? $customer_name[1] : '';
				$data['telephone'] = '';
				
				if (!empty($webhook['customer_phone'])) {
					$data['telephone'] = $webhook['customer_phone'];
				} elseif (!empty($stripe_customer['phone'])) {
					$data['telephone'] = $stripe_customer['phone'];
				} elseif (!empty($opencart_customer['telephone'])) {
					$data['telephone'] = $opencart_customer['telephone'];
				}
				
				// Customer billing address
				if (!empty($webhook['customer_address'])) {
					$billing_address = $webhook['customer_address'];
				} elseif (!empty($stripe_customer['address'])) {
					$billing_address = $stripe_customer['address'];
				} else {
					$billing_address = array(
						'line1'			=> '',
						'line2'			=> '',
						'city'			=> '',
						'state'			=> '',
						'postal_code'	=> '',
						'country'		=> '',
					);
				}
				
				$country_id = 0;
				$country_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "country WHERE `name` = '" . $this->db->escape($billing_address['country']) . "'");
				if ($country_query->num_rows) {
					$country_id = $country_query->row['country_id'];
				}
				
				$zone_id = 0;
				$zone_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone WHERE `name` = '" . $this->db->escape($billing_address['state']) . "' AND country_id = " . (int)$country_id);
				if ($zone_query->num_rows) {
					$zone_id = $zone_query->row['zone_id'];
				} else {
					$zone_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone WHERE `code` = '" . $this->db->escape($billing_address['state']) . "' AND country_id = " . (int)$country_id);
					if ($zone_query->num_rows) {
						$zone_id = $zone_query->row['zone_id'];
					}
				}
				
				$data['payment_firstname']	= $data['firstname'];
				$data['payment_lastname']	= $data['lastname'];
				$data['payment_company']	= '';
				$data['payment_company_id']	= '';
				$data['payment_tax_id']		= '';
				$data['payment_address_1']	= $billing_address['line1'];
				$data['payment_address_2']	= $billing_address['line2'];
				$data['payment_city']		= $billing_address['city'];
				$data['payment_postcode']	= $billing_address['postal_code'];
				$data['payment_zone_id']	= $zone_id;
				$data['payment_zone']		= $billing_address['state'];
				$data['payment_country_id']	= $country_id;
				$data['payment_country']	= $billing_address['country'];
				
				// Use Stripe address for shipping
				if ($settings['order_address'] == 'stripe') {
					if (!empty($webhook['customer_shipping'])) {
						$shipping_name = explode(' ', $webhook['customer_shipping']['name'], 2);
						$shipping_address = $webhook['customer_shipping']['address'];
						
						$country_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "country WHERE `name` = '" . $this->db->escape($shipping_address['country']) . "'");
						$country_id = (isset($country_query->row['country_id'])) ? $country_query->row['country_id'] : 0;
						
						$zone_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone WHERE `name` = '" . $this->db->escape($shipping_address['state']) . "' AND country_id = " . (int)$country_id);
						if ($zone_query->num_rows) {
							$zone_id = $zone_query->row['zone_id'];
						} else {
							$zone_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone WHERE `code` = '" . $this->db->escape($shipping_address['state']) . "' AND country_id = " . (int)$country_id);
							if ($zone_query->num_rows) {
								$zone_id = $zone_query->row['zone_id'];
							}
						}
						
						$data['shipping_firstname']		= $shipping_name[0];
						$data['shipping_lastname']		= (isset($shipping_name[1]) ? $shipping_name[1] : '');
						$data['shipping_company']		= '';
						$data['shipping_company_id']	= '';
						$data['shipping_tax_id']		= '';
						$data['shipping_address_1']		= $shipping_address['line1'];
						$data['shipping_address_2']		= $shipping_address['line2'];
						$data['shipping_city']			= $shipping_address['city'];
						$data['shipping_postcode']		= $shipping_address['postal_code'];
						$data['shipping_zone_id']		= $zone_id;
						$data['shipping_zone']			= $shipping_address['state'];
						$data['shipping_country_id']	= $country_id;
						$data['shipping_country']		= $shipping_address['country'];
					} else {
						foreach (array('firstname', 'lastname', 'company', 'company_id', 'tax_id', 'address_1', 'address_2', 'city', 'postcode', 'zone_id', 'zone', 'country_id', 'country') as $field) {
							$data['shipping_' . $field] = $data['payment_' . $field];
						}
					}
				}
			}
			
			// Set products and line items
			$data['shipping_method'] = '(none)';
			
			$plan_name = '';
			$product_data = array();
			$total_data = array();
			$subtotal = 0;
			$original_order_id = 0;
			
			foreach ($webhook['lines']['data'] as $line) {
				$line_currency = strtoupper($line['currency']);
				$line_decimal_factor = (in_array($line_currency, array('BIF','CLP','DJF','GNF','JPY','KMF','KRW','MGA','PYG','RWF','VND','VUV','XAF','XOF','XPF'))) ? 1 : 100;
				
				if (empty($line['plan'])) {
					
					$shipping_line_item = (strpos($line['description'], 'Shipping for') === 0);
					
					// Add non-product line items
					$total_data[] = array(
						'code'			=> ($shipping_line_item) ? 'shipping' : 'total',
						'title'			=> $line['description'],
						'text'			=> $this->currency->format($line['amount'] / $line_decimal_factor, $line_currency, 1),
						'value'			=> $line['amount'] / $line_decimal_factor,
						'sort_order'	=> 2
					);
					
					// Add invoice item for shipping
					if ($shipping_line_item) {
						$data['shipping_method'] = $line['description'];
						
						$invoice_item_data = array(
							'amount'		=> $line['amount'],
							'currency'		=> $line['currency'],
							'customer'		=> $webhook['customer'],
							'description'	=> $line['description'],
						);
						
						$invoice_item_response = $this->curlRequest('POST', 'invoiceitems', $invoice_item_data);
						if (!empty($invoice_item_response['error'])) {
							$this->log->write('STRIPE ERROR: ' . $invoice_item_response['error']['message']);
						}
					}
					
				} else {
					
					// Add product corresponding to line item
					$plan_name = (!empty($line['metadata']['product_name'])) ? $line['metadata']['product_name'] : $line['description'];
					$charge = $line['amount'] / $line_decimal_factor;
					$subtotal += $charge;
					
					if (!empty($line['metadata']['order_id'])) {
						$original_order_id = $line['metadata']['order_id'];
					}
					
					if (!empty($line['metadata']['product_id'])) {
						$product_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product p LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id AND pd.language_id = " . (int)$this->config->get('config_language_id') . ") WHERE p.product_id = " . (int)$line['metadata']['product_id']);
					} else {
						$product_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product p LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id AND pd.language_id = " . (int)$this->config->get('config_language_id') . ") WHERE p.location = '" . $this->db->escape($line['plan']['id']) . "'");
					}
					
					if ($product_query->num_rows) {
						$product = $product_query->row;
					} else {
						$product = array(
							'product_id'	=> 0,
							'name'			=> $plan_name,
							'model'			=> '',
							'subtract'		=> 0,
							'tax_class_id'	=> 0,
							'shipping'		=> 1,
						);
					}
					
					$product_data[] = array(
						'product_id'	=> $product['product_id'],
						'name'			=> $product['name'],
						'model'			=> $product['model'],
						'option'		=> array(),
						'download'		=> array(),
						'quantity'		=> $line['quantity'],
						'subtract'		=> $product['subtract'],
						'price'			=> ($charge / $line['quantity']),
						'total'			=> $charge,
						'tax'			=> $this->tax->getTax($charge, $product['tax_class_id']),
						'reward'		=> isset($product['reward']) ? $product['reward'] : 0
					);
				}
				
			}
			
			// Set order totals
			$data['currency_code'] = strtoupper($webhook['currency']);
			$data['currency_id'] = $this->currency->getId($data['currency_code']);
			$data['currency_value'] = $this->currency->getValue($data['currency_code']);
			
			$decimal_factor = (in_array($data['currency_code'], array('BIF','CLP','DJF','GNF','JPY','KMF','KRW','MGA','PYG','RWF','VND','VUV','XAF','XOF','XPF'))) ? 1 : 100;
			
			$total_data[] = array(
				'code'			=> 'sub_total',
				'title'			=> 'Sub-Total',
				'text'			=> $this->currency->format($subtotal, $data['currency_code'], 1),
				'value'			=> $subtotal,
				'sort_order'	=> 1
			);
			if (!empty($webhook['tax'])) {
				$total_data[] = array(
					'code'			=> 'tax',
					'title'			=> 'Tax',
					'text'			=> $this->currency->format($webhook['tax'] / $decimal_factor, $data['currency_code'], 1),
					'value'			=> $webhook['tax'] / $decimal_factor,
					'sort_order'	=> 2
				);
			}
			$total_data[] = array(
				'code'			=> 'total',
				'title'			=> 'Total',
				'text'			=> $this->currency->format($webhook['total'] / $decimal_factor, $data['currency_code'], 1),
				'value'			=> $webhook['total'] / $decimal_factor,
				'sort_order'	=> 3
			);
			
			$data['products'] = $product_data;
			$data['totals'] = $total_data;
			$data['total'] = $webhook['total'] / $decimal_factor;
			
			// Create order in database
			$this->load->model('extension/' . $this->type . '/' . $this->name);
			
			$order_id = $this->{'model_extension_'.$this->type.'_'.$this->name}->createOrder($data);
			$order_status_id = $settings['success_status_id'];
			
			$strong = '<strong style="display: inline-block; width: 140px; padding: 3px">';
			$comment = $strong . 'Charged for Plan:</strong>' . $plan_name . '<br>';
			$comment .= $strong . 'Stripe Event ID:</strong>' . $event['id'] . '<br>';
			if (!empty($webhook['charge'])) {
				$comment .= $strong . 'Stripe Charge ID:</strong>' . $webhook['charge'] . '<br>';
			}
			if (!empty($original_order_id)) {
				$comment .= $strong . 'Original Order ID:</strong>' . $original_order_id . '<br>';
			}
			
			$this->model_checkout_order->addOrderHistory($order_id, $order_status_id, $comment, false);
		}
		
	}
	
	//==============================================================================
	// getSettings()
	//==============================================================================
	private function getSettings() {
		$code = (version_compare(VERSION, '3.0', '<') ? '' : $this->type . '_') . $this->name;
		
		$settings = array();
		$settings_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "setting WHERE `code` = '" . $this->db->escape($code) . "' ORDER BY `key` ASC");
		
		foreach ($settings_query->rows as $setting) {
			$value = $setting['value'];
			if ($setting['serialized']) {
				$value = (version_compare(VERSION, '2.1', '<')) ? unserialize($setting['value']) : json_decode($setting['value'], true);
			}
			$split_key = preg_split('/_(\d+)_?/', str_replace($code . '_', '', $setting['key']), -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
			
				if (count($split_key) == 1)	$settings[$split_key[0]] = $value;
			elseif (count($split_key) == 2)	$settings[$split_key[0]][$split_key[1]] = $value;
			elseif (count($split_key) == 3)	$settings[$split_key[0]][$split_key[1]][$split_key[2]] = $value;
			elseif (count($split_key) == 4)	$settings[$split_key[0]][$split_key[1]][$split_key[2]][$split_key[3]] = $value;
			else 							$settings[$split_key[0]][$split_key[1]][$split_key[2]][$split_key[3]][$split_key[4]] = $value;
		}
		
		return $settings;
	}
	
	//==============================================================================
	// replaceShortcodes()
	//==============================================================================
	private function replaceShortcodes($text, $order_info) {
		$product_names = array();
		foreach ($this->cart->getProducts() as $product) {
			$options = array();
			foreach ($product['option'] as $option) {
				$options[] = $option['name'] . ': ' . $option['value'];
			}
			$product_name = $product['name'] . ($options ? ' (' . implode(', ', $options) . ')' : '');
			$product_names[] = html_entity_decode($product_name, ENT_QUOTES, 'UTF-8');
		}
		
		$replace = array(
			'[store]',
			'[order_id]',
			'[amount]',
			'[email]',
			'[comment]',
			'[products]'
		);
		$with = array(
			$this->config->get('config_name'),
			$order_info['order_id'],
			$this->currency->format($order_info['total'], $this->session->data['currency']),
			$order_info['email'],
			$order_info['comment'],
			implode(', ', $product_names)
		);
		
		return str_replace($replace, $with, $text);
	}
	
	//==============================================================================
	// curlRequest()
	//==============================================================================
	private function curlRequest($request, $api, $data = array()) {
		$settings = $this->getSettings();
		
		$url = 'https://api.stripe.com/v1/';
		
		if ($request == 'GET') {
			$curl = curl_init($url . $api . '?' . http_build_query($data));
		} else {
			$curl = curl_init($url . $api);
			curl_setopt($curl, CURLOPT_POST, true);
			curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
			if ($request != 'POST') {
				curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $request);
			}
		}
		
		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 30);
		curl_setopt($curl, CURLOPT_FORBID_REUSE, true);
		curl_setopt($curl, CURLOPT_FRESH_CONNECT, true);
		curl_setopt($curl, CURLOPT_HEADER, false);
		curl_setopt($curl, CURLOPT_HTTPHEADER, array('Stripe-Version: 2019-03-14'));
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		if ($settings['transaction_mode'] == 'live') {
			//curl_setopt($curl, CURLOPT_SSLVERSION, 6);
		}
		curl_setopt($curl, CURLOPT_TIMEOUT, 30);
		curl_setopt($curl, CURLOPT_USERPWD, $settings[$settings['transaction_mode'] . '_secret_key'] . ':');
		
		$response = json_decode(curl_exec($curl), true);
		
		if (curl_error($curl)) {
			$response = array('error' => array('message' => 'CURL ERROR: ' . curl_errno($curl) . '::' . curl_error($curl)));
			$this->log->write('STRIPE CURL ERROR: ' . curl_errno($curl) . '::' . curl_error($curl));	
		} elseif (empty($response)) {
			$response = array('error' => array('message' => 'CURL ERROR: Empty Gateway Response'));
			$this->log->write('STRIPE CURL ERROR: Empty Gateway Response');
		}
		curl_close($curl);
		
		if (!empty($response['error']['code']) && !empty($settings['error_' . $response['error']['code']])) {
			$response['error']['message'] = html_entity_decode($settings['error_' . $response['error']['code']], ENT_QUOTES, 'UTF-8');
		}
		
		return $response;
	}
}
?>