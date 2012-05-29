<?php
/**
 * Template file for notifications
 * 
 * @package Dragooon:WeNotif
 * @author Shitiz "Dragooon" Garg <Email mail@dragooon.net> <Url http://smf-media.com>
 * @copyright 2012, Shitiz "Dragooon" Garg <mail@dragooon.net>
 * @license
 *		Licensed under "New BSD License (3-clause version)"
 *		http://www.opensource.org/licenses/BSD-3-Clause
 * @version 1.0
 */

function template_notifications_block()
{
	global $txt, $context, $settings, $scripturl;

	echo '
	<section>
		<span class="note', $context['unread_notifications'] ? 'nice' : '', '" style="font-size: 9pt;">', $context['unread_notifications'], '</span>&nbsp;
		<a href="', $scripturl, '?action=notifications" class="title">', $txt['notification_unread_title'], '</a>';
	foreach ($context['quick_notifications'] as $notification)
	{
		echo '
			<p class="description" style="font-size: 1em;" onclick="document.location = \'', $scripturl, '?action=notification;area=redirect;id=', $notification->getID(), '\'">
				', $notification->getText(), '<br />
				<span class="smalltext">', timeformat($notification->getTime()), '</span>
			</p>';
	}
	echo '
	</section>';
}
?>