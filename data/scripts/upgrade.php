<?php declare(strict_types=1);

namespace ZipDownload;

use Common\Stdlib\PsrMessage;

/**
 * @var Module $this
 * @var \Laminas\ServiceManager\ServiceLocatorInterface $services
 * @var string $newVersion
 * @var string $oldVersion
 *
 * @var \Omeka\Api\Manager $api
 * @var \Laminas\Log\Logger $logger
 * @var \Omeka\Settings\Settings $settings
 * @var \Doctrine\DBAL\Connection $connection
 * @var \Doctrine\ORM\EntityManager $entityManager
 * @var \Omeka\Settings\SiteSettings $siteSettings
 * @var \Omeka\Mvc\Controller\Plugin\Messenger $messenger
 */
$plugins = $services->get('ControllerPluginManager');
$api = $plugins->get('api');
$logger = $services->get('Omeka\Logger');
$settings = $services->get('Omeka\Settings');
$translate = $plugins->get('translate');
$connection = $services->get('Omeka\Connection');
$messenger = $plugins->get('messenger');
$siteSettings = $services->get('Omeka\Settings\Site');
$entityManager = $services->get('Omeka\EntityManager');

if (PHP_VERSION_ID < 80200) {
    $message = new \Omeka\Stdlib\Message(
        $translate('The module %1$s requires PHP %2$s or later.'), // @translate
        'ZipDownload', '8.2'
    );
    $messenger->addError($message);
    throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $translate('Missing requirement. Unable to upgrade.')); // @translate
}

if (!method_exists($this, 'checkModuleActiveVersion') || !$this->checkModuleActiveVersion('Common', '3.4.97')) {
    $message = new \Omeka\Stdlib\Message(
        $translate('The module %1$s should be upgraded to version %2$s or later.'), // @translate
        'Common', '3.4.97'
    );
    $messenger->addError($message);
    throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $translate('Missing requirement. Unable to upgrade.')); // @translate
}

if (version_compare((string) $oldVersion, '3.4.3', '<')) {
    // Convert zipdownload_type from string to array for all sites.
    $siteIds = $api->search('sites', [], ['returnScalar' => 'id'])->getContent();
    foreach ($siteIds as $siteId) {
        $siteSettings->setTargetId($siteId);
        $type = $siteSettings->get('zipdownload_type');
        if (is_string($type) && strlen($type)) {
            $siteSettings->set('zipdownload_type', [$type]);
        }
    }

    $message = new PsrMessage(
        'It is now possible to configure multiple file types for download. When multiple types are selected, visitors can choose the format in the download dialog.' // @translate
    );
    $messenger->addSuccess($message);
}

if (version_compare((string) $oldVersion, '3.4.4', '<')) {
    $message = new PsrMessage(
        'The copyright text included in the zip (COPYRIGHT.txt) is now rendered via customizable theme partials.' // @translate
    );
    $messenger->addSuccess($message);
}

if (version_compare((string) $oldVersion, '3.4.5', '<')) {
    $message = new PsrMessage(
        'An asset, for example a pdf presenting the institution, can now be attached to every generated zip via a new site setting.' // @translate
    );
    $messenger->addSuccess($message);

    $message = new PsrMessage(
        'The filename of the copyright file inside the zip can now be customized via a new site setting.' // @translate
    );
    $messenger->addSuccess($message);

    $message = new PsrMessage(
        'Dialog labels for file types are now customizable per site.' // @translate
    );
    $messenger->addSuccess($message);

    $message = new PsrMessage(
        'The {link}digital objects{link_end} are now supported.', // @translate
        ['link' => '<a href="https://gitlab.com/Daniel-KM/Omeka-S-module-DigitalObject">', 'link_end' => '</a>']
    );
    $message->setEscapeHtml(false);
    $messenger->addSuccess($message);
}

if (version_compare((string) $oldVersion, '3.4.8', '<')) {
    $message = new PsrMessage(
        'Iiif media are now included in zip, with their local derivatives, or with the full image from the image server when the new site setting is set.' // @translate
    );
    $messenger->addSuccess($message);
}

if (version_compare((string) $oldVersion, '3.4.10', '<')) {
    // Site settings now fallback to new main settings when empty. When all
    // sites share a value, it becomes the main one and sites use it.
    $mainDefaults = $this->getConfig()['zipdownload']['settings'];
    $booleans = ['zipdownload_enabled', 'zipdownload_iiif_full', 'zipdownload_single_as_file'];
    $emptyValues = [
        'zipdownload_type' => [],
        'zipdownload_type_labels' => [],
        'zipdownload_asset' => null,
    ];
    // An empty text or asset removed the file, so it becomes an exclusion
    // when the main settings define one.
    $files = [
        'zipdownload_text' => 'text',
        'zipdownload_asset' => 'asset',
    ];
    $excludes = [];
    // Numbers may be stored as integers or strings, texts with any line end.
    $normalize = fn ($v) => is_scalar($v) && !is_bool($v) ? strtr((string) $v, ["\r\n" => "\n"]) : $v;
    $siteIds = $api->search('sites', [], ['returnScalar' => 'id'])->getContent();
    foreach ($mainDefaults as $name => $default) {
        $default = $normalize($default);
        $isBoolean = in_array($name, $booleans, true);
        $empty = array_key_exists($name, $emptyValues) ? $emptyValues[$name] : '';
        $values = [];
        foreach ($siteIds as $siteId) {
            $siteSettings->setTargetId($siteId);
            $value = $siteSettings->get($name);
            if ($name === 'zipdownload_text') {
                // The previous code used an empty text when not set.
                $value ??= '';
            } elseif ($value === null || ($name === 'zipdownload_asset' && $value === '')) {
                $value = $default;
            }
            $values[$siteId] = $isBoolean ? (bool) $value : $normalize($value);
        }
        $distinct = array_unique(array_map('serialize', $values));
        $mainValue = count($distinct) === 1 ? reset($values) : $default;
        if ($settings->get($name) === null || count($distinct) === 1) {
            $settings->set($name, $mainValue);
        }
        foreach ($values as $siteId => $value) {
            $siteSettings->setTargetId($siteId);
            if ($value === $mainValue) {
                $siteSettings->set($name, $empty);
            } elseif (isset($files[$name]) && ($value === '' || $value === null)) {
                $siteSettings->set($name, $empty);
                $excludes[$siteId][] = $files[$name];
            } elseif ($isBoolean) {
                $siteSettings->set($name, $value ? 'yes' : 'no');
            }
        }
    }
    foreach ($siteIds as $siteId) {
        $siteSettings->setTargetId($siteId);
        $siteSettings->set('zipdownload_exclude', $excludes[$siteId] ?? []);
    }

    $message = new PsrMessage(
        'The options are now available in main settings. Each site uses them by default and can override them. When all sites had the same value, it was moved to main settings.' // @translate
    );
    $messenger->addSuccess($message);
}
