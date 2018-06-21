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
Each breakpoint corresponds to a resized image version that is displayed on devices with a certain viewport width.
Having no breakpoints configured effectively disables the resizing feature.

Breakpoint packages consist of any number of breakpoints and feature a CSS selector that controls which images its breakpoints apply to.

### Example 1

To replace all images on your website with compressed versions, set the CSS selector of a breakpoint package to

    img

This simple step will most likely reduce your image transfer size by 20 to 50 percent.

>Please note that the [configuration options](#options) have to be set properly, to make this example work.
>The default settings are just fine.

### Example 2

Consider you've build a website with *Bootstrap* that features a full-width slider (`<div id="slider" />`).
You've selected large images for your slides to maintain a high quality on large screens.
On the downside, these large images are downloaded and downscaled on smaller screens.
Particularly mobile devices would benefit from resized images that are just large enough to fill their viewport width.

To make this slider use resized images, set the CSS selector of a breakpoint package to

    #slider img

Then configure 3 breakpoints for the smaller *Bootstrap* device sizes (extra small, small, medium) and use the respective max-width (e.g. XS = 767px) as image width:

    viewportWidth:XS imageWidth:767

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
This is based on the HTML5 [`picture` tag](https://www.w3schools.com/tags/tag_picture.asp) which is [supported by the vast majority](https://caniuse.com/#feat=picture).
The tag is fully backwards-compatible, users with old browsers just won't benefit from resizing.

Due to the additional `picture` tag in the DOM tree, this feature could nevertheless break JavaScript code (e.g. `$slide.children( 'img' )`) and CSS directives (e.g. `.slide > img`).
However, you can easily prevent that by using appropriate CSS selectors when configuring the plugin.

## Configuration

The plugin configuration is split up into three parts:

1. basic configuration options, shown in the plugin tab
2. breakpoint packages that apply to all images, shown in the global tab
3. breakpoint packages that apply to content (e.g. articles), shown in the content tab

### Options

Option | Description
------ | -----------
`Folder` | Folder to store and lookup responsive image versions.
`Replace Original Image` | Flag to replace original images with compressed versions on-page when processing them.
`Generate Images` | Flag to generate missing compressed and resized versions of processed images.
`Download Images` | Flag to download and process images when they're originally hosted on a remote server.

### Breakpoints

Breakpoints steer the resizing feature and mainly specify widths of alternative image versions.
Each breakpoint corresponds to a resized image version that is displayed on devices with a certain viewport width.
Having no breakpoints configured effectively disables the resizing feature.

A breakpoint has three values, where one may be omitted:

The viewport width specifies the maximum width of a viewport that may used this resized version of an image.
You may choose to select this width from a list of pre-defined values, such as *Bootstrap* device widths, or enter a custom value.

The image width specifies the maximum width of the resized version of an image.
This is required to generate the resized version automatically.
If the original image doesn't exceed this width it's not resized at all and hence won't waste disk storage and bandwidth.

### Breakpoint Packages

Breakpoint packages consist of any number of breakpoints and feature a CSS selector that controls which images its breakpoints apply to.
Having no breakpoint packages configured effectively disables the plugin.

Content packages are processed before global packages.
Besides, breakpoint packages are processed in the same order as they're shown.
Always remember: Once an image was handled by a package, it's not processed a second time.
