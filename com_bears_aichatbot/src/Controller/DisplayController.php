<?php
/**
 * Bridge controller so core dispatcher can resolve base namespace controller
 * and delegate to the Administrator controller.
 */

namespace Joomla\Component\BearsAichatbot\Controller;

\defined('_JEXEC') or die;

class DisplayController extends \Joomla\Component\BearsAichatbot\Administrator\Controller\DisplayController
{
}
