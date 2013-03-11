<?php
/**
 * Template file for notifications
 * 
 * @package Dragooon:WeNotif
 * @author Shitiz "Dragooon" Garg <Email mail@dragooon.net> <Url http://smf-media.com>
 * @copyright 2012-2013, Shitiz "Dragooon" Garg <mail@dragooon.net>
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
		<header class="title">
			<span class="note', $context['unread_notifications'] ? 'nice' : '', '" style="font-size: 0.9em; cursor: pointer;" id="notification_handle">
			', $context['unread_notifications'], '
			</span>
			&nbsp;', $txt['notifications'], '
		</header>
        <div class="mimenu" id="notification_shade" style="max-width: 250px;">
            <ul class="actions" style="white-space: normal;">
                <li>
                	<header class="title">
                		', $txt['notifications'], '&nbsp;
                		<a href="<URL>?action=notification" style="display: inline; padding: 0; fonnt-size: 0.2em;">(', $txt['view_all'], ')</a>
                	</header>
                	<div class="notification_container" style="max-height: 24em; overflow: auto;">
	                   	<div class="notification template" style="cursor: pointer;">
	                    	<div class="notification_text" style="color: #444; padding-left: 10px; padding-top: 10px;">
	                    	</div>
	                    	<div class="notification_time" class="smalltext" style="text-align: right;">
	                    	</div>
	                    	<hr style="margin: 0;" />
	                    </div>
	                </div>
                </li>
            </ul>
        </div>
	</section>';
}

function template_notifications_list()
{
	global $txt, $context, $settings, $scripturl;

	echo '
		<we:title>', $txt['notifications'], '</we:title>';
	foreach ($context['notifications'] as $notification)
	{
		echo '
			<p class="', $notification->getUnread() ? 'description' : 'wrc windowbg', '  " style="font-size: 1em; cursor: pointer;"
				onclick="document.location = \'', $scripturl, '?action=notification;area=redirect;id=', $notification->getID(), '\'">
				', $notification->getText(), '<br />
				<span class="smalltext">', timeformat($notification->getTime()), '</span>
			</p>';
	}
}

function template_notification_email($notifications)
{
	global $txt;

	$text = $txt['notification_email_periodical_body'] . '<br /><br />';

	foreach ($notifications as $notifier => $notifs)
	{
		$profile = WeNotif::getNotifie($notifier)->getProfile();

		$text .= '
			<h3>' . $profile[0] . '</h3>
			<hr />
			<div style="margin-left: 15px;">';

		foreach ($notifs as $n)
			$text .= '<div>' . $n->getText() . '</div>';

		$text .='
			</div>';
	}

	return $text;
}
?>