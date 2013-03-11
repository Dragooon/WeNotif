/**
 * WeNotif's JS UI file
 * 
 * @package Dragooon:WeNotif
 * @author Shitiz "Dragooon" Garg <Email mail@dragooon.net> <Url http://smf-media.com>
 * @copyright 2012-2013, Shitiz "Dragooon" Garg <mail@dragooon.net>
 * @license
 *      Licensed under "New BSD License (3-clause version)"
 *      http://www.opensource.org/licenses/BSD-3-Clause
 * @version 1.0
 */

 $(function()
 {
    var $shade = $("#notification_shade").remove().appendTo(document.body).hide();
    $shade.find('.template').hide();

    var $is_open = false;
    $('#notification_handle').click(function()
    {
        if ($is_open)
        {
            $is_open = false;

            $shade.fadeOut();

            return true;
        }

        $is_open = true;
        var $offset = $(this).offset();
        $offset.top += $(this).height();
        $offset.left -= $(this).width() / 2;
        $shade
            .offset($offset)
            .fadeIn();
    });

    var updateNotification = function(data)
    {
        $.each(data.notifications, function(index, item)
        {
            var $template = $shade.find('.template').clone().show();
            $template.removeClass('template');

            $template.prop('data-url', we_script + '?action=notification;area=redirect;id=' + item.id);
            $template.find('.notification_text').html(item.text);
            $template.find('.notification_time').html(item.time);

            $template
                .hover(function()
                {
                    $(this).addClass('windowbg2');
                }, function()
                {
                    $(this).removeClass('windowbg2');
                })
                .click(function()
                {
                    document.location = $template.prop('data-url');
                });

            $template.appendTo($shade.find('.template').parent());
        });
    };

    updateNotification($notifications);
 });