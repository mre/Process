<?php

class ProcessTest extends PHPUnit_Framework_TestCase
{
    /** @var  \mre\Process */
    private $oProcess;

    const COMMAND = 'cat';

    protected function setUp()
    {
        $this->oProcess = new \mre\Process(self::COMMAND);
    }

    public function testCorrectCommand()
    {
        $this->assertEquals(self::COMMAND, $this->oProcess->getCommand());
    }
}
