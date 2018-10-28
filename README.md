# RIMAGES - Responsive Images Plugin for Joomla! 3

*RIMAGES* is a *Joomla!* plugin to make the images on your website responsive.

*RIMAGES* can compress your images and show resized images on smaller devices.
CSS selectors allow to precisely control which images should be made responsive.

Using *RIMAGES* helps to significantly decrease the page loading time and increase the Google *PageSpeed* rating, which contributes to the Google search ranking of your website.
Thus *RIMAGES* is must-have *Search Engine Optimization* (SEO) tool.

**Features**:

* automatically compress/resize images
* use CSS selectors to specify target dimensions for your images
* offer resized image versions to browsers on smaller devices
* compress images according to the [Google recommendation for image optimization](https://developers.google.com/speed/docs/insights/OptimizeImages)
* increases Google *PageSpeed* rating which may improve the Google search ranking
* supports external images (URLs)

## Usage

The usage of the plugin heavily depends on the configuration of [breakpoint packages](#breakpoint-packages) and [breakpoints](#breakpoints).

You configure breakpoint packages to tell the plugin, via a CSS selector, which images to compress and/or resize.
Breakpoints in the package tell the plugin which image dimension is desired for which device.

You can configure packages and breakpoints globally or for content (e.g. articles) only.

## Examples

### Compress all, resize non

Having a breakpoint package with the selector `img` tells the plugin to compress all images on your page.

![Universal breakpoint package configuration screenshot](https://github.com/sebschlicht/plg_system_rimages/blob/master/images/screen_rimages_img.png)

This step will most likely reduce your image transfer size by 20 to 50 percent.

### Resize slider images

Consider you've build a website with *Bootstrap* that features a full-width slider (`<div id="slider" />`).
You've selected large images for your slides to maintain a high quality on large screens but the slider plugin uses these for small devices, too.
Particularly mobile devices would benefit from resized images that are just large enough to fill their viewport width.

To make this slider use resized images, simply set the CSS selector of a breakpoint package to `#slider img`.

Then configure breakpoints for smaller devices using the *Bootstrap* device sizes and set the respective max-widths (e.g. XS = 767px) as image width.

![Slider breakpoint package configuration screenshot](https://github.com/sebschlicht/plg_system_rimages/blob/master/images/screen_rimages_slider.png)

Each breakpoint will lead to a resized version of the slider images and devices can select the smallest version that fills their viewport width.
If enabled, these resized (and compressed) versions will be generated automatically.

You might want to add a fourth breakpoint at 360px to offer even smaller images to smartphones and similar devices.

## Installation

Download the latest *RIMAGES* extension package and use the *Joomla!* extension manager to install it.
The plugin is compatible with *Joomla!* 3.x.

Use the *Joomla!* extension manager to uninstall the plugin and remove the configured image folder if you no longer need the responsive images.

## Technologies and Compatibility

*RIMAGES* works on two different levels: Compressing and resizing.

### Compressing

*RIMAGES* can replace images with compressed versions which is fully compatible with all systems and browsers.

The automatic generation of such files depends on *ImageMagick*.
If *ImageMagick* isn't installed and enabled on your server you can still create them by hand.

### Resizing

*RIMAGES* can offer alternative, resized versions of the original image to browsers on smaller devices.
This is based on the HTML5 [`picture` tag](https://www.w3schools.com/tags/tag_picture.asp) which is [supported by the vast majority](https://caniuse.com/#feat=picture) of users.
The tag is fully backwards-compatible, users with old browsers just won't benefit from resizing.

Due to the additional `picture` tag in the DOM tree, this feature could nevertheless break JavaScript code (e.g. `$slide.children( 'img' )`) and CSS directives (e.g. `.slide > img`).
However, you can easily prevent that by using appropriate CSS selectors when configuring the plugin.
