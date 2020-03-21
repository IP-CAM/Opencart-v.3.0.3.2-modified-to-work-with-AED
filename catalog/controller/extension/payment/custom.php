<?php
class ControllerExtensionPaymentCustom extends Controller{
    private $token = "";

    private function sendCurl($url, $body)
    {
        //Set up the Header
        $header = array();
        $header[] = 'Accept: application/json';
        $header[] = 'Content-type: application/json';
        $header[] = 'Access-Control-Allow-Origin: *';
        if ($this->token!=''){
            $header[] = 'Authorization: Bearer ' . $this->token;
            $this->log->write("Token: ".$this->token);
        }
        //Start the CURL
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        //curl_setopt($curl, CURLOPT_PORT, 443);
        //curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
        //curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
        //curl_setopt($curl, CURLOPT_USERAGENT, $this->request->server['HTTP_USER_AGENT']);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $body); //JSON body
        curl_setopt($curl, CURLOPT_HEADER, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($curl);
        curl_close($curl);
        list($other, $responseBody) = explode("\r\n\r\n", $response, 2);
        $other = preg_split("/\r\n|\n|\r/", $other);
        list($protocol, $code, $text) = explode(' ', trim(array_shift($other)), 3);
        return array('status' => (int) $code, 'ResponseBody' => $responseBody);
    }

    public function index(){
        $this->load->model('extension/payment/custom');
        //Here we get the authentication token using a custom post
        $url = 'https://auth.sandbox.spotii.me/api/v1.0/merchant/authentication/';
        // This contains the keys required to obtain the auth token
        $body = array(
            'public_key' => $this->config->get('payment_custom_custom_public_key'),
            'private_key' => $this->config->get('payment_custom_merchant_private_key')
        );
        $body = json_encode($body, JSON_UNESCAPED_UNICODE);
        //Use the sendCurl function
        $response = $this->sendCurl($url, $body);
        if ($response === FALSE) { /* Handle error */
        }
        $response_body = $response['ResponseBody'];
        
        $response_body_arr = json_decode($response_body, true);
        if (array_key_exists('token', $response_body_arr)) {
            $this->token = $response_body_arr['token'];
        } else {
            error_log("Error on authentication: " . $response_body);
        }

        //Now we prepare the body to obtain the checkout URL
        $this->load->model('checkout/order');
        $this->load->model('catalog/product');
        $order_id = $this->session->data['order_id'];
        $order_info = $this->model_checkout_order->getOrder($order_id);
        $this->log->write("Checkout OrderID :".$order_id);
        $body2 = array(
            "reference" => $order_id,
            "display_reference" => $order_id,
            "description" => "Order #" . $order_id,
            "total" => 240,
            // "total" => $order_info['total'],
            "currency" => $order_info['currency_code'],
            "confirm_callback_url" => $this->url->link('extension/payment/custom/callback', true),
            "reject_callback_url" => $this->url->link('checkout/checkout', '', true),

            // Order
            "order" => array(
                "tax_amount" => 0, // Need to check this
                "shipping_amount" => 0, //Need to check this
                "discount" => 0,
                "customer" => array(
                    "first_name" => $order_info['firstname'],
                    "last_name" => $order_info['lastname'],
                    "email" => $order_info['email'],
                    "phone" => $order_info['telephone'],
                ),

                "billing_address" => array(
                    "title" => "",
                    "first_name" => $order_info['payment_firstname'],
                    "last_name" => $order_info['payment_lastname'],
                    "line1" => $order_info['payment_address_1'],
                    "line2" => $order_info['payment_address_2'],
                    "line3" => "",
                    "line4" => $order_info['payment_city'],
                    "state" => "",
                    "postcode" => $order_info['payment_postcode'],
                    "country" => 'AE',
                    "phone" => $order_info['telephone']
                ),

                "shipping_address" => array(
                    "title" => "",
                    "first_name" => $order_info['shipping_firstname'],
                    "last_name" => $order_info['shipping_lastname'],
                    "line1" => $order_info['shipping_address_1'],
                    "line2" => $order_info['shipping_address_2'],
                    // "line3" => "",
                    // "line4" => $order->get_shipping_city(),
                    // "state" => $order->get_shipping_state(),
                    // "postcode" => $order->get_shipping_postcode(),
                    // "country" => $order->get_shipping_country(),
                    // "phone" => $order->get_billing_phone(),     //-------
                )
            )
        );
        $order = $this->model_checkout_order->getOrderProducts($order_id);
        foreach ($order as $item) {
            $product = $this->model_catalog_product->getProduct($item["product_id"]);
            $lines[] = array(
                "sku" => 1,
                "reference" => $item["product_id"],
                "title" => $product["name"],
                "upc" => $product["sku"],
                "quantity" => $item["quantity"],
                "price" => $product["price"],
                "currency" => $order_info["currency_value"],
                "image_url" => "",
            );
        }

        $body2['order']['lines'] = $lines;
        $url = "https://api.sandbox.spotii.me/api/v1.0/checkouts/";
        
        $body2 = json_encode($body2, JSON_UNESCAPED_UNICODE);
        //$this->log->write("Checkout Body-JSON: ".$body2);
        $response2 = $this->sendCurl($url, $body2);
        if ($response2 === FALSE) { /* Handle error */
        }
        $response_body2 = $response2['ResponseBody'];
        $index = strpos($response_body2, '{');
        $json_body = substr($response_body2, $index);
        $response_body_arr2 = json_decode($json_body, true);
        //$this->log->write("Response: " . ($response_body_arr2));
        if (array_key_exists('checkout_url', $response_body_arr2)) {  
            $checkout_url = $response_body_arr2['checkout_url'];
            $checkout_url = ''.$checkout_url;
            $data['action'] = $checkout_url;
        } else {
            error_log("Error on Checkout: " . $response_body2);
        }
        $temp = explode("?", $checkout_url, 2);
        $temp = explode("=", $temp[1], 2);
        $form_param['token'] = $temp[1];
        $data['form_params'] = $form_param;
        $data['button_confirm'] = $this->language->get('button_confirm');
        $data['currency'] = $order_info['currency_code'];
        $data['total'] = number_format($order_info['total'], 2, '.', '');
        $data['installment'] = number_format($order_info['total']/4.00, 2, '.', '');
        return $this->load->view('extension/payment/custom', $data);
    }


    public function callback()    {
        $order_data = $this->session->data;
        $order_id = $order_data['order_id'];
        $this->log->write("CallBack :".$order_id);
        $body = array();
        $body = json_encode($body, JSON_UNESCAPED_UNICODE);
        $url = 'https://api.sandbox.spotii.me/api/v1.0/orders/'.$order_id.'/capture';
        $response = $this->sendCurl($url, $body);
        $this->log->write($response);
    }
}

https://api.spotii.me/api/v1.0/orders/
