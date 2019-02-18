<?php

require_once DIR_SYSTEM . 'library/sameday-php-sdk/src/Sameday/autoload.php';

class ModelExtensionShippingSameday extends Model
{
    public function install()
    {
        $this->createAwbTable();
        $this->createServiceTable();
        $this->createPickUpPointTable();
        $this->createPackageTable();
    }

    public function uninstall()
    {
        $this->dropAwbTable();
        $this->dropServiceTable();
        $this->dropPickUpPointTable();
        $this->dropPackageTable();
    }

    /**
     * @param $data
     */
    public function saveAwb($data)
    {
        $query = '
            INSERT INTO ' . DB_PREFIX . "sameday_awb(
                order_id,
                awb_number,
                parcels,
                awb_cost
            ) VALUES (
                '{$this->db->escape($data['order_id'])}',
                '{$this->db->escape($data['awb_number'])}',
                '{$this->db->escape($data['parcels'])}',
                '{$this->db->escape($data['awb_cost'])}'
            )";

        $this->db->query($query);
    }

    /**
     * @return array
     */
    public function getCounties()
    {
        $query = 'SELECT country_id FROM ' . DB_PREFIX . 'country WHERE iso_code_2="RO"';

        $result = $this->db->query($query);
        if (empty($result->row)) {
            return array();
        }

        $query = 'SELECT * FROM ' . DB_PREFIX . "zone WHERE country_id='{$result->row['country_id']}'";

        return $this->db->query($query)->rows;
    }

    /**
     * @param bool $testing
     *
     * @return array
     */
    public function getServices($testing)
    {
        $query = 'SELECT * FROM ' . DB_PREFIX . "sameday_service WHERE testing='{$this->db->escape($testing)}'";

        return $this->db->query($query)->rows;
    }

    /**
     * @param int $id
     *
     * @return array
     */
    public function getService($id)
    {
        $query = 'SELECT * FROM ' . DB_PREFIX . "sameday_service WHERE id='{$this->db->escape($id)}'";

        return $this->db->query($query)->row;
    }

    /**
     * @param int $samedayId
     * @param bool $testing
     *
     * @return array
     */
    public function getServiceSameday($samedayId, $testing)
    {
        $query = 'SELECT * FROM ' . DB_PREFIX . "sameday_service WHERE sameday_id='{$this->db->escape($samedayId)}' AND testing='{$this->db->escape($testing)}'";

        return $this->db->query($query)->row;
    }

    /**
     * @param \Sameday\Objects\Service\ServiceObject $service
     * @param bool $testing
     */
    public function addService(\Sameday\Objects\Service\ServiceObject $service, $testing)
    {
        $query = '
            INSERT INTO ' . DB_PREFIX . "sameday_service (
                sameday_id, 
                sameday_name, 
                testing, 
                status
            ) VALUES (
                '{$this->db->escape($service->getId())}', 
                '{$this->db->escape($service->getName())}', 
                '{$this->db->escape($testing)}',
                0
            )";

        $this->db->query($query);
    }

    /**
     * @param int $id
     * @param array $postFields
     */
    public function updateService($id, $postFields)
    {
        $postFields['working_days'] = serialize($postFields['working_days']);

        $this->db->query('
            UPDATE ' . DB_PREFIX . "sameday_service SET 
                name='{$this->db->escape($postFields['name'])}',
                status='{$this->db->escape($postFields['status'])}', 
                price='{$this->db->escape($postFields['price'])}',
                price_free=" . ((string) $postFields['price_free'] !== '' ? ('\''.$this->db->escape($postFields['price_free']) . '\'') : 'NULL') . ",
                working_days='{$this->db->escape($postFields['working_days'])}'
            WHERE 
                id = '{$this->db->escape($id)}'
        ");
    }

    /**
     * @param int $id
     */
    public function deleteService($id)
    {
        $query = 'DELETE FROM ' . DB_PREFIX . "sameday_service WHERE id='{$this->db->escape($id)}'";

        $this->db->query($query);
    }

    /**
     * @param bool $testing
     *
     * @return array
     */
    public function getPickupPoints($testing)
    {
        $query = 'SELECT * FROM ' . DB_PREFIX . "sameday_pickup_point WHERE testing='{$this->db->escape($testing)}'";

        return $this->db->query($query)->rows;
    }

    /**
     * @param int $id
     *
     * @return array
     */
    public function getPickupPoint($id)
    {
        $query = 'SELECT * FROM ' . DB_PREFIX . "sameday_pickup_point WHERE id='{$this->db->escape($id)}'";

        return $this->db->query($query)->row;
    }

    /**
     * @param int $samedayId
     * @param bool $testing
     *
     * @return array
     */
    public function getPickupPointSameday($samedayId, $testing)
    {
        $query = 'SELECT * FROM ' . DB_PREFIX . "sameday_pickup_point WHERE sameday_id='{$this->db->escape($samedayId)}' AND testing='{$this->db->escape($testing)}'";

        return $this->db->query($query)->row;
    }

    /**
     * @param \Sameday\Objects\PickupPoint\PickupPointObject $pickupPointObject
     * @param bool $testing
     */
    public function addPickupPoint(\Sameday\Objects\PickupPoint\PickupPointObject $pickupPointObject, $testing)
    {
        $query = '
            INSERT INTO ' . DB_PREFIX . "sameday_pickup_point (sameday_id, 
                sameday_alias, 
                testing, 
                city, 
                county, 
                address,
                default_pickup_point,
                contactPersons
            ) VALUES (
                '{$this->db->escape($pickupPointObject->getId())}', 
                '{$this->db->escape($pickupPointObject->getAlias())}', 
                '{$this->db->escape($testing)}', 
                '{$this->db->escape($pickupPointObject->getCity()->getName())}', 
                '{$this->db->escape($pickupPointObject->getCounty()->getName())}', 
                '{$this->db->escape($pickupPointObject->getAddress())}',  
                '{$this->db->escape($pickupPointObject->isDefault())}',    
                '{$this->db->escape(serialize($pickupPointObject->getContactPersons()))}')";

        $this->db->query($query);
    }

    /**
     * @param \Sameday\Objects\PickupPoint\PickupPointObject $pickupPointObject
     * @param bool $testing
     */
    public function updatePickupPoint($pickupPointObject, $testing)
    {
        $this->db->query(
            'UPDATE ' . DB_PREFIX . "sameday_pickup_point SET 
                sameday_alias='{$this->db->escape($pickupPointObject->getAlias())}',
                city='{$this->db->escape($pickupPointObject->getCity()->getName())}', 
                county='{$this->db->escape($pickupPointObject->getCounty()->getName())}',
                address='{$this->db->escape($pickupPointObject->getAddress())}'
            WHERE 
                sameday_id='{$this->db->escape($pickupPointObject->getId())}'
        ");
    }

    /**
     * @param int $id
     */
    public function deletePickupPoint($id)
    {
        $query = 'DELETE FROM ' . DB_PREFIX . "sameday_pickup_point WHERE id='{$this->db->escape($id)}'";

        $this->db->query($query);
    }

    /**
     * @param string $awbNumber
     */
    public function deleteAwb($awbNumber)
    {
        $query = 'DELETE FROM ' . DB_PREFIX . "sameday_awb WHERE awb_number='{$this->db->escape($awbNumber)}'";

        $this->db->query($query);
    }

    /**
     * @param int $orderId
     *
     * @return array
     */
    public function getAwbForOrderId($orderId)
    {
        $query = 'SELECT * FROM ' . DB_PREFIX . "sameday_awb WHERE order_id={$this->db->escape($orderId)}";

        return $this->db->query($query)->row;
    }

    /**
     * @param int $orderId
     * @param string $awbParcel
     * @param \Sameday\Objects\ParcelStatusHistory\SummaryObject $summary
     * @param array $history
     * @param \Sameday\Objects\ParcelStatusHistory\ExpeditionObject $expedition
     */
    public function refreshPackageHistory(
        $orderId,
        $awbParcel,
        \Sameday\Objects\ParcelStatusHistory\SummaryObject $summary,
        array $history,
        \Sameday\Objects\ParcelStatusHistory\ExpeditionObject $expedition
    ) {
        $query = '
            INSERT INTO ' . DB_PREFIX . "sameday_package (
                order_id,
                awb_parcel,
                summary, 
                history, 
                expedition_status
            ) VALUES (
                '{$this->db->escape($orderId)}',
                '{$this->db->escape($awbParcel)}', 
                '{$this->db->escape(serialize($summary))}', 
                '{$this->db->escape(serialize($history))}',
                '{$this->db->escape(serialize($expedition))}'
            ) ON DUPLICATE KEY UPDATE
                summary='{$this->db->escape(serialize($summary))}',
                history='{$this->db->escape(serialize($history))}',
                expedition_status='{$this->db->escape(serialize($expedition))}'
            ";

        $this->db->query($query);
    }

    /**
     * @param int $orderId
     *
     * @return array
     */
    public function getPackagesForOrderId($orderId)
    {
        $query = 'SELECT * FROM ' . DB_PREFIX . "sameday_package WHERE order_id={$this->db->escape($orderId)}";

        $rows = $this->db->query($query)->rows;
        foreach ($rows as $k => $row) {
            $rows[$k]['summary'] = unserialize($row['summary']);
            $rows[$k]['history'] = unserialize($row['history']);
            $rows[$k]['expedition_status'] = unserialize($row['expedition_status']);
            $rows[$k]['sync'] = unserialize($row['sync']);
        }

        return $rows;
    }

    private function createAwbTable()
    {
        $query = '
            CREATE TABLE IF NOT EXISTS ' . DB_PREFIX . 'sameday_awb (
                id INT(11) NOT NULL AUTO_INCREMENT,
                order_id INT(11) NOT NULL,
                awb_number VARCHAR(255),
                parcels TEXT,
                awb_cost DOUBLE(10, 2),
                PRIMARY KEY (id)
            ) ENGINE=MyISAM DEFAULT COLLATE=utf8_general_ci;
        ';

        $this->db->query($query);
    }

    private function createServiceTable()
    {
        $query = '
            CREATE TABLE IF NOT EXISTS ' . DB_PREFIX . 'sameday_service (
                id INT(11) NOT NULL AUTO_INCREMENT,
                sameday_id INT(11) NOT NULL,
                sameday_name VARCHAR(255),
                testing TINYINT(1),
                name VARCHAR(255),
                price DOUBLE(10, 2),
                price_free DOUBLE(10, 2),
                status INT(11),
                working_days TEXT,
                PRIMARY KEY (id)
            ) ENGINE=MyISAM DEFAULT COLLATE=utf8_general_ci;
        ';

        $this->db->query($query);
    }

    private function createPickUpPointTable()
    {
        $query = '
            CREATE TABLE IF NOT EXISTS ' . DB_PREFIX . 'sameday_pickup_point (
                id INT(11) NOT NULL AUTO_INCREMENT,
                sameday_id INT(11) NOT NULL,
                sameday_alias VARCHAR(255),
                testing TINYINT(1),
                city VARCHAR(255),
                county VARCHAR(255),
                address VARCHAR(255),
                contactPersons TEXT,
                default_pickup_point TINYINT(1),
                PRIMARY KEY (id)
            ) ENGINE=MyISAM DEFAULT COLLATE=utf8_general_ci;
        ';

        $this->db->query($query);
    }

    private function createPackageTable()
    {
        $query = '
            CREATE TABLE IF NOT EXISTS ' . DB_PREFIX . 'sameday_package (
                order_id INT(11) NOT NULL,
                awb_parcel VARCHAR(255),
                summary TEXT,
                history TEXT,
                expedition_status TEXT,
                sync TEXT,
                PRIMARY KEY (order_id, awb_parcel)
            ) ENGINE=MyISAM DEFAULT COLLATE=utf8_general_ci;
        ';

        $this->db->query($query);
    }

    private function dropPickUpPointTable()
    {
        $query = 'DROP TABLE IF EXISTS ' . DB_PREFIX . 'sameday_pickup_point';

        $this->db->query($query);
    }

    private function dropAwbTable()
    {
        $query = 'DROP TABLE IF EXISTS ' . DB_PREFIX . 'sameday_awb';

        $this->db->query($query);
    }

    private function dropServiceTable()
    {
        $query = 'DROP TABLE IF EXISTS ' . DB_PREFIX . 'sameday_service';

        $this->db->query($query);
    }

    private function dropPackageTable()
    {
        $query = 'DROP TABLE IF EXISTS ' . DB_PREFIX . 'sameday_package';

        $this->db->query($query);
    }
}
