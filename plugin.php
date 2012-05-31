<?php
/**
 * WeNotif's main plugin file
 * 
 * @package Dragooon:WeNotif
 * @author Shitiz "Dragooon" Garg <Email mail@dragooon.net> <Url http://smf-media.com>
 * @copyright 2012, Shitiz "Dragooon" Garg <mail@dragooon.net>
 * @license
 *		Licensed under "New BSD License (3-clause version)"
 *		http://www.opensource.org/licenses/BSD-3-Clause
 * @version 1.0
 */

if (!defined('WEDGE'))
	die('File cannot be requested directly');

/**
 * Class for handling notification hooks and actions
 */
class WeNotif
{
	protected static $notifiers = array();
	protected static $quick_count = 5;

	/**
	 * Returns the notifiers
	 *
	 * @static
	 * @access public
	 * @param string $notifier If specified, only returns this notifier
	 * @return array
	 */
	public static function getNotifiers($notifier = null)
	{
		return !empty($notifier) ? self::$notifiers[$notifier] : self::$notifiers;
	}

	/**
	 * Hook callback for load_theme, calls notification_callback hook for registering notification hooks
	 * Also loads notification for this user's quick view
	 *
	 * @static
	 * @access public
	 * @return void
	 */
	public static function hook_load_theme()
	{
		global $context, $user_info, $scripturl, $txt;

		// Register the notifiers
		call_hook('notification_callback', array(&self::$notifiers));

		foreach (self::$notifiers as $notifier => $object)
			if (!($object instanceof Notifier))
				unset(self::$notifiers[$notifier]);

		loadPluginLanguage('Dragooon:WeNotif', 'languages/plugin');

		// Load quick notifications
		$context['quick_notifications'] = array();
		if (!empty($user_info['id']))
		{
			$notifications = cache_get_data('quick_notification_' . $user_info['id'], 86400);

			if ($notifications == null)
			{
				$context['quick_notifications'] = Notification::get(null, $user_info['id'], self::$quick_count, true);

				// Cache it
				cache_put_data('quick_notification_' . $user_info['id'], $context['quick_notifications'], 86400);
			}
			else
				$context['quick_notifications'] = $notifications;
		
			// Get the unread count
			$request = wesql::query('
				SELECT unread_notifications
				FROM {db_prefix}members
				WHERE id_member = {int:member}
				LIMIT 1',
				array(
					'member' => $user_info['id'],
				)
			);
			list ($context['unread_notifications']) = wesql::fetch_row($request);
			wesql::free_result($request);

			loadPluginTemplate('Dragooon:WeNotif', 'templates/plugin');

			wetem::before('sidebar', 'notifications_block');
		}
	}

	/**
	 * Handles the notification action
	 *
	 * @static
	 * @access public
	 * @return void
	 */
	public static function action()
	{
		global $context, $user_info;

		$area = !empty($_REQUEST['area']) ? $_REQUEST['area'] : '';

		if ($area == 'redirect')
		{
			// We are accessing a notification and redirecting to it's target
			list ($notification) = Notification::get((int) $_REQUEST['id'], $user_info['id']);

			// Not found?
			if (empty($notification))
				fatal_lang_error('notification_not_found');

			// Mark this as read
			$notification->markAsRead();

			// Redirect to the target
			redirectexit($notification->getURL());
		}

		// Otherwise we're displaying all the notifications this user has
		$context['notifications'] = Notification::get(null, $user_info['id'], 0);

		wetem::load('notifications_list');
	}

	/**
	 * Hook callback for "profile_areas"
	 *
	 * @access public
	 * @param array &$profile_areas
	 * @return void
	 */
	public function hook_profile_areas(&$profile_areas)
	{
		global $scripturl, $txt, $context;

		$profile_areas['edit_profile']['areas']['notifications'] = array(
			'label' => $txt['notifications'],
			'enabled' => true,
			'function' => 'WeNotif_profile',
			'permission' => array(
				'own' => array('profile_extra_own'),
			),
		);
	}

	/**
	 * Handles our profile area
	 *
	 * @access public
	 * @param int $memID
	 * @return void
	 */
	public function profile($memID)
	{
		global $context, $txt, $user_info, $scripturl;

		// Not the same user? hell no
		if ($memID != $user_info['id'])
			fatal_lang_error('access_denied');
		
		$notifiers = self::getNotifiers();

		if (!empty($_POST['save']))
		{
			$_POST['disabled_notifiers'] = (array) $_POST['disabled_notifiers'];
			foreach ($_POST['disabled_notifiers'] as $k => $v)
				if (!in_array($v, array_keys($notifiers)))
					unset ($_POST['disabled_notifiers'][$k]);
			
			updateMemberData($user_info['id'], array(
				'disabled_notifiers' => implode(',', $_POST['disabled_notifiers']),
			));

			redirectexit('action=profile;area=notifications');
		}

		$context['notifiers'] = $notifiers;

 		$request = wesql::query('
 			SELECT disabled_notifiers
 			FROM {db_prefix}members
 			WHERE id_member = {int:member}
 			LIMIT 1',
 			array(
	 			'member' => $user_info['id'],
	 		)
	 	);
	 	list ($disabled_notifiers) = wesql::fetch_row($request);
	 	wesql::free_result($request);

		$context['disabled_notifiers'] = explode(',', $disabled_notifiers);

		wetem::load('wenotif_profile');
	}

	/**
	 * Handles routinely pruning notifications older than x days
	 *
	 * @static
	 * @access public
	 * @return void
	 */
	public function scheduled()
	{
		global $settings;

		wesql::query('
			DELETE FROM {db_prefix}notifications
			WHERE unread = 0
				AND time < {int:time}',
			array(
				'time' => time() - ($settings['notifications_prune_days'] * 86400),
			)
		);
	}
}

function WeNotif_profile($memID)
{
	return WeNotif::profile($memID);
}

function scheduled_notification_prune()
{
	return WeNotif::scheduled();
}

/**
 * Notifier interface, every notifier adding their own stuff must implement this interface
 */
interface Notifier
{
	/**
	 * Callback for getting the URL of the object
	 *
	 * @access public
	 * @param Notification $notification
	 * @return string A fully qualified HTTP URL
	 */
	public function getURL(Notification $notification);

	/**
	 * Callback for getting the text to display on the notification screen
	 *
	 * @access public
	 * @param Notification $notification
	 * @return string The text this notification wants to display
	 */
	public function getText(Notification $notification);

	/**
	 * Returns the name of this notifier
	 *
	 * @access public
	 * @return string
	 */
	public function getName();

	/**
	 * Callback for handling multiple notifications on the same object
	 *
	 * @access public
	 * @param Notification $notification
	 * @param array &$data Reference to the new notification's data, if something needs to be altered
	 * @return bool, if false then a new notification is not created but the current one's time is updated
	 */
	public function handleMultiple(Notification $notification, array &$data);

	/**
	 * Returns the title and description of the notifier for the profile area in order to disable/enable them
	 *
	 * @access public
	 * @return array(title, description)
	 */
	public function getProfileDesc();
}

/**
 * For each individual notification
 * The aim for abstracting this into a class is mainly for readibility and sensibility
 * Passing arrays is a tad more confusing and error-prone
 */
class Notification
{
	/**
	 * Stores the basic notification information
	 */
	protected $id;
	protected $id_member;
	protected $notifier;
	protected $id_object;
	protected $time;
	protected $unread;
	protected $data;

	/**
	 * Gets the notifications
	 *
	 * @static
	 * @access public
	 * @param int $id If specified, then fetches the notification of this ID
	 * @param int $id_member If specified, then fetches the notification of this member
	 * @param int $count 0 for no limiit
	 * @param bool $unread (Optional) Whether to fetch only unread notifications or not
	 * @param int $object (Optional) If specified, limits it down to one object
	 * @param string $notifier (Optional) If specified, limits it down to the notifier
	 * @return array
	 */
	public static function get($id = null, $id_member = null, $count = 1, $unread = false, $object = null, $notifier = '')
	{
		if (empty($id) && empty($id_member))
			return array();

		$request = wesql::query('
			SELECT *
			FROM {db_prefix}notifications
			WHERE ' . (!empty($id) ? 'id_notification = {int:id}' : '1=1') . (!empty($id_member) ? '
				AND id_member = {int:member}' : '') . ($unread ? '
				AND unread = 1' : '') . (!empty($object) ? '
				AND id_object = {int:object}' : '') . (!empty($notifier) ? '
				AND notifier = {string:notifier}' : '') . '
			ORDER BY time DESC' . (!empty($count) ? '
			LIMIT {int:count}' : ''),
			array(
				'id' => (int) $id,
				'member' => (int) $id_member,
				'count' => (int) $count,
				'object' => (int) $object,
				'notifier' => $notifier,
			));
		return self::fetchNotifications($request);
	}

	/**
	 * Fetches notifications from a query and arranges them in an array
	 *
	 * @static
	 * @access protected
	 * @return array
	 */
	protected static function fetchNotifications($request)
	{
		$notifications = array();
		$notifiers = WeNotif::getNotifiers();

		while ($row = wesql::fetch_assoc($request))
		{
			// Make sure the notifier for this exists
			if (!isset($notifiers[$row['notifier']]))
				continue;
					
			$notifications[] = new Notification($row, $notifiers[$row['notifier']]);
		}

		wesql::free_result($request);

		return $notifications;
	}

	/**
	 * Marks notification as read for a specific member, notifier and object
	 *
	 * @static
	 * @access public
	 * @param int $id_member
	 * @param Notifier $notifier
	 * @param int $id_object
	 * @return void
	 */
	public static function markReadForNotifier($id_member, Notifier $notifier, $id_object)
	{
		// Oh goody, we have stuff to mark as unread
		wesql::query('
			UPDATE {db_prefix}notifications
			SET unread = 0
			WHERE id_member = {int:member}
				AND id_object = {int:object}
				AND notifier = {string:notifier}
				AND unread = 1',
			array(
				'member' => $id_member,
				'object' => $id_object,
				'notifier' => $notifier->getName(),
			)
		);
		$affected_rows = wesql::affected_rows();

		if ($affected_rows > 0)
		{
			wesql::query('
				UPDATE {db_prefix}members
				SET unread_notifications = unread_notifications - {int:count}
				WHERE id_member = {int:member}',
				array(
					'count' => $affected_rows,
					'member' => (int) $id_member,
				)
			);

			// Flush the cache
			cache_put_data('quick_notification_' . $id_member, array(), 0);
		}
	}

	/**
	 * Issues a new notification to a member, also calls the hook
	 *
	 * @static
	 * @access public
	 * @param int $id_member
	 * @param Notifier $notifier
	 * @param int $id_object
     * @param array $data
     * @return Notification
     * @throws Exception, upon the failure of creating a notification for whatever reason
     */
    public static function issue($id_member, Notifier $notifier, $id_object, $data = array())
    {
    	$id_object = (int) $id_object;
    	if (empty($id_object))
    		throw new Exception('Object cannot be empty for notification');
 
 		// Check for disabled notifications
 		//!!! Speed this thing up, an additional query should preferably be not required
 		$request = wesql::query('
 			SELECT disabled_notifiers
 			FROM {db_prefix}members
 			WHERE id_member = {int:member}
 			LIMIT 1',
 			array(
	 			'member' => $id_member,
	 		)
	 	);
	 	list ($disabled_notifiers) = wesql::fetch_row($request);
	 	wesql::free_result($request);

	 	if (in_array($notifier->getName(), explode(',', $disabled_notifiers)))
	 		return false;

    	// Do we already have a notification from this notifier on this object?
    	$request = wesql::query('
    		SELECT *
    		FROM {db_prefix}notifications
    		WHERE notifier = {string:notifier}
    			AND id_member = {int:member}
    			AND id_object = {int:object}
    			AND unread = 1
    		LIMIT 1',
    		array(
	    		'notifier' => $notifier->getName(),
	    		'object' => $id_object,
	    		'member' => $id_member,
	    	)
	    );
	    // If we do, then we run it by the notifier
	    if (wesql::num_rows($request) > 0)
	    {
	    	$notification = new Notification(wesql::fetch_assoc($request), $notifier);

	    	// If the notifier returns false, we don't create a new notification
	    	if (!$notifier->handleMultiple($notification, &$data))
	    	{
	    		$notification->updateTime();
	    		return $notification;
	    	}
	    }
	    wesql::free_result($request);

    	$time = time();

    	// Create the row
    	wesql::insert('', '{db_prefix}notifications', 
    		array('id_member' => 'int', 'notifier' => 'string-50', 'id_object' => 'int', 'time' => 'int', 'unread' => 'int', 'data' => 'string'),
    		array($id_member, $notifier->getName(), $id_object, $time, 1, serialize((array) $data)),
    		array('id_notification')
    	);
    	$id_notification = wesql::insert_id();

    	if (!empty($id_notification))
    	{
    		// Update the unread notification count
    		wesql::query('
    			UPDATE {db_prefix}members
    			SET unread_notifications = unread_notifications + 1
    			WHERE id_member = {int:member}',
    			array(
	    			'member' => $id_member,
	    		)
	    	);

    		$notification = new self(array(
	    		'id_notification' => $id_notification,
	    		'id_member' => $id_member,
	    		'id_object' => $id_object,
	    		'time' => $time,
	    		'unread' => 1,
	    		'data' => serialize((array) $data),
	    	), $notifier);

	    	call_hook('notification_new', array($notification));

	    	// Flush the cache
	    	cache_put_data('quick_notification_' . $id_member, array(), 0);

	    	return $notification;
    	}
    	else
    		throw new Exception('Unable to create notification');
    }

	/**
	 * Constructor, just initialises the member variables...
	 *
	 * @access public
	 * @param array $row The DB row of this notification (Entirely done in order to prevent typing...)
	 * @param Notifier $notifier The notifier's instance
	 * @return void
	 */
	public function __construct(array $row, Notifier $notifier)
	{
		// Store the data
		$this->id = $row['id_notification'];
		$this->id_member = $row['id_member'];
		$this->notifier = $notifier;
		$this->id_object = $row['id_object'];
		$this->time = (int) $row['time'];
		$this->unread = $row['unread'];
		$this->data = unserialize($row['data']);
	}

	/**
	 * Marks the current notification as read
	 *
	 * @access public
	 * @return void
	 */
	public function markAsRead()
	{
		if ($this->unread == 0)
			return;
		
		$this->unread = 0;
		$this->updateCol('unread', 0);

    	// Update the unread notification count
    	wesql::query('
    		UPDATE {db_prefix}members
    		SET unread_notifications = unread_notifications - 1
    		WHERE id_member = {int:member}',
    		array(
	    		'member' => $this->getMember(),
	    	)
	    );

		// Flush the cache
		cache_put_data('quick_notification_' . $id_member, array(), 0);
	}

	/**
	 * Updates the data of this notification
	 *
	 * @access public
	 * @param array $data
	 * @return void
	 */
	public function updateData(array $data)
	{
		$this->data = (array) $data;
		$this->updateCol('data', serialize((array) $data));
	}

	/**
	 * Updates the time of this notification
	 *
	 * @access public
	 * @return void
	 */
	public function updateTime()
	{
		$this->time = time();
		$this->updateCol('time', time());
	}

	/**
	 * Internal function for updating a column
	 *
	 * @access protected
	 * @param string $column
	 * @param string $value
	 * @return void
	 */
	protected function updateCol($column, $value)
	{
		wesql::query('
			UPDATE {db_prefix}notifications
			SET {raw:column} = {string:value}
			WHERE id_notification = {int:notification}',
			array(
				'column' => addslashes($column),
				'value' => $value,
				'notification' => $this->getID(),
			)
		);
	}

	/**
	 * Returns this notification's ID
	 *
	 * @access public
	 * @return int
	 */
	public function getID()
	{
		return $this->id;
	}

	/**
	 * Returns the text for this notification
	 *
	 * @access public
	 * @return string
	 */
	public function getText()
	{
		return $this->notifier->getText($this);
	}

	/**
	 * Returns the URL for this notification
	 *
	 * @access public
	 * @return string
	 */
	public function getURL()
	{
		return $this->notifier->getURL($this);
	}

	/**
	 * Returns the notifier's object
	 *
	 * @access public
	 * @return object
	 */
	public function getNotifier()
	{
		return $this->notifier;
	}

	/**
	 * Returns this notification's associated object's ID
	 *
	 * @access public
	 * @return int
	 */
	public function getObject()
	{
		return $this->id_object;
	}

	/**
	 * Returns this notification's data
	 *
	 * @access public
	 * @return array
	 */
	public function getData()
	{
		return $this->data;
	}

	/**
	 * Returns this notification's time
	 *
	 * @access public
	 * @return int
	 */
	public function getTime()
	{
		return $this->time;
	}

	/**
	 * Returns this notification's unread status
	 *
	 * @access public
	 * @return int (0, 1)
	 */
	public function getUnread()
	{
		return $this->unread;
	}

	/**
	 * Returns this notification's member's ID
	 *
	 * @access public
	 * @return int
	 */
	public function getMember()
	{
		return $this->id_member;
	}
}
?>