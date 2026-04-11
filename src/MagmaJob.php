<?php
namespace irwinlopez1023\Magma4telegram;

abstract class MagmaJob
{
    protected Magma $magma;
    protected static bool $magmaRunnerLogging = false;

    public function __construct(Magma $magma)
    {
        $this->magma = $magma;
    }

    public function getMagma(): Magma
    {
        return $this->magma;
    }

    abstract public function handle(array $payload): void;
}
