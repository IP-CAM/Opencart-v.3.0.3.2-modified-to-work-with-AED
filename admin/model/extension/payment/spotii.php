<?php

class ModelExtensionPaymentSpotii extends Model
{
    
    public function validateKeys($data){
        $auth_url =  $data['payment_spotii_test'] == "sandbox" ? 'https://auth.sandbox.spotii.me/api/v1.0/merchant/authentication/' : 'https://auth.spotii.me/api/v1.0/merchant/authentication/';
        $body = array(
            'public_key' => $data['payment_spotii_spotii_public_key'],
            'private_key' => $data['payment_spotii_merchant_private_key']
        );
        $body = json_encode($body, JSON_UNESCAPED_UNICODE);
        //Use the sendCurl function
        $resp = $this->sendCurl($auth_url, $body);
        $authorised = false;
        if($resp['status'] == 200) return true; //we got our token back
        return false; // else our keys are invalid
    }

    private function sendCurl($url, $body)
    {
        $header = array();
        $header[] = 'Accept: application/json';
        $header[] = 'Content-type: application/json';

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        curl_setopt($curl, CURLOPT_PORT, 443);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($curl, CURLOPT_USERAGENT, $this->request->server['HTTP_USER_AGENT']);
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

    public function logger($message)
    {
        if ($this->config->get('payment_spotii_debug') == 1) {
            $log = new Log('spotii.log');
            $log->write($message);
        }
    }
}
