<?php declare(strict_types=1);

namespace ZipDownload;

// Load the module dependencies when installed as a zip.
// With composer, libraries are stored in omeka vendor/ and the module has none.
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

if (!trait_exists(\Common\TraitModule::class, false)) {
    if (file_exists(OMEKA_PATH . '/modules/Common/src/TraitModule.php')) {
        require_once OMEKA_PATH . '/modules/Common/src/TraitModule.php';
    } elseif (file_exists(OMEKA_PATH . '/composer-addons/modules/Common/src/TraitModule.php')) {
        require_once OMEKA_PATH . '/composer-addons/modules/Common/src/TraitModule.php';
    } elseif (file_exists(dirname(__DIR__) . '/Common/src/TraitModule.php')) {
        require_once dirname(__DIR__) . '/Common/src/TraitModule.php';
    }
}

use Common\Stdlib\PsrMessage;
use Common\TraitModule;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\ModuleManager\ModuleManager;
use Laminas\Mvc\MvcEvent;
use Omeka\Module\AbstractModule;

/**
 * ZipDownload.
 *
 * Stream resource files as zip archives for download.
 *
 * @copyright Daniel Berthereau, 2021-2026
 * @license http://www.cecill.info/licences/Licence_CeCILL_V2.1-en.txt
 */
class Module extends AbstractModule
{
    use TraitModule;

    const NAMESPACE = __NAMESPACE__;

    public function onBootstrap(MvcEvent $event): void
    {
        parent::onBootstrap($event);

        /** @var \Omeka\Permissions\Acl $acl */
        $acl = $this->getServiceLocator()->get('Omeka\Acl');
        $acl
            ->allow(
                null,
                [Controller\Site\DownloadController::class]
            );
    }

    protected function preInstall(): void
    {
        $services = $this->getServiceLocator();
        $translator = $services->get('MvcTranslator');

        $errors = [];

        if (!method_exists($this, 'checkModuleActiveVersion') || !$this->checkModuleActiveVersion('Common', '3.4.88')) {
            $message = new \Omeka\Stdlib\Message(
                $translator->translate('The module %1$s should be upgraded to version %2$s or later.'), // @translate
                'Common', '3.4.88'
            );
            $errors[] = (string) $message;
        }

        if (PHP_VERSION_ID < 80100) {
            $message = new \Omeka\Stdlib\Message(
                $translator->translate('This module requires PHP 8.1 or later for zip streaming with ZipStream library.') // @translate
            );
            $errors[] = (string) $message;
        }

        if ($errors) {
            throw new \Omeka\Module\Exception\ModuleCannotInstallException(implode("\n", $errors));
        }
    }

    protected function postInstall(): void
    {
        $services = $this->getServiceLocator();
        $messenger = $services->get('ControllerPluginManager')->get('messenger');
        $urlHelper = $services->get('ViewHelperManager')->get('url');
        $message = new PsrMessage(
            'By default, the zip download is not allowed on sites. Enable it in the site settings of each site.' // @translate
        );
        $messenger->addWarning($message);
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager): void
    {
        $sharedEventManager->attach(
            \Omeka\Form\SiteSettingsForm::class,
            'form.add_elements',
            [$this, 'handleSiteSettings']
        );
    }
}
