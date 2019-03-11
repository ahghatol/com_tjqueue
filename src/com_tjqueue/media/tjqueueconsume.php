<?php
/**
 * @package     Techjoomla.Tjqueue
 * @subpackage  TJQueue
 * @author      Techjoomla <extensions@techjoomla.com>
 * @copyright   Copyright (c) 2009-2019 TechJoomla. All rights reserved.
 * @license     GNU General Public License version 2 or later.
 */

namespace Media\TJQueue;

// Doctrine
use Enqueue\Dbal\DbalConnectionFactory;
use Enqueue\Dbal\DbalMessage;

// SQS
use Enqueue\Sqs\SqsConnectionFactory;

// Joomla component helper to get params
use Joomla\CMS\Component\ComponentHelper;

defined('JPATH_PLATFORM') or die;
jimport('joomla.filesystem.folder');
jimport('joomla.application.component.helper');

/**
 * TJQueue handler
 *
 * @package     Techjoomla.Tjqueue
 * @subpackage  TJQueue
 * @since       1.0
 */
class TJQueueConsume
{
	private $jconfig;

	private $params;

	private $consumer;

	private $topic;

	private $message;

	/**
	 * TJQueue Class  constructor.
	 *
	 * @param   string  $topic  Name of the topic from which message should be get
	 *
	 * @since   2.1
	 */
	public function __construct($topic)
	{
		$this->topic = $topic;
		$file = JPATH_SITE . '/media/tjqueue/libs/vendor/autoload.php';

		if (file_exists($file))
		{
			require_once $file;
		}
		else
		{
			throw new \LogicException('Composer autoload was not found');
		}

		$this->params  = ComponentHelper::getParams('com_tjqueue');
		$this->jconfig = \JFactory::getConfig();
		$transport     = $this->params->get('transport');

		switch ($transport)
		{
			case "mysql":
				$this->doctrineDbal();
			break;

			case "aws_sqs":
				$this->awsSqs();
			break;
		}
	}

	/**
	 * Init SQS
	 *
	 * @return  void.
	 *
	 * @since 0.0.1
	 */
	private function awsSqs()
	{
		$config = [
			'key' => $this->params->get("aws_key"),
			'secret' => $this->params->get("aws_secret"),
			'region' => $this->params->get("aws_region"),
		];

		$factory        = new SqsConnectionFactory($config);
		$context        = $factory->createContext();
		$queue          = $context->createQueue($this->topic);
		$this->consumer = $context->createConsumer($queue);
	}

	/**
	 * Init doctrine
	 *
	 * @return  void.
	 *
	 * @since 0.0.1
	 */
	private function doctrineDbal()
	{
		$user     = $this->jconfig->get("user");
		$password = $this->jconfig->get("password");
		$host     = $this->jconfig->get("host");
		$db       = $this->jconfig->get("db");
		$url      = "mysql://" . $user . ":" . $password . "@" . $host . "/" . $db;

		$config = [
			'connection' => [
				'url' => $url,
				'driver' => 'pdo_mysql',
			],
		];

		$factory = new DbalConnectionFactory($config);
		$context = $factory->createContext();
		$context->createDataBaseTable();

		$queue          = $context->createTopic($this->topic);
		$this->consumer = $context->createConsumer($queue);
	}

	/**
	 * Method to acknowledge that message processed successfully and can be deleted from queue
	 *
	 * @return  object Message
	 *
	 * @since 1.0
	 */
	public function receive()
	{
		$this->message = $this->consumer->receive(20000);

		return $this->message;
	}

	/**
	 * Method to acknowledge that message processed successfully and can be deleted from queue
	 *
	 * @param   Object  $result  Message
	 *
	 * @return  boolean
	 *
	 * @since 1.0
	 */
	public function acknowledge($result)
	{
		if ($result == true)
		{
			$this->consumer->acknowledge($this->message);
		}

		return true;
	}
}
