<?php

class ProcessTest extends PHPUnit_Framework_TestCase
{
    /** @var  \mre\Process */
    private $oProcess;

    protected function setUp()
    {
    }

    public function testCorrectCommand()
    {
        $this->oProcess = new \mre\Process('cat');
        $this->assertEquals('cat', $this->oProcess->getCommand());
    }

    public function testIncorrectCommand()
    {
        $this->oProcess = new \mre\Process('PresumablyThereIsNoSuchCommandInYourPATH');
        $this->oProcess->send('tewst');
    }

}
