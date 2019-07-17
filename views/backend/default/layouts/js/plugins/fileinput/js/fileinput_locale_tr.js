/*!
 * FileInput <_LANG_> Translations
 *
 * This file must be loaded after 'fileinput.js'. Patterns in braces '{}', or
 * any HTML markup tags in the messages must not be converted or translated.
 *
 * @see http://github.com/kartik-v/bootstrap-fileinput
 *
 * NOTE: this file must be saved in UTF-8 encoding.
 */
(function ($) {
    "use strict";

    $.fn.fileinput.locales._LANG_ = {
        fileSingle: 'dosya',
        filePlural: 'dosya',
        browseLabel: 'Seç &hellip;',
        removeLabel: 'Kaldır',
        removeTitle: 'Seçileri dosyaları temizle',
        cancelLabel: 'İptal',
        cancelTitle: 'Mevcut yüklemeyi iptal et',
        uploadLabel: 'Yükle',
        uploadTitle: 'Seçili dosyaları yükle',
        msgSizeTooLarge: 'File "{name}" (<b>{size} KB</b>) exceeds maximum allowed upload size of <b>{maxSize} KB</b>. Please retry your upload!',
        msgFilesTooLess: 'You must select at least <b>{n}</b> {files} to upload. Please retry your upload!',
        msgFilesTooMany: 'Number of files selected for upload <b>({n})</b> exceeds maximum allowed limit of <b>{m}</b>. Please retry your upload!',
        msgFileNotFound: 'Dosya "{name}" bulunamadı!',
        msgFileSecured: 'Security restrictions prevent reading the file "{name}".',
        msgFileNotReadable: 'Dosya "{name}" okunabilir değil.',
        msgFilePreviewAborted: 'Önzileme "{name}" için iptal edildi.',
        msgFilePreviewError: 'An error occurred while reading the file "{name}".',
        msgInvalidFileType: 'Invalid type for file "{name}". Only "{types}" files are supported.',
        msgInvalidFileExtension: 'Invalid extension for file "{name}". Only "{extensions}" files are supported.',
        msgValidationError: 'Dosya Yükleme Hatası',
        msgLoading: 'Loading file {index} of {files} &hellip;',
        msgProgress: 'Loading file {index} of {files} - {name} - {percent}% completed.',
        msgSelected: '{n} dosya seçildi',
        msgFoldersNotAllowed: 'Yalnızca dosyaları sürükleyip bırakın! {n} tane dizin atıldı.',
        dropZoneTitle: 'Dosyaları buraya sürükleyip &hellip;'
    };

    $.extend($.fn.fileinput.defaults, $.fn.fileinput.locales._LANG_);
})(window.jQuery);