<?php

declare(strict_types=1);

namespace Masilia\IbexaAiEditor\Tests\Bundle\Controller;

use Ibexa\Contracts\AdminUi\Controller\Controller;
use Masilia\Contracts\IbexaAi\Http\CsrfProtectedController;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\Routing\Attribute\Route;

/**
 * A controller that forgets its access check is open to every user of the admin, and nothing else would say so. Each controller
 * of the bundle is an Ibexa admin controller that states its own performAccessCheck(), and one that answers writes asks for the
 * CSRF token of the admin.
 */
final class EveryEndpointIsGuardedTest extends TestCase
{
    /** @return list<ReflectionClass<object>> */
    private function controllers(): array
    {
        $controllers = [];
        foreach (glob(__DIR__ . '/../../../src/bundle/Controller/*Controller.php') ?: [] as $file) {
            $controllers[] = new ReflectionClass('Masilia\\Bundle\\IbexaAiEditor\\Controller\\' . basename($file, '.php'));
        }

        return $controllers;
    }

    public function testEveryControllerIsAnAdminControllerThatChecksItsOwnAccess(): void
    {
        $unguarded = [];
        foreach ($this->controllers() as $class) {
            $own = $class->hasMethod('performAccessCheck') && $class->getMethod('performAccessCheck')->getDeclaringClass()->getName() === $class->getName();
            if (!$class->isSubclassOf(Controller::class) || !$own) {
                $unguarded[] = $class->getShortName();
            }
        }

        self::assertSame([], $unguarded, "These controllers do not check an access of their own:\n" . implode("\n", $unguarded));
    }

    public function testWhatChangesSomethingAsksForTheCsrfTokenOfTheAdmin(): void
    {
        $open = [];
        foreach ($this->controllers() as $class) {
            foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                foreach ($method->getAttributes(Route::class) as $attribute) {
                    $methods = (array) ($attribute->newInstance()->getMethods() ?: []);
                    if (array_diff($methods, ['GET', 'HEAD']) !== [] && !$class->implementsInterface(CsrfProtectedController::class)) {
                        $open[] = $class->getShortName() . '::' . $method->getName();
                    }
                }
            }
        }

        self::assertSame([], $open, "These routes change something without the CSRF token of the admin:\n" . implode("\n", $open));
    }

    public function testTheBundleHasTheEndpointsThisTestCovers(): void
    {
        $routes = 0;
        foreach ($this->controllers() as $class) {
            foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                $routes += count($method->getAttributes(Route::class));
            }
        }

        self::assertGreaterThan(3, $routes, 'the controllers were not found');
    }
}
