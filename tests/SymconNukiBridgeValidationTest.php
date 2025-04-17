<?php

declare(strict_types=1);

namespace tests;

use TestCaseSymconValidation;

include_once __DIR__ . '/stubs/Validator.php';

class SymconNukiBridgeValidationTest extends TestCaseSymconValidation
{
    public function testValidateLibrary(): void
    {
        $this->validateLibrary(__DIR__ . '/..');
    }

    public function testValidateModule_Bridge(): void
    {
        $this->validateModule(__DIR__ . '/../Bridge');
    }

    public function testValidateModule_Configurator(): void
    {
        $this->validateModule(__DIR__ . '/../Configurator');
    }

    public function testValidateModule_Discovery(): void
    {
        $this->validateModule(__DIR__ . '/../Discovery');
    }

    public function testValidateModule_Opener(): void
    {
        $this->validateModule(__DIR__ . '/../Opener');
    }

    public function testValidateModule_SmartLock(): void
    {
        $this->validateModule(__DIR__ . '/../SmartLock');
    }
}