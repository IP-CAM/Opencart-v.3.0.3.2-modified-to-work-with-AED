<?php

class ModelExtensionPaymentCustom extends Model
{

    public function getMethod($address, $total)
    {
        $this->load->language('extension/payment/custom');
        $hisham = "hisham";

        $method_data = array(
            'code'     => 'custom',
            'title'    => $this->language->get('text_title'),
            'sort_order' => $this->config->get('payment_custom_sort_order')
        );

        return $method_data;
    }
}

