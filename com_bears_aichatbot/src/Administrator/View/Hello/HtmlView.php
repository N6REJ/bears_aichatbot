<?php
/**
 * HtmlView for the Hello view (Administrator)
 */

namespace Joomla\Component\BearsAichatbot\Administrator\View\Hello;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

class HtmlView extends BaseHtmlView
{
    protected $greeting = 'Hello World';

    public function display($tpl = null)
    {
        $this->greeting = 'Hello World';
        return parent::display($tpl);
    }
}
