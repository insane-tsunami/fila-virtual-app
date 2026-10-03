<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

final class SmokeTest extends TestCase
{
    public function testAmbienteTemPhpSuportado(): void
    {
        $this->assertTrue(PHP_VERSION_ID >= 80300);
    }

    public function testAutoloadDasDependenciasFunciona(): void
    {
        $this->assertTrue(class_exists(\Slim\Factory\AppFactory::class));
        $this->assertTrue(class_exists(\Illuminate\Database\Capsule\Manager::class));
    }
}
