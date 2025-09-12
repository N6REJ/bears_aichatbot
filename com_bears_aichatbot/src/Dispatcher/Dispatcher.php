<?php
/**
 * Bridge dispatcher so core factory can resolve base namespace dispatcher
 * and delegate to the Administrator dispatcher.
 */

namespace Joomla\Component\BearsAichatbot\Dispatcher;

\defined('_JEXEC') or die;

class Dispatcher extends \Joomla\Component\BearsAichatbot\Administrator\Dispatcher\Dispatcher
{
}
