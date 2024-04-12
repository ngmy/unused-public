<?php

declare(strict_types=1);

namespace TomasVotruba\UnusedPublic\ValueObject;

final readonly class LocalAndExternalMethodCallReferences
{
    /**
     * @param MethodCallReference[] $localMethodCallReferences
     * @param MethodCallReference[] $externalMethodCallReferences
     */
    public function __construct(
        private array $localMethodCallReferences,
        private array $externalMethodCallReferences,
    ) {
    }

    /**
     * @return MethodCallReference[]
     */
    public function getLocalMethodCallReferences(): array
    {
        return $this->localMethodCallReferences;
    }

    /**
     * @return MethodCallReference[]
     */
    public function getExternalMethodCallReferences(): array
    {
        return $this->externalMethodCallReferences;
    }
}
