<?php declare(strict_types=1);

namespace ZipDownload\Site\ResourcePageBlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Site\ResourcePageBlockLayout\ResourcePageBlockLayoutInterface;

/**
 * Download primary media.
 */
class DownloadPrimary implements ResourcePageBlockLayoutInterface
{
    public function getLabel(): string
    {
        return 'Download primary media'; // @translate
    }

    public function getCompatibleResourceNames(): array
    {
        return [
            'items',
            'media',
            'item_sets',
            'digital_objects',
        ];
    }

    public function render(PhpRenderer $view, AbstractResourceEntityRepresentation $resource): string
    {
        return $view->partial('common/resource-page-block-layout/download-primary', [
            'resource' => $resource,
        ]);
    }
}
