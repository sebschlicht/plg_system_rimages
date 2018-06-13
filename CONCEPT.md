# Web Image Converter

`WIMG` is a Joomla! plugin to make the images on a website responsive.
It utilizes the `picture` tag which is supported by more than 90% of all tracked users.

## Problem Description

The bytes of a website largely consist of image data.
Reducing the data consumption regarding the transfer of image data would yield in a drastically increased page loading speed.
Often, dramatically smaller versions of an image may be used due to viewport restrictions or possible image conversions, as [suggested by Google](https://developers.google.com/speed/docs/insights/OptimizeImages).

Images on a website - built with *Joomla!* - are placed via various means.
Some images are placed directly by the user, such as in articles, where full control over the `img`-tag is given.
Images are also placed indirectly, such as via extensions, where regularly only the source file can be specified.

## Technologies

There are two possible ways to implement a solution:
A server-side solution based on PHP and a client-side solution based on JavaScript.

### PHP

A server-side solution based on PHP could be implemented in form of an extension.

+ supports caching
+ check for responsive image versions without additional data consumption for the client
+ possibly automate the generation of responsive image versions
- more costly to implement

### JavaScript

A client-side solution based on JavaScript could be implemented as part of the template or an extension.

+ easy to implement
+ easy to configure areas to include/exclude from processing (e.g. via classes)
- DOM-tree manipulation required on each request
- may fail to decrease data consumption if not quick enough to fetch and place alternative image versions
- may increase loading/rendering time while waiting for alternative image versions to load

### Conclusion

Though more costly to implement, a PHP-based solution offers far more benefits than a client-side JavaScript-based solution.
If the PHP-based solution should fail to process all images, e.g. due to the execution order, a JavaScript-based solution would be an option to really process all images.

## Responsiveness Techniques

There are multiple possible ways of making an image responsive:

* multiple `img` tags
* CSS `background-image` with media queries
* HTML5 `picture` tag
* JavaScript DOM-tree manipulation on `resize`

All these approaches share the necessity of having to pass some information:
For each alternative image version, we need a location and a breakpoint which specifies when to use this version.

**Example Setting**:

1. automatically placed image `gen-img.jpg` (e.g. by a slider module) in 2 alternative versions: xs and md
2. explicitly placed image `img.jpg` (e.g. in an article) with custom breakpoints: 320 px and xs

**Disclosure of Information**:

*Authorative Naming Scheme*:
Without explicit disclosures, this information would need to be guessed, e.g. based on a naming scheme:
Filenames would be prefixed with the original filename to identify alternative versions and include size information (`320` px, `xs`, etc.) to identify target breakpoints.
However, the process of [scanning for files in PHP](http://php.net/manual/de/function.scandir.php) may be costly.
Furthermore, making filenames the authorative source of this information prohibits to automate the generation of alternative image versions.

*Configuration Cascading*:
A more powerful would be to have global default settings and to allow more individual settings via the extension configuration and image attributes.
The global configuration could be to generate 4 versions for the different Bootstrap classes or to not generate alternative versions at all.
The extension would allow to configure target dimensions and breakpoints per module position and/or module.
However, this wouldn't cover images placed directly inside the template.
Articles and custom modules could specify the information directly on the image, via attributes (`src-xs`, `src-320`).

### Multiple Image Tags

**resulting HTML**:

    <img class="visible-xs-block visible-sm-block" src="gen-img_xs.jpg" />
    <img class="visible-md-block" src="gen-img_md.jpg" />
    <img class="visible-ld-block" src="gen-img.jpg" />

+ [Bootstrap](https://getbootstrap.com/docs/3.3/css/#helper-classes) not required, classes can be easily simulated
+ [fully compatible](https://caniuse.com/#feat=css-mediaqueries) with virtually all browsers
- some [browsers download hidden images](https://stackoverflow.com/questions/12158540/does-displaynone-prevent-an-image-from-loading)
- unclear whether to use smaller or larger version for breakpoints without an explicit image set

### CSS Background Image

In external CSS, all image URLs would have to be known in advance.
Inline CSS would work but all images would need `id` attributes in order to be identified and the CSS code would have to be generated.

    <html>
      <head>
        ...
        <style>
          @media-query (min-width: 640px) {
            #img-001 {
              background-image: url('img.jpg');
            }
          }
        </style>
        ...
      </head>
      <body>
        ...
        <img id="img-001" src="img.jpg" />
        ...
      </body>
    </html>

+ CSS3 is supported by [almost all browsers](https://caniuse.com/#feat=css-mediaqueries)
+ no JavaScript required
- images would need `id`s to be targeted by CSS directives
- generated inline CSS
- only generic breakpoints

## Approaches

In order to not have to change all images and extensions which place images, there are two options:

Either we process the DOM tree on client-side and try to fetch responsive image version via AJAX.
The downsides of the option are that we can not know whether smaller or larger versions are available until we have requested them and got a response header.
Furthermore, if there are responsive versions available, the browser most likely will have downloaded the wrong version in the meantime, resulting in an even larger data consumption.

The second option is to use server-side code, for example an extension, to replace all `img`-tags
