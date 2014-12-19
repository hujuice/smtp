<?php
use Hujuice\Smtp\Smtp;

class AutoloadTestClass extends PHPUnit_Framework_TestCase
{
    /**
     * @expectedException Exception
     * @expectedExceptionMessage Message "QUIT" NOT accepted!
     */
    public function testSmtpClassLoading()
    {
        $smtp = new Smtp('localhost');
        $this->assertInstanceOf(Smtp::class, $smtp);
    }
}
