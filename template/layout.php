<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Diary</title>
        
        <? minify('css','diary',
            'assets/lib/flowplayer/skin/skin.css',
            'assets/lib/fancybox/jquery.fancybox.css',
            'assets/lib/flowplayer/flowplayer.overlay.fancybox.css',
            'assets/css/style.css'
        ) ?>
        
        <script src="<?= url('assets/lib/jquery-ui/external/jquery/jquery.js') ?>"></script>
        <script src="<?= url('assets/lib/jquery-ui/jquery-ui.min.js') ?>"></script>
        <script src="<?= url('assets/lib/jquery-fileupload/jquery.ui.widget.js') ?>"></script>
        <script src="<?= url('assets/lib/jquery-fileupload/jquery.iframe-transport.js') ?>"></script>
        <script src="<?= url('assets/lib/jquery-fileupload/jquery.fileupload.js') ?>"></script>
        <script src="<?= url('assets/lib/remove-whitespace/jquery.removeWhitespace.min.js') ?>"></script>
        <script src="<?= url('assets/lib/fancybox/jquery.fancybox.pack.js') ?>"></script>
        
        <script src="<?= url('assets/lib/flowplayer/flowplayer.min.js') ?>"></script>
        <script src="<?= url('assets/lib/flowplayer/flowplayer.hlsjs.min.js') ?>"></script>
        <script src="<?= url('assets/lib/flowplayer/flowplayer.overlay.min.js') ?>"></script>
        <script src="<?= url('assets/lib/flowplayer/flowplayer.overlay.fancybox.js') ?>"></script>
        
        <script src="<?= url('assets/lib/autosize/autosize.js') ?>"></script>
        <script src="<?= url('assets/js/collagePlusPlus.js') ?>"></script>
        <script src="<?= url('assets/js/core.js') ?>"></script>
        <script>var base_url = "<?= url('') ?>";</script>
    </head>

    <body>
        <? emptyblock('content') ?>
    </body>
</html>