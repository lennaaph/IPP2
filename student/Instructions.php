<?php

namespace IPP\Student;

use IPP\Core\ReturnCode;

class Instructions
{
    // instruction information
    private int $order;
    private string $opcode;
    /** @var array<string[]> */
    private array $args = [];

    // for checking its existence
    private int $is_arg1 = 0;
    private int $is_arg2 = 0;
    private int $is_arg3 = 0;

    /**
     * @param string $opcode
     * @param array<string[]> $args
     * @param int $order
     */
    public function __construct(string $opcode, array $args, int $order) {
        $this->opcode = $opcode;
        $this->args = $args;
        $this->order = $order;
    }

    // checks instruction
    /** @return array{string, array<string[]>, int} */
    public function checkInst(): array {
        // checks its argument nodeNames first
        $this->checkArgs();
        // sort arguments for easier approach
        sort($this->args);
        // return instruction information in a array for better approach
        return [$this->opcode, $this->args, $this->order];
    }

    private function checkArgs(): void {
        // goes through arguments in instruction
        foreach ($this->args as $arg) {
            if ($arg[0] === "arg1") {
                $this->is_arg1++;
            } elseif ($arg[0] === "arg2") {
                $this->is_arg2++;
            } elseif ($arg[0] === "arg3") {
                $this->is_arg3++;
            }
        }
        // checks if its repeated or missing
        $this->checkArgNames();
    }

    // checks correct existence of an argument nodeName
    private function checkArgNames(): void {
        $len = count($this->args);
        $arg_len = $this->is_arg1 + $this->is_arg2 + $this->is_arg3;
        if ($len !== $arg_len) {
            throw new ErrorException("Invalid XML source structure.", ReturnCode::INVALID_SOURCE_STRUCTURE);
        }

        switch ($len) {
            case 1:
                if ($this->is_arg1 !== 1) {
                    throw new ErrorException("Invalid XML source structure.", ReturnCode::INVALID_SOURCE_STRUCTURE);
                }
                break;
            case 2:
                if ($this->is_arg1 !== 1 || $this->is_arg2 !== 1) {
                    throw new ErrorException("Invalid XML source structure.", ReturnCode::INVALID_SOURCE_STRUCTURE);
                }
                break;
            case 3:
                if ($this->is_arg1 !== 1 || $this->is_arg2 !== 1 || $this->is_arg3 !== 1) {
                    throw new ErrorException("Invalid XML source structure.", ReturnCode::INVALID_SOURCE_STRUCTURE);
                }
                break;
        }
    }
}