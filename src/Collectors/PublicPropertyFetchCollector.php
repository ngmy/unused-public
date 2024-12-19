<?php

declare(strict_types=1);

namespace TomasVotruba\UnusedPublic\Collectors;

use PhpParser\Node;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\Reflection\ClassReflection;
use TomasVotruba\UnusedPublic\ClassTypeDetector;
use TomasVotruba\UnusedPublic\Configuration;
use TomasVotruba\UnusedPublic\PropertyReference\ParentPropertyReferenceResolver;
use TomasVotruba\UnusedPublic\ValueObject\PropertyReference;

/**
 * @implements Collector<PropertyFetch, non-empty-array<string>|null>
 */
final readonly class PublicPropertyFetchCollector implements Collector
{
    public function __construct(
        private ParentPropertyReferenceResolver $parentPropertyReferenceResolver,
        private Configuration $configuration,
        private ClassTypeDetector $classTypeDetector,
    ) {
    }

    /**
     * @return class-string<Node>
     */
    public function getNodeType(): string
    {
        return PropertyFetch::class;
    }

    /**
     * @param PropertyFetch $node
     * @return non-empty-array<string>|null
     */
    public function processNode(Node $node, Scope $scope): ?array
    {
        if (! $this->configuration->isUnusedPropertyEnabled()) {
            return null;
        }

        // skip local
        if ($node->var instanceof Variable && $node->var->name === 'this') {
            return null;
        }

        if (! $node->name instanceof Identifier) {
            return null;
        }

        $classReflection = $scope->getClassReflection();
        $isTest = $classReflection instanceof ClassReflection && $this->classTypeDetector->isTestClass(
            $classReflection
        );

        $result = [];
        $propertyFetcherType = $scope->getType($node->var);
        foreach ($propertyFetcherType->getObjectClassReflections() as $classReflection) {
            $propertyName = $node->name->toString();

            if (! $classReflection->hasProperty($propertyName)) {
                continue;
            }

            $propertyReflection = $classReflection->getProperty($propertyName, $scope);
            $className = $propertyReflection->getDeclaringClass()
                ->getName();
            $propertyReference = new PropertyReference($className, $propertyName, $isTest);
            $parentPropertyReferences = $this->parentPropertyReferenceResolver->findParentPropertyReferences(
                $className,
                $propertyName
            );
            $result = [...$result, (string) $propertyReference, ...$parentPropertyReferences];
        }

        if ($result === []) {
            return null;
        }

        return $result;
    }
}
