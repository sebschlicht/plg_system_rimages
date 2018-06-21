# RIMAGES - Responsive Images Plugin for Joomla! 3

*RIMAGES* is a *Joomla!* plugin to make the images on your website responsive.
*RIMAGES* can compress your images and show resized images on smaller devices.
CSS selectors allow to precisely control which images should be made responsive.

Using *RIMAGES* helps to significantly decrease the page loading time and increase the Google *PageSpeed* rating, which contributes to the Google search ranking of your website.
Thus *RIMAGES* is must-have *Search Engine Optimization* (SEO) tool.

**Features**:

* compress images according to the [Google recommendation for image optimization](https://developers.google.com/speed/docs/insights/OptimizeImages)
* offer resized image versions to browsers on smaller devices
* automatically compress/resize images
* increase Google *PageSpeed* rating => improve Google search ranking
* works with external images (URLs)

## Usage

The usage of the plugin heavily depends on the configuration of [breakpoint packages](#breakpoint-packages) and [breakpoints](#breakpoints).

Breakpoints steer the resizing feature and mainly specify widths of alternative image versions.
Having no breakpoints configured effectively disables the resizing feature.

Breakpoint packages consist of any number of breakpoints and feature a CSS selector that controls which images the breakpoints apply to.

*Example 1*: To replace all images on your website with compressed versions, set the CSS selector of a breakpoint package to

    img

Please note that the [configuration options](#options) have to be set properly, to make this example work.
The default settings are just fine.

*Example 2*: To make a full-width slider use resized images on a website build with *Bootstrap* that fit the user device best, you might need to set the CSS selector of a breakpoint package to,

    #slider img

configure 3 breakpoints for the smaller *Bootstrap* device sizes (extra small, small, medium) and use the respective max-widths (e.g. XS = 767px) as image widths.

Now each device can show the compressed and resized image version that fits its viewport width best.

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
This is based on the HTML5 [`picture` tag](https://www.w3schools.com/tags/tag_picture.asp) which is [supported by the vast majority](https://caniuse.com/#feat=picture).
The tag is fully backwards-compatible, users with old browsers just won't benefit from resizing.

Due to the additional `picture` tag in the DOM tree, this feature could nevertheless break JavaScript code (e.g. `$slide.children( 'img' )`) and CSS directives (e.g. `.slide > img`).
However, you can easily prevent that by using appropriate CSS selectors when configuring the plugin.
