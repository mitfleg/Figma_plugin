<?php
class RateLimiter
{
    protected $db;
    protected $limit;
    protected $timeWindow;

    public function __construct($db, $limit = 100, $timeWindow = 3600)
    {
        $this->db = $db;
        $this->limit = $limit;
        $this->timeWindow = $timeWindow;
    }

    public function check($ip)
    {
        $stmt = $this->db->prepare("SELECT requests, last_request FROM rate_limiter WHERE ip = :ip");
        $stmt->execute([':ip' => $ip]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $stmt = $this->db->prepare("INSERT INTO rate_limiter (ip, requests, last_request) VALUES (:ip, 1, NOW())");
            $stmt->execute([':ip' => $ip]);
            return true;
        }

        $requests = $row['requests'];
        $lastRequest = strtotime($row['last_request']);

        if (time() - $lastRequest > $this->timeWindow) {
            $stmt = $this->db->prepare("UPDATE rate_limiter SET requests = 1, last_request = NOW() WHERE ip = :ip");
            $stmt->execute([':ip' => $ip]);
            return true;
        }

        if ($requests < $this->limit) {
            $stmt = $this->db->prepare("UPDATE rate_limiter SET requests = requests + 1, last_request = NOW() WHERE ip = :ip");
            $stmt->execute([':ip' => $ip]);
            return true;
        }

        return false;
    }
}
