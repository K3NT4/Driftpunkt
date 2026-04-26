<?php

namespace App;

use App\Infrastructure\Database\DatabaseUrlRequirement;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function __construct(string $environment, bool $debug)
    {
        DatabaseUrlRequirement::assertSatisfied($environment, self::databaseUrl());

        parent::__construct($environment, $debug);
    }

    private static function databaseUrl(): ?string
    {
        $databaseUrl = $_SERVER['DATABASE_URL'] ?? $_ENV['DATABASE_URL'] ?? getenv('DATABASE_URL');

        return false === $databaseUrl ? null : (string) $databaseUrl;
    }
}
