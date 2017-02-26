;(function( $ ) {

    $.fn.collagePlusPlus = function( options ) {

        return this.each(function() {

            /*
             *
             * set up vars
             *
             */

            // track row width by adding images, padding and css borders etc
            var row = 0,
            // collect elements to be re-sized in current row
                elements = [],
            // track the number of rows generated
                rownum = 1,
            // needed for creating some additional defaults that are actually obtained
            // from the dom, which maybe doesn't make them defaults ?!
                $this = $(this),
                rowOffsetTop = 0;


            // width of the area the collage will be in
            $.fn.collagePlusPlus.defaults.albumWidth    = $this.width();
            // padding between the images. Using padding left as we assume padding is even all the way round
            $.fn.collagePlusPlus.defaults.padding       = parseFloat( $this.css('padding-left') );
            // object that contains the images to collage
            $.fn.collagePlusPlus.defaults.images        = $this.children();

            var settings = $.extend({}, $.fn.collagePlusPlus.defaults, options);
            
            settings.images.each(
                function(index){                  
                    
                    /*
                     *
                     * Cache selector
                     * Even if first child is not an image the whole sizing is based on images
                     * so where we take measurements, we take them on the images
                     *
                     */
                    var $this = $(this),
                        $img  = ($this.is("img")) ? $this : $(this).find("img");



                    /*
                     *
                     * get the current image size. Get image size in this order
                     *
                     * 1. from <img> tag
                     * 2. from data set from initial calculation
                     * 3. after loading the image and checking it's actual size
                     *
                     */
                    var w = (typeof $img.data("width") != 'undefined') ? $img.data("width") : $img.width(),
                        h = (typeof $img.data("height") != 'undefined') ? $img.data("height") : $img.height();



                    /*
                     *
                     * Get any current additional properties that may affect the width or height
                     * like css borders for example
                     *
                     */
                    var imgParams = getImgProperty($img);


                    /*
                     *
                     * store the original size for resize events
                     *
                     */
                    $img.data("width", w);
                    $img.data("height", h);



                    /*
                     *
                     * calculate the w/h based on target height
                     * this is our ideal size, but later we'll resize to make it fit
                     *
                     */
                    var targetHeight = settings.secondaryRowsTargetHeight;
                    if (settings.firstRowTargetHeight && rownum == 1) {
                        targetHeight = settings.firstRowTargetHeight;
                    }
                    
                    var nw = Math.ceil(w/h*targetHeight),
                        nh = Math.ceil(targetHeight);

                    /*
                     *
                     * Keep track of which images are in our row so far
                     *
                     */
                    elements.push([this, nw, nh, imgParams['w'], imgParams['h']]);

                    /*
                     *
                     * calculate the width of the element including extra properties
                     * like css borders
                     *
                     */
                    row += nw + imgParams['w'] + settings.padding;
                    
                    /*
                     *
                     * if the current row width is wider than the parent container
                     * it's time to make a row out of our images
                     *
                     */
                    if ( (row > settings.albumWidth || settings.images.length-1 == index) && elements.length != 0 ){

                        // call the method that calculates the final image sizes
                        resizeRow(elements, row, settings, rownum, rowOffsetTop);

                        // reset our row
                        delete row;
                        delete elements;
                        row         = 0;
                        elements    = [];
                        rownum      += 1;
                        rowOffsetTop += $this.is(':visible') ? ($this.height() + settings.padding) : 0;
                        $this.parent().height(rowOffsetTop - settings.padding);
                    }
                    
                    if (index == settings.images.length - 1)
                        $this.parent().addClass('inited');
                }
            );
        });

        function resizeRow(obj, row, settings, rownum, rowOffsetTop) {

            /*
             *
             * How much bigger is this row than the available space?
             * At this point we have adjusted the images height to fit our target height
             * so the image size will already be different from the original.
             * The resizing we're doing here is to adjust it to the album width.
             *
             * We also need to change the album width (basically available space) by
             * the amount of padding and css borders for the images otherwise
             * this will skew the result.
             *
             * This is because padding and borders remain at a fixed size and we only
             * need to scale the images.
             *
             */
            var imageExtras         = (settings.padding * (obj.length - 2)) + (obj.length * obj[0][3]),
                albumWidthAdjusted  = settings.albumWidth - imageExtras,
                overPercent         = albumWidthAdjusted / (row - imageExtras),
                // start tracking our width with know values that will make up the total width
                // like borders and padding
                trackWidth          = imageExtras,
                // guess whether this is the last row in a set by checking if the width is less
                // than the parent width.
                lastRow             = (row < settings.albumWidth  ? true : false),
                imageOffsetLeft     = 0;

            /*
             * Resize the images by the above % so that they'll fit in the album space
             */
            for (var i = 0; i < obj.length; i++) {



                var $obj        = $(obj[i][0]),
                    fw          = Math.floor(obj[i][1] * overPercent),
                    fh          = Math.floor(obj[i][2] * overPercent),
                    isNotLast   = !!(( i < obj.length - 1 ));
                
                if (settings.visibleRowCount && rownum > settings.visibleRowCount) {
                    $obj.parent().addClass('limited');
                    $obj.addClass('hidden');
                } else {
                    $obj.parent().removeClass('limited');
                    $obj.removeClass('hidden');
                }

                /*
                 * Checking if the user wants to not stretch the images of the last row to fit the
                 * parent element size
                 */
                if(settings.allowPartialLastRow === true && lastRow === true){
                   fw = obj[i][1];
                   fh = obj[i][2];
                }

                /*
                 *
                 * Because we use % to calculate the widths, it's possible that they are
                 * a few pixels out in which case we need to track this and adjust the
                 * last image accordingly
                 *
                 */
                trackWidth += fw;


                /*
                 *
                 * here we check if the combined images are exactly the width
                 * of the parent. If not then we add a few pixels on to make
                 * up the difference.
                 *
                 * This will alter the aspect ratio of the image slightly, but
                 * by a noticable amount.
                 *
                 * If the user doesn't want full width last row, we check for that here
                 *
                 */
                if(!isNotLast && trackWidth < settings.albumWidth){
                    if(settings.allowPartialLastRow === true && lastRow === true){
                        fw = fw;
                    }else{
                        fw = fw + (settings.albumWidth - trackWidth);
                    }
                }

                /*
                 *
                 * We'll be doing a few things to the image so here we cache the image selector
                 *
                 *
                 */
                var $img = ( $obj.is("img") ) ? $obj : $obj.find("img");

                /*
                 *
                 * Set the width of the image and parent element
                 * if the resized element is not an image, we apply it to the child image also
                 *
                 * We need to check if it's an image as the css borders are only measured on
                 * images. If the parent is a div, we need make the contained image smaller
                 * to accommodate the css image borders.
                 *
                 */
                $img.width(fw);
                if( !$obj.is("img") ){
                    $obj.width(fw + obj[i][3]);
                }


                /*
                 *
                 * Set the height of the image
                 * if the resized element is not an image, we apply it to the child image also
                 *
                 */
                $img.height(fh);
                if( !$obj.is("img") ){
                    $obj.height(fh + obj[i][4]);
                }


                /*
                 *
                 * Set image position
                 *
                 */
                $obj.css({'top': rowOffsetTop, 'left': imageOffsetLeft, 'visibility': 'visible'});
                imageOffsetLeft += $obj.is(':visible') ? ($obj.width() + settings.padding) : 0;
            }
        }


        /*
         *
         * This private function calculates any extras like padding, border associated
         * with the image that will impact on the width calculations
         *
         */
        function getImgProperty( img )
        {
            $img = $(img);
            var params =  new Array();
            params["w"] = (parseFloat($img.css("border-left-width")) + parseFloat($img.css("border-right-width")));
            params["h"] = (parseFloat($img.css("border-top-width")) + parseFloat($img.css("border-bottom-width")));
            return params;
        }

    };

    $.fn.collagePlusPlus.defaults = {
        // the ideal height of images in first row
        'firstRowTargetHeight': 400,
        // the ideal height you want your secondary images to be
        'secondaryRowsTargetHeight': 400,
        // Sometimes there is just one image on the last row and it gets blown up to a huge size to fit the
        // parent div width. To stop this behaviour, set this to true
        'allowPartialLastRow': false,
        // Set for visible images limitation
        'visibleRowCount': false
    };

})( jQuery );
