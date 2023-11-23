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

    public function __construct($registry)
    {
        $this->registry = $registry;
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
        return $this->getModel()->getConfig($key);
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
        $this->getModel()->addAdditionalSetting(
            $this->getKeyFormat(self::OC_SETTING_SAMEDAY_CODE),
            [$this->getKeyFormat(self::KEYS[$key]) => $value]
        );
    }

    /**
     * @param $key
     *
     * @return string
     * @throws Exception
     */
    private function getKeyFormat($key): string
    {
        return $this->getModel()->getPrefix() . strtolower($key);
    }

    /**
     * @return mixed
     * @throws Exception
     */
    private function getModel()
    {
        $this->loader->model('extension/shipping/sameday');

        return $this->registry->get('model_extension_shipping_sameday');
    }
}