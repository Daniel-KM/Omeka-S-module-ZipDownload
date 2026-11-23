<?php declare(strict_types=1);

namespace ZipDownload;

return [
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
    ],
    'view_helpers' => [
        'invokables' => [
            'downloadZip' => View\Helper\DownloadZip::class,
        ],
    ],
    'form_elements' => [
        'invokables' => [
            Form\SettingsFieldset::class => Form\SettingsFieldset::class,
            Form\SiteSettingsFieldset::class => Form\SiteSettingsFieldset::class,
        ],
    ],
    'controllers' => [
        'invokables' => [
            Controller\Site\DownloadController::class => Controller\Site\DownloadController::class,
        ],
    ],
    'router' => [
        'routes' => [
            'site' => [
                'child_routes' => [
                    'zip-download' => [
                        'type' => \Laminas\Router\Http\Segment::class,
                        'options' => [
                            'route' => '/zip-download/:resource-type[/:resource-id]',
                            'constraints' => [
                                'resource-type' => 'item|media',
                                'resource-id' => '\d+',
                            ],
                            'defaults' => [
                                '__NAMESPACE__' => 'ZipDownload\Controller\Site',
                                'controller' => 'DownloadController',
                                'action' => 'download',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'resource_page_block_layouts' => [
        'invokables' => [
            'downloadPrimary' => Site\ResourcePageBlockLayout\DownloadPrimary::class,
            'downloadPrimaryDigitalObject' => Site\ResourcePageBlockLayout\DownloadPrimaryDigitalObject::class,
            'downloadZip' => Site\ResourcePageBlockLayout\DownloadZip::class,
        ],
    ],
    'js_translate_strings' => [
        'Cancel', // @translate
        'Confirm download', // @translate
        'Download', // @translate
        'Format', // @translate
    ],
    'translator' => [
        'translation_file_patterns' => [
            [
                'type' => 'gettext',
                'base_dir' => dirname(__DIR__) . '/language',
                'pattern' => '%s.mo',
                'text_domain' => null,
            ],
        ],
    ],
    'zipdownload' => [
        'settings' => [
            'zipdownload_enabled' => false,
            'zipdownload_content' => 'all',
            'zipdownload_type' => ['original'],
            'zipdownload_type_labels' => [],
            'zipdownload_asset' => null,
            'zipdownload_text_filename' => 'COPYRIGHT.txt',
            'zipdownload_iiif_full' => false,
            'zipdownload_iiif_full_max' => 4,
            'zipdownload_single_as_file' => false,
            'zipdownload_tag' => 'button',
            'zipdownload_max' => 25,
            'zipdownload_text' => <<<'TXT'
                Source: {main_title}
                Document: {resource_title}
                Author: {dcterms:creator}
                Date: {dcterms:date}
                Number of files: {file_count}
                Downloaded on: {date}

                Citation: {citation}

                URL: {resource_url}
                TXT,
        ],
        // An empty value uses the main setting; booleans are "yes" or "no".
        'site_settings' => [
            'zipdownload_enabled' => '',
            'zipdownload_content' => '',
            'zipdownload_type' => [],
            'zipdownload_type_labels' => [],
            'zipdownload_asset' => null,
            'zipdownload_text_filename' => '',
            'zipdownload_iiif_full' => '',
            'zipdownload_iiif_full_max' => '',
            'zipdownload_single_as_file' => '',
            'zipdownload_tag' => '',
            'zipdownload_max' => '',
            'zipdownload_text' => '',
        ],
    ],
];
