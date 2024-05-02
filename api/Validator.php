<?php

require_once('ApiException.php');

class Validator
{
    public function validate_email($email): void
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ApiException('Invalid email format', 400, 3);
        }

        $url = "https://raw.githubusercontent.com/elliotjreed/disposable-emails-filter-php/master/list1.txt";
        $disposableEmails = @file_get_contents($url);

        $disposableEmailsArray = [];

        if ($disposableEmails) {
            $disposableEmailsArray = explode("\n", $disposableEmails);
        }

        $disposable_email_domains = [
            '0-mail.com',
            'mailinator.com',
            'guerrillamail.com',
            'sharklasers.com',
            'maildrop.cc',
            'temp-mail.org',
            'getairmail.com',
            'throwawaymail.com',
            'fakeinbox.com',
            '10minutemail.com',
            'tempmailo.com',
            'temp-mail.ru',
            'trashmail.com',
            'tempail.com',
            'yopmail.com',
            'emailondeck.com',
            'dropmail.me',
            'getnada.com',
            'inboxkitten.com',
            'tempinbox.com',
            'mintemail.com',
            'mailnesia.com',
            'emailfake.com',
            'mailinator2.com',
            'mailinator.cc',
            'binkmail.com',
            'mytemp.email',
            'temp-mail.io',
            'tempr.email',
            'yertxenon.tk',
            'deadaddress.com',
            'tempmailaddress.com',
            'tempm.com',
            'trashmail.me',
            'dingsmail.com',
            'tempmail.de',
            'dispostable.com',
            'grr.la',
            'mohmal.com',
            'guerrillamail.biz',
            'guerrillamail.org',
            'getairmail.net',
            'dayrep.com',
            'mailnull.com',
            'notmailinator.com',
            'spambog.com',
            '4warding.com',
            'easytrashmail.com',
            'mailinator.info',
            'getonemail.com',
            'mailforspam.com',
            'tempmail.pro',
            'notsharingmy.info',
            'diwaq.com',
            'owlymail.com',
            'mailcatch.com',
            'kismetmail.com',
            'mytempmail.com',
            'dispostable.net',
            'mail-temp.com',
            'incognitomail.com',
            'filzmail.com',
            'pookmail.com',
            'crazymailing.com',
            'sharklasers.net',
            'tempmailgen.com',
            'jetable.org',
            '33mail.com',
            'bin.8191.at',
            'emltmp.com',
            '1secmail.com',
            'jetable.email',
            'uroid.com',
            'mvrht.com',
            'mailinator.uk',
            'trashinbox.com',
            'zslsz.com',
            'gustr.com',
            'qwfox.com',
            'xjoi.com',
            '10mails.net',
            '33mail.com',
            '1secmail.com',
            'emltmp.com',
            'maildrop.cc',
            'temp-mail.org',
            '027168.com',
            '0815.ru',
            '10-minute-mail.com',
        ];

        $disposable_email_merge = array_merge($disposableEmailsArray, $disposable_email_domains);
        $disposable_email_unique = array_unique($disposable_email_merge);

        $email_domain = substr(strrchr($email, "@"), 1);

        if (in_array($email_domain, $disposable_email_unique)) {
            throw new ApiException('Disposable email addresses are not allowed', 400, 2);
        }
    }
}
