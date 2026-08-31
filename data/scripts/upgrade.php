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

if (!method_exists($this, 'checkModuleActiveVersion') || !$this->checkModuleActiveVersion('Common', '3.4.91')) {
    $message = new \Omeka\Stdlib\Message(
        $translate('The module %1$s should be upgraded to version %2$s or later.'), // @translate
        'Common', '3.4.91'
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
