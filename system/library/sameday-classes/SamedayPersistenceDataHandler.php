<?php

use Sameday\SamedayClient;
use Sameday\PersistentData\SamedayPersistentDataInterface;

class SamedayPersistenceDataHandler implements SamedayPersistentDataInterface
{
    const KEYS = [
        SamedayClient::KEY_TOKEN => 'SAMEDAY_TOKEN',
        SamedayClient::KEY_TOKEN_EXPIRES => 'SAMEDAY_TOKEN_EXPIRES_AT',
    ];

    const OC_SETTING_SAMEDAY_CODE = "sameday";

    protected $registry;
    protected $loader;
    protected $prefix;

    public function __construct($registry, $prefix)
    {
        $this->registry = $registry;
        $this->prefix = $prefix;
        $this->loader = new Loader($this->registry);
    }

    /**
     * @param $key
     * @return mixed
     *
     * @throws Exception
     */
    public function get($key)
    {
        return $this->getModel()->getConfig($this->getKeyFormat($key));
    }

    /**
     * @param string $key
     *
     * @param mixed $value
     *
     * @throws Exception
     */
    public function set($key, $value)
    {
        $this->getModel()->addAdditionalSetting(self::OC_SETTING_SAMEDAY_CODE, [$this->getKeyFormat($key) => $value]);
    }

    /**
     * @param $key
     *
     * @return string
     */
    private function getKeyFormat($key): string
    {
        return $this->prefix . strtolower(self::KEYS[$key]);
    }

    /**
     * @throws Exception
     */
    private function getModel()
    {
        $this->loader->model('extension/shipping/sameday');

        return $this->registry->get('model_extension_shipping_sameday');
    }
}