<?php
class ControllerExtensionPaymentCustom extends Controller
{
    private $error = array();

    public function index(){
        $this->language->load('extension/payment/custom');
        $this->document->setTitle('Custom Payment Method Configuration');
        $this->load->model('setting/setting');
        // $this->model_payment_custom->install();

        if (($this->request->server['REQUEST_METHOD'] == 'POST')) {
            $this->model_setting_setting->editSetting('payment_custom', $this->request->post);
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
            'href' => $this->url->link('extension/payment/custom', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['action'] = $this->url->link('extension/payment/custom', 'user_token=' . $this->session->data['user_token'], true);
        
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true);

        if (isset($this->request->post['payment_custom_merchant_private_key'])) {
            $data['payment_custom_merchant_private_key'] = $this->request->post['payment_custom_merchant_private_key'];
        } else {
            $data['payment_custom_merchant_private_key'] = $this->config->get('payment_custom_merchant_private_key');
        }

        if (isset($this->request->post['payment_custom_custom_public_key'])) {
            $data['payment_custom_custom_public_key'] = $this->request->post['payment_custom_custom_public_key'];
        } else {
            $data['payment_custom_custom_public_key'] = $this->config->get('payment_custom_custom_public_key');
        }


        if (isset($this->request->post['payment_custom_status'])) {
            $data['payment_custom_status'] = $this->request->post['payment_custom_status'];
        } else {
            $data['payment_custom_status'] = $this->config->get('payment_custom_status');
        }

//Not sure about this code
        // $this->load->model('localisation/order_status');

        // $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

//Not sure about this code

        if (isset($this->request->post['payment_custom_sort_order'])) {
            $data['payment_custom_sort_order'] = $this->request->post['payment_custom_sort_order'];
        } else {
            $data['payment_custom_sort_order'] = $this->config->get('payment_custom_sort_order');
        }

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        


        $this->response->setOutput($this->load->view('extension/payment/custom', $data));
    }

    
}
