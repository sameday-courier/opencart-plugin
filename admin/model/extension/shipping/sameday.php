<?php

use Sameday\Objects\Locker\LockerObject;
use Sameday\Objects\PickupPoint\PickupPointObject;
use Sameday\Objects\Service\OptionalTaxObject;
use Sameday\Objects\Service\ServiceObject;

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
     * @param $service
     * @param $lockerId
     * @param $lockerAddress
     * @return void
     */
    public function updateShippingMethodAfterPostAwb($orderId, $service, $lockerId = null, $lockerAddress = null)
    {
        $shippingCode = sprintf(
            'sameday.%s.%s',
            $this->db->escape($service['name']),
            $this->db->escape($service['sameday_id'])
        );

        if (null !== $lockerId && null !== $lockerAddress) {
            $shippingCode .= sprintf('.%s.%s', $this->db->escape($lockerId), $this->db->escape($lockerAddress));
        }

        $this->db->query('
            UPDATE ' . DB_PREFIX . "order SET 
                shipping_method='{$this->db->escape($service['name'])}',
                shipping_code='{$this->db->escape($shippingCode)}'
            WHERE 
                order_id = '{$this->db->escape($orderId)}'
        ");
    }

    /**
     * @param string $hostCountry
     *
     * @return array
     */
    public function getCounties(string $hostCountry): array
    {
        $table = DB_PREFIX . "country";
        $isoCode = $hostCountry;

        $query = sprintf("SELECT country_id FROM %s WHERE iso_code_2='%s'", $table, $this->db->escape($isoCode));

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
        $table = DB_PREFIX . "sameday_service";

        $query = sprintf("SELECT * FROM %s WHERE testing='%s'", $table, $this->db->escape($testing));

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
    public function getService(int $id)
    {
        $table = DB_PREFIX. "sameday_service";
        $id = $this->db->escape($id);

        $query = "SELECT * FROM $table WHERE id=$id";

        return $this->db->query($query)->row;
    }

    /**
     * @param int $samedayId
     * @param bool $testing
     *
     * @return array
     */
    public function getServiceSameday(int $samedayId, $testing)
    {
        $table = DB_PREFIX . "sameday_service";

        $query = sprintf("SELECT * FROM %s WHERE sameday_id='%s' AND testing='%s'",
            $table,
            $this->db->escape($samedayId),
            $this->db->escape($testing)
        );

        return $this->db->query($query)->row;
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

    public function ensureSamedayServiceOptionalTaxColumn()
    {
        $query = 'SHOW COLUMNS FROM ' . DB_PREFIX . "sameday_service LIKE 'service_optional_taxes'";
        $row = $this->db->query($query)->row;

        if ($row) {
            return;
        }

        $this->db->query('alter table '. DB_PREFIX .'sameday_service add service_optional_taxes TEXT default null');
    }

    /**
     * @param $id
     * @param ServiceObject $serviceObject
     *
     * @return void
     */
    public function editService($id, ServiceObject $serviceObject)
    {
        $this->db->query('
            UPDATE ' . DB_PREFIX . "sameday_service SET
                sameday_id='{$this->db->escape($serviceObject->getId())}',
                sameday_name='{$this->db->escape($serviceObject->getName())}',
                sameday_code='{$this->db->escape($serviceObject->getCode())}',
                service_optional_taxes='{$this->db->escape($this->buildServiceOptionalTaxes($serviceObject->getOptionalTaxes()))}'
            WHERE 
                id = '{$this->db->escape($id)}'
        ");
    }

    /**
     * @param ServiceObject $service
     * @param bool $testing
     */
    public function addService(ServiceObject $service, bool $testing)
    {
        $query = '
            INSERT INTO ' . DB_PREFIX . "sameday_service (
                sameday_id, 
                sameday_name, 
                sameday_code,
                testing, 
                status,
                service_optional_taxes
            ) VALUES (
                '{$this->db->escape($service->getId())}', 
                '{$this->db->escape($service->getName())}', 
                '{$this->db->escape($service->getCode())}', 
                '{$this->db->escape($testing)}',
                0,
                '{$this->db->escape($this->buildServiceOptionalTaxes($service->getOptionalTaxes()))}'
            )";

        $this->db->query($query);
    }

    /**
     * @param int $id
     * @param array $postFields
     */
    public function updateService($id, $postFields)
    {
        $this->db->query('
            UPDATE ' . DB_PREFIX . "sameday_service SET 
                name='{$this->db->escape($postFields['name'])}',
                status='{$this->db->escape($postFields['status'])}', 
                price='{$this->db->escape($postFields['price'])}',
                price_free=" . ((string) $postFields['price_free'] !== '' ? ('\''.$this->db->escape($postFields['price_free']) . '\'') : 'NULL') . "
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
     * @param PickupPointObject $pickupPointObject
     * @param bool $testing
     */
    public function addPickupPoint(PickupPointObject $pickupPointObject, $testing)
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
     * @param LockerObject $lockerObject
     * @param bool $testing
     */
    public function addLocker(LockerObject $lockerObject, $testing)
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
     * @param PickupPointObject $pickupPointObject
     * @param int $pickuppointId
     */
    public function updatePickupPoint(PickupPointObject $pickupPointObject, int $pickuppointId)
    {
        $this->db->query(
            'UPDATE ' . DB_PREFIX . "sameday_pickup_point SET 
                sameday_alias='{$this->db->escape($pickupPointObject->getAlias())}',
                city='{$this->db->escape($pickupPointObject->getCity()->getName())}', 
                county='{$this->db->escape($pickupPointObject->getCounty()->getName())}',
                address='{$this->db->escape($pickupPointObject->getAddress())}',
                default_pickup_point='{$this->db->escape($pickupPointObject->isDefault())}'
            WHERE 
                id='{$pickuppointId}'
        ");
    }

    /**
     * @param LockerObject $lockerObject
     * @param int $lockerId
     */
    public function updateLocker(LockerObject $lockerObject, $lockerId)
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
        $table = DB_PREFIX . "sameday_pickup_point";
        $id = $this->db->escape($id);

        $query = "DELETE FROM $table WHERE id = $id";

        $this->db->query($query);
    }

    /**
     * @param int $id
     */
    public function deleteLocker($id)
    {
        $table = DB_PREFIX . "sameday_locker";
        $id = $this->db->escape($id);

        $query = "DELETE FROM $table WHERE id=$id";

        $this->db->query($query);
    }

    /**
     * @param string $awbNumber
     */
    public function deleteAwb($awbNumber)
    {
        $table = DB_PREFIX . "sameday_awb";
        $awbNumber = $this->db->escape($awbNumber);

        $query = sprintf("DELETE FROM $table WHERE awb_number='%s'", $awbNumber);

        $this->db->query($query);
    }

    /**
     * @param int $orderId
     *
     * @return array
     */
    public function getAwbForOrderId($orderId)
    {
        $table = DB_PREFIX . "sameday_awb";
        $orderId = (int) $this->db->escape($orderId);

        $query = "SELECT * FROM $table WHERE order_id = $orderId";

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

    private function buildServiceOptionalTaxes($serviceTaxes): string
    {
        $data = [];
        /** @var OptionalTaxObject $serviceTax */
        foreach ($serviceTaxes as $serviceTax) {
            $data[] = [
                'id' => $serviceTax->getId(),
                'code' => $serviceTax->getCode(),
                'type' => $serviceTax->getPackageType()
            ];
        }

        return json_encode($data);
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

    /**
     * @param string $key
     *
     * @return mixed
     */
    public function getConfig($key)
    {
        return $this->config->get($this->getKey($key));
    }

    public function getKey($key)
    {
        return $this->getPrefix() . $key;
    }

    /**
     * @return string
     */
    public function getPrefix()
    {
        if (strpos(VERSION, '2') === 0) {
            return '';
        }

        return 'shipping_';
    }

    /**
     * @param $code
     * @param $data
     * @param int $store_id
     */
    public function addAdditionalSetting($code, $data, $store_id = 0) {
        foreach ($data as $key => $value) {
            if (substr($key, 0, strlen($code)) == $code) {
                $this->db->query("DELETE FROM `" . DB_PREFIX . "setting` WHERE store_id = '" . (int)$store_id . "' AND `code` = '" . $this->db->escape($code) . "' AND `key` = '".$this->db->escape($key)."'");
                if (!is_array($value)) {
                    $this->db->query("INSERT INTO " . DB_PREFIX . "setting SET store_id = '" . (int)$store_id . "', `code` = '" . $this->db->escape($code) . "', `key` = '" . $this->db->escape($key) . "', `value` = '" . $this->db->escape($value) . "'");
                } else {
                    $this->db->query("INSERT INTO " . DB_PREFIX . "setting SET store_id = '" . (int)$store_id . "', `code` = '" . $this->db->escape($code) . "', `key` = '" . $this->db->escape($key) . "', `value` = '" . $this->db->escape(json_encode($value, true)) . "', serialized = '1'");
                }
            }
        }
    }
}
