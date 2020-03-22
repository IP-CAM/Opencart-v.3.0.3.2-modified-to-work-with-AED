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

    public function sendCurl($url, $order)
    {

        $json = json_encode($order);

        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, 'https://api.spotii.com/v1/orders/' . $url);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($curl, CURLOPT_POSTFIELDS, $json);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 0);
        curl_setopt($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
        curl_setopt($curl, CURLOPT_TIMEOUT, 10);
        curl_setopt(
            $curl,
            CURLOPT_HTTPHEADER,
            array(
                "Authorization: " . $this->config->get('payment_spotii_service_key'),
                "Content-Type: application/json",
                "Content-Length: " . strlen($json)
            )
        );

        $result = json_decode(curl_exec($curl));
        curl_close($curl);

        $response = array();

        if (isset($result)) {
            $response['status'] = $result->httpStatusCode;
            $response['message'] = $result->message;
            $response['full_details'] = $result;
        } else {
            $response['status'] = 'success';
        }

        return $response;
    }

    public function logger($message)
    {
        if ($this->config->get('payment_spotii_debug') == 1) {
            $log = new Log('spotii.log');
            $log->write($message);
        }
    }
}
