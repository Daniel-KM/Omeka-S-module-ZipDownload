Zip Download (module for Omeka S)
=================================

> __New versions of this module and support for Omeka S version 3.0 and above
> are available on [GitLab], which seems to respect users and privacy better
> than the previous repository.__

[Zip Download] is a module for [Omeka S] that allows visitors to download
resource files as zip archives. Files are streamed instantly in real-time
without server storage. A confirmation dialog shows the file size before
downloading.


Installation
------------

See general end user documentation for [installing a module].

The module requires the module [Common], version 3.4.74 or later.

For zip streaming (multiple files), PHP 8.1+ and the library `maennchen/zipstream-php` v3
are required (installed via Composer).

* From the zip

Download the last release [ZipDownload.zip] from the list of releases, and
uncompress it in the `modules` directory.

- From the source and for development

If the module was installed from the source, rename the name of the folder of
the module to `ZipDownload`, go to the root of the module, and run:

```sh
composer install --no-dev
```


Quick start
-----------

The module provides:
- A view helper `downloadZip()` to display download links in themes
- A resource page block layout for the resource pages
- Site settings to configure download behavior

**Important**: Download is disabled by default for security reasons. Enable it
in site settings before the download button appears.


Configuration
-------------

Site settings allow to configure:

- Enable download zip: Must be checked to enable the feature (default: disabled).
  Unauthorized download attempts are logged.
- Download content: Download primary media only or all media files (zip archive)
- File type: Original, large, medium, or square thumbnails
- Single file as native: When enabled, single files are delivered as native
  files instead of zip archives
- Download element: Use a button (default, recommended) to avoid scraping by
  bots, or a link for better accessibility
- Maximum resources: Limit the number of resources for batch downloads (default: 25).
  Used when downloading multiple resources via API query.
- Copyright text: Text included in a COPYRIGHT.txt file inside the zip, with
  placeholders:
  - `{dcterms:creator}`, `{dcterms:date}` - Resource metadata (any property)
  - `{file_count}`, `{resource_count}` - Counts
  - `{citation}` - Auto-generated citation
  - `{resource_id}`, `{resource_title}`, `{resource_url}` - Resource info
  - `{site_title}`, `{site_url}`, `{main_title}` - Site/installation info
  - `{date}`, `{datetime}` - Download date


Resource page blocks
--------------------

### Download Zip

Display a download button that allows visitors to download all resource files
as a zip archive. When clicked, a confirmation dialog shows the file size.

### Download Primary Media

Display a simple download button for the primary media file only.
This resource block was previously included in module [Block Plus].


Theme view helper
-----------------

The view helper `downloadZip()` displays a download link for resource files with
a confirmation dialog showing the file size.

The helper accepts either a single resource or an API query array to download
multiple resources at once.

```php
// Basic usage with a single resource.
echo $this->downloadZip($resource);

// With custom options for a single resource.
echo $this->downloadZip($resource, [
    // 'primary' or 'all' (default).
    'content' => 'all',
    // 'original', 'large', 'medium', 'square'.
    'type' => 'original',
    // true to output single file without zip.
    'single_as_file' => false,
    // 'button' (default, for security) or 'link'.
    'tag' => 'button',
    // Custom button label.
    'label' => 'Download files',
    // Additional CSS classes.
    'class' => 'my-class',
]);

// Download multiple resources via api query:
echo $this->downloadZip(['resource_class_id' => 5]);

// Query with specific resource type (item or media).
echo $this->downloadZip(['resource_class_id' => 5], [
    // 'items' (default) or 'media'.
    'resource_type' => 'media',
]);

// Query with property filter
echo $this->downloadZip(
    [
        'property' => [
            ['property' => 'dcterms:subject', 'type' => 'eq', 'text' => 'art'],
        ],
    ],
    [
        'type' => 'large',
        'label' => 'Download collection',
    ]
);
```

When using a query, the helper respects the site setting `zipdownload_max`
(default: 25) to limit the number of resources. The query is automatically
constrained to the current site. Files are organized in folders by resource
inside the zip archive.


TODO
----

- [ ] Add a limit by collection to forbid zipping some items or media, or file types.


Warning
-------

Use it at your own risk.

It’s always recommended to backup your files and your databases and to check
your archives regularly so you can roll back if needed.

```sh
# database dump example
mariadb-dump -u omeka -p omeka | gzip > "omeka.$(date +%Y%m%d_%H%M%S).sql.gz"
```


Troubleshooting
---------------

See online issues on the [module issues] page on GitLab.


License
-------

This module is published under the [CeCILL v2.1] license, compatible with
[GNU/GPL] and approved by [FSF] and [OSI].

This software is governed by the CeCILL license under French law and abiding by
the rules of distribution of free software. You can use, modify and/ or
redistribute the software under the terms of the CeCILL license as circulated by
CEA, CNRS and INRIA at the following URL "http://www.cecill.info".

As a counterpart to the access to the source code and rights to copy, modify and
redistribute granted by the license, users are provided only with a limited
warranty and the software's author, the holder of the economic rights, and the
successive licensors have only limited liability.

In this respect, the user's attention is drawn to the risks associated with
loading, using, modifying and/or developing or reproducing the software by the
user in light of its specific status of free software, that may mean that it is
complicated to manipulate, and that also therefore means that it is reserved for
developers and experienced professionals having in-depth computer knowledge.
Users are therefore encouraged to load and test the software's suitability as
regards their requirements in conditions enabling the security of their systems
and/or data to be ensured and, more generally, to use and operate it in the same
conditions as regards security.

The fact that you are presently reading this means that you have had knowledge
of the CeCILL license and that you accept its terms.


Copyright
---------

* Copyright Daniel Berthereau, 2021-2025 (see [Daniel-KM] on GitLab)

This module was designed for [Explore PSL] and [Musée de Bretagne].


[Zip Download]: https://gitlab.com/Daniel-KM/Omeka-S-module-ZipDownload
[Omeka S]: https://omeka.org/s
[installing a module]: https://omeka.org/s/docs/user-manual/modules/#installing-modules
[Common]: https://gitlab.com/Daniel-KM/Omeka-S-module-Common
[ZipDownload.zip]: https://gitlab.com/Daniel-KM/Omeka-S-module-ZipDownload/-/releases
[Block Plus]: https://gitlab.com/Daniel-KM/Omeka-S-module-BlockPlus
[module issues]: https://gitlab.com/Daniel-KM/Omeka-S-module-ZipDownload/-/issues
[CeCILL v2.1]: https://www.cecill.info/licences/Licence_CeCILL_V2.1-en.html
[GNU/GPL]: https://www.gnu.org/licenses/gpl-3.0.html
[FSF]: https://www.fsf.org
[OSI]: http://opensource.org
[Explore PSL]: https://bibnum.explore.psl.eu
[Musée de Bretagne]: https://collections.musee-bretagne.fr
[GitLab]: https://gitlab.com/Daniel-KM
[Daniel-KM]: https://gitlab.com/Daniel-KM "Daniel Berthereau"
