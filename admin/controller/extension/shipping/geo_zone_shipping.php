<?php
class ControllerExtensionShippingGeoZoneShipping extends Controller {

	private $error = array();

	public function index(){

		$this->load->language('extension/shipping/geo_zone_shipping');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('shipping_geo_zone_shipping', $this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('extension/shipping/geo_zone_shipping', 'user_token=' . $this->session->data['user_token'] . '&type=shipping', true));
		}


		$this->load->model('localisation/geo_zone');
		$data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

		$this->load->model('localisation/tax_class');
		$data['tax_classes'] = $this->model_localisation_tax_class->getTaxClasses();

		$this->load->model('localisation/language');
		$data['languages'] = $this->model_localisation_language->getLanguages();


		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=shipping', true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/shipping/geo_zone_shipping', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['action'] = $this->url->link('extension/shipping/geo_zone_shipping', 'user_token=' . $this->session->data['user_token'], true);

		$data['cancel'] = $this->url->link('extension/extension', 'user_token=' . $this->session->data['user_token'] . '&type=shipping', true);


		if (isset($this->request->post['shipping_geo_zone_shipping_status'])) {
			$data['shipping_geo_zone_shipping_status'] = $this->request->post['shipping_geo_zone_shipping_status'];
		} else if($this->config->has('shipping_geo_zone_shipping_status')){
			$data['shipping_geo_zone_shipping_status'] = (int)$this->config->get('shipping_geo_zone_shipping_status');
		} else {
			$data['shipping_geo_zone_shipping_status'] = 0;
		}

		if (isset($this->request->post['shipping_geo_zone_shipping_sort_order'])) {
			$data['shipping_geo_zone_shipping_sort_order'] = $this->request->post['shipping_geo_zone_shipping_sort_order'];
		} else if($this->config->has('shipping_geo_zone_shipping_sort_order')){
			$data['shipping_geo_zone_shipping_sort_order'] = (int)$this->config->get('shipping_geo_zone_shipping_sort_order');
		} else {
			$data['shipping_geo_zone_shipping_sort_order'] = 0;
		}

		if (isset($this->request->post['shipping_geo_zone_shipping_methods'])) {
			$data['shipping_geo_zone_shipping_methods'] = $this->request->post['shipping_geo_zone_shipping_methods'];
		} else if($this->config->has('shipping_geo_zone_shipping_methods')){
			$data['shipping_geo_zone_shipping_methods'] = $this->config->get('shipping_geo_zone_shipping_methods');
		} else {
			$data['shipping_geo_zone_shipping_methods'] = array();
		}


		// populate alerts
		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];
			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		// populate errors
		if (isset($this->error['method'])) {
			$data['error_method'] = $this->error['method'];
		} else {
			$data['error_method'] = array();
		}


		$data['user_token'] = $this->session->data['user_token'];

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/shipping/geo_zone_shipping', $data));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/shipping/geo_zone_shipping')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		if(isset($this->request->post['shipping_geo_zone_shipping_methods'])){
			$methods = $this->request->post['shipping_geo_zone_shipping_methods'];
			foreach($methods as $k=>$method){
				foreach($method['name'] as $lang_key=>$lang_val){
					if(empty($lang_val) || trim($lang_val) == ""){
						$this->error['method'][$k]['name'][$lang_key] = $this->language->get('error_method_name');
					}
				}

				if(!empty($method["total"]) &&  trim($method["total"]) != "" && is_numeric($method["total"]) === false){
					$this->error['method'][$k]['total'] = $this->language->get('error_total');
				}

				if(!empty($method["flat_cost"]) &&  trim($method["flat_cost"]) != "" && is_numeric($method["flat_cost"]) === false){
					$this->error['method'][$k]['flat_cost'] = $this->language->get('error_flat_cost');
				}
			}
		}
		return !$this->error;
	}
}