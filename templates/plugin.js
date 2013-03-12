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

    var $hovering = false,
        $timer = 0,
        $is_open = false;

    $shade.hover(function()
    {
        $hovering = true;
    }, function()
    {
        $hovering = false;
    });

    $(document.body).click(function()
    {
        if (!$hovering && $is_open && +new Date() - $timer > 200)
        {
            $shade.fadeOut('fast');
            $is_open = false;
            $hovering = false;
        }
    });

    $('.notification_trigger').click(function()
    {
        if ($is_open)
        {
            $is_open = false;

            $shade.fadeOut('fast');
        }
        else
        {
            $is_open = true;
            var $offset = $(this).parent().offset();
            $offset.top -= 6;
            $offset.left -= 15;
            $timer = +new Date();

            // Yeah I know I didn't need to set top and left CSS manually
            // but it was adding it to the current offset instead of overwriting it
            // hence on second or third viewing things would get weird
            $shade
                .css('top', $offset.top)
                .css('left', $offset.left)
                .fadeIn('fast');
        }
    });

    var updateNotification = function(data)
    {
        $shade.find('.notification_container > .notification:not(.template)').remove();

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