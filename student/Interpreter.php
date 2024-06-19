<?php

namespace IPP\Student;

use IPP\Core\ReturnCode;
use IPP\Core\AbstractInterpreter;

class Interpreter extends AbstractInterpreter
{
    public function execute(): int
    {
        $dom = $this->source->getDOMDocument();
        $xml_struct = new XMLStructure();
        $xml_struct->checkStruct($dom);

        $frame = new Frames($xml_struct->inst_list);
        return $frame->frameLogic($this->input, $this->stdout, $this->stderr);
    }
}
