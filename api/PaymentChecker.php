<?php

require_once('Database.php');
require_once('Subscriptions.php');
require_once('User.php');
require_once('Telegram.php');
require_once('lib/autoload.php');

use YooKassa\Client;

class PaymentChecker {

    private $apiUrl = "https://api.cryptocloud.plus/v1/invoice/info?uuid=";
    private $apiToken = "";
    private $db;

    public function __construct() {
        $this->db = new Database();
    }

    public function checkAllUnpaidInvoices(): void {
        $stmt = $this->db->prepare("SELECT invoice_id FROM subscription_orders WHERE payment_status = 'UNPAID'");
        $stmt->execute();

        $unpaidInvoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach($unpaidInvoices as $invoice) {
            if( strlen($invoice['invoice_id']) < 10 ) {
                $this->checkPaymentCrypto($invoice['invoice_id']);
            }
            else {
                $this->checkPaymentYookassa($invoice['invoice_id']);
            }
        }
    }

    public function checkPaymentYookassa(string $key_pay): void {
        $client = new Client();
        $client->setAuth('', '');
        $payment = $client->getPaymentInfo($key_pay);
        $pay_check = $payment->getstatus();

        $pay_check = 'succeeded';

        if( $pay_check === 'waiting_for_capture' || $pay_check === 'succeeded' ) {
            $this->confirmSubscribe($key_pay, 'PAID');
        }
        elseif( $pay_check === 'canceled' ) {
            $this->confirmSubscribe($key_pay, 'CANCELED');
        }
    }

    public function checkPaymentCrypto(string $invoiceId): void {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $this->apiUrl . $invoiceId);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPGET, 1);

        $headers = [
            "Authorization: Token " . $this->apiToken,
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $output = curl_exec($ch);

        if( curl_errno($ch) ) {
            error_log(curl_error($ch));
            return;
        }

        curl_close($ch);

        $response = json_decode($output, true);

        if( $response === null ) {
            error_log('Invalid response from the payment API');
            return;
        }

        if( isset($response['status']) && $response['status'] === 'success' ) {
            if( $response['status_invoice'] === 'paid' ) {
                $this->confirmSubscribe($invoiceId, 'PAID');
            }
            elseif( $response['status_invoice'] === 'canceled' ) {
                $this->confirmSubscribe($invoiceId, 'CANCELED');
            }
        }
    }

    public function updateSubscribe(string $invoiceId) {
        $subscriptionClass = new Subscriptions($this->db);
        $subscription_info = $subscriptionClass->getSubscribeByInvoiceId($invoiceId);
        $plan = $subscriptionClass->getSubscriptionPlanById($subscription_info['plan']);
        $planDuration = $plan['duration'];

        $figma_id = $subscription_info['figma_id'];
        $subscribe_id = $subscription_info['subscription_id'];
        $subscriptionClass->deactivateAllSubscribe($figma_id);
        $subscriptionClass->activateSubscription($subscribe_id, $planDuration);

        $user = new User($this->db, ['figma_id' => $figma_id]);
        $user->fillUser();
        $user->updateUser(['subscribe_id' => $subscribe_id]);

        $telegramClass = new Telegram();
        $val = $plan['id'] == 5 ? 'RUB' : 'USD';
        $msg = "🎉 Покупка подписки 🎉%0A";
        $msg .= "👤 Пользователь: " . $user->login . "%0A";
        $msg .= "📧 Email: " . $user->email . "%0A";
        $msg .= "💳 Тип подписки: " . $plan['plan_name'] . "%0A";
        $msg .= "💰 Стоимость: " . $plan['price'] . " $val%0A";
        $telegramClass->send($msg);
    }

    private function confirmSubscribe($key_pay, $status) {
        $stmt = $this->db->prepare("UPDATE subscription_orders SET payment_status = 'PAID' WHERE invoice_id = :invoice_id");
        $stmt->execute([':invoice_id' => $key_pay]);

        if( $status === 'PAID' ) {
            $this->updateSubscribe($key_pay);
        }
    }
}

$paymentChecker = new PaymentChecker();
$paymentChecker->checkAllUnpaidInvoices();
