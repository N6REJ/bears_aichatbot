<?php
/**
 * Usage model for listing table
 */

namespace Joomla\Component\BearsAichatbot\Administrator\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ListModel;

class UsageModel extends ListModel
{
    protected function populateState($ordering = 'created_at', $direction = 'DESC')
    {
        parent::populateState($ordering, $direction);
        $app = Factory::getApplication();
        $input = $app->input;
        $this->setState('filter.from', $input->getString('from'));
        $this->setState('filter.to', $input->getString('to'));
        $this->setState('filter.module_id', $input->getInt('module_id'));
        $this->setState('filter.model', $input->getString('model'));
        $this->setState('filter.collection_id', $input->getString('collection_id'));
    }

    protected function getListQuery()
    {
        $db = $this->getDbo();
        $q = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__aichatbot_usage'));

        if ($f = $this->getState('filter.from')) { $q->where($db->quoteName('created_at') . ' >= ' . $db->quote($f)); }
        if ($t = $this->getState('filter.to')) { $q->where($db->quoteName('created_at') . ' <= ' . $db->quote($t)); }
        if ($mid = (int)$this->getState('filter.module_id')) { $q->where($db->quoteName('module_id') . ' = ' . $mid); }
        if ($m = $this->getState('filter.model')) { $q->where($db->quoteName('model') . ' = ' . $db->quote($m)); }
        if ($cid = $this->getState('filter.collection_id')) { $q->where($db->quoteName('collection_id') . ' = ' . $db->quote($cid)); }

        $orderCol = $this->state->get('list.ordering', 'created_at');
        $orderDir = $this->state->get('list.direction', 'DESC');
        $q->order($db->escape($orderCol . ' ' . $orderDir));
        return $q;
    }
}
