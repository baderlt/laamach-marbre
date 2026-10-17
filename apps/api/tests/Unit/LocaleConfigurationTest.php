<?php

namespace Tests\Unit;

use App\Support\LocaleConfiguration;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LocaleConfigurationTest extends TestCase
{
    public function test_an_additional_locale_can_be_the_default_without_a_database_change(): void
    {
        LocaleConfiguration::validate(['default' => 'de', 'fallback' => 'de', 'locales' => [['code' => 'de', 'name' => 'Deutsch', 'direction' => 'ltr']]]);
        $this->addToAssertionCount(1);
    }

    public static function invalidConfigurations(): array
    {
        $entry = ['code' => 'de', 'name' => 'Deutsch', 'direction' => 'ltr'];

        return [
            [['default' => 'de', 'fallback' => 'de', 'locales' => []]],
            [['default' => 'missing', 'fallback' => 'de', 'locales' => [$entry]]],
            [['default' => 'de', 'fallback' => 'missing', 'locales' => [$entry]]],
            [['default' => 'de', 'fallback' => 'de', 'locales' => [$entry, $entry]]],
            [['default' => 'de', 'fallback' => 'de', 'locales' => [[...$entry, 'direction' => 'invalid']]]],
        ];
    }

    #[DataProvider('invalidConfigurations')]
    public function test_invalid_configuration_is_rejected(array $configuration): void
    {
        $this->expectException(InvalidArgumentException::class);
        LocaleConfiguration::validate($configuration);
    }
}
