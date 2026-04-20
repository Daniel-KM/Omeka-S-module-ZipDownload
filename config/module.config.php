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
            'downloadZip' => Site\ResourcePageBlockLayout\DownloadZip::class,
        ],
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
        'site_settings' => [
            'zipdownload_enabled' => false,
            'zipdownload_content' => 'all',
            'zipdownload_type' => ['original'],
            'zipdownload_type_labels' => [],
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
    ],
];
