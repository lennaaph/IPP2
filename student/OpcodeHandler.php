<?php

namespace IPP\Student;

use IPP\Core\ReturnCode;
use IPP\Core\Interface\OutputWriter;
use IPP\Core\Interface\InputReader;

class OpcodeHandler extends Frames
{
    public function __construct(array $list, InputReader $input, OutputWriter $stdout, OutputWriter $stderr) {
        // creates parents constructor
        parent::__construct($list);
        $this->global_frame = new GlobalFrame();
        $this->stdout_writer = $stdout;
        $this->stderr_writer = $stderr;
        $this->input_reader = $input;
    }

    protected function opcodeHandler(): int {
        // gets all labels before executing the program
        $this->getAllLabels();
        $done_inst = 0;
        // program execute
        for ($index = 0; $index < count($this->inst_list); $index++) {
            $instruction = $this->inst_list[$index];
            // access elements of each instruction
            $opcode = $instruction[0];
            $args = $instruction[1];
            $order = $instruction[2];
            $done_inst++;

            // process arguments
            $arg_val = "";
            $arg_info = [];
            foreach ($args as $arg) {
                $arg_val = $arg[2];
                $arg_info = $arg;
            }

            switch (strtoupper($opcode)) {
                case "MOVE":
                    $this->move($args);
                    break;
                case "CREATEFRAME":
                    $this->createFrame();
                    break;
                case "PUSHFRAME":
                    $this->pushFrame();
                    break;
                case "POPFRAME":
                    $this->popFrame();
                    break;
                case "DEFVAR":
                    $this->defVar($arg_val);
                    break;
                case "CALL":
                    $index = $this->call($arg_val, $index);
                    break;
                case "RETURN":
                    $index = $this->return();
                    break;
                case "PUSHS":
                    $this->pushs($arg_info);
                    break;
                case "POPS":
                    $this->pops($arg_info);
                    break;
                case "ADD":
                    $this->add($args);
                    break;
                case "SUB":
                    $this->sub($args);
                    break;
                case "MUL":
                    $this->mul($args);
                    break;
                case "IDIV":
                    $this->idiv($args);
                    break;
                case "LT":
                    $this->lt($args);
                    break;
                case "GT":
                    $this->gt($args);
                    break;
                case "EQ":
                    $this->eq($args);
                    break;
                case "AND":
                    $this->and($args);
                    break;
                case "OR":
                    $this->or($args);
                    break;
                case "NOT":
                    $this->not($args);
                    break;
                case "INT2CHAR":
                    $this->int2char($args);
                    break;
                case "STRI2INT":
                    $this->stri2int($args);
                    break;
                case "READ":
                    $this->read($args);
                    break;
                case "WRITE":
                    $this->write($arg_info);
                    break;
                case "CONCAT":
                    $this->concat($args);
                    break;
                case "STRLEN":
                    $this->strLen($args);
                    break;
                case "GETCHAR":
                    $this->getChar($args);
                    break;
                case "SETCHAR":
                    $this->setChar($args);
                    break;
                case "TYPE":
                    $this->type($args);
                    break;
                case "JUMP":
                    $index = $this->jump($arg_val);
                    break;
                case "JUMPIFEQ":
                    $index = $this->jumpIfEq($args, $index);
                    break;
                case "JUMPIFNEQ":
                    $index = $this->jumpIfNeq($args, $index);
                    break;
                case "EXIT":
                    return $this->exit($arg_info);
                case "DPRINT":
                    $this->dprint($arg_info);
                    break;
                case "BREAK":
                    $this->break((string)$order, (string)$done_inst);
                    break;
                default:
                    break;
            }
        }
        return ReturnCode::OK;
    }

    private function getAllLabels(): void {
        for ($index = 0; $index < count($this->inst_list); $index++) {
            $instruction = $this->inst_list[$index];
            // gets valuable information
            $opcode = $instruction[0];
            $args = $instruction[1];
            $arg_val = "";
            foreach ($args as $arg) {
                $arg_val = $arg[2];
            }
            // saves all labels in a array
            if ($opcode === "LABEL") {
                $this->label($arg_val, $index);
            }
        }
    }

    private function createFrame(): void {
        // checks if a temporary frame already exists
        if (isset($this->tmp_frame)) {
            // deletes old temporary frame
            unset($this->tmp_frame);
        }
        // creates new temporary frame
        $this->tmp_frame = new LocalFrame();
    }

    private function pushFrame(): void {
        // checks if a temporary frame exists
        if (!isset($this->tmp_frame)) {
            throw new ErrorException("Attempted to access undefined frame.", ReturnCode::FRAME_ACCESS_ERROR);
        }
        // moves temporary frame onto the local frame stack
        $this->local_frame = clone $this->tmp_frame;
        array_push($this->local_stack, $this->local_frame);

        // deletes temporary frame
        unset($this->tmp_frame);
    }

    private function popFrame(): void {
        // checks if a temporary frame exists
        if (!isset($this->tmp_frame)) {
            $this->tmp_frame = new LocalFrame();
        }
        // pops the top frame from the local stack
        $pop_frame = array_pop($this->local_stack);
        // checks if local stack is empty
        if ($pop_frame === null) {
            throw new ErrorException("Attempted to access undefined frame.", ReturnCode::FRAME_ACCESS_ERROR);
        }
        // temporary frame now contains pop frame
        $this->tmp_frame = $pop_frame;

        // changes to the local frame
        if (empty($this->local_stack)) {
            // no local frame exists so it deletes local frame
            unset($this->local_frame);
        } else {
            // gets last local frame
            $this->local_frame = end($this->local_stack);
        }
    }

    private function defVar(string $var): void {
        $variable = preg_replace("/\s+/", "", $var);
        // divide into two parts
        [$frame, $var_name] = explode('@', (string)$variable);

        // saves variable based on provided frame
        switch ($frame){
            case "GF":
                // checks if the variable is already defined in global frame
                if ($this->global_frame->isDefined($var_name)) {
                    throw new ErrorException("Variable redefinition.", ReturnCode::SEMANTIC_ERROR);
                }
                // save new variable in a frame
                $this->global_frame->defineVar($var_name);
                break;
            case "LF":
                if (!isset($this->local_frame)) {
                    throw new ErrorException("Attempted to access undefined frame.", ReturnCode::FRAME_ACCESS_ERROR);
                }
                // checks if the variable is already defined in local frame
                if ($this->local_frame->isDefined($var_name)) {
                    throw new ErrorException("Variable redefinition.", ReturnCode::SEMANTIC_ERROR);
                }
                // save new variable in a frame
                $this->local_frame->defineVar($var_name);
                break;
            case "TF":
                // echo "save temporary\n";
                if (!isset($this->tmp_frame)) {
                    throw new ErrorException("Attempted to access undefined frame.", ReturnCode::FRAME_ACCESS_ERROR);
                }
                // checks if the variable is already in temporary frame
                if ($this->tmp_frame->isDefined($var_name)) {
                    throw new ErrorException("Variable redefinition.", ReturnCode::SEMANTIC_ERROR);
                }
                // save new variable in a frame
                $this->tmp_frame->defineVar($var_name);
                break;
        }
    }

    /** @param array<string[]> $args */
    private function move(array $args): void {
        // checks if variable is defined
        $this->checkDefVar($args[0][2]);
        // initialize the variable
        $this->setVar($args[0][2], $this->moveSymb($args[1]));
    }

    /** gets symbol in an array with a type and value
     * @param array<string> $arg
     * @return array<string>
    */
    private function moveSymb(array $arg): array {
        if ($arg[1] === "var") {
            // checks if variable is defined
            return $this->checkVar($arg);
        } else {
        // symbol is a constant [type, value]
            $this->checkSymb($arg);
            return [$arg[1], $arg[2]];
        }
    }

    /** @param array<string> $var_values */
    private function setVar(string $arg, array $var_values): void {
        $var = preg_replace("/\s+/", "", $arg);

        [$frame, $var_name] = explode('@', (string)$var);
        switch ($frame){
            case "GF":
                $this->global_frame->setValue($var_name, $var_values);
                break;
            case "LF":
                $this->local_frame->setValue($var_name, $var_values);
                break;
            case "TF":
                $this->tmp_frame->setValue($var_name, $var_values);
                break;
        }
    }

    // checks if a variable is defined
    private function checkDefVar(string $arg): void {
        $var = preg_replace("/\s+/", "", $arg);
        [$frame, $var_name] = explode('@', (string)$var);
        switch ($frame){
            case "GF":
                // checks if the variable is defined in global frame
                if (!$this->global_frame->isDefined($var_name)) {
                    throw new ErrorException("Variable is not defined.", ReturnCode::VARIABLE_ACCESS_ERROR);
                }
                break;
            case "LF":
                if (!isset($this->local_frame)) {
                    throw new ErrorException("Attempted to access undefined frame.", ReturnCode::FRAME_ACCESS_ERROR);
                }
                // checks if the variable is defined in global or local frame
                if (!$this->global_frame->isDefined($var_name) && !$this->local_frame->isDefined($var_name)) {
                    throw new ErrorException("Variable is not defined.", ReturnCode::VARIABLE_ACCESS_ERROR);
                }
                break;
            case "TF":
                if (!isset($this->tmp_frame)) {
                    throw new ErrorException("Attempted to access undefined frame.", ReturnCode::FRAME_ACCESS_ERROR);
                }
                // checks if the variable is defined in global or temporary frame
                if (!$this->global_frame->isDefined($var_name) && !$this->tmp_frame->isDefined($var_name)) {
                    throw new ErrorException("Variable is not defined.", ReturnCode::VARIABLE_ACCESS_ERROR);
                }
                break;
        }
    }

    // checks operand type
    /** @param array<string> $value */
    private function checkSymb(array $value): void {
        $type = preg_replace("/\s+/", "", $value[0]);
        $value = preg_replace("/\s+/", "", $value[1]);
        switch ($type) {
            case "int":
                if (!preg_match("/^[-+]?(\d+|(0x[0-9a-fA-F]+)|(0o[0-7]+))$/", (string)$value)) {
                    throw new ErrorException("Incorrect operand type value.", ReturnCode::OPERAND_TYPE_ERROR);
                }
                break;
            case "bool":
                if (!preg_match("/^(true|false)$/", (string)$value)) {
                    throw new ErrorException("Incorrect operand type value.", ReturnCode::OPERAND_TYPE_ERROR);
                }
                break;
            case "string":
                if (!preg_match("/^([^\s#\\\\]|\\\\[0-9]{3})*$/", (string)$value)) {
                    throw new ErrorException("Incorrect operand type value.", ReturnCode::OPERAND_TYPE_ERROR);
                }
                break;
            case "nil":
                if (!preg_match("/^(nil)$/", (string)$value)) {
                    throw new ErrorException("Incorrect operand type value.", ReturnCode::OPERAND_TYPE_ERROR);
                }
                break;
        }
    }

    private function call(string $label_name, int $index): int {
        $this->checkLabelDef($label_name);
        // saves position in a stack
        array_push($this->call_stack, $index);
        // jumps to the label
        return $this->label_list[$label_name];
    }

    private function return(): int {
        if (empty($this->call_stack)) {
            throw new ErrorException("Access to empty stack.", ReturnCode::VALUE_ERROR);
        }
        $index = array_pop($this->call_stack);
        return $index;
    }

    /** @param array<string> $arg */
    private function pushs(array $arg): void {
        $symb = $this->getSymb($arg);
        array_push($this->data_stack, $symb);
    }
    
    /** @param array<string> $arg */
    private function pops(array $arg): void {
        // var_dump($this->data_stack);
        if (empty($this->data_stack)) {
            throw new ErrorException("Access to empty stack.", ReturnCode::VALUE_ERROR);
        }
        $this->checkDefVar($arg[2]);
        $value = array_pop($this->data_stack);
        if (isset($value)) {
            $this->setVar($arg[2], [$value[0], $value[1]]);
        }
    }

    /** checks for arithmetic operations
     * @param array<string[]> $arg 
     * @return array<int>
    */
    private function checkArith(array $arg): array {
        // checks if variable is defined
        $this->checkDefVar($arg[0][2]);
        // gets [type, value] from symbols
        $symb1 = $this->getSymb($arg[1]);
        $symb2 = $this->getSymb($arg[2]);

        // both symbols has to be integer
        if ($this->isInt($symb1[0]) && $this->isInt($symb2[0])) {
            // [int, int]
            return [$this->convert2Int($symb1[1]), $this->convert2Int($symb2[1])];
        } else {
            throw new ErrorException("Incorrect operand type value.", ReturnCode::OPERAND_TYPE_ERROR);
        }
    } 

    private function isInt(string $type): bool {
        if ($type !== "int") {
            return false;
        }
        return true;
    }
    private function isBool(string $type): bool {
        if ($type !== "bool") {
            return false;
        }
        return true;
    }
    private function isString(string $type): bool {
        if ($type !== "string") {
            return false;
        }
        return true;
    }
    private function isNil(string $type): bool {
        if ($type !== "nil") {
            return false;
        }
        return true;
    }

    /** gets type and value from variable or constant
     * @param array<string> $arg
     * @return array<string>
    */  
    private function getSymb(array $arg): array {
        if ($arg[1] === "var") {
            // [type, value]
            return $this->checkVar($arg);
        } else {
            $type = preg_replace("/\s+/", "", $arg[1]);
            $value = preg_replace("/\s+/", "", $arg[2]);
            $this->checkSymb([(string)$type,(string)$value]);
            // [type, value]
            return [(string)$type,(string)$value];
        }
    }

    /** gets type and value from variable
     * @param array<string> $arg
     * @return array<string>
    */    
    private function checkVar(array $arg): array {
        $this->checkDefVar($arg[2]);

        $arg = preg_replace("/\s+/", "", $arg[2]);
        [$frame, $var_name] = explode('@', (string)$arg);

        switch ($frame){
            case "GF":
                return $this->global_frame->getValue($var_name);
            case "LF":
                return $this->local_frame->getValue($var_name);
            case "TF":
                return $this->tmp_frame->getValue($var_name);
            default:
                throw new ErrorException("Attempted to access invalid frame.", ReturnCode::FRAME_ACCESS_ERROR);
        }
    }

    /** @param array<string[]> $arg */
    private function add(array $arg): void {
        // does checking and returns array with both symbols
        $symbols = $this->checkArith($arg);
        // add logic
        $add = $symbols[0] + $symbols[1];
        // sets value in variable
        $this->setVar($arg[0][2], ["int", (string) $add]);
    }

    /** @param array<string[]> $arg */
    private function sub(array $arg): void {
        // does checking and returns array with both symbols
        $symbols = $this->checkArith($arg);
        // sub logic
        $sub = $symbols[0] - $symbols[1];
        // sets value in variable
        $this->setVar($arg[0][2], ["int", (string) $sub]);
    }

    /** @param array<string[]> $arg */
    private function mul(array $arg): void {
        // does checking and returns array with both symbols
        $symbols = $this->checkArith($arg);
        // mul logic
        $mul = $symbols[0] * $symbols[1];
        // sets value in variable
        $this->setVar($arg[0][2], ["int", (string) $mul]);
    }

    /** @param array<string[]> $arg */
    private function idiv(array $arg): void {
        // does checking and returns array with both symbols
        $symbols = $this->checkArith($arg);
        // idiv logic
        if ($symbols[1] === 0) {
            throw new ErrorException("Division with zero.", ReturnCode::OPERAND_VALUE_ERROR);
        }
        $idiv = intval($symbols[0] / $symbols[1]);
        // sets value in variable
        $this->setVar($arg[0][2], ["int", (string) $idiv]);
    }

    /** @param array<string[]> $arg */
    private function lt(array $arg): void {
        // checks if variable is defined
        $this->checkDefVar($arg[0][2]);
        // gets [type, value] from symbols
        $symb1 = $this->getSymb($arg[1]);
        $symb2 = $this->getSymb($arg[2]);
        $types = $this->checkRelOp($symb1, $symb2);
        // lt logic
        $lt = false;
        switch ($symb1[0]) {
            case "int":    
                $lt = $types[0] < $types[1];
                break;
            case "bool":
                // only when false < true
                if (!$types[0] && $types[1]) {
                    $lt = true;
                }
                break;
            case "string":
                $tmp = strcmp((string)$types[0], (string)$types[1]);
                if ($tmp < 0) {
                    $lt = true;
                }
                break;
        }
        // sets value in variable
        $this->setVar($arg[0][2], ["bool", $this->bool2str($lt)]);
    }
    /** @param array<string[]> $arg */
    private function gt(array $arg): void {
        // checks if variable is defined
        $this->checkDefVar($arg[0][2]);
        // gets [type, value] from symbols
        $symb1 = $this->getSymb($arg[1]);
        $symb2 = $this->getSymb($arg[2]);
        $values = $this->checkRelOp($symb1, $symb2);
        // gt logic
        $gt = false;
        switch ($symb1[0]) {
            case "int":
                $gt = $values[0] > $values[1];
                break;
            case "bool":
                // only when true > false
                if ($values[0] && !$values[1]) {
                    $gt = true;
                }
                break;
            case "string":
                $tmp = strcmp((string)$values[0], (string)$values[1]);
                if ($tmp > 0) {
                    $gt = true;
                }
                break;
        }
        // sets value in variable
        $this->setVar($arg[0][2], ["bool", $this->bool2str($gt)]);
    }
    /** @param array<string[]> $arg */
    private function eq(array $arg): void {
        $this->checkDefVar($arg[0][2]);
        // gets [type, value] from symbols
        $symb1 = $this->getSymb($arg[1]);
        $symb2 = $this->getSymb($arg[2]);
        
        $eq = false;
        $nil1 = $this->isNil($symb1[0]);
        $nil2 = $this->isNil($symb2[0]);
        // eq logic
        if ($nil1 || $nil2) {
            // both nil
            if ($nil1 && $nil2) {
                $eq = true;
            }
        } else {
            // [value, value]
            $values = $this->checkRelOp($symb1, $symb2);
            $eq = $this->eqLogic($symb1[0], $values);
        }
        // sets value in variable
        $this->setVar($arg[0][2], ["bool", $this->bool2str($eq)]);
    }
    
    /** 
     * @param array<bool|int|string> $values
     */
    private function eqLogic(string $type, array $values): bool {
        switch ($type) {
            case "int":
                return ($values[0] === $values[1]);
            case "bool":
                if (($values[0] && $values[1]) || (!$values[0] && !$values[1])) {
                    return true;
                }
            case "string":
                $tmp = strcmp((string)$values[0], (string)$values[1]);
                if ($tmp === 0) {
                    return true;
                }
        }
        return false;
    }

    /** checks relational operations
     * @param array<string> $symb1 
     * @param array<string> $symb2
     * @return array<int|bool|string>
    */
    private function checkRelOp(array $symb1, array $symb2): array {
        if ($this->isInt($symb1[0]) && $this->isInt($symb2[0])) {
            // [int, int]
            return [$this->convert2Int($symb1[1]), $this->convert2Int($symb2[1])];
        } else if ($this->isBool($symb1[0]) && $this->isBool($symb2[0])) {
            // [bool, bool]
            return [$this->str2bool($symb1[1]),$this->str2bool($symb2[1])];
        } else if ($this->isString($symb1[0]) && $this->isString($symb2[0])) {
            // [string, string]
            return [$symb1[1], $symb2[1]];
        } else {
            throw new ErrorException("Incorrect operand type value.", ReturnCode::OPERAND_TYPE_ERROR);
        }
    }

    private function str2bool(string $value): bool {
        if ($value === "true") {
            return true;
        }
        return false;
    }

    private function bool2str(bool $value): string {
        if ($value) {
            return "true";
        }
        return "false";
    }

    /** @param array<string[]> $arg */
    private function and(array $arg): void {
        $symbols = $this->checkBoolOp($arg);
        // and logic
        $and = $symbols[0] && $symbols[1];
        // sets value in variable
        $this->setVar($arg[0][2], ["bool", $this->bool2str($and)]);
    }

    /** @param array<string[]> $arg */
    private function or(array $arg): void {
        $symbols = $this->checkBoolOp($arg);
        // or logic
        $or = $symbols[0] || $symbols[1];
        // sets value in variable
        $this->setVar($arg[0][2], ["bool", $this->bool2str($or)]);
    }

    /** @param array<string[]> $arg */
    private function not(array $arg): void {
        // checks if variable is defined
        $this->checkDefVar($arg[0][2]);
        // gets [type, value]
        $symb1 = $this->getSymb($arg[1]);

        // not logic
        if ($this->isBool($symb1[0])) {
            $not = !$this->str2bool($symb1[1]);

            // sets value in variable
            $this->setVar($arg[0][2], ["bool", $this->bool2str($not)]);
        } else {
            throw new ErrorException("Incorrect operand type value.", ReturnCode::OPERAND_TYPE_ERROR);
        }
    }

    /** checks boolean operations
     * @param array<string[]> $arg 
     * @return array<bool>
    */
    private function checkBoolOp(array $arg): array {
        // checks if variable is defined
        $this->checkDefVar($arg[0][2]);
        // gets [type, value] from symbols
        $symb1 = $this->getSymb($arg[1]);
        $symb2 = $this->getSymb($arg[2]);

        // both needs to be bool
        if ($this->isBool($symb1[0]) && $this->isBool($symb2[0])) {
            // [bool, bool]
            return [$this->str2bool($symb1[1]),$this->str2bool($symb2[1])];
        } else {
            throw new ErrorException("Incorrect operand type value.", ReturnCode::OPERAND_TYPE_ERROR);
        }
    }

    /** @param array<string[]> $arg */
    private function int2char(array $arg): void {
        $this->checkDefVar($arg[0][2]);
        // gets [type, value]
        $symb1 = $this->getSymb($arg[1]);

        // needs to be int
        if ($this->isInt($symb1[0])) {
            // int2char logic
            $int2char = mb_chr($this->convert2Int($symb1[1]), 'UTF-8');
            
            if ((bool)$int2char === false) {
                throw new ErrorException("Invalid Unicode code point.", ReturnCode::STRING_OPERATION_ERROR);
            }
            // sets value in variable
            $this->setVar($arg[0][2], ["string", $int2char]);
        } else {
            throw new ErrorException("Incorrect operand type value.", ReturnCode::OPERAND_TYPE_ERROR);
        }
    }

    private function convert2Int(string $symb): int {
        // decimal
        if (preg_match("/^[-+]?(\d+)$/", $symb)) {
            return intval($symb);
        // hexadecimal
        } else if (preg_match("/^[-+]?(0x[0-9a-fA-F]+)$/", $symb)) {
            return (int)hexdec($symb);
        // octal
        }else if (preg_match("/^[-+]?(0o[0-7]+)$/", $symb)) {
            return (int)octdec($symb);
        } else {
            throw new ErrorException("Incorrect operand type value.", ReturnCode::OPERAND_TYPE_ERROR);
        }
    }

    /** @param array<string[]> $arg */
    private function stri2int(array $arg): void {
        // checks if variable is defined
        $this->checkDefVar($arg[0][2]);
        // gets [type, value] from symbols
        $symb1 = $this->getSymb($arg[1]);
        $symb2 = $this->getSymb($arg[2]);

        // has to be a string and int
        if ($this->isString($symb1[0]) && $this->isInt($symb2[0])) {
            // gets and checks index value
            $index = $this->convert2Int($symb2[1]);
            if ((strlen($symb1[1]) > $index) && ($index >= 0)) {
                // stri2int logic
                $string = $symb1[1];
                $char = $string[$index];
                // converts string to int
                $stri2int = mb_ord($char, 'UTF-8');
                
                if ((bool)$stri2int === false) {
                    throw new ErrorException("Invalid Unicode code point.", ReturnCode::STRING_OPERATION_ERROR);
                }
                // sets value in variable
                $this->setVar($arg[0][2], ["int", (string)$stri2int]);
            } else {
                throw new ErrorException("Invalid index.", ReturnCode::STRING_OPERATION_ERROR);
            }
        } else {
            throw new ErrorException("Incorrect operand type value.", ReturnCode::OPERAND_TYPE_ERROR);
        }
    }

    /** @param array<string[]> $arg */
    private function read(array $arg): void {
        // checks if variable is defined
        $this->checkDefVar($arg[0][2]);
        // check type
        $type = $arg[1][2];
        $set_value = "";
        $set_type = "";
        if (preg_match("/^(int|bool|string)$/", $type)) {
            switch ($type) {
                case "int":
                    $tmp = $this->input_reader->readInt();
                    if ($tmp === null) {
                        $set_type = $set_value = "nil";
                    } else {
                        $set_type = "int";
                        $set_value = (string)$tmp;
                    }
                    break;
                case "bool":
                    $tmp = $this->input_reader->readBool();
                    if ($tmp === null) {
                        $set_type = $set_value = "nil";
                    } else {
                        $set_type = "bool";
                        $set_value = $this->bool2str($tmp);
                    }
                    break;
                case "string":
                    $tmp = $this->input_reader->readString();
                    if ($tmp === null) {
                        $set_type = $set_value = "nil";
                    } else {
                        $set_type = "string";
                        $set_value = $tmp;
                    }
                    break;
            }
            // sets value in variable
            $this->setVar($arg[0][2], [$set_type, $set_value]);
        } else {
            throw new ErrorException("Incorrect operand type value.", ReturnCode::OPERAND_TYPE_ERROR);
        }
    }
    
    /** @param array<string> $arg */
    private function write(array $arg): void {
        // [type, value]
        $symb = $this->getSymb($arg);
        $type = preg_replace("/\s+/", "", $symb[0]);
        $value = preg_replace("/\s+/", "", $symb[1]);
        switch ($type) {
            case "int":
            case "bool":
                $this->stdout_writer->writeString((string)$value);
                break;
            case "string":
                $string = $this->convertEscape((string)$value);
                $this->stdout_writer->writeString($string);
                break;
            case "nil":
                $this->stdout_writer->writeString('');
                break;
        }
    }

    private function convertEscape(string $string): string {
        $reg_escape = '/\\\\[0-9]{3}/';
        preg_match_all($reg_escape, $string, $found);
        if (!empty($found)) {
            // callback function to convert escape sequences
            $convert = function($matches) {
                $num = intval(str_replace("\\", "", $matches[0]));
                return mb_chr($num, 'UTF-8');
            };
            $string = preg_replace_callback($reg_escape, $convert, $string);
        }
        return (string)$string;
    }

    /** @param array<string[]> $arg */
    private function concat(array $arg): void {
        $this->checkDefVar($arg[0][2]);
        $symb1 = $this->getSymb($arg[1]);
        $symb2 = $this->getSymb($arg[2]);
    
        // both has to be string
        if ($this->isString($symb1[0]) && $this->isString($symb2[0])) {
            // concat logic
            $concat = $symb1[1] . $symb2[1];
            // sets value in variable
            $this->setVar($arg[0][2], ["string", $concat]);
        } else {
            throw new ErrorException("Incorrect operand type value.", ReturnCode::OPERAND_TYPE_ERROR);
        }
    }

    /** @param array<string[]> $arg */
    private function strLen(array $arg): void {
        $this->checkDefVar($arg[0][2]);
        // gets [type, value]
        $symb1 = $this->getSymb($arg[1]);

        // has to be string
        if ($this->isString($symb1[0])) {
            // strlen logic
            $strlen = strlen($symb1[1]);
            // sets value in variable
            $this->setVar($arg[0][2], ["int", (string)$strlen]);
        } else {
            throw new ErrorException("Incorrect operand type value.", ReturnCode::OPERAND_TYPE_ERROR);
        }
    }

    /** @param array<string[]> $arg */
    private function getChar(array $arg): void {
        $this->checkDefVar($arg[0][2]);
        $symb1 = $this->getSymb($arg[1]);
        $symb2 = $this->getSymb($arg[2]);

        // has to be string and int
        $type1 = preg_replace("/\s+/", "", $symb1[0]);
        $type2 = preg_replace("/\s+/", "", $symb2[0]);
        $symb1 = preg_replace("/\s+/", "", $symb1[1]);
        $symb2 = preg_replace("/\s+/", "", $symb2[1]);
        if ($this->isString((string)$type1) && $this->isInt((string)$type2)) {
            // gets and checks index value
            $index = $this->convert2Int((string)$symb2);
            if ((strlen((string)$symb1) > $index) && ($index >= 0)) {
                // getchar logic
                $string = (string)$symb1;
                $getchar = $string[$index];

                // sets value in variable
                $this->setVar($arg[0][2], ["string", $getchar]);
            } else {
                throw new ErrorException("Invalid index.", ReturnCode::STRING_OPERATION_ERROR);
            }
        } else {
            throw new ErrorException("Incorrect operand type value.", ReturnCode::OPERAND_TYPE_ERROR);
        }
    }

    /** @param array<string[]> $arg */
    private function setChar(array $arg): void {
        // checks if variable is defined
        $this->checkDefVar($arg[0][2]);
        // gets [type, value] from symbols
        $symb1 = $this->getSymb($arg[1]);
        $symb2 = $this->getSymb($arg[2]);
        // var [type, value]
        $var = $this->getSymb($arg[0]);

        // has to be int and string
        if ($this->isInt($symb1[0]) && $this->isString($symb2[0])) {
            // gets and checks index value
            $index = $this->convert2Int($symb1[1]);
            if ((strlen($var[1]) > $index) && ($index >= 0) && (!empty($symb2[1]))) {
                // setchar logic
                $setchar = $var[1];
                $setchar[$index] = $symb2[1][0];

                $this->setVar($arg[0][2], ["string", $setchar]);
            } else {
                throw new ErrorException("Invalid index.", ReturnCode::STRING_OPERATION_ERROR);
            }
        } else {
            throw new ErrorException("Incorrect operand type value.", ReturnCode::OPERAND_TYPE_ERROR);
        }
    }

    /** @param array<string[]> $arg */
    private function type(array $arg): void {
        $this->checkDefVar($arg[0][2]);
        // gets [type, value]
        $symb1 = $this->getSymbType($arg[1]);
        // sets value in variable
        $this->setVar($arg[0][2], ["string", $symb1[0]]);        
    }

    /**
     * @param array<string> $arg
     * @return array<string>
    */  
    private function getSymbType(array $arg): array {
        if ($arg[1] === "var") {
            return $this->checkVarType($arg);
        } else {
            $this->checkSymb([$arg[1],$arg[2]]);
            return [$arg[1],$arg[2]];
        }
    }

    /**
     * @param array<string> $arg
     * @return array<string>
    */    
    private function checkVarType(array $arg): array {
        $this->checkDefVar($arg[2]);
        [$frame, $var_name] = explode('@', $arg[2]);

        switch ($frame){
            case "GF":
                return $this->global_frame->getTypeValue($var_name);
            case "LF":
                return $this->local_frame->getTypeValue($var_name);
            case "TF":
                return $this->tmp_frame->getTypeValue($var_name);
            default:
                throw new ErrorException("Attempted to access invalid frame.", ReturnCode::FRAME_ACCESS_ERROR);
        }
    }

    private function label(string $label_name, int $index): void {
        if (array_key_exists($label_name, $this->label_list)) {
            throw new ErrorException("Label duplicity.", ReturnCode::SEMANTIC_ERROR);
        }
        $this->label_list[$label_name] = $index;
    }

    private function jump(string $label_name): int {
        $this->checkLabelDef($label_name);
        return $this->label_list[$label_name];
    }

    /** @param array<string[]> $arg */
    private function jumpIfEq(array $arg, int $index): int {
        $this->checkLabelDef($arg[0][2]);
        // gets [type, value] from symbols
        $symb1 = $this->getSymb($arg[1]);
        $symb2 = $this->getSymb($arg[2]);

        $nil1 = $this->isNil($symb1[0]);
        $nil2 = $this->isNil($symb2[0]);

        $jumpifeq = false;
        // jumpifeq logic
        if ($nil1 || $nil2) {
            // both nil
            if ($nil1 && $nil2) {
                $jumpifeq = true;
            }
        } else {
            $values = $this->checkLabelType($symb1, $symb2);
            $jumpifeq = $this->eqLogic($symb1[0], $values);
            
        }
        // jumps when it is equal
        if ($jumpifeq) {
            return $this->label_list[$arg[0][2]];
        }
        return $index;
    }
    /** @param array<string[]> $arg */
    private function jumpIfNeq(array $arg, int $index): int {
        $this->checkLabelDef($arg[0][2]);
        // gets [type, value] from symbols
        $symb1 = $this->getSymb($arg[1]);
        $symb2 = $this->getSymb($arg[2]);

        $nil1 = $this->isNil($symb1[0]);
        $nil2 = $this->isNil($symb2[0]);
        $jumpifneq = false;
        // jumpifneq logic
        if ($nil1 || $nil2) {
            // both not nil
            if (!($nil1 && $nil2)) {
                $jumpifneq = true;
            }
        } else {
            $types = $this->checkLabelType($symb1, $symb2);
            switch ($arg[1][1]) {
                case "int":
                    $jumpifneq = ($types[0] !== $types[1]);
                    break;
                case "bool":
                    if ((!$types[0] && $types[1]) || ($types[0] && !$types[1])) {
                        $jumpifneq = true;
                    }
                    break;
                case "string":
                    $tmp = strcmp((string)$types[0], (string)$types[1]);
                    if ($tmp !== 0) {
                        $jumpifneq = true;
                    }
                    break;
            }
        }
        // jumps when it is not equal
        if ($jumpifneq) {
            return $this->label_list[$arg[0][2]];
        }
        return $index;
    }

    // checks if label is defined
    private function checkLabelDef(string $label_name): void {
        if (!array_key_exists($label_name, $this->label_list)) {
            throw new ErrorException("Label is not defined.", ReturnCode::SEMANTIC_ERROR);
        }
    }

    /**
     * @param array<string> $symb1
     * @param array<string> $symb2
     * @return array<int|bool|string>
    */
    private function checkLabelType(array $symb1, array $symb2): array {
        if ($this->isInt($symb1[0]) && $this->isInt($symb2[0])) {
            // [int, int]
            return [$this->convert2Int($symb1[1]), $this->convert2Int($symb2[1])];
        } else if ($this->isBool($symb1[0]) && $this->isBool($symb2[0])) {
            // [bool, bool]
            return [$this->str2bool($symb1[1]),$this->str2bool($symb2[1])];
        } else if ($this->isString($symb1[0]) && $this->isString($symb2[0])) {
            // [string, string]
            return [$symb1[1], $symb2[1]];
        } else {
            throw new ErrorException("Incorrect operand type value.", ReturnCode::OPERAND_TYPE_ERROR);
        }
    }

    /** @param array<string> $arg */
    private function exit(array $arg): int {
        $symb = $this->getSymb($arg);
        $exit_num = $this->convert2Int($symb[1]);
        // has to be integer
        if (!$this->isInt($symb[0]) || $exit_num < 0 || $exit_num > 9) {
            throw new ErrorException("Exit number is not an integer.", ReturnCode::OPERAND_VALUE_ERROR);
        }
        return $exit_num;
    }

    /** @param array<string> $arg */
    private function dprint(array $arg): void {
        $symb = $this->getSymb($arg);
        switch ($symb[0]) {
            case "int":
            case "bool":
            case "string":
                $this->stderr_writer->writeString($symb[1]);
                break;
            case "nil":
                $this->stderr_writer->writeString('');
                break;
        }
    }

    private function break(string $order, string $done_inst): void {
        $this->stderr_writer->writeString("\n___________\nBREAK\n");
        $this->stderr_writer->writeString("GLOBAL FRAME:\n");
        $this->stderr_writer->writeString(print_r($this->global_frame, true));
        if (isset($this->local_frame)) {
            $this->stderr_writer->writeString("\nLOCAL FRAME:\n");
            $this->stderr_writer->writeString(print_r($this->local_frame, true));
        } else {
            $this->stderr_writer->writeString("\nLOCAL FRAME: UNSET\n");
        }
        if (isset($this->tmp_frame)) {
            $this->stderr_writer->writeString("\nTEMPORARY FRAME:\n");
            $this->stderr_writer->writeString(print_r($this->tmp_frame, true));
        } else {
            $this->stderr_writer->writeString("\nTEMPORARY FRAME: UNSET\n");
        }
        $this->stderr_writer->writeString("POSITION ORDER: $order\n");
        $this->stderr_writer->writeString("DONE INSTRUCTIONS: $done_inst\n");
    }
}