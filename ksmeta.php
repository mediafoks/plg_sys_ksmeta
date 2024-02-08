<?php

/**
 * @version    1.0.3
 * @package    ksmeta (plugin)
 * @author     Sergey Kuznetsov - mediafoks@google.com
 * @copyright  Copyright (c) 2024 Sergey Kuznetsov
 * @license    GNU/GPL license: https://www.gnu.org/copyleft/gpl.html
 */

//kill direct access
defined('_JEXEC') || die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Factory;
use Joomla\Event\SubscriberInterface;

class PlgSystemKsMeta extends CMSPlugin implements SubscriberInterface
{
    protected $autoloadLanguage = true;
    protected $allowLegacyListeners = false;

    public static function getSubscribedEvents(): array
    {
        return [
            'onBeforeCompileHead' => 'onBeforeCompileHead',
        ];
    }

    public function renderMeta($item): void
    {
        $doc = Factory::getDocument();
        $head = $doc->getHeadData();

        !empty($item->titleprefix) ? $head['title'] = $item->titleprefix . $head['title'] : $head['title'] = $head['title'];
        !empty($item->titlesuffix) ? $head['title'] = $head['title'] . $item->titlesuffix : $head['title'] = $head['title'];
        !empty($item->description) && !is_null($head['description']) ? $head['description'] = $head['description'] . $item->description : $head['description'] = $head['description'];

        $doc->setHeadData($head);
    }

    public function onBeforeCompileHead(): void
    {
        $app = Factory::getApplication();

        if (!$app->isClient('site')) { // если это не фронтэнд, то прекращаем работу
            return;
        }

        $view = $app->input->get('view');

        $articleParams = $this->params->get('article');
        $categoryParams = $this->params->get('category');

        if ($view == 'article' && !empty($articleParams)) {
            $article_id = $app->getInput()->get('id');

            $model = $app->bootComponent('com_content')
                ->getMVCFactory()
                ->createModel('Article', 'Site', ['ignore_request' => false]);
            $article = $model->getItem((int) $article_id);
            $current_category_id = $article->catid;
            $parent_current_category_id = $article->parent_id;

            foreach ($articleParams as $item) {

                if (isset($item->catid) && intval($item->subcategories) == 1 && in_array($parent_current_category_id, $item->catid)) {
                    $this->renderMeta($item);
                }
                if (isset($item->catid) && in_array($current_category_id, $item->catid)) {
                    $this->renderMeta($item);
                }
            }
        } elseif ($view == 'category' && $app->input->get('option') != 'com_contact' && !empty($categoryParams)) {
            $model = $app->bootComponent('com_content')
                ->getMVCFactory()
                ->createModel('Category', 'Site', ['ignore_request' => false]);
            $category = $model->getCategory();
            $parent_category_id = $category->get('_parent')->id;

            foreach ($categoryParams as $item) {
                if (isset($item->catid) && in_array($parent_category_id, $item->catid)) {
                    $this->renderMeta($item);
                }
            }
        }
    }
}
