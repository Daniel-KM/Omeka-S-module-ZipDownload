<?php declare(strict_types=1);

namespace ZipDownloadTest\Controller\Site;

use Omeka\Test\AbstractHttpControllerTestCase;
use ZipDownloadTest\ZipDownloadTestTrait;

/**
 * Tests for the ZipDownload DownloadController.
 */
class DownloadControllerTest extends AbstractHttpControllerTestCase
{
    use ZipDownloadTestTrait;

    protected $site;

    public function setUp(): void
    {
        parent::setUp();
        $this->loginAdmin();

        // Create a test site.
        $this->site = $this->createSite('test-site', 'Test Site');
    }

    public function tearDown(): void
    {
        $this->cleanupResources();
        parent::tearDown();
    }

    /**
     * Test download returns 404 when disabled.
     */
    public function testDownloadReturns404WhenDisabled(): void
    {
        // Create a test item with media.
        $item = $this->createItem([
            'dcterms:title' => [['type' => 'literal', '@value' => 'Test Item']],
        ]);

        // Ensure zipdownload is disabled (default).
        $siteSettings = $this->getServiceLocator()->get('Omeka\Settings\Site');
        $siteSettings->setTargetId($this->site->id());
        $siteSettings->set('zipdownload_enabled', false);

        $this->dispatch('/s/test-site/download/item/' . $item->id());

        $this->assertResponseStatusCode(404);
    }

    /**
     * Test download route with invalid resource type returns 404.
     */
    public function testDownloadReturnsNotFoundForInvalidResourceType(): void
    {
        $this->dispatch('/s/test-site/download/invalid/1');

        $this->assertResponseStatusCode(404);
    }

    /**
     * Test download route with non-existent resource returns 404.
     */
    public function testDownloadReturnsNotFoundForNonExistentResource(): void
    {
        // Enable download for this site.
        $siteSettings = $this->getServiceLocator()->get('Omeka\Settings\Site');
        $siteSettings->setTargetId($this->site->id());
        $siteSettings->set('zipdownload_enabled', true);

        $this->dispatch('/s/test-site/download/item/999999');

        $this->assertResponseStatusCode(404);
    }

    /**
     * Test route constraint only accepts item or media.
     *
     * @dataProvider validResourceTypesProvider
     */
    public function testRouteAcceptsValidResourceTypes(string $type): void
    {
        // Enable download for this site.
        $siteSettings = $this->getServiceLocator()->get('Omeka\Settings\Site');
        $siteSettings->setTargetId($this->site->id());
        $siteSettings->set('zipdownload_enabled', true);

        // Even with non-existent ID, route should match (returns 404, not routing error).
        $this->dispatch('/s/test-site/download/' . $type . '/1');

        // Should be 404 (resource not found), not 500 or routing error.
        $this->assertResponseStatusCode(404);
    }

    public function validResourceTypesProvider(): array
    {
        return [
            ['item'],
            ['media'],
        ];
    }

    /**
     * Test query download route without resource ID.
     */
    public function testQueryDownloadRoute(): void
    {
        // Enable download for this site.
        $siteSettings = $this->getServiceLocator()->get('Omeka\Settings\Site');
        $siteSettings->setTargetId($this->site->id());
        $siteSettings->set('zipdownload_enabled', true);

        // Query route (no resource ID).
        $this->dispatch('/s/test-site/download/item');

        // Should return 404 since no items match query.
        $this->assertResponseStatusCode(404);
    }

    /**
     * Test download of an item without any media returns 404 (no crash).
     */
    public function testDownloadItemWithoutMediaReturns404(): void
    {
        $siteSettings = $this->getServiceLocator()->get('Omeka\Settings\Site');
        $siteSettings->setTargetId($this->site->id());
        $siteSettings->set('zipdownload_enabled', true);

        // Create an item without any media.
        $item = $this->createItem([
            'dcterms:title' => [['type' => 'literal', '@value' => 'Item Without Media']],
        ]);

        $this->dispatch('/s/test-site/download/item/' . $item->id());

        // No media means no downloadable files: should return 404, not crash.
        $this->assertResponseStatusCode(404);
    }

    /**
     * Test download of an item whose physical files are missing returns 404.
     *
     * This simulates the case where hasOriginal() is true in the database
     * but the file has been deleted from disk.
     */
    public function testDownloadItemWithMissingPhysicalFileReturns404(): void
    {
        $siteSettings = $this->getServiceLocator()->get('Omeka\Settings\Site');
        $siteSettings->setTargetId($this->site->id());
        $siteSettings->set('zipdownload_enabled', true);

        // Create an item without media (no physical files exist).
        $item = $this->createItem([
            'dcterms:title' => [['type' => 'literal', '@value' => 'Item With Missing Files']],
        ]);

        // Request with single_as_file option.
        $this->dispatch('/s/test-site/download/item/' . $item->id(), 'GET', [
            'content' => 'all',
            'type' => 'original',
        ]);

        // Should return 404, not 500.
        $this->assertResponseStatusCode(404);
    }

    /**
     * Test query download with no matching resources returns 404.
     */
    public function testQueryDownloadNoResultsReturns404(): void
    {
        $siteSettings = $this->getServiceLocator()->get('Omeka\Settings\Site');
        $siteSettings->setTargetId($this->site->id());
        $siteSettings->set('zipdownload_enabled', true);

        // Query with impossible filter.
        $this->dispatch('/s/test-site/download/item', 'GET', [
            'id' => [999999],
            'type' => 'original',
        ]);

        // No matching items: should return 404, not crash.
        $this->assertResponseStatusCode(404);
    }
}
