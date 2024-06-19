<?php

namespace IPP\Student;

use DOMDocument;
use DOMElement;
use DOMNode;
use IPP\Core\ReturnCode;

class XMLStructure
{
    // arrays of valid values
    /** @var string[] */
    private array $opcodes = ["CREATEFRAME", "PUSHFRAME", "POPFRAME", "RETURN", "BREAK","CALL", "LABEL", "JUMP", "DEFVAR", "POPS", "PUSHS", "WRITE", "EXIT", "DPRINT", "MOVE", "NOT", "INT2CHAR", "STRLEN", "TYPE", "READ", "ADD", "SUB", "MUL", "IDIV", "LT", "GT", "EQ", "AND", "OR", "STRI2INT", "CONCAT", "GETCHAR", "SETCHAR", "JUMPIFEQ", "JUMPIFNEQ"];
    /** @var string[] */
    private array $arg_names = ["arg1", "arg2", "arg3"];
    /** @var string[] */
    private array $arg_types = ["int", "bool", "string", "nil", "label", "type", "var"];

    // variables to check its existence
    private int $is_opcode = 0;
    private int $is_order = 0;
    private int $is_lang = 0;
    
    // array for order values
    /** @var string[] */
    private array $val_order = [];

    // variables to save in $args[] array
    private string $arg_name = '';
    private string $arg_type = '';
    private string $arg_val = '';

    // needed information for processing in Instructions.php
    private int $order;
    private string $opcode;
    // array contains argument nodeName, nodeValue and nodeAttr value
    /** @var array<string[]> */
    private array $args = [];

    // public list of all instructions and its information
    /** @var array<array{string, array<string[]>, int}> */
    public array $inst_list = [];

    // checks whole XML structure
    public function checkStruct(DOMDocument $dom): void {
        $program = $dom->documentElement;
        if ($program === null) {
            throw new ErrorException("Invalid XML source structure.", ReturnCode::INVALID_SOURCE_STRUCTURE);
        }
        if ($program->nodeName !== "program") {
            throw new ErrorException("Invalid XML source structure.", ReturnCode::INVALID_SOURCE_STRUCTURE);
        }
        $this->checkProgAttr($program);
        $this->checkProgNodes($program);
    }

    // goes through every child-node of a program
    private function checkProgNodes(DOMElement $program): void {
        foreach ($program->childNodes as $instruction) {
            if ($instruction->nodeType === XML_ELEMENT_NODE) {
                if ($instruction->nodeName != "instruction") {
                    throw new ErrorException("Invalid XML source structure.", ReturnCode::INVALID_SOURCE_STRUCTURE);
                }

                if ($instruction->hasAttributes()) {
                    $this->checkInstAttr($instruction);
                }

                if ($instruction->hasChildNodes()) {
                    $this->checkInstNodes($instruction);
                }

                // needed information for Instruction.php
                $instructions = new Instructions($this->opcode, $this->args, $this->order);
                // checks instruction parameters, values
                $this->inst_list[] = $instructions->checkInst();
                $this->args = []; // resets array
            }
        }
    }

    // checks attributes in program
    private function checkProgAttr(DOMElement $program): void {
        $prog_attrs = $program->attributes;
        foreach ($prog_attrs as $prog_attr) {
            if (!($prog_attr instanceof \DOMAttr)) {
                throw new ErrorException("Invalid XML source structure.", ReturnCode::INVALID_SOURCE_STRUCTURE);
            }
            $prog_name = $prog_attr->nodeName;
            $prog_val = $prog_attr->nodeValue;
            if ($prog_name === "language") {
                $this->is_lang++;
                if ($prog_val !== "IPPcode24") {
                    throw new ErrorException("Invalid XML source structure.", ReturnCode::INVALID_SOURCE_STRUCTURE);
                }
            } elseif (!in_array($prog_name, ["language", "name", "description"])) {
                throw new ErrorException("Invalid XML source structure.", ReturnCode::INVALID_SOURCE_STRUCTURE);
            }
        }

        // in case attribute is repeated or missing
        if ($this->is_lang !== 1) {
            throw new ErrorException("Invalid XML source structure.", ReturnCode::INVALID_SOURCE_STRUCTURE);
        }
    }

    // goes through every child-node of an instruction
    private function checkInstNodes(DOMNode $instruction): void {
        foreach ($instruction->childNodes as $arg) {
            if ($arg->nodeType === XML_ELEMENT_NODE) {
                // argument order
                if (!in_array($arg->nodeName, $this->arg_names)) {
                    throw new ErrorException("Invalid XML source structure.", ReturnCode::INVALID_SOURCE_STRUCTURE);
                }
                // saves for later processing
                $this->arg_name = $arg->nodeName;
                if ($arg->nodeValue === null) {
                    throw new ErrorException("Invalid XML source structure.", ReturnCode::INVALID_SOURCE_STRUCTURE);
                }
                $this->arg_val = $arg->nodeValue;
            }

            if ($arg->hasAttributes()) {
                $this->checkArgAttr($arg);
            }
        }
    }

    // checks all attributes in one instruction
    private function checkInstAttr(DOMNode $instruction): void {
        $in_attrs = $instruction->attributes;
        if (!($in_attrs instanceof \DOMNamedNodeMap)) {
            throw new ErrorException("Invalid XML source structure.", ReturnCode::INVALID_SOURCE_STRUCTURE);
        }
        foreach ($in_attrs as $in_attr) {
            if (!($in_attr instanceof \DOMAttr)) {
                throw new ErrorException("Invalid XML source structure.", ReturnCode::INVALID_SOURCE_STRUCTURE);
            }
            $in_name = $in_attr->nodeName;
            $in_val = $in_attr->nodeValue;
            if ($in_name === "opcode") {
                $this->is_opcode++;
                if (!in_array(strtoupper((string)$in_val), $this->opcodes)) {
                    throw new ErrorException("Invalid XML source structure.", ReturnCode::INVALID_SOURCE_STRUCTURE);
                }
                // saves for later processing
                if ($in_val === null) {
                    throw new ErrorException("Invalid XML source structure.", ReturnCode::INVALID_SOURCE_STRUCTURE);
                }
                $this->opcode = $in_val;
                
            } else if ($in_name === "order") {
                $this->is_order++;
                if ((int)$in_val < 1) {
                    throw new ErrorException("Invalid XML source structure.", ReturnCode::INVALID_SOURCE_STRUCTURE);
                }

                // check for duplicity
                foreach ($this->val_order as $value) {
                    if ($in_val === $value) {
                        throw new ErrorException("Invalid XML source structure.", ReturnCode::INVALID_SOURCE_STRUCTURE);
                    }
                }
                if ($in_val === null) {
                    throw new ErrorException("Invalid XML source structure.", ReturnCode::INVALID_SOURCE_STRUCTURE);
                }
                // saves value in a array
                $this->val_order[] = $in_val;
                // saves for later processing
                $this->order = (int) $in_val;

            } else {
                throw new ErrorException("Invalid XML source structure.", ReturnCode::INVALID_SOURCE_STRUCTURE);
            }
        }
        
        // in case if attributes are repeated or missing
        if ($this->is_opcode !== 1 || $this->is_order !== 1) {
            throw new ErrorException("Invalid XML source structure.", ReturnCode::INVALID_SOURCE_STRUCTURE);
        }

        // resets for next instruction
        $this->is_opcode = 0;
        $this->is_order = 0;
    }

    // checks attribute in each argument
    private function checkArgAttr(DOMNode $arg): void {
        $arg_attrs = $arg->attributes;
        if (!($arg_attrs instanceof \DOMNamedNodeMap)) {
            throw new ErrorException("Invalid XML source structure.", ReturnCode::INVALID_SOURCE_STRUCTURE);
        }
        foreach ($arg_attrs as $arg_attr) {
            if (!($arg_attr instanceof \DOMAttr)) {
                throw new ErrorException("Invalid XML source structure.", ReturnCode::INVALID_SOURCE_STRUCTURE);
            }
            $arg_name = $arg_attr->nodeName;
            $arg_val = $arg_attr->nodeValue;
            // argument type
            if ($arg_name !== "type" || !in_array($arg_val, $this->arg_types)) {
                throw new ErrorException("Invalid XML source structure.", ReturnCode::INVALID_SOURCE_STRUCTURE);
            }
            if ($arg_val === null) {
                throw new ErrorException("Invalid XML source structure.", ReturnCode::INVALID_SOURCE_STRUCTURE);
            }
            $this->arg_type = $arg_val;
        }
        // saves for later processing
        $this->args[] = [$this->arg_name, $this->arg_type, $this->arg_val];
    }
}