<?php
header('Content-Type: application/json');

class ApiException
{
    private $message;
    private $code;
    private $statusCode;

    public function __construct($message = "", $statusCode = 0, $code = 0)
    {
        $this->message = $message;
        $this->code = $code;
        $this->statusCode = $statusCode;
        $this->sendErrorResponse();
    }

    public function sendErrorResponse()
    {
        http_response_code($this->statusCode);
        echo json_encode([
            'error' => [
                'message' => $this->message,
                'code' => $this->code
            ]
        ]);
        exit;
    }
}
