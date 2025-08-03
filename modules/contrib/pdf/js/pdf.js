(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.pdf = {
    attach: function(context, settings) {
      pdfjsLib.GlobalWorkerOptions.workerSrc = settings.pdf.workerSrc;

      const canvases = context.getElementsByClassName("pdf-thumbnail");
      Array.prototype.forEach.call(canvases, function(canvas) {
        const file = canvas.attributes.file.value;
        let loadingTask = pdfjsLib.getDocument({url: file});
        loadingTask.promise.then(function(pdf) {
          pdf.getPage(1).then(function(page) {
            const scale = (canvas.attributes.scale) ? canvas.attributes.scale.value : 1;
            let viewport = page.getViewport({ scale: scale });
            let context = canvas.getContext('2d');
            canvas.height = viewport.height;
            canvas.width = viewport.width;
            var renderContext = {
              canvasContext: context,
              viewport
            };
            const renderTask = page.render(renderContext);
          });
        });
      });

      const fields = context.getElementsByClassName("pdf-pages");
      Array.prototype.forEach.call(fields, function(container) {
        const file = container.attributes.file.value;
        let loadingTask = pdfjsLib.getDocument({url: file});
        loadingTask.promise.then(function(pdf) {
          for (var i = 1; i <= pdf.numPages; i++) {
            pdf.getPage(i).then(function(page) {
              var canvas = document.createElement("canvas");
              canvas.setAttribute("class", "pdf-canvas");
              container.appendChild(canvas);
              const scale = (container.attributes.scale) ? container.attributes.scale.value : 1;
              var viewport = page.getViewport({scale: scale});
              var context = canvas.getContext('2d');
              canvas.height = viewport.height;
              canvas.width = viewport.width;
              var renderContext = {
                canvasContext: context,
                viewport
              };
              const renderTask = page.render(renderContext);
            });
          }
        });
      });
    }
  };
})(jQuery, Drupal, drupalSettings);
