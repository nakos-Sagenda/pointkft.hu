# CKEditor 5 HTML Embed Plugin

This module adds support for the HTML Embed CKEditor 5 plugin.

See https://ckeditor.com/docs/ckeditor5/latest/features/html-embed.html


## Installation:

1. Install & Enable the module
2. Open Administration > Configuration > Content authoring >
   Text formats and editors (admin/config/content/formats)
3. Edit a text format's settings, like Full HTML
4. Drag n Drop the EmbedHTML to the toolbar to show it to the editors
5. If the "Limit allowed HTML tags and correct faulty HTML" filter is enabled,
   you may need to add additional HTML tags/attributes to its configuration to
   support whatever HTML tags you want to allow the editor to embed.
6. Save

#### Module Development Notes

When Drupal updates to use newer versions of CKEditor 5, it may be necessary to
update any files copied from here to your module.

In the module directory, run `yarn install` to set up the necessary assets. The
initial run of `install` may take a few minutes, but subsequent builds will be
faster.

After installing dependencies, plugins can be built with `yarn build` or `yarn
watch`. They will be built to `js/build/{pluginNameDirectory}.js`. This does not
yet work so the assets have to be copied manually.
