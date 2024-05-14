<?php

use Sameday\Exceptions\SamedaySDKException;
use Sameday\SamedayClient;

class SamedayHelper
{
    /**
     * @var string $prefix
     */
    private $prefix;

    /**
     * @var Registry $registry
     */
    private $registry;

    /**
     * @var array $samedayConfigs
     */
    private $samedayConfigs;

    public const CASH_ON_DELIVERY_CODE = 'cod';

    public const SAMEDAY_6H_SERVICE = '6H';
    public const DEFAULT_SAMEDAY_SERVICE = '24';
    public const LOCKER_NEXT_DAY_SERVICE = 'LN';
    public const SAMEDAY_PUDO_SERVICE = 'PD';
    public const DEFAULT_SAMEDAY_CROSSBORDER_SERVICE = 'XB';
    public const LOCKER_NEXT_DAY_CROSSBORDER_SERVICE = 'XL';
    public const OOH_SERVICE = 'OOH';

    public const SAMEDAY_IN_USE_SERVICES = [
        self::SAMEDAY_6H_SERVICE,
        self::DEFAULT_SAMEDAY_SERVICE,
        self::LOCKER_NEXT_DAY_SERVICE,
        self::SAMEDAY_PUDO_SERVICE,
        self::DEFAULT_SAMEDAY_CROSSBORDER_SERVICE,
        self::LOCKER_NEXT_DAY_CROSSBORDER_SERVICE,
    ];

    public const ELIGIBLE_SAMEDAY_SERVICES = [
        self::SAMEDAY_6H_SERVICE,
        self::DEFAULT_SAMEDAY_SERVICE,
        self::LOCKER_NEXT_DAY_SERVICE
    ];

    public const ELIGIBLE_SAMEDAY_SERVICES_CROSSBORDER = [
        self::DEFAULT_SAMEDAY_CROSSBORDER_SERVICE,
        self::LOCKER_NEXT_DAY_CROSSBORDER_SERVICE
    ];

    public const OOH_SERVICES = [
        self::LOCKER_NEXT_DAY_SERVICE,
        self::SAMEDAY_PUDO_SERVICE,
    ];

    public const ELIGIBLE_TO_LOCKER = [
        self::LOCKER_NEXT_DAY_SERVICE,
        self::LOCKER_NEXT_DAY_CROSSBORDER_SERVICE,
    ];

    public const OOH_SERVICES_LABELS = [
        self::API_HOST_LOCALE_RO => 'Ridicare personala',
        self::API_HOST_LOCALE_BG => 'Персонален асансьор',
        self::API_HOST_LOCALE_HU => 'Személyi lift',
    ];

    public const AFTER_48_HOURS = 172800;

    // PDO stands for Personal Delivery Option and is an additional tax that apply to Service
    public const SERVICE_OPTIONAL_TAX_PDO_CODE = 'PDO';

    public const API_PROD = 0;
    public const API_DEMO = 1;

    public const API_HOST_LOCALE_RO = 'RO';
    public const API_HOST_LOCALE_HU = 'HU';
    public const API_HOST_LOCALE_BG = 'BG';

    public const SAMEDAY_ELIGIBLE_CURRENCIES = [
        self::API_HOST_LOCALE_RO => 'RON',
        self::API_HOST_LOCALE_HU => 'HUF',
        self::API_HOST_LOCALE_BG => 'BGN',
    ];

    public static function getEnvModes(): array
    {
        return [
            self::API_HOST_LOCALE_RO => [
                self::API_PROD => 'https://api.sameday.ro',
                self::API_DEMO => 'https://sameday-api.demo.zitec.com',
            ],
            self::API_HOST_LOCALE_HU => [
                self::API_PROD => 'https://api.sameday.hu',
                self::API_DEMO => 'https://sameday-api-hu.demo.zitec.com',
            ],
            self::API_HOST_LOCALE_BG => [
                self::API_PROD => 'https://api.sameday.bg',
                self::API_DEMO => 'https://sameday-api-bg.demo.zitec.com',
            ]
        ];
    }

    private static function getEAWBInstances(): array
    {
        return [
            self::API_HOST_LOCALE_RO => 'https://eawb.sameday.ro/',
            self::API_HOST_LOCALE_HU => 'https://eawb.sameday.hu/',
            self::API_HOST_LOCALE_BG => 'https://eawb.sameday.bg/',
        ];
    }

    public static function getEAWBInstanceUrlByCountry($countryCode): string
    {
        return self::getEawbInstances()[$countryCode ?? self::API_HOST_LOCALE_RO];
    }

    /**
     * @return int
     */
    public function isTesting(): int
    {
        return (int) $this->samedayConfigs['sameday_testing'];
    }

    /**
     * @return string
     */
    public function getApiUrl(): string
    {
        return self::getEnvModes()[$this->getHostCountry()][$this->isTesting()];
    }

    /**
     * @param $samedayConfigs
     * @param $registry
     * @param $prefix
     */
    public function __construct($samedayConfigs, $registry, $prefix)
    {
        $this->samedayConfigs = $samedayConfigs;

        $this->registry = $registry;

        $this->prefix = $prefix;
    }

    /**
     * @param null $username
     * @param null $password
     * @param null $testing
     *
     * @return SamedayClient
     * @throws SamedaySDKException
     */
    public function initClient($username = null, $password = null, $apiUrl = null): SamedayClient
    {
        if ($username === null && $password === null && $apiUrl === null) {
            $username = $this->samedayConfigs['sameday_username'];
            $password = $this->samedayConfigs['sameday_password'];
            $apiUrl = $this->getApiUrl();
        }

        return new SamedayClient(
            $username,
            $password,
            $apiUrl,
            'opencart',
            VERSION,
            'curl',
            Samedayclasses::getSamedayPersistenceDataHandler($this->registry)
        );
    }

    /**
     * @param string $samedayCode
     *
     * @return bool
     */
    public function isEligibleToLocker(string $samedayCode): bool
    {
        return in_array($samedayCode, self::ELIGIBLE_TO_LOCKER, true);
    }

    public function isOohDeliveryOption(string $samedayCode): bool
    {
        return in_array($samedayCode, self::OOH_SERVICES);
    }

    public function getHostCountry(): string
    {
        return $this->samedayConfigs['sameday_host_country'] ?? self::API_HOST_LOCALE_RO;
    }
}