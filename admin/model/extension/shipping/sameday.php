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
        $this->createLockerTable();
    }

    public function uninstall()
    {
        $this->dropAwbTable();
        $this->dropServiceTable();
        $this->dropPickUpPointTable();
        $this->dropPackageTable();
        $this->dropLockerTable();
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
     * @param $orderId
     */
    public function updateShippingMethodAfterPostAwb($orderId, $service)
    {
        $this->db->query('
            UPDATE ' . DB_PREFIX . "order SET 
                shipping_method='{$this->db->escape($service['name'])}',
                shipping_code='{$this->db->escape('sameday' . '.' . $service['name'] . '.' . $service['sameday_id'])}'
            WHERE 
                order_id = '{$this->db->escape($orderId)}'
        ");
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
        $rows = $this->db->query($query)->rows;

        foreach ($rows as $k => $row) {
            if (!array_key_exists('sameday_code', $row)) {
                $rows[$k]['sameday_code'] = '';
            }
        }

        return $rows;
    }

    /**
     * @param int $id
     *
     * @return array
     */
    public function getService($id)
    {
        $query = 'SELECT * FROM ' . DB_PREFIX . "sameday_service WHERE id='{$this->db->escape($id)}'";
        $row = $this->db->query($query)->row;
        if ($row && !array_key_exists('sameday_code', $row)) {
            $row['sameday_code'] = '';
        }

        return $row;
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
        $row = $this->db->query($query)->row;
        if ($row && !array_key_exists('sameday_code', $row)) {
            $row['sameday_code'] = '';
        }

        return $row;
    }

    public function ensureSamedayServiceCodeColumn()
    {
        $query = 'SHOW COLUMNS FROM ' . DB_PREFIX . "sameday_service LIKE 'sameday_code'";
        $row = $this->db->query($query)->row;

        if ($row) {
            return;
        }

        $this->db->query('alter table '. DB_PREFIX .'sameday_service add sameday_code VARCHAR(255) default \'\' not null');
    }

    /**
     * @param int $id
     * @param string $samedayServiceCode
     */
    public function updateServiceCode($id, $samedayServiceCode)
    {
        $this->db->query('
            UPDATE ' . DB_PREFIX . "sameday_service SET 
                sameday_code='{$this->db->escape($samedayServiceCode)}'
            WHERE 
                id = '{$this->db->escape($id)}'
        ");
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
                sameday_code,
                testing, 
                status
            ) VALUES (
                '{$this->db->escape($service->getId())}', 
                '{$this->db->escape($service->getName())}', 
                '{$this->db->escape($service->getCode())}', 
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
     * @param bool $testing
     *
     * @return array
     */
    public function getLockers($testing)
    {
        $query = 'SELECT * FROM ' . DB_PREFIX . "sameday_locker WHERE testing='{$this->db->escape($testing)}'";

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
     * @param int $lockerId
     * @param bool $testing
     *
     * @return array
     */
    public function getLockerSameday($lockerId, $testing)
    {
        $query = 'SELECT * FROM ' . DB_PREFIX . "sameday_locker WHERE locker_id='{$this->db->escape($lockerId)}' AND testing='{$this->db->escape($testing)}'";

        return $this->db->query($query)->row;
    }

    /**
     * @param int $id
     * @param bool $testing
     *
     * @return array
     */
    public function getLocker($id, $testing)
    {
        $query = 'SELECT * FROM ' . DB_PREFIX . "sameday_locker WHERE locker_id='{$this->db->escape($id)}' AND testing='{$this->db->escape($testing)}'";

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
     * @param \Sameday\Objects\Locker\LockerObject $lockerObject
     * @param bool $testing
     */
    public function addLocker(\Sameday\Objects\Locker\LockerObject $lockerObject, $testing)
    {
        $query = '
            INSERT INTO ' . DB_PREFIX . "sameday_locker (
                locker_id,
                name,
                county,
                city,
                address,
                lat,
                lng,
                postal_code,
                boxes,
                testing
            ) VALUES (
                '{$this->db->escape($lockerObject->getId())}',
                '{$this->db->escape($lockerObject->getName())}',
                '{$this->db->escape($lockerObject->getCounty())}',
                '{$this->db->escape($lockerObject->getCity())}',
                '{$this->db->escape($lockerObject->getAddress())}',
                '{$this->db->escape($lockerObject->getLat())}',
                '{$this->db->escape($lockerObject->getLong())}',
                '{$this->db->escape($lockerObject->getPostalCode())}',
                '{$this->db->escape(serialize($lockerObject->getBoxes()))}',
                '{$this->db->escape($testing)}')";

        $this->db->query($query);
    }

    /**
     * @param \Sameday\Objects\PickupPoint\PickupPointObject $pickupPointObject
     * @param int $pickuppointId
     */
    public function updatePickupPoint(\Sameday\Objects\PickupPoint\PickupPointObject $pickupPointObject, $pickuppointId)
    {
        $this->db->query(
            'UPDATE ' . DB_PREFIX . "sameday_pickup_point SET 
                sameday_alias='{$this->db->escape($pickupPointObject->getAlias())}',
                city='{$this->db->escape($pickupPointObject->getCity()->getName())}', 
                county='{$this->db->escape($pickupPointObject->getCounty()->getName())}',
                address='{$this->db->escape($pickupPointObject->getAddress())}',
                default_pickup_point='{$this->db->escape($pickupPointObject->isDefault())}'
                address='{$this->db->escape($pickupPointObject->getAddress())}'
            WHERE 
                id='{$pickuppointId}'
        ");
    }

    /**
     * @param \Sameday\Objects\Locker\LockerObject $lockerObject
     * @param int $lockerId
     */
    public function updateLocker(\Sameday\Objects\Locker\LockerObject $lockerObject, $lockerId)
    {
        $this->db->query(
            'UPDATE ' . DB_PREFIX . "sameday_locker SET 
                name='{$this->db->escape($lockerObject->getName())}',
                city='{$this->db->escape($lockerObject->getCity())}', 
                county='{$this->db->escape($lockerObject->getCounty())}',
                address='{$this->db->escape($lockerObject->getAddress())}',
                lat='{$this->db->escape($lockerObject->getLat())}',
                lng='{$this->db->escape($lockerObject->getLong())}',
                postal_code='{$this->db->escape($lockerObject->getPostalCode())}',
                boxes='{$this->db->escape(serialize($lockerObject->getBoxes()))}'
            WHERE 
                id='{$lockerId}'
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
     * @param int $id
     */
    public function deleteLocker($id)
    {
        $query = 'DELETE FROM ' . DB_PREFIX . "sameday_locker WHERE id='{$this->db->escape($id)}'";

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
                sameday_code VARCHAR(255),
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

    private function createLockerTable()
    {
        $query = '
            CREATE TABLE IF NOT EXISTS ' . DB_PREFIX . 'sameday_locker (
                id INT(11) NOT NULL AUTO_INCREMENT,
                locker_id INT(11),
                name VARCHAR(255),
                county VARCHAR(255),
                city VARCHAR(255),
                address VARCHAR(255),
                lat VARCHAR(255),
                lng VARCHAR(255),
                postal_code VARCHAR(255),
                boxes TEXT,
                testing TINYINT(1),
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

    private function dropLockerTable()
    {
        $query = 'DROP TABLE IF EXISTS ' . DB_PREFIX . 'sameday_locker';

        $this->db->query($query);
    }
}
