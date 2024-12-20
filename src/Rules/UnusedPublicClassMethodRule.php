<?php

declare(strict_types=1);

namespace TomasVotruba\UnusedPublic\Rules;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\CollectedDataNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use TomasVotruba\UnusedPublic\Collectors\FormTypeClassCollector;
use TomasVotruba\UnusedPublic\Collectors\PublicClassMethodCollector;
use TomasVotruba\UnusedPublic\Configuration;
use TomasVotruba\UnusedPublic\Enum\RuleTips;
use TomasVotruba\UnusedPublic\NodeCollectorExtractor;
use TomasVotruba\UnusedPublic\Templates\TemplateMethodCallsProvider;
use TomasVotruba\UnusedPublic\Templates\UsedMethodAnalyzer;
use TomasVotruba\UnusedPublic\Utils\Arrays;
use TomasVotruba\UnusedPublic\ValueObject\MethodCallReference;

/**
 * @see \TomasVotruba\UnusedPublic\Tests\Rules\UnusedPublicClassMethodRule\UnusedPublicClassMethodRuleTest
 */
final readonly class UnusedPublicClassMethodRule implements Rule
{
    /**
     * @var string
     *
     * @api
     */
    public const ERROR_MESSAGE = 'Public method "%s::%s()" is never used';

    public function __construct(
        private Configuration $configuration,
        private TemplateMethodCallsProvider $templateMethodCallsProvider,
        private UsedMethodAnalyzer $usedMethodAnalyzer,
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
        if (! $this->configuration->isUnusedMethodEnabled()) {
            return [];
        }

        $twigMethodNames = $this->templateMethodCallsProvider->provideTwigMethodCalls();
        $bladeMethodNames = $this->templateMethodCallsProvider->provideBladeMethodCalls();

        $completeMethodCallReferences = $this->nodeCollectorExtractor->extractMethodCallReferences($node);
        $formTypeClasses = Arrays::flatten($node->get(FormTypeClassCollector::class));

        $ruleErrors = [];

        $publicClassMethodCollector = $node->get(PublicClassMethodCollector::class);
        foreach ($publicClassMethodCollector as $filePath => $declarations) {
            foreach ($declarations as [$className, $methodName, $line, $isInternal]) {
                if (in_array($className, $formTypeClasses, true)) {
                    continue;
                }

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

                /** @var string $methodName */
                $errorMessage = sprintf(self::ERROR_MESSAGE, $className, $methodName);

                $ruleErrors[] = RuleErrorBuilder::message($errorMessage)
                    ->file($filePath)
                    ->line($line)
                    ->tip(RuleTips::SOLUTION_MESSAGE)
                    ->identifier('public.method.unused')
                    ->build();
            }
        }

        return $ruleErrors;
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
}
