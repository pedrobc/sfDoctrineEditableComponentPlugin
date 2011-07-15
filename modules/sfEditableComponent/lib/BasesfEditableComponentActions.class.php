<?php
/**
 * sfDoctrineEditableComponent base actions
 *
 * @package    sfDoctrineEditableComponent
 * @subpackage action
 * @author     Nicolas Perriault <nperriault@gmail.com>
 */
class BasesfEditableComponentActions extends sfActions
{
  public function preExecute()
  {
    $assetsConfig = sfConfig::get('app_sfDoctrineEditableComponentPlugin_assets', array());
    $this->pluginWebRoot = isset($assetsConfig['web_root']) ? $assetsConfig['web_root'] : '';
    $this->componentCssClassName = sfConfig::get('app_sfDoctrineEditableComponentPlugin_component_css_class_name', 'sfEditableComponent');
    $this->defaultContent = sfConfig::get('app_sfDoctrineEditableComponentPlugin_default_content', 'Edit me');
  }

  public function executeCss(sfWebRequest $request)
  {
    $this->forward404Unless($this->getUser()->hasCredential(sfConfig::get('app_sfDoctrineEditableComponentPlugin_admin_credential', 'editable_content_admin')));
    $this->setLayout(false);
  }

  public function executeJs(sfWebRequest $request)
  {
    $this->useRichEditor = sfConfig::get('app_sfDoctrineEditableComponentPlugin_use_rich_editor', false);
    $this->forward404Unless($this->getUser()->hasCredential(sfConfig::get('app_sfDoctrineEditableComponentPlugin_admin_credential', 'editable_content_admin')));
    $this->setLayout(false);
  }

  /**
   * Retrieves a component content
   *
   * @param  sfWebRequest  $request
   */
  public function executeGet(sfWebRequest $request)
  {
    $result = $error = '';

    if (!$request->hasParameter('name') || !$request->hasParameter('type'))
    {
      $error = 'Missing component name or type values, cannot create nor update component record';
    }
    else
    {
      try
      {
        $component = sfEditableComponentTable::getComponent(
          $name = $request->getParameter('name'),
          $type = $request->getParameter('type')
        );

        $result['title'] = $component->getTitle();
        $result['content'] = $component->getContent();
      }
      catch (Exception $e)
      {
        $this->logMessage(sprintf('{%s} %s', __CLASS__, $e->getMessage()), 'err');

        $error = sprintf('Unable to create nor retrieve component contents for "%s"', $name);
      }
    }

    return $this->renderResult($result, $error);
  }

  /**
   * Update an editable component
   *
   * @param sfRequest $request A request object
   */
  public function executeUpdate(sfWebRequest $request)
  {
    $result = $error = '';

    if (!$this->getUser()->hasCredential(sfConfig::get('app_sfDoctrineEditableComponentPlugin_admin_credential', 'editable_content_admin')))
    {
      $this->getResponse()->setStatusCode(403);

      $error = 'Forbidden';
    }
    else if (!$request->hasParameter('id') || !$request->hasParameter('value'))
    {
      $error = 'Missing parameters';
    }

    // Dispatching of editable_component.filter_contents event
    $result['title'] = $request->getParameter('title');
    $result['content'] = $this->dispatcher->filter(new sfEvent($this, 'editable_component.filter_contents', array(
      'name' => $name = $request->getParameter('id'),
      'type' => $type = $request->getParameter('type', PluginsfEditableComponentTable::DEFAULT_TYPE),
    )), $request->getParameter('value'))->getReturnValue();
    try
    {
      $component = sfEditableComponentTable::updateComponent($name, $result, $type);
    }
    catch (Doctrine_Exception $e)
    {
      $error = sprintf('Unable to update component "%s": %s', $name, $e->getMessage());
    }

    // Dispatching of editable_component.updated event
    $this->dispatcher->notify(new sfEvent($component, 'editable_component.updated', array(
      'view_cache' => $this->context->getViewCacheManager(),
      'culture'    => $this->getUser()->getCulture(),
    )));

    return $this->renderResult($result, $error);
  }

  protected function renderResult($result = null, $error = null)
  {
    return $this->renderText(json_encode(array(
      'error'  => $error,
      'title' => $result['title'],
      'content' => $result['content'],
    )));
  }
}