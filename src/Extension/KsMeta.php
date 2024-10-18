<?php

/**
 * @version    1.5.0
 * @package    ksmeta (plugin)
 * @author     Sergey Kuznetsov - mediafoks@google.com
 * @copyright  Copyright (c) 2024 Sergey Kuznetsov
 * @license    GNU/GPL license: https://www.gnu.org/copyleft/gpl.html
 */

namespace Joomla\Plugin\System\KsMeta\Extension;
//kill direct access
\defined('_JEXEC') || die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;

final class KsMeta extends CMSPlugin implements SubscriberInterface
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
        $app = $this->getApplication();
        $doc = $app->getDocument();
        $head = $doc->getHeadData();

        isset($item->titleprefix) && !empty($item->titleprefix)
            ? $head['title'] = $item->titleprefix . $head['title']
            : $head['title'] = $head['title'];
        isset($item->titlesuffix) && !empty($item->titlesuffix)
            ? $head['title'] = $head['title'] . $item->titlesuffix
            : $head['title'] = $head['title'];
        isset($item->descriptionprefix) && !empty($item->descriptionprefix) && !is_null($head['description'])
            ? $head['description'] = $item->descriptionprefix . $head['description']
            : $head['description'] = $head['description'];
        isset($item->description) && !empty($item->description) && !is_null($head['description'])
            ? $head['description'] = $head['description'] . $item->description
            : $head['description'] = $head['description'];

        $doc->setHeadData($head);
    }

    public function onBeforeCompileHead(): void
    {
        $app = $this->getApplication();

        if (!$app->isClient('site')) return; // если это не фронтэнд, то прекращаем работу

        $view = $app->input->get('view');

        $articleParams = $this->params->get('article');
        $categoryParams = $this->params->get('category');
        $tagParams = $this->params->get('tag');
        $contactParams = $this->params->get('contact');

        if ($view == 'article' && !empty($articleParams)) {
            $model = $app->bootComponent('com_content')
                ->getMVCFactory()
                ->createModel('Article', 'Site', ['ignore_request' => false]);
            $article_id = $app->getInput()->get('id'); // ID текущего материала
            $article = $model->getItem((int) $article_id); // Материал
            $current_category_id = $article->catid; // ID категории материала

            foreach ($articleParams as $item) {
                if (isset($item->catid) && (int) $item->subcategories == 1) { // Если включены все дочерние категории
                    $categories = $app->bootComponent('com_content')
                        ->getMVCFactory()
                        ->createModel('Categories', 'Site', ['ignore_request' => true]);

                    foreach ($item->catid as $catid) {
                        $categories->setState('filter.parentId', $catid);

                        $items = $categories->getItems(true);
                        $additional_catids = [];

                        foreach ($items as $category) $additional_catids[] = $category->id;

                        if (in_array($current_category_id, $additional_catids)) {
                            $this->renderMeta($item);
                        }
                    }
                }
                if (isset($item->catid) && (int) $item->subcategories != 1 && in_array($current_category_id, $item->catid)) {
                    $this->renderMeta($item);
                }
            }
        } elseif ($view == 'category' && $app->input->get('option') != 'com_contact' && !empty($categoryParams)) {
            $model = $app->bootComponent('com_content')
                ->getMVCFactory()
                ->createModel('Category', 'Site', ['ignore_request' => false]);
            $category = $model->getCategory();
            $category_id = $category->id;

            foreach ($categoryParams as $item) {
                if (isset($item->catid) && (int) $item->subcategories == 1) { // Если включены все дочерние категории
                    $categories = $app->bootComponent('com_content')
                        ->getMVCFactory()
                        ->createModel('Categories', 'Site', ['ignore_request' => true]);

                    foreach ($item->catid as $catid) {
                        $categories->setState('filter.parentId', $catid);

                        $items = $categories->getItems(true);
                        $additional_catids = [];

                        foreach ($items as $category) $additional_catids[] = $category->id;

                        if (in_array($category_id, $additional_catids)) {
                            $this->renderMeta($item);
                        }
                    }
                }
                if (isset($item->catid) && (int) $item->subcategories != 1 && in_array($category_id, $item->catid)) {
                    $this->renderMeta($item);
                }
            }
        } elseif ($view == 'tag' && !empty($tagParams)) {
            $model = $app->bootComponent('com_tags')
                ->getMVCFactory()
                ->createModel('Tag', 'Site', ['ignore_request' => false]);
            $tag_id = $app->getInput()->get('id')[0]; // ID текущего тега
            $tag = $model->getItem((int) $tag_id)[0]; // Тег

            foreach ($tagParams as $item) {
                if (isset($item->parentTag)) {
                    if (in_array($tag_id, $item->parentTag) || in_array($tag->parent_id, $item->parentTag)) {
                        $this->renderMeta($item);
                    }
                }
            }
        } elseif ($view == 'contact') {
            $model = $app->bootComponent('com_contact')
                ->getMVCFactory()
                ->createModel('Contact', 'Site', ['ignore_request' => false]);
            $conract_id = $app->getInput()->get('id'); // ID текущего контакта
            $conract = $model->getItem((int) $conract_id); // Контакт
            $current_category_id = $conract->catid; // ID категории контакта

            foreach ($contactParams as $item) {
                if (isset($item->catid) && in_array($current_category_id, $item->catid)) {
                    $this->renderMeta($item);
                }
            }
        }
    }
}
