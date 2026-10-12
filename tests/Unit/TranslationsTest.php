<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusCompanyAccountsPlugin\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyMemberInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Member\MemberChangeGuard;

final class TranslationsTest extends TestCase
{
    private const LOCALES = ['en', 'fr', 'de', 'es', 'it', 'nl', 'pl'];

    private const DOMAINS = ['messages', 'validators', 'flashes'];

    /** @return iterable<string, array{string, string}> */
    public static function catalogues(): iterable
    {
        foreach (self::DOMAINS as $domain) {
            foreach (self::LOCALES as $locale) {
                yield sprintf('%s.%s', $domain, $locale) => [$domain, $locale];
            }
        }
    }

    #[DataProvider('catalogues')]
    public function testEveryLocaleDefinesTheSameKeys(string $domain, string $locale): void
    {
        $reference = $this->flatten($this->parse($domain, 'en'));
        $catalogue = $this->flatten($this->parse($domain, $locale));

        self::assertSame([], array_values(array_diff(array_keys($reference), array_keys($catalogue))), 'Missing keys');
        self::assertSame([], array_values(array_diff(array_keys($catalogue), array_keys($reference))), 'Unknown keys');
        foreach ($catalogue as $key => $value) {
            self::assertNotSame('', trim($value), sprintf('Empty translation "%s"', $key));
            preg_match_all('/%\w+%|\{\{ \w+ \}\}/', $reference[$key], $expected);
            preg_match_all('/%\w+%|\{\{ \w+ \}\}/', $value, $actual);
            self::assertEqualsCanonicalizing($expected[0], $actual[0], sprintf('Placeholders of "%s"', $key));
        }
    }

    public function testEveryRoleAndGuardErrorHasALabel(): void
    {
        $messages = $this->flatten($this->parse('messages', 'en'));
        foreach (CompanyMemberInterface::ROLES as $role) {
            self::assertArrayHasKey('tmonier_company_accounts.role.' . $role, $messages);
        }
        $validators = $this->flatten($this->parse('validators', 'en'));
        foreach ([MemberChangeGuard::ERROR_NOT_ALLOWED, MemberChangeGuard::ERROR_INVALID_ROLE, MemberChangeGuard::ERROR_SELF, MemberChangeGuard::ERROR_LAST_ADMIN] as $key) {
            self::assertArrayHasKey($key, $validators);
        }
    }

    public function testEveryKeyUsedInTheCodeExists(): void
    {
        $known = [];
        foreach (self::DOMAINS as $domain) {
            $known += $this->flatten($this->parse($domain, 'en'));
        }
        $known = array_keys($known);
        $root = dirname(__DIR__, 2);
        $used = [];
        foreach (['src', 'templates', 'config'] as $directory) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/' . $directory, \FilesystemIterator::SKIP_DOTS));
            foreach ($files as $file) {
                // Service ids (tmonier_company_accounts.repository...) and event names are not labels.
                if (!$file instanceof \SplFileInfo || !in_array($file->getExtension(), ['php', 'twig', 'yaml', 'xml'], true) || in_array($file->getFilename(), ['services.php', 'order_hold.php', 'CompanyEvents.php'], true)) {
                    continue;
                }
                preg_match_all('/tmonier_company_accounts\.(?:member_updated|ui|form|role|company|member)(?:\.[a-z_]*[a-z_])?/', (string) file_get_contents($file->getPathname()), $matches);
                foreach ($matches[0] as $key) {
                    $used[$key] = true;
                }
            }
        }

        // Prefixes completed at runtime ('tmonier_company_accounts.role.' ~ member.role) are skipped.
        $missing = array_filter(
            array_diff(array_keys($used), $known),
            static fn (string $key): bool => [] === array_filter($known, static fn (string $knownKey): bool => str_starts_with($knownKey, $key . '.')),
        );

        self::assertGreaterThan(40, count($used));
        self::assertSame([], array_values($missing));
    }

    /** @return array<mixed> */
    private function parse(string $domain, string $locale): array
    {
        $data = Yaml::parseFile(sprintf('%s/../../translations/%s.%s.yaml', __DIR__, $domain, $locale));
        self::assertIsArray($data);

        return $data;
    }

    /**
     * @param array<mixed> $data
     *
     * @return array<string, string>
     */
    private function flatten(array $data, string $prefix = ''): array
    {
        $flat = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $flat += $this->flatten($value, $prefix . $key . '.');

                continue;
            }

            $flat[$prefix . $key] = is_scalar($value) ? (string) $value : '';
        }

        return $flat;
    }
}
