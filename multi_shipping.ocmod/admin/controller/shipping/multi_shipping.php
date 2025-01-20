<?php
namespace Opencart\Admin\Controller\Extension\MultiShipping\Shipping;
class MultiShipping extends \Opencart\System\Engine\Controller {
	private $error = array();
	public function index(): void {
		$this->load->language('extension/multi_shipping/shipping/multi_shipping');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');
		$this->load->model('localisation/geo_zone');
		$this->load->model('setting/store');
		$this->load->model('localisation/tax_class');
		$this->load->model('localisation/language');

		$data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();
		$data['tax_classes'] = $this->model_localisation_tax_class->getTaxClasses();
		$data['languages'] = $this->model_localisation_language->getLanguages();
		$data['stores'] = array();

		$data['stores'][] = array(
			'store_id' => 0,
			'name'     => $this->language->get('text_default')
		);

		$stores = $this->model_setting_store->getStores();

		foreach ($stores as $store) {
			$data['stores'][] = array(
				'store_id' => $store['store_id'],
				'name'     => $store['name']
			);
		}

		$this->load->model('customer/customer_group');

		$data['customer_groups'] = $this->model_customer_customer_group->getCustomerGroups();

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=shipping')
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/multi_shipping/shipping/multi_shipping', 'user_token=' . $this->session->data['user_token'])
		);

		$data['save'] = $this->url->link('extension/multi_shipping/shipping/multi_shipping.save', 'user_token=' . $this->session->data['user_token']);
		$data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=shipping');

		$data['shipping_multi_shipping_status'] = (int)$this->config->get('shipping_multi_shipping_status');
		$data['shipping_multi_shipping_sort_order'] = (int)$this->config->get('shipping_multi_shipping_sort_order');
		$data['shipping_multi_shipping_method'] = $this->config->get('shipping_multi_shipping_method');

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->error['method'])) {
			$data['error_method'] = $this->error['method'];
		} else {
			$data['error_method'] = array();
		}

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];
			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		$data['user_token'] = $this->session->data['user_token'];

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');
		
		$this->response->setOutput($this->load->view('extension/multi_shipping/shipping/multi_shipping', $data));
	}
	
	public function save(): void {
		$this->load->language('extension/multi_shipping/shipping/multi_shipping');

		$json = [];
		
		if(isset($this->request->post['shipping_multi_shipping_method'])){
			$methods = $this->request->post['shipping_multi_shipping_method'];
			
			foreach($methods as $key => $method){
				foreach($method['name'] as $lkey => $langauge){
					if(empty($langauge) || trim($langauge) == ""){
						$json['error']['method_' . $key . '_name_' . $lkey] = $this->language->get('error_name');
					}
				}
				
				if($method['rate_type'] == 'flat' && empty($method['flat_rate'])) {
					$json['error']['method_' . $key . '_flat_rate'] = $this->language->get('error_rate');
				}
				
				if($method['rate_type'] == 'price' && empty($method['price_rate'])) {
					$json['error']['method_' . $key . '_price_rate'] = $this->language->get('error_rate');
				}
				
				if($method['rate_type'] == 'weight' && empty($method['weight_rate'])) {
					$json['error']['method_' . $key . '_weight_rate'] = $this->language->get('error_rate');
				}
				
				if (!isset($method['store_id'])) {
					$json['error']['method_' . $key . '_store'] = $this->language->get('error_store');
				}
				
				if (!isset($method['customer_group_id'])) {
					$json['error']['method_' . $key . '_group
					'] = $this->language->get('error_group');
				}
			}
		}
		
		if (isset($json['error']) && !isset($json['error']['warning'])) {
			$json['error']['warning'] = $this->language->get('error_warning');
		}

		if (!$json) {
			$this->load->model('setting/setting');

			$this->model_setting_setting->editSetting('shipping_multi_shipping', $this->request->post);

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}