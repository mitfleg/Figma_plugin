<?php

require_once('ApiException.php');

class Subscriptions {

    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function checkingSubscribe(string $figma_id): ?string {
        $currentDate = date('Y-m-d H:i:s');

        $stmt = $this->db->prepare(
            "
            SELECT * 
            FROM subscriptions 
            WHERE figma_id = :figma_id 
            AND start_date <= :current_date 
            AND end_date >= :current_date
            AND is_active = 1
        "
        );

        $stmt->execute(
            [
                ':figma_id' => $figma_id,
                ':current_date' => $currentDate,
            ]
        );

        $subscription = $stmt->fetch(PDO::FETCH_ASSOC);

        if( $stmt->rowCount() > 0 ) {
            if( $this->validateEndDate($subscription['end_date']) ) {
                $end_date = DateTime::createFromFormat('Y-m-d H:i:s', $subscription['end_date']);
                return $end_date->format('d.m.Y H:i');
            }
        }
        else {
            $this->deactivateAllSubscribe($figma_id);
        }

        return null;
    }

    public function validateEndDate(?string $endDate): bool {
        if( $endDate === "0000-00-00 00:00:00" ) {
            return false;
        }

        $dt = DateTime::createFromFormat("Y-m-d H:i:s", $endDate);

        if( $dt === false || array_sum($dt::getLastErrors()) > 0 ) {
            return false;
        }

        return true;
    }

    public function activateSubscription(int $subscription_id, string $subscriptionPeriod = '7 DAY'): void {
        $startDate = date('Y-m-d H:i:s');
        $endDate = date("Y-m-d H:i:s", strtotime("+" . $subscriptionPeriod));

        $stmt = $this->db->prepare(
            "
            UPDATE subscriptions 
            SET 
                start_date = :start_date,
                end_date = :end_date,
                is_active = 1
            WHERE
                id = :subscription_id
            "
        );

        $executeData = [
            ':start_date' => $startDate,
            ':end_date' => $endDate,
            ':subscription_id' => $subscription_id,
        ];

        if( !$stmt->execute($executeData) ) {
            throw new ApiException('There was a problem with activating the subscription', 500, 3);
        }
    }

    public function deactivateAllSubscribe(string $figma_id): void {
        $stmt = $this->db->prepare(
            "
            UPDATE subscriptions 
            SET 
                is_active = 0
            WHERE
                figma_id = :figma_id
            "
        );

        $executeData = [
            ':figma_id' => $figma_id,
        ];

        if( !$stmt->execute($executeData) ) {
            throw new ApiException('There was a problem with activating the subscription', 500, 3);
        }
    }

    public function getSubscribeByInvoiceId(string $invoice_id): array {
        $stmt = $this->db->prepare("SELECT * FROM subscription_orders WHERE invoice_id = :invoice_id");
        $stmt->execute([':invoice_id' => $invoice_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function createSubscription(string $figma_id): int {
        $stmt = $this->db->prepare(
            "
            INSERT INTO subscriptions 
                (figma_id) 
            VALUES 
                (:figma_id)
        "
        );

        $executeData = [
            ':figma_id' => $figma_id,
        ];

        if( $stmt->execute($executeData) ) {
            return (int) $this->db->lastInsertId();
        }

        throw new ApiException('There was a problem with creating a subscription', 500, 3);
    }

    public function savePromocodes(int $quantity = 1, array $interval = ['1 DAY']): void {
        $stmt = $this->db->prepare("INSERT INTO promocodes (code, expiry_interval) VALUES (:code, :interval)");

        foreach($interval as $item) {
            for($i = 0; $i < $quantity; $i++) {
                $code = $this->generateOnePromocode();

                if( !$stmt->execute([':code' => $code, ':interval' => $item]) ) {
                    throw new ApiException('There was a problem with inserting a promocode', 500, 3);
                }
            }
        }
    }

    private function generateOnePromocode(): string {
        $permittedChars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $uniqueNumber = microtime(true) * 10000 . rand();

        $code = '';
        while( $uniqueNumber > 0 && strlen($code) < 20 ) {
            $code .= $permittedChars[$uniqueNumber % strlen($permittedChars)];
            $uniqueNumber = (int) ($uniqueNumber / strlen($permittedChars));
        }

        while( strlen($code) < 20 ) {
            $code .= $permittedChars[rand(0, strlen($permittedChars) - 1)];
        }

        return $code;
    }

    public function getPromocode(string $promocode): array {
        $stmt = $this->db->prepare("SELECT * FROM promocodes WHERE code = :code");
        $stmt->execute([':code' => $promocode]);

        if( $stmt->rowCount() > 0 ) {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
        else {
            throw new ApiException('Invalid or used promocode', 400, 3);
        }
    }

    public function activatePromocode(string $promocode, string $figma_id): ?int {
        $promocodeInfo = $this->getPromocode($promocode);

        if( $promocodeInfo['status'] === 'UNUSED' ) {
            $promocodeInterval = $promocodeInfo['expiry_interval'];
            $stmt = $this->db->prepare("UPDATE promocodes SET status = 'USED', figma_id = :figma_id WHERE code = :code");

            if( $stmt->execute([':code' => $promocode, ':figma_id' => $figma_id]) ) {
                $currentEndDate = $this->checkingSubscribe($figma_id);

                if( !$currentEndDate ) {
                    $subscriptionId = $this->createSubscription($figma_id);
                    $this->activateSubscription($subscriptionId, $promocodeInterval);
                    return $subscriptionId;
                }
                else {
                    $newEndDate = date("Y-m-d H:i:s", strtotime($currentEndDate . " +$promocodeInterval"));

                    $stmt = $this->db->prepare("UPDATE subscriptions SET end_date = :end_date, is_active = 1 WHERE figma_id = :figma_id");

                    if( !$stmt->execute([':end_date' => $newEndDate, ':figma_id' => $figma_id]) ) {
                        throw new ApiException('There was a problem with extending a subscription', 500, 3);
                    }
                }
            }
            else {
                throw new ApiException('There was a problem with activating a promocode', 500, 3);
            }
        }
        else {
            throw new ApiException('Invalid or used promocode', 400, 3);
        }

        return null;
    }

    public function getSubscriptionPlans() {
        $stmt = $this->db->prepare("SELECT * FROM subscription_plans WHERE for_russia = 0");
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $data = [];

        foreach($result as $item) {
            $data[] = [
                'name' => $item['plan_name'],
                'id' => $item['id'],
                'price' => $item['price'],
                'old_price' => $item['old_price'],
            ];
        }

        return $data;
    }

    public function getSubscriptionPlanById($plan_id) {
        $stmt = $this->db->prepare("SELECT * FROM subscription_plans WHERE id = :plan_id");
        $stmt->execute([':plan_id' => $plan_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
