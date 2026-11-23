<?php declare(strict_types=1);

namespace ZipDownloadTest;

use Omeka\Test\AbstractHttpControllerTestCase;

/**
 * The full size images of iiif media are fetched only up to a number of files.
 */
class IiifFullMaxTest extends AbstractHttpControllerTestCase
{
    use ZipDownloadTestTrait;

    protected $site;

    public function setUp(): void
    {
        parent::setUp();
        $this->loginAdmin();
        $this->site = $this->createSite('iiif-full-site', 'Iiif full site');
        $services = $this->getServiceLocator();
        $services->get('ControllerPluginManager')->get('currentSite')->setSite($this->site);
        $services->get('Omeka\Settings\Site')->setTargetId($this->site->id());
    }

    public function tearDown(): void
    {
        $this->cleanupResources();
        parent::tearDown();
    }

    public function testFullSizeUpToTheMaximum(): void
    {
        $this->assertTrue($this->iiifFullFor(true, 4, 4));
        $this->assertFalse($this->iiifFullFor(true, 4, 5));
    }

    public function testNoLimitAndDisabled(): void
    {
        $this->assertTrue($this->iiifFullFor(true, 0, 50));
        $this->assertFalse($this->iiifFullFor(false, 0, 1));
    }

    protected function iiifFullFor(bool $enabled, int $max, int $countFiles): bool
    {
        $services = $this->getServiceLocator();
        $siteSettings = $services->get('Omeka\Settings\Site');
        $siteSettings->set('zipdownload_iiif_full', $enabled);
        $siteSettings->set('zipdownload_iiif_full_max', $max);

        $helper = $services->get('ViewHelperManager')->get('downloadZip');
        $calculate = new \ReflectionMethod($helper, 'calculateTotalSize');
        $calculate->setAccessible(true);
        $media = $this->createMock(\Omeka\Api\Representation\MediaRepresentation::class);
        $media->method('hasOriginal')->willReturn(false);
        $media->method('storageId')->willReturn('missing');
        $calculate->invoke($helper, array_fill(0, $countFiles, $media), 'original');
        $property = new \ReflectionProperty($helper, 'iiifFull');
        $property->setAccessible(true);
        return $property->getValue($helper);
    }
}
