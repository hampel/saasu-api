<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Tests;

use Hampel\Saasu\Api\Client;
use Hampel\Saasu\Api\Exception\ExceptionInterface;
use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * The README is documentation for people who cannot read the source, which makes a wrong example
 * worse than a missing one: it is copied, it fails, and the reader concludes the package is
 * broken. Renaming a method is exactly the change that passes every other test here - the suite
 * calls the new name and only the README still calls the old one.
 *
 * So this checks that everything the README names exists. It cannot check the examples are
 * correct; it can check they are not stale, which is the failure that actually happens.
 */
final class ReadmeTest extends BaseTestCase
{
    private function readme(): string
    {
        return (string) file_get_contents(dirname(__DIR__) . '/README.md');
    }

    public function testEveryDocumentedEndpointCallExists(): void
    {
        preg_match_all('/\$saasu->(\w+)\(\)->(\w+)\(/', $this->readme(), $matches, PREG_SET_ORDER);

        $this->assertGreaterThan(30, count($matches), 'the README should show rather more endpoint calls than this');

        foreach ($matches as [, $accessor, $method]) {
            $this->assertTrue(method_exists(Client::class, $accessor), sprintf('the README calls $saasu->%s(), which Client does not have', $accessor));

            $returns = (string) (new \ReflectionMethod(Client::class, $accessor))->getReturnType();

            $this->assertTrue(
                method_exists($returns, $method),
                sprintf('the README calls $saasu->%s()->%s(), which %s does not have', $accessor, $method, $returns)
            );
        }
    }

    public function testEveryDocumentedClientMethodExists(): void
    {
        preg_match_all('/(?:\$saasu->|Client::)(\w+)\(/', $this->readme(), $matches);

        foreach (array_unique($matches[1]) as $method) {
            $this->assertTrue(method_exists(Client::class, $method), sprintf('the README calls %s() on Client, which does not exist', $method));
        }
    }

    public function testEveryImportedClassAndStaticCallExists(): void
    {
        $readme = $this->readme();

        preg_match_all('/^use (Hampel\\\\Saasu\\\\Api\\\\[\w\\\\]+);$/m', $readme, $uses);

        $this->assertNotEmpty($uses[1]);

        $imported = [];

        foreach ($uses[1] as $class) {
            $this->assertTrue(class_exists($class) || enum_exists($class), sprintf('the README imports %s, which does not exist', $class));
            $imported[substr((string) strrchr($class, '\\'), 1)] = $class;
        }

        preg_match_all('/\b([A-Z]\w+)::(\w+)\b/', $readme, $statics, PREG_SET_ORDER);

        foreach ($statics as [, $short, $member]) {
            if (!isset($imported[$short])) {
                continue;
            }

            $class = $imported[$short];

            $this->assertTrue(
                method_exists($class, $member) || defined($class . '::' . $member),
                sprintf('the README names %s::%s, which does not exist', $short, $member)
            );
        }
    }

    public function testEveryDocumentedExceptionExistsAndIsOurs(): void
    {
        preg_match_all('/`(\w+Exception)`/', $this->readme(), $matches);

        foreach (array_unique($matches[1]) as $name) {
            $class = 'Hampel\\Saasu\\Api\\Exception\\' . $name;

            $this->assertTrue(class_exists($class), sprintf('the README names %s, which does not exist', $name));
            $this->assertTrue(is_subclass_of($class, ExceptionInterface::class));
        }
    }

    /**
     * `Filter::method()` written in prose rather than code - `PaymentFilter::paidBetween()`.
     */
    public function testEveryFilterMethodNamedInProseExists(): void
    {
        preg_match_all('/`(\w+Filter)::(\w+)\(\)`/', $this->readme(), $matches, PREG_SET_ORDER);

        $this->assertNotEmpty($matches);

        foreach ($matches as [, $filter, $method]) {
            $this->assertTrue(
                method_exists('Hampel\\Saasu\\Api\\Filter\\' . $filter, $method),
                sprintf('the README names %s::%s(), which does not exist', $filter, $method)
            );
        }
    }
}
