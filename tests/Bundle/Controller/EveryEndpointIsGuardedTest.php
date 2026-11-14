<?php

declare(strict_types=1);

namespace Masilia\IbexaAiEditor\Tests\Bundle\Controller;

use Masilia\Contracts\IbexaAi\Security\RequiresAccess;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\Routing\Attribute\Route;

/**
 * An endpoint that forgets #[RequiresAccess] is open to every user of the admin, and nothing else would say so. Each route of
 * the bundle names its access, or is listed here with the reason it does not.
 */
final class EveryEndpointIsGuardedTest extends TestCase
{
    /** Routes with no #[RequiresAccess], and why. */
    private const OPEN = [
        'SuggestionController::fieldTypes' => 'a fixed list of the field types the suggestions support: it holds nothing of the site',
    ];

    /** @return list<ReflectionMethod> */
    private function actions(): array
    {
        $actions = [];
        foreach (glob(__DIR__ . '/../../../src/bundle/Controller/*Controller.php') ?: [] as $file) {
            $class = new ReflectionClass('Masilia\\Bundle\\IbexaAiEditor\\Controller\\' . basename($file, '.php'));
            foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->getAttributes(Route::class) !== []) {
                    $actions[] = $method;
                }
            }
        }

        return $actions;
    }

    private static function routeName(ReflectionMethod $method): string
    {
        return $method->getDeclaringClass()->getShortName() . '::' . $method->getName();
    }

    public function testEveryRouteNamesItsAccessOrIsAKnownException(): void
    {
        $unguarded = [];
        foreach ($this->actions() as $method) {
            $guarded = $method->getAttributes(RequiresAccess::class) !== [] || $method->getDeclaringClass()->getAttributes(RequiresAccess::class) !== [];
            if (!$guarded && !isset(self::OPEN[self::routeName($method)])) {
                $unguarded[] = self::routeName($method);
            }
        }

        self::assertSame([], $unguarded, "These routes have no #[RequiresAccess]:\n" . implode("\n", $unguarded));
    }

    public function testTheExceptionsAreRoutesThatStillExistAndStillHaveNoAccess(): void
    {
        $unguarded = [];
        foreach ($this->actions() as $method) {
            if ($method->getAttributes(RequiresAccess::class) === [] && $method->getDeclaringClass()->getAttributes(RequiresAccess::class) === []) {
                $unguarded[] = self::routeName($method);
            }
        }
        sort($unguarded);
        $expected = array_keys(self::OPEN);
        sort($expected);

        self::assertSame($expected, $unguarded, 'An exception that is guarded now, or gone, must leave the list.');
    }

    public function testTheBundleHasTheEndpointsThisTestCovers(): void
    {
        self::assertGreaterThan(2, count($this->actions()), 'the controllers were not found');
    }
}
