(($) => {
  $(() => {
    // Hide the description for any GDPR checkboxes.
    let container = $('.gdpr_consent_agreement').parent();
    let desc = container.find('.description');

    if (!desc.length) {
      container = container.parent();
      desc = container.next('.description');
    }

    desc.hide();

    $('<a href="javascript:void(0)" class="gdpr_agreed_toggle">?</a>')
      .appendTo(container)
      .click(() => {
        // eslint-disable-next-line no-shadow
        let desc = $(this).parent().find('.description');
        if (!desc.length) {
          desc = $(this).parent().next('.description');
        }

        desc.slideToggle();
      });

    // Do the same for implicit
    container = $('.gdpr_consent_implicit').parent();
    desc = container.find('.description');
    desc.hide();

    $('<a href="javascript:void(0)" class="gdpr_agreed_toggle">?</a>')
      .appendTo(container)
      .click(() => {
        $(this).parent().find('.description').slideToggle();
      });
  });
})(jQuery);
