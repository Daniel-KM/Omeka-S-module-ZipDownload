<?php declare(strict_types=1);

namespace ZipDownload\Form;

use Common\Form\Element as CommonElement;
use Laminas\Form\Element;
use Laminas\Form\Fieldset;
use Omeka\Form\Element as OmekaElement;

class SettingsFieldset extends Fieldset
{
    /**
     * @var string
     */
    protected $label = 'Zip Download'; // @translate

    protected $elementGroups = [
        'zip_download' => 'Zip Download', // @translate
    ];

    /**
     * In site settings, an empty value uses the main settings.
     *
     * @var bool
     */
    protected $isSite = false;

    public function init(): void
    {
        $this
            ->setAttribute('id', 'zip-download')
            ->setOption('element_groups', $this->elementGroups)
        ;

        if ($this->isSite) {
            $this->add([
                'name' => 'zipdownload_note_main',
                'type' => CommonElement\Note::class,
                'options' => [
                    'element_group' => 'zip_download',
                    'text' => 'Empty values and "Use main settings" use the values of the main settings.', // @translate
                ],
            ]);
        }

        $this
            ->add($this->booleanElement(
                'zipdownload_enabled',
                $this->isSite ? 'Enable download zip' : 'Enable download zip on all sites', // @translate
                'Allow visitors to download resource files. When disabled, the download button is hidden and the download endpoint returns a 404 error.' // @translate
            ))
            ->add($this->radioElement(
                'zipdownload_content',
                'Download content', // @translate
                [
                    'all' => 'All media files (zip archive)', // @translate
                    'primary' => 'Primary media only (single file, no zip)', // @translate
                ]
            ))
            ->add([
                'name' => 'zipdownload_type',
                'type' => CommonElement\OptionalMultiCheckbox::class,
                'options' => [
                    'element_group' => 'zip_download',
                    'label' => 'File types available for download', // @translate
                    'info' => 'Select which file types visitors can download. If multiple types are selected, visitors will be able to choose in the download dialog.', // @translate
                    'value_options' => [
                        'original' => 'Original', // @translate
                        'large' => 'Large', // @translate
                        'medium' => 'Medium', // @translate
                        'square' => 'Square', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'zipdownload_type',
                ],
            ])
            ->add([
                'name' => 'zipdownload_type_labels',
                'type' => OmekaElement\ArrayTextarea::class,
                'options' => [
                    'element_group' => 'zip_download',
                    'label' => 'File type labels in download dialog', // @translate
                    'info' => 'Override the dialog labels for each file type, one per line as "type = label". Leave empty to use the default translated labels.', // @translate
                    'as_key_value' => true,
                ],
                'attributes' => [
                    'id' => 'zipdownload_type_labels',
                    'rows' => 4,
                    'placeholder' => <<<'TXT'
                        original = High definition
                        large = Medium definition
                        TXT,
                ],
            ])
            ->add($this->booleanElement(
                'zipdownload_iiif_full',
                'Fetch full size images of iiif media', // @translate
                'Iiif media have no original file. By default, the local derivative "large" is used for the type "original". When checked, the full image is fetched from the image server for each download, so the zip is better but slower and heavier.' // @translate
            ))
            ->add($this->numberElement(
                'zipdownload_iiif_full_max',
                'Maximum number of files to fetch in full size', // @translate
                'When a download has more files, the local derivatives "large" are used for all of them, so the image server is not overloaded. Set 0 for no limit.' // @translate
            ))
            ->add($this->booleanElement(
                'zipdownload_single_as_file',
                'Output single file as native file' // @translate
            ))
            ->add($this->radioElement(
                'zipdownload_tag',
                'Download element', // @translate
                [
                    'button' => 'Button (recommended)', // @translate
                    'link' => 'Link', // @translate
                ],
                'Use a button (default) to avoid scraping by bots, or a link for better accessibility.' // @translate
            ))
            ->add($this->numberElement(
                'zipdownload_max',
                'Maximum resources for batch download', // @translate
                'Maximum number of resources that can be downloaded at once via query. Set 0 for no limit.' // @translate
            ))
            ->add([
                'name' => 'zipdownload_asset',
                'type' => OmekaElement\Asset::class,
                'options' => [
                    'element_group' => 'zip_download',
                    'label' => 'Asset to include in zip', // @translate
                    'info' => 'Optional file (e.g. PDF presenting the institution) included in every generated zip.', // @translate
                ],
                'attributes' => [
                    'id' => 'zipdownload_asset',
                ],
            ])
            ->add([
                'name' => 'zipdownload_text_filename',
                'type' => Element\Text::class,
                'options' => [
                    'element_group' => 'zip_download',
                    'label' => 'Name of the text file to add in the zip', // @translate
                    'info' => 'This text usually contains the record, the url and the copyright. Default: COPYRIGHT.txt', // @translate
                ],
                'attributes' => [
                    'id' => 'zipdownload_text_filename',
                    'placeholder' => 'COPYRIGHT.txt',
                ],
            ])
            ->add([
                'name' => 'zipdownload_text',
                'type' => Element\Textarea::class,
                'options' => [
                    'element_group' => 'zip_download',
                    'label' => 'Text of the file to add in the zip', // @translate
                    'info' => 'This text usually contains the record, the url and the copyright.', // @translate
                ],
                'attributes' => [
                    'id' => 'zipdownload_text',
                    'rows' => 6,
                    'placeholder' => <<<'TXT'
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
            ])
        ;

        if ($this->isSite) {
            $this->add([
                'name' => 'zipdownload_exclude',
                'type' => CommonElement\OptionalMultiCheckbox::class,
                'options' => [
                    'element_group' => 'zip_download',
                    'label' => 'Remove files set in main settings', // @translate
                    'value_options' => [
                        'text' => 'No text file', // @translate
                        'asset' => 'No asset', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'zipdownload_exclude',
                ],
            ]);
        }
    }

    /**
     * A checkbox in main settings, a radio "main / yes / no" in site settings.
     */
    protected function booleanElement(string $name, string $label, ?string $info = null): array
    {
        if (!$this->isSite) {
            return [
                'name' => $name,
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'zip_download',
                    'label' => $label,
                    'info' => $info,
                ],
                'attributes' => [
                    'id' => $name,
                ],
            ];
        }
        return $this->radioElement($name, $label, [
            'yes' => 'Yes', // @translate
            'no' => 'No', // @translate
        ], $info);
    }

    protected function radioElement(string $name, string $label, array $valueOptions, ?string $info = null): array
    {
        if ($this->isSite) {
            $valueOptions = ['' => 'Use main settings'] + $valueOptions; // @translate
        }
        return [
            'name' => $name,
            'type' => CommonElement\OptionalRadio::class,
            'options' => [
                'element_group' => 'zip_download',
                'label' => $label,
                'info' => $info,
                'value_options' => $valueOptions,
            ],
            'attributes' => [
                'id' => $name,
            ],
        ];
    }

    protected function numberElement(string $name, string $label, ?string $info = null): array
    {
        return [
            'name' => $name,
            'type' => CommonElement\OptionalNumber::class,
            'options' => [
                'element_group' => 'zip_download',
                'label' => $label,
                'info' => $info,
            ],
            'attributes' => [
                'id' => $name,
                'min' => 0,
            ],
        ];
    }
}
