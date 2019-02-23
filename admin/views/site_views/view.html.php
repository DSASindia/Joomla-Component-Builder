<?php
/**
 * @package    Joomla.Component.Builder
 *
 * @created    30th April, 2015
 * @author     Llewellyn van der Merwe <http://www.joomlacomponentbuilder.com>
 * @github     Joomla Component Builder <https://github.com/vdm-io/Joomla-Component-Builder>
 * @copyright  Copyright (C) 2015 - 2019 Vast Development Method. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

// No direct access to this file
defined('_JEXEC') or die('Restricted access');

/**
 * Componentbuilder View class for the Site_views
 */
class ComponentbuilderViewSite_views extends JViewLegacy
{
	/**
	 * Site_views view display method
	 * @return void
	 */
	function display($tpl = null)
	{
		if ($this->getLayout() !== 'modal')
		{
			// Include helper submenu
			ComponentbuilderHelper::addSubmenu('site_views');
		}

		// Assign data to the view
		$this->items = $this->get('Items');
		$this->pagination = $this->get('Pagination');
		$this->state = $this->get('State');
		$this->user = JFactory::getUser();
		$this->listOrder = $this->escape($this->state->get('list.ordering'));
		$this->listDirn = $this->escape($this->state->get('list.direction'));
		$this->saveOrder = $this->listOrder == 'ordering';
		// set the return here value
		$this->return_here = urlencode(base64_encode((string) JUri::getInstance()));
		// get global action permissions
		$this->canDo = ComponentbuilderHelper::getActions('site_view');
		$this->canEdit = $this->canDo->get('core.edit');
		$this->canState = $this->canDo->get('core.edit.state');
		$this->canCreate = $this->canDo->get('core.create');
		$this->canDelete = $this->canDo->get('core.delete');
		$this->canBatch = $this->canDo->get('core.batch');

		// We don't need toolbar in the modal window.
		if ($this->getLayout() !== 'modal')
		{
			$this->addToolbar();
			$this->sidebar = JHtmlSidebar::render();
			// load the batch html
			if ($this->canCreate && $this->canEdit && $this->canState)
			{
				$this->batchDisplay = JHtmlBatch_::render();
			}
		}
		
		// Check for errors.
		if (count($errors = $this->get('Errors')))
		{
			throw new Exception(implode("\n", $errors), 500);
		}

		// Display the template
		parent::display($tpl);

		// Set the document
		$this->setDocument();
	}

	/**
	 * Setting the toolbar
	 */
	protected function addToolBar()
	{
		JToolBarHelper::title(JText::_('COM_COMPONENTBUILDER_SITE_VIEWS'), 'palette');
		JHtmlSidebar::setAction('index.php?option=com_componentbuilder&view=site_views');
		JFormHelper::addFieldPath(JPATH_COMPONENT . '/models/fields');

		if ($this->canCreate)
		{
			JToolBarHelper::addNew('site_view.add');
		}

		// Only load if there are items
		if (ComponentbuilderHelper::checkArray($this->items))
		{
			if ($this->canEdit)
			{
				JToolBarHelper::editList('site_view.edit');
			}

			if ($this->canState)
			{
				JToolBarHelper::publishList('site_views.publish');
				JToolBarHelper::unpublishList('site_views.unpublish');
				JToolBarHelper::archiveList('site_views.archive');

				if ($this->canDo->get('core.admin'))
				{
					JToolBarHelper::checkin('site_views.checkin');
				}
			}

			// Add a batch button
			if ($this->canBatch && $this->canCreate && $this->canEdit && $this->canState)
			{
				// Get the toolbar object instance
				$bar = JToolBar::getInstance('toolbar');
				// set the batch button name
				$title = JText::_('JTOOLBAR_BATCH');
				// Instantiate a new JLayoutFile instance and render the batch button
				$layout = new JLayoutFile('joomla.toolbar.batch');
				// add the button to the page
				$dhtml = $layout->render(array('title' => $title));
				$bar->appendButton('Custom', $dhtml, 'batch');
			}

			if ($this->state->get('filter.published') == -2 && ($this->canState && $this->canDelete))
			{
				JToolbarHelper::deleteList('', 'site_views.delete', 'JTOOLBAR_EMPTY_TRASH');
			}
			elseif ($this->canState && $this->canDelete)
			{
				JToolbarHelper::trash('site_views.trash');
			}

			if ($this->canDo->get('core.export') && $this->canDo->get('site_view.export'))
			{
				JToolBarHelper::custom('site_views.exportData', 'download', '', 'COM_COMPONENTBUILDER_EXPORT_DATA', true);
			}
		}
		if ($this->user->authorise('site_view.get_snippets', 'com_componentbuilder'))
		{
			// add Get Snippets button.
			JToolBarHelper::custom('site_views.getSnippets', 'search', '', 'COM_COMPONENTBUILDER_GET_SNIPPETS', false);
		}

		if ($this->canDo->get('core.import') && $this->canDo->get('site_view.import'))
		{
			JToolBarHelper::custom('site_views.importData', 'upload', '', 'COM_COMPONENTBUILDER_IMPORT_DATA', false);
		}

		// set help url for this view if found
		$help_url = ComponentbuilderHelper::getHelpUrl('site_views');
		if (ComponentbuilderHelper::checkString($help_url))
		{
				JToolbarHelper::help('COM_COMPONENTBUILDER_HELP_MANAGER', false, $help_url);
		}

		// add the options comp button
		if ($this->canDo->get('core.admin') || $this->canDo->get('core.options'))
		{
			JToolBarHelper::preferences('com_componentbuilder');
		}

		if ($this->canState)
		{
			JHtmlSidebar::addFilter(
				JText::_('JOPTION_SELECT_PUBLISHED'),
				'filter_published',
				JHtml::_('select.options', JHtml::_('jgrid.publishedOptions'), 'value', 'text', $this->state->get('filter.published'), true)
			);
			// only load if batch allowed
			if ($this->canBatch)
			{
				JHtmlBatch_::addListSelection(
					JText::_('COM_COMPONENTBUILDER_KEEP_ORIGINAL_STATE'),
					'batch[published]',
					JHtml::_('select.options', JHtml::_('jgrid.publishedOptions', array('all' => false)), 'value', 'text', '', true)
				);
			}
		}

		JHtmlSidebar::addFilter(
			JText::_('JOPTION_SELECT_ACCESS'),
			'filter_access',
			JHtml::_('select.options', JHtml::_('access.assetgroups'), 'value', 'text', $this->state->get('filter.access'))
		);

		if ($this->canBatch && $this->canCreate && $this->canEdit)
		{
			JHtmlBatch_::addListSelection(
				JText::_('COM_COMPONENTBUILDER_KEEP_ORIGINAL_ACCESS'),
				'batch[access]',
				JHtml::_('select.options', JHtml::_('access.assetgroups'), 'value', 'text')
			);
		}

		// Set Main Get Name Selection
		$this->main_getNameOptions = JFormHelper::loadFieldType('Maingets')->options;
		if ($this->main_getNameOptions)
		{
			// Main Get Name Filter
			JHtmlSidebar::addFilter(
				'- Select '.JText::_('COM_COMPONENTBUILDER_SITE_VIEW_MAIN_GET_LABEL').' -',
				'filter_main_get',
				JHtml::_('select.options', $this->main_getNameOptions, 'value', 'text', $this->state->get('filter.main_get'))
			);

			if ($this->canBatch && $this->canCreate && $this->canEdit)
			{
				// Main Get Name Batch Selection
				JHtmlBatch_::addListSelection(
					'- Keep Original '.JText::_('COM_COMPONENTBUILDER_SITE_VIEW_MAIN_GET_LABEL').' -',
					'batch[main_get]',
					JHtml::_('select.options', $this->main_getNameOptions, 'value', 'text')
				);
			}
		}

		// Set Add Php Ajax Selection
		$this->add_php_ajaxOptions = $this->getTheAdd_php_ajaxSelections();
		if ($this->add_php_ajaxOptions)
		{
			// Add Php Ajax Filter
			JHtmlSidebar::addFilter(
				'- Select '.JText::_('COM_COMPONENTBUILDER_SITE_VIEW_ADD_PHP_AJAX_LABEL').' -',
				'filter_add_php_ajax',
				JHtml::_('select.options', $this->add_php_ajaxOptions, 'value', 'text', $this->state->get('filter.add_php_ajax'))
			);

			if ($this->canBatch && $this->canCreate && $this->canEdit)
			{
				// Add Php Ajax Batch Selection
				JHtmlBatch_::addListSelection(
					'- Keep Original '.JText::_('COM_COMPONENTBUILDER_SITE_VIEW_ADD_PHP_AJAX_LABEL').' -',
					'batch[add_php_ajax]',
					JHtml::_('select.options', $this->add_php_ajaxOptions, 'value', 'text')
				);
			}
		}

		// Set Add Custom Button Selection
		$this->add_custom_buttonOptions = $this->getTheAdd_custom_buttonSelections();
		if ($this->add_custom_buttonOptions)
		{
			// Add Custom Button Filter
			JHtmlSidebar::addFilter(
				'- Select '.JText::_('COM_COMPONENTBUILDER_SITE_VIEW_ADD_CUSTOM_BUTTON_LABEL').' -',
				'filter_add_custom_button',
				JHtml::_('select.options', $this->add_custom_buttonOptions, 'value', 'text', $this->state->get('filter.add_custom_button'))
			);

			if ($this->canBatch && $this->canCreate && $this->canEdit)
			{
				// Add Custom Button Batch Selection
				JHtmlBatch_::addListSelection(
					'- Keep Original '.JText::_('COM_COMPONENTBUILDER_SITE_VIEW_ADD_CUSTOM_BUTTON_LABEL').' -',
					'batch[add_custom_button]',
					JHtml::_('select.options', $this->add_custom_buttonOptions, 'value', 'text')
				);
			}
		}
	}

	/**
	 * Method to set up the document properties
	 *
	 * @return void
	 */
	protected function setDocument()
	{
		if (!isset($this->document))
		{
			$this->document = JFactory::getDocument();
		}
		$this->document->setTitle(JText::_('COM_COMPONENTBUILDER_SITE_VIEWS'));
		$this->document->addStyleSheet(JURI::root() . "administrator/components/com_componentbuilder/assets/css/site_views.css", (ComponentbuilderHelper::jVersion()->isCompatible('3.8.0')) ? array('version' => 'auto') : 'text/css');
	}

	/**
	 * Escapes a value for output in a view script.
	 *
	 * @param   mixed  $var  The output to escape.
	 *
	 * @return  mixed  The escaped value.
	 */
	public function escape($var)
	{
		if(strlen($var) > 50)
		{
			// use the helper htmlEscape method instead and shorten the string
			return ComponentbuilderHelper::htmlEscape($var, $this->_charset, true);
		}
		// use the helper htmlEscape method instead.
		return ComponentbuilderHelper::htmlEscape($var, $this->_charset);
	}

	/**
	 * Returns an array of fields the table can be sorted by
	 *
	 * @return  array  Array containing the field name to sort by as the key and display text as value
	 */
	protected function getSortFields()
	{
		return array(
			'a.sorting' => JText::_('JGRID_HEADING_ORDERING'),
			'a.published' => JText::_('JSTATUS'),
			'a.system_name' => JText::_('COM_COMPONENTBUILDER_SITE_VIEW_SYSTEM_NAME_LABEL'),
			'a.name' => JText::_('COM_COMPONENTBUILDER_SITE_VIEW_NAME_LABEL'),
			'a.description' => JText::_('COM_COMPONENTBUILDER_SITE_VIEW_DESCRIPTION_LABEL'),
			'g.name' => JText::_('COM_COMPONENTBUILDER_SITE_VIEW_MAIN_GET_LABEL'),
			'a.context' => JText::_('COM_COMPONENTBUILDER_SITE_VIEW_CONTEXT_LABEL'),
			'a.id' => JText::_('JGRID_HEADING_ID')
		);
	}

	protected function getTheAdd_php_ajaxSelections()
	{
		// Get a db connection.
		$db = JFactory::getDbo();

		// Create a new query object.
		$query = $db->getQuery(true);

		// Select the text.
		$query->select($db->quoteName('add_php_ajax'));
		$query->from($db->quoteName('#__componentbuilder_site_view'));
		$query->order($db->quoteName('add_php_ajax') . ' ASC');

		// Reset the query using our newly populated query object.
		$db->setQuery($query);

		$results = $db->loadColumn();

		if ($results)
		{
			// get model
			$model = $this->getModel();
			$results = array_unique($results);
			$_filter = array();
			foreach ($results as $add_php_ajax)
			{
				// Translate the add_php_ajax selection
				$text = $model->selectionTranslation($add_php_ajax,'add_php_ajax');
				// Now add the add_php_ajax and its text to the options array
				$_filter[] = JHtml::_('select.option', $add_php_ajax, JText::_($text));
			}
			return $_filter;
		}
		return false;
	}

	protected function getTheAdd_custom_buttonSelections()
	{
		// Get a db connection.
		$db = JFactory::getDbo();

		// Create a new query object.
		$query = $db->getQuery(true);

		// Select the text.
		$query->select($db->quoteName('add_custom_button'));
		$query->from($db->quoteName('#__componentbuilder_site_view'));
		$query->order($db->quoteName('add_custom_button') . ' ASC');

		// Reset the query using our newly populated query object.
		$db->setQuery($query);

		$results = $db->loadColumn();

		if ($results)
		{
			// get model
			$model = $this->getModel();
			$results = array_unique($results);
			$_filter = array();
			foreach ($results as $add_custom_button)
			{
				// Translate the add_custom_button selection
				$text = $model->selectionTranslation($add_custom_button,'add_custom_button');
				// Now add the add_custom_button and its text to the options array
				$_filter[] = JHtml::_('select.option', $add_custom_button, JText::_($text));
			}
			return $_filter;
		}
		return false;
	}
}
