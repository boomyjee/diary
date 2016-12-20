(function() {
  /* global flowplayer */

  var $ = window.jQuery;

  flowplayer.overlay.fancybox = function(api, root) {
    var conf = api.conf.overlay
      , trigger = conf.trigger
      , closeBtn = conf.closeBtn !== false
      , id = $(root).attr('id')
      , ctrlHeight = $(root).hasClass('fixed-controls')
                       ? $('.fp-controls', root).height()
                       : $(root).hasClass('no-toggle') ? 0 : 4;


    if (!id) {
      id = 'flowplayer-' + Math.random().toString().slice(2);
      $(root).attr({id: id});
    }
    $(root).css({marginBottom: ctrlHeight}).toggleClass('is-closeable', !closeBtn);


    $(trigger).fancybox({
      href: '#' + id,
      wrapCSS: 'fancybox-flowplayer',
      type: 'inline',
      closeBtn: closeBtn,
      scrolling: 'no',
      live: false,
      beforeShow: function () {
        $(root).addClass('is-open');
        if (conf.maxWidth) {
          $(root).closest('.fancybox-flowplayer').css({maxWidth: conf.maxWidth});
        }
        api.load();
      },
      afterClose: function () {
        api.unload();
      }
    });

    api.on('unload', function () {
      $(root).removeClass('is-open');
      if ($(root).parent().hasClass('fancybox-inner')) {
        $.fancybox.close();
      }
    });

  };
})();
