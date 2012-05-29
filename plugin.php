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
	 * @return array
	 */
	public static function getNotifiers()
	{
		return self::$notifiers;
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

		// Load quick notifications
		$context['quick_notifications'] = array();
		if (!empty($user_info['id']))
		{
			$notifications = cache_get_data('quick_notification_' . $user_info['id'], 86400);

			if ($notifications == null)
			{
				$request = wesql::query('
					SELECT *
					FROM {db_prefix}notifications
					WHERE id_member = {int:member}
					ORDER BY time DESC
					LIMIT {int:count}',
					array(
						'count' => self::$quick_count,
						'member' => $user_info['id'],
					)
				);
				while ($row = wesql::fetch_assoc($request))
				{
					// Make sure the notifier for this exists
					if (!isset(self::$notifiers[$row['notifier']]))
						continue;
					
					$context['quick_notifications'][] = new Notification($row, self::$notifiers[$row['notifier']]);
				}
				wesql::free_result($request);

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
			loadPluginLanguage('Dragooon:WeNotif', 'languages/plugin');

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
			$request = wesql::query('
				SELECT *
				FROM {db_prefix}notifications
				WHERE id_member = {int:member}
					AND id_notification = {int:notification}
				LIMIT 1',
				array(
					'member' => $user_info['id'],
					'notification' => (int) $_REQUEST['id'],
				)
			);

			// Not found?
			if (wesql::num_rows($request) == 0)
				fatal_lang_error('notification_not_found');
			
			$notification = wesql::fetch_assoc($request);
			$notification = new Notification($notification, self::$notifiers[$notification['notifier']]);

			// Mark this as read
			$notification->markAsRead();

			// Redirect to the target
			redirectexit($notification->getURL());
		}
	} 
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
	public function getUnead()
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