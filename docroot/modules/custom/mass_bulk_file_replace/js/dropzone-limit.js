(function (Drupal, once, drupalSettings) {
  'use strict';

  // Waits for integration to attach and the Dropzone instance to exist.
  function withDropzone(el, cb, tries = 20) {
    if (el.dropzone) return cb(el.dropzone);
    if (tries <= 0) return;
    setTimeout(() => withDropzone(el, cb, tries - 1), 50);
  }

  Drupal.behaviors.mbfDropzoneLimit = {
    attach(context) {
      const limit =
        (drupalSettings.massBulkFileReplace &&
          drupalSettings.massBulkFileReplace.maxUploads) || 10;

      once('mbf-dz-limit', '.dropzone-enable', context).forEach((el) => {
        withDropzone(el, (dz) => {
          // Ensure runtime options exist and set our max + message.
          dz.options.maxFiles = limit;
          dz.options.dictMaxFilesExceeded =
            Drupal.t('Only the first @count files were added. Please add extra files in the next batch.', {'@count': limit});

          // Add a small inline banner inside the widget when over cap.
          const showBanner = (msg) => {
            let banner = el.querySelector('[data-dz-limit-msg]');
            if (!banner) {
              banner = document.createElement('div');
              banner.setAttribute('data-dz-limit-msg', '1');
              banner.className = 'messages messages--warning';
              el.insertBefore(banner, el.firstChild);
            }
            banner.textContent = msg;
          };
          const hideBannerIfOk = () => {
            const banner = el.querySelector('[data-dz-limit-msg]');
            if (banner && dz.getAcceptedFiles().length <= limit) banner.remove();
          };

          // Built-in event in many builds.
          dz.on('maxfilesexceeded', function (file) {
            const msg = dz.options.dictMaxFilesExceeded;
            file.status = Dropzone.ERROR;
            dz.emit('error', file, msg);
            dz.emit('complete', file);
            if (file.previewElement) {
              file.previewElement.classList.add('dz-error');
              const em = file.previewElement.querySelector('[data-dz-errormessage]');
              if (em) em.textContent = msg;
            }
            showBanner(msg);
          });

          // Guard for builds that add before enforcing maxFiles.
          dz.on('addedfile', function (file) {
            const accepted = dz.getAcceptedFiles().filter((f) => f !== file).length;
            if (accepted >= limit) {
              const msg = dz.options.dictMaxFilesExceeded;
              file.status = Dropzone.ERROR;
              dz.emit('error', file, msg);
              dz.emit('complete', file);
              if (file.previewElement) {
                file.previewElement.classList.add('dz-error');
                const em = file.previewElement.querySelector('[data-dz-errormessage]');
                if (em) em.textContent = msg;
              }
              showBanner(msg);
            }
          });

          dz.on('removedfile', hideBannerIfOk);
        });
      });
    },
  };
})(Drupal, once, drupalSettings);
