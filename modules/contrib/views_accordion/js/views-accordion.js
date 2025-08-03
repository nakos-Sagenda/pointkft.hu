/**
 * @file
 */

(($, Drupal, drupalSettings) => {
  Drupal.behaviors.views_accordion = {
    attach() {
      if (drupalSettings.views_accordion) {
        $.each(drupalSettings.views_accordion, function processViews() {
          const $display = $(`${this.display}:not(.ui-accordion)`);

          /* The row count to be used if Row to display opened on start is set to random */
          let rowCount = 0;

          /* Prepare our markup for jquery ui accordion */
          $(this.header, $display).each(function processHeader() {
            // Wrap the accordion content within a div if necessary.
            if (!this.usegroupheader) {
              $(this).siblings().wrapAll('<div></div>');
              rowCount += 1;
            }
          });

          if (this.rowstartopen === 'random') {
            this.rowstartopen = Math.floor(Math.random() * rowCount);
          }

          // The settings for the accordion.
          const accordionSettings = {
            header: this.header,
            animate: {
              easing: this.animated,
              duration: parseInt(this.duration, 10),
            },
            active: this.rowstartopen,
            collapsible: this.collapsible,
            heightStyle: this.heightStyle,
            event: this.event,
            icons: false,
          };
          if (this.useHeaderIcons) {
            accordionSettings.icons = {
              header: this.iconHeader,
              activeHeader: this.iconActiveHeader,
            };
          }

          /* jQuery UI accordion call */
          $display.accordion(accordionSettings);
        });
      }
    },
  };
})(jQuery, Drupal, drupalSettings);
