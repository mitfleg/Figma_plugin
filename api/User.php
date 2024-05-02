<?php

require_once('ApiException.php');
require_once('SxGeo/SxGeo.php');
require_once('templateMail/Mailer.php');
require_once('Token.php');
require_once('Subscriptions.php');
require_once('Payment.php');
require_once('Telegram.php');
require_once('Validator.php');

require_once('Database.php');

class User {

    private $db;
    public $login;
    public $email;
    private $password;
    public $figma_id;
    public $ip;
    public $ip_id;
    public $subscribe_id;
    public $country;
    public $isActive;
    private $activationCode;
    private $jwtToken;
    public $subscribe_end_date;
    public $promocode;

    public function __construct(Database $db, array $data = []) {
        $this->db = $db;

        $this->login = $data['login'] ?? null;
        $this->email = $data['email'] ?? null;
        $this->figma_id = $data['figma_id'] ?? null;
        $this->ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $this->country = null;
        $this->ip_id = null;
        $this->subscribe_id = null;
        $this->activationCode = $data['activationCode'] ?? null;
        $this->jwtToken = $data['token'] ?? null;
        $this->subscribe_end_date = null;
        $this->promocode = $data['promocode'] ?? null;

        if( !is_null($this->jwtToken) ) {
            $this->updateFromJwtToken();
        }
    }

    private function updateFromJwtToken(): void {
        $jwt = new Token();
        $user = $jwt->verifyToken($this->jwtToken);

        if( $user ) {
            $this->figma_id = $user->figma_id;
            $this->fillUser();
        }
        else {
            throw new ApiException('Invalid or missing token', 401, 1);
        }
    }

    public function generateActivationCode() {
        $permitted_chars = '0123456789';
        $code = '';

        for($i = 0; $i < 6; $i++) {
            $code .= $permitted_chars[rand(0, strlen($permitted_chars) - 1)];
        }

        return $code;
    }

    public function checkActivationCode($code) {
        return $code === $this->activationCode;
    }

    public function signUp(): array {
        $validatorClass = new Validator();
        $validatorClass->validate_email($this->email);

        try {
            $this->db->beginTransaction();

            if( $this->userExist() ) {
                $this->login();
            }
            else {
                $this->register();
            }

            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw new ApiException('There was a problem registering the user', 500, 2);
        }

        http_response_code(201);
        return [
            'status' => 'success',
        ];
    }

    public function getLocation(string $ip): string {
        $SxGeo = new SxGeo('SxGeo/SxGeo.dat', SXGEO_BATCH | SXGEO_MEMORY);
        return $SxGeo->getCountry($ip);
    }

    public function saveIpAddress() {
        if( !is_null($this->ip) ) {
            $country = $this->getLocation($this->ip);

            $checkExistingIp = $this->db->prepare("SELECT id FROM ip_addresses WHERE ip_address = :ip_address");
            $checkExistingIp->execute([':ip_address' => $this->ip]);
            $existingIp = $checkExistingIp->fetch(PDO::FETCH_ASSOC);
            $ip_id = ($existingIp !== false) ? $existingIp['id'] : null;

            if( !$ip_id ) {
                $insertIp = $this->db->prepare("INSERT INTO ip_addresses (ip_address, country) VALUES (:ip_address, :country)");

                if( $insertIp->execute([':ip_address' => $this->ip, ':country' => $country]) ) {
                    $ip_id = $this->db->lastInsertId();
                }
                else {
                    throw new ApiException('There was a problem with saving the IP address', 500, 2);
                }
            }

            return $ip_id;
        }
    }

    private function getLoginFromEmail() {
        $this->login = substr($this->email, 0, strrpos($this->email, "@"));
    }

    private function login(): void {
        $code = $this->generateActivationCode();
        $this->sendVerificationCode($code);
        $this->updateUser(['activation_code' => $code]);
    }

    private function register(): void {
        $code = $this->generateActivationCode();
        $stmt = $this->db->prepare(
            "
            INSERT INTO users 
                (login, email, figma_id, ip_id, activation_code) 
            VALUES 
                (:login, :email, :figma_id, :ip_id, :activation_code)"
        );

        $this->getLoginFromEmail();
        $result = $stmt->execute(
            [
                ':login' => $this->login,
                ':email' => $this->email,
                ':figma_id' => $this->figma_id,
                ':ip_id' => $this->saveIpAddress(),
                ':activation_code' => $code,
            ]
        );

        if( $result ) {
            $this->sendVerificationCode($code);
        }
        else {
            throw new ApiException('There was a problem registering the user', 500, 2);
        }
    }

    public function confirmEmail(): array {
        $user = $this->getUser();

        if( $this->activationCode === $user['activation_code'] ) {
            if( !$user['is_active'] ) {
                $subscribeClass = new Subscriptions($this->db);
                $subscribeClass->deactivateAllSubscribe($this->figma_id);
                $subscribe_id = $subscribeClass->createSubscription($this->figma_id);
                $subscribeClass->activateSubscription($subscribe_id);
                $this->updateUser(['subscribe_id' => $subscribe_id, 'is_active' => true]);
            }

            $this->fillUser();
            $this->jwtToken = $this->createJwtToken();

            if( !$user['is_active'] ) {
                $telegramClass = new Telegram();
                $msg = "🎉 Новая регистрация%0A";
                $msg .= "👤 Login: " . $this->login . "%0A";
                $msg .= "📧 Email: " . $this->email . "%0A";
                $msg .= "🌐 IP: " . $this->ip . "%0A";
                $msg .= "🌍 Country: " . $this->country . "%0A";
                $telegramClass->send($msg);
            }

            return $this->generateUserResponse('confirmed');
        }
        else {
            throw new ApiException('Invalid confirmation code', 403, 3);
        }
    }

    private function generateUserResponse(string $type): array {
        $subscribeClass = new Subscriptions($this->db);
        $plans = $subscribeClass->getSubscriptionPlans();
        $this->updateLastLogin();
        http_response_code(200);
        return [
            'status' => 'success',
            'type_operation' => $type,
            'data' => [
                'login' => $this->login,
                'token' => $this->jwtToken,
                'subscribe' => $this->subscribe_end_date,
                'country' => $this->country
            ],
            'plans' => $plans
        ];
    }

    public function fillUser(): void {
        $user = $this->getUser();

        if( $user ) {
            $subscribeClass = new Subscriptions($this->db);

            $this->login = $user['login'];
            $this->email = $user['email'];
            $this->figma_id = $user['figma_id'];
            $this->isActive = $user['is_active'];
            $this->ip_id = $user['ip_id'];
            $this->subscribe_id = $user['subscribe_id'];
            $this->subscribe_end_date = $subscribeClass->checkingSubscribe($this->figma_id);
            $this->ip = $user['ip'];
            $this->country = $user['country'];
        }
        else {
            throw new ApiException('User not found', 401, 2);
        }
    }

    private function userExist(): bool {
        $checkExistingUser = $this->db->prepare(
            "SELECT * FROM users WHERE email = :email OR figma_id = :figma_id"
        );
        $checkExistingUser->execute(
            [
                ':email' => $this->email,
                ':figma_id' => $this->figma_id
            ]
        );
        $user = $checkExistingUser->fetch(PDO::FETCH_ASSOC);

        if( $user ) {
            if( !$user['is_active'] ) {
                $this->deleteUser();
                return false;
            }

            return true;
        }

        return false;
    }

    public function sendVerificationCode(string $code): void {
        $subject = "Registration to SVG Converter";
        $message = $this->renderHtml($code);
        $mailer = new Mailer($this->email, $subject, $message);

        if( !$mailer->sendEmail() ) {
            throw new ApiException('There was a problem with sending the code', 500, 2);
        }
    }

    private function renderHtml($code): string {
        ob_start();
        include('templateMail/registr.php');
        return ob_get_clean();
    }

    public function createJwtToken() {
        $jwt = new Token();
        return $jwt->createToken($this->figma_id);
    }

    public function getUser() {
        $currentDate = date('Y-m-d H:i:s');

        $stmt = $this->db->prepare(
            "
            SELECT 
                users.*, 
                ip_addresses.ip_address AS ip, 
                ip_addresses.country, 
                subscriptions.end_date AS subscribe_end_date
            FROM users 
            LEFT JOIN ip_addresses 
                ON users.ip_id = ip_addresses.id
            LEFT JOIN subscriptions 
                ON users.figma_id = subscriptions.figma_id 
                AND subscriptions.start_date <= :current_date 
                AND subscriptions.end_date >= :current_date 
                AND subscriptions.is_active = 1
            WHERE users.figma_id = :figma_id
        "
        );

        $stmt->execute([':figma_id' => $this->figma_id, ':current_date' => $currentDate]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if( $user && !empty($user['subscribe_end_date']) ) {
            $user['subscribe_end_date'] = DateTime::createFromFormat('Y-m-d H:i:s', $user['subscribe_end_date'])->format('d.m.Y H:i');
        }

        return $user;
    }

    public function updateUser(array $fields): void {
        $setFields = [];
        $bindValues = [];

        foreach($fields as $key => $value) {
            $setFields[] = "`" . $key . "` = :" . $key;
            $bindValues[':' . $key] = $value;
        }

        $sql = "UPDATE users SET " . implode(", ", $setFields) . " WHERE figma_id = :figma_id";
        $bindValues[':figma_id'] = $this->figma_id;

        $updateUser = $this->db->prepare($sql);

        if( !$updateUser->execute($bindValues) ) {
            throw new ApiException('There was a problem updating the user', 500, 3);
        }
    }

    public function deleteUser() {
        $stmt = $this->db->prepare("DELETE FROM users WHERE figma_id = :figma_id");
        $stmt->execute([':figma_id' => $this->figma_id]);
    }

    public function getUsers() {
        $stmt = $this->db->prepare("SELECT * FROM users");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function authorize(): array {
        return $this->generateUserResponse('authorized');
    }

    public function updateLastLogin() {
        $date = date('Y-m-d H:i:s');
        $this->updateUser(['last_login' => $date]);
    }

    public function activatePromocode(): array {
        $subscribeClass = new Subscriptions($this->db);
        $subscription_id = $subscribeClass->activatePromocode($this->promocode, $this->figma_id);

        if( !is_null($subscription_id) ) {
            $this->updateUser(['subscribe_id' => $subscription_id]);
        }

        $this->fillUser();
        return $this->generateUserResponse('activated');
    }

    public function resendCode(): array {
        $code = $this->generateActivationCode();

        $this->updateUser(['activation_code' => $code]);
        $this->fillUser();
        $this->sendVerificationCode($code);
        http_response_code(200);
        return [
            'status' => 'success',
        ];
    }

    public function paySubscribe($plan_id) {
        if( !is_null($this->jwtToken) ) {
            $subscribeClass = new Subscriptions($this->db);
            $payment = new Payment($this->db, $this->figma_id, $this->email);
            $subscription_id = $subscribeClass->createSubscription($this->figma_id);
            $payment_url = $payment->createSubscriptionOrder($subscription_id, (int) $plan_id);

            http_response_code(200);
            return [
                'status' => 'success',
                'type_operation' => 'subscribe',
                'data' => [
                    'url' => $payment_url
                ]
            ];
        }
        else {
            throw new ApiException('You are not logged in', 401, 2);
        }
    }
}
