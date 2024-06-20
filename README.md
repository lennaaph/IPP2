# Principles of programming languages - 2.project

## XML Code Interpreter

### Description:
This project implements an interpreter for IPPcode24 using PHP 8.3. The interpreter reads an XML representation of a program, interprets it, and produces the corresponding output. The interpretation is based on the `ipp-core` framework, which aids in handling input and output, parameter processing, and some core functionalities. 

### Usage:
To run the interpreter, use the following command:
```bash
php8.3 interpret.php [options]
```

### Options:
- `--help`: Displays the help message and exits with code 0.
- `--source=<file>`: Specifies the input file containing the XML representation of the source code.
- `--input=<file>`: Specifies the input file with additional inputs required for interpreting the source code.

*At least one of the `--source` or `--input` parameters must be provided. If one is missing, the interpreter reads from the standard input.*

### Object-Oriented Design:
- **Framework Usage:** The `ipp-core` framework is designed in an object-oriented manner and must be used accordingly. Your extensions and customizations should follow this paradigm.
- **Starting Point:** The implementation starts in the `student/Interpreter.php` file.

### Notes:
- Modify and create files only within the `student` directory, as this is the part of the project to be submitted.
