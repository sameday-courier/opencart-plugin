<?php
trait SamedayTraitCatalogController {
    private \SamedayVersionValidator $samedayVersionValidator;

    public function __construct($registry){
        parent::__construct($registry);
        $this->samedayVersionValidator = Samedayclasses::getSamedayVersionValidator();
    }

    /**
     * Save selected locker ID to session (called via AJAX from locker map subscribe callback).
     *
     * @return void
     */
    public function saveLocker(): void {
        $json = [];

        if (isset($this->request->post['locker_id']) && isset($this->request->post['locker_address'])) {
            $this->session->data['locker_id'] = $this->request->post['locker_id'];
            $this->session->data['locker_address'] = $this->request->post['locker_address'];

            $json['success'] = true;
            $json['locker_id'] = $this->session->data['locker_id'];
            $json['locker_address'] = $this->request->post['locker_address'];
        } else {
            $json['error'] = 'Locker ID missing';
        }
    }

    /**
     * After order model addOrder: persist sameday_locker_id to oc_sameday_orders_locker (event: model/checkout/order.addOrder/after).
     *
     * @param string $route
     * @param array  $args
     * @param mixed  $output
     * @return void
     */
    public function order_model_after(string &$route, array &$args, &$output): void {
        $locker = [
            'lockerId' => isset($this->session->data['locker_id']) ? (int) $this->session->data['locker_id'] : null,
            'lockerAddress' => isset($this->session->data['locker_address']) ? (string) $this->session->data['locker_address'] : null
        ];
        $order_id = isset($this->session->data['order_id']) ? (int) $this->session->data['order_id'] : null;
        if ($locker == null || $order_id == null) {
            return;
        }
        $this->load->model($this->samedayVersionValidator->buildModelPath());
        $this->{$this->samedayVersionValidator->buildMagicMethod()}->addOrderLocker($order_id, $locker);
        unset(
            $this->session->data['order_id'],
            $this->session->data['locker_id'],
            $this->session->data['sameday_locker_id'],
            $this->session->data['sameday_locker_name'],
            $this->session->data['locker_address']
        );
    }

    public function order_add_after(string &$route, array &$args, &$output): void {
        $this->session->data['order_id'] = (int) $output;
    }

    /**
     * Add Sameday locker and payment scripts on checkout (called via event catalog/controller/common/footer/before).
     *
     * @param string $route  Controller route being invoked (e.g. common/footer)
     * @param array  $args  Controller arguments
     * @return void
     */
    public function checkout_scripts_before(string &$route, array &$args): void {
        $main_route = $this->request->get['route'] ?? '';
        if (strpos($main_route, 'checkout') !== 0) {
            return;
        }

        if (!$this->config->get('shipping_sameday_status')) {
            return;
        }

        $this->document->addScript('https://cdn.sameday.ro/locker-plugin/lockerpluginsdk.js', 'footer');
        $this->document->addScript('extension/sameday/catalog/view/javascript/sameday/assets/sameday-locker.js?v='. time(), 'footer');
        $this->document->addScript('extension/sameday/catalog/view/javascript/sameday/assets/update-payment.js', 'footer');
    }

    /**
     * Inject script to add class "paymentMethod" to payment radios when modal is shown (replaces OC3 OCMOD payment_method.twig operations).
     * Event: catalog/view/checkout/payment_method/after
     *
     * @param string $route
     * @param array  $data
     * @param string $output
     * @return void
     */
    public function payment_method_view_after(string &$route, array &$data, string &$output): void {
        if (!$this->config->get('shipping_sameday_status')) {
            return;
        }

        $output .= '
            <script>
                (function(){
                    $(document).on("shown.bs.modal","#modal-payment",function(){
                        $("#modal-payment input[name=\'payment_method\']").addClass("paymentMethod");
                    });
                })();
            </script>
        ';
    }
}