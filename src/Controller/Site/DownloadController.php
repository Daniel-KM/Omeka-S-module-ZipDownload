<?php declare(strict_types=1);

namespace ZipDownload\Controller\Site;

use Laminas\Http\Response as HttpResponse;
use Laminas\Mvc\Controller\AbstractActionController;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Api\Representation\ItemRepresentation;
use Omeka\Api\Representation\MediaRepresentation;
use ZipStream\CompressionMethod;
use ZipStream\ZipStream;

/**
 * Controller to handle resource file downloads, with zip streaming.
 */
class DownloadController extends AbstractActionController
{
    /**
     * Stream a resource download (single file or zip archive).
     */
    public function downloadAction()
    {
        $resourceId = $this->params('resource-id');
        if ($resourceId === null) {
            return $this->downloadQuery();
        }

        $resourceType = $this->params('resource-type');
        $content = $this->params()->fromQuery('content', 'primary');
        $type = $this->params()->fromQuery('type', 'original');
        $singleAsFile = (bool) $this->params()->fromQuery('single_as_file', false);

        // Check if download is enabled for this site.
        $siteSettings = $this->siteSettings();
        if (!$siteSettings->get('zipdownload_enabled', false)) {
            $this->logger()->warn(
                'Attempt to download zip for resource {resource_type} #{resource_id} on site "{site}" without download enabled.', // @translate
                [
                    'resource_type' => $resourceType,
                    'resource_id' => $resourceId,
                    'site' => $this->currentSite()->slug(),
                ]
            );
            return $this->notFoundAction();
        }

        // Validate resource type (singular in route, plural for api).
        $validTypes = ['item', 'media', 'digital-object'];
        if (!in_array($resourceType, $validTypes)) {
            return $this->notFoundAction();
        }

        // Validate type.
        // TODO Get the list of thumbnail types from the config.
        $validFileTypes = ['original', 'large', 'medium', 'square'];
        if (!in_array($type, $validFileTypes)) {
            $type = 'original';
        }

        // Get the right resource name (resources/items/media).
        $resourceType = $this->easyMeta()->resourceName($resourceType);

        // Get the resource.
        try {
            $resource = $this->api()->read($resourceType, $resourceId)->getContent();
        } catch (\Throwable $e) {
            return $this->notFoundAction();
        }

        // Get downloadable medias.
        $medias = $this->getDownloadableMedias($resource, $content);

        // Allow empty medias when copyright text or asset is configured.
        $hasCopyrightText = strlen(trim((string) $this->siteSettings()->get('zipdownload_text', '')));
        $hasAsset = (int) $this->siteSettings()->get('zipdownload_asset');
        if (!$medias && !$hasCopyrightText && !$hasAsset) {
            return $this->notFoundAction();
        }

        // Determine if single file or zip. Default is always zip. Single file
        // output only when option is enabled.
        $isSingleFile = $singleAsFile
            && $medias
            && ($content === 'primary' || count($medias) === 1);

        // Check if ZipStream is available for zip output.
        $hasZipStream = class_exists(\ZipStream\ZipStream::class);

        if ($medias && $isSingleFile) {
            return $this->streamSingleFile(reset($medias), $type, $resource);
        }

        // Fallback to single file if ZipStream is not available.
        if (!$hasZipStream) {
            if (count($medias) === 1) {
                return $this->streamSingleFile(reset($medias), $type, $resource);
            }
            if (!$medias) {
                return $this->notFoundAction();
            }
            // Cannot create zip without ZipStream library.
            $this->messenger()->addError(
                'The ZipStream library is required to download multiple files as zip.' // @translate
            );
            return $this->redirect()->toRoute('site/resource-id', [
                'controller' => $resourceType === 'items' ? 'item' : 'media',
                'action' => 'show',
                'id' => $resourceId,
            ], [], true);
        }

        return $this->streamZipArchive($medias, $type, $resource);
    }

    /**
     * Stream a zip archive for multiple resources via api query.
     */
    protected function downloadQuery()
    {
        $resourceType = $this->params('resource-type') ?: 'item';
        $type = $this->params()->fromQuery('type', 'original');

        // Check if download is enabled for this site.
        $siteSettings = $this->siteSettings();
        if (!$siteSettings->get('zipdownload_enabled', false)) {
            $this->logger()->warn(
                'Attempt to download zip via query for {resource_type} on site "{site}" without download enabled.', // @translate
                [
                    'resource_type' => $resourceType,
                    'site' => $this->currentSite()->slug(),
                ]
            );
            return $this->notFoundAction();
        }

        // Get the right resource name (resources/items/media).
        $resourceType = $this->easyMeta()->resourceName($resourceType);

        // Validate resource type (singular in route, plural for api).
        $validTypes = ['items', 'media'];
        if (!in_array($resourceType, $validTypes)) {
            return $this->notFoundAction();
        }

        // Validate file type.
        $validFileTypes = ['original', 'large', 'medium', 'square'];
        if (!in_array($type, $validFileTypes)) {
            $type = 'original';
        }

        // Build query from request parameters.
        $query = $this->params()->fromQuery();
        unset($query['type']);

        // Apply limit from site settings.
        $maxResources = (int) $siteSettings->get('zipdownload_max', 25);
        if ($maxResources > 0) {
            $query['limit'] = min($query['limit'] ?? $maxResources, $maxResources);
        }

        // Limit to current site.
        $query['site_id'] = $this->currentSite()->id();

        // Search resources.
        try {
            $response = $this->api()->search($resourceType, $query);
            $resources = $response->getContent();
        } catch (\Throwable $e) {
            return $this->notFoundAction();
        }

        if (empty($resources)) {
            return $this->notFoundAction();
        }

        // Collect all downloadable medias from resources.
        $allMedias = [];
        foreach ($resources as $resource) {
            $medias = $this->getDownloadableMedias($resource, 'all');
            foreach ($medias as $media) {
                $allMedias[] = $media;
            }
        }

        if (empty($allMedias)) {
            return $this->notFoundAction();
        }

        // Check if ZipStream is available.
        if (!class_exists(\ZipStream\ZipStream::class)) {
            $this->messenger()->addError(
                'The ZipStream library is required to download multiple files as zip.' // @translate
            );
            return $this->redirect()->toRoute('site', [], [], true);
        }

        return $this->streamZipArchiveQuery($allMedias, $type, $resources);
    }

    /**
     * Stream a single file directly.
     */
    protected function streamSingleFile(
        AbstractResourceEntityRepresentation $media,
        string $type,
        $resource
    ): HttpResponse {
        $file = $this->resolveMediaFile($media, $type);
        if (!$file) {
            return $this->notFoundAction();
        }

        $response = $this->sendFile($file['path'], [
            'filename' => $this->buildFilename($resource, $file['extension']),
            'disposition_mode' => 'attachment',
            'content_type' => $file['media_type'],
        ]);

        if ($file['temp']) {
            @unlink($file['path']);
        }

        return $response ?? $this->notFoundAction();
    }

    /**
     * Stream a zip archive with all files.
     */
    protected function streamZipArchive(
        array $medias,
        string $type,
        $resource
    ): HttpResponse {
        $filename = $this->buildFilename($resource);

        // Get copyright text.
        $copyrightText = $this->buildCopyrightText($resource, $medias, $type);
        $copyrightFilename = $this->getCopyrightFilename();

        // Get response and set headers.
        /** @var \Laminas\Http\PhpEnvironment\Response $response */
        $response = $this->getResponse();
        $headers = $response->getHeaders();

        $headers
            ->addHeaderLine('Content-Type: application/zip')
            ->addHeaderLine(sprintf('Content-Disposition: attachment; filename="%s"', $filename))
            ->addHeaderLine('Content-Transfer-Encoding: binary')
            ->addHeaderLine('Cache-Control: no-cache, no-store, must-revalidate')
            ->addHeaderLine('Pragma: no-cache')
            ->addHeaderLine('Expires: 0');

        // Fix deprecated warning.
        $errorReporting = error_reporting();
        error_reporting($errorReporting & ~E_DEPRECATED);

        $response->sendHeaders();

        error_reporting($errorReporting);

        // Clear output buffers.
        $response->setContent('');
        while (ob_get_level()) {
            ob_end_clean();
        }

        // A large archive may stream for a long time over a slow connection;
        // lift PHP's execution time limit so the download is not killed
        // mid-stream. Memory stays flat (ZipStream streams each file).
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        // Stream the zip using ZipStream.
        // Use STORE (no compression) by default since media files (images,
        // pdf, video) are already compressed.
        $zip = new ZipStream(
            outputName: $filename,
            sendHttpHeaders: false,
            defaultCompressionMethod: CompressionMethod::STORE,
        );

        // Add copyright file if configured (use DEFLATE for text).
        if ($copyrightText) {
            $zip->addFile(
                fileName: $copyrightFilename,
                data: $copyrightText,
                compressionMethod: CompressionMethod::DEFLATE,
            );
        }

        // Add configured asset (e.g. a presentation PDF).
        $this->addAssetToZip($zip);

        // Add each media file (no compression, already compressed formats).
        foreach ($medias as $index => $media) {
            $this->addMediaToZip($zip, $media, $type, $index);
        }

        $zip->finish();

        // Prevent further output.
        ini_set('display_errors', '0');

        return $response;
    }

    /**
     * Stream a zip archive for multiple resources from query.
     */
    protected function streamZipArchiveQuery(
        array $medias,
        string $type,
        array $resources
    ): HttpResponse {
        $site = $this->currentSite();
        $filename = $this->sanitizeFilename($site->title()) . '_' . date('Ymd_His') . '.zip';

        // Get copyright text for batch download.
        $copyrightText = $this->buildCopyrightTextQuery($resources, $medias, $type);
        $copyrightFilename = $this->getCopyrightFilename();

        // Get response and set headers.
        /** @var \Laminas\Http\PhpEnvironment\Response $response */
        $response = $this->getResponse();
        $headers = $response->getHeaders();

        $headers
            ->addHeaderLine('Content-Type: application/zip')
            ->addHeaderLine(sprintf('Content-Disposition: attachment; filename="%s"', $filename))
            ->addHeaderLine('Content-Transfer-Encoding: binary')
            ->addHeaderLine('Cache-Control: no-cache, no-store, must-revalidate')
            ->addHeaderLine('Pragma: no-cache')
            ->addHeaderLine('Expires: 0');

        // Fix deprecated warning.
        $errorReporting = error_reporting();
        error_reporting($errorReporting & ~E_DEPRECATED);

        $response->sendHeaders();

        error_reporting($errorReporting);

        // Clear output buffers.
        $response->setContent('');
        while (ob_get_level()) {
            ob_end_clean();
        }

        // A large archive may stream for a long time over a slow connection;
        // lift PHP's execution time limit so the download is not killed
        // mid-stream. Memory stays flat (ZipStream streams each file).
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        // Stream the zip using ZipStream.
        // Use STORE (no compression) by default since media files are already compressed.
        $zip = new ZipStream(
            outputName: $filename,
            sendHttpHeaders: false,
            defaultCompressionMethod: CompressionMethod::STORE,
        );

        // Add copyright file if configured.
        if ($copyrightText) {
            $zip->addFile(
                fileName: $copyrightFilename,
                data: $copyrightText,
                compressionMethod: CompressionMethod::DEFLATE,
            );
        }

        // Add configured asset (e.g. a presentation PDF).
        $this->addAssetToZip($zip);

        // Add each media file organized by resource.
        $resourceIndex = 0;
        $mediasByItem = [];
        foreach ($medias as $media) {
            $itemId = $media->item() ? $media->item()->id() : 0;
            $mediasByItem[$itemId][] = $media;
        }

        foreach ($mediasByItem as $itemId => $itemMedias) {
            $resourceIndex++;
            $item = $itemMedias[0]->item();
            $folderName = $item
                ? sprintf('%03d_%s', $resourceIndex, $this->sanitizeFilename($item->displayTitle()))
                : sprintf('%03d_media', $resourceIndex);

            foreach ($itemMedias as $mediaIndex => $media) {
                $this->addMediaToZip($zip, $media, $type, $mediaIndex, $folderName . '/');
            }
        }

        $zip->finish();

        // Prevent further output.
        ini_set('display_errors', '0');

        return $response;
    }

    /**
     * Get the configured filename for the copyright text inside the zip.
     */
    protected function getCopyrightFilename(): string
    {
        $name = trim((string) $this->siteSettings()->get('zipdownload_text_filename', ''));
        return $name !== '' ? $this->sanitizeFilename($name) : 'COPYRIGHT.txt';
    }

    /**
     * Append the site-configured asset (if any) to the zip stream.
     */
    protected function addAssetToZip(ZipStream $zip): void
    {
        $assetId = (int) $this->siteSettings()->get('zipdownload_asset');
        if (!$assetId) {
            return;
        }
        try {
            $asset = $this->api()->read('assets', $assetId)->getContent();
        } catch (\Throwable $e) {
            return;
        }
        $filename = $asset->filename();
        if (!$filename) {
            return;
        }
        $filepath = OMEKA_PATH . '/files/asset/' . $filename;
        if (!file_exists($filepath)) {
            return;
        }
        $name = $asset->name() ?: $filename;
        $zip->addFileFromPath(
            fileName: $this->sanitizeFilename($name) ?: $filename,
            path: $filepath,
        );
    }

    /**
     * Get downloadable medias from a resource.
     */
    protected function getDownloadableMedias($resource, string $content): array
    {
        $medias = [];

        $isDo = $resource instanceof AbstractResourceEntityRepresentation
            && $resource->resourceName() === 'digital_objects';

        if ($resource instanceof MediaRepresentation || $isDo) {
            if ($this->isDownloadableMedia($resource)) {
                $medias[] = $resource;
            }
        } elseif ($resource instanceof ItemRepresentation) {
            if ($content === 'primary') {
                $primary = $resource->primaryMedia();
                if ($primary && $this->isDownloadableMedia($primary)) {
                    $medias[] = $primary;
                }
            } else {
                foreach ($resource->media() as $media) {
                    if ($this->isDownloadableMedia($media)) {
                        $medias[] = $media;
                    }
                }
                // Also collect digital objects referenced by the item via
                // property values (the parent-link DOs use since they have no
                // item FK).
                foreach ($this->iterateItemDigitalObjects($resource) as $vr) {
                    if ($vr->hasOriginal()) {
                        $medias[] = $vr;
                    }
                }
            }
        }

        return $medias;
    }

    /**
     * Iterate digital objects referenced by an item via property values,
     * deduplicated by id.
     *
     * @return iterable<AbstractResourceEntityRepresentation>
     */
    protected function iterateItemDigitalObjects(ItemRepresentation $item): iterable
    {
        $seen = [];
        foreach ($item->values() as $property) {
            foreach ($property['values'] as $value) {
                $vr = $value->valueResource();
                if (!$vr || $vr->resourceName() !== 'digital_objects') {
                    continue;
                }
                $id = $vr->id();
                if (isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;
                yield $vr;
            }
        }
    }

    /**
     * Check if a media has a file to download (original or iiif derivatives).
     */
    protected function isDownloadableMedia(AbstractResourceEntityRepresentation $media): bool
    {
        return $media->hasOriginal()
            || ($media instanceof MediaRepresentation
                && $media->renderer() === 'iiif'
                && $media->hasThumbnails());
    }

    /**
     * Resolve the file to send for a media.
     *
     * A media without original (iiif) uses its local derivative "large" when
     * the original is requested, or the full image fetched from the image
     * server when the site option is set.
     *
     * @return array|null Keys: path, extension, media_type, temp.
     */
    protected function resolveMediaFile(AbstractResourceEntityRepresentation $media, string $type): ?array
    {
        if ($type === 'original' && !$media->hasOriginal()) {
            if ($this->siteSettings()->get('zipdownload_iiif_full')) {
                $filepath = $this->fetchIiifFullImage($media);
                if ($filepath) {
                    return [
                        'path' => $filepath,
                        'extension' => 'jpg',
                        'media_type' => 'image/jpeg',
                        'temp' => true,
                    ];
                }
            }
            $type = 'large';
        }

        $basePath = OMEKA_PATH . '/files/';
        if ($type === 'original') {
            $filepath = $basePath . 'original/' . $media->filename();
            $extension = (string) pathinfo($media->filename(), PATHINFO_EXTENSION);
            $mediaType = $media->mediaType() ?: 'application/octet-stream';
        } else {
            $filepath = $basePath . $type . '/' . $media->storageId() . '.jpg';
            $extension = 'jpg';
            $mediaType = 'image/jpeg';
        }

        return file_exists($filepath) && is_readable($filepath)
            ? ['path' => $filepath, 'extension' => $extension, 'media_type' => $mediaType, 'temp' => false]
            : null;
    }

    /**
     * Fetch the full size image of a iiif media into a temporary file.
     */
    protected function fetchIiifFullImage(AbstractResourceEntityRepresentation $media): ?string
    {
        if (!$media instanceof MediaRepresentation || $media->renderer() !== 'iiif') {
            return null;
        }

        // Same urls than the core iiif ingester.
        $data = $media->mediaData();
        $context = $data['@context'] ?? null;
        if ($context === 'http://iiif.io/api/image/3/context.json') {
            $id = $data['id'] ?? null;
            $path = '/full/max/0/default.jpg';
        } elseif ($context === 'http://iiif.io/api/image/2/context.json') {
            $id = $data['@id'] ?? null;
            $path = '/full/full/0/default.jpg';
        } else {
            $id = $data['@id'] ?? null;
            $path = '/full/full/0/native.jpg';
        }
        if (!$id) {
            return null;
        }

        /** @var \Omeka\File\Downloader $downloader */
        $downloader = $this->getEvent()->getApplication()->getServiceManager()
            ->get('Omeka\File\Downloader');
        $tempFile = $downloader->download(rtrim($id, '/') . $path);
        if (!$tempFile) {
            $this->logger()->warn(
                'Media #{media_id}: unable to fetch the full iiif image, so the local derivative is used.', // @translate
                ['media_id' => $media->id()]
            );
            return null;
        }

        return $tempFile->getTempPath();
    }

    /**
     * Add a media file to the zip stream.
     */
    protected function addMediaToZip(
        ZipStream $zip,
        AbstractResourceEntityRepresentation $media,
        string $type,
        int $index,
        string $folder = ''
    ): void {
        $file = $this->resolveMediaFile($media, $type);
        if (!$file) {
            return;
        }

        // Stream file from disk without compression.
        $zip->addFileFromPath(
            fileName: $folder . $this->buildMediaFilename($media, $file['extension'], $index),
            path: $file['path'],
        );

        if ($file['temp']) {
            @unlink($file['path']);
        }
    }

    /**
     * Build download filename, with the extension of the file, else zip.
     */
    protected function buildFilename($resource, ?string $extension = null): string
    {
        $extension ??= 'zip';
        $title = $this->sanitizeFilename($resource->displayTitle());
        return $this->stripExtension($title, $extension) . '.' . $extension;
    }

    /**
     * Build filename for a media inside the zip.
     */
    protected function buildMediaFilename(
        AbstractResourceEntityRepresentation $media,
        string $extension,
        int $index
    ): string {
        // Without original, the default title is the source url (iiif).
        $title = $media->hasOriginal() || !$media instanceof MediaRepresentation
            ? $media->displayTitle()
            : ($media->title() ?? $media->item()->displayTitle());
        $title = substr($this->sanitizeFilename($title), 0, 80);
        $title = $this->stripExtension($title, $extension);
        return sprintf('%02d_%s.%s', $index + 1, $title, $extension);
    }

    /**
     * Remove a trailing extension from a filename when it matches the target,
     * so we do not build "foo.jpeg.jpeg".
     */
    protected function stripExtension(string $name, string $extension): string
    {
        if ($extension === '') {
            return $name;
        }
        $suffix = '.' . $extension;
        if (strcasecmp(substr($name, -strlen($suffix)), $suffix) === 0) {
            $name = substr($name, 0, -strlen($suffix));
        }
        return $name === '' ? 'file' : $name;
    }

    /**
     * Build copyright text by rendering the copyright partial.
     */
    protected function buildCopyrightText($resource, array $medias, string $type): ?string
    {
        $zipdownloadText = $this->siteSettings()->get('zipdownload_text', '');
        if (!strlen(trim((string) $zipdownloadText))) {
            return null;
        }

        $placeholders = $this->buildPlaceholders($resource, $medias, $type, $zipdownloadText);

        $partial = $this->viewHelpers()->get('partial');
        $text = (string) $partial('common/zip-download-copyright', [
            'resource' => $resource,
            'medias' => $medias,
            'site' => $this->currentSite(),
            'zipdownloadText' => $zipdownloadText,
            'placeholders' => $placeholders,
        ]);

        return strlen(trim($text)) ? $text : null;
    }

    /**
     * Build all placeholder values for a single resource copyright text.
     */
    protected function buildPlaceholders(
        $resource,
        array $medias,
        string $type,
        string $zipdownloadText
    ): array {
        $site = $this->currentSite();
        $settings = $this->settings();
        $partial = $this->viewHelpers()->get('partial');

        $placeholders = [
            '{main_title}' => $settings->get('installation_title', 'Omeka S'),
            '{site_title}' => $site ? $site->title() : '',
            '{site_url}' => $site ? $site->siteUrl(null, true) : '',
            '{resource_id}' => (string) $resource->id(),
            '{resource_title}' => $resource->displayTitle(),
            '{resource_url}' => $resource->siteUrl($site ? $site->slug() : null, true) ?: $resource->apiUrl(),
            '{file_count}' => (string) count($medias),
            '{file_type}' => $type,
            '{date}' => date('Y-m-d'),
            '{datetime}' => date('Y-m-d H:i:s'),
            '{citation}' => trim((string) $partial(
                'common/zip-download-citation',
                ['resource' => $resource, 'site' => $site]
            )),
        ];

        // RDF property terms detected in the template text.
        if (preg_match_all('/\{([a-zA-Z0-9_-]+:[a-zA-Z0-9_-]+)\}/', $zipdownloadText, $matches)) {
            foreach ($matches[1] as $term) {
                $values = $resource->value($term, ['all' => true]) ?: [];
                $values = array_map(fn($v) => strip_tags((string) $v), $values);
                $placeholders['{' . $term . '}'] = implode(', ', $values);
            }
        }

        return $placeholders;
    }

    /**
     * Build copyright text for batch download from query.
     */
    protected function buildCopyrightTextQuery(
        array $resources,
        array $medias,
        string $type
    ): ?string {
        $zipdownloadText = $this->siteSettings()->get('zipdownload_text', '');
        if (!strlen(trim((string) $zipdownloadText))) {
            return null;
        }

        $placeholders = $this->buildPlaceholdersQuery($resources, $medias, $type);

        $partial = $this->viewHelpers()->get('partial');
        $text = (string) $partial('common/zip-download-copyright-multiple', [
            'resources' => $resources,
            'medias' => $medias,
            'site' => $this->currentSite(),
            'zipdownloadText' => $zipdownloadText,
            'placeholders' => $placeholders,
        ]);

        return strlen(trim($text)) ? $text : null;
    }

    /**
     * Build placeholder values for a batch (query) copyright text.
     */
    protected function buildPlaceholdersQuery(
        array $resources,
        array $medias,
        string $type
    ): array {
        $site = $this->currentSite();
        $settings = $this->settings();

        return [
            '{main_title}' => $settings->get('installation_title', 'Omeka S'),
            '{site_title}' => $site ? $site->title() : '',
            '{site_url}' => $site ? $site->siteUrl(null, true) : '',
            '{file_count}' => (string) count($medias),
            '{resource_count}' => (string) count($resources),
            '{file_type}' => $type,
            '{date}' => date('Y-m-d'),
            '{datetime}' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Sanitize a string for use as filename.
     */
    protected function sanitizeFilename(string $name): string
    {
        if ($name !== '' && preg_match('//u', $name) && class_exists(\Transliterator::class)) {
            $tr = \Transliterator::create('Any-Latin; Latin-ASCII; [:Nonspacing Mark:] Remove; NFC');
            if ($tr) {
                $translit = $tr->transliterate($name);
                if (is_string($translit) && $translit !== '') {
                    $name = $translit;
                }
            }
        }
        $name = preg_replace('/[^a-zA-Z0-9_\-\.\s]/', '', $name);
        $name = preg_replace('/\s+/', '_', $name);
        return substr($name, 0, 100) ?: 'download';
    }
}
