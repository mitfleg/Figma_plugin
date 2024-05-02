<?php
header('Content-Type: application/json');

use PHPMailer\PHPMailer\PHPMailer;

class Mailer
{
    private $to;
    private $subject;
    private $message;

    public function __construct($to, $subject, $message)
    {
        $this->to = $to;
        $this->subject = $subject;
        $this->message = $message;
    }

    public function sendEmail()
    {
        require_once("vendor/autoload.php");

        $mail = new PHPMailer();
        $mail->CharSet = 'utf-8';
        //$mail->SMTPDebug = 1;                              
        $mail->isSMTP();
        $mail->Host = 'mail.hosting.reg.ru';
        $mail->SMTPAuth = true;
        $mail->Username = 'support@svgconverter.ru';
        $mail->Password = '';
        $mail->SMTPSecure = 'ssl';
        $mail->Port = 465;

        $mail->setFrom($mail->Username, 'SVG Converter');
        $mail->addAddress($this->to);
        $mail->isHTML(true);
        $mail->addEmbeddedImage('logo.png', 'logo', 'logo.png');

        $mail->Subject = $this->subject;
        $mail->Body = $this->message;

        if (!$mail->send()) {
            return false;
        } else {
            return true;
        }
    }
}