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
use TomasVotruba\UnusedPublic\Enum\RuleTips;
use TomasVotruba\UnusedPublic\NodeCollectorExtractor;
use TomasVotruba\UnusedPublic\Templates\TemplateMethodCallsProvider;
use TomasVotruba\UnusedPublic\Templates\UsedMethodAnalyzer;
use TomasVotruba\UnusedPublic\ValueObject\MethodCallReference;

/**
 * @see \TomasVotruba\UnusedPublic\Tests\Rules\LocalOnlyPublicClassMethodRule\LocalOnlyPublicClassMethodRuleTest
 */
final readonly class LocalOnlyPublicClassMethodRule implements Rule
{
    /**
     * @var string
     *
     * @api
     */
    public const ERROR_MESSAGE = 'Public method "%s::%s()" is used only locally and should be turned protected/private';

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
        if (! $this->configuration->isLocalMethodEnabled()) {
            return [];
        }

        $twigMethodNames = $this->templateMethodCallsProvider->provideTwigMethodCalls();

        $localAndExternalMethodCallReferences = $this->nodeCollectorExtractor->extractLocalAndExternalMethodCallReferences(
            $node
        );

        $ruleErrors = [];

        $publicClassMethodCollector = $node->get(PublicClassMethodCollector::class);
        foreach ($publicClassMethodCollector as $filePath => $declarations) {
            foreach ($declarations as [$className, $methodName, $line, $isInternal]) {
                if (! $this->isUsedOnlyLocally(
                    $className,
                    $methodName,
                    $localAndExternalMethodCallReferences->getExternalMethodCallReferences(),
                    $localAndExternalMethodCallReferences->getLocalMethodCallReferences(),
                    $twigMethodNames,
                    $isInternal,
                )) {
                    continue;
                }

                /** @var string $methodName */
                $errorMessage = sprintf(self::ERROR_MESSAGE, $className, $methodName);

                $ruleErrors[] = RuleErrorBuilder::message($errorMessage)
                    ->file($filePath)
                    ->line($line)
                    ->tip(RuleTips::NARROW_SCOPE)
                    ->identifier('public.method.unused')
                    ->build();
            }
        }

        return $ruleErrors;
    }

    /**
     * @param MethodCallReference[] $externalRefs
     * @param MethodCallReference[] $localRefs
     * @param string[] $twigMethodNames
     */
    private function isUsedOnlyLocally(
        string $className,
        string $methodName,
        array $externalRefs,
        array $localRefs,
        array $twigMethodNames,
        bool $isInternal,
    ): bool {
        if ($this->usedMethodAnalyzer->isUsedInTwig($methodName, $twigMethodNames)) {
            return true;
        }

        $publicMethodReference = strtolower($className . '::' . $methodName);

        foreach ($externalRefs as $externalRef) {
            // skip calls in tests, if they are not internal
            if (! $isInternal && $externalRef->isTest()) {
                continue;
            }

            $methodCallReference = $externalRef->getClass() . '::' . $externalRef->getMethod();
            // php method calls are case-insensitive
            if (strtolower($methodCallReference) === $publicMethodReference) {
                return false;
            }
        }

        foreach ($localRefs as $localRef) {
            $methodCallReference = $localRef->getClass() . '::' . $localRef->getMethod();
            // php method calls are case-insensitive
            if (strtolower($methodCallReference) === $publicMethodReference) {
                return true;
            }
        }

        return false;
    }
}
