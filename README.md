# RIMAGES - Responsive Images Plugin for Joomla! 3

RIMAGES (*rimg*) is a *Joomla!* plugin to make the images on your website responsive.

*rimg* can compress your images and display resized (i.e. smaller) images on smaller devices.
Both compressed and resized images are generated automatically.
Use CSS selectors to limit this process to specific images.

Using *rimg* helps to significantly decrease the page loading time and increase the Google *PageSpeed* rating, which contributes to the Google search ranking of your website.
Thus *rimg* is a great addition to your Search Engine Optimization (SEO) toolbox.

**Features**:

* display smaller or different images on smaller devices
* automatically compress and/or resize images according to the [Google recommendation for image optimization](https://developers.google.com/speed/docs/insights/OptimizeImages)
* limit image processing via CSS selectors
* increase Google *PageSpeed* and improve Google search ranking in return
* support for external images (from URL)
* languages: English, German

## Usage

The usage of the plugin heavily depends on the [configuration](https://github.com/sebschlicht/plg_system_rimages/wiki/Configuration) of breakpoint packages and breakpoints, see examples below.

You configure breakpoint packages with a CSS selector to tell the plugin which images to compress and/or resize.
Breakpoints in the package specify which image dimension is desired for which device size.

## Examples

You find [example configuration](https://github.com/sebschlicht/plg_system_rimages/wiki/examples) which cover the following use cases:

* compress all images (without any resizing) - see below
* resize article images to match the *Bootstrap 3* device classes
* having custom breakpoints (e.g. for smaller and tiny smartphones)
* resize images of a specific extension (e.g. a slider)
* provide different images on smaller devices - see below

### Compress all, resize non

Having a global breakpoint package with the selector `img` tells the plugin to compress all images on your page.

![Global breakpoint package](https://github.com/sebschlicht/plg_system_rimages/blob/master/images/rimages_cfg-ex_global.png)

This simple step will most likely reduce your image transfer size by 20 to 50 percent.

### Cropped version on small devices

In case one of your images has a clear focus on a particular object or subject, you may want to crop the image to the area of interest rather than downscaling the whole image.
You can simply provide cropped versions of a particular image for defined breakpoint by following the plugin's [naming convention](https://github.com/sebschlicht/plg_system_rimages/wiki/naming-convention) and still have smaller versions of other images generated automatically, if you want.

## Installation

Download the latest extension package and use the *Joomla!* extension manager to install it.
The plugin is compatible with *Joomla!* 3.x.

Use the *Joomla!* extension manager to uninstall the plugin and remove the configured image folder if you no longer need the responsive images.

## Compatibility

*rimg* is compatible with modern browsers and with caching.
Incompatible browsers will neither benefit nor encounter issues.
Please consider pitfalls for CSS directives and JavaScript code at the wiki page on [compatibility](https://github.com/sebschlicht/plg_system_rimages/wiki/Compatibility).

*ImageMagick* is required on your server to generate compressed and/or resized images automatically.
