<?php

class ModelExtensionPaymentSpotii extends Model
{


    public function getMethod($address, $total)
    {
        $this->load->language('extension/payment/spotii');
        if ($this->config->get('payment_spotii_total') > 0 && $this->config->get('payment_spotii_total') > $total) {
            $status = false;
        } else {
            $status = true;
        }
        $method_data = array();
        //If the order meets the minimum size

        //Using this for development - remove for deploy
        if($status){
            $method_data = array(
                'code'     => 'spotii',
                'title'    => $this->language->get('text_title'),
                'sort_order' => $this->config->get('payment_spotii_sort_order')
        );
    }
        return $method_data;
    }

    //For REFUND capability
    // public function addOrder($order_info)
    // {
    //     $this->db->query("INSERT INTO `" . DB_PREFIX . "spotii_order` SET `order_id` = '" . (int) $order_info['order_id'] . "', `order_code` = '" . (int) $order_info['order_id'] . "', `date_added` = now(), `date_modified` = now(), `currency_code` = '" . $this->db->escape($order_info['currency_code']) . "', `total` = '" . $this->currency->format($order_info['total'], $order_info['currency_code'], false, false) . "'");

    //     return $this->db->getLastId();
    // }


}

