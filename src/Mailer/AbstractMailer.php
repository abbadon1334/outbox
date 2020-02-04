<?php

namespace atk4\outbox\Mailer;

use atk4\core\DIContainerTrait;
use atk4\outbox\MailerInterface;
use atk4\outbox\Model\Mail;
use Exception;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP as PHPMailerSMTP;

class AbstractMailer implements MailerInterface
{
    const SMTP_SECURE_NULL = '';
    const SMTP_SECURE_TLS  = 'tls';
    const SMTP_SECURE_SSL  = 'ssl';

    use DIContainerTrait;

    /** @var PHPMailer */
    protected $phpmailer;

    /** @var int */
    protected $debug = PHPMailerSMTP::DEBUG_OFF;
    /** @var bool */
    protected $auth = false;
    /** @var string */
    protected $host = 'localhost';
    /** @var int */
    protected $port = 587;
    /** @var int */
    protected $secure = self::SMTP_SECURE_NULL;
    /** @var string */
    protected $username;
    /** @var string */
    protected $password;

    public function __construct(array $defaults = [])
    {
        $this->setDefaults($defaults);
        $this->phpmailer = new PHPMailer(true);

        $this->phpmailer->SMTPDebug = $this->debug;

        $this->phpmailer->Host       = $this->host;
        $this->phpmailer->Port       = $this->port;
        $this->phpmailer->SMTPSecure = $this->secure;

        $this->phpmailer->SMTPAuth = $this->auth;
        $this->phpmailer->Username = $this->username;
        $this->phpmailer->Password = $this->password;
    }

    public function send(Mail $mail): void
    {
        try {
            $from = $mail->ref('from');
            $this->phpmailer->setFrom($from->get('email'), $from->get('name'));

            $this->addAddress(
                $mail, 'replyto', function ($address) {
                $this->phpmailer->addReplyTo($address->get('email'), $address->get('name'));
            }
            );

            $this->addAddress(
                $mail, 'cc', function ($address) {
                $this->phpmailer->addCC($address->get('email'), $address->get('name'));
            }
            );

            $this->addAddress(
                $mail, 'replyto', function ($address) {
                $this->phpmailer->addBCC($address->get('email'), $address->get('name'));
            }
            );

            $this->phpmailer->Subject = $mail->get('subject');
            $this->phpmailer->msgHTML = $mail->get('html');
            $this->phpmailer->AltBody = $mail->get('text');

            foreach ($mail->ref('attachments') as $model) {
                $this->phpmailer->addAttachment(
                    $model->get('path'),
                    $model->get('name'),
                    $model->get('encoding'),
                    $model->get('mime'),
                    $model->get('disposition')
                );
            }

            $mail->set('status', Mail::STATUS_SENDING);
            $mail->save();
            $this->phpmailer->send();
            $mail->set('status', Mail::STATUS_SENT);
            $mail->save();
        } catch (Exception $exception) {
            $mail->set('status', Mail::STATUS_ERROR);
            $mail->save();
            throw $exception;
        }

        /* @todo insert MailResponse - every mailer has a different way of "response" */
    }

    private function addAddress(Mail $mail, string $ref_name, callable $func)
    {
        foreach ($mail->ref($ref_name) as $id => $address) {
            $func($address);
        }
    }
}