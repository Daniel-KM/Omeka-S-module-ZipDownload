<?php declare(strict_types=1);

namespace ZipDownloadTest;

use PHPUnit\Framework\TestCase;

/**
 * Source-level checks that the module exposes the digital_objects resource name
 * and the digital-object route segment used by DownloadController and
 * DownloadZip helper, and that the DownloadPrimary block layout is compatible
 * with DOs.
 */
class DigitalObjectSupportTest extends TestCase
{
    public function testDownloadControllerAcceptsDigitalObjectRoute(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 2) . '/src/Controller/Site/DownloadController.php'
        );
        self::assertNotFalse($source);
        // Route segment validation accepts the singular form.
        self::assertStringContainsString(
            "['item', 'media', 'digital-object']",
            $source
        );
        // The collected DOs are referenced by the item via values, not via a
        // media() collection.
        self::assertStringContainsString(
            "'digital_objects'",
            $source,
            'Controller must filter referenced resources by digital_objects resource name.'
        );
    }

    public function testDownloadZipHelperMapsRouteParam(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 2) . '/src/View/Helper/DownloadZip.php'
        );
        self::assertNotFalse($source);
        self::assertStringContainsString(
            "'digital_objects' => 'digital-object'",
            $source,
            'Helper must map digital_objects resource name to the digital-object route segment.'
        );
    }

    public function testDownloadPrimaryBlockDoesNotIncludeDigitalObjects(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 2) . '/src/Site/ResourcePageBlockLayout/DownloadPrimary.php'
        );
        self::assertNotFalse($source);
        self::assertStringNotContainsString(
            "'digital_objects'",
            $source,
            'DownloadPrimary targets media; DOs have their own dedicated block.'
        );
    }

    public function testDownloadPrimaryDigitalObjectBlockExistsAndIsCompatible(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 2) . '/src/Site/ResourcePageBlockLayout/DownloadPrimaryDigitalObject.php'
        );
        self::assertNotFalse($source);
        self::assertStringContainsString("'items'", $source);
        self::assertStringContainsString("'digital_objects'", $source);
    }

    public function testDownloadZipBlockSupportsDigitalObjects(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 2) . '/src/Site/ResourcePageBlockLayout/DownloadZip.php'
        );
        self::assertNotFalse($source);
        self::assertStringContainsString("'digital_objects'", $source);
    }

    public function testGetDownloadableMediasWalksItemValuesForDigitalObjects(): void
    {
        foreach ([
            '/src/Controller/Site/DownloadController.php',
            '/src/View/Helper/DownloadZip.php',
        ] as $relative) {
            $source = file_get_contents(dirname(__DIR__, 2) . $relative);
            self::assertNotFalse($source, "Could not read $relative");
            self::assertStringContainsString(
                'valueResource()',
                $source,
                "Expected $relative to walk item values to collect referenced DOs."
            );
            self::assertStringContainsString(
                "'digital_objects'",
                $source,
                "Expected $relative to filter value resources by digital_objects resource name."
            );
        }
    }
}
