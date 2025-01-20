<?php
namespace Opencart\Catalog\Model\Extension\MultiShipping\shipping;
class MultiShipping extends \Opencart\System\Engine\Model {
	function getQuote($address) {
		$this->load->language('shipping/multi_shipping');
		$shipping_methods = $this->config->get('shipping_multi_shipping_method');
		$subtotal = $this->cart->getSubTotal();
		$cart_weight = $this->cart->getWeight();
		$quote_data = array();
		$method_data = array();

		if ($this->customer->isLogged()) {  
			$groupId = $this->customer->getGroupId(); 
		} elseif ($this->config->get('config_customer_group_id')) {
			$groupId = $this->config->get('config_customer_group_id'); 
		} else {    
			$groupId = 0; 
		}	
		
		foreach ($shipping_methods as $method) {
			if (!$method['status']) {
				continue;
			}

			if (!$this->isMethodApplicable($method, $subtotal, $cart_weight)) {
				continue;
			}

			$status = $this->checkGeoZoneStatus($method, $address);

			if ($status) {
				$status = $this->checkStoreStatus($method);
			}

			if ($status) {
				$status = $this->checkGroupStatus($method, $groupId);
			}

			if ($status) {
				$method_code = $this->getMethodCode($method);
				$cost = $this->calculateCost($method, $subtotal, $cart_weight);
				$title = $this->formatTitle($method, $cost);

				$quote_data[$method_code] = [
					'code' => 'multi_shipping.' . $method_code,
					'name' => $method['name'][$this->config->get('config_language_id')],
					'cost' => $cost,
					'tax_class_id' => $method['tax_class_id'],
					'text' => $title
				];
			}
		}

		if ($quote_data) {
			$method_data = [
				'code' => 'multishipping',
				'name' => '',
				'quote' => $quote_data,
				'sort_order' => $this->config->get('shipping_multi_shipping_sort_order'),
				'error' => false
			];
		}

		return $method_data;
	}

	private function isMethodApplicable($method, $subtotal, $cart_weight) {
		return ($method['rate_type'] == 'flat' && $subtotal >= (float)$method['total']) ||
			   ($method['rate_type'] == 'price' && $subtotal >= (float)$method['total']) ||
			   ($method['rate_type'] == 'weight' && $cart_weight >= (float)$method['weight'] && $subtotal >= (float)$method['total']);
	}

	private function checkGeoZoneStatus($method, $address) {
		if (!$method['geo_zone_id']) {
			return true;
		}

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . (int)$method['geo_zone_id'] . "' AND country_id = '" . (int)$address['country_id'] . "' AND (zone_id = '" . (int)$address['zone_id'] . "' OR zone_id = '0')");
		return $query->num_rows > 0;
	}

    private function checkProductStatus($method) {
        if (!isset($method['product'])) {
            return !$this->hasAnyProductSpecificMethods();
        }
        
        $products = $this->cart->getProducts();
        $has_matching_product = false;
        
        foreach($products as $product) {
            if (in_array($product['product_id'], $method['product'])) {
                $has_matching_product = true;
                break;
            }
        }
        
        if ($has_matching_product) {
            foreach($products as $product) {
                if (!in_array($product['product_id'], $method['product'])) {
                    return false; // Found a product that doesn't match
                }
            }
            return true; // All products match this method
        }
        
        return $this->hasAnyProductSpecificMethods();
    }
    
    private function hasAnyProductSpecificMethods() {
        $shipping_methods = $this->config->get('shipping_multi_shipping_method');
        $products = $this->cart->getProducts();
        
        foreach ($shipping_methods as $method) {
            if (!isset($method['product'])) {
                continue;
            }
            
            foreach ($products as $product) {
                if (in_array($product['product_id'], $method['product'])) {
                    return true;
                }
            }
        }
        
        return false;
    }

	private function checkStoreStatus($method) {
		foreach ($method['store_id'] as $store) {
			if ($store == $this->config->get('config_store_id')) {
				return true;
			}
		}
		return false;
	}

	private function checkGroupStatus($method, $groupId) {
		foreach ($method['customer_group_id'] as $group) {
			if ($group == $groupId) {
				return true;
			}
		}
		return false;
	}

	private function getMethodCode($method) {
		$method_name = $method['name'][$this->config->get('config_language_id')];
		return strtolower(preg_replace('/[^\da-z]/i', '', $method_name));
	}

	private function calculateCost($method, $subtotal, $cart_weight) {
		switch ($method['rate_type']) {
			case 'flat':
				return $method['flat_rate'];
			case 'price':
				return $this->calculateMethodCost($method['price_rate'], $subtotal);
			case 'weight':
				return $this->calculateMethodCost($method['weight_rate'], $cart_weight);
			default:
				return 0;
		}
	}

	private function calculateMethodCost($method_rate, $cart_subtotal) {
		$rates = explode(',', $method_rate);
		
		foreach ($rates as $rate) {
			list($threshold, $cost) = explode(':', $rate);
			if ($threshold >= $cart_subtotal) {
				return $cost;
			}
		}
		
		$last_rate = end($rates);
		$last_rate_parts = explode(':', $last_rate);
		return end($last_rate_parts);
	}

	private function formatTitle($method, $cost) {
		$title = $this->currency->format(
			$this->tax->calculate($cost, $method['tax_class_id'], $this->config->get('config_tax')),
			$this->session->data['currency']
		);

		if (!empty($method['comment'][$this->config->get('config_language_id')])) {
			$title .= ' (' . $method['comment'][$this->config->get('config_language_id')] . ')';
		}

		return $title;
	}
}