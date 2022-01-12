<?php

use Sameday\SamedayClient;
use Sameday\PersistentData\SamedayPersistentDataInterface;

class SamedayPersistenceDataHandler implements SamedayPersistentDataInterface
{
    public const KEYS = [
        SamedayClient::KEY_TOKEN => 'SAMEDAY_TOKEN',
        SamedayClient::KEY_TOKEN_EXPIRES => 'SAMEDAY_TOKEN_EXPIRES_AT'
    ];

    protected $registry;
    protected $loader;
    protected $prefix;
    protected $session;

    public function __construct($registry, $prefix) {
        $this->registry = $registry;
        $this->prefix = $prefix;
        $this->loader = new Loader($this->registry);
    }

    /**
     * @param string $key
     *
     * @return string
     */
    public function get($key): ?string
    {
        $this->loader->model('setting/setting');
        $model = $this->registry->get('model_setting_setting');
        $key = $this->prefix.strtolower(self::KEYS[$key]);
        return $model->getSettingValue($key);
    }

    /**
     * @param string $key
     *
     * @param mixed $value
     */
    public function set($key, $value): void
    {
        $this->session = $this->registry->get('session');
        $key = strtolower(self::KEYS[$key]);
        $this->session->data[$this->prefix.$key] = $value;
    }
}