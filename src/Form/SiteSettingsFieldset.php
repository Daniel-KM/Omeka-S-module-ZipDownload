<?php declare(strict_types=1);

namespace ZipDownload\Form;

use Common\Form\Element as CommonElement;
use Laminas\Form\Element;
use Laminas\Form\Fieldset;
use Omeka\Form\Element as OmekaElement;

class SiteSettingsFieldset extends Fieldset
{
    /**
     * @var string
     */
    protected $label = 'Zip Download'; // @translate

    protected $elementGroups = [
        'zip_download' => 'Zip Download', // @translate
    ];

    public function init(): void
    {
        $this
            ->setAttribute('id', 'zip-download')
            ->setOption('element_groups', $this->elementGroups)

            ->add([
                'name' => 'zipdownload_enabled',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'zip_download',
                    'label' => 'Enable download zip', // @translate
                    'info' => 'Allow visitors to download resource files. When disabled, the download button is hidden and the download endpoint returns a 404 error.', // @translate
                ],
                'attributes' => [
                    'id' => 'zipdownload_enabled',
                ],
            ])
            ->add([
                'name' => 'zipdownload_content',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'element_group' => 'zip_download',
                    'label' => 'Download content', // @translate
                    'value_options' => [
                        'all' => 'All media files (zip archive)', // @translate
                        'primary' => 'Primary media only (single file, no zip)', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'zipdownload_content',
                ],
            ])
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
            ->add([
                'name' => 'zipdownload_single_as_file',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'zip_download',
                    'label' => 'Output single file as native file', // @translate
                ],
                'attributes' => [
                    'id' => 'zipdownload_single_as_file',
                ],
            ])
            ->add([
                'name' => 'zipdownload_tag',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'element_group' => 'zip_download',
                    'label' => 'Download element', // @translate
                    'info' => 'Use a button (default) to avoid scraping by bots, or a link for better accessibility.', // @translate
                    'value_options' => [
                        'button' => 'Button (recommended)', // @translate
                        'link' => 'Link', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'zipdownload_tag',
                ],
            ])
            ->add([
                'name' => 'zipdownload_max',
                'type' => Element\Number::class,
                'options' => [
                    'element_group' => 'zip_download',
                    'label' => 'Maximum resources for batch download', // @translate
                    'info' => 'Maximum number of resources that can be downloaded at once via query. Set 0 for no limit.', // @translate
                ],
                'attributes' => [
                    'id' => 'zipdownload_max',
                    'min' => 0,
                    'value' => 25,
                ],
            ])
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
    }
}
