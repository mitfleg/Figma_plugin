<?php

require_once('Telegram.php');
require_once('Subscriptions.php');
require_once('lib/autoload.php');

use YooKassa\Client;

class Payment {

    private $db;
    private $figma_id;
    private $email;
    private $shop_id;
    private $api_token;

    public function __construct($db, $figma_id, $email) {
        $this->db = $db;
        $this->figma_id = $figma_id;
        $this->email = $email;
        $this->shop_id = '';
        $this->api_token = '';
    }

    public function createSubscriptionOrder(int $subscription_id, int $plan_id): string {
        $subscriptionClass = new Subscriptions($this->db);
        $plan = $subscriptionClass->getSubscriptionPlanById($plan_id);

        if( $plan ) {
            $stmt = $this->db->prepare(
                "
            INSERT INTO subscription_orders
                (figma_id, subscription_id, plan)
            VALUES
                (:figma_id, :subscription_id, :plan)
            "
            );

            $executeData = [
                ':figma_id' => $this->figma_id,
                ':subscription_id' => $subscription_id,
                ':plan' => $plan_id,
            ];

            if( $stmt->execute($executeData) ) {
                $order_id = (int) $this->db->lastInsertId();

                if( $plan_id === 5 ) {
                    $order = $this->createYookassa($plan['price']);
                }
                else {
                    $order = $this->createInvoice($plan['price'], $order_id);
                }

                $this->updateSubscriptionOrder(['invoice_id' => $order['invoice_id']], $order_id);
                return $order['pay_url'];
            }

            throw new ApiException('There was a problem with creating a subscription order', 500, 3);
        }
        else {
            throw new ApiException('Subscription not found', 500, 3);
        }
    }

    public function updateSubscriptionOrder(array $fields, int $subscription_id) {
        $setFields = [];
        $bindValues = [];

        foreach($fields as $key => $value) {
            $setFields[] = "`" . $key . "` = :" . $key;
            $bindValues[':' . $key] = $value;
        }

        $sql = "UPDATE subscription_orders SET " . implode(", ", $setFields) . " WHERE id = :id";
        $bindValues[':id'] = $subscription_id;

        $updateUser = $this->db->prepare($sql);

        if( !$updateUser->execute($bindValues) ) {
            throw new ApiException('There was a problem updating the subscription', 500, 3);
        }
    }

    public function createInvoice(float $amount, int $order_id): array {
        $telegram = new Telegram();

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, "https://api.cryptocloud.plus/v1/invoice/create");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);

        $headers = [
            "Authorization: Token " . $this->api_token,
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $data = [
            "amount" => $amount,
            "shop_id" => $this->shop_id,
            "order_id" => $order_id,
            "email" => $this->email,
        ];
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

        $output = curl_exec($ch);

        if( curl_errno($ch) ) {
            $this->handleError(curl_error($ch), $telegram, $amount, $order_id, $this->email);
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if( $httpCode >= 400 ) {
            $this->handleHttpError($httpCode, $telegram, $amount, $order_id, $this->email);
        }

        $response = json_decode($output, true);

        if( $response === null || !isset($response['status']) || $response['status'] !== 'success' ) {
            $this->handleError('Invalid response from the invoice API', $telegram, $amount, $order_id, $this->email);
        }

        return $response;
    }

    public function createYookassa(float $amount): array {
        $client = new Client();
        $client->setAuth('', '');
        $payment = $client->createPayment(
            array(
                "amount" => array(
                    "value" => $amount,
                    "currency" => "RUB"
                ),
                "confirmation" => array(
                    "type" => "redirect",
                    "return_url" => "https://svgconverter.ru/"
                ),
                "capture" => true,
                "receipt" => array(
                    "customer" => array(
                        "email" => $this->email,
                    ),
                    "items" => array(
                        array(
                            "description" => "Оформление подписки на 1 месяц",
                            "quantity" => "1.00",
                            "amount" => array(
                                "value" => $amount,
                                "currency" => "RUB"
                            ),
                            "tax_system_code" => "2",
                            "vat_code" => "2",
                            "payment_mode" => "full_prepayment",
                            "payment_subject" => "service"
                        )
                    )
                )
            ),
            uniqid('', true)
        );
        $pay_url = $payment->getConfirmation()->getConfirmationUrl();
        $pay_key = $payment->getid();
        $response = [
            'invoice_id' => $pay_key,
            'pay_url' => $pay_url
        ];
        return $response;
    }

    private function handleError(string $message, Telegram $telegram, float $amount, int $order_id, string $email) {
        $message = $message . ", Email: {$email}, Order ID: {$order_id}, Amount: {$amount}";
        $telegram->send($message);
        error_log($message);
        throw new ApiException('There was a problem with creating the invoice', 500, 3);
    }

    private function handleHttpError($httpError, Telegram $telegram, float $amount, int $order_id, string $email) {
        $errors = [
            401 => 'Unauthenticated: Invalid API KEY',
            403 => 'Forbidden: You do not have permission to access this resource',
            406 => 'Service is not available',
            400 => 'There was a problem with creating the invoice',
        ];

        $message = $errors[$httpError] ?? 'Unknown error';
        $this->handleError($message, $telegram, $amount, $order_id, $email);
    }
}
