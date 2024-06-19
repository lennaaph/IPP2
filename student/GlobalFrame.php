<?php

namespace IPP\Student;

use IPP\Core\ReturnCode;

class GlobalFrame  {
    /** @var array<string[]|null> */
    private array $var_list = [];
    
    public function __construct() {
    }

    public function defineVar(string $var_name): void {
        $this->var_list[$var_name] = null;
    }

    public function isDefined(string $var_name): bool {
        return array_key_exists($var_name, $this->var_list);
    }

    /** @return array<string> */
    public function getValue(string $var_name): array {
        if (!isset($this->var_list[$var_name])) {
            throw new ErrorException("Variable is not initialized.", ReturnCode::VALUE_ERROR);
        }
        return $this->var_list[$var_name];
    }

    /** @param array<string> $var_values*/
    public function setValue(string $var_name, array $var_values): void {
        $this->var_list[$var_name] = $var_values;
    }

    /** @return array<string> */
    public function getTypeValue(string $var_name): array {
        if (!isset($this->var_list[$var_name])) {
            return ["",""];
        }
        return $this->var_list[$var_name];
    }
}