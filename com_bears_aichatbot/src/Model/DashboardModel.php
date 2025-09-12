<?php
/**
 * Dashboard model (base namespace copy to avoid admin namespace conflicts)
 */

namespace Joomla\Component\BearsAichatbot\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ListModel;

class DashboardModel extends ListModel
{
    public function getFilters(): array
    {
        $app = Factory::getApplication();
        $input = $app->input;
        return [
            'from' => $input->getString('from', ''),
            'to'   => $input->getString('to', ''),
            'group'=> $input->getCmd('group', 'day'),
            'module_id' => $input->getInt('module_id', 0),
            'model'     => $input->getString('model', ''),
            'collection_id' => $input->getString('collection_id', ''),
        ];
    }
}
