<?php

use Sameday\Exceptions\SamedayBadRequestException;
use Sameday\Exceptions\SamedaySDKException;
use Sameday\Objects\ParcelDimensionsObject;
use Sameday\Objects\PickupPoint\PickupPointContactPersonObject;
use Sameday\Objects\PostAwb\ParcelObject;
use Sameday\Objects\PostAwb\Request\AwbRecipientEntityObject;
use Sameday\Objects\PostAwb\Request\CompanyEntityObject;
use Sameday\Objects\PostAwb\Request\ThirdPartyPickupEntityObject;
use Sameday\Requests\SamedayDeleteAwbRequest;
use Sameday\Requests\SamedayGetParcelStatusHistoryRequest;
use Sameday\Requests\SamedayPostAwbEstimationRequest;
use Sameday\Requests\SamedayPostPickupPointRequest;
use Sameday\Objects\Types\AwbPaymentType;
use Sameday\Objects\Types\AwbPdfType;
use Sameday\Objects\Types\CodCollectorType;
use Sameday\Objects\Types\PackageType;
use Sameday\Requests\SamedayGetAwbPdfRequest;
use Sameday\Requests\SamedayGetLockersRequest;
use Sameday\Requests\SamedayGetPickupPointsRequest;
use Sameday\Requests\SamedayGetServicesRequest;
use Sameday\Requests\SamedayPostAwbRequest;
use Sameday\Sameday;

require_once DIR_SYSTEM . 'library/sameday-php-sdk/src/Sameday/autoload.php';

class ControllerExtensionShippingSameday extends Controller
{
    private $error = array();

    const DEFAULT_VALUE_LOCKER_MAX_ITEMS = 5;

    const SAMEDAY_CONFIGS = [
        'username' => null,
        'password' => null,
        'testing' => null,
        'tax_class_id' => null,
        'geo_zone_id' => null,
        'status' => null,
        'estimated_cost' => null,
        'show_lockers_map' => null,
        'locker_max_items' => self::DEFAULT_VALUE_LOCKER_MAX_ITEMS,
        'sort_order' => 0,
        'host_country' => null,
    ];

    const TOGGLE_HTML_ELEMENT = [
        'show' => 'block',
        'hide' => 'none',
    ];

    const IMPORT_LOCAL_DATA_ACTIONS = [
        'importServices',
        'importPickupPoint',
        'importLockers',
    ];

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
     * @param mixed $registry
     *
     * @throws Exception
     */
    public function __construct($registry)
    {
        parent::__construct($registry);

        $this->load->model('extension/shipping/sameday');

        $this->samedayHelper = Samedayclasses::getSamedayHelper(
            $this->buildRequest(),
            $registry,
            $this->model_extension_shipping_sameday->getPrefix()
        );
    }

    /**
     * @return void
     *
     * @throws Exception
     */
    public function install()
    {
        $this->model_extension_shipping_sameday->install();

        $this->load->model('setting/setting');
        $this->model_setting_setting->editSetting(
            $this->model_extension_shipping_sameday->getPrefix() . "sameday",
            [$this->model_extension_shipping_sameday->getKey('sameday_sync_until_ts') => time()]
        );

        $this->model_setting_setting->editSetting(
            $this->model_extension_shipping_sameday->getPrefix() . "sameday",
            [$this->model_extension_shipping_sameday->getKey('sameday_sync_lockers_ts') => 0]
        );
    }

    /**
     * @return void
     */
    public function uninstall()
    {
        $this->model_extension_shipping_sameday->uninstall();
    }

    /**
     * @return void
     *
     * @throws SamedaySDKException
     */
    public function index()
    {
        $this->load->language('extension/shipping/sameday');
        $this->document->setTitle($this->language->get('heading_title'));
        $this->load->model('setting/setting');
        $settingsModel = $this->model_extension_shipping_sameday;
        $post = $this->request->post;

        if ($this->request->server['REQUEST_METHOD'] === 'POST' && $this->validate()) {
            $post[$settingsModel->getKey('sameday_sync_until_ts')] = $this->getConfig('sameday_sync_until_ts');
            $post[$settingsModel->getKey('sameday_sync_lockers_ts')] = $this->getConfig('sameday_sync_lockers_ts');
            $post[$settingsModel->getKey('sameday_testing')] = $this->getConfig('sameday_testing');
            $post[$settingsModel->getKey('sameday_host_country')] = $this->getConfig('sameday_host_country')
                ?? $this->samedayHelper::API_HOST_LOCALE_RO
            ;

            if (null !== $this->testing && null !== $this->hostCountry) {
                $post[$this->model_extension_shipping_sameday->getKey('sameday_testing')] = $this->testing;
                $post[$this->model_extension_shipping_sameday->getKey('sameday_host_country')] = $this->hostCountry;
            }

            // Add custom sanitization for password
            $passKey = $this->model_extension_shipping_sameday->getKey('sameday_password');
            $password = $this->model_extension_shipping_sameday->sanitizeInput($_POST[$passKey]);
            if ('' === $password) {
                $password = $this->getConfig('sameday_password');
                if ('' === $password || null === $password) {
                    $this->session->data['error_warning'] = $this->language->get('error_username_password');

                    $this->response->redirect(
                        $this->url->link('extension/shipping/sameday', $this->addToken(), true)
                    );
                }
            }

            $this->request->post[$passKey] = $password;

            $this->model_setting_setting->editSetting(
                $this->model_extension_shipping_sameday->getPrefix() . "sameday",
                $this->request->post
            );

            $this->session->data['error_success'] = $this->language->get('text_success');

            $this->response->redirect(
                $this->url->link('extension/shipping/sameday', $this->addToken(), true)
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
            'entry_import_local_data',
            'entry_import_nomenclator',
            'entry_import_nomenclator_button',
            'entry_drop_down_list',
            'entry_interactive_map',

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
                'href' => $this->url->link('extension/shipping/sameday', $this->addToken(), true)
            )
        );

        $data['statuses'] = $this->getStatuses();
        $data['action'] = $this->url->link('extension/shipping/sameday', $this->addToken(), true);
        $data['cancel'] = $this->url->link(
            $this->getRouteExtension(),
            $this->addToken(array('type' => 'shipping')),
            true
        );
        $data['services'] = $this->displayServices();
        $data['import_local_data_actions'] = json_encode(self::IMPORT_LOCAL_DATA_ACTIONS, true);
        $data['import_local_data_href'] = $this->url->link(
            'extension/shipping/sameday/importLocalData',
            $this->addToken(),
            true
        );
        $data['import_geolocations'] = $this->url->link(
            'extension/shipping/sameday/importGeolocations',
            $this->addToken(),
            true
        );
        $data['get_all_counties'] = $this->url->link(
            'extension/shipping/sameday/get_all_counties',
            $this->addToken(),
            true
        );
        $data['service_refresh'] = $this->url->link(
            'extension/shipping/sameday/serviceRefresh',
            $this->addToken(),
            true
        );
        $data['pickupPoints'] = $this->model_extension_shipping_sameday->getPickupPoints();
        $data['lockers'] = $this->model_extension_shipping_sameday->getLockers();
        $data['pickupPoint_refresh'] = $this->url->link(
            'extension/shipping/sameday/pickupPointRefresh',
            $this->addToken(),
            true
        );
        $data['lockers_refresh'] = $this->url->link(
            'extension/shipping/sameday/lockersRefresh',
            $this->addToken(),
            true
        );
        $data['tax_classes'] = $this->model_localisation_tax_class->getTaxClasses();
        $data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();
        $data['service_links'] = array_map(
            function ($service) {
                return $this->url->link(
                    'extension/shipping/sameday/service',
                    $this->addToken(array('id' => $service['id'])),
                    true
                );
            },
            $data['services']
        );
        $data['url_cities_ajax'] = $this->url->link('extension/shipping/sameday/getCities', $this->addToken(), true);
        $data['url_deletePickupPoint'] = $this->url->link(
            'extension/shipping/sameday/deletePickupPoint',
            $this->addToken(),
            true
        );

        $data['pp_countries'] = SamedayHelper::SAMEDAY_COUNTRIES;

        $username = $this->getConfig('sameday_username');
        $password = $this->getConfig('sameday_password');

        if (!empty($username) && !empty($password)) {
            // Proceed with calling get_counties if the user is logged in
            $data['pp_counties'] = $this->getCounties();
        }

        $data['sameday_nomenclator_use'] = $this->getConfig('sameday_nomenclator_use');

        $data['shipping_sameday_nomenclator_use'] = $this->getConfig('sameday_nomenclator_use');

        $data = array_merge($data, $this->buildRequest());

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $data['pickupPointsTest'] = $this->url->link(
            'extension/shipping/sameday/sendNewPickupPoint',
            $this->addToken(),
            true
        );

        $this->response->setOutput($this->load->view('extension/shipping/sameday', $data));
    }

    /**
     * @return false|string
     */
    public function sendNewPickupPoint()
    {
        $this->load->language('extension/shipping/sameday');

        $actionKeys = $this->request->post['data'];

        $country = $actionKeys[0]['value'];
        $county = $actionKeys[1]['value'];
        $city = $actionKeys[2]['value'];
        $address = $actionKeys[3]['value'];
        $default = (int) $actionKeys[4]['value'];
        $postalCode = $actionKeys[5]['value'];
        $alias = $actionKeys[6]['value'];
        $fullname = $actionKeys[7]['value'];
        $phone = $actionKeys[8]['value'];

        $contact = [new PickupPointContactPersonObject($fullname, $phone, true)];

        try {
            $sameday = new Sameday($this->samedayHelper->initClient());
        } catch (Exception $exception) {
            $this->response->setOutput($exception->getMessage());
        }

        try {
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
        }

        return json_encode('ceva');
    }

    /**
     * @return array
     *
     * @throws SamedayBadRequestException
     * @throws SamedaySDKException
     * @throws \Sameday\Exceptions\SamedayAuthenticationException
     * @throws \Sameday\Exceptions\SamedayAuthorizationException
     * @throws \Sameday\Exceptions\SamedayServerException
     */
    public function getCounties(): array
    {
        try {
            $sameday = new Sameday($this->samedayHelper->initClient());
        } catch (Exception $exception) {
            var_dump($exception);
        }

        $counties = $sameday->getCounties(new \Sameday\Requests\SamedayGetCountiesRequest(''));
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
     * @throws \Sameday\Exceptions\SamedayAuthenticationException
     * @throws \Sameday\Exceptions\SamedayAuthorizationException
     * @throws \Sameday\Exceptions\SamedayServerException
     */
    public function getCities()
    {
        $id = $this->request->post['id'];
        try {
            $sameday = new Sameday($this->samedayHelper->initClient());
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
    public function deletePickupPoint()
    {
        $this->load->language('extension/shipping/sameday');
        $sameday_id = $this->request->post['id'];

        try {
            $sameday = new Sameday($this->samedayHelper->initClient());
        } catch (Exception $exception) {
            var_dump($exception);
        }

        try {
            $sameday->deletePickupPoint(new \Sameday\Requests\SamedayDeletePickupPointRequest($sameday_id));
            $pickupPoint = $this->model_extension_shipping_sameday->getPickupPointSameday($sameday_id);
            try {
                $this->model_extension_shipping_sameday->deletePickupPoint($pickupPoint['id']);
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
        $services = $this->model_extension_shipping_sameday->getServices();
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


        if (null !== $oohService) {
            $oohService['sameday_name'] = $samedayHelper::OOH_SERVICES_LABELS[$samedayHelper->getHostCountry()];
            $oohService['name'] = $samedayHelper::OOH_SERVICES_LABELS[$samedayHelper->getHostCountry()];
            $oohService['sameday_code'] = $samedayHelper::OOH_SERVICE_CODE;
            $oohService['column_ooh_label'] = $this->buildLanguage('column_ooh_label');

            $services = array_merge([$oohService], $services);
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
        return $this->getConfig('sameday_testing');
    }

    /**
     * @return void
     */
    public function importLocalData()
    {
        $action = $this->request->post['action'] ?? null;
        if (! in_array($action, self::IMPORT_LOCAL_DATA_ACTIONS, true)) {
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
        $this->model_extension_shipping_sameday->install();

        $sameday = new Sameday($this->samedayHelper->initClient());

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
                        $this->url->link('extension/shipping/sameday', $this->addToken(), true)
                    );
                } else {
                    throw new \RuntimeException($errorMessage);
                }
            }

            foreach ($lockers->getLockers() as $lockerObject) {
                $locker = $this->model_extension_shipping_sameday->getLockerSameday($lockerObject->getId());
                if (!$locker) {
                    $this->model_extension_shipping_sameday->addLocker($lockerObject, $this->isTesting());
                } else {
                    $this->model_extension_shipping_sameday->updateLocker($lockerObject, $locker['id']);
                }

                $remoteLockers[] = $lockerObject->getId();
            }
        } while ($page < $lockers->getPages());

        // Build array of local lockers.
        $localLockers = array_map(
            static function ($locker) {
                return array(
                    'id' => (int) $locker['id'],
                    'sameday_id' => (int) $locker['locker_id']
                );
            },
            $this->model_extension_shipping_sameday->getLockers($this->isTesting())
        );

        // Delete local lockers that aren't present in remote lockers anymore.
        foreach ($localLockers as $localLocker) {
            if (!in_array($localLocker['sameday_id'], $remoteLockers, true)) {
                $this->model_extension_shipping_sameday->deleteLocker($localLocker['id']);
            }
        }

        $this->updateLastSyncTimestamp();

        if ($redirectToPage) {
            $this->response->redirect($this->url->link(
                'extension/shipping/sameday',
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
        $this->model_extension_shipping_sameday->install();

        $sameday = new Sameday($this->samedayHelper->initClient());

        $samedayDBModel = $this->model_extension_shipping_sameday;

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
                        $this->url->link('extension/shipping/sameday', $this->addToken(), true)
                    );
                } else {
                    throw new \RuntimeException($errorMessage);
                }
            }

            foreach ($services->getServices() as $serviceObject) {
                $service = $this->model_extension_shipping_sameday->getServiceSameday(
                    $serviceObject->getId(),
                    $this->isTesting()
                );
                if (!$service) {
                    // Service not found, add it.
                    $this->model_extension_shipping_sameday->addService($serviceObject, $this->isTesting());
                } else {
                    // Service already exist, update it.
                    $this->model_extension_shipping_sameday->editService($service['id'], $serviceObject);

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
                    'id' => (int) $service['id'],
                    'sameday_id' => (int) $service['sameday_id']
                );
            },
            $this->model_extension_shipping_sameday->getServices()
        );

        // Delete local services that aren't present in remote services anymore.
        foreach ($localServices as $localService) {
            if (!in_array($localService['sameday_id'], $remoteServices, true)) {
                $this->model_extension_shipping_sameday->deleteService($localService['id']);
            }
        }

        // Update Pudo Service status to be same as LN
        if (null !== $lockerNextDayService) {
            $pudoService = $this->model_extension_shipping_sameday->getSamedayServiceByCode(
                $this->samedayHelper::SAMEDAY_PUDO_SERVICE,
                $this->isTesting()
            );

            $pudoService['status'] = $lockerNextDayService['status'];
            $this->model_extension_shipping_sameday->updateServiceStatus(
                $pudoService['id'],
                $lockerNextDayService['status']
            );
        }

        if ($redirectToPage) {
            $this->response->redirect($this->url->link('extension/shipping/sameday', $this->addToken(), true));
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
        $this->model_extension_shipping_sameday->install();

        $sameday = new Sameday($this->samedayHelper->initClient());

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
                        $this->url->link('extension/shipping/sameday', $this->addToken(), true)
                    );
                } else {
                    throw new \RuntimeException($errorMessage);
                }
            }

            foreach ($pickUpPoints->getPickupPoints() as $pickupPointObject) {
                $pickupPoint = $this->model_extension_shipping_sameday->getPickupPointSameday(
                    $pickupPointObject->getId()
                );
                if (!$pickupPoint) {
                    // Pickup point not found, add it.
                    $this->model_extension_shipping_sameday->addPickupPoint($pickupPointObject);
                } else {
                    $this->model_extension_shipping_sameday->updatePickupPoint($pickupPointObject, $pickupPoint['id']);
                }

                // Save as current pickup points.
                $remotePickupPoints[] = $pickupPointObject->getId();
            }
        } while ($page <= $pickUpPoints->getPages());

        // Build array of local pickup points.
        $localPickupPoints = array_map(
            static function ($pickupPoint) {
                return array(
                    'id' => (int) $pickupPoint['id'],
                    'sameday_id' => (int) $pickupPoint['sameday_id']
                );
            },
            $this->model_extension_shipping_sameday->getPickupPoints($this->isTesting())
        );

        // Delete local pickup points that aren't present in remote pickup points anymore.
        foreach ($localPickupPoints as $localPickupPoint) {
            if (!in_array($localPickupPoint['sameday_id'], $remotePickupPoints, true)) {
                $this->model_extension_shipping_sameday->deletePickupPoint($localPickupPoint['id']);
            }
        }

        if ($redirectToPage) {
            $this->response->redirect(
                $this->url->link('extension/shipping/sameday', $this->addToken(), true)
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
        $code = $this->model_extension_shipping_sameday->getKey('sameday');
        $key =  $this->model_extension_shipping_sameday->getKey('sameday_sync_lockers_ts');

        $time = time();

        $lastTimeSynced = $this->getConfig('sameday_sync_lockers_ts');

        if ($lastTimeSynced === null) {
            $value = $time;

            $this->db->query(
                sprintf(
                    "INSERT INTO %s SET `store_id` = '%s', `code` = '%s', `key` = '%s', `value` = '%s'",
                    DB_PREFIX . "setting",
                    $store_id,
                    $this->db->escape($code),
                    $this->db->escape($key),
                    $this->db->escape($value)
                )
            );
        }

        $lastTs = $this->db->query(
            sprintf(
                "SELECT * FROM %s WHERE `store_id` = '%s' AND `code` = '%s' AND `key` = '%s' AND `value` = '%s'",
                DB_PREFIX . "setting",
                $store_id,
                $this->db->escape($code),
                $this->db->escape($key),
                $this->db->escape($value)
            )
        )->row;

        $this->db->query(
            sprintf(
                "UPDATE %s SET `value` = '%s' WHERE `setting_id` = '%'",
                DB_PREFIX . "setting",
                $this->db->escape($time),
                $this->db->escape($lastTs['setting_id'])
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
        $service = $this->model_extension_shipping_sameday->getService($this->request->get['id']);

        $this->load->language('extension/shipping/sameday');
        $this->document->setTitle($this->language->get('heading_title_service'));
        $this->load->model('setting/setting');

        if ($this->request->server['REQUEST_METHOD'] === 'POST' && $this->validatePermissions()) {
            if (null === $this->request->post['name'] ?? null) {
                $this->request->post['name'] = $this->samedayHelper::OOH_SERVICES_LABELS[
                    $this->samedayHelper->getHostCountry()
                ];
            }

            $this->model_extension_shipping_sameday->updateService($service['id'], $this->request->post);

            // Update Pudo Service status to be same as LN
            if ($service['sameday_code'] === $this->samedayHelper::LOCKER_NEXT_DAY_SERVICE) {
                $pudoService = $this->model_extension_shipping_sameday->getSamedayServiceByCode(
                    $this->samedayHelper::SAMEDAY_PUDO_SERVICE,
                    $this->isTesting()
                );

                if (!empty($pudoService)) {
                    $this->model_extension_shipping_sameday->updateServiceStatus(
                        $pudoService['id'],
                        $this->request->post['status']
                    );
                }
            }

            $this->session->data['error_success'] = $this->language->get('text_success');

            $this->response->redirect($this->url->link(
                'extension/shipping/sameday/service',
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
                'href' => $this->url->link('extension/shipping/sameday', $this->addToken(), true)
            ),
            array(
                'text' => $this->language->get('heading_title_service'),
                'href' => $this->url->link(
                    'extension/shipping/sameday/service',
                    $this->addToken(array('id' => $service['id'])),
                    true
                )
            )
        );

        $data['action'] = $this->url->link(
            'extension/shipping/sameday/service',
            $this->addToken(array('id' => $service['id'])),
            true
        );
        $data['cancel'] = $this->url->link('extension/shipping/sameday', $this->addToken(), true);

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

        $this->response->setOutput($this->load->view('extension/shipping/sameday_service', $data));
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

        $this->load->language('extension/shipping/sameday');
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
                'extension/shipping/sameday/addAwb',
                $this->addToken(array('order_id' => $orderInfo['order_id'])),
                true
            ),
            'buttonShowAwbPdf' => $this->url->link(
                'extension/shipping/sameday/showAsPdf',
                $this->addToken(array('order_id' => $orderInfo['order_id'])),
                true
            ),
            'buttonShowAwbHistory' => $this->url->link(
                'extension/shipping/sameday/showAwbHistory',
                $this->addToken(array('order_id' => $orderInfo['order_id'])),
                true
            ),
            'buttonDeleteAwbLink' => $this->url->link(
                'extension/shipping/sameday/deleteAwb',
                $this->addToken(array('order_id' => $orderInfo['order_id'])),
                true
            )
        );

        $awb = $this->model_extension_shipping_sameday->getAwbForOrderId($orderInfo['order_id']);

        if ($awb) {
            $data['awb_number'] = $awb['awb_number'];
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
        $this->load->language('extension/shipping/sameday');
        $this->document->setTitle($this->language->get('heading_title_add_awb'));
        $this->load->model('sale/order');

        if (!isset($this->request->get['order_id'])
            || !($orderInfo = $this->model_sale_order->getOrder($this->request->get['order_id']))
        ) {
            return new Action('error/not_found');
        }

        $shippingSamedayModel = $this->model_extension_shipping_sameday;

        $awb = $shippingSamedayModel->getAwbForOrderId($orderInfo['order_id']);

        if ($awb) {
            // Already generated.
            $this->response->redirect(
                $this->url->link(
                    'sale/order/info',
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

        $showPDO = $this->toggleHtmlElement(false);

        if (null !== $locationId = $parts[3] ?? null) {
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
                    (int) ($data['default_service_id'] ?? null),
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
                (int) $postRequestData['sameday_service'],
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
                $shippingSamedayModel->saveAwb(array(
                    'order_id' => $orderInfo['order_id'],
                    'awb_number' => $awb->getAwbNumber(),
                    'parcels' => serialize($awb->getParcels()),
                    'awb_cost' =>  $awb->getCost()
                ));

                $shippingSamedayModel->updateShippingMethodAfterPostAwb(
                    $orderInfo['order_id'],
                    $service,
                    $postRequestData['sameday_locker_id'],
                    $postRequestData['sameday_locker_address']
                );

                // Redirect to order page.
                $this->response->redirect(
                    $this->url->link(
                        'sale/order/info',
                        $this->addToken(array('order_id' => $orderInfo['order_id'])),
                        true
                    )
                );
            } elseif (null !== $errors = $postAwb['errors'] ?? null) {
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
        if ($orderInfo['payment_code'] === $this->samedayHelper::CASH_ON_DELIVERY_CODE) {
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
                    'sale/order/info',
                    $this->addToken(array('order_id' => $orderInfo['order_id'])),
                    true
                )
            ),
            array(
                'text' => $this->language->get('text_add_awb'),
                'href' => $this->url->link(
                    'extension/shipping/sameday/createAwb',
                    $this->addToken(array('order_id' => $orderInfo['order_id'])),
                    true
                )
            )
        );

        /*
        * Actions
        */
        $data['estimate_cost_href'] = $this->url->link('extension/shipping/sameday/estimateCost');
        $data['action'] = $this->url->link(
            'extension/shipping/sameday/addAwb',
            $this->addToken(array('order_id' => $orderInfo['order_id'])),
            true
        );
        $data['cancel'] = $this->url->link(
            'sale/order/info',
            $this->addToken(array('order_id' => $orderInfo['order_id'])),
            true
        );

        /*
         * Main Layout
         */
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/shipping/sameday_add_awb', $data));
    }

    /**
     * @return void
     *
     * @throws JsonException
     */
    public function importGeolocations()
    {
        $countiesFile = DIR_SYSTEM . 'library/sameday-classes/utils/counties.json';
        $countiesData = json_decode(file_get_contents($countiesFile));
        $this->load->model('extension/shipping/sameday');

        foreach ($countiesData as $county) {
            $country_id = $this->model_extension_shipping_sameday->getCountryByCode($county->country_code);
            $this->model_extension_shipping_sameday->addZoneCounty((int) $country_id, (string) $county);
        }

        if ($this->model_extension_shipping_sameday->citiesCheck() === false) {
            $this->model_extension_shipping_sameday->createCitiesTable();
        }

        try {
            $citiesData = json_decode(file_get_contents(DIR_SYSTEM . 'library/sameday-classes/utils/cities.json'));
            $action = $this->model_extension_shipping_sameday;
            $action->truncateNomenclator();
            foreach ($citiesData as $city) {
                $countryId = $this->model_extension_shipping_sameday->getZone($city->country_code)['country_id'];
                $zoneId = $this->model_extension_shipping_sameday->getZoneId($countryId, $city->county_code);
                if ($zoneId !== null) {
                    $action->addCity($city, $zoneId);
                }
            }
        } catch (Exception $exception) {
            return;
        }
    }

    /**
     * @return void|Action
     *
     * @throws \Sameday\Exceptions\SamedayOtherException
     * @throws SamedaySDKException
     * @throws \Sameday\Exceptions\SamedayServerException
     * @throws \Sameday\Exceptions\SamedayAuthenticationException
     * @throws \Sameday\Exceptions\SamedayAuthorizationException
     * @throws \Sameday\Exceptions\SamedayNotFoundException
     */
    public function showAwbHistory()
    {
        $this->load->language('extension/shipping/sameday');
        $this->document->setTitle($this->language->get('heading_title_awb_history'));

        $awb = $this->model_extension_shipping_sameday->getAwbForOrderId($this->request->get['order_id']);

        if (!$awb) {
            return new Action('error/not_found');
        }

        $orderId = (int) $awb['order_id'];

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
                    'sale/order/info',
                    $this->addToken(array('order_id' => $orderId)),
                    true
                )
            ),
            array(
                'text' => 'AWB',
                'href' => $this->url->link(
                    'extension/shipping/sameday/showAwbStatus',
                    $this->addToken(array('order_id' => $orderId)),
                    true
                )
            )
        );

        /*
         * Actions
         */
        $data['action'] = $this->url->link('extension/shipping/sameday', $this->addToken(), true);
        $data['cancel'] = $this->url->link(
            'sale/order/info',
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
            $this->response->setOutput($this->load->view('extension/shipping/sameday_awb_history_status', $data));

            return null;
        }

        // Build ajax html.
        $sameday = new Sameday($this->samedayHelper->initClient());

        /** @var ParcelObject[] $parcels */
        $parcels = unserialize($awb['parcels'], ['']);
        foreach ($parcels as $parcel) {
            $parcelStatus = $sameday->getParcelStatusHistory(
                new SamedayGetParcelStatusHistoryRequest(
                    $parcel->getAwbNumber()
                )
            );
            $this->model_extension_shipping_sameday->refreshPackageHistory(
                $awb['order_id'],
                $parcel->getAwbNumber(),
                $parcelStatus->getSummary(),
                $parcelStatus->getHistory(),
                $parcelStatus->getExpeditionStatus()
            );
        }

        $data['packages'] = $this->model_extension_shipping_sameday->getPackagesForOrderId($awb['order_id']);

        $this->response->setOutput($this->load->view('extension/shipping/sameday_awb_history_status_refresh', $data));
    }

    /**
     * @return Action|void
     *
     * @throws SamedayBadRequestException
     * @throws SamedaySDKException
     * @throws \Sameday\Exceptions\SamedayAuthenticationException
     * @throws \Sameday\Exceptions\SamedayAuthorizationException
     * @throws \Sameday\Exceptions\SamedayNotFoundException
     * @throws \Sameday\Exceptions\SamedayServerException
     */
    public function showAsPdf()
    {
        $orderId = (int) $this->request->get['order_id'];
        $awb = $this->model_extension_shipping_sameday->getAwbForOrderId($orderId);

        if (!$awb) {
            return new Action('error/not_found');
        }

        header('Content-type: application/pdf');
        header("Cache-Control: no-cache");
        header("Pragma: no-cache");

        $sameday = new Sameday($this->samedayHelper->initClient());

        $content = $sameday->getAwbPdf(
            new SamedayGetAwbPdfRequest($awb['awb_number'], new AwbPdfType(AwbPdfType::A4))
        );

        $this->response->setOutput($content->getPdf());

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
            (int) ($params['sameday_pickup_point'] ?? null),
            null,
            new PackageType($params['sameday_package_type']),
            $parcelDimensions,
            (int) ($params['sameday_service'] ?? null),
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
            $sameday = new Sameday($this->samedayHelper->initClient());
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
        $totalWeight = 0 ;
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
        $this->load->language('extension/shipping/sameday');

        $params = $this->request->post;
        $order_id = $params['order_id'];

        $orderInfo = $this->model_sale_order->getOrder($order_id);

        if (!strlen($params['sameday_insured_value'])) {
            $return['errors'][] = $this->language->get('error_insured_value_cost');
        }

        $parcelDimensions = [];
        foreach ($params['sameday_package_weight'] as $k => $weight) {
            if (!strlen($weight) || $weight < 1) {
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

        if (! isset($return['errors'])) {
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

            $sameday = new Sameday($this->samedayHelper->initClient());
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

        $items = $this->model_sale_order->getOrderProducts($orderId);

        foreach ($items as $item => $value) {
            $items[$item]['product_info'] = $this->model_catalog_product->getProduct($value['product_id']);
        }

        return $items;
    }

    /**
     * @return void
     *
     * @throws SamedaySDKException
     */
    public function deleteAwb()
    {
        $orderId = $this->request->get['order_id'];
        $awb = $this->model_extension_shipping_sameday->getAwbForOrderId($orderId);
        $sameday = new Sameday($this->samedayHelper->initClient());

        if ($awb) {
            try {
                $sameday->deleteAwb(new SamedayDeleteAwbRequest($awb['awb_number']));
                $this->model_extension_shipping_sameday->deleteAwb($awb['awb_number']);
            } catch (\Exception $e) {
                $this->response->setOutput(json_encode($e->getMessage()));
            }
        }

        $this->response->redirect($this->url->link(
            'sale/order/info',
            $this->addToken(array('order_id' => $orderId)),
            true
        ));
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
        if (!$this->user->hasPermission('modify', 'extension/shipping/sameday')) {
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
        if ($this->request->post[$this->model_extension_shipping_sameday->getKey('sameday_username')] !== $username) {
            // Username changed.
            $username = $this->request->post[$this->model_extension_shipping_sameday->getKey('sameday_username')];
            $needLogin = true;
        }

        $password = $this->getConfig('sameday_password');
        $newPassword = $this->model_extension_shipping_sameday->sanitizeInput(
            $_POST[$this->model_extension_shipping_sameday->getKey('sameday_password')]
        );
        if ('' !== $newPassword) {
            // Password updated.
            $password = $newPassword;
            $needLogin = true;
        }

        if ($needLogin) {
            // Check if login is valid.
            $isLogged = false;
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
                            $isTesting = (int) ($this->samedayHelper::API_DEMO === $key);
                            $this->testing = $isTesting;
                            $this->hostCountry = $hostCountry;
                            $isLogged = true;

                            break;
                        }
                    } catch (Exception $exception) {
                        continue;
                    }
                }
            }

            if (!$isLogged) {
                $this->error['warning'] = $this->language->get('error_username_password');

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
        $keys = self::SAMEDAY_CONFIGS;
        foreach ($keys as $key => $value) {
            $requestKey = sprintf("%ssameday_%s", $this->model_extension_shipping_sameday->getPrefix(), $key);
            if ('' === $valueOfKey = ($this->getConfig("sameday_$key") ?? '')) {
                $valueOfKey = $value;
            }

            $entries["sameday_$key"] = $this->request->post[$requestKey] ?? $valueOfKey;
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
                $entries[$key] = $this->samedayHelper::OOH_SERVICES_LABELS[$this->samedayHelper->getHostCountry()];
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
        return $this->model_extension_shipping_sameday->getConfig($key);
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
        return $isShow === true ? self::TOGGLE_HTML_ELEMENT['show'] : self::TOGGLE_HTML_ELEMENT['hide'];
    }
    // End of file
}
