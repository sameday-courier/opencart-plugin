<?php

use Sameday\Exceptions\SamedayAuthenticationException;
use Sameday\Exceptions\SamedayAuthorizationException;
use Sameday\Exceptions\SamedayBadRequestException;
use Sameday\Exceptions\SamedayNotFoundException;
use Sameday\Exceptions\SamedayOtherException;
use Sameday\Exceptions\SamedaySDKException;
use Sameday\Exceptions\SamedayServerException;
use Sameday\Objects\Locker\LockerObject;
use Sameday\Objects\ParcelDimensionsObject;
use Sameday\Objects\Types\AwbPaymentType;
use Sameday\Objects\Types\PackageType;

class ModelExtensionShippingSameday extends Model
{
    /**
     * @var SamedayHelper
     */
    private $samedayHelper;

    const DEFAULT_VALUE_LOCKER_MAX_ITEMS = 5;

    const SAMEDAY_CONFIGS = [
        'username',
        'password',
        'testing',
        'host_country',
    ];

    /**
     * @param mixed $registry
     */
    public function __construct($registry)
    {
        parent::__construct($registry);

        require_once DIR_SYSTEM . 'library/sameday-php-sdk/src/Sameday/autoload.php';

        $this->samedayHelper = Samedayclasses::getSamedayHelper($this->buildRequest(), $registry, $this->getPrefix());
    }

    /**
     * @param array $address
     * @return array|null
     * @throws SamedaySDKException
     * @throws SamedayAuthenticationException
     * @throws SamedayAuthorizationException
     * @throws SamedayNotFoundException
     * @throws SamedayOtherException
     * @throws SamedayServerException
     */
    public function getQuote(array $address)
    {
        $table = DB_PREFIX . "zone_to_geo_zone";
        $countryId = (int) $address['country_id'];
        $zoneId = (int) $address['zone_id'];

        $query = $this->db->query(
            "SELECT * FROM $table WHERE 
            geo_zone_id='{$this->getConfig('sameday_geo_zone_id')}'
            AND country_id=$countryId
            AND (zone_id=$zoneId OR zone_id=0)"
        );

        if (!$this->getConfig('sameday_geo_zone_id')) {
            $status = true;
        } elseif ($query->num_rows) {
            $status = true;
        } else {
            $status = false;
        }

        $this->load->language('extension/shipping/sameday');

        $method_data = array();
        if (!$status) {
            return $method_data;
        }

        $isEstimatedCostEnabled = $this->getConfig('sameday_estimated_cost');
        $hostCountry = $this->getHostCountry();
        $destCountry = $address['iso_code_2'];

        $eligibleServices = $hostCountry === $destCountry
            ? SamedayHelper::ELIGIBLE_SAMEDAY_SERVICES
            : SamedayHelper::ELIGIBLE_SAMEDAY_SERVICES_CROSSBORDER;
        $availableService = array_filter(
            $this->getAvailableServices(),
            static function (array $service) use ($eligibleServices) {
                return in_array($service['sameday_code'], $eligibleServices);
            }
        );

        $quote_data = array();

        if (empty($availableService)) {
            return null;
        }

        foreach ($availableService as $service) {
            if ($service['sameday_code'] === $this->samedayHelper::SAMEDAY_6H_SERVICE
                && $address['zone'] !== "Bucuresti"
            ) {
                continue;
            }

            if ($this->samedayHelper->isEligibleToLocker($service['sameday_code'])) {
                if ('' === $lockerMaxItems = ($this->getConfig('sameday_locker_max_items') ?? '')) {
                    $lockerMaxItems = self::DEFAULT_VALUE_LOCKER_MAX_ITEMS;
                }

                if ((count($this->cart->getProducts()) > $lockerMaxItems)) {
                    continue;
                }
            }

            $price = $service['price'];

            if ($service['price_free'] !== null && $this->cart->getSubtotal() >= $service['price_free']) {
                $price = 0;
            }

            if ($isEstimatedCostEnabled) {
                $estimatedCost = $this->estimateCost($address, $service['sameday_id']);
                if ($estimatedCost !== null) {
                    $price = $estimatedCost;
                }
            }

            $serviceCode = $service['sameday_code'];
            if ($this->samedayHelper->isOohDeliveryOption($service['sameday_code'])) {
                $serviceCode = $this->samedayHelper::OOH_SERVICE_CODE;
            }

            $quote_data[$serviceCode] = array(
                'sameday_name' => $service['name'],
                'code' => sprintf(
                    '%s.%s.%s',
                    'sameday',
                    $serviceCode,
                    $service['sameday_id']
                ),
                'service_id' => $service['sameday_id'],
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
                ),
            );

            if ($this->samedayHelper->isOohDeliveryOption($service['sameday_code'])) {
                if (true === $this->isShowLockersMap()) {
                    $quote_data[$serviceCode]['lockers'] = '';
                    $quote_data[$serviceCode]['destCountry'] = $destCountry;
                    $quote_data[$serviceCode]['destCity'] = $address['city'];
                    $quote_data[$serviceCode]['destCounty'] = $address['zone'];
                    $quote_data[$serviceCode]['apiUsername'] = $this->getApiUsername();
                } else {
                    $this->syncLockers();

                    $quote_data[$serviceCode]['lockers'] = $this->showLockersList();
                }
            }
        }

        if (empty($quote_data)) {
            return null;
        }

        return array(
            'code' => 'sameday',
            'title' => 'Sameday',
            'quote' => $quote_data,
            'sort_order' => $this->getConfig('sameday_sort_order'),
            'error' => false
        );
    }

    /**
     * @return array
     */
    private function buildRequest(): array
    {
        $keys = self::SAMEDAY_CONFIGS;

        $entries = [];
        foreach ($keys as $key) {
            $entries["sameday_$key"] = $this->request->post["{$this->getPrefix()}sameday_$key"]
                ?? $this->getConfig("sameday_$key")
            ;
        }

        return $entries;
    }

    /**
     * @return void
     *
     * @throws SamedaySDKException
     */
    private function syncLockers()
    {
        $time = time();

        if ($time >= (((int) $this->getConfig('sameday_sync_lockers_ts')) + $this->samedayHelper::AFTER_48_HOURS)) {
            $this->lockersRefresh();
        }
    }

    /**
     * @return void
     *
     * @throws SamedaySDKException
     */
    private function lockersRefresh()
    {
        $this->load->model('extension/shipping/sameday');
        $sameday = new \Sameday\Sameday($this->samedayHelper->initClient());

        $request = new Sameday\Requests\SamedayGetLockersRequest();

        try {
            $lockers = $sameday->getLockers($request)->getLockers();
        } catch (\Exception $exception) {
            return;
        }

        $remoteLockers = [];
        foreach ($lockers as $lockerObject) {
            $locker = $this->getLockerSameday($lockerObject->getId());
            if (!$locker) {
                $this->addLocker($lockerObject);
            } else {
                $this->updateLocker($lockerObject, $locker['id']);
            }

            $remoteLockers[] = $lockerObject->getId();
        }

        // Build array of local lockers.
        $localLockers = array_map(
            static function ($locker) {
                if (isset($locker['id'])) {
                    return array(
                        'id' => (int) $locker['id'],
                        'sameday_id' => (int) $locker['locker_id'],
                    );
                }

                return false;
            },
            $this->getLockers()
        );

        // Delete local lockers that aren't present in remote lockers anymore.
        foreach ($localLockers as $localLocker) {
            if (!in_array($localLocker['sameday_id'], $remoteLockers, true)) {
                $this->deleteLocker($localLocker['id']);
            }
        }

        $this->updateLastSyncTimestamp();
    }

    /**
     * @return void
     */
    private function updateLastSyncTimestamp()
    {
        $store_id = 0;
        $code = "{$this->getPrefix()}sameday";
        $key = "{$this->getPrefix()}sameday_sync_lockers_ts";
        $time = time();

        $lastTimeSynced = $this->getConfig('sameday_sync_lockers_ts');

        if ($lastTimeSynced === null) {
            $this->db->query(
                sprintf(
                    "INSERT INTO %s SET `store_id` = %d, `code` = '%s', `key` = '%s', `value` = '%s'",
                    DB_PREFIX . "setting",
                    $store_id,
                    $this->db->escape($code),
                    $this->db->escape($key),
                    $this->db->escape($time)
                )
            );
        }

        $lastTs = $this->db->query(
            sprintf(
                "SELECT * FROM %s WHERE `store_id` = %d AND `code` = '%s' AND `key` = '%s'",
                DB_PREFIX . "setting",
                $store_id,
                $this->db->escape($code),
                $this->db->escape($key)
            )
        )->row;

        $this->db->query(
            sprintf(
                "UPDATE %s SET `value` = '%s' WHERE `setting_id` = '%s'",
                DB_PREFIX . "settting",
                $this->db->escape($time),
                $this->db->escape($lastTs['setting_id'])
            )
        );
    }

    /**
     * @return array
     */
    public function getLockers(): array
    {
        return (array) $this->db->query(
            sprintf(
                "SELECT * FROM %s WHERE `testing` = '%'",
                DB_PREFIX . "sameday_locker",
                $this->db->escape($this->getPrefix())
            )
        )->rows;
    }

    /**
     * @return bool
     */
    private function isShowLockersMap(): bool
    {
        return (bool) $this->getConfig('sameday_show_lockers_map');
    }

    /**
     * @return array
     */
    public function showLockersList(): array
    {
        $lockers = array();
        foreach ($this->getLockers() as $locker) {
            if ('' !== $locker['city']) {
                $lockers[$locker['city'] . ' (' . $locker['county'] . ')'][] = $locker;
            }
        }

        return $lockers;
    }

    /**
     * @param int $lockerId
     *
     * @return mixed
     */
    private function getLockerSameday(int $lockerId)
    {
        return $this->db->query(
            sprintf(
                "SELECT * FROM %s WHERE `locker_id` = '%s' AND `testing` = '%'",
                DB_PREFIX . "sameday_locker",
                $lockerId,
                $this->isTesting()
            )
        )->row;
    }

    /**
     * @param LockerObject $lockerObject
     *
     * @return void
     */
    private function addLocker(LockerObject $lockerObject)
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
                '{$this->isTesting()}')";

        $this->db->query($query);
    }

    /**
     * @param LockerObject $lockerObject
     * @param int $lockerId
     *
     * @return void
     */
    private function updateLocker(LockerObject $lockerObject, int $lockerId)
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
                id='$lockerId'
            "
        );
    }

    /**
     * @param int $id
     *
     * @return void
     */
    private function deleteLocker(int $id)
    {
        $this->db->query(
            sprintf(
                "DELETE FROM %s WHERE `id` = %d",
                DB_PREFIX . "sameday_locker",
                $id
            )
        );
    }

    /**
     * @return mixed
     */
    private function isTesting()
    {
        return $this->getConfig('sameday_testing');
    }

    /**
     * @return mixed
     */
    public function getHostCountry()
    {
        return $this->getConfig('sameday_host_country');
    }

    /**
     * @return string
     */
    public function getApiUsername(): string
    {
        return $this->getConfig('sameday_username');
    }

    /**
     * @param array $address
     * @param int $serviceId
     *
     * @return float|null
     *
     * @throws SamedayAuthenticationException
     * @throws SamedayAuthorizationException
     * @throws SamedayNotFoundException
     * @throws SamedayOtherException
     * @throws SamedaySDKException
     * @throws SamedayServerException
     */
    private function estimateCost(array $address, int $serviceId)
    {
        $pickupPointId = $this->getDefaultPickupPointId();
        $weight = $this->cart->getWeight();

        $selectedPaymentMethod = $this->request->cookie['selected_payment_method'] ?? null;
        $repayment = 0;
        if (null === $selectedPaymentMethod || $this->samedayHelper::CASH_ON_DELIVERY_CODE === $selectedPaymentMethod) {
            $repayment = $this->cart->getTotal();
        }

        $estimateCostRequest = new Sameday\Requests\SamedayPostAwbEstimationRequest(
            $pickupPointId,
            null,
            new Sameday\Objects\Types\PackageType(
                PackageType::PARCEL
            ),
            [new ParcelDimensionsObject($weight)],
            $serviceId,
            new Sameday\Objects\Types\AwbPaymentType(
                AwbPaymentType::CLIENT
            ),
            new Sameday\Objects\PostAwb\Request\AwbRecipientEntityObject(
                ucwords(strtolower($address['city'])) !== 'Bucuresti' ? $address['city'] : 'Sector 1',
                $address['zone'],
                ltrim($address['address_1']) . " " . $address['address_2'],
                null,
                null,
                null,
                null,
                $address['postcode']
            ),
            0,
            $repayment,
            null,
            array()
        );

        $sameday = new Sameday\Sameday($this->samedayHelper->initClient());

        try {
            return $sameday->postAwbEstimation($estimateCostRequest)->getCost();
        } catch (SamedayBadRequestException $exception) {
            return null;
        } catch (SamedaySDKException $exception) {
            return null;
        }
    }

    /**
     * @return array
     */
    private function getAvailableServices(): array
    {
        $services = $this->db->query(
            sprintf(
                "SELECT * FROM %s WHERE testing='%s' AND status > 0",
                DB_PREFIX . "sameday_service",
                $this->isTesting()
            )
        )->rows;

        $availableServices = array();
        foreach ($services as $service) {
            if (1 === (int) $service['status']) {
                $availableServices[] = $service;
            }
        }

        return $availableServices;
    }

    /**
     * @return array|null
     */
    private function getDefaultPickupPointId()
    {
        $defaultPickupPoint = $this->db->query(
            sprintf(
                "SELECT sameday_id FROM %s WHERE testing='%s' AND default_pickup_point=1",
                DB_PREFIX . "sameday_pickup_point",
                $this->isTesting()
            )
        )->row;

        if (empty($defaultPickupPoint)) {
            return null;
        }

        return $defaultPickupPoint['sameday_id'];
    }

    /**
     * @param string $key
     *
     * @return mixed
     */
    public function getConfig(string $key)
    {
        return $this->config->get("{$this->getPrefix()}$key");
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
                        $this->db->escape($key),
                        $value
                    )
                );
            }
        }
    }

    /**
     * @param string $isoCode
     *
     * @return int|null
     */
    public function getCountryIdByCode(string $isoCode)
    {
        return $this->db->query(
            sprintf(
                "SELECT `country_id` FROM %s WHERE `iso_code_2` = '%s'",
                DB_PREFIX . "country",
                $this->db->escape($isoCode)
            )
        )->row['country_id'] ?? null;
    }

    /**
     * @param int $countryId
     *
     * @return array
     */
    public function getCountiesByCountryId(int $countryId): array
    {
        return $this->db->query(
            sprintf(
                "SELECT * FROM %s WHERE `country_id` = %d",
                DB_PREFIX . "zone",
                $countryId
            )
        )->rows;
    }

    /**
     * @param int $zoneId
     *
     * @return array
     */
    public function getCitiesByCountyId(int $zoneId): array
    {
        return $this->db->query(
            sprintf(
                "SELECT * FROM %s WHERE `zone_id` = %d",
                DB_PREFIX . "sameday_cities",
                $zoneId
            )
        )->rows;
    }

    /**
     * @return string
     */
    public function displayCities(): string
    {
        // Display in Checkout zone
        $countriesCodes = $this->getCountriesCodes();
        $samedayCities = [];
        foreach ($countriesCodes as $countryCode) {
            $countryId = $this->getCountryIdByCode($countryCode);
            $samedayCities[$countryId] = [];
            if (null !== $countryId) {
                $counties = $this->getCountiesByCountryId($countryId);
                foreach ($counties as $county) {
                    $zone_id = $county['zone_id'];
                    $samedayCities[$countryId][$zone_id] = array_map(
                        static function (array $city) {
                            return [
                                'name' => $city['city_name']
                            ];
                        },
                        $this->getCitiesByCountyId($zone_id)
                    );
                }
            }
        }

        return json_encode($samedayCities);
    }

    /**
     * @return array
     */
    public function getCountriesCodes(): array
    {
        return [
            SamedayHelper::API_HOST_LOCALE_RO,
            SamedayHelper::API_HOST_LOCALE_BG,
            SamedayHelper::API_HOST_LOCALE_HU,
        ];
    }
    // End of file
}
