$(function() {
    function bindPlugins(elem) {
        elem.find('.entry-form .media-prev').sortable({
            placeholder: "preview-placeholder",
            stop: function(event, ui) {
                var mediaPrevEl = $(ui.item).parent();
                var form = mediaPrevEl.parents('.entry-form');
                collage(
                    mediaPrevEl, 
                    form.find('input[name=first_row_height_percent]').val(), 
                    form.find('input[name=secondary_rows_height_percent]').val(),
                    form.find('input[name=visible_row_count]').val()
                );
            }
        });
        
        elem.find('.entry-form .without-prev').sortable();
        
        elem.find('.attachment-input').each(function() {
            $(this).fileupload({
                url: base_url + 'attachments/upload',
                dropZone: $(this).parents('form'),
                add: function (e, data) {
                    var attachmentBlock = $('<div>', {'class': 'item working'}).append(
                        $('<div>', {'class': 'progress'}).append(
                            $('<div>', {'class': 'progress-bar progress-bar-striped active', 'role': 'progressbar', 'style': 'width:0%'})
                        ),
                        $('<p>').text(data.files[0].name),
                        $('<span>')
                    );

                    data.context = attachmentBlock.appendTo($(this).parents('.entry-form').find('.upload-statuses'));
                    attachmentBlock.find('span').click(function() {
                        if (attachmentBlock.hasClass('working')) {
                            jqXHR.abort();
                        }
                        attachmentBlock.fadeOut(function() {
                            attachmentBlock.remove();
                        });
                    });

                    var jqXHR = data.submit().success(function(result, textStatus, jqXHR) {
                        result = JSON.parse(result);
                        if (result.status != 'success') {
                            data.context.addClass('error');
                        } else {
                            if (result.preview_url) {
                                var preview = $('<img>', {'src': result.preview_url});
                                $('<img/>').load(function() {
                                    attachmentBlock.empty().append($('<input>', {'type': 'hidden', 'name': 'attachments[]', 'value': result.filename})).css({'visibility': 'hidden'});
                                    var mediaPreviewsBlock = attachmentBlock.parents('.entry-form').find('.media-prev');
                                    attachmentBlock.append(preview, $('<span>', {'class': 'remove'}));
                                    attachmentBlock.appendTo(mediaPreviewsBlock);

                                    var form = mediaPreviewsBlock.parents('.entry-form').addClass('media-added');
                                    collage(
                                        mediaPreviewsBlock, 
                                        form.find('input[name=first_row_height_percent]').val(), 
                                        form.find('input[name=secondary_rows_height_percent]').val(),
                                        form.find('input[name=visible_row_count]').val()
                                    );
                                }).attr('src', preview.attr('src'));
                            } else {
                                attachmentBlock.empty().append(
                                    $('<input>', {'type': 'hidden', 'name': 'attachments[]', 'value': result.filename}),
                                    $('<p>').text(result.original_filename),
                                    $('<span>', {'class': 'remove'})
                                );
                                attachmentBlock.appendTo(attachmentBlock.parents('.entry-form').find('.without-prev'));
                            }
                        }
                    });
                },
                done: function(e, data) {
                    data.context.removeClass('working');
                },
                progress: function(e, data){
                    var progress = parseInt(data.loaded / data.total * 100, 10);
                    data.context.find('.progress-bar').css('width', progress + '%');
                },
                fail: function (e, data) {
                    data.context.addClass('error');
                }
            });
        });
        
        elem.find('.fancybox').fancybox({
            afterLoad: function() {
                this.title += '<span class="img-number">Картинка ' + (this.index + 1) + ' из ' + this.group.length + '</span><br>' + '<a class="open-original" href="'+this.element.attr('data-original-url')+'" target="_blank">Открыть оригинал</a>';
            }
        });
        
        elem.find('.player').each(function() {
            $(this).flowplayer({
                tooltip: false,
                splash: true,
                embed: false,
                live: false,
                hlsjs: true,
                overlay: {
                    vendor: "fancybox",
                    trigger: '#' + $(this).attr('data-trigger')
                },
                clip: {
                    sources: [{
                        type: "application/x-mpegurl",
                        src: $(this).attr('data-url')
                    }]
                }
            });
        });
        
        elem.find('.attachments .media-prev').each(function() {
            collage(
                $(this),
                $(this).attr('data-first-row-height-percent'), 
                $(this).attr('data-secondary-rows-height-percent'), 
                $(this).attr('data-visible-row-count')
            );
        });
        
        elem.find('.media-prev').each(function() {
            if ($(this).find('*').length)
                $(this).parents('form').addClass('media-added');
        });
        
        elem.find('.media-prev, .without-prev, .upload-statuses').removeWhitespace();
        
        autosize(elem.find('.entry-form textarea'));
    };
    
    function collage(elem, firstRowHeightPercent, secondaryRowsHeightPercent, visibleRowCount) {
        var container = $(elem);
        var imgs = $(elem).find('img');
        var imgCount = imgs.length;
        var counter = 0;

        imgs.each(function(i) {
            var img	= $(this);
            $('<img/>').load(function() {
                if(++counter === imgCount) {
                    container.collagePlusPlus({
                        'firstRowTargetHeight': firstRowHeightPercent * container.width() / 100,
                        'secondaryRowsTargetHeight': secondaryRowsHeightPercent * container.width() / 100,
                        'visibleRowCount': visibleRowCount,
                        'allowPartialLastRow' : true
                    });
                }
            }).attr('src', img.attr('src'));
        });	
    }
    
    bindPlugins($('.entry-list'));
    
    var resizeTimer = null;
    $(window).bind('resize', function() {
        if (resizeTimer) clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            $('.media-prev:visible').each(function() {
                var firstRowHeightPercent = $(this).attr('data-first-row-height-percent');
                var secondaryRowsHeightPercent = $(this).attr('data-secondary-rows-height-percent');
                var visibleRowCount = $(this).siblings('.show-all-attachments').is(':not(.shown)') ? $(this).attr('data-visible-row-count') : false;
                
                if ($(this).parents('.entry-form').length) {
                    var form = $(this).parents('.entry-form');
                    firstRowHeightPercent = form.find('input[name=first_row_height_percent]').val();
                    secondaryRowsHeightPercent = form.find('input[name=secondary_rows_height_percent]').val();
                    visibleRowCount = form.find('input[name=visible_row_count]').val();
                }
                collage($(this), firstRowHeightPercent, secondaryRowsHeightPercent, visibleRowCount);
            });
        }, 50);
    });

    $(document).on('click', '.entry-form .add-attachment', function(e) {
        e.preventDefault();
        $(this).parents('.entry-form').find('input[type=file]').click();
    });

    $(document).on('click', '.entry-form .upload-statuses .remove, .entry-form .without-prev .remove', function(e) {
        e.preventDefault();
        $(this).parent().remove();
    });
    
    $(document).on('click', '.entry-form .media-prev .remove', function(e) {
        e.preventDefault();
        var form = $(this).parents('.entry-form');       
        $(this).parent().remove();
        collage(
            form.find('.media-prev'), 
            form.find('input[name=first_row_height_percent]').val(), 
            form.find('input[name=secondary_rows_height_percent]').val(),
            form.find('input[name=visible_row_count]').val()
        );
        if (!form.find('.media-prev *').length)
            form.removeClass('media-added');
    });

    $(document).on('click', '.entry .actions .edit', function(e) {
        e.preventDefault();
        $('.entry').removeClass('editing');
        $(this).parents('.entry').addClass('editing');
        var form =$(this).parents('.entry').find('.entry-form');
        collage(
            form.find('.media-prev'), 
            form.find('input[name=first_row_height_percent]').val(), 
            form.find('input[name=secondary_rows_height_percent]').val(),
            form.find('input[name=visible_row_count]').val()
        );
        autosize.update(form.find('textarea'));
    });

    $(document).on('click', '.entry .cancel', function(e) {
        e.preventDefault();
        var entryEl = $(this).parents('.entry').removeClass('editing');
        var mediaPrevEl = entryEl.find('.attachments .media-prev');
        collage(
            mediaPrevEl, 
            mediaPrevEl.attr('data-first-row-height-percent'), 
            mediaPrevEl.attr('data-secondary-rows-height-percent'),
            mediaPrevEl.siblings('.show-all-attachments').is(':not(.shown)') ? mediaPrevEl.attr('data-visible-row-count') : false
        );
    });
    
    $(document).on('click', '.entry .actions .remove', function(e) {
        e.preventDefault();

        var entryEl = $(this).parents('.entry');
        var entryId = entryEl.find('input[name=entry_id]').val();
        entryEl.remove();
        $.post(location.href, {action: 'delete_entry', entry_id: entryId});
        $.get(base_url + 'sync-entries');
    });
    
    $(document).on('submit', '.entry-form', function(e) {
        e.preventDefault();
        if (!$(this).find('textarea').val() && !$(this).find('.media-prev div').length && !$(this).find('.without-prev div').length || $(this).find('.upload-statuses div').length) {
            return false;
        }
       
        var form = $(this);
        var entryId = form.find('input[name=entry_id]').val();
        var postData = $(this).serializeArray();
        form.parent().addClass('loading');
        postData.push({name: 'action', value: 'edit_entry'});
        $.post(location.href, postData, function(html) {
            form.parent().removeClass('loading');
            if (!html) return;
            if (entryId) {
                $('.entry[data-entry-id='+entryId+']').replaceWith($(html).find('.entry[data-entry-id='+entryId+']'));
                bindPlugins($('.entry[data-entry-id='+entryId+']'));
            } else {
                $('.entry-list').replaceWith($(html).find('.entry-list'));
                bindPlugins($('.entry-list'));
            }
            
            $.get(base_url + 'sync-entries');
        });
    });
        
    var searchTimeout;
    $(document).on('input propertychange', '#search input', function() {
        var searchCriteria = $(this).val();
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() {
            $.get(location.pathname, {search: searchCriteria}, (function(requestSearchTimeout) {
                return function(html) {           
                    if (requestSearchTimeout != searchTimeout) return;

                    $('.entry-list').replaceWith($(html).find('.entry-list'));
                    bindPlugins($('.entry-list'));
                }
            })(searchTimeout));
        }, 350);
    });
    
    $('body').infinitescroll({
        loading: {
            finishedMsg: "",
            msg: $('<span>', {'class': 'page-loading'}),
            msgText: "",
            selector: ".entry-list",
            finished: null
        },
        nextSelector: ".load-more a",
        navSelector: ".load-more",
        itemSelector : ".entry",
        appendCallback: false,
        errorCallback: function () { 
            $("span.page-loading").remove();
        }
    }, function(newEntries, opts) {
            if (newEntries.length) {
                $(newEntries).insertBefore($('.load-more'));
                bindPlugins($(newEntries));
            }
    }); 
    
    $(document).on('click', '.hash-tag', function(e) {
        e.preventDefault();
        $('#search input').val($(this).text()).trigger('input');
    });
    
    $(document).on('click', '.show-all-attachments a', function(e) {
        e.preventDefault();
        $(this).parent().toggleClass('shown');
        var entryEl = $(this).parents('.entry');
        var mediaPrevEl = entryEl.find('.attachments .media-prev');
        collage(
            mediaPrevEl, 
            mediaPrevEl.attr('data-first-row-height-percent'), 
            mediaPrevEl.attr('data-secondary-rows-height-percent'),
            $(this).is('.show-all') ? false : mediaPrevEl.attr('data-visible-row-count')
        );
    });
    
    $(document).on('focus', '.add-entry textarea', function() {
        $(this).parents('.add-entry').addClass('full');
    });
    
    $(document).on('click', '.add-entry', function(e) {
        e.stopPropagation();
    });
    
    $(document).on('click', function() {
        if (!$('.add-entry textarea').val() && !$('.add-entry .media-prev div, .add-entry .upload-statuses div, .add-entry .without-prev div').length) {
            $('.add-entry').removeClass('full');
        }
    });
    
    var resizeTimeout;
    $(document).on('input', '.range-control', function(e) {
        e.preventDefault();
        var form = $(this).parents('.entry-form');
        clearTimeout(resizeTimeout);
        
        resizeTimeout = setTimeout(function() {
            collage(
                form.find('.media-prev'), 
                form.find('input[name=first_row_height_percent]').val(), 
                form.find('input[name=secondary_rows_height_percent]').val(),
                form.find('input[name=visible_row_count]').val()
            );
        }, 10);
    });
});