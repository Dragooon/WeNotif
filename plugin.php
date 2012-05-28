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
    		updateMemberData($id_member, array('unread_notifications' => '+'));

    		$notification = new self(array(
	    		'id_notification' => $id_notification,
	    		'id_member' => $id_member,
	    		'id_object' => $id_object,
	    		'time' => $time,
	    		'unread' => 1,
	    		'data' => serialize((array) $data),
	    	), $notifier);

	    	call_hook('notification_new', array($notification));

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