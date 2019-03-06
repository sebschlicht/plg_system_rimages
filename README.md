# RIMAGES - Responsive Images Plugin for Joomla! 3

RIMAGES (*rimg*) is a *Joomla!* plugin to make the images on your website responsive.

*rimg* can compress your images and display resized (i.e. smaller) images on smaller devices.
Both compressed and resized images are generated automatically.
Use CSS selectors to limit this process to specific images.

Using *rimg* helps to significantly decrease the page loading time and increase the Google *PageSpeed* rating, which contributes to the Google search ranking of your website.
Thus *rimg* is a great addition to your Search Engine Optimization (SEO) toolbox.

**Features**:

* display smaller images on smaller devices
* automatically compress/resize images according to the [Google recommendation for image optimization](https://developers.google.com/speed/docs/insights/OptimizeImages)
* limit image processing with CSS selectors
* increase Google *PageSpeed* and improve Google search ranking in return
* supports external images (from URL)
* languages: English, German

## Usage

The usage of the plugin heavily depends on the [configuration](https://github.com/sebschlicht/plg_system_rimages/wiki/Configuration) of breakpoint packages and breakpoints, see examples below.

You configure breakpoint packages with a CSS selector to tell the plugin which images to compress and/or resize.
Breakpoints in the package specify which image dimension is desired for which device size.

## Examples

### Compress all, resize non

Having a breakpoint package with the selector `img` tells the plugin to compress all images on your page.

![Universal breakpoint package configuration screenshot](https://github.com/sebschlicht/plg_system_rimages/blob/master/images/screen_rimages_img.png)

This step will most likely reduce your image transfer size by 20 to 50 percent.

### Resize slider images

Consider you've build a website with *Bootstrap* that features a full-width slider (`<div id="slider" />`).
You've used large images to maintain a high quality on large screens but the slider plugin uses these for small devices, as well.
Particularly mobile devices would benefit from resized images that are just large enough to fill their viewport width.

To make this slider use resized images, simply set the CSS selector of a breakpoint package to `#slider img`, configure breakpoints for smaller devices (e.g. using the *Bootstrap* device sizes) and set the respective max-widths (e.g. XS = 767px) as image width.

![Slider breakpoint package configuration screenshot](https://github.com/sebschlicht/plg_system_rimages/blob/master/images/screen_rimages_slider.png)

Each breakpoint will lead to a resized version of the slider images and devices can select the smallest version that fills their viewport width.
If image generation is enabled (default setting), these resized and compressed versions will be generated automatically.

Now you could add another breakpoint at 360px to offer tiny versions to older smartphones and similar devices, for example.

## Installation

Download the latest extension package and use the *Joomla!* extension manager to install it.
The plugin is compatible with *Joomla!* 3.x.

Use the *Joomla!* extension manager to uninstall the plugin and remove the configured image folder if you no longer need the responsive images.

## Compatibility

*rimg* is compatible with modern browsers and with caching.
Incompatible browsers will neither benefit nor encounter issues.
Please consider pitfalls for CSS directives and JavaScript code at the wiki page on [compatibility](https://github.com/sebschlicht/plg_system_rimages/wiki/Compatibility).

*ImageMagick* is required on your server to generate compressed and/or resized images automatically.
