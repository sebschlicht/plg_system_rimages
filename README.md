# RIMAGES - Responsive Images Plugin for Joomla! 3

*RIMAGES* is a *Joomla!* plugin to make the images on your website responsive.
You can configure which images should be made responsive and which image sizes should be used, depending on the viewport width.
*RIMAGES* can automatically generate resized versions of the images - even if they're on remote servers.

*RIMAGES* helps to significantly decrease the page loading time and increase the Google *PageSpeed* rating which contributes to the Google search ranking of your website.
Thus *RIMAGES* is also suited for *Search Engine Optimization* (SEO).

**Features**:

* re-encode images according to the [Google recommendation for image optimization](https://developers.google.com/speed/docs/insights/OptimizeImages) (reduces the file size without dimension reduction)
* offer resized image versions to browsers on smaller devices
* automatically generate resized image versions
* increase Google *PageSpeed* rating => improve Google search ranking
* supports local images (files) and remote images (URLs)

## Installation

Download the latest *RIMAGES* extension package and use the *Joomla!* extension manager to install it.
The plugin is compatible with *Joomla!* 3.x.

Use the *Joomla!* extension manager to uninstall the plugin and remove the configured image folder if you don't need the responsive image versions anymore.

## Technologies and Compatibility

*RIMAGES* is based on the HTML5 [`picture` tag](https://www.w3schools.com/tags/tag_picture.asp) which is already [supported by the vast majority](https://caniuse.com/#feat=picture).
The tag is fully backwards-compatible, thus users with browsers that don't support the tag will still benefit from the plugin but won't support the automatic selection of resized image versions.

However, due to the additional `picture` tag (being a parent of the original `img` tag), the plugin could break JavaScript code (e.g. `$slide.children( 'img' )`) and CSS directives (e.g. `.slide > img`).
This can easily be prevented by using appropriate CSS selectors when configuring the plugin.

The automatic generation of resized image version depends on *ImageMagick* which has to be enabled on your server in order to use this feature.
If you don't have it, you'd have to create resized versions of your images by hand.

## Usage

At the very heart of this plugin is the configuration of breakpoint packages and breakpoints.
Breakpoints are organized in breakpoint packages which specify the images that its breakpoints should apply to.

Breakpoint packages can be configured in two contexts: global and content.
Content packages apply to images within the content component, such as in articles.
Global packages apply to all images on the page which haven't been covered by a content package before.
You can configure up to five breakpoint packages per context.

Whatever breakpoints you configure, all responsive versions of an image are stored in the specified image folder.
Whenever a responsive version is looked up or is to be generated, this is the directory that the plugin works with.

### Breakpoint Package

A breakpoint package identifies targeted images via a CSS selector.

For example, you could have configured a breakpoint package for all large images:

    cssSelector:'img.large'

Breakpoints within this package would apply to all images with the `large` class.

Further examples:

* `#slider .slide`
* `#logo`

>Please note that the selector is quite limited and doesn't support any feature that isn't shown in the examples.

### Breakpoint

Each breakpoint specifies an alternative version of an image that should be used.
In principle, a breakpoint is a maximum viewport width where a certain image should be displayed.
Good breakpoints may be the Bootstrap device widths (extra small, small, medium, large) or prominent device widths of your users.

Let's say you have configured the following breakpoint

    viewportWidth:480px

and the plugin finds an image on your website, for example `images/test.png`.
Then *RIMAGES* would look for an alternative version of this image with the given size and add it to the page, if available.
A device with a viewport width below 480 pixels will now use this alternative version.

## Image Generation

*RIMAGES* may generate responsive version of your images automatically.
All you need to do is to specify the maximum width of the generated image in the respective breakpoint.

Let's say you have configured the following breakpoint:

    viewportWidth:480px imageWidth:440px

Now *RIMAGES* may generate a responsive version of the image with a maximum width of 440 pixels for the 480 pixels viewport width if it's missing.
