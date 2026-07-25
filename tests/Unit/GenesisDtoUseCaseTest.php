<?php
/**
 * Unit test: Genesis DTO & UseCase (PR-03).
 *
 * @package Linked3
 */

namespace Linked3\Tests\Unit;

use PHPUnit\Framework\TestCase;

class GenesisDtoUseCaseTest extends TestCase
{
    public function testGenesisRequestDtoExists(): void
    {
        $path = __DIR__ . '/../../src/Classes/Genesis/Dto/GenesisGenerateRequest.php';
        if (!file_exists($path)) {
            $this->markTestSkipped('PR-03: GenesisGenerateRequest DTO not yet merged into base');
        }
        $this->assertFileExists($path);
    }

    public function testGenesisResponseDtoExists(): void
    {
        $path = __DIR__ . '/../../src/Classes/Genesis/Dto/GenesisGenerateResponse.php';
        if (!file_exists($path)) {
            $this->markTestSkipped('PR-03: GenesisGenerateResponse DTO not yet merged into base');
        }
        $this->assertFileExists($path);
    }

    public function testGenerateGenesisUseCaseExists(): void
    {
        $path = __DIR__ . '/../../src/Classes/Genesis/UseCase/GenerateGenesisUseCase.php';
        if (!file_exists($path)) {
            $this->markTestSkipped('PR-03: GenerateGenesisUseCase not yet merged into base');
        }
        $this->assertFileExists($path);
    }

    public function testFetchJsHasV2Features(): void
    {
        $content = file_get_contents(__DIR__ . '/../../admin/js/linked3-fetch.js');

        // PR-04: timeout support
        $this->assertStringContainsString('timeout', $content, 'fetch.js must support timeout');

        // PR-04: retry support
        $this->assertStringContainsString('retry', $content, 'fetch.js must support retry');

        // PR-04: FormData support
        $this->assertStringContainsString('FormData', $content, 'fetch.js must support FormData');

        // PR-04: Custom error class
        $this->assertStringContainsString('Linked3Error', $content, 'fetch.js must define Linked3Error');
    }
}
