<?php

declare(strict_types=1);

namespace TomasVotruba\UnusedPublic\Tests\Rules\DeadCode\UnusedPublicStaticPropertyRule\Fixture;

use TomasVotruba\UnusedPublic\Tests\Rules\DeadCode\UnusedPublicStaticPropertyRule\Source\SkipExternallyUsedPublicStaticProperty;

final class AnotherClassUsingPublicStaticProperty
{
    public function run()
    {
        return SkipExternallyUsedPublicStaticProperty::$name;
    }
}