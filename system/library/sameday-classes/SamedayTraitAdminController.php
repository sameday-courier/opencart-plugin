<?php

use Sameday\Exceptions\SamedayAuthenticationException;
use Sameday\Exceptions\SamedayAuthorizationException;
use Sameday\Exceptions\SamedayBadRequestException;
use Sameday\Exceptions\SamedayNotFoundException;
use Sameday\Exceptions\SamedayOtherException;
use Sameday\Exceptions\SamedaySDKException;
use Sameday\Exceptions\SamedayServerException;
use Sameday\Objects\ParcelDimensionsObject;
use Sameday\Objects\PickupPoint\PickupPointContactPersonObject;
use Sameday\Objects\PostAwb\ParcelObject;
use Sameday\Objects\PostAwb\Request\AwbRecipientEntityObject;
use Sameday\Objects\PostAwb\Request\CompanyEntityObject;
use Sameday\Objects\PostAwb\Request\ThirdPartyPickupEntityObject;
use Sameday\Objects\Types\AwbPaymentType;
use Sameday\Objects\Types\AwbPdfType;
use Sameday\Objects\Types\CodCollectorType;
use Sameday\Objects\Types\PackageType;
use Sameday\Requests\SamedayDeleteAwbRequest;
use Sameday\Requests\SamedayGetAwbPdfRequest;
use Sameday\Requests\SamedayGetCountiesRequest;
use Sameday\Requests\SamedayGetLockersRequest;
use Sameday\Requests\SamedayGetParcelStatusHistoryRequest;
use Sameday\Requests\SamedayGetPickupPointsRequest;
use Sameday\Requests\SamedayGetServicesRequest;
use Sameday\Requests\SamedayPostAwbEstimationRequest;
use Sameday\Requests\SamedayPostAwbRequest;
use Sameday\Requests\SamedayPostParcelRequest;
use Sameday\Requests\SamedayPostPickupPointRequest;
use Sameday\Sameday as SamedayAlias;

trait SamedayTraitAdminController {
    private $error = array();

    /**
     * @var null
     */
    private $testing;

    /**
     * @var null
     */
    private $hostCountry;

    /**
     * @var SamedayHelper
     */
    private $samedayHelper;

    /**
     * @var SamedayVersionValidator
     *
     */
    private $samedayVersionValidator;

    /**
     * @param $registry
     *
     * @throws Exception
     */
    public function __construct($registry)
    {
        parent::__construct($registry);
        $this->samedayVersionValidator = Samedayclasses::getSamedayVersionValidator();
        $this->load->model($this->samedayVersionValidator->buildModelPath());

        $this->samedayHelper = Samedayclasses::getSamedayHelper(
            $this->buildRequest(),
            $registry,
            $this->{$this->samedayVersionValidator->buildMagicMethod()}->getPrefix()
        );
    }

    private function methodPath($path){
        return $this->samedayVersionValidator->buildMethodPath($path);
    }

    /**
     * @return void
     *
     * @throws Exception
     */
    public function install()
    {
        $this->{$this->samedayVersionValidator->buildMagicMethod()}->install();
        $this->ensureShippingExtensionRegistered();

        // Do NOT use editSetting() here — it deletes the entire `sameday` settings
        // group and would wipe credentials/status on every Install click.
        $settingsModel = $this->{$this->samedayVersionValidator->buildMagicMethod()};
        $code = $settingsModel->getPrefix() . 'sameday';

        if ($this->getConfig('sameday_sync_until_ts') === null || $this->getConfig('sameday_sync_until_ts') === '') {
            $settingsModel->addAdditionalSetting(
                $code,
                [$settingsModel->getKey('sameday_sync_until_ts') => time()]
            );
        }

        if ($this->getConfig('sameday_sync_lockers_ts') === null || $this->getConfig('sameday_sync_lockers_ts') === '') {
            $settingsModel->addAdditionalSetting(
                $code,
                [$settingsModel->getKey('sameday_sync_lockers_ts') => 0]
            );
        }

        // Default to enabled on first install if status was never set.
        if ($this->getConfig('sameday_status') === null || $this->getConfig('sameday_status') === '') {
            $settingsModel->addAdditionalSetting(
                $code,
                [$settingsModel->getKey('sameday_status') => 1]
            );
        }

        $this->registerSamedayEvents();
    }

    /**
     * Checkout only loads shipping codes present in oc_extension.
     * OCMOD "installed" is not enough — register the shipping extension row.
     */
    private function ensureShippingExtensionRegistered()
    {
        $query = $this->db->query(
            "SELECT * FROM `" . DB_PREFIX . "extension` WHERE `type` = 'shipping' AND `code` = 'sameday'"
        );

        if (!$query->num_rows) {
            $this->db->query(
                "INSERT INTO `" . DB_PREFIX . "extension` SET `type` = 'shipping', `code` = 'sameday'"
            );
        }
    }

    /**
     * @return void
     */
    private function registerSamedayEvents()
    {
        $this->{$this->samedayVersionValidator->buildMagicMethod()}->createBulkAwbTable();

        if ($this->samedayVersionValidator->isOc4()) {
            $this->load->model('setting/event');
            $events = [
                [
                    'code'        => 'sameday_order_info',
                    'description' => 'Sameday AWB on order info',
                    'trigger'     => 'admin/view/sale/order_info/before',
                    'action'      => 'extension/sameday/shipping/sameday.order_info_before',
                    'status'      => true,
                    'sort_order'  => 1
                ],
                [
                    'code'        => 'sameday_checkout_scripts',
                    'description' => 'Add Sameday locker and payment scripts on checkout',
                    'trigger'     => 'catalog/controller/common/footer/before',
                    'action'      => 'extension/sameday/shipping/sameday.checkout_scripts_before',
                    'status'      => true,
                    'sort_order'  => 1
                ],
                [
                    'code'        => 'sameday_payment_method_view',
                    'description' => 'Sameday paymentMethod class on payment modal (OC4)',
                    'trigger'     => 'view/checkout/payment_method/after',
                    'action'      => 'extension/sameday/shipping/sameday.payment_method_view_after',
                    'status'      => true,
                    'sort_order'  => 1
                ],
                [
                    'code'        => 'sameday_order_save',
                    'description' => 'Saving the order id when temporary order is generated',
                    'trigger'     => 'catalog/model/checkout/order.addOrder/after',
                    'action'      => 'extension/sameday/shipping/sameday.order_add_after',
                    'status'      => true,
                    'sort_order'  => 1
                ],
                [
                    'code'        => 'sameday_order_locker_save',
                    'description' => 'Save Sameday locker_id to oc_sameday_orders_locker when order is created',
                    'trigger'     => 'catalog/model/checkout/order.addHistory/after',
                    'action'      => 'extension/sameday/shipping/sameday.order_model_after',
                    'status'      => true,
                    'sort_order'  => 1
                ],
                [
                    'code'        => 'sameday_order_list',
                    'description' => 'Sameday bulk AWB feedback on order list',
                    'trigger'     => 'admin/view/sale/order_list/before',
                    'action'      => 'extension/sameday/shipping/sameday.order_list_before',
                    'status'      => true,
                    'sort_order'  => 1
                ],
                [
                    'code'        => 'sameday_order_list_after',
                    'description' => 'Sameday bulk AWB modals and scripts on order list',
                    'trigger'     => 'admin/view/sale/order_list/after',
                    'action'      => 'extension/sameday/shipping/sameday.order_list_after',
                    'status'      => true,
                    'sort_order'  => 1
                ],
                [
                    'code'        => 'sameday_order_page_after',
                    'description' => 'Sameday bulk AWB toolbar on order list page',
                    'trigger'     => 'admin/view/sale/order/after',
                    'action'      => 'extension/sameday/shipping/sameday.order_page_after',
                    'status'      => true,
                    'sort_order'  => 1
                ],
            ];

            foreach ($events as $event) {
                if (empty($this->model_setting_event->getEventByCode($event['code']))) {
                    $this->model_setting_event->addEvent($event);
                }
            }

            return;
        }

        $legacyEvents = [
            [
                'code'    => 'sameday_order_info',
                'trigger' => 'admin/view/sale/order_info/before',
                'action'  => 'extension/shipping/sameday/order_info_before',
            ],
            [
                'code'    => 'sameday_order_list',
                'trigger' => 'admin/view/sale/order_list/before',
                'action'  => 'extension/shipping/sameday/order_list_before',
            ],
            [
                'code'    => 'sameday_checkout_scripts',
                'trigger' => 'catalog/controller/common/footer/before',
                'action'  => 'extension/shipping/sameday/checkout_scripts_before',
            ],
        ];

        if ($this->samedayVersionValidator->isOc2()) {
            $this->load->model('extension/event');

            foreach ($legacyEvents as $event) {
                $existing = $this->model_extension_event->getEvent(
                    $event['code'],
                    $event['trigger'],
                    $event['action']
                );

                if (empty($existing)) {
                    $this->model_extension_event->addEvent(
                        $event['code'],
                        $event['trigger'],
                        $event['action'],
                        1
                    );
                }
            }

            return;
        }

        $this->load->model('setting/event');

        foreach ($legacyEvents as $event) {
            // OC3: skip checkout_scripts here if using twig OCMOD path only — still register scripts event.
            if (empty($this->model_setting_event->getEventByCode($event['code']))) {
                $this->model_setting_event->addEvent(
                    $event['code'],
                    $event['trigger'],
                    $event['action'],
                    1,
                    1
                );
            }
        }
    }

    /**
     * @return void
     */
    public function uninstall()
    {
        // Do not drop Sameday tables here. OC core already removes the extension row
        // and settings with code=sameday; dropping tables made reinstall look broken
        // (empty services / missing schema) and forced a full re-import.
        if ($this->samedayVersionValidator->isOc4()) {
            $this->load->model('setting/event');
            $this->model_setting_event->deleteEventByCode('sameday_order_info');
            $this->model_setting_event->deleteEventByCode('sameday_checkout_scripts');
            $this->model_setting_event->deleteEventByCode('sameday_payment_method_view');
            $this->model_setting_event->deleteEventByCode('sameday_order_save');
            $this->model_setting_event->deleteEventByCode('sameday_order_locker_save');
            $this->model_setting_event->deleteEventByCode('sameday_order_list');
            $this->model_setting_event->deleteEventByCode('sameday_order_list_after');
            $this->model_setting_event->deleteEventByCode('sameday_order_page_after');
        } elseif ($this->samedayVersionValidator->isOc2()) {
            $this->load->model('extension/event');
            $this->model_extension_event->deleteEvent('sameday_order_info');
            $this->model_extension_event->deleteEvent('sameday_order_list');
            $this->model_extension_event->deleteEvent('sameday_checkout_scripts');
        } else {
            $this->load->model('setting/event');
            $this->model_setting_event->deleteEventByCode('sameday_order_info');
            $this->model_setting_event->deleteEventByCode('sameday_order_list');
            $this->model_setting_event->deleteEventByCode('sameday_checkout_scripts');
        }
    }

    /**
     * @return void
     *
     * @throws SamedaySDKException
     */
    public function index()
    {
        // Ensure schema exists after OCMOD upgrades without a full reinstall (CREATE IF NOT EXISTS).
        $this->{$this->samedayVersionValidator->buildMagicMethod()}->install();
        $this->ensureShippingExtensionRegistered();
        $this->registerSamedayEvents();

        $this->load->language($this->samedayVersionValidator->buildModelPath());
        $this->document->setTitle($this->language->get('heading_title'));
        $this->load->model('setting/setting');
        $settingsModel = $this->{$this->samedayVersionValidator->buildMagicMethod()};
        $post = $this->{$this->samedayVersionValidator->buildMagicMethod()}->sanitizeInputs($_POST);
        $hostCountry = $this->getConfig('sameday_host_country') ?? $this->samedayHelper::API_HOST_LOCALE_RO;

        $this->{$this->samedayVersionValidator->buildMagicMethod()}->checkCodSetting();

        if ($this->request->server['REQUEST_METHOD'] === 'POST' && $this->validate()) {
            $post[$settingsModel->getKey('sameday_sync_until_ts')] = $this->getConfig('sameday_sync_until_ts');
            $post[$settingsModel->getKey('sameday_sync_lockers_ts')] = $this->getConfig('sameday_sync_lockers_ts');
            $post[$settingsModel->getKey('sameday_testing')] = $this->getConfig('sameday_testing');
            $post[$settingsModel->getKey('sameday_host_country')] = $hostCountry;

            if (null !== $this->testing && null !== $this->hostCountry) {
                $post[$this->{$this->samedayVersionValidator->buildMagicMethod()}->getKey('sameday_testing')] = $this->testing;
                $post[$this->{$this->samedayVersionValidator->buildMagicMethod()}->getKey('sameday_host_country')] = $this->hostCountry;
            }

            // Add custom sanitization for password
            $passKey = $this->{$this->samedayVersionValidator->buildMagicMethod()}->getKey('sameday_password');
            $password = $post[$passKey];
            if ('' === $password) {
                $password = $this->getConfig('sameday_password');
                if ('' === $password || null === $password) {
                    $this->session->data['error_warning'] = $this->language->get('error_username_password');

                    $this->response->redirect(
                        $this->url->link($this->samedayVersionValidator->buildModelPath(), $this->addToken(), true)
                    );
                }
            }
            $post[$passKey] = $password;

            // editSetting() wipes the whole code group — keep API token / sync keys.
            foreach ([
                'sameday_token',
                'sameday_token_expire_at',
                'sameday_sync_until_ts',
                'sameday_sync_lockers_ts',
                'sameday_cod',
                'sameday_awb_format',
                'sameday_nomenclator_use',
            ] as $preserveKey) {
                $fullKey = $settingsModel->getKey($preserveKey);
                if (!isset($post[$fullKey]) || $post[$fullKey] === '' || $post[$fullKey] === null) {
                    $existing = $this->getConfig($preserveKey);
                    if ($existing !== null && $existing !== '') {
                        $post[$fullKey] = $existing;
                    }
                }
            }

            $this->model_setting_setting->editSetting(
                $this->{$this->samedayVersionValidator->buildMagicMethod()}->getPrefix() . "sameday",
                $post
            );

            $this->{$this->samedayVersionValidator->buildMagicMethod()}->checkCodSetting();

            // Keep runtime config / API client in sync with what we just persisted.
            foreach ($post as $configKey => $configValue) {
                if (strpos($configKey, 'sameday') !== false) {
                    $this->config->set($configKey, $configValue);
                }
            }
            $this->samedayHelper = Samedayclasses::getSamedayHelper(
                $this->buildRequest(),
                $this->registry,
                $settingsModel->getPrefix()
            );

            // After credentials are stored, pull remote data if local tables are empty
            // or a fresh API login just succeeded.
            $shouldImport = (null !== $this->testing && null !== $this->hostCountry);
            if (!$shouldImport && $this->getConfig('sameday_username') && $this->getConfig('sameday_password')) {
                $existingServices = $this->{$this->samedayVersionValidator->buildMagicMethod()}->getServices();
                $shouldImport = empty($existingServices);
            }

            if ($shouldImport) {
                try {
                    $this->importServices(false);
                    $this->importPickupPoint(false);
                    $this->importLockers(false);
                } catch (Exception $exception) {
                    $this->session->data['error_warning'] = $exception->getMessage();
                }
            }

            $this->session->data['error_success'] = $this->language->get('text_success');

            $this->response->redirect(
                $this->url->link($this->samedayVersionValidator->buildModelPath(), $this->addToken(), true)
            );
        }

        $this->load->model('localisation/tax_class');
        $this->load->model('localisation/geo_zone');

        $data = $this->buildLanguage(array(
            'heading_title',
            'button_save',
            'button_cancel',

            'text_edit',
            'text_services',
            'text_services_refresh',
            'text_lockers',
            'text_lockers_refresh',
            'text_services_empty',
            'text_lockers_empty',
            'text_enabled',
            'text_disabled',
            'text_pickup_points',
            'text_pickup_points_refresh',
            'text_pickup_points_empty',
            'text_services_status_always',
            'text_none',
            'text_all_zones',
            'text_pickupPoint_add',
            'text_pickupPointContactName',
            'text_pickupPoint_address',
            'text_pickupPointPo',
            'text_pickupPointCounty',
            'text_pickupPointCountry',
            'text_pickupPointCity',
            'text_pickupPointPhoneNumber',
            'text_pickupPointEmail',
            'text_pickupPointWorkingHours',
            'text_pickupPointAlias',
            'text_pickupPoint_delete',
            'text_pickupPoint_delete_question',
            'text_pickupPoint_delete_confirm',
            'text_pickupPoint_delete_decline',
            'text_pickupPointAddFeedbackSuccess',
            'text_pickupPointAddFeedbackError',
            'text_pickupPointAddress',
            'text_pickupPointDefault',
            'text_pickupPointDeleteWarning',
            'text_pickupPointDeleteSuccess',

            'entry_username',
            'entry_password',
            'entry_testing',
            'entry_tax_class',
            'entry_geo_zone',
            'entry_status',
            'entry_estimated_cost',
            'entry_show_lockers_map',
            'entry_locker_max_items',
            'entry_sort_order',
            'entry_cod',
            'entry_import_local_data',
            'entry_import_nomenclator',
            'entry_import_nomenclator_button',
            'entry_drop_down_list',
            'entry_interactive_map',
            'entry_awb_format',

            'column_internal_id',
            'column_internal_name',
            'column_name',
            'column_price',
            'column_price_free',
            'column_status',

            'column_locker_name',
            'column_locker_county',
            'column_locker_city',
            'column_locker_address',
            'column_locker_lat',
            'column_locker_lng',
            'column_locker_postal_code',

            'column_pickupPoint_samedayId',
            'column_pickupPoint_alias',
            'column_pickupPoint_city',
            'column_pickupPoint_county',
            'column_pickupPoint_address',
            'column_pickupPoint_default_address',
            'column_pickupPoint_action',
            'yes',
            'no',
        ));

        $data['error_warning'] = $this->buildError('warning');
        $data['error_success'] = $this->buildError('success');

        $data['breadcrumbs'] = array(
            array(
                'text' => $this->language->get('text_home'),
                'href' => $this->url->link('common/dashboard', $this->addToken(), true)
            ),
            array(
                'text' => $this->language->get('text_shipping'),
                'href' => $this->url->link(
                    $this->getRouteExtension(),
                    $this->addToken(array('type' => 'shipping')),
                    true
                )
            ),
            array(
                'text' => $this->language->get('heading_title'),
                'href' => $this->url->link($this->samedayVersionValidator->buildModelPath(), $this->addToken(), true)
            )
        );

        $data['statuses'] = $this->getStatuses();
        $data['action'] = $this->url->link($this->samedayVersionValidator->buildModelPath(), $this->addToken(), true);
        $data['cancel'] = $this->url->link(
            $this->getRouteExtension(),
            $this->addToken(array('type' => 'shipping')),
            true
        );
        $data['services'] = $this->displayServices();
        $data['import_local_data_actions'] = json_encode($this->samedayHelper::IMPORT_LOCAL_DATA_ACTIONS, true);
        $data['import_local_data_href'] = $this->url->link(
            $this->samedayVersionValidator->buildSamedayMethodPath('importLocalData'),
            $this->addToken(),
            true
        );
        $data['import_geolocations'] = $this->url->link(
            $this->samedayVersionValidator->buildSamedayMethodPath('importGeolocations'),
            $this->addToken(),
            true
        );
        $data['get_all_counties'] = $this->url->link(
            $this->samedayVersionValidator->buildSamedayMethodPath('get_all_counties'),
            $this->addToken(),
            true
        );
        $data['service_refresh'] = $this->url->link(
            $this->samedayVersionValidator->buildSamedayMethodPath('serviceRefresh'),
            $this->addToken(),
            true
        );
        $data['pickupPoints'] = $this->{$this->samedayVersionValidator->buildMagicMethod()}->getPickupPoints();
        $data['lockers'] = $this->{$this->samedayVersionValidator->buildMagicMethod()}->getLockers();
        $data['pickupPoint_refresh'] = $this->url->link(
            $this->samedayVersionValidator->buildSamedayMethodPath('pickupPointRefresh'),
            $this->addToken(),
            true
        );
        $data['lockers_refresh'] = $this->url->link(
            $this->samedayVersionValidator->buildSamedayMethodPath('lockersRefresh'),
            $this->addToken(),
            true
        );
        $data['tax_classes'] = $this->model_localisation_tax_class->getTaxClasses();
        $data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();
        $data['service_links'] = array_map(
            function ($service) {
                return $this->url->link(
                    $this->samedayVersionValidator->buildSamedayMethodPath('service'),
                    $this->addToken(array('id' => $service['id'])),
                    true
                );
            },
            $data['services']
        );
        $data['url_cities_ajax'] = $this->url->link(
            $this->samedayVersionValidator->buildSamedayMethodPath('getCities'),
            $this->addToken(),
            true
        );
        $data['url_deletePickupPoint'] = $this->url->link(
            $this->samedayVersionValidator->buildSamedayMethodPath('deletePickupPoint'),
            $this->addToken(),
            true
        );

        $data['sameday_nomenclator_use'] = $this->getConfig('sameday_nomenclator_use');
        // For Add Pickup-Point Form (never leave unset — OC2 tpl foreach fatals otherwise)
        $data['pp_counties'] = [];
        try {
            if ($this->getConfig('sameday_username') && $this->getConfig('sameday_password')) {
                $data['pp_counties'] = $this->getCounties();
            }
        } catch (\Exception $exception) {
            $data['pp_counties'] = [];
        }
        $data['pp_countries'] = array_filter(
            $this->samedayHelper::SAMEDAY_COUNTRIES,
            static function ($country) use ($hostCountry) {
                return $country['code'] === $hostCountry;
            }
        );

        $data = array_merge($data, $this->buildRequest());

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $data['pickupPointsTest'] = $this->url->link(
            $this->samedayVersionValidator->buildSamedayMethodPath('sendNewPickupPoint'),
            $this->addToken(),
            true
        );

        $data['url_cod_ajax'] = $this->url->link(
            $this->samedayVersionValidator->buildSamedayMethodPath('updateCod'),
            $this->addToken(),
            true
        );
        $codConfig = $this->getConfig('sameday_cod');
        $data['cods'] = $codConfig ? json_decode($codConfig) : [];

        $data['awbFormatTypes'] = [
            AwbPdfType::A4,
            AwbPdfType::A6
        ];

        $data['awbFormatType'] = $this->getConfig('sameday_awb_format');

        $this->response->setOutput(
            $this->load->view($this->samedayVersionValidator->buildTemplatePath('extension/sameday/shipping/sameday'),$data)
        );
    }

    /**
     * @return false|string
     */
    public function sendNewPickupPoint()
    {
        $this->load->language($this->samedayVersionValidator->buildModelPath());

        $fields = $this->extractPickupPointFormFields(
            isset($this->request->post['data']) ? $this->request->post['data'] : []
        );

        $country = $fields['pickupPointCountry'];
        $county = $fields['pickupPointCounty'];
        $city = $fields['pickupPointCity'];
        $address = $fields['pickupPointAddress'];
        $default = (int)$fields['pickupPointDefault'];
        $postalCode = $fields['pickupPointPo'];
        $alias = $fields['pickupPointAlias'];
        $person = $fields['pickupPointContactName'];
        $phone = $fields['pickupPointPhoneNumber'];

        $contact = [new PickupPointContactPersonObject($person, $phone, true)];

        try {
            $sameday = new SamedayAlias($this->samedayHelper->initClient());
            $sameday->postPickupPoint(
                new SamedayPostPickupPointRequest(
                    $country,
                    $county,
                    $city,
                    $address,
                    $postalCode,
                    $alias,
                    $contact,
                    $default
                )
            );
            $this->session->data['error_success'] = $this->buildLanguage('text_pickupPointAddFeedbackSuccess');
        } catch (Exception $exception) {
            $this->session->data['error_warning'] = $exception->getMessage();
            $this->response->setOutput($exception->getMessage());

            return;
        }

        $this->response->setOutput(json_encode(['success' => true]));
    }

    /**
     * Map jQuery serializeArray() rows by field name.
     * Index-based access breaks when unchecked checkboxes / disabled city are omitted.
     *
     * @param array $actionKeys
     *
     * @return array
     */
    private function extractPickupPointFormFields(array $actionKeys)
    {
        $defaults = [
            'pickupPointCountry' => '',
            'pickupPointCounty' => '',
            'pickupPointCity' => '',
            'pickupPointAddress' => '',
            'pickupPointDefault' => 0,
            'pickupPointPo' => '',
            'pickupPointAlias' => '',
            'pickupPointContactName' => '',
            'pickupPointPhoneNumber' => '',
            'pickupPointEmail' => '',
        ];

        foreach ($actionKeys as $row) {
            if (!is_array($row) || !isset($row['name'])) {
                continue;
            }
            $name = $row['name'];
            if (array_key_exists($name, $defaults)) {
                $defaults[$name] = isset($row['value']) ? $row['value'] : '';
            }
        }

        return $defaults;
    }

    /**
     * @return array
     */
    public function getCounties(): array
    {
        try {
            $sameday = new SamedayAlias($this->samedayHelper->initClient());
        } catch (Exception $exception) {
            return [];
        }

        try {
            $counties = $sameday->getCounties(new SamedayGetCountiesRequest(''));
        } catch (Exception $exception) {
            return [];
        }

        $countiesArray = [];
        foreach ($counties->getCounties() as $county) {
            $countiesArray[] = [
                'name' => $county->getName(),
                'id' => $county->getId(),
            ];
        }

        return $countiesArray;
    }

    /**
     * @return void
     *
     * @throws SamedayBadRequestException
     * @throws SamedaySDKException
     * @throws SamedayAuthenticationException
     * @throws SamedayAuthorizationException
     * @throws SamedayServerException
     */
    public function getCities()
    {
        $id = $this->request->post['id'];
        try {
            $sameday = new SamedayAlias($this->samedayHelper->initClient());
        } catch (Exception $exception) {
            var_dump($exception);
        }

        $cities = $sameday->getCities(new \Sameday\Requests\SamedayGetCitiesRequest($id));
        $citiesArray = [];
        foreach ($cities->getCities() as $city) {
            $citiesArray[] = [
                'name' => $city->getName(),
                'id' => $city->getId()
            ];
        }

        $this->response->setOutput(json_encode($citiesArray));
    }

    /**
     * @return void
     */
    public function updateCod()
    {
        if (isset($this->request->post['cods'])) {
            $data = $this->request->post['cods'];
            if (isset($this->request->post['newCod'])) {
                array_push($data, $this->request->post['newCod']);
            }
        } else {
            $data = array();
            if (isset($this->request->post['newCod'])) {
                array_push($data, $this->request->post['newCod']);
            } else {
                return;
            }
        }

        $newCods = json_encode($data);
        $this->{$this->samedayVersionValidator->buildMagicMethod()}->updateCod($newCods);
        echo $newCods;
    }

    /**
     * @return void
     */
    public function deletePickupPoint()
    {
        $this->load->language($this->samedayVersionValidator->buildModelPath());
        $sameday_id = $this->request->post['id'];

        try {
            $sameday = new SamedayAlias($this->samedayHelper->initClient());
        } catch (Exception $exception) {
            var_dump($exception);
        }

        try {
            $sameday->deletePickupPoint(new \Sameday\Requests\SamedayDeletePickupPointRequest($sameday_id));
            $pickupPoint = $this->{$this->samedayVersionValidator->buildMagicMethod()}->getPickupPointSameday($sameday_id);
            try {
                $this->{$this->samedayVersionValidator->buildMagicMethod()}->deletePickupPoint($pickupPoint['id']);
                $this->session->data['error_success'] = $this->buildLanguage('text_pickupPointDeleteSuccess');
            } catch (Exception $exception) {
                $this->session->data['error_warning'] = $this->buildLanguage('text_pickupPointDeleteWarning');
            }
        } catch (Exception $exception) {
            $this->session->data['error_warning'] = $exception->getMessage();
        }
    }

    /**
     * @return array
     */
    private function displayServices(): array
    {
        $services = $this->{$this->samedayVersionValidator->buildMagicMethod()}->getServices();
        $samedayHelper = $this->samedayHelper;

        $services = array_filter(
            $services,
            static function (array $service) use ($samedayHelper) {
                return in_array($service['sameday_code'], $samedayHelper::SAMEDAY_IN_USE_SERVICES, true);
            }
        );

        $oohService = array_values(array_filter(
            $services,
            static function (array $service) use ($samedayHelper) {
                return $service['sameday_code'] === $samedayHelper::LOCKER_NEXT_DAY_SERVICE;
            },
            true
        ))[0] ?? null;

        $oohCrossService = array_values(array_filter(
            $services,
            static function (array $service) use ($samedayHelper) {
                return $service['sameday_code'] === $samedayHelper::LOCKER_NEXT_DAY_CROSSBORDER_SERVICE;
            },
            true
        ))[0] ?? null;

        if (null !== $oohService) {
            $oohService['sameday_name'] = $samedayHelper::OOH_SERVICES_LABELS[$samedayHelper->getHostCountry()];
            $oohService['name'] = $samedayHelper::OOH_SERVICES_LABELS[$samedayHelper->getHostCountry()];
            $oohService['sameday_code'] = $samedayHelper::OOH_SERVICE_CODE;
            $oohService['column_ooh_label'] = $this->buildLanguage('column_ooh_label');

            $services = array_merge([$oohService], $services);
        }

        if (null !== $oohCrossService) {
            $oohCrossService['sameday_name'] = $samedayHelper::OOH_CROSSBORDER_SERVICES_LABELS[$samedayHelper->getHostCountry()];
            $oohCrossService['name'] = $samedayHelper::OOH_CROSSBORDER_SERVICES_LABELS[$samedayHelper->getHostCountry()];
            $oohCrossService['sameday_code'] = $samedayHelper::OOH_CROSSBORDER_SERVICE_CODE;
            $oohCrossService['column_ooh_label'] = $this->buildLanguage('column_ooh_cross_label');

            $services = array_merge([$oohCrossService], $services);
        }

        return array_filter($services, static function ($service) use ($samedayHelper) {
            return !$samedayHelper->isOohDeliveryOption($service['sameday_code']);
        });
    }

    /**
     * @return mixed
     */
    private function isTesting()
    {
        if (null !== $this->testing) {
            return (int)$this->testing;
        }

        return (int)$this->getConfig('sameday_testing');
    }

    /**
     * @return void
     */
    public function importLocalData()
    {
        $action = $this->request->post['action'] ?? null;
        if (!in_array($action, $this->samedayHelper::IMPORT_LOCAL_DATA_ACTIONS, true)) {
            $this->response->setOutput(json_encode(['error' => 'Invalid action!']));
        }

        try {
            $this->{$action}();
        } catch (Exception $exception) {
            $this->response->setOutput(json_encode(['error' => $exception->getMessage()]));
        }

        $this->response->setOutput(json_encode($action));
    }

    /**
     * @param bool $redirectToPage
     *
     * @return void
     *
     * @throws SamedaySDKException
     */
    private function importLockers(bool $redirectToPage = false)
    {
        $this->{$this->samedayVersionValidator->buildMagicMethod()}->install();

        $sameday = new SamedayAlias($this->samedayHelper->initClient());

        $request = new SamedayGetLockersRequest();

        $remoteLockers = [];
        $page = 1;

        do {
            $request->setPage($page++);
            try {
                $lockers = $sameday->getLockers($request);
            } catch (\Exception $e) {
                $errorMessage = sprintf('Import Lockers error: %s', $e->getMessage());

                $this->session->data['error_warning'] = sprintf(
                    '%s &#8226; %s',
                    $this->session->data['error_warning'] ?? '',
                    $errorMessage
                );

                if ($redirectToPage) {
                    $this->response->redirect(
                        $this->url->link($this->samedayVersionValidator->buildModelPath(), $this->addToken(), true)
                    );
                } else {
                    throw new \RuntimeException($errorMessage);
                }
            }

            foreach ($lockers->getLockers() as $lockerObject) {
                $locker = $this->{$this->samedayVersionValidator->buildMagicMethod()}->getLockerSameday($lockerObject->getId());
                if (!$locker) {
                    $this->{$this->samedayVersionValidator->buildMagicMethod()}->addLocker($lockerObject, $this->isTesting());
                } else {
                    $this->{$this->samedayVersionValidator->buildMagicMethod()}->updateLocker($lockerObject, $locker['id']);
                }

                $remoteLockers[] = $lockerObject->getId();
            }
        } while ($page < $lockers->getPages());

        // Build array of local lockers.
        $localLockers = array_map(
            static function ($locker) {
                return array(
                    'id' => (int)$locker['id'],
                    'sameday_id' => (int)$locker['locker_id']
                );
            },
            $this->{$this->samedayVersionValidator->buildMagicMethod()}->getLockers($this->isTesting())
        );

        // Delete local lockers that aren't present in remote lockers anymore.
        foreach ($localLockers as $localLocker) {
            if (!in_array($localLocker['sameday_id'], $remoteLockers, true)) {
                $this->{$this->samedayVersionValidator->buildMagicMethod()}->deleteLocker($localLocker['id']);
            }
        }

        $this->updateLastSyncTimestamp();

        if ($redirectToPage) {
            $this->response->redirect($this->url->link(
                $this->samedayVersionValidator->buildModelPath(),
                $this->addToken(),
                true
            ));
        }
    }

    /**
     * @param bool $redirectToPage
     *
     * @return void
     *
     * @throws SamedaySDKException
     */
    private function importServices(bool $redirectToPage = false)
    {
        $this->{$this->samedayVersionValidator->buildMagicMethod()}->install();

        $sameday = new SamedayAlias($this->samedayHelper->initClient());

        $samedayDBModel = $this->{$this->samedayVersionValidator->buildMagicMethod()};

        $samedayDBModel->ensureSamedayServiceCodeColumn();

        $samedayDBModel->ensureSamedayServiceOptionalTaxColumn();

        $remoteServices = [];
        $page = 1;
        $lockerNextDayService = null;
        do {
            $request = new SamedayGetServicesRequest();
            $request->setPage($page++);
            try {
                $services = $sameday->getServices($request);
            } catch (\Exception $e) {
                $errorMessage = sprintf('Import Services error: %s', $e->getMessage());

                $this->session->data['error_warning'] = sprintf(
                    '%s &#8226; %s',
                    $this->session->data['error_warning'] ?? '',
                    $errorMessage
                );

                if ($redirectToPage) {
                    $this->response->redirect(
                        $this->url->link($this->samedayVersionValidator->buildModelPath(), $this->addToken(), true)
                    );
                } else {
                    throw new \RuntimeException($errorMessage);
                }
            }

            foreach ($services->getServices() as $serviceObject) {
                $service = $this->{$this->samedayVersionValidator->buildMagicMethod()}->getServiceSameday(
                    $serviceObject->getId(),
                    $this->isTesting()
                );
                if (!$service) {
                    // Service not found, add it.
                    $this->{$this->samedayVersionValidator->buildMagicMethod()}->addService($serviceObject, $this->isTesting());
                } else {
                    // Service already exist, update it.
                    $this->{$this->samedayVersionValidator->buildMagicMethod()}->editService($service['id'], $serviceObject);

                    // Keep in mind lockerService:
                    if ($service['sameday_code'] === $this->samedayHelper::LOCKER_NEXT_DAY_SERVICE) {
                        $lockerNextDayService = $service;
                    }
                }

                // Save as current sameday service.
                $remoteServices[] = $serviceObject->getId();
            }
        } while ($page <= $services->getPages());

        // Build array of local services.
        $localServices = array_map(
            static function ($service) {
                return array(
                    'id' => (int)$service['id'],
                    'sameday_id' => (int)$service['sameday_id']
                );
            },
            $this->{$this->samedayVersionValidator->buildMagicMethod()}->getServices()
        );

        // Delete local services that aren't present in remote services anymore.
        foreach ($localServices as $localService) {
            if (!in_array($localService['sameday_id'], $remoteServices, true)) {
                $this->{$this->samedayVersionValidator->buildMagicMethod()}->deleteService($localService['id']);
            }
        }

        // Update Pudo Service status to be same as LN
        if (null !== $lockerNextDayService) {
            $pudoService = $this->{$this->samedayVersionValidator->buildMagicMethod()}->getSamedayServiceByCode(
                $this->samedayHelper::SAMEDAY_PUDO_SERVICE,
                $this->isTesting()
            );

            $pudoService['status'] = $lockerNextDayService['status'];
            $this->{$this->samedayVersionValidator->buildMagicMethod()}->updateServiceStatus(
                $pudoService['id'],
                $lockerNextDayService['status']
            );
        }

        if ($redirectToPage) {
            $this->response->redirect(
                $this->url->link($this->samedayVersionValidator->buildModelPath(), $this->addToken(), true)
            );
        }
    }

    /**
     * @param bool $redirectToPage
     *
     * @return void
     *
     * @throws SamedaySDKException
     */
    private function importPickupPoint(bool $redirectToPage = false)
    {
        $this->{$this->samedayVersionValidator->buildMagicMethod()}->install();

        $sameday = new SamedayAlias($this->samedayHelper->initClient());

        $remotePickupPoints = [];
        $page = 1;
        do {
            $request = new SamedayGetPickupPointsRequest();
            $request->setPage($page++);
            try {
                $pickUpPoints = $sameday->getPickupPoints($request);
            } catch (\Exception $e) {
                $errorMessage = sprintf('Import Pickuppoint error: %s', $e->getMessage());

                $this->session->data['error_warning'] = sprintf(
                    '%s &#8226; %s',
                    $this->session->data['error_warning'] ?? '',
                    $errorMessage
                );

                if ($redirectToPage) {
                    $this->response->redirect(
                        $this->url->link($this->samedayVersionValidator->buildModelPath(), $this->addToken(), true)
                    );
                } else {
                    throw new \RuntimeException($errorMessage);
                }
            }

            foreach ($pickUpPoints->getPickupPoints() as $pickupPointObject) {
                $pickupPoint = $this->{$this->samedayVersionValidator->buildMagicMethod()}->getPickupPointSameday(
                    $pickupPointObject->getId()
                );
                if (!$pickupPoint) {
                    // Pickup point not found, add it.
                    $this->{$this->samedayVersionValidator->buildMagicMethod()}->addPickupPoint($pickupPointObject);
                } else {
                    $this->{$this->samedayVersionValidator->buildMagicMethod()}->updatePickupPoint($pickupPointObject, $pickupPoint['id']);
                }

                // Save as current pickup points.
                $remotePickupPoints[] = $pickupPointObject->getId();
            }
        } while ($page <= $pickUpPoints->getPages());

        // Build array of local pickup points.
        $localPickupPoints = array_map(
            static function ($pickupPoint) {
                return array(
                    'id' => (int)$pickupPoint['id'],
                    'sameday_id' => (int)$pickupPoint['sameday_id']
                );
            },
            $this->{$this->samedayVersionValidator->buildMagicMethod()}->getPickupPoints($this->isTesting())
        );

        // Delete local pickup points that aren't present in remote pickup points anymore.
        foreach ($localPickupPoints as $localPickupPoint) {
            if (!in_array($localPickupPoint['sameday_id'], $remotePickupPoints, true)) {
                $this->{$this->samedayVersionValidator->buildMagicMethod()}->deletePickupPoint($localPickupPoint['id']);
            }
        }

        if ($redirectToPage) {
            $this->response->redirect(
                $this->url->link($this->samedayVersionValidator->buildModelPath(), $this->addToken(), true)
            );
        }
    }

    /**
     * @return void
     *
     * @throws SamedaySDKException
     */
    public function serviceRefresh()
    {
        $this->importServices(true);
    }

    /**
     * @return void
     *
     * @throws SamedaySDKException
     */
    public function pickupPointRefresh()
    {
        $this->importPickupPoint(true);
    }

    /**
     * @return void
     *
     * @throws SamedaySDKException
     */
    public function lockersRefresh()
    {
        $this->importLockers(true);
    }

    /**
     * @return void
     */
    private function updateLastSyncTimestamp()
    {
        $store_id = 0;
        $code = $this->{$this->samedayVersionValidator->buildMagicMethod()}->getKey('sameday');
        $key = $this->{$this->samedayVersionValidator->buildMagicMethod()}->getKey('sameday_sync_lockers_ts');
        $time = (string)time();

        $existing = $this->db->query(
            sprintf(
                "SELECT `setting_id` FROM %s WHERE `store_id` = '%s' AND `code` = '%s' AND `key` = '%s' LIMIT 1",
                DB_PREFIX . "setting",
                (int)$store_id,
                $this->db->escape($code),
                $this->db->escape($key)
            )
        )->row;

        if (empty($existing['setting_id'])) {
            $this->db->query(
                sprintf(
                    "INSERT INTO %s SET `store_id` = '%s', `code` = '%s', `key` = '%s', `value` = '%s'",
                    DB_PREFIX . "setting",
                    (int)$store_id,
                    $this->db->escape($code),
                    $this->db->escape($key),
                    $this->db->escape($time)
                )
            );

            return;
        }

        $this->db->query(
            sprintf(
                "UPDATE %s SET `value` = '%s' WHERE `setting_id` = '%s'",
                DB_PREFIX . "setting",
                $this->db->escape($time),
                (int)$existing['setting_id']
            )
        );
    }

    /**
     * @return void
     *
     * @throws Exception
     */
    public function service()
    {
        $service = $this->{$this->samedayVersionValidator->buildMagicMethod()}->getService($this->request->get['id']);

        $this->load->language($this->samedayVersionValidator->buildModelPath());
        $this->document->setTitle($this->language->get('heading_title_service'));
        $this->load->model('setting/setting');

        if ($this->request->server['REQUEST_METHOD'] === 'POST' && $this->validatePermissions()) {
            if (null === $this->request->post['name'] ?? null) {
                $this->request->post['name'] = $this->samedayHelper::OOH_SERVICES_LABELS[$this->samedayHelper->getHostCountry()];
            }

            $this->{$this->samedayVersionValidator->buildMagicMethod()}->updateService($service['id'], $this->request->post);

            // Update Pudo Service status to be same as LN
            if ($service['sameday_code'] === $this->samedayHelper::LOCKER_NEXT_DAY_SERVICE) {
                $pudoService = $this->{$this->samedayVersionValidator->buildMagicMethod()}->getSamedayServiceByCode(
                    $this->samedayHelper::SAMEDAY_PUDO_SERVICE,
                    $this->isTesting()
                );

                if (!empty($pudoService)) {
                    $this->{$this->samedayVersionValidator->buildMagicMethod()}->updateServiceStatus(
                        $pudoService['id'],
                        $this->request->post['status']
                    );
                }
            }

            $this->session->data['error_success'] = $this->language->get('text_success');

            $this->response->redirect($this->url->link(
                $this->samedayVersionValidator->buildSamedayMethodPath('service'),
                $this->addToken(array('id' => $service['id'])),
                true
            ));
        }

        $data = $this->buildLanguage(array(
            'heading_title_service',
            'button_save',
            'button_cancel',

            'text_edit_service',
            'text_enabled',
            'text_disabled',
            'text_services_status_always',

            'entry_name',
            'entry_price',
            'entry_price_free',
            'entry_status',

            'from',
            'to',

            'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'
        ));
        $serviceName = $service['sameday_name'];
        if ($this->samedayHelper->isOohDeliveryOption($service['sameday_code'])) {
            $serviceName = $this->samedayHelper::OOH_SERVICES_LABELS[$this->samedayHelper->getHostCountry()];
        }

        $data['text_edit_service'] = sprintf($this->language->get('text_edit_service'), $serviceName);

        $data['error_warning'] = $this->buildError('warning');
        $data['error_success'] = $this->buildError('success');

        $data['breadcrumbs'] = array(
            array(
                'text' => $this->language->get('text_home'),
                'href' => $this->url->link('common/dashboard', $this->addToken(), true)
            ),
            array(
                'text' => $this->language->get('text_shipping'),
                'href' => $this->url->link(
                    $this->getRouteExtension(),
                    $this->addToken(array('type' => 'shipping')),
                    true
                )
            ),
            array(
                'text' => $this->language->get('heading_title'),
                'href' => $this->url->link($this->samedayVersionValidator->buildModelPath(), $this->addToken(), true)
            ),
            array(
                'text' => $this->language->get('heading_title_service'),
                'href' => $this->url->link(
                    $this->samedayVersionValidator->buildSamedayMethodPath('service'),
                    $this->addToken(array('id' => $service['id'])),
                    true
                )
            )
        );

        $data['action'] = $this->url->link(
            $this->samedayVersionValidator->buildSamedayMethodPath('service'),
            $this->addToken(array('id' => $service['id'])),
            true
        );
        $data['cancel'] = $this->url->link($this->samedayVersionValidator->buildModelPath(), $this->addToken(), true);

        $data = array_merge(
            $data,
            $this->buildRequestService(
                array(
                    'name',
                    'price',
                    'price_free',
                    'status',
                ),
                $service
            )
        );

        $data['statuses'] = $this->getStatuses();
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view($this->samedayVersionValidator->buildTemplatePath('extension/sameday/shipping/sameday_service'), $data));
    }

    /**
     * Get data for order info template.
     *
     * @param array $orderInfo
     *
     * @return array|null
     */
    public function info(array $orderInfo)
    {
        if (!$orderInfo) {
            return null;
        }

        $this->load->language($this->samedayVersionValidator->buildModelPath());
        $data = array(
            'EAWB_country_instance' => $this->samedayHelper::getEAWBInstanceUrlByCountry(
                $this->getConfig('sameday_host_country')
            ),
            'samedayAwb' => $this->language->get('text_sameday_awb'),
            'buttonAddAwb' => $this->language->get('text_button_add_awb'),
            'buttonDeleteAwb' => $this->language->get('text_button_delete_awb'),
            'buttonShowAwb' => $this->language->get('text_button_show_awb'),
            'buttonAwbHistory' => $this->language->get('text_button_show_awb_history'),
            'buttonAddAwbLink' => $this->url->link(
                $this->samedayVersionValidator->buildSamedayMethodPath('addAwb'),
                $this->addToken(array('order_id' => $orderInfo['order_id'])),
                true
            ),
            'buttonShowAwbPdf' => $this->url->link(
                $this->samedayVersionValidator->buildSamedayMethodPath('showAsPdf'),
                $this->addToken(array('order_id' => $orderInfo['order_id'])),
                true
            ),
            'buttonShowAwbHistory' => $this->url->link(
                $this->samedayVersionValidator->buildSamedayMethodPath('showAwbHistory'),
                $this->addToken(array('order_id' => $orderInfo['order_id'])),
                true
            ),
            'buttonDeleteAwbLink' => $this->url->link(
                $this->samedayVersionValidator->buildSamedayMethodPath('deleteAwb'),
                $this->addToken(array('order_id' => $orderInfo['order_id'])),
                true
            ),
            'buttonAddParcel' => $this->language->get('text_button_add_parcel'),
            'buttonAddParcelLink' => $this->url->link(
                $this->samedayVersionValidator->buildSamedayMethodPath('addParcel'),
                $this->addToken(array('order_id' => $orderInfo['order_id'])),
                true
            ),
        );

        $awb = $this->{$this->samedayVersionValidator->buildMagicMethod()}->getAwbForOrderId($orderInfo['order_id']);

        if ($awb) {
            $data['awb_number'] = $awb['awb_number'];
            $storedParcels = @unserialize($awb['parcels'], ['allowed_classes' => true]);
            $data['parcel_count'] = is_array($storedParcels) ? count($storedParcels) : 0;
        }

        return $data;
    }

    /**
     * @return Action|void
     *
     * @throws Exception
     */
    public function addAwb()
    {
        /**
         * Set Title
         */
        $this->load->language($this->samedayVersionValidator->buildModelPath());
        $this->document->setTitle($this->language->get('heading_title_add_awb'));
        $this->load->model('sale/order');

        if (!isset($this->request->get['order_id'])
            || !($orderInfo = $this->model_sale_order->getOrder($this->request->get['order_id']))
        ) {
            return new Action('error/not_found');
        }

        $shippingSamedayModel = $this->{$this->samedayVersionValidator->buildMagicMethod()};

        $awb = $shippingSamedayModel->getAwbForOrderId($orderInfo['order_id']);

        if ($awb) {
            // Already generated.
            $this->response->redirect(
                $this->url->link(
                    $this->samedayVersionValidator->buildMethodPath('sale/order/info'),
                    $this->addToken(array('order_id' => $orderInfo['order_id'])),
                    true
                )
            );
        }

        $data = $this->buildRequestAwb(array(
            'sameday_insured_value',
            'sameday_package_number',
            'sameday_package_weight',
            'sameday_observation',
            'sameday_client_reference',
            'sameday_package_type',
            'sameday_pickup_point',
            'sameday_service',
            'sameday_locker_first_mile',
            'sameday_locker_id',
            'sameday_awb_payment',
            'sameday_third_party_pickup',
            'sameday_third_party_pickup_county',
            'sameday_third_party_pickup_city',
            'sameday_third_party_pickup_address',
            'sameday_third_party_pickup_name',
            'sameday_third_party_pickup_phone',
            'sameday_third_party_person_type',
            'sameday_third_party_person_company',
            'sameday_third_party_person_cif',
            'sameday_third_party_person_onrc',
            'sameday_third_party_person_bank',
            'sameday_third_party_person_iban'
        ));

        $data = array_merge($data, $this->buildLanguage(array(
            'text_create_awb',
            'text_type_person_individual',
            'text_type_person_business',
            'heading_title_add_awb',
            'heading_title_create_awb',
            'estimate_cost_msg',
            'estimate_cost_title',
            'awb_options',
            'estimate_cost',
            'button_cancel',

            'entry_insured_value',
            'entry_insured_value_title',
            'entry_packages_number',
            'entry_packages_number_title',
            'entry_calculated_weight',
            'entry_calculated_weight_title',
            'entry_package_dimension',
            'entry_client_reference',
            'entry_weight',
            'entry_width',
            'entry_length',
            'entry_height',
            'entry_observation',
            'entry_repayment',
            'entry_pickup_point',
            'entry_pickup_point_title',
            'entry_locker_details',
            'entry_locker_details_title',
            'entry_locker_change',
            'entry_observation_title',
            'entry_client_reference_title',
            'entry_repayment_title',
            'entry_package_type',
            'entry_package_type_title',
            'entry_awb_payment',
            'entry_service',
            'entry_service_title',
            'entry_locker_first_mile',
            'entry_locker_first_mile_title',
            'entry_awb_payment_title',
            'entry_third_party_pickup',
            'entry_third_party_pickup_title',
            'entry_third_party_pickup_county',
            'entry_third_party_pickup_county_title',
            'entry_third_party_pickup_city',
            'entry_third_party_pickup_city_title',
            'entry_third_party_pickup_name',
            'entry_third_party_pickup_name_title',
            'entry_third_party_pickup_address',
            'entry_third_party_pickup_address_title',
            'entry_third_party_pickup_phone',
            'entry_third_party_pickup_phone_title',
            'entry_third_party_person_type',
            'entry_third_party_person_type_title',
            'entry_third_party_person_company',
            'entry_third_party_person_company_title',
            'entry_third_party_person_cif',
            'entry_third_party_person_cif_title',
            'entry_third_party_person_onrc',
            'entry_third_party_person_onrc_title',
            'entry_third_party_person_bank',
            'entry_third_party_person_bank_title',
            'entry_third_party_person_iban',
            'entry_third_party_person_iban_title'
        )));

        if($this->samedayVersionValidator->isOc4()){
            $locker = json_decode($this->{$this->samedayVersionValidator->buildMagicMethod()}->getLockerByOrderId($orderInfo['order_id']));
            $parts = explode('.', $orderInfo['shipping_method']['code'], 5);
            $data['default_service_id'] = $parts[2] ?? null;

            $showLockerDetails = $this->toggleHtmlElement(false);
            $lockerDetails = '';
            $lockerPluginData = null;
            if (isset($locker)) {
                $lockerDetails = $locker->lockerAddress;
                $lockerPluginData = [
                    'lockerId' => $locker->lockerId,
                    'lockerAddress' => $locker->lockerAddress,
                    'country' => $orderInfo['shipping_iso_code_2'],
                    'city' => $orderInfo['shipping_city'] ?? null,
                    'apiUsername' => $this->getConfig('sameday_username'),
                ];

                $showLockerDetails = $this->toggleHtmlElement(true);
            }
        }else{
            $parts = explode('.', $orderInfo['shipping_code'], 5);
            $data['default_service_id'] = $parts[2] ?? null;

            $showLockerDetails = $this->toggleHtmlElement(false);
            $lockerDetails = '';
            $lockerPluginData = null;
            if (isset($parts[3], $parts[4])) {
                $lockerDetails = $parts[4];
                $lockerPluginData = [
                    'lockerId' => $parts[3],
                    'lockerAddress' => $parts[4],
                    'country' => $orderInfo['shipping_iso_code_2'],
                    'city' => $orderInfo['shipping_city'] ?? null,
                    'apiUsername' => $this->getConfig('sameday_username'),
                ];

                $showLockerDetails = $this->toggleHtmlElement(true);
            }
        }

        $showPDO = $this->toggleHtmlElement(false);

        if (null !== $locationId = $locker->lockerId ?? null) {
            if (($this->samedayHelper::OOH_SERVICE_CODE === $parts[1] ?? null)
                && $this->samedayHelper::SAMEDAY_PUDO_SERVICE === $this->samedayHelper->checkOohLocationType($locationId)
            ) {
                $currentService = $shippingSamedayModel->getSamedayServiceByCode(
                    $this->samedayHelper::SAMEDAY_PUDO_SERVICE,
                    $this->isTesting()
                );
                $data['default_service_id'] = $currentService['sameday_id'];
            } else {
                $currentService = $shippingSamedayModel->getServiceSameday(
                    (int)($data['default_service_id'] ?? null),
                    $this->isTesting()
                );
            }
        }

        if (isset($currentService['service_optional_taxes'])
            && $this->isServiceEligibleToPDO($currentService['service_optional_taxes'])
        ) {
            $showPDO = $this->toggleHtmlElement(true);
        }

        if ($this->request->server['REQUEST_METHOD'] === 'POST' && $this->validateFormBeforeAwbGeneration()) {
            $postRequestData = $this->request->post;

            $service = $shippingSamedayModel->getServiceSameday(
                (int)$postRequestData['sameday_service'],
                $this->isTesting()
            );

            $serviceCode = $service['sameday_code'] ?? null;

            if ('' === $postRequestData['sameday_locker_id']
                || '' === $postRequestData['sameday_locker_address']
                || false === $this->samedayHelper->isEligibleToLocker($serviceCode)
            ) {
                $postRequestData['sameday_locker_id'] = null;
                $postRequestData['sameday_locker_address'] = null;
            }

            $params = array_merge($postRequestData, $orderInfo);

            $postAwb = $this->postAwb($params);

            if (null !== $awb = $postAwb['awb'] ?? null) {
                $orderId = (int)$orderInfo['order_id'];

                $shippingSamedayModel->saveAwb(array(
                    'order_id' => $orderId,
                    'awb_number' => $awb->getAwbNumber(),
                    'parcels' => serialize($awb->getParcels()),
                    'awb_cost' => $awb->getCost()
                ));

                $shippingSamedayModel->updateBulkFeedback([
                    'awb_number' => (string)$awb->getAwbNumber(),
                ], $orderId);

                $lockerId = isset($postRequestData['sameday_locker_id']) && is_numeric($postRequestData['sameday_locker_id'])
                    ? (int)$postRequestData['sameday_locker_id']
                    : null;
                $lockerAddress = !empty($postRequestData['sameday_locker_address'])
                    ? (string)$postRequestData['sameday_locker_address']
                    : null;

                try {
                    $shippingSamedayModel->updateShippingMethodAfterPostAwb(
                        $orderId,
                        is_array($service) ? $service : [],
                        $lockerId,
                        $lockerAddress
                    );
                } catch (\Throwable $shippingUpdateException) {
                    // AWB already saved; keep success feedback.
                }

                // Redirect to order page.
                $this->response->redirect(
                    $this->url->link(
                        $this->samedayVersionValidator->buildMethodPath('sale/order/info'),
                        $this->addToken(array('order_id' => $orderInfo['order_id'])),
                        true
                    )
                );
            } elseif (null !== $errors = $postAwb['errors'] ?? null) {
                $shippingSamedayModel->updateBulkFeedback(['errors' => $errors], (int)$orderInfo['order_id']);

                $data['awb_errors'] = [];

                foreach ($errors as $error) {
                    foreach ($error['errors'] as $message) {
                        $data['awb_errors'][] = implode('.', $error['key']) . ': ' . $message;
                    }
                }
            }
        }

        if (!empty($this->error)) {
            foreach ($this->error as $key => $value) {
                $data[$key] = $this->buildError($key);
            }

            $data['all_errors'] = $this->error;
        }

        $data['packageTypes'] = array(
            array(
                'name' => $this->language->get('text_package_type_package'),
                'value' => PackageType::PARCEL
            ),
            array(
                'name' => $this->language->get('text_package_type_envelope'),
                'value' => PackageType::ENVELOPE
            ),
            array(
                'name' => $this->language->get('text_package_type_large_package'),
                'value' => PackageType::LARGE
            )
        );

        $data['awbPaymentsType'] = array(
            array(
                'name' => $this->language->get('text_client'),
                'value' => AwbPaymentType::CLIENT
            )
        );
        $repayment = 0;
        $paymentMethod = ($this->samedayVersionValidator->isOc4()) ?
            $this->samedayHelper::isCodCode($orderInfo['payment_method']['code'], $this->getConfig('sameday_cod')) :
            $this->samedayHelper::isCodCode($orderInfo['payment_code'], $this->getConfig('sameday_cod'));
        if ($paymentMethod) {
            $repayment = $this->currency->format(
                $orderInfo['total'],
                $orderInfo['currency_code'],
                $orderInfo['currency_value'],
                false
            );
        }

        $availableServices = [];
        $services = $shippingSamedayModel->getServices($this->getConfig('sameday_testing'));
        foreach ($services as $service) {
            if ($service['status'] > 0) {
                $service['service_eligible_to_locker'] = $this->toggleHtmlElement(false);
                if (isset($service['sameday_code'])) {
                    $service['service_eligible_to_locker'] = $this->toggleHtmlElement(
                        $this->samedayHelper->isEligibleToLocker($service['sameday_code'])
                    );
                }

                $service['service_eligible_to_pdo'] = $this->toggleHtmlElement(false);
                if (isset($service['service_optional_taxes'])) {
                    $service['service_eligible_to_pdo'] = $this->toggleHtmlElement(
                        $this->isServiceEligibleToPDO($service['service_optional_taxes'])
                    );
                }

                $availableServices[] = $service;
            }
        }

        $orderCurrency = $orderInfo['currency_code'];
        $destCurrency = $this->samedayHelper::SAMEDAY_ELIGIBLE_CURRENCIES[$orderInfo['shipping_iso_code_2']];

        $repaymentCurrencyAlert = null;
        if ($orderCurrency !== $destCurrency) {
            $repaymentCurrencyAlert = sprintf(
                "Be aware that the intended currency is %s but the Repayment value is expressed in %s. 
                Please consider a conversion !!",
                $destCurrency,
                $orderCurrency
            );
        }

        $data['sameday_repayment'] = $repayment;
        $data['sameday_currency'] = $orderInfo['currency_code'];
        $data['repaymentCurrencyAlert'] = $repaymentCurrencyAlert;
        $data['sameday_client_reference'] = $orderInfo['order_id'];
        $data['pickupPoints'] = $shippingSamedayModel->getPickupPoints($this->getConfig('sameday_testing'));
        $data['services'] = $availableServices;
        $data['lockerDetails'] = $lockerDetails;
        $data['lockerPluginData'] = $lockerPluginData;
        $data['showLockerDetails'] = $showLockerDetails;
        $data['showPDO'] = $showPDO;
        $data['pdo_code'] = $this->samedayHelper::SERVICE_OPTIONAL_TAX_PDO_CODE;
        $data['calculated_weight'] = $this->calculatePackageWeight($orderInfo['order_id']);
        $data['counties'] = $shippingSamedayModel->getCounties(
            $this->getConfig('sameday_host_country') ?? $this->samedayHelper::API_HOST_LOCALE_RO
        );

        /*
         * Breadcrumbs
         */
        $data['breadcrumbs'] = array(
            array(
                'text' => $this->language->get('text_home'),
                'href' => $this->url->link('common/dashboard', $this->addToken(), true),
                'separator' => false
            ),
            array(
                'text' => $this->language->get('text_orders'),
                'href' => $this->url->link('sale/order', $this->addToken(), true)
            ),
            array(
                'text' => $this->language->get('text_order_info'),
                'href' => $this->url->link(
                    $this->samedayVersionValidator->buildMethodPath('sale/order/info'),
                    $this->addToken(array('order_id' => $orderInfo['order_id'])),
                    true
                )
            ),
            array(
                'text' => $this->language->get('text_add_awb'),
                'href' => $this->url->link(
                    $this->samedayVersionValidator->buildSamedayMethodPath('createAwb'),
                    $this->addToken(array('order_id' => $orderInfo['order_id'])),
                    true
                )
            )
        );

        /*
        * Actions
        */
        $data['estimate_cost_href'] = $this->url->link(
            $this->samedayVersionValidator->buildSamedayMethodPath('estimateCost')
        );
        $data['action'] = $this->url->link(
            $this->samedayVersionValidator->buildSamedayMethodPath('addAwb'),
            $this->addToken(array('order_id' => $orderInfo['order_id'])),
            true
        );
        $data['cancel'] = $this->url->link(
            $this->samedayVersionValidator->buildMethodPath('sale/order/info'),
            $this->addToken(array('order_id' => $orderInfo['order_id'])),
            true
        );

        /*
         * Main Layout
         */
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view($this->samedayVersionValidator->buildTemplatePath('extension/sameday/shipping/sameday_add_awb'), $data));
    }

    /**
     * @return void
     *
     * @throws JsonException|Exception
     */
    public function importGeolocations()
    {
        $countiesFile = DIR_SYSTEM . 'library/sameday-classes/utils/nomenclature/counties.json';
        $countiesData = json_decode(file_get_contents($countiesFile), true);
        $this->load->model($this->samedayVersionValidator->buildModelPath());

        foreach ($countiesData as $county) {
            $country_id = $this->{$this->samedayVersionValidator->buildMagicMethod()}->getCountryByCode($county['country_code']);
            $this->{$this->samedayVersionValidator->buildMagicMethod()}->addZoneCounty((int)$country_id, $county);
        }

        if ($this->{$this->samedayVersionValidator->buildMagicMethod()}->citiesCheck() === false) {
            $this->{$this->samedayVersionValidator->buildMagicMethod()}->createCitiesTable();
        }

        try {
            $citiesFile = DIR_SYSTEM . 'library/sameday-classes/utils/nomenclature/cities.json';
            $cities = json_decode(file_get_contents($citiesFile));
            $this->{$this->samedayVersionValidator->buildMagicMethod()}->truncateNomenclator();
            foreach ($cities as $city) {
                $countryId = $this->{$this->samedayVersionValidator->buildMagicMethod()}->getZone($city->country_code)['country_id'];
                $zoneId = $this->{$this->samedayVersionValidator->buildMagicMethod()}->getZoneId($countryId, $city->county_code);
                if ($zoneId !== null) {
                    $this->{$this->samedayVersionValidator->buildMagicMethod()}->addCity($city, $zoneId);
                }
            }
        } catch (\Exception $exception) {
            return;
        }

        $this->response->redirect(
            $this->url->link($this->samedayVersionValidator->buildModelPath(), $this->addToken(), true)
        );
    }

    public function bulkAwb()
    {
        $order_id = (int)($this->request->get['order_id'] ?? 0);
        $json = [];

        if (!$order_id) {
            $json['error'] = 'Missing order_id';
            $this->sendBulkAwbJson($json);
            return;
        }

        try {
            $model = $this->{$this->samedayVersionValidator->buildMagicMethod()};
            $model->createBulkAwbTable();
            $existingAwb = $model->getAwbForOrderId($order_id);

            if (!empty($existingAwb['awb_number'])) {
                $this->sendBulkAwbJson([
                    'order_id' => $order_id,
                    'already_exists' => true,
                    'awb_number' => $existingAwb['awb_number'],
                    'message' => 'AWB is already generated (' . $existingAwb['awb_number'] . ')',
                ]);
                return;
            }

            $model->ensureBulkAwbEntry($order_id);
            $this->postAwbShort($order_id);

            $bulkRows = $model->getBulkAwbByOrderIds([$order_id]);
            $json['order_id'] = $order_id;
            $json['sameday_bulk'] = isset($bulkRows[$order_id])
                ? $model->formatBulkAwbRow($bulkRows[$order_id])
                : null;

            $this->sendBulkAwbJson($json);
        } catch (\Throwable $e) {
            $this->sendBulkAwbJson([
                'order_id' => $order_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sendBulkAwbJson(array $json)
    {
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function clearBulkErrors()
    {
        $model = $this->{$this->samedayVersionValidator->buildMagicMethod()};
        $orderIds = $model->clearBulkAwbWithoutGenerated();

        $this->sendBulkAwbJson([
            'success' => true,
            'deleted' => count($orderIds),
            'order_ids' => $orderIds,
        ]);
    }

    /**
     * @return Action|void
     *
     * @throws Exception
     */
    public function addParcel()
    {
        $this->load->language($this->samedayVersionValidator->buildModelPath());
        $this->document->setTitle($this->language->get('heading_title_add_parcel'));
        $this->load->model('sale/order');

        if (!isset($this->request->get['order_id'])
            || !($orderInfo = $this->model_sale_order->getOrder($this->request->get['order_id']))
        ) {
            return new Action('error/not_found');
        }

        $orderId = (int)$orderInfo['order_id'];
        $shippingSamedayModel = $this->{$this->samedayVersionValidator->buildMagicMethod()};
        $awb = $shippingSamedayModel->getAwbForOrderId($orderId);

        if (empty($awb['awb_number'])) {
            $this->response->redirect(
                $this->url->link(
                    $this->samedayVersionValidator->buildMethodPath('sale/order/info'),
                    $this->addToken(['order_id' => $orderId]),
                    true
                )
            );
            return;
        }

        $data = [
            'heading_title_add_parcel' => $this->language->get('heading_title_add_parcel'),
            'text_create_parcel' => $this->language->get('text_create_parcel'),
            'entry_package_dimension' => $this->language->get('entry_package_dimension'),
            'entry_weight' => $this->language->get('entry_weight'),
            'entry_width' => $this->language->get('entry_width'),
            'entry_length' => $this->language->get('entry_length'),
            'entry_height' => $this->language->get('entry_height'),
            'entry_observation' => $this->language->get('entry_observation'),
            'entry_observation_title' => $this->language->get('entry_observation_title'),
            'entry_parcel_last' => $this->language->get('entry_parcel_last'),
            'entry_parcel_last_title' => $this->language->get('entry_parcel_last_title'),
            'text_awb_number' => $this->language->get('text_awb_number'),
            'text_existing_parcels' => $this->language->get('text_existing_parcels'),
            'awb_number' => $awb['awb_number'],
            'calculated_weight' => $this->calculatePackageWeight($orderId),
            'sameday_observation' => '',
            'sameday_parcel_last' => 0,
            'parcel_errors' => null,
            'error_weight' => null,
        ];

        $storedParcels = @unserialize($awb['parcels'], ['allowed_classes' => true]);
        $data['parcel_count'] = is_array($storedParcels) ? count($storedParcels) : 0;

        if ($this->request->server['REQUEST_METHOD'] === 'POST') {
            $result = $this->submitAddParcel($orderId, $awb);
            if (!empty($result['success'])) {
                $this->session->data['success'] = $result['success'];
                $this->response->redirect(
                    $this->url->link(
                        $this->samedayVersionValidator->buildMethodPath('sale/order/info'),
                        $this->addToken(['order_id' => $orderId]),
                        true
                    )
                );
                return;
            }

            $data['parcel_errors'] = $result['errors'] ?? ['An error occurred while adding the parcel.'];
            $data['sameday_observation'] = $this->request->post['sameday_observation'] ?? '';
            $data['sameday_parcel_last'] = !empty($this->request->post['sameday_parcel_last']) ? 1 : 0;
            if (!empty($this->error['error_weight'])) {
                $data['error_weight'] = $this->error['error_weight'];
            }
        }

        $data['breadcrumbs'] = [
            [
                'text' => $this->language->get('text_home'),
                'href' => $this->url->link('common/dashboard', $this->addToken(), true),
            ],
            [
                'text' => $this->language->get('text_orders'),
                'href' => $this->url->link('sale/order', $this->addToken(), true),
            ],
            [
                'text' => $this->language->get('text_order_info'),
                'href' => $this->url->link(
                    $this->samedayVersionValidator->buildMethodPath('sale/order/info'),
                    $this->addToken(['order_id' => $orderId]),
                    true
                ),
            ],
            [
                'text' => $this->language->get('heading_title_add_parcel'),
                'href' => $this->url->link(
                    $this->samedayVersionValidator->buildSamedayMethodPath('addParcel'),
                    $this->addToken(['order_id' => $orderId]),
                    true
                ),
            ],
        ];

        $data['action'] = $this->url->link(
            $this->samedayVersionValidator->buildSamedayMethodPath('addParcel'),
            $this->addToken(['order_id' => $orderId]),
            true
        );
        $data['cancel'] = $this->url->link(
            $this->samedayVersionValidator->buildMethodPath('sale/order/info'),
            $this->addToken(['order_id' => $orderId]),
            true
        );

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput(
            $this->load->view(
                $this->samedayVersionValidator->buildTemplatePath('extension/sameday/shipping/sameday_add_parcel'),
                $data
            )
        );
    }

    /**
     * @param int $orderId
     * @param array $awb
     *
     * @return array{success?: string, errors?: string[]}
     *
     * @throws Exception
     */
    private function submitAddParcel(int $orderId, array $awb): array
    {
        $this->load->language($this->samedayVersionValidator->buildModelPath());
        $weightRaw = $this->request->post['sameday_package_weight'] ?? null;
        $weight = is_array($weightRaw) ? ($weightRaw[0] ?? null) : $weightRaw;
        $widthRaw = $this->request->post['sameday_package_width'] ?? 0;
        $lengthRaw = $this->request->post['sameday_package_length'] ?? 0;
        $heightRaw = $this->request->post['sameday_package_height'] ?? 0;

        if ($weight === null || $weight === '' || (float)$weight <= 0) {
            $this->error['error_weight'] = $this->language->get('error_weight');

            return ['errors' => [$this->language->get('error_weight')]];
        }

        $storedParcels = @unserialize($awb['parcels'], ['allowed_classes' => true]);
        if (!is_array($storedParcels)) {
            $storedParcels = [];
        }

        $position = 1;
        foreach ($storedParcels as $parcel) {
            if ($parcel instanceof ParcelObject) {
                $position = max($position, (int)$parcel->getPosition() + 1);
            }
        }

        $dimensions = new ParcelDimensionsObject(
            (float)$weight,
            (float)(is_array($widthRaw) ? ($widthRaw[0] ?? 0) : $widthRaw),
            (float)(is_array($lengthRaw) ? ($lengthRaw[0] ?? 0) : $lengthRaw),
            (float)(is_array($heightRaw) ? ($heightRaw[0] ?? 0) : $heightRaw)
        );

        $observation = trim((string)($this->request->post['sameday_observation'] ?? ''));
        $isLast = !empty($this->request->post['sameday_parcel_last']);

        try {
            $sameday = new SamedayAlias($this->samedayHelper->initClient());
            $response = $sameday->postParcel(
                new SamedayPostParcelRequest(
                    $awb['awb_number'],
                    $dimensions,
                    $position,
                    $observation !== '' ? $observation : null,
                    null,
                    $isLast
                )
            );

            $storedParcels[] = new ParcelObject($position, $response->getParcelAwbNumber());
            $this->{$this->samedayVersionValidator->buildMagicMethod()}->updateAwbParcels(
                $orderId,
                serialize($storedParcels)
            );

            return [
                'success' => sprintf(
                    $this->language->get('text_parcel_added_success'),
                    $response->getParcelAwbNumber()
                ),
            ];
        } catch (SamedayBadRequestException $e) {
            return ['errors' => $this->formatSamedayExceptionErrors($e)];
        } catch (SamedaySDKException $e) {
            return ['errors' => [$e->getMessage()]];
        }
    }

    /**
     * @param SamedayBadRequestException $exception
     *
     * @return string[]
     */
    private function formatSamedayExceptionErrors(SamedayBadRequestException $exception): array
    {
        $errors = [];
        foreach ($exception->getErrors() as $error) {
            $key = isset($error['key']) ? implode('.', (array)$error['key']) : '';
            foreach ((array)($error['errors'] ?? []) as $message) {
                if (is_array($message)) {
                    $message = implode(' ', array_filter($message, 'is_string'));
                }
                if (!is_string($message) || $message === '') {
                    continue;
                }
                $errors[] = ($key !== '' ? $key . ': ' : '') . $message;
            }
        }

        if ($errors !== []) {
            return $errors;
        }

        $message = trim((string)$exception->getMessage());
        if ($message !== '') {
            return [$message];
        }

        $body = trim((string)$exception->getRawResponse()->getBody());
        if ($body !== '') {
            $json = json_decode($body, true);
            if (is_array($json)) {
                if (!empty($json['message']) && is_string($json['message'])) {
                    return [$json['message']];
                }
                if (!empty($json['error']['message']) && is_string($json['error']['message'])) {
                    return [$json['error']['message']];
                }
            }

            return [strlen($body) > 300 ? substr($body, 0, 300) . '...' : $body];
        }

        return ['Sameday rejected the request'];
    }

    /**
     * True when Sameday will not allow remote AWB cancellation (already closed / irreversible).
     */
    private function isRemoteAwbDeleteBlocked($message)
    {
        $normalized = function_exists('mb_strtolower')
            ? mb_strtolower($message)
            : strtolower($message);

        $needles = [
            'inchis din punct de vedere operational',
            'închis din punct de vedere operațional',
            'operationally closed',
            'closed from an operational',
            'pentru a-l redeschide',
            'already closed',
            'nu poate fi anulat',
            'cannot be cancelled',
            'cannot be canceled',
        ];

        foreach ($needles as $needle) {
            $needleNormalized = function_exists('mb_strtolower')
                ? mb_strtolower($needle)
                : strtolower($needle);
            $pos = function_exists('mb_strpos')
                ? mb_strpos($normalized, $needleNormalized)
                : strpos($normalized, $needleNormalized);
            if ($needle !== '' && $pos !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Remove local AWB + bulk feedback rows without calling Sameday.
     */
    private function purgeLocalAwbForOrder($orderId, $awbNumber)
    {
        $model = $this->{$this->samedayVersionValidator->buildMagicMethod()};
        if ($awbNumber !== '') {
            $model->deleteAwb($awbNumber);
        }
        $model->deleteBulkAwbByOrderId($orderId);
    }

    public function postAwbShort($order_id){
        $model = $this->{$this->samedayVersionValidator->buildMagicMethod()};
        $awb = null;
        $errors = null;

        try {
            $this->load->model('sale/order');
            $this->load->model('catalog/product');
            $order = $this->model_sale_order->getOrder($order_id);

            if (empty($order)) {
                throw new \RuntimeException('Order not found');
            }

            $orderProducts = ($this->samedayVersionValidator->isOc4())
                ? $this->model_sale_order->getProducts($order_id)
                : $this->model_sale_order->getOrderProducts($order_id);

            $pickupPoint = $model->getDefaultPickupPointId();
            if ($pickupPoint === null) {
                throw new \RuntimeException('No default Sameday pickup point configured');
            }

            $totalWeight = 0.0;
            foreach ($orderProducts as $orderProduct) {
                $orderData = $this->model_catalog_product->getProduct((int)$orderProduct['product_id']);
                $productWeight = (float)($orderData['weight'] ?? 0);
                $quantity = max(1, (int)($orderProduct['quantity'] ?? 1));
                $totalWeight += $productWeight * $quantity;
            }

            $parcelDimensions = [
                new ParcelDimensionsObject(max(0.1, $totalWeight > 0 ? $totalWeight : 1.0)),
            ];

            $address = trim($order['shipping_address_1'] . ' ' . $order['shipping_address_2']);
            $phone = $order['telephone'];
            $email = $order['email'];
            $companyObject = null;
            if (strlen((string)($order['payment_company'] ?? ''))) {
                $companyObject = new CompanyEntityObject(
                    $order['payment_company'],
                    '',
                    '',
                    '',
                    ''
                );
            }

            $shippingCode = ($this->samedayVersionValidator->isOc4())
                ? ($order['shipping_method']['code'] ?? '')
                : ($order['shipping_code'] ?? '');
            $shippingParts = explode('.', (string)$shippingCode);
            $serviceId = $shippingParts[2] ?? null;
            $lockerLastMile = $shippingParts[3] ?? '';
            $lockerAddress = $shippingParts[4] ?? '';

            if ($serviceId === null || $serviceId === '') {
                throw new \RuntimeException('Invalid Sameday shipping method on order');
            }

            $awbPaymentType = new AwbPaymentType(AwbPaymentType::CLIENT);
            $shippingCity = $order['shipping_city'];
            $shippingZone = $order['shipping_zone'];
            $name = $order['shipping_firstname'] . ' ' . $order['shipping_lastname'];
            $shippingPostcode = $order['shipping_postcode'];
            $repayment = 0;
            $paymentMethod = ($this->samedayVersionValidator->isOc4())
                ? $this->samedayHelper::isCodCode($order['payment_method']['code'] ?? '', $this->getConfig('sameday_cod'))
                : $this->samedayHelper::isCodCode($order['payment_code'] ?? '', $this->getConfig('sameday_cod'));
            if ($paymentMethod) {
                $repayment = $this->currency->format(
                    $order['total'],
                    $order['currency_code'],
                    $order['currency_value'],
                    false
                );
            }

            $thirdPartyPickUp = null;
            $reference = '';
            $observation = '';
            $oohLastMile = '';

            $service = $model->getServiceSameday(
                (int)$serviceId,
                $this->isTesting()
            );

            $request = new SamedayPostAwbRequest(
                (int)$pickupPoint,
                null,
                new PackageType(PackageType::PARCEL),
                $parcelDimensions,
                (int)$serviceId,
                $awbPaymentType,
                new AwbRecipientEntityObject(
                    $shippingCity,
                    $shippingZone,
                    $address,
                    $name,
                    $phone,
                    $email,
                    $companyObject,
                    $shippingPostcode
                ),
                0,
                $repayment,
                new CodCollectorType(CodCollectorType::CLIENT),
                $thirdPartyPickUp,
                [],
                null,
                $reference,
                $observation,
                '',
                '',
                null,
                $lockerLastMile,
                null,
                $oohLastMile,
                $order['currency_code']
            );

            try {
                $sameday = new SamedayAlias($this->samedayHelper->initClient());
            } catch (SamedaySDKException $exception) {
                $errors = [
                    [
                        'key' => ['SDK Error'],
                        'errors' => [$exception->getMessage()],
                    ],
                ];
                $model->updateBulkFeedback(['errors' => $errors], $order_id);

                return [
                    'awb' => null,
                    'errors' => $errors,
                ];
            }

            $awb = $sameday->postAwb($request);
            if ($awb !== null && !is_array($awb)) {
                $awbNumber = (string)$awb->getAwbNumber();
                $model->saveAwb([
                    'order_id' => $order['order_id'],
                    'awb_number' => $awbNumber,
                    'parcels' => serialize($awb->getParcels()),
                    'awb_cost' => $awb->getCost()
                ]);

                // Persist a plain payload so bulk UI can always read awb_number
                // (serializing SDK response objects is brittle across PHP/autoload).
                $model->updateBulkFeedback(['awb_number' => $awbNumber], $order_id);

                $lockerId = (is_numeric($lockerLastMile) && (string)$lockerLastMile !== '')
                    ? (int)$lockerLastMile
                    : null;
                $lockerAddressValue = ($lockerAddress !== null && $lockerAddress !== '')
                    ? (string)$lockerAddress
                    : null;

                try {
                    $model->updateShippingMethodAfterPostAwb(
                        (int)$order['order_id'],
                        is_array($service) ? $service : [],
                        $lockerId,
                        $lockerAddressValue
                    );
                } catch (\Throwable $shippingUpdateException) {
                    // AWB already saved + bulk feedback marked success; do not overwrite.
                }
            } elseif (is_array($awb) && null !== ($errors = $awb['errors'] ?? null)) {
                $model->updateBulkFeedback(['errors' => $errors], $order_id);
            }
        } catch (SamedayBadRequestException $e) {
            $errors = $e->getErrors();
            $model->updateBulkFeedback(['errors' => $errors], $order_id);
        } catch (SamedaySDKException $e) {
            $errors = [
                [
                    'key' => ['SDK Error'],
                    'errors' => [$e->getMessage() !== '' ? $e->getMessage() : 'SamedaySDKException'],
                ],
            ];
            $model->updateBulkFeedback(['errors' => $errors], $order_id);
        } catch (\Throwable $e) {
            $errors = [
                [
                    'key' => ['Generic Error'],
                    'errors' => [$e->getMessage() !== '' ? $e->getMessage() : get_class($e)],
                ],
            ];
            $model->updateBulkFeedback(['errors' => $errors], $order_id);
        }

        return [
            'awb' => $awb ?? null,
            'errors' => $errors ?? null
        ];
    }

    /**
     * @return void|Action
     *
     * @throws SamedayOtherException
     * @throws SamedaySDKException
     * @throws SamedayServerException
     * @throws SamedayAuthenticationException
     * @throws SamedayAuthorizationException
     * @throws SamedayNotFoundException
     */
    public function showAwbHistory()
    {
        $this->load->language($this->samedayVersionValidator->buildModelPath());
        $this->document->setTitle($this->language->get('heading_title_awb_history'));

        $awb = $this->{$this->samedayVersionValidator->buildMagicMethod()}->getAwbForOrderId($this->request->get['order_id']);

        if (!$awb) {
            return new Action('error/not_found');
        }

        $orderId = (int)$awb['order_id'];

        /*
        * Labels
        */
        $data = $this->buildLanguage(array(
            'text_awb_sync',
            'heading_title',
            'text_summary',
            'text_history',
            'awb_history_title',
            'button_cancel',
            'column_parcel_number',
            'column_parcel_weight',
            'column_delivered',
            'column_delivery_attempts',
            'column_is_picked_up',
            'column_picked_up_at',
            'column_status',
            'column_status_label',
            'column_status_state',
            'column_status_date',
            'column_county',
            'column_transit_location',
            'column_reason'
        ));

        $data['breadcrumbs'] = array(
            array(
                'text' => $this->language->get('text_home'),
                'href' => $this->url->link('common/dashboard', $this->addToken(), true),
                'separator' => false
            ),
            array(
                'text' => $this->language->get('text_orders'),
                'href' => $this->url->link('sale/order', $this->addToken(), true)
            ),
            array(
                'text' => $this->language->get('text_order_info'),
                'href' => $this->url->link(
                    $this->samedayVersionValidator->buildMethodPath('sale/order/info'),
                    $this->addToken(array('order_id' => $orderId)),
                    true
                )
            ),
            array(
                'text' => 'AWB',
                'href' => $this->url->link(
                    $this->samedayVersionValidator->buildSamedayMethodPath('showAwbStatus'),
                    $this->addToken(array('order_id' => $orderId)),
                    true
                )
            )
        );

        /*
         * Actions
         */
        $data['action'] = $this->url->link($this->samedayVersionValidator->buildModelPath(), $this->addToken(), true);
        $data['cancel'] = $this->url->link(
            $this->samedayVersionValidator->buildSamedayMethodPath('info'),
            $this->addToken(array('order_id' => $awb['order_id'])),
            true
        );

        /*
         * Main Layout
         */
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        if (empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            || strtolower($_SERVER['HTTP_X_REQUESTED_WITH'])
            !== 'xmlhttprequest'
        ) {
            // Not an ajax request, return main page.
            $this->response->setOutput($this->load->view($this->samedayVersionValidator->buildTemplatePath('extension/sameday/shipping/sameday_awb_history_status'), $data));

            return null;
        }

        // Build ajax html.
        $sameday = new SamedayAlias($this->samedayHelper->initClient());

        /** @var ParcelObject[] $parcels */
        $parcels = unserialize($awb['parcels'], ['']);
        foreach ($parcels as $parcel) {
            $parcelStatus = $sameday->getParcelStatusHistory(
                new SamedayGetParcelStatusHistoryRequest(
                    $parcel->getAwbNumber()
                )
            );
            $this->{$this->samedayVersionValidator->buildMagicMethod()}->refreshPackageHistory(
                $awb['order_id'],
                $parcel->getAwbNumber(),
                $parcelStatus->getSummary(),
                $parcelStatus->getHistory(),
                $parcelStatus->getExpeditionStatus()
            );
        }

        $data['packages'] = $this->{$this->samedayVersionValidator->buildMagicMethod()}->getPackagesForOrderId($awb['order_id']);

        $this->response->setOutput($this->load->view($this->samedayVersionValidator->buildTemplatePath('extension/sameday/shipping/sameday_awb_history_status_refresh'), $data));
    }

    /**
     * @return Action|void
     *
     * @throws SamedayBadRequestException
     * @throws SamedaySDKException
     * @throws SamedayAuthenticationException
     * @throws SamedayAuthorizationException
     * @throws SamedayNotFoundException
     * @throws SamedayServerException
     */
    public function showAsPdf()
    {
        $orderId = (int)$this->request->get['order_id'];
        $awb = $this->{$this->samedayVersionValidator->buildMagicMethod()}->getAwbForOrderId($orderId);

        if (!$awb) {
            return new Action('error/not_found');
        }

        header('Content-type: application/pdf');
        header("Cache-Control: no-cache");
        header("Pragma: no-cache");

        $sameday = new SamedayAlias($this->samedayHelper->initClient());

        $awbFormat = $this->getConfig('sameday_awb_format');

        $content = $sameday->getAwbPdf(
            new SamedayGetAwbPdfRequest($awb['awb_number'], new AwbPdfType($awbFormat))
        );

        echo $content->getPdf();

        exit;
    }

    /**
     * @param array $params
     *
     * @return array
     */
    private function postAwb(array $params): array
    {
        $parcelDimensions = [];
        foreach ($this->request->post['sameday_package_weight'] as $k => $weight) {
            $parcelDimensions[] = new ParcelDimensionsObject(
                $weight,
                $this->request->post['sameday_package_width'][$k],
                $this->request->post['sameday_package_length'][$k],
                $this->request->post['sameday_package_height'][$k]
            );
        }

        $companyObject = null;
        if (strlen($params['payment_company'])) {
            $companyObject = new CompanyEntityObject(
                $params['payment_company'],
                '',
                '',
                '',
                ''
            );
        }

        $thirdPartyPickUp = null;
        if ($params['sameday_third_party_pickup']) {
            $thirdPartyCompany = null;
            if ($params['sameday_third_party_person_type']) {
                $thirdPartyCompany = new CompanyEntityObject(
                    $params['sameday_third_party_person_company'],
                    $params['sameday_third_party_person_cif'],
                    $params['sameday_third_party_person_onrc'],
                    $params['sameday_third_party_person_bank'],
                    $params['sameday_third_party_person_iban']
                );
            }

            $thirdPartyPickUp = new ThirdPartyPickupEntityObject(
                $params['sameday_third_party_pickup_county'],
                $params['sameday_third_party_pickup_city'],
                $params['sameday_third_party_pickup_address'],
                $params['sameday_third_party_pickup_name'],
                $params['sameday_third_party_pickup_phone'],
                $thirdPartyCompany
            );
        }

        $address = trim($params['shipping_address_1'] . ' ' . $params['shipping_address_2']);

        $fieldErrors = null;
        if ('' === $phone = $params['telephone'] ?? '') {
            $fieldErrors[] = 'Must complete phone number!';
        }

        if ('' === $email = $params['email'] ?? '') {
            $fieldErrors[] = 'Must complete email address!';
        }

        if (null !== $fieldErrors) {
            return [
                'errors' => [
                    'errors' => [
                        'key' => ['Invalid field'],
                        'errors' => $fieldErrors
                    ]
                ]
            ];
        }

        $serviceTaxes = [];
        if (isset($params['sameday_locker_first_mile'])) {
            $serviceTaxes[] = $params['sameday_locker_first_mile'];
        }

        $lockerLastMile = null;
        $oohLastMile = null;
        if (null !== $params['sameday_locker_id']) {
            if ($this->samedayHelper::LOCKER_NEXT_DAY_SERVICE
                === $this->samedayHelper->checkOohLocationType($params['sameday_locker_id'])
            ) {
                $lockerLastMile = $params['sameday_locker_id'];
            }

            if ($this->samedayHelper::SAMEDAY_PUDO_SERVICE
                === $this->samedayHelper->checkOohLocationType($params['sameday_locker_id'])
            ) {
                $oohLastMile = $params['sameday_locker_id'];
            }
        }

        $request = new SamedayPostAwbRequest(
            (int)($params['sameday_pickup_point'] ?? null),
            null,
            new PackageType($params['sameday_package_type']),
            $parcelDimensions,
            (int)($params['sameday_service'] ?? null),
            new AwbPaymentType($params['sameday_awb_payment']),
            new AwbRecipientEntityObject(
                $params['shipping_city'],
                $params['shipping_zone'],
                $address,
                $params['shipping_firstname'] . ' ' . $params['shipping_lastname'],
                $phone,
                $email,
                $companyObject,
                $params['shipping_postcode']
            ),
            $params['sameday_insured_value'],
            $params['sameday_repayment'],
            new CodCollectorType(CodCollectorType::CLIENT),
            $thirdPartyPickUp,
            $serviceTaxes,
            null,
            $params['sameday_client_reference'],
            $params['sameday_observation'],
            '',
            '',
            null,
            $lockerLastMile,
            null,
            $oohLastMile,
            $this->samedayHelper::SAMEDAY_ELIGIBLE_CURRENCIES[$params['shipping_iso_code_2']]
        );

        try {
            $sameday = new SamedayAlias($this->samedayHelper->initClient());
        } catch (SamedaySDKException $exception) {
            return [
                'errors' => [
                    'key' => [$exception->getCode()],
                    'errors' => [$exception->getMessage()]
                ]
            ];
        }

        try {
            $awb = $sameday->postAwb($request);
        } catch (SamedayBadRequestException $e) {
            $errors = $e->getErrors();
        } catch (SamedaySDKException $e) {
            $errors[] = [
                'key' => ['SDK Error'],
                'errors' => [$e->getMessage()],
            ];
        } catch (\Exception $e) {
            $errors[] = [
                'key' => ['Generic Error'],
                'errors' => [$e->getMessage()],
            ];
        }

        return [
            'awb' => $awb ?? null,
            'errors' => $errors ?? null
        ];
    }

    /**
     * @param int $orderId
     *
     * @return float|int
     *
     * @throws Exception
     */
    private function calculatePackageWeight(int $orderId)
    {
        $items = $this->getItemsByOrderId($orderId);
        $totalWeight = 0;
        foreach ($items as $item) {
            $totalWeight += round($item['product_info']['weight'] * $item['quantity'], 2);
        }

        return $totalWeight;
    }

    /**
     * @return void
     *
     * @throws SamedaySDKException
     */
    public function estimateCost()
    {
        $this->load->model('sale/order');
        $this->load->language($this->samedayVersionValidator->buildModelPath());

        $params = $this->request->post;
        $order_id = $params['order_id'];

        $orderInfo = $this->model_sale_order->getOrder($order_id);

        if (!strlen($params['sameday_insured_value'])) {
            $return['errors'][] = $this->language->get('error_insured_value_cost');
        }

        $parcelDimensions = [];
        foreach ($params['sameday_package_weight'] as $k => $weight) {
            if ($weight === '' || $weight < 1) {
                $return['errors'][] = $this->language->get('error_weight_cost');
            }

            $parcelDimensions[] = new ParcelDimensionsObject(
                $weight,
                $params['sameday_package_width'][$k],
                $params['sameday_package_length'][$k],
                $params['sameday_package_height'][$k]
            );
        }

        $city = ucwords(strtolower($orderInfo['shipping_city']));
        if ($city === 'Bucuresti') {
            $city = 'Sectorul 1';
        }

        if (!isset($return['errors'])) {
            $serviceTaxes = [];
            if (isset($params['sameday_locker_first_mile'])) {
                $serviceTaxes[] = $params['sameday_locker_first_mile'];
            }

            $estimateCostRequest = new SamedayPostAwbEstimationRequest(
                $params['sameday_pickup_point'],
                null,
                new PackageType(
                    $params['sameday_package_type']
                ),
                $parcelDimensions,
                $params['sameday_service'],
                new AwbPaymentType(
                    $params['sameday_awb_payment']
                ),
                new AwbRecipientEntityObject(
                    $city,
                    $orderInfo['shipping_zone'],
                    $orderInfo['shipping_address_1'],
                    null,
                    null,
                    null,
                    null,
                    $orderInfo['shipping_postcode']
                ),
                $params['sameday_insured_value'],
                $params['sameday_repayment'],
                null,
                $serviceTaxes,
                $this->samedayHelper::SAMEDAY_ELIGIBLE_CURRENCIES[$orderInfo['shipping_iso_code_2']]
            );

            $sameday = new SamedayAlias($this->samedayHelper->initClient());
            $return = [];

            try {
                $estimation = $sameday->postAwbEstimation($estimateCostRequest);
                $cost = $estimation->getCost();
                $currency = $estimation->getCurrency();

                $return['success'] = sprintf($this->language->get('estimated_cost_success_message'), $cost, $currency);
            } catch (SamedayBadRequestException $exception) {
                $errors = $exception->getErrors();
            } catch (SamedaySDKException $exception) {
                $errors[] = [
                    'key' => ['SDK Error'],
                    'errors' => [$exception->getMessage()],
                ];
            }

            if (isset($errors)) {
                foreach ($errors as $error) {
                    foreach ($error['errors'] as $message) {
                        $return['errors'][] = implode('.', $error['key']) . ': ' . $message;
                    }
                }
            }
        }

        $this->response->setOutput(json_encode($return));
    }

    /**
     * @param int $orderId
     *
     * @return array
     *
     * @throws Exception
     */
    private function getItemsByOrderId(int $orderId)
    {
        $this->load->model('sale/order');
        $this->load->model('catalog/product');

        $items = ($this->samedayVersionValidator->isOc4()) ?
            $this->model_sale_order->getProducts($orderId) :
            $this->model_sale_order->getOrderProducts($orderId);

        foreach ($items as $item => $value) {
            $items[$item]['product_info'] = $this->model_catalog_product->getProduct($value['product_id']);
        }

        return $items;
    }

    /**
     * @param int $orderId
     * @param bool $forceLocal
     *
     * @return array
     *
     * @throws SamedaySDKException
     */
    private function deleteAwbForOrderId($orderId, $forceLocal = false)
    {
        $awb = $this->{$this->samedayVersionValidator->buildMagicMethod()}->getAwbForOrderId($orderId);

        if (empty($awb['awb_number'])) {
            return [
                'order_id' => $orderId,
                'success' => false,
                'error' => 'AWB not found',
                'awb_number' => '',
            ];
        }

        $awbNumber = (string)$awb['awb_number'];

        if ($forceLocal) {
            $this->purgeLocalAwbForOrder($orderId, $awbNumber);

            return [
                'order_id' => $orderId,
                'success' => true,
                'error' => '',
                'awb_number' => $awbNumber,
                'local_only' => true,
                'message' => 'Local AWB link removed (Sameday was not contacted).',
            ];
        }

        try {
            $sameday = new SamedayAlias($this->samedayHelper->initClient());
            $sameday->deleteAwb(new SamedayDeleteAwbRequest($awbNumber));
            $this->purgeLocalAwbForOrder($orderId, $awbNumber);

            return [
                'order_id' => $orderId,
                'success' => true,
                'error' => '',
                'awb_number' => $awbNumber,
                'message' => 'AWB removed successfully',
            ];
        } catch (SamedayBadRequestException $e) {
            $messages = $this->formatSamedayExceptionErrors($e);
            $message = implode('; ', array_filter($messages));
            if ($message === '') {
                $message = 'Sameday rejected AWB deletion';
            }

            // Closed / irreversible AWBs cannot be cancelled remotely — free the order locally.
            if ($this->isRemoteAwbDeleteBlocked($message)) {
                $this->purgeLocalAwbForOrder($orderId, $awbNumber);

                return [
                    'order_id' => $orderId,
                    'success' => true,
                    'error' => '',
                    'awb_number' => $awbNumber,
                    'local_only' => true,
                    'message' => 'AWB is closed on Sameday and cannot be cancelled remotely. Local AWB link was removed so you can continue with the order.',
                    'warning' => $message,
                ];
            }

            return [
                'order_id' => $orderId,
                'success' => false,
                'error' => $message,
                'awb_number' => $awbNumber,
            ];
        } catch (\Throwable $e) {
            $message = $e->getMessage();

            return [
                'order_id' => $orderId,
                'success' => false,
                'error' => $message !== '' ? $message : get_class($e),
                'awb_number' => $awbNumber,
            ];
        }
    }

    /**
     * @return void
     *
     * @throws SamedaySDKException
     */
    public function deleteAwb()
    {
        $orderId = (int)($this->request->get['order_id'] ?? 0);
        $isAjax = !empty($this->request->get['ajax']);
        $forceLocal = !empty($this->request->get['force_local']);
        $result = $this->deleteAwbForOrderId($orderId, $forceLocal);

        if ($result['success']) {
            if ($isAjax) {
                $this->sendBulkAwbJson([
                    'success' => true,
                    'order_id' => $orderId,
                    'awb_number' => isset($result['awb_number']) ? $result['awb_number'] : '',
                    'message' => isset($result['message']) ? $result['message'] : 'AWB removed successfully',
                    'local_only' => !empty($result['local_only']),
                    'warning' => isset($result['warning']) ? $result['warning'] : '',
                ]);
                return;
            }
        } else {
            if ($isAjax) {
                $this->sendBulkAwbJson([
                    'error' => $result['error'],
                    'order_id' => $orderId,
                    'can_force_local' => true,
                ]);
                return;
            }

            if ($result['error'] !== 'AWB not found') {
                $this->response->setOutput(json_encode($result['error']));
                return;
            }
        }

        if ($isAjax) {
            return;
        }

        $this->response->redirect($this->url->link(
            $this->samedayVersionValidator->buildMethodPath('sale/order/info'),
            $this->addToken(array('order_id' => $orderId)),
            true
        ));
    }

    /**
     * @return void
     *
     * @throws SamedaySDKException
     */
    public function bulkDeleteAwb()
    {
        $orderId = (int)($this->request->get['order_id'] ?? 0);

        if (!$orderId) {
            $this->sendBulkAwbJson(['error' => 'Missing order_id']);
            return;
        }

        $forceLocal = !empty($this->request->get['force_local']);
        $result = $this->deleteAwbForOrderId($orderId, $forceLocal);

        if ($result['success']) {
            $this->sendBulkAwbJson([
                'success' => true,
                'order_id' => $orderId,
                'awb_number' => $result['awb_number'],
                'message' => isset($result['message']) ? $result['message'] : 'AWB removed successfully',
                'local_only' => !empty($result['local_only']),
                'warning' => isset($result['warning']) ? $result['warning'] : '',
            ]);
            return;
        }

        $this->sendBulkAwbJson([
            'error' => $result['error'],
            'order_id' => $orderId,
            'awb_number' => $result['awb_number'],
        ]);
    }

    /**
     * @return bool
     */
    private function validateFormBeforeAwbGeneration(): bool
    {
        if (!strlen($this->request->post['sameday_insured_value'])
            || $this->request->post['sameday_insured_value'] < 0) {
            $this->error['error_insured_val'] = $this->language->get('error_insured_value');
        }

        $packageWeights = $this->request->post['sameday_package_weight'];
        foreach ($packageWeights as $weight) {
            if (!strlen($weight)) {
                $this->error['error_weight'] = $this->language->get('error_weight');
            }
        }

        if ($this->request->post['sameday_third_party_pickup']) {
            $thirdPartyMandatoryFields = array(
                'sameday_third_party_pickup_county',
                'sameday_third_party_pickup_city',
                'sameday_third_party_pickup_address',
                'sameday_third_party_pickup_name',
                'sameday_third_party_pickup_phone'
            );
            foreach ($thirdPartyMandatoryFields as $field) {
                if (!strlen($this->request->post[$field])) {
                    $error = str_replace('sameday_', 'error_', $field);
                    $entry = str_replace('sameday_', 'entry_', $field);
                    $this->error[$error] = sprintf(
                        $this->language->get('error_third_party_pickup_mandatory_fields'),
                        $this->language->get($entry)
                    );
                }
            }
        }

        if ($this->request->post['sameday_third_party_person_type']) {
            $personTypeMandatoryFields = array(
                'sameday_third_party_person_company',
                'sameday_third_party_person_cif',
                'sameday_third_party_person_onrc',
                'sameday_third_party_person_bank',
                'sameday_third_party_person_iban'
            );
            foreach ($personTypeMandatoryFields as $field) {
                if (!strlen($this->request->post[$field])) {
                    $error = str_replace('sameday_', 'error_', $field);
                    $entry = str_replace('sameday_', 'entry_', $field);
                    $this->error[$error] = sprintf(
                        $this->language->get('error_third_party_person_mandatory_fields'),
                        $this->language->get($entry)
                    );
                }
            }
        }

        return !$this->error;
    }

    /**
     * @return bool
     */
    private function validatePermissions(): bool
    {
        if (!$this->user->hasPermission('modify', $this->samedayVersionValidator->buildModelPath())) {
            $this->error['warning'] = $this->language->get('error_permission');

            return false;
        }

        return true;
    }

    /**
     * @return bool
     *
     * @throws SamedaySDKException
     */
    private function validate(): bool
    {
        if (!$this->validatePermissions()) {
            return false;
        }

        $needLogin = false;

        $username = $this->getConfig('sameday_username');
        $post = $this->{$this->samedayVersionValidator->buildMagicMethod()}->sanitizeInputs($_POST);
        $usernameKey = $this->{$this->samedayVersionValidator->buildMagicMethod()}->getKey('sameday_username');
        $passwordKey = $this->{$this->samedayVersionValidator->buildMagicMethod()}->getKey('sameday_password');

        if (!isset($post[$usernameKey])) {
            $this->error['warning'] = $this->language->get('error_username_password');

            return false;
        }

        if ($post[$usernameKey] !== $username) {
            // Username changed.
            $username = $post[$usernameKey];
            $needLogin = true;
        }

        $password = $this->getConfig('sameday_password');
        $newPassword = isset($post[$passwordKey]) ? $post[$passwordKey] : '';
        if ('' !== $newPassword) {
            // Password updated.
            $password = $newPassword;
            $needLogin = true;
        } elseif (($password === '' || $password === null) && $needLogin) {
            $this->error['warning'] = $this->language->get('error_username_password');

            return false;
        }

        if ($needLogin) {
            // Check if login is valid.
            $isLogged = false;
            $lastLoginError = '';
            $envModes = $this->samedayHelper::getEnvModes();
            foreach ($envModes as $hostCountry => $envModesByHosts) {
                if ($isLogged === true) {
                    break;
                }

                foreach ($envModesByHosts as $key => $apiUrl) {
                    $sameday = $this->samedayHelper->initClient(
                        $username,
                        $password,
                        $apiUrl
                    );

                    try {
                        if ($sameday->login()) {
                            $isTesting = (int)($this->samedayHelper::API_DEMO === $key);
                            $this->testing = $isTesting;
                            $this->hostCountry = $hostCountry;
                            $isLogged = true;

                            break;
                        }
                    } catch (Exception $exception) {
                        $lastLoginError = $exception->getMessage();
                        continue;
                    }
                }
            }

            if (!$isLogged) {
                $this->error['warning'] = $this->language->get('error_username_password');
                if ($lastLoginError !== '') {
                    $this->error['warning'] .= ' (' . $lastLoginError . ')';
                }

                return false;
            }
        }

        return !$this->error;
    }

    /**
     * @param string|array $keyOrKeys
     *
     * @return string|array
     */
    private function buildLanguage($keyOrKeys)
    {
        if (is_array($keyOrKeys)) {
            $entries = [];
            foreach ($keyOrKeys as $key) {
                $entries[$key] = $this->language->get($key);
            }

            return $entries;
        }

        return $this->language->get($keyOrKeys);
    }

    /**
     * @return array
     */
    private function buildRequest(): array
    {
        $entries = array();
        $keys = \SamedayHelper::SAMEDAY_CONFIGS;
        foreach ($keys as $key => $value) {
            $requestKey = sprintf("%ssameday_%s", $this->{$this->samedayVersionValidator->buildMagicMethod()}->getPrefix(), $key);
            if ('' === $valueOfKey = ($this->getConfig("sameday_$key") ?? '')) {
                $valueOfKey = $value;
            }

            $posted = isset($this->request->post[$requestKey]) ? $this->request->post[$requestKey] : null;
            // Password field is always empty in the HTML form — keep stored value for the client helper.
            if ($key === 'password' && ($posted === null || $posted === '')) {
                $entries["sameday_$key"] = $valueOfKey;
            } else {
                $entries["sameday_$key"] = ($posted !== null) ? $posted : $valueOfKey;
            }
        }

        return $entries;
    }

    /**
     * @param array $keys
     * @param array $service
     *
     * @return array
     */
    private function buildRequestService(array $keys, array $service): array
    {
        $entries = array();
        $entries['disabled'] = '';
        foreach ($keys as $key) {
            if ($key === 'name' && $this->samedayHelper->isOohDeliveryOption($service['sameday_code'])) {
                $entries['disabled'] = 'disabled';
                if (in_array($service['sameday_code'], $this->samedayHelper::ELIGIBLE_SAMEDAY_SERVICES_CROSSBORDER)) {
                    $entries[$key] = $this->samedayHelper::OOH_CROSSBORDER_SERVICES_LABELS[$this->samedayHelper->getHostCountry()];
                } else {
                    $entries[$key] = $this->samedayHelper::OOH_SERVICES_LABELS[$this->samedayHelper->getHostCountry()];
                }
            } else {
                $entries[$key] = $this->request->post[$key] ?? $service[$key];
            }
        }

        return $entries;
    }

    /**
     * @param array $keys
     *
     * @return array
     */
    private function buildRequestAwb(array $keys): array
    {
        $entries = array();
        foreach ($keys as $key) {
            $entries[$key] = $this->request->post[$key] ?? '';
        }

        return $entries;
    }

    /**
     * @param string $key
     *
     * @return string
     */
    private function buildError(string $key): string
    {
        if (isset($this->error[$key])) {
            return $this->error[$key];
        }

        if (isset($this->session->data["error_$key"])) {
            $message = $this->session->data["error_$key"];
            unset($this->session->data["error_$key"]);

            return $message;
        }

        return '';
    }

    /**
     * @return array[]
     */
    private function getStatuses(): array
    {
        $lang = $this->buildLanguage([
            'text_disabled',
            'text_services_status_always'
        ]);

        return array(
            array(
                'value' => 0,
                'text' => $lang['text_disabled']
            ),
            array(
                'value' => 1,
                'text' => $lang['text_services_status_always']
            )
        );
    }

    /**
     * @param string $serviceOptionalTaxes
     *
     * @return bool
     */
    private function isServiceEligibleToPDO(string $serviceOptionalTaxes): bool
    {
        $serviceOptionalTaxes = json_decode($serviceOptionalTaxes, true);
        foreach ($serviceOptionalTaxes as $tax) {
            if ($tax['code'] === $this->samedayHelper::SERVICE_OPTIONAL_TAX_PDO_CODE) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array $parts
     *
     * @return array
     */
    private function addToken(array $parts = array())
    {
        if (isset($this->session->data['token'])) {
            return array_merge($parts, array('token' => $this->session->data['token']));
        }

        if (isset($this->session->data['user_token'])) {
            return array_merge($parts, array('user_token' => $this->session->data['user_token']));
        }

        return $parts;
    }

    /**
     * @param string $key
     *
     * @return mixed
     */
    private function getConfig(string $key)
    {
        return $this->{$this->samedayVersionValidator->buildMagicMethod()}->getConfig($key);
    }

    /**
     * @return string
     */
    private function getRouteExtension(): string
    {
        if (strpos(VERSION, '2') === 0) {
            return 'extension/extension';
        }

        return 'marketplace/extension';
    }

    /**
     * @param bool $isShow
     *
     * @return string
     */
    private function toggleHtmlElement(bool $isShow): string
    {
        return $isShow === true ? $this->samedayHelper::TOGGLE_HTML_ELEMENT['show'] : $this->samedayHelper::TOGGLE_HTML_ELEMENT['hide'];
    }

    private function isOC4(){
        return (strpos(VERSION, '4') === 0);
    }

    public function order_info_before(&$route, array &$data, &$code = null)
    {
        // OC3 injects AWB UI via OCMOD on sale/order + extension/shipping/sameday/info.
        // Calling sale/order/info here would re-enter the order page and exhaust memory.
        if (!$this->samedayVersionValidator->isOc4()) {
            return;
        }

        $order_id = (int)($this->request->get['order_id'] ?? 0);
        if (!$order_id) {
            return;
        }

        $this->load->model('sale/order');
        $order_info = $this->model_sale_order->getOrder($order_id);
        if (!$order_info) {
            return;
        }

        $info_data = $this->load->controller(
            $this->samedayVersionValidator->buildSamedayMethodPath('info'),
            $order_info
        );
        if (!is_array($info_data)) {
            return;
        }

        $content = $this->load->view(
            $this->samedayVersionValidator->buildTemplatePath('extension/sameday/shipping/sameday_order_info_tab'),
            $info_data
        );
        $data['tabs'][] = [
            'code'    => 'sameday',
            'title'   => 'Sameday AWB',
            'content' => $content
        ];
    }

    public function order_list_before(&$route, &$data, &$code = null)
    {
        $this->populateOrderListViewData($data);

        if ($this->samedayVersionValidator->isOc4()) {
            $templateFile = $this->samedayVersionValidator->buildOrderListTemplateFile();
            if ($templateFile !== '' && is_file($templateFile)) {
                $code = file_get_contents($templateFile);
            }
        }
    }

    /**
     * @param array $data
     */
    private function populateOrderListViewData(array &$data)
    {
        $this->load->language($this->samedayVersionValidator->buildModelPath());
        $data['sameday_button_show_awb'] = $this->language->get('text_button_show_awb');
        $data['sameday_show_awb_pdf_url_base'] = html_entity_decode(
            $this->url->link(
                $this->samedayVersionValidator->buildSamedayMethodPath('showAsPdf'),
                $this->addToken(),
                true
            ) . '&order_id=',
            ENT_QUOTES,
            'UTF-8'
        );
        $data['sameday_show_awb_history_url_base'] = html_entity_decode(
            $this->url->link(
                $this->samedayVersionValidator->buildSamedayMethodPath('showAwbHistory'),
                $this->addToken(),
                true
            ) . '&order_id=',
            ENT_QUOTES,
            'UTF-8'
        );

        $data['ajax_url_bulk'] = html_entity_decode(
            $this->url->link(
                $this->samedayVersionValidator->buildSamedayMethodPath('bulkAwb'),
                $this->addToken(),
                true
            ),
            ENT_QUOTES,
            'UTF-8'
        );

        $data['ajax_url_bulk_delete'] = html_entity_decode(
            $this->url->link(
                $this->samedayVersionValidator->buildSamedayMethodPath('bulkDeleteAwb'),
                $this->addToken(),
                true
            ),
            ENT_QUOTES,
            'UTF-8'
        );

        $data['ajax_url_delete_awb'] = html_entity_decode(
            $this->url->link(
                $this->samedayVersionValidator->buildSamedayMethodPath('deleteAwb'),
                $this->addToken(),
                true
            ),
            ENT_QUOTES,
            'UTF-8'
        );

        $data['ajax_url_clear_bulk_errors'] = html_entity_decode(
            $this->url->link(
                $this->samedayVersionValidator->buildSamedayMethodPath('clearBulkErrors'),
                $this->addToken(),
                true
            ),
            ENT_QUOTES,
            'UTF-8'
        );

        if (empty($data['orders']) || !is_array($data['orders'])) {
            return;
        }

        $orderIds = array_column($data['orders'], 'order_id');
        $model = $this->{$this->samedayVersionValidator->buildMagicMethod()};
        $bulkRows = $model->getBulkAwbByOrderIds($orderIds);
        $awbRows = $model->getAwbByOrderIds($orderIds);

        foreach ($data['orders'] as &$order) {
            $orderId = (int)$order['order_id'];
            $order['sameday_bulk'] = isset($bulkRows[$orderId])
                ? $model->formatBulkAwbRow($bulkRows[$orderId])
                : null;

            if (isset($awbRows[$orderId]['awb_number']) && $awbRows[$orderId]['awb_number'] !== '') {
                $order['sameday_awb_number'] = $awbRows[$orderId]['awb_number'];
                if (empty($order['sameday_bulk']['awb_number'])) {
                    if ($order['sameday_bulk'] === null) {
                        $order['sameday_bulk'] = [
                            'status' => 1,
                            'awb_number' => $order['sameday_awb_number'],
                            'error' => null,
                            'label' => $order['sameday_awb_number'],
                        ];
                    } else {
                        $order['sameday_bulk']['awb_number'] = $order['sameday_awb_number'];
                    }
                }
            } else {
                $order['sameday_awb_number'] = null;
            }

            $hasAwb = !empty($order['sameday_awb_number'])
                || !empty($order['sameday_bulk']['awb_number']);
            $order['has_sameday_awb'] = $hasAwb;
            $order['add_awb_link'] = $hasAwb ? '' : html_entity_decode(
                $this->url->link(
                    $this->samedayVersionValidator->buildSamedayMethodPath('addAwb'),
                    $this->addToken(['order_id' => $orderId]),
                    true
                ),
                ENT_QUOTES,
                'UTF-8'
            );
            $order['show_awb_pdf_link'] = $hasAwb
                ? $data['sameday_show_awb_pdf_url_base'] . $orderId
                : '';
            $order['show_awb_history_link'] = $hasAwb
                ? $data['sameday_show_awb_history_url_base'] . $orderId
                : '';
            $order['add_parcel_link'] = $hasAwb ? html_entity_decode(
                $this->url->link(
                    $this->samedayVersionValidator->buildSamedayMethodPath('addParcel'),
                    $this->addToken(['order_id' => $orderId]),
                    true
                ),
                ENT_QUOTES,
                'UTF-8'
            ) : '';
        }
        unset($order);
    }

    public function order_list_after(string &$route, array &$data, string &$output)
    {
        if (!$this->samedayVersionValidator->isOc4()) {
            return;
        }

        $this->populateOrderListViewData($data);
        $output .= $this->load->view(
            $this->samedayVersionValidator->buildOrderListModalsViewRoute(),
            $data
        );
    }

    public function order_page_after(string &$route, array &$data, string &$output)
    {
        if (!$this->samedayVersionValidator->isOc4()) {
            return;
        }

        $search = $this->samedayVersionValidator->getOrderToolbarInjectionSearch();
        $replace = $search . $this->samedayVersionValidator->getOrderListToolbarHtml();
        $pos = strpos($output, $search);

        if ($pos !== false) {
            $output = substr_replace($output, $replace, $pos, strlen($search));
        }
    }

    public function checkout_scripts_before(string &$route, array &$args) {
        $current_route = $this->request->get['route'] ?? '';
        if (strpos($current_route, 'checkout') !== 0) {
            return;
        }

        // Locker map (same as OC3 shipping_method)
        $this->document->addScript('https://cdn.sameday.ro/locker-plugin/lockerpluginsdk.js', 'footer');
        $this->document->addScript('extension/sameday/catalog/view/javascript/sameday/assets/sameday-locker.js', 'footer');

        // Payment (same as OC3 payment_method)
        $this->document->addScript('extension/sameday/catalog/view/javascript/sameday/assets/update-payment.js', 'footer');
    }
    // End of file
}