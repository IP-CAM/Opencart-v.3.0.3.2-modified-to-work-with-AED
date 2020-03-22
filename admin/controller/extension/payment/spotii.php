<?php
class ControllerExtensionPaymentSpotii extends Controller
{
    private $error = array();

    public function index(){
        $this->language->load('extension/payment/spotii');
        $this->document->setTitle('Spotii Payment Method Configuration');
        $this->load->model('setting/setting');
        // $this->model_payment_spotii->install();

        if (($this->request->server['REQUEST_METHOD'] == 'POST')) {
            $this->model_setting_setting->editSetting('payment_spotii', $this->request->post);
            $this->session->data['success'] = 'Saved.';
 

            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true));
        }

        // $data['heading_title'] = $this->language->get('heading_title');
        // $data['entry_text_config_one'] = $this->language->get('text_config_one');
        // $data['entry_text_config_two'] = $this->language->get('text_config_two');
        // $data['button_save'] = $this->language->get('text_button_save');
        // $data['button_cancel'] = $this->language->get('text_button_cancel');
        // $data['entry_order_status'] = $this->language->get('entry_order_status');
        // $data['text_enabled'] = $this->language->get('text_enabled');
        // $data['text_disabled'] = $this->language->get('text_disabled');
        // $data['entry_status'] = $this->language->get('entry_status');

        //Set the data for the breadcrumbs
        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/payment/spotii', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['action'] = $this->url->link('extension/payment/spotii', 'user_token=' . $this->session->data['user_token'], true);
        
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true);

        if (isset($this->request->post['payment_spotii_merchant_private_key'])) {
            $data['payment_spotii_merchant_private_key'] = $this->request->post['payment_spotii_merchant_private_key'];
        } else {
            $data['payment_spotii_merchant_private_key'] = $this->config->get('payment_spotii_merchant_private_key');
        }

        if (isset($this->request->post['payment_spotii_spotii_public_key'])) {
            $data['payment_spotii_spotii_public_key'] = $this->request->post['payment_spotii_spotii_public_key'];
        } else {
            $data['payment_spotii_spotii_public_key'] = $this->config->get('payment_spotii_spotii_public_key');
        }

        if (isset($this->request->post['payment_spotii_order_status_id'])) {
            $data['payment_spotii_order_status_id'] = $this->request->post['payment_spotii_order_status_id'];
        } else {
            $data['payment_spotii_order_status_id'] = $this->config->get('payment_spotii_order_status_id');
        }


        if (isset($this->request->post['payment_spotii_status'])) {
            $data['payment_spotii_status'] = $this->request->post['payment_spotii_status'];
        } else {
            $data['payment_spotii_status'] = $this->config->get('payment_spotii_status');
        }

        $this->load->model('localisation/order_status');

        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

        if (isset($this->request->post['payment_spotii_sort_order'])) {
            $data['payment_spotii_sort_order'] = $this->request->post['payment_spotii_sort_order'];
        } else {
            $data['payment_spotii_sort_order'] = $this->config->get('payment_spotii_sort_order');
        }

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        


        $this->response->setOutput($this->load->view('extension/payment/spotii', $data));
    }

    public function refund()
    {
        $this->load->language('extension/payment/spotii');
        $json = array();

        if (isset($this->request->post['order_id']) && !empty($this->request->post['order_id'])) {
            $this->load->model('extension/payment/spotii');

            $spotii_order = $this->model_extension_payment_spotii->getOrder($this->request->post['order_id']);
            $refund_response = $this->model_extension_payment_spotii->refund($this->request->post['order_id'], $this->request->post['amount']);

            if ($refund_response['status'] == 'success') {
                $this->model_extension_payment_spotii->addTransaction($spotii_order['spotii_order_id'], 'refund', $this->request->post['amount'] * -1);

                $total_refunded = $this->model_extension_payment_spotti->getTotalRefunded($spotii_order['spotii_order_id']);
                $total_released = $this->model_extension_payment_spotii->getTotalReleased($spotii_order['spotii_order_id']);

                $this->model_extension_payment_spotii->updateRefundStatus($spotii_order['spotii_order_id'], 1);

                $json['msg'] = $this->language->get('text_refund_ok_order');
                $json['data'] = array();
                $json['data']['created'] = date("Y-m-d H:i:s");
                $json['data']['amount'] = $this->currency->format(($this->request->post['amount'] * -1), $spotii_order['currency_code'], false);
                $json['data']['total_released'] = $this->currency->format($total_released, $spotii_order['currency_code'], false);
                $json['data']['total_refund'] = $this->currency->format($total_refunded, $spotii_order['currency_code'], false);
                $json['data']['refund_status'] = 1;
                $json['error'] = false;
            } else {
                $json['error'] = true;
                $json['msg'] = isset($refund_response['message']) && !empty($refund_response['message']) ? (string) $refund_response['message'] : 'Unable to refund';
            }
        } else {
            $json['error'] = true;
            $json['msg'] = 'Missing data';
        }

        $this->response->setOutput(json_encode($json));
    }


    public function install()
    {
        $this->load->model('extension/payment/spotii');
        $this->model_extension_payment_spotii->install();
    }

    public function uninstall()
    {
        $this->load->model('extension/payment/spotii');
        $this->model_extension_payment_spotii->uninstall();
    }

    
}
