<?php

namespace IPP\Student;

use IPP\Core\Interface\InputReader;
use IPP\Core\Interface\OutputWriter;

class Frames {
    // output writer
    protected OutputWriter $stdout_writer;
    protected OutputWriter $stderr_writer;
    protected InputReader $input_reader;

    // frames
    protected GlobalFrame $global_frame;
    protected LocalFrame $local_frame;
    protected LocalFrame $tmp_frame;

    // stack of local frames
    /** @var LocalFrame[] */
    protected array $local_stack = [];
    // list of instructions
    /** @var array<array{string, array<string[]>, int}> */
    protected array $inst_list = [];
    // list of labels
    /** @var array<int> */
    protected array $label_list = [];
    // stack of data
    /** @var array<string[]> */
    protected array $data_stack = [];
    // stack of called labels
    /** @var array<int> */
    protected array $call_stack = [];

    /** @param array<array{string, array<string[]>, int}> $list */
    public function __construct(array $list) {
        // sorts the array based on order number
        usort($list, function ($a_order, $b_order) {
            // function compares order number of each inner array
            return $a_order[2] <=> $b_order[2]; 
        });
        $this->inst_list = $list;
    }

    public function frameLogic(InputReader $input, OutputWriter $stdout, OutputWriter $stderr): int {
        $opcode_handler = new OpcodeHandler($this->inst_list, $input, $stdout, $stderr);
        return $opcode_handler->opcodeHandler();
    }
}