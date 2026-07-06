<?php declare(strict_types=1);

namespace ZipDownload\Site\ResourcePageBlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Site\ResourcePageBlockLayout\ResourcePageBlockLayoutInterface;

/**
 * Download the first downloadable digital object referenced by the resource.
 *
 * Companion to DownloadPrimary, which targets the primary media. On an item
 * whose file-bearing children are autonomous digital objects (no media), or to
 * expose a one-click download of the canonical DO regardless of medias, use
 * this block.
 */
class DownloadPrimaryDigitalObject implements ResourcePageBlockLayoutInterface
{
    public function getLabel(): string
    {
        return 'Download primary digital object'; // @translate
    }

    public function getCompatibleResourceNames(): array
    {
        return [
            'items',
            'digital_objects',
        ];
    }

    public function render(PhpRenderer $view, AbstractResourceEntityRepresentation $resource): string
    {
        return $view->partial('common/resource-page-block-layout/download-primary-digital-object', [
            'resource' => $resource,
        ]);
    }
}
