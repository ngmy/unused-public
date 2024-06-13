<?php

declare(strict_types=1);

namespace TomasVotruba\UnusedPublic\Rules;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\CollectedDataNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use TomasVotruba\UnusedPublic\Collectors\PublicClassMethodCollector;
use TomasVotruba\UnusedPublic\Configuration;
use TomasVotruba\UnusedPublic\NodeCollectorExtractor;
use TomasVotruba\UnusedPublic\Templates\TemplateMethodCallsProvider;
use TomasVotruba\UnusedPublic\Templates\UsedMethodAnalyzer;
use TomasVotruba\UnusedPublic\ValueObject\MethodCallReference;

/**
 * @see \TomasVotruba\UnusedPublic\Tests\Rules\RelativeUnusedPublicClassMethodRule\RelativeUnusedPublicClassMethodRuleTest
 */
final readonly class RelativeUnusedPublicClassMethodRule implements Rule
{
    /**
     * @var string
     *
     * @api
     */
    public const ERROR_MESSAGE = 'Found %.1f %% of public methods as unused. Reduce it under %.1f %%';

    public function __construct(
        private Configuration $configuration,
        private UsedMethodAnalyzer $usedMethodAnalyzer,
        private TemplateMethodCallsProvider $templateMethodCallsProvider,
        private NodeCollectorExtractor $nodeCollectorExtractor,
    ) {
    }

    public function getNodeType(): string
    {
        return CollectedDataNode::class;
    }

    /**
     * @param CollectedDataNode $node
     * @return RuleError[]
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (! $this->configuration->isUnusedRelativeMethodEnabled()) {
            return [];
        }

        $twigMethodNames = $this->templateMethodCallsProvider->provideTwigMethodCalls();
        $bladeMethodNames = $this->templateMethodCallsProvider->provideBladeMethodCalls();

        $completeMethodCallReferences = $this->nodeCollectorExtractor->extractMethodCallReferences($node);

        $publicMethodCount = 0;
        $unusedPublicMethodCount = 0;

        $filePathToLines = [];

        $publicClassMethodCollector = $node->get(PublicClassMethodCollector::class);
        foreach ($publicClassMethodCollector as $filePath => $declarations) {
            foreach ($declarations as [$className, $methodName, $line, $isInternal]) {
                ++$publicMethodCount;

                if ($this->isUsedClassMethod(
                    $className,
                    $methodName,
                    $completeMethodCallReferences,
                    $twigMethodNames,
                    $bladeMethodNames,
                    $isInternal,
                )) {
                    continue;
                }

                $filePathToLines[$filePath][] = $line;
                ++$unusedPublicMethodCount;
            }
        }

        // unable to divide by 0
        if ($publicMethodCount === 0) {
            return [];
        }

        $relativeUnusedPublicMethods = $unusedPublicMethodCount / $publicMethodCount * 100;
        $maximumRelativeAllowed = $this->configuration->getMaximumRelativeUnusedPublicMethod();

        // is withing limit?
        if ($relativeUnusedPublicMethods < $maximumRelativeAllowed) {
            return [];
        }

        return $this->createRuleErrors($filePathToLines, $relativeUnusedPublicMethods, $maximumRelativeAllowed);
    }

    /**
     * @param MethodCallReference[] $completeMethodCallReferences
     * @param string[] $twigMethodNames
     * @param string[] $bladeMethodNames
     */
    private function isUsedClassMethod(
        string $className,
        string $methodName,
        array $completeMethodCallReferences,
        array $twigMethodNames,
        array $bladeMethodNames,
        bool $isInternal,
    ): bool {
        if ($this->usedMethodAnalyzer->isUsedInTwig($methodName, $twigMethodNames)) {
            return true;
        }

        if (in_array($methodName, $bladeMethodNames, true)) {
            return true;
        }

        $methodReference = $className . '::' . $methodName;
        foreach ($completeMethodCallReferences as $completeMethodCallReference) {
            // skip calls in tests, if they are not internal
            if (! $isInternal && $completeMethodCallReference->isTest()) {
                continue;
            }

            $methodCallReference = $completeMethodCallReference->getClass() . '::' . $completeMethodCallReference->getMethod();
            // php method calls are case-insensitive
            if (strtolower($methodCallReference) === strtolower($methodReference)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, int[]> $filePathToLines
     * @return RuleError[]
     */
    private function createRuleErrors(array $filePathToLines, float $relative, int|float $maximumLimit): array
    {
        $ruleErrors = [];

        foreach ($filePathToLines as $filePath => $lines) {
            foreach ($lines as $line) {
                $errorMessage = sprintf(self::ERROR_MESSAGE, $relative, $maximumLimit);

                $ruleErrors[] = RuleErrorBuilder::message($errorMessage)
                    ->file($filePath)
                    ->line($line)
                    ->identifier('public.method.unused')
                    ->build();
            }
        }

        return $ruleErrors;
    }
}
