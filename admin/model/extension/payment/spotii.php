<?php

class ModelExtensionPaymentSpotii extends Model
{
    public function install()
    {
        $this->db->query("
			CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "spotii_order` (
			  `spotii_order_id` INT(11) NOT NULL AUTO_INCREMENT,
			  `order_id` INT(11) NOT NULL,
			  `order_code` VARCHAR(50),
			  `date_added` DATETIME NOT NULL,
			  `date_modified` DATETIME NOT NULL,
			  `refund_status` INT(1) DEFAULT NULL,
			  `currency_code` CHAR(3) NOT NULL,
			  `total` DECIMAL( 10, 2 ) NOT NULL,
			  PRIMARY KEY (`spotii_order_id`)
			) ENGINE=MyISAM DEFAULT COLLATE=utf8_general_ci;");

        $this->db->query("
			CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "spotii_order_transaction` (
			  `spotii_order_transaction_id` INT(11) NOT NULL AUTO_INCREMENT,
			  `spotii_order_id` INT(11) NOT NULL,
			  `date_added` DATETIME NOT NULL,
			  `type` ENUM('auth', 'payment', 'refund', 'void') DEFAULT NULL,
			  `amount` DECIMAL( 10, 2 ) NOT NULL,
			  PRIMARY KEY (`spotii_order_transaction_id`)
			) ENGINE=MyISAM DEFAULT COLLATE=utf8_general_ci;");

        $this->db->query("
			CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "spotii_card` (
			  `spotii_card_id` INT(11) NOT NULL AUTO_INCREMENT,
			  `customer_id` INT(11) NOT NULL,
			  `date_added` DATETIME NOT NULL,
			  `digits` CHAR(4) NOT NULL,
			  `expire_month` INT(2) NOT NULL,
			  `expire_year` INT(2) NOT NULL,
			  `card_type` CHAR(15) NOT NULL,
			  `token` CHAR(64) NOT NULL,
			  PRIMARY KEY (`spotii_card_id`)
			) ENGINE=MyISAM DEFAULT COLLATE=utf8_general_ci;");
    }

    public function uninstall()
    {
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "spotii_order`;");
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "spotii_order_transaction`;");
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "spotii_card`;");
    }

    public function refund($order_id, $amount)
    {
        $spotii_order = $this->getOrder($order_id);
        
        if (!empty($spotii_order) && $spotii_order['refund_status'] != 1) {
            $order['refundAmount'] = (int) ($amount * 100);

            $url = $spotii_order['order_id'] . '/refund';

            $response_data = $this->sendCurl($url, $order);

            return $response_data;
        } else {
            return false;
        }
    }

    public function updateRefundStatus($spotii_order_id, $status)
    {
        $this->db->query("UPDATE `" . DB_PREFIX . "spotii_order` SET `refund_status` = '" . (int) $status . "' WHERE `spotii_order_id` = '" . (int) $spotii_order_id . "'");
    }

    public function getOrder($order_id)
    {

        $qry = $this->db->query("SELECT * FROM `" . DB_PREFIX . "spotii_order` WHERE `order_id` = '" . (int) $order_id . "' LIMIT 1");

        if ($qry->num_rows) {
            $order = $qry->row;
            $order['transactions'] = $this->getTransactions($order['spotii_order_id'], $qry->row['currency_code']);

            return $order;
        } else {
            return false;
        }
    }

    private function getTransactions($spotii_order_id, $currency_code)
    {
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "spotii_order_transaction` WHERE `spotii_order_id` = '" . (int) $spotii_order_id . "'");

        $transactions = array();
        if ($query->num_rows) {
            foreach ($query->rows as $row) {
                $row['amount'] = $this->currency->format($row['amount'], $currency_code, false);
                $transactions[] = $row;
            }
            return $transactions;
        } else {
            return false;
        }
    }

    public function addTransaction($spotii_order_id, $type, $total)
    {
        $this->db->query("INSERT INTO `" . DB_PREFIX . "spotii_order_transaction` SET `spotii_order_id` = '" . (int) $spotii_order_id . "', `date_added` = now(), `type` = '" . $this->db->escape($type) . "', `amount` = '" . (float) $total . "'");
    }

    public function getTotalReleased($spotii_order_id)
    {
        $query = $this->db->query("SELECT SUM(`amount`) AS `total` FROM `" . DB_PREFIX . "spotii_order_transaction` WHERE `spotii_order_id` = '" . (int) $spotii_order_id . "' AND (`type` = 'payment' OR `type` = 'refund')");

        return (float) $query->row['total'];
    }

    public function getTotalRefunded($spotii_order_id)
    {
        $query = $this->db->query("SELECT SUM(`amount`) AS `total` FROM `" . DB_PREFIX . "spotii_order_transaction` WHERE `spotii_order_id` = '" . (int) $spotii_order_id . "' AND 'refund'");

        return (float) $query->row['total'];
    }

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
