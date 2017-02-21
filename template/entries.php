<? include partial('layout') ?>

<? startblock('content') ?>
    <div id="header">
        <div class="container">
            <div id="search">
                <input type="text" name="search" class="form-control" value="<?= $search_criteria ?>" placeholder="Поиск" >
            </div>
            <? if ($user): ?>
                <div class="dropdown">
                    <span class="main-point" tabindex='1'><?= $user->login ?><span class="dropdown-arrow"></span></span>
                    <ul class="sub-menu">
                        <li><a href="<?= url('logout') ?>" class="logout">Выйти</a></li>
                    </ul>
                </div>
            <? endif ?>
        </div>
    </div>
    
    <div id="entries" class="container">
        <div class="list-wrapper">    
            <div class="entry-list">
                <div class="add-entry">
                    <form role="form" class="entry-form" method="post" action="" enctype="multipart/form-data">
                        <div class="form-group textfield">
                            <textarea name="text" class="form-control" placeholder="Добавить запись"></textarea>
                        </div>

                        <div class="controls">
                            <div class="prev-controls">
                                <div class="col"><input class="range-control" name="first_row_height_percent" type="range" min="10" max="100" step="1" value="40"> </div>
                                <div class="col"><input class="range-control" name="secondary_rows_height_percent" type="range" min="10" max="100" step="1" value="20"></div>
                                <div class="col"><input class="range-control" name="visible_row_count" type="range" min="1" max="10" step="1" value="2"></div>
                            </div>
                            <div class="media-prev"></div>
                            <div class="without-prev"></div>
                            <div class="upload-statuses"></div>
                            <div class="buttons">
                                <a href="#" class="add-attachment pull-left">(приложить файлы)</a>
                                <button type="submit" name="action" value="edit_entry" class="btn btn-primary pull-right add-new-entry">Отправить</button>
                            </div>
                            <input class="attachment-input" type="file" name="attachment" multiple />
                        </div>
                    </form>
                </div>

                <? if (count($entries)): ?>
                    <? foreach ($entries as $entry): ?>
                        <div class="entry" data-entry-id="<?= $entry->id ?>">
                            <form role="form" class="entry-form" method="post" action="" enctype="multipart/form-data">
                                <input type="hidden" name="entry_id" value="<?= $entry->id ?>">
                                <div class="form-group textfield">
                                    <textarea name="text" class="form-control" placeholder="Текст записи"><?= $entry->text ?></textarea>
                                </div>
                                <div class="controls">
                                    <div class="prev-controls">
                                        <div class="col"><input class="range-control" name="first_row_height_percent" type="range" min="10" max="100" step="1" value="<?= $entry->attachment_preview_settings['first_row_height_percent'] ?>"> </div>
                                        <div class="col"><input class="range-control" name="secondary_rows_height_percent" type="range" min="10" max="100" step="1" value="<?= $entry->attachment_preview_settings['secondary_rows_height_percent'] ?>"></div>
                                        <div class="col"><input class="range-control" name="visible_row_count" type="range" min="1" max="10" step="1" value="<?= $entry->attachment_preview_settings['visible_row_count'] ?>"></div>
                                    </div>
                                    <div class="media-prev">
                                        <? $withoutPreview = []; ?>
                                        <? foreach ($entry->attachments as $attachment): ?>
                                            <? $attachmentType = \App\Models\Entry::getAttachmentType($attachment); ?>
                                            <? if ($attachmentType == \App\Models\Entry::ATTACHMENT_TYPE_IMAGE): ?>
                                                <div class="item">
                                                    <img src="<?= url('entry_attachments/'.$entry->id.'/images_preview/'.rawurlencode($attachment)) ?>">
                                                    <input type="hidden" name="attachments[]" value="<?= $attachment ?>">
                                                    <span class="remove"></span>
                                                </div>
                                            <? elseif ($attachmentType == \App\Models\Entry::ATTACHMENT_TYPE_VIDEO): ?>
                                                <div class="item video">
                                                    <img src="<?= url('entry_attachments/'.$entry->id.'/video_thumbs/'.rawurlencode($attachment)) ?>">
                                                    <input type="hidden" name="attachments[]" value="<?= $attachment ?>">
                                                    <span class="remove"></span>
                                                </div>
                                            <? else: ?>
                                                <? $withoutPreview[] = $attachment; ?>
                                            <? endif ?>
                                        <? endforeach ?>
                                    </div>
                                    <div class="without-prev">
                                        <? foreach ($withoutPreview as $attachment): ?>
                                            <div class="item">
                                                <p><?= $attachment ?></p>
                                                <input type="hidden" name="attachments[]" value="<?= $attachment ?>">
                                                <span class="remove"></span>
                                            </div>
                                        <? endforeach ?>
                                    </div>
                                    <div class="upload-statuses"></div>
                                    <div class="buttons">
                                        <a href="#" class="add-attachment pull-left">(приложить файлы)</a>
                                        <button type="submit" name="action" value="edit_entry" class="btn btn-primary pull-right">Сохранить</button>
                                        <button class="btn btn-primary pull-right cancel">Отмена</button>
                                    </div>
                                    
                                    <input class="attachment-input" type="file" name="attachment" multiple />
                                </div>
                            </form>

                            <div class="entry-content">
                                <div class="entry-body">
                                    <div class="actions">
                                        <ul>
                                            <li class="edit">Редактировать запись</li>
                                            <li class="remove">Удалить запись</li>
                                        </ul>
                                    </div>
                                    <? $text = preg_replace('/#([\w|\p{L}]+)/u', '<a class="hash-tag" href="#">#\1</a>', nl2br($entry->text)) ?>
                                    <? $text = preg_replace('/^(https?:\/\/)?([\da-z\.-]+)\.([a-z\.]{2,6})([\/\w \.-]*)*\/?$/', '<a href="\0">\0</a>', $text) ?>
                                    <p><?= $text ?></p>
                                    <? if (count($entry->attachments)): ?>
                                    <div class="attachments">
                                        <div class="media-prev"
                                             data-first-row-height-percent="<?= $entry->attachment_preview_settings['first_row_height_percent'] ?>"
                                             data-secondary-rows-height-percent="<?= $entry->attachment_preview_settings['secondary_rows_height_percent'] ?>"
                                             data-visible-row-count="<?= $entry->attachment_preview_settings['visible_row_count'] ?>"
                                         >
                                            <? $withoutPreview = []; ?>
                                            <? foreach ($entry->attachments as $attachment): ?>
                                                <? $attachmentType = \App\Models\Entry::getAttachmentType($attachment) ?>
                                                <? if ($attachmentType == \App\Models\Entry::ATTACHMENT_TYPE_IMAGE): ?>
                                                    <div class="item">
                                                        <a class="fancybox" 
                                                           data-original-url="<?= url('attachments/show-original-image/'.$entry->id.'/'.rawurlencode($attachment)) ?>" 
                                                           data-fancybox-group="<?= $entry->id ?>" href="<?= url('entry_attachments/'.$entry->id.'/resized_images/'.rawurlencode($attachment)) ?>">
                                                            <img class="" src="<?= url('entry_attachments/'.$entry->id.'/images_preview/'.rawurlencode($attachment)) ?>">
                                                        </a>
                                                    </div>
                                                <? elseif ($attachmentType == \App\Models\Entry::ATTACHMENT_TYPE_VIDEO): ?>
                                                    <div class="item">
                                                        <a class="open-video" href="#" id="<?= md5($attachment) ?>">
                                                            <img src="<?= url('entry_attachments/'.$entry->id.'/video_thumbs/'.rawurlencode($attachment)) ?>">
                                                        </a>
                                                        <div class="player" data-trigger="<?= md5($attachment) ?>" data-url="<?= url('attachments/'.$entry->id.'/'.rawurlencode($attachment).'/bitrates.m3u8') ?>"></div>
                                                    </div>
                                                <? else: ?>
                                                    <? $withoutPreview[] = $attachment; ?>
                                                <? endif ?>
                                            <? endforeach ?>
                                        </div>
                                        <div class="show-all-attachments">
                                            <a href="#" class="show-all"><img src="<?= url('assets/images/arrow_bottom.png') ?>" title="Показать все" /></a>
                                            <a href="#" class="hide-all"><img src="<?= url('assets/images/arrow_top.png') ?>" title="Скрыть"/></a>
                                        </div>
                                        <div class="without-prev">
                                            <? foreach ($withoutPreview as $attachment): ?>
                                                <? if (\App\Models\Entry::getAttachmentType($attachment) == \App\Models\Entry::ATTACHMENT_TYPE_AUDIO): ?>
                                                    <div class="audio">
                                                        <audio controls="true" preload="none">
                                                            <source src="<?= url('entry_attachments/'.$entry->id.'/'.rawurlencode($attachment)) ?>" type="audio/mpeg">
                                                        </audio>
                                                        <span><?= $attachment ?></span>
                                                    </div>
                                                <? else: ?>
                                                    <a href="<?= url('entry_attachments/'.$entry->id.'/'.rawurlencode($attachment)) ?>"><?= $attachment ?></a>
                                                <? endif ?>
                                            <? endforeach ?>
                                        </div>
                                    </div>
                                    <? endif ?>
                                </div>
                            </div>
                        </div>
                    <? endforeach ?>
                
                    <div class="load-more">
                        <? if ($show_load_more_button): ?>
                            <a href="<?= url('entries?p='.($current_page + 1).'&search='.$search_criteria) ?>" class="btn">Еще записи</a>
                        <? endif ?>
                    </div>
                <? else: ?>
                    <div class="empty-list">
                        <p class="hint">Записи не найдены</p>
                    </div>
                <? endif ?>
            </div>
        </div>
    </div>
<? endblock() ?>
