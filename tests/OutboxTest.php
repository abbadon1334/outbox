<?php

namespace atk4\outbox\Test;

use atk4\core\AtkPhpunit\TestCase;
use atk4\data\Model;
use atk4\outbox\Mailer\Gmail;
use atk4\outbox\Model\Mail;
use atk4\outbox\Outbox;
use atk4\ui\App;
use atk4\ui\Layout\Generic;

/**
 * Class OutboxTest
 */
class OutboxTest extends TestCase
{
    private function getApp(): App
    {
        $app = new App();
        $app->db = Bootstrap::instance()->_getFromCollection('persistence',
            'elements');
        $app->initLayout(Generic::class);
        $app->add([
            Outbox::class,
            [
                'mailer' => [
                    FakeMailer::class
                ],
                'model'  => [
                    Mail::class,
                ],
            ],
        ]);

        return $app;
    }

    protected function setUp(): void
    {
        Bootstrap::instance()->setup();
    }

    public function testSend()
    {
        $app = $this->getApp();

        /** @var Outbox $outbox */
        $outbox = $this->getApp()->getOutbox();

        $mail = $outbox->new()
            ->withTemplateIdentifier('template_test')
            ->replaceContent('token', 'Agile Toolkit');

        $mail->ref('to')->save([
            "email" => 'destination@email.it',
            "name"  => "destination",
        ]);

        $response = $outbox->send($mail);

        $this->assertEquals(
            'hi to all,<br/>this is outbox library of Agile Toolkit.<br/><br/>have a good day.',
            $mail->get('html')
        );
        $this->assertEquals($response->get('email_id'), $mail->id);
    }

    public function testSendCallable()
    {
        $app = $this->getApp();

        /** @var Outbox $outbox */
        $outbox = $this->getApp()->getOutbox();

        $response = $outbox->callableSend(function (Mail $mail) use (&$mail2test) {

            $mail->withTemplateIdentifier('template_test')
                ->replaceContent('token', 'Agile Toolkit');

            $mail->ref('to')->save([
                "email" => 'destination@email.it',
                "name"  => "destination",
            ]);

            $mail->onHook('afterSend', function($m, $response) {
                $this->assertEquals(
                    'hi to all,<br/>this is outbox library of Agile Toolkit.<br/><br/>have a good day.',
                    $m->get('html')
                );
            });

            return $mail;
        });
    }

    public function testMailSaveAsTemplate()
    {
        /** @var Mail $mail_model */
        $mail_model = Bootstrap::instance()->_getFromCollection('mail_model','elements');

        $template_model = $mail_model->loadAny()->saveAsTemplate('new_mail_template');
        $data = $template_model->get();
        $template_model->delete();

        foreach ($data as $fieldname => $value) {
            if ($fieldname !== $template_model->id_field && $mail_model->hasField($fieldname)) {
                $this->assertEquals($value, $mail_model->get($fieldname));
            }
        }
    }
}
