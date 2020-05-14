<?php
declare(strict_types=1);

namespace atk4\outbox\Mailer;

use atk4\core\DIContainerTrait;
use atk4\outbox\MailerInterface;
use atk4\outbox\Model\Mail;
use atk4\outbox\Model\MailResponse;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP as PHPMailerSMTP;

class AbstractMailer implements MailerInterface
{
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

        $this->phpmailer->Host = $this->host;
        $this->phpmailer->Port = $this->port;
        $this->phpmailer->SMTPSecure = $this->secure;

        $this->phpmailer->SMTPAuth = $this->auth;
        $this->phpmailer->Username = $this->username;
        $this->phpmailer->Password = $this->password;
    }

    public function send(Mail $mail): MailResponse
    {
        $mail_response = new MailResponse($mail->persistence);

        try {

            $this->phpmailer->setFrom(
                $mail->ref('from')->get('email'),
                $mail->ref('from')->get('name')
            );

            $this->addAddress(
                $mail,
                'replyto',
                function ($address): void {
                    $this->phpmailer->addReplyTo(
                        $address->get('email'),
                        $address->get('name')
                    );
                }
            );

            $this->addAddress(
                $mail,
                'cc',
                function ($address): void {
                    $this->phpmailer->addCC(
                        $address->get('email'),
                        $address->get('name')
                    );
                }
            );

            $this->addAddress(
                $mail,
                'bcc',
                function ($address): void {
                    $this->phpmailer->addBCC(
                        $address->get('email'),
                        $address->get('name')
                    );
                }
            );

            $this->phpmailer->Subject = $mail->get('subject');
            $this->phpmailer->msgHTML = $mail->get('html');
            $this->phpmailer->AltBody = $mail->get('text');

            foreach ($mail->ref('headers')->getIterator() as $model) {
                $this->phpmailer->addCustomHeader($model->get('name'),
                    $model->get('value'));
            }

            foreach ($mail->ref('attachments')->getIterator() as $model) {
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

            // save successful MailResponse
            $mail_response->save(["email_id" => $mail->id]);

        } catch (Exception $exception) {
            $mail->set('status', Mail::STATUS_ERROR);
            $mail->save();

            // save successful MailResponse
            $mail_response->save([
                "email_id" => $mail->id,
                "code"     => $exception->getCode(),
                "message"  => $exception->getMessage(),
            ]);

            throw $exception;
        }

        return $mail_response;
    }

    protected function addAddress(Mail $mail, string $ref_name, callable $func): void
    {
        foreach ($mail->ref($ref_name)->getIterator() as $id => $address) {
            $func($address);
        }
    }
}
