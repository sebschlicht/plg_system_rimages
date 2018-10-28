# RIMAGES - Responsive Images Plugin for Joomla! 3

RIMAGES (*rim*) is a *Joomla!* plugin to make the images on your website responsive.

*rim* can compress your images and display smaller images on smaller devices.
Both compressend and resized images are generated automatically.
Use CSS selectors to limit this process to specific images.

Using *rim* helps to significantly decrease the page loading time and increase the Google *PageSpeed* rating, which contributes to the Google search ranking of your website.
Thus *rim* is a great addition to your *Search Engine Optimization* (SEO) toolbox.

**Features**:

* automatically compress/resize images
* use CSS selectors to specify target dimensions for your images
* offer resized image versions to browsers on smaller devices
* compress images according to the [Google recommendation for image optimization](https://developers.google.com/speed/docs/insights/OptimizeImages)
* increase Google *PageSpeed* rating which may improve the Google search ranking
* supports external images (URLs)
* Languages: English, German

## Usage

The usage of the plugin heavily depends on the [configuration](https://github.com/sebschlicht/plg_system_rimages/wiki/Configuration) of breakpoint packages and breakpoints, see examples below.

You configure breakpoint packages with a CSS selector to tell the plugin which images to compress and/or resize.
Breakpoints in the package specify which image dimension is desired for which device size.

You can configure packages and breakpoints globally or for content (e.g. articles) only.
Having no breakpoints in a package effectively disables the resize feature for this package.
Having no breakpoint packages effectively disables the plugin.

## Examples

### Compress all, resize non

Having a breakpoint package with the selector `img` tells the plugin to compress all images on your page.

![Universal breakpoint package configuration screenshot](https://github.com/sebschlicht/plg_system_rimages/blob/master/images/screen_rimages_img.png)

This step will most likely reduce your image transfer size by 20 to 50 percent.

### Resize slider images

Consider you've build a website with *Bootstrap* that features a full-width slider (`<div id="slider" />`).
You used large images to maintain a high quality on large screens but the slider plugin uses these for small devices, too.
Particularly mobile devices would benefit from resized images that are just large enough to fill their viewport width.

To make this slider use resized images, simply set the CSS selector of a breakpoint package to `#slider img`, configure breakpoints for smaller devices using the *Bootstrap* device sizes and set the respective max-widths (e.g. XS = 767px) as image width.

![Slider breakpoint package configuration screenshot](https://github.com/sebschlicht/plg_system_rimages/blob/master/images/screen_rimages_slider.png)

Each breakpoint will lead to a resized version of the slider images and devices can select the smallest version that fills their viewport width.
If image generation is enabled (default setting), these resized and compressed versions will be generated automatically.

You could add another breakpoint at 360px to offer tiny versions to smartphones and similar devices.

## Installation

Download the latest extension package and use the *Joomla!* extension manager to install it.
The plugin is compatible with *Joomla!* 3.x.

Use the *Joomla!* extension manager to uninstall the plugin and remove the configured image folder if you no longer need the responsive images.

## Compatibility

*rim* is fully compatible with all browsers and with caching.
Please consider pitfalls for CSS directives and JavaScript code at the wiki page on [compatibility](https://github.com/sebschlicht/plg_system_rimages/wiki/Compatibility).

*ImageMagick* is required on your server to generate compressed and/or resized images automatically.
