<?php

use Sameday\Objects\Locker\LockerObject;
use Sameday\Objects\ParcelStatusHistory\ExpeditionObject;
use Sameday\Objects\ParcelStatusHistory\SummaryObject;
use Sameday\Objects\PickupPoint\PickupPointObject;
use Sameday\Objects\Service\OptionalTaxObject;
use Sameday\Objects\Service\ServiceObject;

require_once DIR_SYSTEM . 'library/sameday-php-sdk/src/Sameday/autoload.php';

class ModelExtensionShippingSameday extends Model
{
    /**
     * @return void
     */
    public function install()
    {
        $this->createAwbTable();
        $this->createServiceTable();
        $this->createPickUpPointTable();
        $this->createPackageTable();
        $this->createLockerTable();
        $this->createCountiesTable();
        $this->createCitiesTable();
    }

    /**
     * @return void
     */
    public function uninstall()
    {
        $this->dropAwbTable();
        $this->dropServiceTable();
        $this->dropPickUpPointTable();
        $this->dropPackageTable();
        $this->dropLockerTable();
        $this->dropCountiesTable();
        $this->dropCitiesTable();
    }

    /**
     * @param array $data
     *
     * @return void
     */
    public function saveAwb(array $data)
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
     * @param int $orderId
     * @param array $service
     * @param int $lockerId
     * @param string $lockerAddress
     *
     * @return void
     */
    public function updateShippingMethodAfterPostAwb(
        int $orderId,
        array $service,
        int $lockerId = null,
        string $lockerAddress = null
    ) {
        $shippingCode = sprintf(
            'sameday.%s.%s',
            $this->db->escape($service['sameday_code']),
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
     * @return array
     */
    public function getServices(): array
    {
        $rows = $this->db->query(sprintf(
            "SELECT * FROM %s WHERE testing='%s'",
            DB_PREFIX . "sameday_service",
            $this->getConfig('sameday_testing')
        ))->rows;

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
        $table = DB_PREFIX . "sameday_service";
        $id = $this->db->escape($id);

        $query = "SELECT * FROM $table WHERE id=$id";

        return $this->db->query($query)->row;
    }

    /**
     * @param int $samedayId
     *
     * @return array
     */
    public function getServiceSameday(int $samedayId): array
    {
        return $this->db->query(
            sprintf(
                "SELECT * FROM %s WHERE sameday_id='%s' AND testing='%s'",
                DB_PREFIX . "sameday_service",
                $this->db->escape($samedayId),
                $this->getConfig('sameday_testing')
            )
        )->row;
    }

    /**
     * @param string $samedayCode
     *
     * @return array
     */
    public function getSamedayServiceByCode(string $samedayCode): array
    {
        return $this->db->query(
            sprintf(
                "SELECT * FROM %s WHERE sameday_code='%s' AND testing='%s'",
                DB_PREFIX . "sameday_service",
                $this->db->escape($samedayCode),
                $this->getConfig('sameday_testing')
            )
        )->row;
    }

    /**
     * @return void
     */
    public function ensureSamedayServiceCodeColumn()
    {
        $query = 'SHOW COLUMNS FROM ' . DB_PREFIX . "sameday_service LIKE 'sameday_code'";
        $row = $this->db->query($query)->row;

        if ($row) {
            return;
        }

        $this->db->query(
            sprintf(
                "ALTER TABLE %s ADD `sameday_code` VARCHAR(255) DEFAULT '' NOT NULL",
                DB_PREFIX . "sameday_service"
            )
        );
    }

    /**
     * @return void
     */
    public function ensureSamedayServiceOptionalTaxColumn()
    {
        $query = 'SHOW COLUMNS FROM ' . DB_PREFIX . "sameday_service LIKE 'service_optional_taxes'";
        $row = $this->db->query($query)->row;

        if ($row) {
            return;
        }

        $this->db->query(
            sprintf(
                "ALTER TABLE %s ADD `service_optional_taxes` TEXT DEFAULT NULL",
                DB_PREFIX . "sameday_service"
            )
        );
    }

    /**
     * @param int $id
     * @param ServiceObject $serviceObject
     *
     * @return void
     */
    public function editService(int $id, ServiceObject $serviceObject)
    {
        $serviceOptionalTaxes = $this->db->escape($this->buildServiceOptionalTaxes($serviceObject->getOptionalTaxes()));
        $this->db->query('
            UPDATE ' . DB_PREFIX . "sameday_service SET
                sameday_id='{$this->db->escape($serviceObject->getId())}',
                sameday_name='{$this->db->escape($serviceObject->getName())}',
                sameday_code='{$this->db->escape($serviceObject->getCode())}',
                service_optional_taxes='$serviceOptionalTaxes'
            WHERE 
                id = '{$this->db->escape($id)}'
        ");
    }

    /**
     * @param int $id
     * @param int $status
     *
     * @return void
     */
    public function updateServiceStatus(int $id, int $status)
    {
        $this->db->query(
            sprintf(
                "UPDATE %s SET status='%s' WHERE id = '%s'",
                DB_PREFIX . "sameday_service",
                $status,
                $id
            )
        );
    }

    /**
     * @param ServiceObject $service
     *
     * @return void
     */
    public function addService(ServiceObject $service)
    {
        $testing = $this->getConfig("sameday_testing");
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
                '$testing',
                0,
                '{$this->db->escape($this->buildServiceOptionalTaxes($service->getOptionalTaxes()))}'
            )";

        $this->db->query($query);
    }

    /**
     * @param int $id
     * @param array $postFields
     *
     * @return void
     */
    public function updateService(int $id, array $postFields)
    {
        if ('' === $priceFree = $this->db->escape($postFields['price_free'] ?? '')) {
            $priceFree =  'NULL';
        }

        $this->db->query('
            UPDATE ' . DB_PREFIX . "sameday_service SET 
                name='{$this->db->escape($postFields['name'])}',
                status='{$this->db->escape($postFields['status'])}', 
                price='{$this->db->escape($postFields['price'])}',
                price_free=" . $priceFree . "
            WHERE 
                id = '{$this->db->escape($id)}'
        ");
    }

    /**
     * @param int $id
     *
     * @return void
     */
    public function deleteService(int $id)
    {
        $query = 'DELETE FROM ' . DB_PREFIX . "sameday_service WHERE id='{$this->db->escape($id)}'";

        $this->db->query($query);
    }

    /**
     * @return array
     */
    public function getPickupPoints(): array
    {
        return $this->db->query(
            sprintf(
                "SELECT * FROM %s WHERE `testing`='%s'",
                DB_PREFIX . "sameday_pickup_point",
                $this->getConfig('sameday_testing')
            )
        )->rows;
    }

    /**
     * @return array
     */
    public function getLockers(): array
    {
        return $this->db->query(
            sprintf(
                "SELECT * FROM %s WHERE `testing`='%s'",
                DB_PREFIX . "sameday_locker",
                $this->getConfig('sameday_testing')
            )
        )->rows;
    }

    /**
     * @param int $id
     *
     * @return array
     */
    public function getPickupPoint(int $id): array
    {
        return $this->db->query(
            sprintf(
                "SELECT * FROM %s WHERE `id` = %d",
                DB_PREFIX . "sameday_pickup_point",
                $id
            )
        )->row;
    }

    /**
     * @param int $samedayId
     *
     * @return array
     */
    public function getPickupPointSameday(int $samedayId): array
    {
        return $this->db->query(
            sprintf(
                "SELECT * FROM %s WHERE `sameday_id` = '%s' AND `testing` = '%s'",
                DB_PREFIX . "sameday_pickup_point",
                $samedayId,
                $this->getConfig('sameday_testing')
            )
        )->row;
    }

    /**
     * @param int $lockerId
     *
     * @return array
     */
    public function getLockerSameday(int $lockerId): array
    {
        return $this->db->query(
            sprintf(
                "SELECT * FROM %s WHERE `locker_id`='%s' AND `testing`='%s'",
                DB_PREFIX . "sameday_locker",
                $lockerId,
                $this->getConfig('sameday_testing')
            )
        )->row;
    }

    /**
     * @param int $id
     *
     * @return array
     */
    public function getLocker(int $id): array
    {
        return $this->db->query(
            sprintf(
                "SELECT * FROM %s WHERE `locker_id` = '%s' AND `testing` = '%s'",
                DB_PREFIX . "sameday_locker",
                $id,
                $this->getConfig('sameday_testing')
            )
        )->row;
    }

    /**
     * @param PickupPointObject $pickupPointObject
     *
     * @return void
     */
    public function addPickupPoint(PickupPointObject $pickupPointObject)
    {
        $testing = $this->getConfig('sameday_testing');
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
                '$testing', 
                '{$this->db->escape($pickupPointObject->getCity()->getName())}', 
                '{$this->db->escape($pickupPointObject->getCounty()->getName())}', 
                '{$this->db->escape($pickupPointObject->getAddress())}',  
                '{$this->db->escape($pickupPointObject->isDefault())}',    
                '{$this->db->escape(serialize($pickupPointObject->getContactPersons()))}')";

        $this->db->query($query);
    }

    /**
     * @param LockerObject $lockerObject
     *
     * @return void
     */
    public function addLocker(LockerObject $lockerObject)
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
                '{$this->getConfig('sameday_testing')}')";

        $this->db->query($query);
    }

    /**
     * @param PickupPointObject $pickupPointObject
     * @param int $pickupPointId
     *
     * @return void
     */
    public function updatePickupPoint(PickupPointObject $pickupPointObject, int $pickupPointId)
    {
        $this->db->query(
            'UPDATE ' . DB_PREFIX . "sameday_pickup_point SET 
                sameday_alias='{$this->db->escape($pickupPointObject->getAlias())}',
                city='{$this->db->escape($pickupPointObject->getCity()->getName())}', 
                county='{$this->db->escape($pickupPointObject->getCounty()->getName())}',
                address='{$this->db->escape($pickupPointObject->getAddress())}',
                default_pickup_point='{$this->db->escape($pickupPointObject->isDefault())}'
                WHERE id='{$pickupPointId}'"
        );
    }

    /**
     * @param LockerObject $lockerObject
     * @param int $lockerId
     *
     * @return void
     */
    public function updateLocker(LockerObject $lockerObject, int $lockerId)
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
                WHERE id='{$lockerId}'"
        );
    }

    /**
     * @param int $id
     *
     * @return void
     */
    public function deletePickupPoint(int $id)
    {
        $table = DB_PREFIX . "sameday_pickup_point";
        $id = $this->db->escape($id);

        $query = "DELETE FROM $table WHERE id = $id";

        $this->db->query($query);
    }

    /**
     * @param int $id
     *
     * @return void
     */
    public function deleteLocker(int $id)
    {
        $table = DB_PREFIX . "sameday_locker";
        $id = $this->db->escape($id);

        $query = "DELETE FROM $table WHERE id=$id";

        $this->db->query($query);
    }

    /**
     * @param string $awbNumber
     *
     * @return void
     */
    public function deleteAwb(string $awbNumber)
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
    public function getAwbForOrderId(int $orderId): array
    {
        return $this->db->query(
            sprintf(
                "SELECT * FROM %s WHERE `order_id` = '%s'",
                DB_PREFIX . "sameday_awb",
                $orderId
            )
        )->row;
    }

    /**
     * @param int $orderId
     * @param string $awbParcel
     * @param SummaryObject $summary
     * @param array $history
     * @param ExpeditionObject $expedition
     *
     * @return void
     */
    public function refreshPackageHistory(
        int $orderId,
        string $awbParcel,
        SummaryObject $summary,
        array $history,
        ExpeditionObject $expedition
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
    public function getPackagesForOrderId(int $orderId): array
    {
        $rows = $this->db->query(
            sprintf(
                "SELECT * FROM %s WHERE `order_id` = '%s'",
                DB_PREFIX . "sameday_package",
                $orderId
            )
        )->rows;


        foreach ($rows as $k => $row) {
            $rows[$k]['summary'] = unserialize($row['summary']);
            $rows[$k]['history'] = unserialize($row['history']);
            $rows[$k]['expedition_status'] = unserialize($row['expedition_status']);
            $rows[$k]['sync'] = unserialize($row['sync']);
        }

        return $rows;
    }

    /**
     * @param array $serviceTaxes
     *
     * @return string
     */
    private function buildServiceOptionalTaxes(array $serviceTaxes): string
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

    /**
     * @param string $county
     *
     * @return void
     */
    public function addCounty(string $county)
    {
        $query = '
            INSERT INTO ' . DB_PREFIX . "sameday_counties (
                county_id,
                county_name,
                county_code
            ) VALUES (
                '{$this->db->escape($county->getId())}',
                '{$this->db->escape($county->getName())}',
                '{$this->db->escape($county->getCode())}')";

        $this->db->query($query);
    }

    /**
     * @param string $city
     * @param int $zone_id
     *
     * @return void
     */
    public function addCity(string $city, int $zone_id)
    {
        $query = '
            INSERT INTO ' . DB_PREFIX . "sameday_cities (
                city_id,
                city_name,
                county_code,
                zone_id
            ) VALUES (
                '{$this->db->escape($city->city_id)}',
                '{$this->db->escape($city->city_name)}',
                '{$this->db->escape($city->county_code)}',
                '{$this->db->escape($zone_id)}'
            )";

        $this->db->query($query);
    }

    /**
     * @return void
     */
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

    /**
     * @return void
     */
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

    /**
     * @return void
     */
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

    /**
     * @return void
     */
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

    /**
     * @return void
     */
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

    /**
     * @return void
     */
    private function createCountiesTable()
    {
        $query = '
            CREATE TABLE IF NOT EXISTS ' . DB_PREFIX . 'sameday_counties (
                id INT(11) NOT NULL AUTO_INCREMENT,
                county_id INT(11),
                county_name VARCHAR(255),
                county_code VARCHAR(255),
                PRIMARY KEY (id)
            )  ENGINE=MyISAM DEFAULT COLLATE=utf8_general_ci;
        ';

        $this->db->query($query);
    }

    /**
     * @return void
     */
    private function createCitiesTable()
    {
        $query = '
            CREATE TABLE IF NOT EXISTS ' . DB_PREFIX . 'sameday_cities (
                id INT(11) NOT NULL AUTO_INCREMENT,
                city_id INT(11),
                city_name VARCHAR(255),
                county_code VARCHAR(255),
                zone_id INT(11),
                PRIMARY KEY (id)
            ) ENGINE=MyISAM DEFAULT COLLATE=utf8_general_ci;
        ';

        $this->db->query($query);
    }

    /**
     * @param int $countryId
     * @param string $countryCode
     *
     * @return null|array
     */
    public function getZoneId(int $countryId, string $countryCode)
    {
        $result = $this->db->query(
            sprintf(
                "SELECT zone_id FROM %s WHERE country_id = '%s' AND code = '%s'",
                DB_PREFIX . "zone",
                $countryId,
                $countryCode
            )
        )->row;

        return $result['zone_id'] ?? null;
    }

    /**
     * @return void
     */
    public function truncateNomenclator()
    {
        $this->db->query('TRUNCATE TABLE ' . DB_PREFIX . 'sameday_cities');
    }

    /**
     * @param string $isoCode
     *
     * @return array
     */
    public function getZone(string $isoCode): array
    {

        $table = DB_PREFIX . "country";

        $query = "SELECT * FROM $table WHERE iso_code_2 = '$isoCode'";

        return $this->db->query($query)->row;
    }

    /**
     * @return void
     */
    private function dropPickUpPointTable()
    {
        $query = 'DROP TABLE IF EXISTS ' . DB_PREFIX . 'sameday_pickup_point';

        $this->db->query($query);
    }

    /**
     * @return void
     */
    private function dropCountiesTable()
    {
        $query = 'DROP TABLE IF EXISTS ' . DB_PREFIX . 'sameday_counties';

        $this->db->query($query);
    }

    /**
     * @return void
     */
    private function dropCitiesTable()
    {
        $query = 'DROP TABLE IF EXISTS ' . DB_PREFIX . 'sameday_cities';

        $this->db->query($query);
    }

    /**
     * @return void
     */
    private function dropAwbTable()
    {
        $query = 'DROP TABLE IF EXISTS ' . DB_PREFIX . 'sameday_awb';

        $this->db->query($query);
    }

    /**
     * @return void
     */
    private function dropServiceTable()
    {
        $query = 'DROP TABLE IF EXISTS ' . DB_PREFIX . 'sameday_service';

        $this->db->query($query);
    }

    /**
     * @return void
     */
    private function dropPackageTable()
    {
        $query = 'DROP TABLE IF EXISTS ' . DB_PREFIX . 'sameday_package';

        $this->db->query($query);
    }

    /**
     * @return void
     */
    private function dropLockerTable()
    {
        $query = 'DROP TABLE IF EXISTS ' . DB_PREFIX . 'sameday_locker';

        $this->db->query($query);
    }

    /**
     * @param string $key
     *
     * @return string
     */
    public function getConfig(string $key): string
    {
        return $this->config->get($this->getKey($key));
    }

    /**
     * @param string $code
     * @param string $key
     * @param string $value
     *
     * @return null
     */
    public function editConfig(string $code, string $key, string $value)
    {
        return $this->config->set($code, $key, $value);
    }

    /**
     * @param string $key
     *
     * @return string
     */
    public function getKey(string $key): string
    {
        return $this->getPrefix() . $key;
    }

    /**
     * @param string $input
     *
     * @return string
     */
    public static function sanitizeInput(string $input): string
    {
        return stripslashes(strip_tags(str_replace(["'", "\""], '&#39;', $input)));
    }

    /**
     * @return string
     */
    public function getPrefix(): string
    {
        if (strpos(VERSION, '2') === 0) {
            return '';
        }

        return 'shipping_';
    }

    /**
     * @param string $code
     * @param array $data
     * @param int $store_id
     *
     * @return void
     */
    public function addAdditionalSetting(string $code, array $data, int $store_id = 0)
    {
        foreach ($data as $key => $value) {
            if (substr($key, 0, strlen($code)) == $code) {
                $this->db->query(sprintf(
                    "DELETE FROM %s WHERE `store_id` = %d AND `code` = '%s' AND `key` = '%s'",
                    DB_PREFIX . "setting",
                    $store_id,
                    $this->db->escape($code),
                    $this->db->escape($key)
                ));

                $queryFormat = "INSERT INTO %s SET `store_id` = %d, `code` = '%s', key = '%s', `value` = '%s'" ;
                if (is_array($value)) {
                    $value = $this->db->escape(json_encode($value, true));
                    $queryFormat .= ", `serialized` = 1";
                }

                $this->db->query(
                    sprintf(
                        $queryFormat,
                        DB_PREFIX . "setting",
                        $store_id,
                        $this->db->escape($code),
                        $value
                    )
                );
            }
        }
    }

    /**
     * @return bool
     */
    public function citiesCheck(): bool
    {
        return $this->db->query(
            sprintf("SHOW TABLES LIKE %s", DB_PREFIX . "sameday_cities'")
        )->num_rows > 0;
    }

    /**
     * @param string $isoCode
     *
     * @return mixed
     */
    public function getCountryByCode(string $isoCode)
    {
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "country WHERE iso_code_2 = '$isoCode'");

        return $query->row['country_id'];
    }

    /**
     * @param int $country_id
     * @param string $county
     *
     * @return void
     */
    public function addZoneCounty(int $country_id, string $county)
    {
        $county_name = $this->db->escape($county->county);
        $county_code = $this->db->escape($county->code);

        $zone = $this->db->query(sprintf(
            "SELECT * FROM %s WHERE `country_id` = '%s' AND `name` = '%s'",
            DB_PREFIX . "zone",
            $country_id,
            $county_name
        ));

        if ($zone->num_rows === 0) {
            $this->db->query("INSERT INTO `" . DB_PREFIX . "zone` SET 
                `country_id` = '$country_id', 
                `name` = '$county_name', 
                `code` = '$county_code', 
                `status` = 1
            ");
        } else {
            if ($zone->row['code'] === '') {
                $this->db->query(sprintf(
                    "UPDATE %s SET `code` = '%s' WHERE `zone_id` = '%d'",
                    DB_PREFIX . 'zone',
                    $county_code,
                    (int)$zone->row['zone_id']
                ));
            }
        }
    }
    // End of file
}
