'use strict';

/**
 * Requires common-dialog.js.
 */

/**
 * Download zip link handler with confirmation dialog.
 *
 * This script intercepts clicks on .zip-download-link elements and shows a
 * confirmation dialog with file size information before starting the download.
 * When multiple file types are available, radio buttons are shown to let the
 * visitor choose the format.
 */
var ZipDownload = (function() {

    var self = {};

    /**
     * Handle click on download link or button.
     */
    self.handleClick = function(event) {
        const element = event.target.closest('.zip-download-link');
        if (!element) {
            return;
        }

        // Check if dialog confirmation is required.
        if (!element.dataset.dialogConfirm) {
            return;
        }

        event.preventDefault();

        const message = element.dataset.dialogMessage || '';
        const filename = element.dataset.filename || '';
        // Support both link (href) and button (data-download-url).
        const url = element.dataset.downloadUrl || element.href || '';

        // Check for multiple download types.
        if (element.dataset.downloadTypes) {
            try {
                var typesData = JSON.parse(element.dataset.downloadTypes);
                if (Object.keys(typesData).length > 1) {
                    self.handleMultiTypeDownload(typesData, message, filename);
                    return;
                }
            } catch (e) {
                // Fall through to default behavior.
            }
        }

        CommonDialog.dialogConfirm({
            heading: Omeka.jsTranslate('Confirm download'),
            message: message,
            textOk: Omeka.jsTranslate('Download'),
            textCancel: Omeka.jsTranslate('Cancel'),
        }).then(function(confirmed) {
            if (confirmed) {
                self.startDownload(url, filename);
            }
        });
    };

    /**
     * Handle download with multiple type choices.
     *
     * Shows radio buttons in the confirmation dialog so the visitor can choose
     * the file format. The selected type determines the download URL.
     */
    self.handleMultiTypeDownload = function(typesData, message, filename) {
        var types = Object.keys(typesData);
        var selectedType = types[0];

        // Build radio buttons HTML.
        var radiosHtml = '<fieldset class="zip-download-type-choice" style="border: none; padding: 0; margin: 1em 0 0;">'
            + '<legend style="font-weight: bold; margin-bottom: .5em;">' + Omeka.jsTranslate('Format') + '</legend>';
        for (var i = 0; i < types.length; i++) {
            var type = types[i];
            var data = typesData[type];
            var checked = i === 0 ? ' checked' : '';
            radiosHtml += '<label style="display: block; margin-bottom: .35em; cursor: pointer;">'
                + '<input type="radio" name="zip-download-type" value="' + type + '"' + checked + '> '
                + data.label + ' (' + data.formattedSize + ')'
                + '</label>';
        }
        radiosHtml += '</fieldset>';

        CommonDialog.dialogConfirm({
            heading: Omeka.jsTranslate('Confirm download'),
            message: message,
            body: radiosHtml,
            textOk: Omeka.jsTranslate('Download'),
            textCancel: Omeka.jsTranslate('Cancel'),
        }).then(function(confirmed) {
            if (confirmed) {
                var selectedData = typesData[selectedType];
                self.startDownload(selectedData.url, filename);
            }
        });

        // Track radio selection via event delegation on the dialog.
        var dialog = document.querySelector('dialog.dialog-generic');
        if (dialog) {
            dialog.addEventListener('change', function(e) {
                if (e.target.name === 'zip-download-type') {
                    selectedType = e.target.value;
                }
            });
        }
    };

    /**
     * Start the file download.
     *
     * Creates a temporary link to trigger the download, which allows the
     * download to proceed even with the 'download' attribute.
     */
    self.startDownload = function(url, filename) {
        const tempLink = document.createElement('a');
        tempLink.href = url;
        tempLink.download = filename;
        tempLink.style.display = 'none';
        document.body.appendChild(tempLink);
        tempLink.click();
        document.body.removeChild(tempLink);
    };

    /**
     * Initialize event listeners.
     */
    self.init = function() {
        document.addEventListener('click', self.handleClick);
        return self;
    };

    return self;

})();

document.addEventListener('DOMContentLoaded', function() {
    ZipDownload.init();
});
