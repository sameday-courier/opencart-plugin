<?php

class ModelExtensionShippingSameday extends Model
{
    /**
     * @param array $address
     *
     * @return array
     */
    public function getQuote($address)
    {
        $query = $this->db->query('SELECT * FROM ' . DB_PREFIX . "zone_to_geo_zone WHERE 
            geo_zone_id='{$this->getConfig('sameday_geo_zone_id')}'
            AND country_id='{$address['country_id']}'
            AND (zone_id='{$address['zone_id']}' OR zone_id='0')");

        if (!$this->getConfig('sameday_geo_zone_id')) {
            $status = true;
        } elseif ($query->num_rows) {
            $status = true;
        } else {
            $status = false;
        }

        $method_data = array();
        if (!$status) {
            return $method_data;
        }

        $availableService = $this->getAvailableServices($this->getConfig('sameday_testing'));
        $quote_data = array();

        foreach ($availableService as $service) {
            $price = $price = $service['price'];
            if ($service['price_free'] !== null && $this->cart->getSubTotal() >= $service['price_free']) {
                $price = 0;
            }

            $quote_data[$service['name']] = array(
                'code' => 'sameday.' . $service['name'] . '.' . $service['sameday_id'],
                'title' => $service['name'],
                'cost' => $price,
                'tax_class_id' => $this->getConfig('sameday_tax_class_id'),
                'text' => $this->currency->format(
                    $this->tax->calculate(
                        $price,
                        $this->getConfig('sameday_tax_class_id'),
                        $this->getConfig('config_tax')
                    ),
                    $this->session->data['currency']
                )
            );
        }

        $method_data = array(
            'code' => 'sameday',
            'title' => 'Sameday',
            'quote' => $quote_data,
            'sort_order' => $this->getConfig('sameday_sort_order'),
            'error' => false
        );

        return $method_data;
    }

    /**
     * @param bool $testing
     *
     * @return array
     */
    private function getAvailableServices($testing)
    {
        $query = 'SELECT * FROM ' . DB_PREFIX . "sameday_service WHERE testing='{$this->db->escape($testing)}' AND status>0";
        $services = $this->db->query($query)->rows;

        $availableServices = array();
        foreach ($services as $service) {
            switch ($service['status']) {
                case 1:
                    $availableServices[] = $service;
                    break;

                case 2:
                    $working_days = unserialize($service['working_days']);

                    $today = date('w');
                    $date_from = strtotime($working_days[$today]['from']);
                    $date_to = strtotime($working_days[$today]['to']);
                    $time = time();

                    if (!isset($working_days[$today]['check']) || $time < $date_from || $time > $date_to) {
                        // Not working on this day, or out of available time period.
                        break;
                    }

                    $availableServices[] = $service;
                    break;
            }
        }

        return $availableServices;
    }

    /**
     * @param string $key
     *
     * @return mixed
     */
    private function getConfig($key)
    {
        return $this->config->get("{$this->getPrefix()}$key");
    }

    /**
     * @return string
     */
    private function getPrefix()
    {
        if (strpos(VERSION, '2') === 0) {
            return '';
        }

        return 'shipping_';
    }
}
