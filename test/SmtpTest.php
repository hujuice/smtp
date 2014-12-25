<?php
use Hujuice\Smtp\Smtp;

class SmtpTestClass extends PHPUnit_Framework_TestCase
{
    /**
     * @expectedException Exception
     * @expectedExceptionMessage Unexpected response
     */
    public function testSmtpClassLoading()
    {
        $smtp = new Smtp('localhost');
        $this->assertInstanceOf(Smtp::class, $smtp);
    }

    public function testSend()
    {
        $smtp = new Smtp('localhost');
        $smtp->from('foo@example.com');
        $smtp->to('bar@example.com');
        $smtp->subject('Test');
        $smtp->text('Test');
        $res = $smtp->send();
        $this->assertStringStartsWith('Message queued for delivery', $res);
        echo $smtp->dump();
    }
}
