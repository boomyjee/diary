<? include partial('layout') ?>

<? startblock('content') ?>
    <div class="container login">
        <div class="well auth-block">
            <form id="login_form" action="<?=url('login')?>" method="POST">
                <div class="form-group">
                    <? $login_form->findElement('login')->render(); ?>
                </div>
                <div class="form-group">
                    <? $login_form->findElement('password')->render(); ?>
                </div>

                <div class="form-group text-center">
                    <div class="inline">
                        <div class="checkbox">
                            <? $login_form->findElement('remember_me')->render(); ?>
                        </div>
                    </div>
                    <button class="btn btn-primary btn-login pull-right">Войти</button>
                </div>
            </form>
        </div>
    </div>
<? endblock() ?>